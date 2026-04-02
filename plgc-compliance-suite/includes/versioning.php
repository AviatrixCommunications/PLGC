<?php
/**
 * Document Versioning
 *
 * Tracks document versions in the media library. When a new
 * version of a document is uploaded, the old version can be
 * automatically archived and linked. Prevents duplicate
 * documents from appearing in search results.
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * VERSION LINKING
 * ============================================================
 * Documents can be linked as versions of each other.
 * The newest version is "current" and older ones are "superseded."
 */

/**
 * Add "Replaces Document" field to media upload.
 * Allows users to explicitly mark a new upload as replacing an existing doc.
 */
function plgc_docmgr_version_fields($form_fields, $post) {
    $mime = get_post_mime_type($post->ID);
    if (! in_array($mime, plgc_docmgr_tracked_mimes(), true)) {
        return $form_fields;
    }

    $replaces_id = get_post_meta($post->ID, '_plgc_replaces_doc', true);
    $replaced_by = get_post_meta($post->ID, '_plgc_replaced_by', true);
    $version_group = get_post_meta($post->ID, '_plgc_version_group', true);

    // --- "This Replaces" Field ---
    $replaces_html = '<div class="plgc-version-field">';

    if ($replaced_by) {
        // This document has been superseded
        $newer = get_post($replaced_by);
        if ($newer) {
            $replaces_html .= '<span style="color: #666;">⬆️ Superseded by: ';
            $replaces_html .= '<a href="' . get_edit_post_link($replaced_by) . '">' . esc_html($newer->post_title) . '</a>';
            $replaces_html .= '</span>';
        }
    }

    if ($replaces_id) {
        // This document replaces an older one
        $older = get_post($replaces_id);
        if ($older) {
            $replaces_html .= '<span style="color: #567915;">⬇️ Replaces: ';
            $replaces_html .= '<a href="' . get_edit_post_link($replaces_id) . '">' . esc_html($older->post_title) . '</a>';
            $replaces_html .= '</span>';
        }
    }

    // AJAX search field to find existing document to replace
    if (! $replaces_id && ! $replaced_by) {
        $replaces_html .= '<div class="plgc-version-search-wrap" style="position: relative;">';
        $replaces_html .= '<input type="hidden" name="attachments[' . $post->ID . '][plgc_replaces_doc]" id="plgc_replaces_id_' . $post->ID . '" value="" />';
        $replaces_html .= '<input type="text" class="plgc-version-search regular-text" data-attachment-id="' . $post->ID . '" data-mime="' . esc_attr($mime) . '" placeholder="Search by document title to find what this replaces..." style="width: 100%;" autocomplete="off" />';
        $replaces_html .= '<div class="plgc-version-results" id="plgc_version_results_' . $post->ID . '" style="display:none; position:absolute; z-index:9999; background:#fff; border:1px solid #ccc; max-height:200px; overflow-y:auto; width:100%; box-shadow:0 2px 8px rgba(0,0,0,0.15);"></div>';
        $replaces_html .= '<div class="plgc-version-selected" id="plgc_version_selected_' . $post->ID . '" style="display:none; margin-top:6px; padding:6px 10px; background:#f0f6e4; border-left:3px solid #567915; font-size:13px;"></div>';
        $replaces_html .= '</div>';
    }

    $replaces_html .= '</div>';

    // Show version history if this doc is part of a version group
    if ($version_group) {
        $versions = plgc_docmgr_get_version_chain($post->ID);
        if (count($versions) > 1) {
            $replaces_html .= '<div style="margin-top: 8px; padding: 8px; background: #f9f9f9; border-left: 3px solid #567915;">';
            $replaces_html .= '<strong>Version History:</strong><br>';
            foreach ($versions as $i => $v) {
                $is_current = empty(get_post_meta($v->ID, '_plgc_replaced_by', true));
                $label = esc_html($v->post_title) . ' — ' . get_the_date('M j, Y', $v->ID);
                if ($v->ID === $post->ID) {
                    $replaces_html .= '<span style="font-weight: 600;">→ ' . $label . ' (viewing)</span><br>';
                } elseif ($is_current) {
                    $replaces_html .= '<a href="' . get_edit_post_link($v->ID) . '">' . $label . '</a> <span style="color: #567915; font-weight: 600;">(current)</span><br>';
                } else {
                    $replaces_html .= '<a href="' . get_edit_post_link($v->ID) . '">' . $label . '</a> <span style="color: #999;">(archived)</span><br>';
                }
            }
            $replaces_html .= '</div>';
        }
    }

    $form_fields['plgc_version'] = [
        'label' => 'Version Control',
        'input' => 'html',
        'html'  => $replaces_html,
        'helps' => 'If this document replaces an older version, select it here. The old version will be automatically archived.',
    ];

    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'plgc_docmgr_version_fields', 15, 2);

/**
 * Save version relationship when set.
 */
