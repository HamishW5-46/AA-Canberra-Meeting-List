<?php

// ajax functions


// ajax for the location typeahead on the meeting edit page
add_action('wp_ajax_tsml_locations', function () {
    tsml_require_meetings_permission();
    $locations = tsml_get_locations();
    $results = [];
    foreach ($locations as $location) {
        $results[] = [
            'value' => html_entity_decode($location['location']),
            'formatted_address' => $location['formatted_address'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'region' => $location['region_id'],
            'timezone' => $location['timezone'],
            'notes' => html_entity_decode($location['location_notes']),
            'tokens' => tsml_string_tokens($location['location']),
        ];
    }
    wp_send_json($results);
});

// ajax for the meeting edit group typeahead
add_action('wp_ajax_tsml_groups', function () {
    global $tsml_contact_fields;

    tsml_require_meetings_permission();

    $groups = get_posts(['post_type' => 'tsml_group', 'numberposts' => -1]);
    $results = [];
    foreach ($groups as $group) {
        $group_custom = get_post_meta($group->ID);

        // basic group info
        $result = [
            'value' => $group->post_title,
            'notes' => $group->post_content,
            'tokens' => tsml_string_tokens($group->post_title),
        ];

        foreach ($tsml_contact_fields as $field => $type) {
            $result[$field] = !empty($group_custom[$field][0]) ? $group_custom[$field][0] : null;
        }

        // district
        if ($district = get_the_terms($group, 'tsml_district')) {
            $result += [
                'district' => $district[0]->term_id,
            ];
        }

        $results[] = $result;
    }
    wp_send_json($results);
});


// ajax for the search typeahead on the public meeting directory
add_action('wp_ajax_tsml_typeahead', 'tsml_ajax_typeahead');
add_action('wp_ajax_nopriv_tsml_typeahead', 'tsml_ajax_typeahead');
function tsml_ajax_typeahead()
{
    // regions
    // phpcs:ignore
    $regions = get_terms('tsml_region');
    $results = [];
    foreach ($regions as $region) {
        $results[] = [
            'value' => html_entity_decode($region->name),
            'type' => 'region',
            'tokens' => tsml_string_tokens($region->name),
            'id' => $region->slug // needed by legacy search typeahead menu
        ];
    }

    // locations
    $locations = get_posts(['post_type' => 'tsml_location', 'numberposts' => -1]);
    foreach ($locations as $location) {
        $results[] = [
            'value' => html_entity_decode($location->post_title),
            'type' => 'location',
            'tokens' => tsml_string_tokens($location->post_title),
            'url' => get_permalink($location->ID),
        ];
    }

    // groups
    $groups = get_posts(['post_type' => 'tsml_group', 'numberposts' => -1]);
    foreach ($groups as $group) {
        $results[] = [
            'value' => html_entity_decode($group->post_title),
            'type' => 'group',
            'tokens' => tsml_string_tokens($group->post_title),
        ];
    }

    wp_send_json($results);
}

// ajax for address checking
add_action('wp_ajax_tsml_address', function () {
    tsml_require_meetings_permission();

    if (
        !$posts = get_posts([
            'post_type' => 'tsml_location',
            'numberposts' => 1,
            'meta_key' => 'formatted_address',
            'meta_value' => sanitize_text_field($_GET['formatted_address']),
        ])
    ) {
        wp_send_json(false);
    }

    $region = get_the_terms($posts[0]->ID, 'tsml_region');

    // return info to user
    wp_send_json([
        'location' => $posts[0]->post_title,
        'location_notes' => $posts[0]->post_content,
        'region' => $region[0]->term_id,
    ]);
});


// get all contact email addresses (for europe)
// linked from admin_import.php
add_action('wp_ajax_contacts', function () {
    global $wpdb, $tsml_nonce;
    tsml_require_meetings_permission();
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], $tsml_nonce)) {
        wp_die(__('Security check failed.', '12-step-meeting-list'), 403);
    }
    $post_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_group", "tsml_meeting") AND post_status = "publish"');
    if (empty($post_ids)) {
        die('');
    }
    $emails = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key IN ("email", "contact_1_email", "contact_2_email", "contact_3_email") AND post_id IN (' . implode(',', array_map('intval', $post_ids)) . ')');
    $emails = array_unique(array_filter($emails));
    sort($emails);
    die(wp_kses_post(implode(',<br>', $emails)));
});


