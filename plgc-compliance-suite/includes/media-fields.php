<?php
/**
 * Media Library Fields
 *
 * Adds retention and accessibility fields to media attachments.
 * Categories are pulled from the settings page — fully customizable.
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

/**
 * Document MIME types we track.
 */
function plgc_docmgr_tracked_mimes() {
    return [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    ];
}

/**
 * Add fields to the media attachment edit screen.
 */
function plgc_docmgr_attachment_fields($form_fields, $post) {
    $mime = get_post_mime_type($post->ID);
    $is_document = in_array($mime, plgc_docmgr_tracked_mimes(), true);
    $is_image = strpos($mime, 'image/') === 0;

    // ================================================================
    // IMAGE-SPECIFIC FIELDS
    // ================================================================
    if ($is_image) {
        // --- Decorative Image Toggle ---
        $decorative = get_post_meta($post->ID, '_plgc_decorative', true);
        $alt_text   = get_post_meta($post->ID, '_wp_attachment_image_alt', true);

        $dec_html  = '<label style="display:inline-flex;align-items:center;gap:6px;">';
        $dec_html .= '<input type="checkbox" name="attachments[' . $post->ID . '][plgc_decorative]" value="1"' . checked($decorative, '1', false) . ' class="plgc-decorative-toggle" data-attachment-id="' . $post->ID . '" />';
        $dec_html .= ' This image is decorative (no alt text needed)';
        $dec_html .= '</label>';

        // Compliance indicator
        if ($decorative) {
            $dec_html .= '<br><span style="color:#567915;font-weight:600;margin-top:4px;display:inline-block;">✅ Marked decorative — compliant (renders as alt="")</span>';
        } elseif (! empty($alt_text)) {
            $dec_html .= '<br><span style="color:#567915;font-weight:600;margin-top:4px;display:inline-block;">✅ Alt text present — compliant</span>';
        } else {
            $dec_html .= '<br><span style="color:#d63638;font-weight:600;margin-top:4px;display:inline-block;">🔴 Missing alt text — add alt text above or mark as decorative</span>';
        }

        $form_fields['plgc_decorative'] = [
            'label' => 'Accessibility',
            'input' => 'html',
            'html'  => $dec_html,
        ];

        // --- Enhance existing field help text ---
        if (isset($form_fields['image_alt'])) {
            $form_fields['image_alt']['helps'] = '<strong>Required for accessibility.</strong> Describe the image for screen reader users. Should convey the same information a sighted user gets from the image. Leave blank ONLY if marked decorative above.';
        }

        // Caption help
        if (isset($form_fields['post_excerpt'])) {
            $form_fields['post_excerpt']['helps'] = '<strong>Optional — visible on page.</strong> Displays below the image when inserted into a post. Use for photo credits, context, or supplementary information visitors should see.';
        }

        // Description help
        if (isset($form_fields['post_content'])) {
            $form_fields['post_content']['helps'] = '<strong>Internal only — not shown to visitors.</strong> Use for notes, source info, or internal search. Does not appear on the front end or affect accessibility.';
        }

        return $form_fields;
    }

    // Only show full retention fields on documents
    if (! $is_document) {
        return $form_fields;
    }

    $categories = get_option('plgc_docmgr_categories', []);

    // --- Document Category (dynamic from settings) ---
    $current_cat = get_post_meta($post->ID, '_plgc_doc_category', true);
    $cat_html = '<select name="attachments[' . $post->ID . '][plgc_doc_category]" id="plgc_doc_cat_' . $post->ID . '" class="plgc-doc-category-select" data-attachment-id="' . $post->ID . '">';
    $cat_html .= '<option value="">— Select Category —</option>';
    foreach ($categories as $cat) {
        $cat_html .= '<option value="' . esc_attr($cat['slug']) . '" data-retention="' . esc_attr($cat['retention']) . '"' . selected($current_cat, $cat['slug'], false) . '>' . esc_html($cat['label']) . '</option>';
    }
    $cat_html .= '</select>';

    $form_fields['plgc_doc_category'] = [
        'label' => 'Document Category',
        'input' => 'html',
        'html'  => $cat_html,
        'helps' => 'Select a category to auto-suggest a review date based on the retention schedule.',
    ];

    // --- Review Date ---
    $retention_date = get_post_meta($post->ID, '_plgc_retention_date', true);
    $date_html  = '<input type="date" name="attachments[' . $post->ID . '][plgc_retention_date]" ';
    $date_html .= 'id="plgc_date_' . $post->ID . '" value="' . esc_attr($retention_date) . '" style="width: 200px;" />';
    $date_html .= ' <button type="button" class="button button-small plgc-clear-date" data-target="plgc_date_' . $post->ID . '">Clear</button>';

    if ($retention_date && strtotime($retention_date) < time()) {
        $date_html .= ' <span style="color: #d63638; font-weight: 600;">⚠ Overdue</span>';
    }

    $form_fields['plgc_retention_date'] = [
        'label' => 'Review Date',
        'input' => 'html',
        'html'  => $date_html,
        'helps' => 'When this document should be reviewed. Auto-calculated from category retention period, or set manually. Leave blank for no expiration.',
    ];

    // --- Accessibility Status ---
    $a11y_status = get_post_meta($post->ID, '_plgc_a11y_status', true) ?: 'unknown';
    $statuses = [
        'unknown'       => '⚪ Not Checked',
        'compliant'     => '🟢 Compliant',
        'non_compliant' => '🔴 Non-Compliant',
        'exempt'        => '⚫ Exempt',
    ];

    $status_html = '<select name="attachments[' . $post->ID . '][plgc_a11y_status]">';
    foreach ($statuses as $val => $label) {
        $status_html .= '<option value="' . esc_attr($val) . '"' . selected($a11y_status, $val, false) . '>' . esc_html($label) . '</option>';
    }
    $status_html .= '</select>';

    $form_fields['plgc_a11y_status'] = [
        'label' => 'Accessibility Status',
        'input' => 'html',
        'html'  => $status_html,
    ];

    // --- Accessibility Notes ---
    $form_fields['plgc_a11y_notes'] = [
        'label' => 'Accessibility Notes',
        'input' => 'textarea',
        'value' => get_post_meta($post->ID, '_plgc_a11y_notes', true) ?: '',
        'helps' => 'Remediation notes, vendor info, issues found.',
    ];

    // --- Lifecycle Status (read-only display) ---
    $lifecycle = get_post_meta($post->ID, '_plgc_lifecycle', true) ?: 'active';
    $lifecycle_labels = [
        'active'   => '<span style="color:#567915;font-weight:600;">● Active (Public)</span>',
        'review'   => '<span style="color:#FFAE40;font-weight:600;">⚠ Pending Review</span>',
        'archived' => '<span style="color:#666;">● Archived</span>',
    ];

    $lifecycle_html = $lifecycle_labels[$lifecycle] ?? $lifecycle_labels['active'];

    if ($lifecycle === 'review' || $lifecycle === 'archived') {
        $lifecycle_html .= ' <button type="button" class="button button-small plgc-restore-doc" data-id="' . $post->ID . '">Restore to Active</button>';
    }
    if ($lifecycle === 'active') {
        $lifecycle_html .= ' <button type="button" class="button button-small plgc-archive-doc" data-id="' . $post->ID . '">Archive Now</button>';
    }

    $form_fields['plgc_lifecycle'] = [
        'label' => 'Lifecycle Status',
        'input' => 'html',
        'html'  => $lifecycle_html,
    ];

    // --- Title II Exception Type (shows when archived) ---
    $exception = get_post_meta($post->ID, '_plgc_title2_exception', true);
    $exception_date = get_post_meta($post->ID, '_plgc_original_doc_date', true);
    $exception_notes = get_post_meta($post->ID, '_plgc_exception_notes', true);

    $exceptions = [
        ''                  => '— Not Applicable / None —',
        'archived_content'  => '📁 Archived Web Content (§35.200(b)(2)(i))',
        'preexisting_doc'   => '📄 Pre-Existing Document (§35.200(b)(2)(ii))',
        'third_party'       => '🔗 Third-Party Content (§35.200(b)(2)(iii))',
        'password_protected'=> '🔒 Password-Protected Individual Document (§35.200(b)(2)(iv))',
    ];

    $exc_html = '<select name="attachments[' . $post->ID . '][plgc_title2_exception]" id="plgc_exc_' . $post->ID . '">';
    foreach ($exceptions as $val => $label) {
        $exc_html .= '<option value="' . esc_attr($val) . '"' . selected($exception, $val, false) . '>' . esc_html($label) . '</option>';
    }
    $exc_html .= '</select>';

    // Exception-specific guidance
    $exc_html .= '<div id="plgc_exc_guidance_' . $post->ID . '" style="margin-top: 8px; padding: 8px; background: #f9f9f9; border-left: 3px solid #567915; font-size: 12px; display: ' . ($exception ? 'block' : 'none') . ';">';

    if ($exception === 'archived_content') {
        $exc_html .= '<strong>All four criteria must be met:</strong><br>';
        $exc_html .= '☐ Created before compliance deadline<br>';
        $exc_html .= '☐ Kept only for reference, research, or recordkeeping<br>';
        $exc_html .= '☐ Stored in dedicated archive area<br>';
        $exc_html .= '☐ Not modified since archiving<br>';
        $exc_html .= '<em>Must still provide accessible version upon request.</em>';
    } elseif ($exception === 'preexisting_doc') {
        $exc_html .= '<strong>Applies to:</strong> PDF, Word, Excel, PowerPoint files created before compliance deadline.<br>';
        $exc_html .= '<strong>Exception lost if:</strong> document is currently used to apply for, gain access to, or participate in services.<br>';
        $exc_html .= '<em>Does NOT need to be in the archive section, but must not be in active use.</em>';
    } elseif ($exception === 'third_party') {
        $exc_html .= '<strong>Applies to:</strong> Content posted by unaffiliated third parties (public comments, user submissions).<br>';
        $exc_html .= '<strong>Does NOT apply to:</strong> Content from contractors, vendors, or partners acting on behalf of the entity.';
    } elseif ($exception === 'password_protected') {
        $exc_html .= '<strong>Applies to:</strong> Individualized, password-protected documents (utility bills, tax documents).<br>';
        $exc_html .= '<strong>Note:</strong> The portal/system delivering these documents must still be accessible.';
    }

    $exc_html .= '</div>';

    $form_fields['plgc_title2_exception'] = [
        'label' => 'Title II Exception',
        'input' => 'html',
        'html'  => $exc_html,
        'helps' => 'If this document qualifies for a Title II exception, select which one. This is tracked for compliance documentation.',
    ];

    // --- Original Document Date ---
    $date_html = '<input type="date" name="attachments[' . $post->ID . '][plgc_original_doc_date]" ';
    $date_html .= 'value="' . esc_attr($exception_date) . '" style="width: 200px;" />';
    $date_html .= '<p class="description" style="margin-top: 4px;">When was this document originally created? Important for establishing it predates the compliance deadline.</p>';

    $form_fields['plgc_original_doc_date'] = [
        'label' => 'Original Document Date',
        'input' => 'html',
        'html'  => $date_html,
    ];

    // --- Exception Notes ---
    $form_fields['plgc_exception_notes'] = [
        'label' => 'Exception Notes',
        'input' => 'textarea',
        'value' => $exception_notes ?: '',
        'helps' => 'Document why this exception applies. This creates an audit trail for compliance reviews.',
    ];

    // --- Smart Title II Compliance Guidance ---
    $settings = get_option('plgc_docmgr_settings', []);
    $deadline_str = $settings['compliance_deadline'] ?? 'April 24, 2026';
    $deadline_ts  = strtotime($deadline_str);
    $original_ts  = ! empty($exception_date) ? strtotime($exception_date) : false;

    $guidance_html = '<div id="plgc_title2_guidance_' . $post->ID . '" class="plgc-title2-guidance" ';
    $guidance_html .= 'data-deadline="' . esc_attr(wp_date('Y-m-d', $deadline_ts)) . '" ';
    $guidance_html .= 'data-attachment-id="' . $post->ID . '" ';
    $guidance_html .= 'style="padding: 10px; border-radius: 4px; font-size: 13px; line-height: 1.5;">';

    if ($a11y_status === 'compliant' || $a11y_status === 'exempt') {
        $guidance_html .= '<span style="color: #567915;">✅ <strong>Compliant</strong> — This document meets accessibility standards.</span>';
    } elseif ($a11y_status === 'non_compliant') {
        if (! $original_ts) {
            $guidance_html .= '<span style="color: #FFAE40; background: #fff3cd; display: block; padding: 8px; border-left: 4px solid #FFAE40;">';
            $guidance_html .= '⚠ <strong>Original Document Date needed</strong> — Set the date above to determine if this document predates the compliance deadline (' . esc_html($deadline_str) . ') and may qualify for a pre-existing document exception.';
            $guidance_html .= '</span>';
        } elseif ($original_ts < $deadline_ts) {
            $guidance_html .= '<span style="color: #0073aa; background: #e8f0fe; display: block; padding: 8px; border-left: 4px solid #0073aa;">';
            $guidance_html .= 'ℹ <strong>Pre-deadline document</strong> — Created ' . esc_html(wp_date('M j, Y', $original_ts)) . ', before the ' . esc_html($deadline_str) . ' deadline. ';
            $guidance_html .= 'May qualify for the Pre-Existing Document exception (§35.200(b)(2)(ii)) <em>if not currently used to apply for, gain access to, or participate in services.</em>';
            if (empty($exception)) {
                $guidance_html .= '<br><strong>→ Select "Pre-Existing Document" in Title II Exception above if applicable, or remediate.</strong>';
            }
            $guidance_html .= '</span>';
        } else {
            $guidance_html .= '<span style="color: #d63638; background: #fef0f0; display: block; padding: 8px; border-left: 4px solid #d63638;">';
            $guidance_html .= '🔴 <strong>Post-deadline document</strong> — Created ' . esc_html(wp_date('M j, Y', $original_ts)) . ', after the ' . esc_html($deadline_str) . ' compliance deadline. ';
            $guidance_html .= 'This document <strong>must be remediated</strong> to meet WCAG 2.1 AA standards. No exception applies.';
            $guidance_html .= '</span>';
        }
    } else {
        // 'unknown' / not checked
        $guidance_html .= '<span style="color: #666;">Accessibility status not yet determined. Scan or manually set the status above.</span>';
    }

    $guidance_html .= '</div>';

    $form_fields['plgc_title2_guidance'] = [
        'label' => 'Compliance Guidance',
        'input' => 'html',
        'html'  => $guidance_html,
    ];

    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'plgc_docmgr_attachment_fields', 10, 2);

