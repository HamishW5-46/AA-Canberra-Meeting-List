<?php

add_action('admin_menu', function () {

    // add menu items
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Regions', '12-step-meeting-list'),
        __('Regions', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Districts', '12-step-meeting-list'),
        __('Districts', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'edit-tags.php?taxonomy=tsml_district&post_type=tsml_group'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Import & Export', '12-step-meeting-list'),
        __('Import & Export', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'import',
        'tsml_import_page'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Event Log', '12-step-meeting-list'),
        __('Event Log', '12-step-meeting-list'),
        TSML_SETTINGS_PERMISSION,
        'log',
        'tsml_log_page'
    );
    // don't collapse the menu when regions or distrits are selected
    add_filter('parent_file', function ($parent_file) {
        global $submenu_file, $current_screen, $pagenow;
        if ($current_screen->post_type == 'tsml_location') {
            if ($pagenow == 'edit-tags.php') {
                $submenu_file = 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location';
            }
            $parent_file = 'edit.php?post_type=tsml_meeting';
        } elseif ($current_screen->post_type == 'tsml_group') {
            if ($pagenow == 'edit-tags.php') {
                $submenu_file = 'edit-tags.php?taxonomy=tsml_district&post_type=tsml_group';
            }
            $parent_file = 'edit.php?post_type=tsml_meeting';
        }
        return $parent_file;
    });
});

// add a widget to the main dashboard page
add_action(
    'wp_dashboard_setup',
    function () {
        wp_add_dashboard_widget('tsml_addresses_widget', __('Meeting Email Addresses', '12-step-meeting-list'), 'tsml_dashboard_addresses_widget', null, null, 'normal', 'high');
    }
);
