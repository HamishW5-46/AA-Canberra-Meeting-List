<?php

// import / export page

if (!function_exists('tsml_import_page')) {

    function tsml_import_page()
    {
        global $tsml_nonce, $tsml_sharing, $tsml_slug, $tsml_auto_import;

        // todo consider whether this check is necessary, since it is run from add_submenu_page() which is already checking for the same permission
        // potentially tsml_import_page() could be a closure within the call to add_submenu_page which would prevent it from being reused elsewhere
        tsml_require_meetings_permission();

        $error = false;
        $tsml_data_sources = tsml_get_option_array('tsml_data_sources');

        // is this a valid TSML post
        $valid_nonce = isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce);

        // add or refresh a data source
        if (!empty($_POST['tsml_add_data_source']) && $valid_nonce) {
            tsml_import_data_source(
                $_POST['tsml_add_data_source'],
                $_POST['tsml_add_data_source_name'],
                $_POST['tsml_add_data_source_parent_region_id'],
                'disabled'
            );
        }

        // check for existing import buffer
        $meetings = tsml_get_option_array('tsml_import_buffer');

        // remove data source
        if (!empty($_POST['tsml_remove_data_source']) && $valid_nonce) {

            // sanitize URL
            $_POST['tsml_remove_data_source'] = esc_url_raw($_POST['tsml_remove_data_source'], ['http', 'https']);

            if (array_key_exists($_POST['tsml_remove_data_source'], $tsml_data_sources)) {

                // get data source for log entry
                $data_source = $tsml_data_sources[$_POST['tsml_remove_data_source']];

                // remove all meetings for this data source
                tsml_delete(tsml_get_data_source_ids($_POST['tsml_remove_data_source']));

                // clean up orphaned locations & groups
                tsml_delete_orphans();

                // remove data source
                unset($tsml_data_sources[$_POST['tsml_remove_data_source']]);
                update_option('tsml_data_sources', $tsml_data_sources);

                tsml_log('data_source', __('Removed', '12-step-meeting-list'), $data_source['name']);

                tsml_alert(__('Data source removed.', '12-step-meeting-list'));
            }
        }

        $tsml_auto_import = 'on';
        if (get_option('tsml_auto_import') !== $tsml_auto_import) {
            update_option('tsml_auto_import', $tsml_auto_import);
        }
        // ensure cron follows setting
        tsml_import_cron_check();
        ?>

        <!-- Admin page content should all be inside .wrap -->
        <div class="wrap tsml_admin_settings">

            <h1></h1> <!-- Set alerts here -->
            <style>
                .tsml-import-summary-grid {
                    align-items: stretch;
                    display: grid;
                    gap: 20px;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .tsml-import-summary-grid .postbox {
                    height: 100%;
                }

                @media (max-width: 900px) {
                    .tsml-import-summary-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <div class="stack">

                <?php if ($error) { ?>
                    <div class="error inline">
                        <p>
                            <?php echo wp_kses($error, TSML_ALLOWED_HTML) ?>
                        </p>
                    </div>
                <?php } elseif ($total = count($meetings)) { ?>
                    <div id="tsml_import_progress" class="progress" data-total="<?php echo esc_attr($total) ?>">
                        <div class="progress-bar"></div>
                    </div>
                    <ol id="tsml_import_errors" class="error inline hidden"></ol>
                <?php } ?>

                <!-- Import Data Sources -->
                <div class="postbox stack">
                    <h2>
                        <?php esc_html_e('Import Data Sources', '12-step-meeting-list') ?>
                    </h2>
                    <p>
                        <?php esc_html_e('Data sources are JSON feeds or Google Sheets that contain public meeting data. They can be used to aggregate meetings from different sites into a single list on this site.', '12-step-meeting-list') ?>
                    </p>
                    <?php if (!empty($tsml_data_sources)) { ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="small align-center"></th>
                                    <th>
                                        <?php esc_html_e('Source', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-left">
                                        <?php esc_html_e('Parent Region', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-left">
                                        <?php esc_html_e('Change Detection', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-center">
                                        <?php esc_html_e('Meetings', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-right">
                                        <?php esc_html_e('Last Refresh', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="small"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tsml_data_sources as $feed => $properties) { ?>
                                    <tr data-source="<?php echo esc_attr($feed) ?>">
                                        <td class="small ">
                                            <form method="post">
                                                <?php
                                                wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                                tsml_input_hidden('tsml_add_data_source', $feed);
                                                tsml_input_hidden('tsml_add_data_source_name', @$properties['name']);
                                                tsml_input_hidden('tsml_add_data_source_parent_region_id', @$properties['parent_region_id']);
                                                tsml_input_hidden('tsml_add_data_source_change_detect', @$properties['change_detect']);
                                                tsml_input_submit(__('Refresh', '12-step-meeting-list'));
                                                ?>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_attr($feed) ?>" target="_blank" data-source-name>
                                                <?php echo esc_html(!empty($properties['name']) ? $properties['name'] : __('Unnamed Feed', '12-step-meeting-list')) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            $parent_region = null;
                                            if (empty($properties['parent_region_id']) || $properties['parent_region_id'] == -1) {
                                                $parent_region = __('Top-level region', '12-step-meeting-list');
                                            } elseif (empty($regions[$properties['parent_region_id']])) {
                                                $term = get_term_by('term_id', $properties['parent_region_id'], 'tsml_region');
                                                $parent_region = $term->name;
                                                if ($parent_region == null) {
                                                    $parent_region = __('Top-level region', '12-step-meeting-list');
                                                    $parent_region = 'Missing Parent Region: ' . $properties['parent_region_id'];
                                                }
                                            } else {
                                                $parent_region = $regions[$properties['parent_region_id']];
                                            }
                                            echo esc_html($parent_region);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $change_detect = null;
                                            if (empty($properties['change_detect']) || $properties['change_detect'] == -1) {
                                                $change_detect = __('Disabled', '12-step-meeting-list');
                                            } else {
                                                $change_detect = ucfirst($properties['change_detect']);
                                            }
                                            echo esc_html($change_detect);
                                            ?>
                                        </td>
                                        <td class="align-center count_meetings">
                                            <?php echo number_format($properties['count_meetings']) ?>
                                        </td>

                                        <td class="align-right">
                                            <?php echo esc_html(date(get_option('date_format') . ' ' . get_option('time_format'), $properties['last_import'])) ?>
                                        </td>

                                        <td class="small">
                                            <form method="post">
                                                <?php
                                                wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                                tsml_input_hidden('tsml_remove_data_source', $feed);
                                                ?>
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                    <form class="row" method="post">
                        <?php
                        wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                        tsml_input_text('tsml_add_data_source_name', '', ['placeholder' => __('District or Intergroup Name', '12-step-meeting-list')]);
                        tsml_input_url('tsml_add_data_source');
                        wp_dropdown_categories(
                            [
                                'name' => 'tsml_add_data_source_parent_region_id',
                                'taxonomy' => 'tsml_region',
                                'hierarchical' => true,
                                'hide_empty' => false,
                                'orderby' => 'name',
                                'selected' => null,
                                'title' => __('Append regions created by this data source to… (top-level, if none selected)', '12-step-meeting-list'),
                                'show_option_none' => __('Parent Region…', '12-step-meeting-list'),
                            ]
                        );
                        ?>

                        <?php tsml_input_submit(__('Add Data Source', '12-step-meeting-list')) ?>
                    </form>
                </div>

                <?php
                $meetings = tsml_count_meetings();
                $locations = tsml_count_locations();
                $regions = tsml_count_regions();
                $groups = tsml_count_groups();

                $pdf_link = 'https://pdf.code4recovery.org/?' . http_build_query([
                    'json' => admin_url('admin-ajax.php') . '?' . http_build_query([
                        'action' => 'meetings',
                        'nonce' => $tsml_sharing === 'restricted' ? wp_create_nonce($tsml_nonce) : null
                    ])
                ]);
                ?>

                <div class="tsml-import-summary-grid">
                    <!-- Wheres My Info? -->
                    <div class="postbox stack">
                            <h2>
                                <?php esc_html_e('Where\'s My Info?', '12-step-meeting-list') ?>
                            </h2>
                            <?php if ($tsml_slug) { ?>
                                <p>
                                    <?php echo wp_kses(sprintf(
                                        // translators: %s is the link to the public meetings page
                                        __('Your public meetings page is <a href="%s">right here</a>. Link that page from your site\'s nav menu to make it visible to the public.', '12-step-meeting-list'),
                                        tsml_meetings_url()
                                    ), TSML_ALLOWED_HTML) ?>
                                </p>
                                <?php
                            } ?>

                            <div id="tsml_counts" <?php if (!($meetings + $locations + $groups + $regions)) { ?> class="hidden"
                                <?php } ?>>
                                <p>
                                    <?php esc_html_e('You have:', '12-step-meeting-list') ?>
                                </p>
                                <div class="table">
                                    <ul class="ul-disc">
                                        <li class="meetings<?php if (!$meetings) { ?> hidden<?php } ?>">
                                            <?php echo esc_html(sprintf(
                                                // translators: %s is the number of meetings
                                                _n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'),
                                                number_format_i18n($meetings)
                                            )) ?>
                                        </li>
                                        <li class="locations<?php if (!$locations) { ?> hidden<?php } ?>">
                                            <?php echo esc_html(sprintf(
                                                // translators: %s is the number of locations
                                                _n('%s location', '%s locations', $locations, '12-step-meeting-list'),
                                                number_format_i18n($locations)
                                            )) ?>
                                        </li>
                                        <li class="groups<?php if (!$groups) { ?> hidden<?php } ?>">
                                            <?php echo esc_html(sprintf(
                                                // translators: %s is the number of groups
                                                _n('%s group', '%s groups', $groups, '12-step-meeting-list'),
                                                number_format_i18n($groups)
                                            )) ?>
                                        </li>
                                        <li class="regions<?php if (!$regions) { ?> hidden<?php } ?>">
                                            <?php echo esc_html(sprintf(
                                                // translators: %s is the number of regions
                                                _n('%s region', '%s regions', $regions, '12-step-meeting-list'),
                                                number_format_i18n($regions)
                                            )) ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                    </div>

                    <!-- Export Meeting List -->
                    <div class="postbox stack">
                            <h2>
                                <?php esc_html_e('Export Meeting List', '12-step-meeting-list') ?>
                            </h2>
                            <?php
                            if ($meetings) { ?>
                                <p>
                                    <a href="<?php echo esc_attr(admin_url('admin-ajax.php') . '?action=csv') ?>" target="_blank"
                                        class="button">
                                        <?php esc_html_e('Download CSV', '12-step-meeting-list') ?>
                                    </a>
                                    &nbsp;
                                    <a href="<?php echo esc_attr($pdf_link) ?>" target="_blank" class="button">
                                        <?php esc_html_e('Generate PDF', '12-step-meeting-list') ?>
                                    </a>
                                </p>

                            <?php } ?>
                            <p>
                                <?php echo wp_kses(sprintf(
                                    // translators: %s is the link to the contacts page
                                    __('Want to send a mass email to your contacts? <a href="%s" target="_blank">Click here</a> to see their email addresses.', '12-step-meeting-list'),
                                    admin_url('admin-ajax.php') . '?action=contacts&nonce=' . wp_create_nonce($tsml_nonce)
                                ), TSML_ALLOWED_HTML) ?>
                            </p>
                    </div>
                </div>

                <!-- Import Log -->
                <?php
                $log_entries = tsml_log_get(['count' => 5, 'type' => 'data_source']);
                if (count($log_entries)) { ?>
                    <div class="postbox stack">
                        <h2>
                            <?php esc_html_e('Import Log', '12-step-meeting-list') ?>
                        </h2>
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date', '12-step-meeting-list') ?></th>
                                    <th><?php esc_html_e('Info', '12-step-meeting-list') ?></th>
                                </tr>
                            </thead>
                            <tbody id="tsml_import_log_widget">
                                <?php foreach ($log_entries as $entry) {
                                    $msg = tsml_log_format_entry_msg($entry);
                                    if (!empty($entry['info'])) {
                                        $msg = $entry['info'] . '<br/>' . PHP_EOL . $msg;
                                    }
                                    ?>
                                    <tr class="log-table">
                                        <td>
                                            <?php echo tsml_date_localised(get_option('date_format'), intval($entry['timestamp'])); ?>
                                        </td>
                                        <td><?php echo $msg; ?></td>
                                    </tr>
                                    <?php
                                } ?>
                            </tbody>
                        </table>
                        <p>
                            <a href="<?php echo admin_url('edit.php?post_type=tsml_meeting&page=log'); ?>" class="button">
                                <?php esc_html_e('View full event log', '12-step-meeting-list') ?>
                            </a>
                        </p>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}