function plgc_docmgr_save_version_fields($post, $attachment) {
    if (! isset($attachment['plgc_replaces_doc']) || empty($attachment['plgc_replaces_doc'])) {
        return $post;
    }

    $new_id = $post['ID'];
    $old_id = absint($attachment['plgc_replaces_doc']);

    if ($old_id <= 0 || $old_id === $new_id) {
        return $post;
    }

    plgc_docmgr_link_versions($new_id, $old_id);

    return $post;
}
add_filter('attachment_fields_to_save', 'plgc_docmgr_save_version_fields', 10, 2);

/**
 * AJAX: Search documents for version control autocomplete.
 */
function plgc_docmgr_ajax_version_search() {
    check_ajax_referer('plgc_docmgr_actions');

    if (! current_user_can('upload_files')) {
        wp_send_json_error('Permission denied.');
    }

    $search  = sanitize_text_field($_POST['search'] ?? '');
    $mime    = sanitize_text_field($_POST['mime'] ?? '');
    $exclude = absint($_POST['exclude'] ?? 0);

    if (strlen($search) < 2) {
        wp_send_json_success([]);
    }

    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 15,
        's'              => $search,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => '_plgc_replaced_by', 'compare' => 'NOT EXISTS'],
            ['key' => '_plgc_replaced_by', 'value' => ''],
        ],
    ];

    if ($mime) {
        $args['post_mime_type'] = $mime;
    }
    if ($exclude) {
        $args['exclude'] = [$exclude];
    }

    $docs = get_posts($args);
    $results = [];

    foreach ($docs as $doc) {
        $cat_slug = get_post_meta($doc->ID, '_plgc_doc_category', true);
        $cat_label = '';
        if ($cat_slug) {
            $categories = get_option('plgc_docmgr_categories', []);
            foreach ($categories as $cat) {
                if ($cat['slug'] === $cat_slug) { $cat_label = $cat['label']; break; }
            }
        }

        $results[] = [
            'id'       => $doc->ID,
            'title'    => $doc->post_title,
            'date'     => get_the_date('M j, Y', $doc->ID),
            'category' => $cat_label,
            'filename' => basename(get_attached_file($doc->ID)),
        ];
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_plgc_docmgr_version_search', 'plgc_docmgr_ajax_version_search');

/**
 * Link two documents as versions and archive the old one.
 *
 * @param int $new_id The new/current version attachment ID.
 * @param int $old_id The old/superseded version attachment ID.
 */
function plgc_docmgr_link_versions($new_id, $old_id) {
    // Set up the version relationship
    update_post_meta($new_id, '_plgc_replaces_doc', $old_id);
    update_post_meta($old_id, '_plgc_replaced_by', $new_id);

    // Create or inherit version group ID
    $group = get_post_meta($old_id, '_plgc_version_group', true);
    if (! $group) {
        $group = 'vg_' . $old_id; // Use the original doc ID as group seed
    }
    update_post_meta($new_id, '_plgc_version_group', $group);
    update_post_meta($old_id, '_plgc_version_group', $group);

    // Carry forward metadata from old doc to new doc (if not already set)
    $inherit_fields = ['_plgc_doc_category', '_plgc_retention_date'];
    foreach ($inherit_fields as $field) {
        $old_val = get_post_meta($old_id, $field, true);
        $new_val = get_post_meta($new_id, $field, true);
        if ($old_val && ! $new_val) {
            update_post_meta($new_id, $field, $old_val);
        }
    }

    // Archive the old version
    plgc_docmgr_archive_document($old_id);

    // Mark why it was archived
    update_post_meta($old_id, '_plgc_archive_reason', 'superseded');
    update_post_meta($old_id, '_plgc_archive_reason_detail',
        'Replaced by "' . get_the_title($new_id) . '" on ' . current_time('Y-m-d'));
}

/**
 * Get the full version chain for a document.
 *
 * @param int $attachment_id Any document in the chain.
 * @return array Array of WP_Post objects, oldest first.
 */
function plgc_docmgr_get_version_chain($attachment_id) {
    $group = get_post_meta($attachment_id, '_plgc_version_group', true);
    if (! $group) {
        $post = get_post($attachment_id);
        return $post ? [$post] : [];
    }

    $versions = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_key'       => '_plgc_version_group',
        'meta_value'     => $group,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);

    return $versions;
}

/**
 * ============================================================
 * DUPLICATE DETECTION ON UPLOAD
 * ============================================================
 * When a new document is uploaded, check for existing documents
 * with similar names and alert the user.
 */

/**
 * Check for potential duplicates after upload.
 */
function plgc_docmgr_check_duplicates($attachment_id) {
    $mime = get_post_mime_type($attachment_id);
    if (! in_array($mime, plgc_docmgr_tracked_mimes(), true)) {
        return;
    }

    $filename = basename(get_attached_file($attachment_id));
    $title    = get_the_title($attachment_id);

    // Normalize filename for comparison (remove dates, version numbers, extensions)
    $normalized = plgc_docmgr_normalize_filename($filename);

    // Search for existing documents with similar names
    $similar = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => $mime,
        'posts_per_page' => 10,
        'exclude'        => [$attachment_id],
        's'              => $normalized,
    ]);

    if (! empty($similar)) {
        // Store the potential duplicates for the admin notice
        $duplicate_ids = wp_list_pluck($similar, 'ID');
        update_post_meta($attachment_id, '_plgc_potential_duplicates', $duplicate_ids);
    }
}
add_action('add_attachment', 'plgc_docmgr_check_duplicates', 20);

