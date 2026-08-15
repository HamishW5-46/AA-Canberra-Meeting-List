<?php

function aac_gso_feedback_result($overrides = [])
{
    return (object) array_merge([
        'mode' => aac_gso_feedback_mode(),
        'success' => false,
        'attempted' => false,
        'attempts' => 0,
        'http_status' => null,
        'body' => '',
        'message' => '',
        'meeting_url' => '',
        'endpoint' => '',
        'error' => '',
        'nonce_failed' => false,
    ], $overrides);
}

function aac_gso_feedback_mode()
{
    if (defined('AA_CANBERRA_GSO_FEEDBACK_MODE')) {
        return sanitize_key(AA_CANBERRA_GSO_FEEDBACK_MODE);
    }

    $env_mode = getenv('AAC_GSO_FEEDBACK_MODE');
    if ($env_mode) {
        return sanitize_key($env_mode);
    }

    return 'mock_success';
}

function aac_gso_feedback_origin()
{
    if (defined('AA_CANBERRA_GSO_FEEDBACK_ORIGIN')) {
        $origin = AA_CANBERRA_GSO_FEEDBACK_ORIGIN;
    } elseif (defined('AA_CANBERRA_TSML_UI_FEEDBACK_PUBLIC_ORIGIN')) {
        $origin = AA_CANBERRA_TSML_UI_FEEDBACK_PUBLIC_ORIGIN;
    } else {
        $origin = 'https://meetings.aa.org.au';
    }

    return untrailingslashit(esc_url_raw($origin, ['https']));
}

function aac_gso_feedback_live_allowed()
{
    $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    return 'production' === $environment || (defined('AA_CANBERRA_GSO_FEEDBACK_LIVE_ALLOWED') && AA_CANBERRA_GSO_FEEDBACK_LIVE_ALLOWED);
}

function aac_gso_feedback_meeting_url($meeting)
{
    $origin = aac_gso_feedback_origin();
    $slug = '';

    if (!empty($meeting->source_slug)) {
        $slug = $meeting->source_slug;
    } elseif (!empty($meeting->slug)) {
        $slug = $meeting->slug;
    } elseif (!empty($meeting->post_name)) {
        $slug = $meeting->post_name;
    }

    if ($origin && $slug) {
        return trailingslashit($origin) . 'meetings/' . rawurlencode($slug) . '/';
    }

    return tsml_feedback_public_permalink($meeting->ID);
}

function aac_gso_feedback_submission_value($submission, $key, $default = '')
{
    if (is_array($submission) && array_key_exists($key, $submission)) {
        return $submission[$key];
    }

    if (is_object($submission) && isset($submission->$key)) {
        return $submission->$key;
    }

    return $default;
}

function aac_gso_feedback_message($meeting, $submission, $changes)
{
    $lines = [
        'Meeting feedback submitted through AA Canberra',
        '',
        'Comments:',
        aac_feedback_plain_text(aac_gso_feedback_submission_value($submission, 'message')),
        '',
    ];

    if ($changes) {
        $lines[] = 'Suggested changes:';
        foreach ($changes as $change) {
            $lines[] = $change['label'] . ':';
            $lines[] = 'Current: ' . aac_feedback_plain_text($change['current']);
            $lines[] = 'Proposed: ' . aac_feedback_plain_text($change['proposed']);
            $lines[] = '';
        }
    }

    $meeting_url = aac_gso_feedback_submission_value($submission, 'gso_meeting_url');
    if (!$meeting_url && $meeting) {
        $meeting_url = aac_gso_feedback_meeting_url($meeting);
    }

    $meeting_details = [
        __('Meeting', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'meeting_name'),
        __('When', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'meeting_time'),
        __('Location', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'meeting_location'),
        __('Address', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'meeting_address'),
        __('Region', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'meeting_region'),
        __('Slug', 'aa-canberra-meeting-list') => aac_gso_feedback_submission_value($submission, 'meeting_slug'),
        __('URL', 'aa-canberra-meeting-list') => $meeting_url ?: aac_gso_feedback_submission_value($submission, 'meeting_url'),
    ];

    $lines[] = 'Meeting:';
    foreach ($meeting_details as $label => $value) {
        $value = aac_feedback_plain_text($value);
        if ('' !== $value) {
            $lines[] = $label . ': ' . $value;
        }
    }

    $requester_details = [
        __('Name', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'name'),
        __('Email', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'email'),
        __('Phone', '12-step-meeting-list') => aac_gso_feedback_submission_value($submission, 'phone'),
    ];

    $lines[] = '';
    $lines[] = 'Submitted by:';
    foreach ($requester_details as $label => $value) {
        $value = aac_feedback_plain_text($value);
        if ('' !== $value) {
            $lines[] = $label . ': ' . $value;
        }
    }

    $lines[] = '';
    $lines[] = 'This message was automatically forwarded after the AA Canberra meeting feedback form was submitted.';

    return implode("\n", $lines);
}