// function:	export csv
// used:		linked from admin-import.php
add_action('wp_ajax_csv', function () {

    // going to need this later
    global $tsml_days, $tsml_programs, $tsml_program, $tsml_sharing, $tsml_export_columns, $tsml_custom_meeting_fields;

    // security
    tsml_require_meetings_permission();

    // get data source
    $meetings = tsml_get_meetings([], false, true);

    // helper vars
    $delimiter = ',';
    $escape = '"';

    // allow user-defined fields to be exported
    if (!empty($tsml_custom_meeting_fields)) {
        $tsml_export_columns = array_merge($tsml_export_columns, $tsml_custom_meeting_fields);
    }

    // do header
    $return = implode($delimiter, array_values($tsml_export_columns)) . PHP_EOL;

    // get the preferred time format setting
    $time_format = get_option('time_format');

    // append meetings
    foreach ($meetings as $meeting) {
        $line = [];
        foreach ($tsml_export_columns as $column => $value) {
            if (in_array($column, ['time', 'end_time'])) {
                $line[] = empty($meeting[$column]) ? null : date($time_format, strtotime($meeting[$column]));
            } elseif ($column == 'day') {
                $line[] = $tsml_days[$meeting[$column]];
            } elseif ($column == 'types') {
                $types = !empty($meeting[$column]) ? $meeting[$column] : [];
                if (!is_array($types)) {
                    $types = [];
                }
                foreach ($types as &$type) {
                    $type = $tsml_programs[$tsml_program]['types'][trim($type)];
                }
                sort($types);
                $line[] = $escape . implode(', ', $types) . $escape;
            } elseif (strstr($column, 'notes')) {
                $line[] = $escape . strip_tags(str_replace($escape, str_repeat($escape, 2), !empty($meeting[$column]) ? $meeting[$column] : '')) . $escape;
            } elseif (array_key_exists($column, $meeting)) {
                $line[] = $escape . str_replace($escape, '', $meeting[$column]) . $escape;
            } else {
                $line[] = '';
            }
        }
        $return .= implode($delimiter, $line) . PHP_EOL;
    }

    // headers to trigger file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="meetings.csv"');

    // output
    wp_die(wp_kses($return, []));
});

// function: receives React TSML UI meeting feedback modal submissions, sends email to admins
function aa_canberra_feedback_request_ip()
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    return 'unknown';
}

function aa_canberra_feedback_rate_limit_check($bucket, $limit, $window)
{
    $key = 'aa_can_fb_' . md5($bucket);
    $count = intval(get_transient($key));

    if ($count >= $limit) {
        return false;
    }

    set_transient($key, $count + 1, $window);
    return true;
}

