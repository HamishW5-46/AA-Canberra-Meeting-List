<?php

/**
 * AA Canberra dashboard-only settings.
 *
 * The full TSML settings page is intentionally removed in this fork. The only
 * admin-editable settings are feedback and change notification email addresses.
 */

function tsml_settings_removed_redirect()
{
    global $pagenow;

    if (
        $pagenow === 'edit.php'
        && isset($_GET['post_type'], $_GET['page'])
        && $_GET['post_type'] === 'tsml_meeting'
        && $_GET['page'] === 'settings'
    ) {
        wp_safe_redirect(add_query_arg('tsml_settings_removed', 1, admin_url('index.php')));
        exit;
    }
}
add_action('admin_init', 'tsml_settings_removed_redirect');

function tsml_hardcode_removed_settings()
{
    $settings = [
        'tsml_program' => 'aa',
        'tsml_distance_units' => 'km',
        'tsml_contact_display' => 'public',
        'tsml_timezone' => 'Australia/Sydney',
        'tsml_user_interface' => 'tsml_ui',
        'tsml_auto_import' => 'on',
    ];

    foreach ($settings as $option => $value) {
        if (get_option($option) !== $value) {
            update_option($option, $value);
        }
    }
}
add_action('admin_init', 'tsml_hardcode_removed_settings');

function tsml_dashboard_addresses_notice()
{
    if (empty($_GET['tsml_addresses_updated']) && empty($_GET['tsml_address_error']) && empty($_GET['tsml_settings_removed'])) {
        return;
    }

    if (!empty($_GET['tsml_settings_removed'])) {
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('The full meeting settings screen has been removed for this customised plugin. Feedback and notification addresses can be managed from the dashboard widget.', '12-step-meeting-list') . '</p></div>';
        return;
    }

    if (!empty($_GET['tsml_address_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please enter a valid email address.', '12-step-meeting-list') . '</p></div>';
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Meeting email addresses updated.', '12-step-meeting-list') . '</p></div>';
}
add_action('admin_notices', 'tsml_dashboard_addresses_notice');

function tsml_dashboard_addresses_redirect($args = [])
{
    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = admin_url('index.php');
    }

    wp_safe_redirect(add_query_arg($args, remove_query_arg(['tsml_addresses_updated', 'tsml_address_error', 'tsml_settings_removed'], $redirect)));
    exit;
}

function tsml_dashboard_addresses_get_option($option)
{
    $addresses = tsml_get_option_array($option);
    $addresses = array_filter(array_map('sanitize_email', $addresses));
    $addresses = array_filter($addresses, 'is_email');
    $addresses = array_unique($addresses);
    sort($addresses);

    return $addresses;
}

function tsml_dashboard_addresses_update_option($option, $addresses)
{
    $addresses = array_filter(array_map('sanitize_email', $addresses));
    $addresses = array_filter($addresses, 'is_email');
    $addresses = array_unique($addresses);
    sort($addresses);

    if (empty($addresses)) {
        delete_option($option);
    } else {
        update_option($option, $addresses);
    }

    if ($option === 'tsml_feedback_addresses') {
        tsml_cache_rebuild();
    }
}

function tsml_dashboard_addresses_handle_update()
{
    global $tsml_nonce;

    tsml_require_settings_permission();

    if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
        wp_die(esc_html__('Security check failed.', '12-step-meeting-list'), '', ['response' => 403]);
    }

    $address_type = isset($_POST['tsml_address_type']) ? sanitize_key($_POST['tsml_address_type']) : '';
    $operation = isset($_POST['tsml_address_operation']) ? sanitize_key($_POST['tsml_address_operation']) : '';

    $option = '';
    if ($address_type === 'feedback') {
        $option = 'tsml_feedback_addresses';
    } elseif ($address_type === 'notification') {
        $option = 'tsml_notification_addresses';
    }

    if (!$option || !in_array($operation, ['add', 'remove'], true)) {
        wp_die(esc_html__('Invalid address update request.', '12-step-meeting-list'), '', ['response' => 400]);
    }

    $email = isset($_POST['tsml_address']) ? sanitize_email(wp_unslash($_POST['tsml_address'])) : '';
    if (!is_email($email)) {
        tsml_dashboard_addresses_redirect(['tsml_address_error' => 1]);
    }

    $addresses = tsml_dashboard_addresses_get_option($option);
    if ($operation === 'add') {
        $addresses[] = $email;
    } else {
        $addresses = array_diff($addresses, [$email]);
    }

    tsml_dashboard_addresses_update_option($option, $addresses);
    tsml_dashboard_addresses_redirect(['tsml_addresses_updated' => 1]);
}
add_action('admin_post_tsml_dashboard_addresses', 'tsml_dashboard_addresses_handle_update');