/**
 * Normalize a filename for duplicate comparison.
 * Strips dates, version numbers, and common suffixes.
 *
 * @param string $filename The filename to normalize.
 * @return string Normalized search string.
 */
function plgc_docmgr_normalize_filename($filename) {
    // Remove file extension
    $name = pathinfo($filename, PATHINFO_FILENAME);

    // Remove common date patterns (2024, 2025, 01-2025, Jan2025, etc.)
    $name = preg_replace('/[-_]?\d{4}[-_]?\d{0,2}[-_]?\d{0,2}/', '', $name);
    $name = preg_replace('/[-_]?(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[-_]?\d{0,4}/i', '', $name);

    // Remove version indicators (v2, v3, _v2, -rev2, final, draft, etc.)
    $name = preg_replace('/[-_]?v\d+/i', '', $name);
    $name = preg_replace('/[-_]?(final|draft|revised|rev\d*|updated|new)/i', '', $name);

    // Replace separators with spaces
    $name = str_replace(['-', '_', '.'], ' ', $name);

    // Remove extra whitespace
    $name = trim(preg_replace('/\s+/', ' ', $name));

    return $name;
}

/**
 * Show duplicate warning on the attachment edit screen.
 */
function plgc_docmgr_duplicate_notice() {
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'attachment') {
        return;
    }

    global $post;
    if (! $post) return;

    $duplicates = get_post_meta($post->ID, '_plgc_potential_duplicates', true);
    if (empty($duplicates) || ! is_array($duplicates)) {
        return;
    }

    // Filter out archived and already-superseded docs
    $active_dupes = [];
    foreach ($duplicates as $dupe_id) {
        $lifecycle = get_post_meta($dupe_id, '_plgc_lifecycle', true);
        if ($lifecycle !== 'archived') {
            $dupe_post = get_post($dupe_id);
            if ($dupe_post) {
                $active_dupes[] = $dupe_post;
            }
        }
    }

    if (empty($active_dupes)) {
        // Clear the notice if all dupes are already archived
        delete_post_meta($post->ID, '_plgc_potential_duplicates');
        return;
    }

    echo '<div class="notice notice-warning">';
    echo '<p><strong>⚠️ Possible Duplicate Documents Detected:</strong> ';
    echo 'The following existing documents have similar names. If this is a newer version, ';
    echo 'use the <strong>Version Control</strong> field below to link them and auto-archive the old one.</p>';
    echo '<ul style="margin-left: 20px; list-style: disc;">';
    foreach ($active_dupes as $dupe) {
        $url = get_edit_post_link($dupe->ID);
        $date = get_the_date('M j, Y', $dupe->ID);
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($dupe->post_title) . '</a> (uploaded ' . esc_html($date) . ')</li>';
    }
    echo '</ul>';
    echo '</div>';
}
add_action('admin_notices', 'plgc_docmgr_duplicate_notice');

/**
 * Add "Superseded" indicator to the media columns.
 */
function plgc_docmgr_version_column_indicator($column, $id) {
    if ($column !== 'plgc_lifecycle') {
        return;
    }

    $replaced_by = get_post_meta($id, '_plgc_replaced_by', true);
    if ($replaced_by) {
        $newer = get_post($replaced_by);
        if ($newer) {
            echo '<br><small style="color: #999;">Replaced by: ' . esc_html($newer->post_title) . '</small>';
        }
    }
}
add_action('manage_media_custom_column', 'plgc_docmgr_version_column_indicator', 11, 2);

/**
 * ============================================================
 * SEARCH EXCLUSION FOR SUPERSEDED DOCUMENTS
 * ============================================================
 * Ensures only the current version appears in front-end search
 * and WP Engine Smart Search results.
 */

/**
 * Exclude superseded/archived documents from front-end search.
 * This prevents old versions from showing up in site search.
 */
function plgc_docmgr_exclude_from_search($query) {
    if (is_admin() || ! $query->is_search() || ! $query->is_main_query()) {
        return;
    }

    $meta_query = $query->get('meta_query') ?: [];

    // Exclude documents that have been replaced by a newer version
    $meta_query[] = [
        'relation' => 'OR',
        [
            'key'     => '_plgc_replaced_by',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'   => '_plgc_replaced_by',
            'value' => '',
        ],
    ];

    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'plgc_docmgr_exclude_from_search');

/**
 * Add noindex to superseded document attachment pages.
 * Belt-and-suspenders with the search exclusion.
 */
function plgc_docmgr_noindex_superseded() {
    if (! is_attachment()) {
        return;
    }

    $replaced_by = get_post_meta(get_the_ID(), '_plgc_replaced_by', true);
    if ($replaced_by) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
add_action('wp_head', 'plgc_docmgr_noindex_superseded');
