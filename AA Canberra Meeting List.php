<?php

/**
 * Plugin Name: AA Canberra Meeting List
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Customised version of Twelve Step Meeting List, originally developed by Code4Recoery
 * Version: 3.19.12-aacanberra.1
 * Requires PHP: 5.6
 * Author: Hamish Wright
 * Author URI: https://github.com/HamishW5-46/aa-canberra-meeting-list
 * Text Domain: aa-canberra-meeting-list
 */

// define constants
define('TSML_ALLOWED_HTML', [
    'a' => ['class' => [], 'href' => [], 'title' => []],
    'br' => [],
    'code' => [],
    'em' => [],
    'pre' => [],
    'span' => ['class' => []],
    'small' => [],
    'strong' => [],
    'table' => ['style' => []],
    'td' => [],
    'tr' => []
]);
if (!defined('TSML_GEOCODING_URL')) {
    define('TSML_GEOCODING_URL', 'https://geo.code4recovery.org');
}
define('TSML_GROUP_CONTACT_COUNT', 3);
define('TSML_MEETING_GUIDE_APP_NOTIFY', 'appsupport@aa.org');
define('TSML_MEETINGS_PERMISSION', 'edit_posts');
define('TSML_PATH', plugin_dir_path(__FILE__));
define('TSML_SETTINGS_PERMISSION', 'manage_options');
define('TSML_VERSION', '3.19.12');
if (!defined('AA_CANBERRA_TSML_UI_FEEDBACK_PUBLIC_ORIGIN')) {
    define('AA_CANBERRA_TSML_UI_FEEDBACK_PUBLIC_ORIGIN', 'https://meetings.aa.org.au');
}
if (!defined('AA_CANBERRA_TSML_UI_CUSTOM_LINKS')) {
    define('AA_CANBERRA_TSML_UI_CUSTOM_LINKS', [
        [
            'label' => 'Printable Meetings List',
            'url' => 'https://docs.google.com/document/d/1ovwL8Nq9_0uJOSprqpRSyhtGhLX4fVud',
        ],
        [
            'label' => 'Updates to Printable Meetings List',
            'url' => 'https://docs.google.com/document/d/1_L3nRk05VkgpauLz76vnCHnOr_RkqcVJ',
        ],
    ]);
}

// include these files first
include TSML_PATH . '/includes/filter_meetings.php';
include TSML_PATH . '/includes/functions.php';
include TSML_PATH . '/includes/functions_email.php';
include TSML_PATH . '/includes/functions_format.php';
include TSML_PATH . '/includes/functions_get.php';
include TSML_PATH . '/includes/functions_import.php';
include TSML_PATH . '/includes/functions_input.php';
include TSML_PATH . '/includes/functions_log.php';
include TSML_PATH . '/includes/functions_timezone.php';
include TSML_PATH . '/includes/variables.php';

// include public files
include TSML_PATH . '/includes/ajax.php';
include TSML_PATH . '/includes/init.php';
include TSML_PATH . '/includes/rest.php';
include TSML_PATH . '/includes/shortcodes.php';
include TSML_PATH . '/includes/widgets.php';
include TSML_PATH . '/includes/widgets_init.php';
include TSML_PATH . '/includes/blocks.php';

// include admin files
if (is_admin()) {
    include TSML_PATH . '/includes/admin_import.php';
    include TSML_PATH . '/includes/admin_lock.php';
    include TSML_PATH . '/includes/admin_lists.php';
    include TSML_PATH . '/includes/admin_log.php';
    include TSML_PATH . '/includes/admin_meeting.php';
    include TSML_PATH . '/includes/admin_menu.php';
    include TSML_PATH . '/includes/admin_region.php';
    include TSML_PATH . '/includes/admin_settings.php';
    include TSML_PATH . '/includes/save.php';
}

// these hooks need to be in this file
register_activation_hook(__FILE__, 'tsml_plugin_activation');
register_deactivation_hook(__FILE__, 'tsml_plugin_deactivation');