function aa_canberra_feedback_turnstile_verify($token, $remote_ip)
{
    $secret_key = defined('CF_TURNSTILE_SECRET_KEY') ? CF_TURNSTILE_SECRET_KEY : '';
    $site_key = defined('CF_TURNSTILE_SITE_KEY') ? CF_TURNSTILE_SITE_KEY : '';

    if (!$secret_key && !$site_key) {
        return true;
    }

    if (!$secret_key || !$site_key || !$token) {
        return false;
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'timeout' => 10,
        'body' => [
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $remote_ip,
        ],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($body) && !empty($body['success']);
}

function aa_canberra_feedback_plain_text($value)
{
    return aac_feedback_plain_text($value);
}

function aa_canberra_feedback_compare_text($value)
{
    $value = aa_canberra_feedback_plain_text($value);
    return preg_replace('/\s+/', ' ', $value);
}

function aa_canberra_feedback_display_value($value, $strike = false, $strong = false)
{
    $value = aa_canberra_feedback_plain_text($value);
    if ('' === $value) {
        $value = __('Blank', 'aa-canberra-meeting-list');
    }

    $style = 'overflow-wrap:anywhere;';
    if ($strike) {
        $style .= 'color:#6b7280;text-decoration:line-through;';
    }
    if ($strong) {
        $style .= 'font-weight:700;color:#111827;';
    }

    return '<span style="' . esc_attr($style) . '">' . nl2br(esc_html($value)) . '</span>';
}

function aa_canberra_feedback_time_label($meeting)
{
    if (empty($meeting->time)) {
        return __('Appointment', '12-step-meeting-list');
    }

    $time = tsml_format_time($meeting->time);
    if (!empty($meeting->end_time)) {
        $time .= ' - ' . tsml_format_time($meeting->end_time);
    }

    return $time;
}

function aa_canberra_feedback_change_fields($meeting)
{
    return [
        'name' => [
            'label' => __('Meeting name', 'aa-canberra-meeting-list'),
            'current' => $meeting->post_title ?? '',
            'multiline' => false,
        ],
        'time' => [
            'label' => __('Time', '12-step-meeting-list'),
            'current' => aa_canberra_feedback_time_label($meeting),
            'multiline' => false,
        ],
        'location' => [
            'label' => __('Location / Group', '12-step-meeting-list'),
            'current' => !empty($meeting->location) ? $meeting->location : ($meeting->group ?? ''),
            'multiline' => false,
        ],
        'address' => [
            'label' => __('Address / Platform', '12-step-meeting-list'),
            'current' => $meeting->formatted_address ?? '',
            'multiline' => false,
        ],
        'region' => [
            'label' => __('Region', '12-step-meeting-list'),
            'current' => $meeting->region ?? '',
            'multiline' => false,
        ],
        'phone' => [
            'label' => __('Phone', '12-step-meeting-list'),
            'current' => $meeting->phone ?? '',
            'multiline' => false,
        ],
        'email' => [
            'label' => __('Email', '12-step-meeting-list'),
            'current' => $meeting->email ?? '',
            'multiline' => false,
        ],
        'website' => [
            'label' => __('Website', '12-step-meeting-list'),
            'current' => $meeting->website ?? '',
            'multiline' => false,
        ],
        'conference_url' => [
            'label' => __('Online meeting link', 'aa-canberra-meeting-list'),
            'current' => $meeting->conference_url ?? '',
            'multiline' => false,
        ],
        'notes' => [
            'label' => __('Notes', '12-step-meeting-list'),
            'current' => $meeting->notes ?? '',
            'multiline' => true,
        ],
    ];
}

function aa_canberra_feedback_suggested_changes($meeting, $posted_changes)
{
    if (!is_array($posted_changes)) {
        return [];
    }

    $posted_changes = wp_unslash($posted_changes);
    $fields = aa_canberra_feedback_change_fields($meeting);
    $changes = [];

    foreach ($fields as $key => $field) {
        if (!array_key_exists($key, $posted_changes) || is_array($posted_changes[$key])) {
            continue;
        }

        $proposed = $field['multiline']
            ? trim(tsml_sanitize_text_area($posted_changes[$key]))
            : trim(sanitize_text_field($posted_changes[$key]));
        $current = aa_canberra_feedback_plain_text($field['current']);

        if (strlen($proposed) > 2000) {
            $proposed = substr($proposed, 0, 2000);
        }

        if (aa_canberra_feedback_compare_text($current) === aa_canberra_feedback_compare_text($proposed)) {
            continue;
        }

        $changes[] = [
            'label' => $field['label'],
            'current' => $current,
            'proposed' => $proposed,
        ];
    }

    return $changes;
}

function aa_canberra_feedback_render_heading($heading)
{
    return aac_feedback_render_heading($heading);
}

function aa_canberra_feedback_render_details_table($rows)
{
    $rows = array_filter($rows, function ($row) {
        return '' !== aa_canberra_feedback_plain_text($row['value']);
    });

    if (!$rows) {
        return '';
    }

    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 0 8px;width:100%;">';
    foreach ($rows as $row) {
        $html .= '<tr>';
        $html .= '<td style="border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:700;padding:9px 12px 9px 0;vertical-align:top;width:150px;">' . esc_html($row['label']) . '</td>';
        $html .= '<td style="border-top:1px solid #e5e7eb;color:#374151;font-size:14px;line-height:1.5;padding:9px 0;vertical-align:top;">' . $row['value'] . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
}

function aa_canberra_feedback_render_changes_table($changes)
{
    if (!$changes) {
        return '';
    }

    $html = aa_canberra_feedback_render_heading(__('Suggested changes', 'aa-canberra-meeting-list'));
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 0 8px;width:100%;">';
    $html .= '<tr>';
    $html .= '<th align="left" style="background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;font-size:12px;padding:8px;vertical-align:top;width:120px;">' . esc_html__('Field', 'aa-canberra-meeting-list') . '</th>';
    $html .= '<th align="left" style="background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;font-size:12px;padding:8px;vertical-align:top;">' . esc_html__('Current', 'aa-canberra-meeting-list') . '</th>';
    $html .= '<th align="left" style="background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;font-size:12px;padding:8px;vertical-align:top;">' . esc_html__('Proposed', 'aa-canberra-meeting-list') . '</th>';
    $html .= '</tr>';

    foreach ($changes as $change) {
        $html .= '<tr>';
        $html .= '<td style="border:1px solid #e5e7eb;color:#374151;font-size:13px;font-weight:700;padding:8px;vertical-align:top;width:120px;">' . esc_html($change['label']) . '</td>';
        $html .= '<td style="border:1px solid #e5e7eb;font-size:14px;line-height:1.5;padding:8px;vertical-align:top;">' . aa_canberra_feedback_display_value($change['current'], true, false) . '</td>';
        $html .= '<td style="border:1px solid #e5e7eb;font-size:14px;line-height:1.5;padding:8px;vertical-align:top;">' . aa_canberra_feedback_display_value($change['proposed'], false, true) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';

    return $html;
}

add_action('wp_ajax_aa_canberra_meeting_feedback', 'aa_canberra_ajax_meeting_feedback');
add_action('wp_ajax_nopriv_aa_canberra_meeting_feedback', 'aa_canberra_ajax_meeting_feedback');
function aa_canberra_ajax_meeting_feedback()
{
    global $tsml_feedback_addresses;

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aa_canberra_meeting_feedback')) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'aa-canberra-meeting-list')], 403);
    }

    if (!empty($_POST['website'])) {
        wp_send_json_success(['message' => __('Thank you for your feedback.', 'aa-canberra-meeting-list')]);
    }

    $remote_ip = aa_canberra_feedback_request_ip();

    $loaded_at = isset($_POST['loaded_at']) ? intval($_POST['loaded_at']) : 0;
    $elapsed = time() - $loaded_at;
    if (!$loaded_at || $elapsed < 3 || $elapsed > HOUR_IN_SECONDS || $loaded_at > time() + MINUTE_IN_SECONDS) {
        wp_send_json_error(['message' => __('Please refresh the page and try again.', 'aa-canberra-meeting-list')], 400);
    }

    $requester_name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $requester_email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $requester_phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $feedback_message = isset($_POST['message']) ? trim(tsml_sanitize_text_area(wp_unslash($_POST['message']))) : '';
    $meeting_id = isset($_POST['meeting_id']) ? absint($_POST['meeting_id']) : 0;
    $meeting_slug = isset($_POST['meeting_slug']) ? sanitize_title(wp_unslash($_POST['meeting_slug'])) : '';
    $meeting_name = isset($_POST['meeting_name']) ? sanitize_text_field(wp_unslash($_POST['meeting_name'])) : '';
    $meeting_url = isset($_POST['meeting_url']) ? esc_url_raw(wp_unslash($_POST['meeting_url'])) : '';
    $meeting_time = isset($_POST['meeting_time']) ? sanitize_text_field(wp_unslash($_POST['meeting_time'])) : '';
    $meeting_location = isset($_POST['meeting_location']) ? sanitize_text_field(wp_unslash($_POST['meeting_location'])) : '';
    $meeting_address = isset($_POST['meeting_address']) ? sanitize_text_field(wp_unslash($_POST['meeting_address'])) : '';
    $meeting_region = isset($_POST['meeting_region']) ? sanitize_text_field(wp_unslash($_POST['meeting_region'])) : '';

    if (!$requester_name || !is_email($requester_email) || !$feedback_message || !$meeting_id) {
        wp_send_json_error(['message' => __('Please complete the required fields.', 'aa-canberra-meeting-list')], 400);
    }

    if (strlen($requester_name) > 120 || strlen($requester_email) > 254 || strlen($requester_phone) > 80 || strlen($feedback_message) > 5000) {
        wp_send_json_error(['message' => __('One or more fields is too long.', 'aa-canberra-meeting-list')], 400);
    }

    $meeting_post = $meeting_id ? get_post($meeting_id) : null;
    if ($meeting_post && ('tsml_meeting' !== $meeting_post->post_type || 'publish' !== $meeting_post->post_status)) {
        $meeting_post = null;
    }

    if (!$meeting_post) {
        wp_send_json_error(['message' => __('Meeting could not be verified. Please refresh the page and try again.', 'aa-canberra-meeting-list')], 400);
    }

    $turnstile_token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';
    if (!aa_canberra_feedback_turnstile_verify($turnstile_token, $remote_ip)) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'aa-canberra-meeting-list')], 403);
    }

    if (!aa_canberra_feedback_rate_limit_check('send:email:' . strtolower($requester_email), 6, HOUR_IN_SECONDS)) {
        wp_send_json_error(['message' => __('Please wait before sending another update request.', 'aa-canberra-meeting-list')], 429);
    }

    if (!aa_canberra_feedback_rate_limit_check('send:ip_meeting:' . $remote_ip . ':' . $meeting_slug, 6, HOUR_IN_SECONDS)) {
        wp_send_json_error(['message' => __('Please wait before sending another update request for this meeting.', 'aa-canberra-meeting-list')], 429);
    }

    $meeting = null;
    if ($meeting_post) {
        $meeting = tsml_get_meeting($meeting_post->ID);
        if (!$meeting_name) {
            $meeting_name = html_entity_decode($meeting->post_title, ENT_QUOTES);
        }
        if (!$meeting_url) {
            $meeting_url = tsml_feedback_public_permalink($meeting->ID);
        }
        if (!$meeting_time && isset($meeting->day, $meeting->time)) {
            $meeting_time = tsml_format_day_and_time($meeting->day, $meeting->time);
        }
        if (!$meeting_location && !empty($meeting->location)) {
            $meeting_location = html_entity_decode($meeting->location, ENT_QUOTES);
        }
        if (!$meeting_address && !empty($meeting->formatted_address)) {
            $meeting_address = html_entity_decode($meeting->formatted_address, ENT_QUOTES);
        }
        if (!$meeting_region && !empty($meeting->region)) {
            $meeting_region = html_entity_decode($meeting->region, ENT_QUOTES);
        }
    }

    $to_email_addresses = $tsml_feedback_addresses;
    if (is_string($to_email_addresses)) {
        $to_email_addresses = explode(',', $to_email_addresses);
    }
    $to_email_addresses = array_map('trim', (array) $to_email_addresses);
    $to_email_addresses = array_values(array_filter($to_email_addresses, 'is_email'));

    if (empty($to_email_addresses)) {
        wp_send_json_error(['message' => __('Feedback is not configured. Please contact the site administrator.', 'aa-canberra-meeting-list')], 500);
    }

    $posted_changes = isset($_POST['feedback_changes']) ? $_POST['feedback_changes'] : [];
    $suggested_changes = $meeting ? aa_canberra_feedback_suggested_changes($meeting, $posted_changes) : [];
    $submission = [
        'message' => $feedback_message,
        'name' => $requester_name,
        'email' => $requester_email,
        'phone' => $requester_phone,
        'meeting_name' => $meeting_name,
        'meeting_url' => $meeting_url,
        'meeting_time' => $meeting_time,
        'meeting_location' => $meeting_location,
        'meeting_address' => $meeting_address,
        'meeting_region' => $meeting_region,
        'meeting_slug' => $meeting_slug,
    ];
    $gso_result = aac_gso_submit_feedback($meeting, $submission, $suggested_changes);

    $message = aa_canberra_feedback_render_heading(__('Comments', 'aa-canberra-meeting-list'));
    $message .= '<p style="background:#f9fafb;border:1px solid #e5e7eb;margin:0 0 8px;padding:12px;">' . nl2br(esc_html($feedback_message)) . '</p>';
    $message .= aa_canberra_feedback_render_changes_table($suggested_changes);

    $message .= aa_canberra_feedback_render_heading(__('Meeting', '12-step-meeting-list'));
    $message .= aa_canberra_feedback_render_details_table([
        [
            'label' => __('Meeting', '12-step-meeting-list'),
            'value' => $meeting_url ? '<a href="' . esc_url($meeting_url) . '">' . esc_html($meeting_name) . '</a>' : esc_html($meeting_name),
        ],
        [
            'label' => __('When', '12-step-meeting-list'),
            'value' => esc_html($meeting_time),
        ],
        [
            'label' => __('Location', '12-step-meeting-list'),
            'value' => esc_html($meeting_location),
        ],
        [
            'label' => __('Address', '12-step-meeting-list'),
            'value' => esc_html($meeting_address),
        ],
        [
            'label' => __('Region', '12-step-meeting-list'),
            'value' => esc_html($meeting_region),
        ],
        [
            'label' => __('Slug', 'aa-canberra-meeting-list'),
            'value' => esc_html($meeting_slug),
        ],
    ]);

    $message .= aa_canberra_feedback_render_heading(__('Submitted by', 'aa-canberra-meeting-list'));
    $message .= aa_canberra_feedback_render_details_table([
        [
            'label' => __('Name', '12-step-meeting-list'),
            'value' => esc_html($requester_name),
        ],
        [
            'label' => __('Email', '12-step-meeting-list'),
            'value' => '<a href="mailto:' . esc_attr($requester_email) . '">' . esc_html($requester_email) . '</a>',
        ],
        [
            'label' => __('Phone', '12-step-meeting-list'),
            'value' => esc_html($requester_phone),
        ],
    ]);
    $message .= aac_feedback_render_gso_status($gso_result);

    $subject = __('Meeting Feedback Form', '12-step-meeting-list') . ': ' . ($meeting_name ?: $meeting_slug);
    if (tsml_email($to_email_addresses, $subject, $message, $requester_name . ' <' . $requester_email . '>')) {
        wp_send_json_success(['message' => __('Thank you. Your update request has been sent.', 'aa-canberra-meeting-list')]);
    }

    global $phpmailer;
    $error = !empty($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : __('An error occurred while sending email.', 'aa-canberra-meeting-list');
    wp_send_json_error(['message' => $error], 500);
}

