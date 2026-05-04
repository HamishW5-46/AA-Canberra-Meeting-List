<?php

/**
 * Locks meeting records in wp-admin.
 *
 * AA Canberra receives meetings from an external feed, so manual edits in the
 * WordPress admin should be blocked while import/cron/CLI updates continue to work.
 */

function tsml_meeting_admin_lock_enabled()
{
    return apply_filters('tsml_meeting_admin_lock_enabled', true);
}

function tsml_meeting_admin_lock_is_import_context()
{
    if (defined('WP_CLI') && WP_CLI) {
        return true;
    }

    if (function_exists('wp_doing_cron') && wp_doing_cron()) {
        return true;
    }

    $doing_ajax = function_exists('wp_doing_ajax') ? wp_doing_ajax() : (defined('DOING_AJAX') && DOING_AJAX);
    if ($doing_ajax) {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        return in_array($action, ['tsml_import'], true);
    }

    return false;
}

function tsml_meeting_admin_lock_message()
{
    return __('Meeting records are locked because they are maintained by the external feed. Use Meetings > Import & Export to refresh data.', '12-step-meeting-list');
}

function tsml_admin_lock_taxonomies()
{
    return ['tsml_region', 'tsml_district'];
}

function tsml_admin_lock_is_locked_taxonomy($taxonomy)
{
    return in_array($taxonomy, tsml_admin_lock_taxonomies(), true);
}

function tsml_admin_lock_taxonomy_message()
{
    return __('Regions and districts are locked because they are maintained by the external feed. Use Meetings > Import & Export to refresh data.', '12-step-meeting-list');
}

function tsml_meeting_admin_lock_redirect_url()
{
    return add_query_arg(
        [
            'post_type' => 'tsml_meeting',
            'tsml_locked' => 1,
        ],
        admin_url('edit.php')
    );
}

function tsml_meeting_admin_lock_redirect_edit_screens()
{
    if (!tsml_meeting_admin_lock_enabled() || tsml_meeting_admin_lock_is_import_context()) {
        return;
    }

    global $pagenow;

    if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'tsml_meeting') {
        wp_safe_redirect(tsml_meeting_admin_lock_redirect_url());
        exit;
    }

    if ($pagenow === 'post.php' && !empty($_GET['post']) && get_post_type(absint($_GET['post'])) === 'tsml_meeting') {
        wp_safe_redirect(tsml_meeting_admin_lock_redirect_url());
        exit;
    }

    if ($pagenow === 'post.php' && !empty($_POST['post_type']) && $_POST['post_type'] === 'tsml_meeting') {
        wp_die(esc_html(tsml_meeting_admin_lock_message()));
    }

    if ($pagenow === 'term.php' && isset($_GET['taxonomy']) && tsml_admin_lock_is_locked_taxonomy($_GET['taxonomy'])) {
        wp_safe_redirect(add_query_arg(
            [
                'taxonomy' => sanitize_key($_GET['taxonomy']),
                'post_type' => $_GET['taxonomy'] === 'tsml_region' ? 'tsml_location' : 'tsml_group',
                'tsml_locked' => 1,
            ],
            admin_url('edit-tags.php')
        ));
        exit;
    }

    if ($pagenow === 'edit-tags.php' && isset($_POST['taxonomy']) && tsml_admin_lock_is_locked_taxonomy($_POST['taxonomy'])) {
        wp_die(esc_html(tsml_admin_lock_taxonomy_message()));
    }
}
add_action('admin_init', 'tsml_meeting_admin_lock_redirect_edit_screens');

function tsml_meeting_admin_lock_admin_notice()
{
    if (!tsml_meeting_admin_lock_enabled()) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-tsml_meeting') {
        return;
    }

    $class = empty($_GET['tsml_locked']) ? 'notice notice-info' : 'notice notice-warning';
    echo '<div class="' . esc_attr($class) . '"><p>' . esc_html(tsml_meeting_admin_lock_message()) . '</p></div>';
}
add_action('admin_notices', 'tsml_meeting_admin_lock_admin_notice');

function tsml_admin_lock_taxonomy_notice()
{
    if (!tsml_meeting_admin_lock_enabled()) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || empty($screen->taxonomy) || !tsml_admin_lock_is_locked_taxonomy($screen->taxonomy)) {
        return;
    }

    $class = empty($_GET['tsml_locked']) ? 'notice notice-info' : 'notice notice-warning';
    echo '<div class="' . esc_attr($class) . '"><p>' . esc_html(tsml_admin_lock_taxonomy_message()) . '</p></div>';
}
add_action('admin_notices', 'tsml_admin_lock_taxonomy_notice');

function tsml_meeting_admin_lock_remove_add_new_menu()
{
    if (!tsml_meeting_admin_lock_enabled()) {
        return;
    }

    remove_submenu_page('edit.php?post_type=tsml_meeting', 'post-new.php?post_type=tsml_meeting');
}
add_action('admin_menu', 'tsml_meeting_admin_lock_remove_add_new_menu', 999);