function aac_gso_feedback_extract_input($html, $name)
{
    $name = preg_quote($name, '/');
    $patterns = [
        '/<input\b[^>]*\bname=["\']' . $name . '["\'][^>]*\bvalue=["\']([^"\']*)["\'][^>]*>/i',
        '/<input\b[^>]*\bvalue=["\']([^"\']*)["\'][^>]*\bname=["\']' . $name . '["\'][^>]*>/i',
        '/["\']' . $name . '["\']\s*:\s*["\']([^"\']*)["\']/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, get_bloginfo('charset'));
        }
    }

    return '';
}

function aac_gso_feedback_fetch_fields($meeting_url)
{
    $response = wp_safe_remote_get($meeting_url, [
        'redirection' => 3,
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return [
            'result' => aac_gso_feedback_result([
                'meeting_url' => $meeting_url,
                'message' => __('Could not load the GSO meeting page.', 'aa-canberra-meeting-list'),
                'error' => $response->get_error_message(),
            ]),
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $meeting_id = aac_gso_feedback_extract_input($body, 'meeting_id');
    $nonce = aac_gso_feedback_extract_input($body, 'tsml_nonce');

    if (!$nonce) {
        $nonce = aac_gso_feedback_extract_input($body, 'nonce');
    }

    if (!$meeting_id || !$nonce) {
        return [
            'result' => aac_gso_feedback_result([
                'meeting_url' => $meeting_url,
                'http_status' => wp_remote_retrieve_response_code($response),
                'body' => $body,
                'message' => __('Could not find the GSO feedback form fields.', 'aa-canberra-meeting-list'),
            ]),
        ];
    }

    return [
        'meeting_id' => absint($meeting_id),
        'nonce' => sanitize_text_field($nonce),
        'cookies' => function_exists('wp_remote_retrieve_cookies') ? wp_remote_retrieve_cookies($response) : [],
    ];
}

function aac_gso_feedback_nonce_failed($body)
{
    return (bool) preg_match('/nonce|security|refresh the page/i', aac_feedback_plain_text($body));
}

function aac_gso_submit_feedback_live($meeting, $submission, $changes)
{
    $meeting_url = aac_gso_feedback_submission_value($submission, 'gso_meeting_url');
    if (!$meeting_url && $meeting) {
        $meeting_url = aac_gso_feedback_meeting_url($meeting);
    }

    if (!aac_gso_feedback_live_allowed()) {
        return aac_gso_feedback_result([
            'mode' => 'live',
            'meeting_url' => $meeting_url,
            'message' => __('Live GSO submission is disabled for this WordPress environment.', 'aa-canberra-meeting-list'),
        ]);
    }

    $endpoint = trailingslashit(aac_gso_feedback_origin()) . 'wp-admin/admin-ajax.php';
    $gso_message = aac_gso_feedback_message($meeting, $submission, $changes);
    $last_result = aac_gso_feedback_result([
        'mode' => 'live',
        'attempted' => true,
        'meeting_url' => $meeting_url,
        'endpoint' => $endpoint,
    ]);

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $fields = aac_gso_feedback_fetch_fields($meeting_url);
        if (!empty($fields['result'])) {
            $result = $fields['result'];
            $result->mode = 'live';
            $result->attempted = true;
            $result->attempts = $attempt;
            $result->endpoint = $endpoint;
            return $result;
        }

        $response = wp_safe_remote_post($endpoint, [
            'redirection' => 0,
            'timeout' => 15,
            'cookies' => $fields['cookies'],
            'body' => [
                'action' => 'tsml_feedback',
                'meeting_id' => $fields['meeting_id'],
                'tsml_nonce' => $fields['nonce'],
                'tsml_name' => aac_gso_feedback_submission_value($submission, 'name'),
                'tsml_email' => aac_gso_feedback_submission_value($submission, 'email'),
                'tsml_message' => $gso_message,
            ],
        ]);

        if (is_wp_error($response)) {
            $last_result->attempts = $attempt;
            $last_result->message = __('The GSO feedback request failed before a response was received.', 'aa-canberra-meeting-list');
            $last_result->error = $response->get_error_message();
            return $last_result;
        }

        $http_status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $body_text = aac_feedback_plain_text($body);
        $success = 200 === intval($http_status) && 'Thank you for your feedback.' === trim($body_text);
        $nonce_failed = aac_gso_feedback_nonce_failed($body);

        $last_result = aac_gso_feedback_result([
            'mode' => 'live',
            'success' => $success,
            'attempted' => true,
            'attempts' => $attempt,
            'http_status' => $http_status,
            'body' => $body,
            'message' => $success
                ? __('Successfully submitted to the General Service Office meeting feedback system for actioning.', 'aa-canberra-meeting-list')
                : __('Automatic submission to the General Service Office failed. Manual intervention is required for actioning.', 'aa-canberra-meeting-list'),
            'meeting_url' => $meeting_url,
            'endpoint' => $endpoint,
            'nonce_failed' => $nonce_failed,
        ]);

        if ($success || !$nonce_failed) {
            return $last_result;
        }
    }

    return $last_result;
}

function aac_gso_submit_feedback($meeting, $submission, $changes)
{
    $mode = aac_gso_feedback_mode();
    $meeting_url = aac_gso_feedback_submission_value($submission, 'gso_meeting_url');
    if (!$meeting_url && $meeting) {
        $meeting_url = aac_gso_feedback_meeting_url($meeting);
    }

    if ('mock_success' === $mode) {
        return aac_gso_feedback_result([
            'mode' => $mode,
            'success' => true,
            'message' => __('GSO submission was simulated successfully. No live GSO request was sent.', 'aa-canberra-meeting-list'),
            'meeting_url' => $meeting_url,
        ]);
    }

    if ('mock_failure' === $mode) {
        return aac_gso_feedback_result([
            'mode' => $mode,
            'message' => __('Automatic submission to the General Service Office failed in simulation. Manual intervention is required for actioning.', 'aa-canberra-meeting-list'),
            'meeting_url' => $meeting_url,
        ]);
    }

    if ('mock_nonce_retry' === $mode) {
        return aac_gso_feedback_result([
            'mode' => $mode,
            'success' => true,
            'attempts' => 2,
            'nonce_failed' => true,
            'message' => __('GSO submission was simulated successfully after refreshing the nonce. No live GSO request was sent.', 'aa-canberra-meeting-list'),
            'meeting_url' => $meeting_url,
        ]);
    }

    if ('live' === $mode) {
        return aac_gso_submit_feedback_live($meeting, $submission, $changes);
    }

    return aac_gso_feedback_result([
        'mode' => $mode,
        'meeting_url' => $meeting_url,
        'message' => __('GSO submission is not configured. Manual intervention is required for actioning.', 'aa-canberra-meeting-list'),
    ]);
}