function tsml_dashboard_addresses_render_list($addresses, $type)
{
    global $tsml_nonce;

    if (empty($addresses)) {
        echo '<p><em>' . esc_html__('No addresses configured.', '12-step-meeting-list') . '</em></p>';
        return;
    }

    echo '<ul class="tsml-dashboard-addresses-list">';
    foreach ($addresses as $address) {
        echo '<li>';
        echo '<span>' . esc_html($address) . '</span>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
        echo '<input type="hidden" name="action" value="tsml_dashboard_addresses">';
        echo '<input type="hidden" name="tsml_address_type" value="' . esc_attr($type) . '">';
        echo '<input type="hidden" name="tsml_address_operation" value="remove">';
        echo '<input type="hidden" name="tsml_address" value="' . esc_attr($address) . '">';
        submit_button(__('Remove', '12-step-meeting-list'), 'link-delete small', 'submit', false);
        echo '</form>';
        echo '</li>';
    }
    echo '</ul>';
}

function tsml_dashboard_addresses_render_form($type)
{
    global $tsml_nonce;

    echo '<form class="tsml-dashboard-addresses-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
    echo '<input type="hidden" name="action" value="tsml_dashboard_addresses">';
    echo '<input type="hidden" name="tsml_address_type" value="' . esc_attr($type) . '">';
    echo '<input type="hidden" name="tsml_address_operation" value="add">';
    echo '<input type="email" class="regular-text" name="tsml_address" placeholder="' . esc_attr__('email@example.org', '12-step-meeting-list') . '" required>';
    submit_button(__('Add', '12-step-meeting-list'), 'secondary', 'submit', false);
    echo '</form>';
}

function tsml_dashboard_addresses_widget()
{
    $feedback_addresses = tsml_dashboard_addresses_get_option('tsml_feedback_addresses');
    $notification_addresses = tsml_dashboard_addresses_get_option('tsml_notification_addresses');
    ?>
    <div class="tsml-dashboard-addresses">
        <section>
            <h3><?php esc_html_e('Feedback Addresses', '12-step-meeting-list') ?></h3>
            <p><?php esc_html_e('Receive public meeting feedback form submissions.', '12-step-meeting-list') ?></p>
            <?php tsml_dashboard_addresses_render_list($feedback_addresses, 'feedback') ?>
            <?php tsml_dashboard_addresses_render_form('feedback') ?>
        </section>

        <section>
            <h3><?php esc_html_e('Change Notification Addresses', '12-step-meeting-list') ?></h3>
            <p><?php esc_html_e('Receive meeting change notification emails.', '12-step-meeting-list') ?></p>
            <?php tsml_dashboard_addresses_render_list($notification_addresses, 'notification') ?>
            <?php tsml_dashboard_addresses_render_form('notification') ?>
        </section>
    </div>
    <?php
}

function tsml_dashboard_addresses_widget_css()
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'dashboard') {
        return;
    }
    ?>
    <style>
        .tsml-dashboard-addresses section + section {
            border-top: 1px solid #dcdcde;
            margin-top: 16px;
            padding-top: 12px;
        }

        .tsml-dashboard-addresses h3 {
            margin: 0 0 6px;
        }

        .tsml-dashboard-addresses p {
            margin: 0 0 10px;
        }

        .tsml-dashboard-addresses-list {
            margin: 0 0 12px;
        }

        .tsml-dashboard-addresses-list li,
        .tsml-dashboard-addresses-form {
            align-items: center;
            display: flex;
            gap: 8px;
        }

        .tsml-dashboard-addresses-list li {
            justify-content: space-between;
        }

        .tsml-dashboard-addresses-form input[type="email"] {
            flex: 1;
            min-width: 0;
        }
    </style>
    <?php
}
add_action('admin_head-index.php', 'tsml_dashboard_addresses_widget_css');