function tsml_meeting_admin_lock_admin_css()
{
    if (!tsml_meeting_admin_lock_enabled()) {
        return;
    }

    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-tsml_meeting') {
        echo '<style>.post-type-tsml_meeting .page-title-action, .post-type-tsml_meeting .bulkactions { display: none; }</style>';
    }

    if ($screen && !empty($screen->taxonomy) && tsml_admin_lock_is_locked_taxonomy($screen->taxonomy)) {
        echo '<style>
            body.taxonomy-tsml_region #col-left,
            body.taxonomy-tsml_district #col-left,
            body.taxonomy-tsml_region .bulkactions,
            body.taxonomy-tsml_district .bulkactions {
                display: none;
            }

            body.taxonomy-tsml_region #col-right,
            body.taxonomy-tsml_district #col-right {
                float: none;
                width: 100%;
            }
        </style>';
    }
}
add_action('admin_head-edit.php', 'tsml_meeting_admin_lock_admin_css');
add_action('admin_head-edit-tags.php', 'tsml_meeting_admin_lock_admin_css');

function tsml_meeting_admin_lock_meta_caps($caps, $cap, $user_id, $args)
{
    if (!tsml_meeting_admin_lock_enabled() || tsml_meeting_admin_lock_is_import_context()) {
        return $caps;
    }

    if (in_array($cap, ['edit_post', 'delete_post'], true) && !empty($args[0])) {
        if (get_post_type(absint($args[0])) === 'tsml_meeting') {
            return ['do_not_allow'];
        }
    }

    if (in_array($cap, ['edit_term', 'delete_term'], true) && !empty($args[0])) {
        $term = get_term(absint($args[0]));
        if ($term && !is_wp_error($term) && tsml_admin_lock_is_locked_taxonomy($term->taxonomy)) {
            return ['do_not_allow'];
        }
    }

    return $caps;
}
add_filter('map_meta_cap', 'tsml_meeting_admin_lock_meta_caps', 20, 4);

function tsml_meeting_admin_lock_row_actions($actions, $post)
{
    if (tsml_meeting_admin_lock_enabled() && $post->post_type === 'tsml_meeting') {
        unset($actions['edit']);
        unset($actions['inline hide-if-no-js']);
        unset($actions['trash']);
        unset($actions['delete']);
    }

    return $actions;
}
add_filter('post_row_actions', 'tsml_meeting_admin_lock_row_actions', 20, 2);

function tsml_admin_lock_term_row_actions($actions, $tag)
{
    if (tsml_meeting_admin_lock_enabled() && tsml_admin_lock_is_locked_taxonomy($tag->taxonomy)) {
        unset($actions['edit']);
        unset($actions['inline hide-if-no-js']);
        unset($actions['delete']);
    }

    return $actions;
}
add_filter('tsml_region_row_actions', 'tsml_admin_lock_term_row_actions', 20, 2);
add_filter('tsml_district_row_actions', 'tsml_admin_lock_term_row_actions', 20, 2);

function tsml_admin_lock_term_edit_link($termlink, $term_id, $taxonomy)
{
    if (tsml_meeting_admin_lock_enabled() && tsml_admin_lock_is_locked_taxonomy($taxonomy)) {
        return '';
    }

    return $termlink;
}
add_filter('get_edit_term_link', 'tsml_admin_lock_term_edit_link', 20, 3);

function tsml_meeting_admin_lock_edit_link($link, $post_id)
{
    if (tsml_meeting_admin_lock_enabled() && get_post_type($post_id) === 'tsml_meeting') {
        return '';
    }

    return $link;
}
add_filter('get_edit_post_link', 'tsml_meeting_admin_lock_edit_link', 20, 2);

function tsml_meeting_admin_lock_bulk_actions()
{
    return [];
}
add_filter('bulk_actions-edit-tsml_meeting', 'tsml_meeting_admin_lock_bulk_actions', 999);
add_filter('bulk_actions-edit-tsml_region', 'tsml_meeting_admin_lock_bulk_actions', 999);
add_filter('bulk_actions-edit-tsml_district', 'tsml_meeting_admin_lock_bulk_actions', 999);

function tsml_meeting_admin_lock_block_trash($trash, $post)
{
    if (tsml_meeting_admin_lock_enabled() && !tsml_meeting_admin_lock_is_import_context() && $post->post_type === 'tsml_meeting') {
        wp_die(esc_html(tsml_meeting_admin_lock_message()));
    }

    return $trash;
}
add_filter('pre_trash_post', 'tsml_meeting_admin_lock_block_trash', 10, 2);

function tsml_meeting_admin_lock_block_delete($post_id)
{
    if (!tsml_meeting_admin_lock_enabled() || tsml_meeting_admin_lock_is_import_context()) {
        return;
    }

    if (get_post_type($post_id) === 'tsml_meeting') {
        wp_die(esc_html(tsml_meeting_admin_lock_message()));
    }
}
add_action('before_delete_post', 'tsml_meeting_admin_lock_block_delete');
