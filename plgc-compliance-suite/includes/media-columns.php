<?php
/**
 * Media Library Columns & Filters
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

// --- Custom Columns ---
function plgc_docmgr_media_columns($columns) {
    $columns['plgc_a11y']      = '♿';
    $columns['plgc_lifecycle'] = 'Status';
    $columns['plgc_retention'] = 'Review Date';
    $columns['plgc_category']  = 'Doc Type';
    $columns['plgc_exception'] = 'Exception';
    return $columns;
}
add_filter('manage_media_columns', 'plgc_docmgr_media_columns');

function plgc_docmgr_media_column_content($column, $id) {
    switch ($column) {
        case 'plgc_a11y':
            $mime = get_post_mime_type($id);
            if (strpos($mime, 'image/') === 0) {
                // Image: show alt text / decorative status
                $decorative = get_post_meta($id, '_plgc_decorative', true);
                $alt_text   = get_post_meta($id, '_wp_attachment_image_alt', true);
                if ($decorative) {
                    echo '<span title="Decorative image (compliant)" style="font-size:16px;">🟢</span>';
                } elseif (! empty($alt_text)) {
                    echo '<span title="Alt text present (compliant)" style="font-size:16px;">🟢</span>';
                } else {
                    echo '<span title="Missing alt text" style="font-size:16px;">🔴</span>';
                }
            } else {
                // Document: show a11y status
                $status = get_post_meta($id, '_plgc_a11y_status', true) ?: 'unknown';
                $icons = ['unknown' => '⚪', 'compliant' => '🟢', 'non_compliant' => '🔴', 'exempt' => '⚫'];
                $titles = ['unknown' => 'Not checked', 'compliant' => 'Compliant', 'non_compliant' => 'Non-compliant', 'exempt' => 'Exempt'];
                echo '<span title="' . esc_attr($titles[$status] ?? '') . '" style="font-size:16px;">' . ($icons[$status] ?? '⚪') . '</span>';
            }
            break;

        case 'plgc_lifecycle':
            $lc = get_post_meta($id, '_plgc_lifecycle', true) ?: 'active';
            $labels = [
                'active'   => '<span style="color:#567915;font-weight:600;">Active</span>',
                'review'   => '<span style="color:#FFAE40;font-weight:600;">⚠ Review</span>',
                'archived' => '<span style="color:#666;">Archived</span>',
            ];
            echo $labels[$lc] ?? $labels['active'];
            break;

        case 'plgc_retention':
            $date = get_post_meta($id, '_plgc_retention_date', true);
            if ($date) {
                $past = strtotime($date) < time();
                echo '<span style="' . ($past ? 'color:#d63638;font-weight:600;' : '') . '">' . esc_html(wp_date('M j, Y', strtotime($date))) . '</span>';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            break;

        case 'plgc_category':
            $slug = get_post_meta($id, '_plgc_doc_category', true);
            if ($slug) {
                $categories = get_option('plgc_docmgr_categories', []);
                $label = $slug;
                foreach ($categories as $cat) {
                    if ($cat['slug'] === $slug) { $label = $cat['label']; break; }
                }
                echo esc_html($label);
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            break;

        case 'plgc_exception':
            $exc = get_post_meta($id, '_plgc_title2_exception', true);
            $labels = [
                'archived_content'   => '📁 Archived',
                'preexisting_doc'    => '📄 Pre-Existing',
                'third_party'        => '🔗 Third-Party',
                'password_protected' => '🔒 Protected',
            ];
            echo $exc && isset($labels[$exc]) ? $labels[$exc] : '<span style="color:#999;">—</span>';
            break;
    }
}
add_action('manage_media_custom_column', 'plgc_docmgr_media_column_content', 10, 2);

// --- Sortable ---
function plgc_docmgr_sortable_columns($columns) {
    $columns['plgc_retention'] = 'plgc_retention';
    $columns['plgc_lifecycle'] = 'plgc_lifecycle';
    $columns['plgc_a11y']      = 'plgc_a11y';
    return $columns;
}
add_filter('manage_upload_sortable_columns', 'plgc_docmgr_sortable_columns');

function plgc_docmgr_sort_query($query) {
    if (! is_admin() || ! $query->is_main_query()) return;
    $ob = $query->get('orderby');
    if ($ob === 'plgc_retention')  { $query->set('meta_key', '_plgc_retention_date'); $query->set('orderby', 'meta_value'); $query->set('meta_type', 'DATE'); }
    if ($ob === 'plgc_lifecycle')  { $query->set('meta_key', '_plgc_lifecycle'); $query->set('orderby', 'meta_value'); }
    if ($ob === 'plgc_a11y')      { $query->set('meta_key', '_plgc_a11y_status'); $query->set('orderby', 'meta_value'); }
}
add_action('pre_get_posts', 'plgc_docmgr_sort_query');

// --- Filter Dropdowns ---
function plgc_docmgr_filter_dropdowns() {
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'upload') return;

    $cur_lc   = sanitize_text_field($_GET['plgc_lifecycle'] ?? '');
    $cur_a11y = sanitize_text_field($_GET['plgc_a11y_filter'] ?? '');
    $cur_cat  = sanitize_text_field($_GET['plgc_doc_cat'] ?? '');

    echo '<select name="plgc_lifecycle"><option value="">All Statuses</option>';
    foreach (['active' => 'Active', 'review' => '⚠ Pending Review', 'archived' => 'Archived'] as $v => $l)
        echo '<option value="' . $v . '"' . selected($cur_lc, $v, false) . '>' . $l . '</option>';
    echo '</select>';

    echo '<select name="plgc_a11y_filter"><option value="">All A11y</option>';
    foreach (['unknown' => '⚪ Not Checked', 'compliant' => '🟢 Compliant', 'non_compliant' => '🔴 Non-Compliant', 'exempt' => '⚫ Exempt'] as $v => $l)
        echo '<option value="' . $v . '"' . selected($cur_a11y, $v, false) . '>' . $l . '</option>';
    echo '</select>';

    $categories = get_option('plgc_docmgr_categories', []);
    echo '<select name="plgc_doc_cat"><option value="">All Categories</option>';
    foreach ($categories as $cat)
        echo '<option value="' . esc_attr($cat['slug']) . '"' . selected($cur_cat, $cat['slug'], false) . '>' . esc_html($cat['label']) . '</option>';
    echo '</select>';
}
add_action('restrict_manage_posts', 'plgc_docmgr_filter_dropdowns');

function plgc_docmgr_filter_query($query) {
    if (! is_admin() || ! $query->is_main_query()) return;
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'upload') return;

    $mq = $query->get('meta_query') ?: [];
    if (! empty($_GET['plgc_lifecycle']))   $mq[] = ['key' => '_plgc_lifecycle', 'value' => sanitize_text_field($_GET['plgc_lifecycle'])];
    if (! empty($_GET['plgc_a11y_filter'])) $mq[] = ['key' => '_plgc_a11y_status', 'value' => sanitize_text_field($_GET['plgc_a11y_filter'])];
    if (! empty($_GET['plgc_doc_cat']))     $mq[] = ['key' => '_plgc_doc_category', 'value' => sanitize_text_field($_GET['plgc_doc_cat'])];
    if (! empty($mq)) $query->set('meta_query', $mq);
}
add_action('pre_get_posts', 'plgc_docmgr_filter_query');

// --- Bulk Actions ---
function plgc_docmgr_bulk_actions($actions) {
    $actions['plgc_archive']        = 'Archive Documents';
    $actions['plgc_restore']        = 'Restore to Active';
    $actions['plgc_mark_compliant'] = 'Mark A11y Compliant';
    return $actions;
}
add_filter('bulk_actions-upload', 'plgc_docmgr_bulk_actions');

function plgc_docmgr_handle_bulk($redirect, $action, $ids) {
    switch ($action) {
        case 'plgc_archive':
            foreach ($ids as $id) plgc_docmgr_archive_document($id);
            $redirect = add_query_arg('plgc_bulk_archived', count($ids), $redirect);
            break;
        case 'plgc_restore':
            foreach ($ids as $id) plgc_docmgr_restore_document($id);
            $redirect = add_query_arg('plgc_bulk_restored', count($ids), $redirect);
            break;
        case 'plgc_mark_compliant':
            foreach ($ids as $id) update_post_meta($id, '_plgc_a11y_status', 'compliant');
            $redirect = add_query_arg('plgc_bulk_compliant', count($ids), $redirect);
            break;
    }
    return $redirect;
}
add_filter('handle_bulk_actions-upload', 'plgc_docmgr_handle_bulk', 10, 3);

function plgc_docmgr_bulk_notices() {
    if (isset($_GET['plgc_bulk_archived']))  printf('<div class="notice notice-success"><p>%d document(s) archived.</p></div>', (int) $_GET['plgc_bulk_archived']);
    if (isset($_GET['plgc_bulk_restored']))  printf('<div class="notice notice-success"><p>%d document(s) restored.</p></div>', (int) $_GET['plgc_bulk_restored']);
    if (isset($_GET['plgc_bulk_compliant'])) printf('<div class="notice notice-success"><p>%d document(s) marked compliant.</p></div>', (int) $_GET['plgc_bulk_compliant']);
}
add_action('admin_notices', 'plgc_docmgr_bulk_notices');

// --- Missing Alt Text Notice (only when images actually need alt text) ---
function plgc_docmgr_alt_text_notice() {
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'upload') return;

    // Count images (not PDFs/docs) missing alt text, excluding decorative images
    $missing = new WP_Query([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => false,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'],
                ['key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '='],
            ],
            [
                'relation' => 'OR',
                ['key' => '_plgc_decorative', 'compare' => 'NOT EXISTS'],
                ['key' => '_plgc_decorative', 'value' => '1', 'compare' => '!='],
            ],
        ],
    ]);

    if ($missing->found_posts > 0) {
        printf(
            '<div class="notice notice-info"><p><strong>Accessibility:</strong> %d image(s) missing alt text. <a href="%s">Show images missing alt text →</a></p></div>',
            $missing->found_posts,
            admin_url('upload.php?plgc_no_alt=1&mode=list')
        );
    }
}
add_action('admin_notices', 'plgc_docmgr_alt_text_notice');

function plgc_docmgr_no_alt_filter($query) {
    if (! is_admin() || ! $query->is_main_query() || ! isset($_GET['plgc_no_alt'])) return;
    $query->set('post_mime_type', 'image');
    $query->set('meta_query', [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            ['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'],
            ['key' => '_wp_attachment_image_alt', 'value' => ''],
        ],
        [
            'relation' => 'OR',
            ['key' => '_plgc_decorative', 'compare' => 'NOT EXISTS'],
            ['key' => '_plgc_decorative', 'value' => '1', 'compare' => '!='],
        ],
    ]);
}
add_action('pre_get_posts', 'plgc_docmgr_no_alt_filter');