/**
 * Save custom fields.
 */
function plgc_docmgr_save_attachment_fields($post, $attachment) {
    // --- Handle decorative image toggle (applies to images) ---
    $mime = get_post_mime_type($post['ID']);
    if (strpos($mime, 'image/') === 0) {
        $decorative = ! empty($attachment['plgc_decorative']);
        if ($decorative) {
            update_post_meta($post['ID'], '_plgc_decorative', '1');
        } else {
            delete_post_meta($post['ID'], '_plgc_decorative');
        }
        return $post;
    }

    // --- Document fields only below this point ---
    if (! in_array($mime, plgc_docmgr_tracked_mimes(), true)) {
        return $post;
    }

    // Capture old values BEFORE saving so we can detect changes
    $old_cat  = get_post_meta($post['ID'], '_plgc_doc_category', true);
    $old_date = get_post_meta($post['ID'], '_plgc_retention_date', true);

    // Save all fields EXCEPT retention_date (handled separately below)
    $fields = [
        'plgc_doc_category'      => '_plgc_doc_category',
        'plgc_a11y_status'       => '_plgc_a11y_status',
        'plgc_a11y_notes'        => '_plgc_a11y_notes',
        'plgc_title2_exception'  => '_plgc_title2_exception',
        'plgc_original_doc_date' => '_plgc_original_doc_date',
        'plgc_exception_notes'   => '_plgc_exception_notes',
    ];

    foreach ($fields as $field_key => $meta_key) {
        if (isset($attachment[$field_key])) {
            update_post_meta($post['ID'], $meta_key, sanitize_text_field($attachment[$field_key]));
        }
    }

    // Handle retention date: recalculate on category change, otherwise save as-is
    $new_cat        = isset($attachment['plgc_doc_category']) ? sanitize_text_field($attachment['plgc_doc_category']) : $old_cat;
    $submitted_date = isset($attachment['plgc_retention_date']) ? sanitize_text_field($attachment['plgc_retention_date']) : '';
    $cat_changed    = ($new_cat !== $old_cat && ! empty($new_cat));

    if ($cat_changed && $submitted_date === $old_date) {
        // Category changed but user did NOT touch the date → recalculate from new category
        $retention = plgc_docmgr_get_retention_period($new_cat);
        if ($retention) {
            update_post_meta($post['ID'], '_plgc_retention_date', wp_date('Y-m-d', strtotime($retention)));
        }
    } elseif (! empty($submitted_date)) {
        // User set or kept a manual date → save it
        update_post_meta($post['ID'], '_plgc_retention_date', $submitted_date);
    } elseif (empty($old_date) && ! empty($new_cat)) {
        // No date at all yet → auto-calculate from category
        $retention = plgc_docmgr_get_retention_period($new_cat);
        if ($retention) {
            update_post_meta($post['ID'], '_plgc_retention_date', wp_date('Y-m-d', strtotime($retention)));
        }
    }

    // Set lifecycle to active if not already set
    $current_lifecycle = get_post_meta($post['ID'], '_plgc_lifecycle', true);
    if (empty($current_lifecycle)) {
        update_post_meta($post['ID'], '_plgc_lifecycle', 'active');
    }

    return $post;
}
add_filter('attachment_fields_to_save', 'plgc_docmgr_save_attachment_fields', 10, 2);