// function: receives user feedback, sends email to admin
// used:		single-meetings.php
add_action('wp_ajax_tsml_feedback', 'tsml_ajax_feedback');
add_action('wp_ajax_nopriv_tsml_feedback', 'tsml_ajax_feedback');
function tsml_ajax_feedback()
{
    global $tsml_feedback_addresses, $tsml_nonce;

    $meeting = tsml_get_meeting(intval($_POST['meeting_id']));
    $name = sanitize_text_field($_POST['tsml_name']);
    $email = sanitize_email($_POST['tsml_email']);

    $message = '<p style="padding-bottom: 20px; border-bottom: 2px dashed #ccc; margin-bottom: 20px;">' . nl2br(tsml_sanitize_text_area(stripslashes($_POST['tsml_message']))) . '</p>';

    $message_lines = [
        __('Requested By', '12-step-meeting-list') => $name . ' &lt;<a href="mailto:' . $email . '">' . $email . '</a>&gt;',
        __('Meeting', '12-step-meeting-list') => '<a href="' . tsml_feedback_public_permalink($meeting->ID) . '">' . $meeting->post_title . '</a>',
        __('When', '12-step-meeting-list') => tsml_format_day_and_time($meeting->day, $meeting->time),
    ];

    if (!empty($meeting->types)) {
        $message_lines[__('Types', '12-step-meeting-list')] = implode(', ', $meeting->types);
    }

    if (!empty($meeting->notes)) {
        $message_lines[__('Notes', '12-step-meeting-list')] = $meeting->notes;
    }

    if (!empty($meeting->location)) {
        $message_lines[__('Location', '12-step-meeting-list')] = $meeting->location;
    }

    if (!empty($meeting->formatted_address)) {
        $message_lines[__('Address', '12-step-meeting-list')] = $meeting->formatted_address;
    }

    if (!empty($meeting->region)) {
        $message_lines[__('Region', '12-step-meeting-list')] = $meeting->region;
    }

    if (!empty($meeting->location_notes)) {
        $message_lines[__('Location Notes', '12-step-meeting-list')] = $meeting->location_notes;
    }

    foreach ($message_lines as $key => $value) {
        $message .= '<p>' . $key . ': ' . $value . '</p>';
    }

    $to_email_addresses = $tsml_feedback_addresses;

    // email vars
    if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
        esc_html_e('Error: nonce value not set correctly. Email was not sent.', '12-step-meeting-list');
    } elseif (empty($to_email_addresses) || empty($name) || !is_email($email) || empty($message)) {
        esc_html_e('Error: required form value missing. Email was not sent.', '12-step-meeting-list');
    } else {
        // send HTML email
        $subject = __('Meeting Feedback Form', '12-step-meeting-list') . ': ' . $meeting->post_title;
        if (tsml_email($to_email_addresses, $subject, $message, $name . ' <' . $email . '>')) {
            esc_html_e('Thank you for your feedback.', '12-step-meeting-list');
        } else {
            global $phpmailer;
            if (!empty($phpmailer->ErrorInfo)) {
                // translators: %s is the error message
                echo esc_html(sprintf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo));
            } else {
                esc_html_e('An error occurred while sending email!', '12-step-meeting-list');
            }
        }
    }

    exit;
}