/**
 * AJAX: Archive a document.
 */
function plgc_docmgr_ajax_archive() {
    check_ajax_referer('plgc_docmgr_actions');
    $id = absint($_POST['attachment_id'] ?? 0);
    if (! $id || ! current_user_can('edit_post', $id)) {
        wp_send_json_error('Permission denied.');
    }
    plgc_docmgr_archive_document($id);
    wp_send_json_success('Document archived.');
}
add_action('wp_ajax_plgc_docmgr_archive', 'plgc_docmgr_ajax_archive');

/**
 * AJAX: Restore a document.
 */
function plgc_docmgr_ajax_restore() {
    check_ajax_referer('plgc_docmgr_actions');
    $id = absint($_POST['attachment_id'] ?? 0);
    if (! $id || ! current_user_can('edit_post', $id)) {
        wp_send_json_error('Permission denied.');
    }

    // Extend by the category's retention period
    $cat = get_post_meta($id, '_plgc_doc_category', true);
    $new_date = '';
    if ($cat) {
        $period = plgc_docmgr_get_retention_period($cat);
        $new_date = wp_date('Y-m-d', strtotime($period));
    }

    plgc_docmgr_restore_document($id, $new_date);
    wp_send_json_success('Document restored to active.');
}
add_action('wp_ajax_plgc_docmgr_restore', 'plgc_docmgr_ajax_restore');