function tsml_feedback_public_permalink($post_id)
{
    $permalink = get_permalink($post_id);
    $parts = wp_parse_url($permalink);

    $path = !empty($parts['path']) ? ltrim($parts['path'], '/') : '';
    $url = trailingslashit('https://meetings.aa.org.au') . $path;

    if (!empty($parts['query'])) {
        $url .= '?' . $parts['query'];
    }

    if (!empty($parts['fragment'])) {
        $url .= '#' . $parts['fragment'];
    }

    return esc_url($url);
}


// function: get geocode for string
// used: public meeting directory, admin_meeting.php
add_action('wp_ajax_tsml_geocode', 'tsml_ajax_geocode');
add_action('wp_ajax_nopriv_tsml_geocode', 'tsml_ajax_geocode');
function tsml_ajax_geocode()
{
    global $tsml_nonce;
    if (!wp_verify_nonce(@$_GET['nonce'], $tsml_nonce)) {
        tsml_ajax_unauthorized();
    }
    wp_send_json(tsml_geocode(@$_GET['address']));
}

// function: get a list of all the geocodes in the database
// used: for debugging
add_action('wp_ajax_tsml_geocodes', function () {
    global $tsml_geocoding_overrides;

    tsml_require_meetings_permission();

    $addresses = tsml_get_option_array('tsml_addresses');

    // handle get request to remove an address from the cache
    if (isset($_GET['remove'])) {
        $remove = stripslashes($_GET['remove']);
        if (!empty($addresses[$remove])) {
            unset($addresses[$remove]);
            update_option('tsml_addresses', $addresses);
        }
    }

    // include the google overrides
    if (!empty($tsml_geocoding_overrides)) {
        $addresses = array_merge($addresses, $tsml_geocoding_overrides);
    }

    // add useful links
    foreach ($addresses as $address => $geocode) {
        $addresses[$address]['map_address'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($geocode['formatted_address']);
        $addresses[$address]['map_coordinates'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($geocode['latitude'] . ',' . $geocode['longitude']);
        if ($geocode['status'] === 'geocode') {
            $addresses[$address]['remove'] = admin_url('admin-ajax.php?action=tsml_geocodes&remove=' . urlencode($address));
        }
    }

    ksort($addresses);

    wp_send_json($addresses);
});

// ajax function to import the meetings in the import buffer
// used by admin_import.php
add_action('wp_ajax_tsml_import', function () {
    tsml_require_meetings_permission();
    $response = tsml_import_buffer_next(25);
    wp_send_json($response);
});

// api ajax function
// used by theme, web app, mobile app
add_action('wp_ajax_meetings', 'tsml_ajax_meetings');
add_action('wp_ajax_nopriv_meetings', 'tsml_ajax_meetings');
function tsml_ajax_meetings()
{
    global $tsml_sharing, $tsml_sharing_keys, $tsml_nonce;

    // accepts GET or POST
    $input = empty($_POST) ? $_GET : $_POST;

    if ($tsml_sharing == 'open') {
        // sharing is open
    } elseif (!empty($input['nonce']) && wp_verify_nonce($input['nonce'], $tsml_nonce)) {
        // nonce checks out
    } elseif (!empty($input['key']) && array_key_exists($input['key'], $tsml_sharing_keys)) {
        // key checks out
    } else {
        tsml_ajax_unauthorized();
    }

    // ignore post_status from user input to prevent exposing drafts
    unset($input['post_status']);

    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
    }
    wp_send_json(tsml_get_meetings($input));
}

// create and email a sharing key to meeting guide
add_action('wp_ajax_meeting_guide', 'tsml_ajax_meeting_guide');
add_action('wp_ajax_nopriv_meeting_guide', 'tsml_ajax_meeting_guide');
function tsml_ajax_meeting_guide()
{
    global $tsml_sharing_keys;

    $mg_key = false;

    // check for existing keys
    foreach ($tsml_sharing_keys as $key => $value) {
        if ($value == 'Meeting Guide') {
            $mg_key = $key;
        }
    }

    // add new key
    if (empty($mg_key)) {
        $mg_key = md5(uniqid('Meeting Guide', true));
        $tsml_sharing_keys[$mg_key] = 'Meeting Guide';
        asort($tsml_sharing_keys);
        update_option('tsml_sharing_keys', $tsml_sharing_keys);
    }

    // build url
    $message = admin_url('admin-ajax.php?') . http_build_query(
        array(
            'action' => 'meetings',
            'key' => $mg_key,
        )
    );

    // send email
    if (tsml_email(TSML_MEETING_GUIDE_APP_NOTIFY, 'Sharing Key', $message)) {
        die('sent');
    }

    die('not sent!');
}

// send a 401 and exit
function tsml_ajax_unauthorized()
{
    if (!headers_sent()) {
        header('HTTP/1.1 401 Unauthorized', true, 401);
    }
    wp_send_json(['error' => 'HTTP/1.1 401 Unauthorized']);
}
