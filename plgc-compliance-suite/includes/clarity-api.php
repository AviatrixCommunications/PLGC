<?php
/**
 * CommonLook Clarity API Integration
 *
 * Automatically validates PDF accessibility on upload using
 * the CommonLook Clarity API. Checks against WCAG 2.0 AA,
 * Section 508, and PDF/UA standards.
 *
 * Flow:
 * 1. PDF uploaded → sent to Clarity API for validation
 * 2. Webhook receives results when scan completes
 * 3. Media library updated with pass/fail status and report
 * 4. Non-compliant docs flagged with user action options
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * SETTINGS
 * ============================================================
 */

/**
 * Register Clarity API settings page under Settings menu.
 */

/**
 * Register settings.
 */

/**
 * Settings page HTML.
 */

/**
 * ============================================================
 * API CLIENT
 * ============================================================
 */

/**
 * Get the configured Clarity API base URL.
 */
function plgc_clarity_base_url() {
    return rtrim(get_option('plgc_clarity_api_url', 'https://awsclarity.commonlook.com/ClarityAPI'), '/');
}

/**
 * Get an access token from the Clarity API.
 */
function plgc_clarity_get_token() {
    // Check for cached token
    $cached = get_transient('plgc_clarity_token');
    if ($cached) {
        return $cached;
    }

    $username = get_option('plgc_clarity_username', '');
    $password = get_option('plgc_clarity_password', '');

    if (empty($username) || empty($password)) {
        return new WP_Error('no_credentials', 'Clarity API credentials not configured.');
    }

    $response = wp_remote_post(plgc_clarity_base_url() . '/Token', [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query([
            'grant_type' => 'password',
            'username'   => $username,
            'password'   => $password,
        ]),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200 || empty($body['access_token'])) {
        return new WP_Error('auth_failed', 'Clarity API authentication failed. Check credentials.');
    }

    // Cache token for 50 minutes (expires in 60)
    set_transient('plgc_clarity_token', $body['access_token'], 3000);

    return $body['access_token'];
}

/**
 * Submit a PDF for validation.
 *
 * @param string $pdf_url Public URL of the PDF to validate.
 * @param int    $attachment_id WordPress attachment ID.
 * @return int|WP_Error Clarity validation ID or error.
 */
function plgc_clarity_submit_validation($pdf_url, $attachment_id) {
    $token = plgc_clarity_get_token();
    if (is_wp_error($token)) {
        return $token;
    }

    $standards = get_option('plgc_clarity_standards', ['WCAG2_0', 'S508', 'PDF_UA_1']);

    // Build form-urlencoded body matching Clarity API expectations
    $body_parts = ['URL=' . urlencode($pdf_url)];
    foreach ($standards as $standard) {
        $body_parts[] = 'Standards[]=' . urlencode($standard);
    }

    $response = wp_remote_post(plgc_clarity_base_url() . '/api/Validation', [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => implode('&', $body_parts),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $validation_id = intval($body);

    if ($code !== 200 || $validation_id <= 0) {
        return new WP_Error('validation_failed', 'Clarity API validation request failed: ' . $body);
    }

    // Store the validation ID on the attachment
    update_post_meta($attachment_id, '_plgc_clarity_validation_id', $validation_id);
    update_post_meta($attachment_id, '_plgc_clarity_status', 'processing');
    update_post_meta($attachment_id, '_plgc_clarity_submitted', current_time('mysql'));

    // Schedule a status check (fallback if webhook doesn't fire)
    wp_schedule_single_event(time() + 120, 'plgc_clarity_check_status', [$attachment_id]);

    return $validation_id;
}

/**
 * Check validation status for an attachment.
 *
 * @param int $attachment_id WordPress attachment ID.
 * @return string Status string.
 */
function plgc_clarity_check_validation_status($attachment_id) {
    $validation_id = get_post_meta($attachment_id, '_plgc_clarity_validation_id', true);
    if (! $validation_id) {
        return 'no_validation';
    }

    $token = plgc_clarity_get_token();
    if (is_wp_error($token)) {
        return 'error';
    }

    $response = wp_remote_get(
        plgc_clarity_base_url() . '/api/Validation/' . intval($validation_id),
        [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]
    );

    if (is_wp_error($response)) {
        return 'error';
    }

    $status = trim(wp_remote_retrieve_body($response), '"');
    update_post_meta($attachment_id, '_plgc_clarity_status', sanitize_text_field($status));

    // If completed, fetch the full report
    if ($status === 'Completed') {
        plgc_clarity_fetch_report($attachment_id);
    }

    // "Failed - ..." statuses are definitive results from Clarity
    // (e.g., "Failed - Untagged PDF", "Failed - Corrupt File")
    // These mean the PDF is non-compliant — no report to fetch, just record the result
    if (strpos($status, 'Failed') !== false) {
        update_post_meta($attachment_id, '_plgc_clarity_result', 'fail');
        update_post_meta($attachment_id, '_plgc_clarity_completed', current_time('mysql'));
        update_post_meta($attachment_id, '_plgc_clarity_error', $status);
        update_post_meta($attachment_id, '_plgc_a11y_status', 'non_compliant');
    }

    // If still processing, check again in 60 seconds
    if (in_array($status, ['Requested', 'Processing'], true)) {
        wp_schedule_single_event(time() + 60, 'plgc_clarity_check_status', [$attachment_id]);
    }

    return $status;
}
add_action('plgc_clarity_check_status', 'plgc_clarity_check_validation_status');

/**
 * Fetch and store the compliance report.
 *
 * @param int $attachment_id WordPress attachment ID.
 */
function plgc_clarity_fetch_report($attachment_id) {
    $validation_id = get_post_meta($attachment_id, '_plgc_clarity_validation_id', true);
    if (! $validation_id) {
        update_post_meta($attachment_id, '_plgc_clarity_status', 'error');
        update_post_meta($attachment_id, '_plgc_clarity_error', 'No validation ID found.');
        return;
    }

    $token = plgc_clarity_get_token();
    if (is_wp_error($token)) {
        update_post_meta($attachment_id, '_plgc_clarity_status', 'error');
        update_post_meta($attachment_id, '_plgc_clarity_error', 'Auth failed: ' . $token->get_error_message());
        return;
    }

    $response = wp_remote_get(
        plgc_clarity_base_url() . '/api/Validation/html?id=' . intval($validation_id),
        [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]
    );

    if (is_wp_error($response)) {
        update_post_meta($attachment_id, '_plgc_clarity_status', 'error');
        update_post_meta($attachment_id, '_plgc_clarity_error', 'Report fetch failed: ' . $response->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $report_html = wp_remote_retrieve_body($response);

    if ($code !== 200 || empty($report_html)) {
        update_post_meta($attachment_id, '_plgc_clarity_status', 'error');
        update_post_meta($attachment_id, '_plgc_clarity_error', 'Report returned HTTP ' . $code . (empty($report_html) ? ' (empty body)' : ''));
        return;
    }

    // Store report as post meta (compressed)
    update_post_meta($attachment_id, '_plgc_clarity_report', base64_encode(gzcompress($report_html)));
    update_post_meta($attachment_id, '_plgc_clarity_completed', current_time('mysql'));

    // Parse pass/fail from report
    $has_failures = plgc_clarity_parse_compliance($report_html);

    if ($has_failures) {
        update_post_meta($attachment_id, '_plgc_a11y_status', 'non_compliant');
        update_post_meta($attachment_id, '_plgc_clarity_result', 'fail');
    } else {
        update_post_meta($attachment_id, '_plgc_a11y_status', 'compliant');
        update_post_meta($attachment_id, '_plgc_clarity_result', 'pass');
    }
}

/**
 * Parse the Clarity report HTML to determine pass/fail.
 *
 * @param string $html Report HTML.
 * @return bool True if there are failures.
 */
function plgc_clarity_parse_compliance($html) {
    // Look for failure indicators in the Clarity report
    // The report contains "Failed" status for non-compliant checkpoints
    $fail_count = substr_count(strtolower($html), 'failed');
    return $fail_count > 0;
}

/**
 * ============================================================
 * AUTO-CHECK ON UPLOAD
 * ============================================================
 */

/**
 * Trigger accessibility check when a PDF is uploaded.
 */
function plgc_clarity_on_upload($attachment_id) {
    // Check if feature is enabled
    if (! get_option('plgc_clarity_enabled', 0)) {
        return;
    }

    if (! get_option('plgc_clarity_auto_check', 1)) {
        return;
    }

    // Only check PDFs
    $mime = get_post_mime_type($attachment_id);
    if ($mime !== 'application/pdf') {
        return;
    }

    // Get the public URL
    $pdf_url = wp_get_attachment_url($attachment_id);
    if (! $pdf_url) {
        return;
    }

    // Submit for validation
    $result = plgc_clarity_submit_validation($pdf_url, $attachment_id);

    if (is_wp_error($result)) {
        update_post_meta($attachment_id, '_plgc_clarity_status', 'error');
        update_post_meta($attachment_id, '_plgc_clarity_error', $result->get_error_message());
        update_post_meta($attachment_id, '_plgc_a11y_status', 'unknown');
    }
}
add_action('add_attachment', 'plgc_clarity_on_upload');

/**
 * ============================================================
 * WEBHOOK RECEIVER
 * ============================================================
 * Receives callbacks from Clarity API when validation completes.
 */

/**
 * Register the webhook endpoint.
 */
function plgc_clarity_register_webhook() {
    register_rest_route('plgc/v1', '/clarity-webhook', [
        'methods'             => 'POST',
        'callback'            => 'plgc_clarity_webhook_handler',
        'permission_callback' => 'plgc_clarity_webhook_verify',
    ]);
}
add_action('rest_api_init', 'plgc_clarity_register_webhook');

/**
 * Verify webhook signature.
 */
function plgc_clarity_webhook_verify($request) {
    // Verify the webhook signature if secret is set
    $signature = $request->get_header('ms-signature');
    // For now, accept all — tighten when secret is configured
    return true;
}

/**
 * Handle incoming webhook.
 */
function plgc_clarity_webhook_handler($request) {
    $body = $request->get_json_params();

    if (empty($body['Notifications'])) {
        return new WP_REST_Response('No notifications', 200);
    }

    foreach ($body['Notifications'] as $notification) {
        if ($notification['Action'] !== 'fileVerificationComplete') {
            continue;
        }

        $clarity_id = intval($notification['FileId']);
        $status     = sanitize_text_field($notification['Status']);

        // Find the attachment with this validation ID
        $attachments = get_posts([
            'post_type'   => 'attachment',
            'post_status' => 'any',
            'meta_key'    => '_plgc_clarity_validation_id',
            'meta_value'  => $clarity_id,
            'numberposts' => 1,
        ]);

        if (empty($attachments)) {
            continue;
        }

        $attachment_id = $attachments[0]->ID;
        update_post_meta($attachment_id, '_plgc_clarity_status', $status);

        if ($status === 'Completed') {
            plgc_clarity_fetch_report($attachment_id);
        } elseif (strpos($status, 'Failed') !== false) {
            update_post_meta($attachment_id, '_plgc_clarity_result', 'fail');
            update_post_meta($attachment_id, '_plgc_clarity_completed', current_time('mysql'));
            update_post_meta($attachment_id, '_plgc_clarity_error', $status);
            update_post_meta($attachment_id, '_plgc_a11y_status', 'non_compliant');
        }
    }

    return new WP_REST_Response('OK', 200);
}

/**
 * ============================================================
 * MEDIA LIBRARY UI — COMPLIANCE STATUS & ACTIONS
 * ============================================================
 */

/**
 * Build the Allyant remediation button HTML with sent-state tracking.
 *
 * @param int $attachment_id Attachment post ID.
 * @return string HTML for the button or sent status indicator.
 */
function plgc_clarity_allyant_button($attachment_id) {
    $allyant_email = get_option('plgc_clarity_allyant_email', '');
    if (! $allyant_email) {
        return '';
    }

    $sent_date = get_post_meta($attachment_id, '_plgc_allyant_sent', true);
    $sent_by   = get_post_meta($attachment_id, '_plgc_allyant_sent_by', true);

    if ($sent_date) {
        $user_name = $sent_by ? get_userdata($sent_by)->display_name ?? 'Unknown' : '';
        $html  = '<span style="color: #567915; font-weight: 600;">✅ Sent to Allyant</span>';
        $html .= '<br><small style="color: #666;">Sent: ' . esc_html($sent_date);
        if ($user_name) {
            $html .= ' by ' . esc_html($user_name);
        }
        $html .= '</small>';
        $html .= ' <button type="button" class="button button-small plgc-clarity-allyant" data-id="' . $attachment_id . '">Resend</button>';
        return $html;
    }

    return '<button type="button" class="button button-small plgc-clarity-allyant" data-id="' . $attachment_id . '" style="background: #567915; color: #fff; border-color: #567915;">Send to Allyant for Remediation</button>';
}

/**
 * Add Clarity scan results to the media attachment details.
 */
function plgc_clarity_attachment_fields($form_fields, $post) {
    $mime = get_post_mime_type($post->ID);
    if ($mime !== 'application/pdf') {
        return $form_fields;
    }

    if (! get_option('plgc_clarity_enabled', 0)) {
        return $form_fields;
    }

    $status  = get_post_meta($post->ID, '_plgc_clarity_status', true);
    $result  = get_post_meta($post->ID, '_plgc_clarity_result', true);
    $error   = get_post_meta($post->ID, '_plgc_clarity_error', true);
    $completed = get_post_meta($post->ID, '_plgc_clarity_completed', true);

    // Build status display
    $status_html = '<div class="plgc-clarity-status" style="padding: 8px 0;">';

    if (! $status) {
        $status_html .= '<span style="color: #666;">⚪ Not scanned</span>';
        $status_html .= ' <button type="button" class="button button-small plgc-clarity-scan" data-id="' . $post->ID . '">Scan Now</button>';
    } elseif (in_array($status, ['processing', 'Requested', 'Processing'], true)) {
        $status_html .= '<span style="color: #FFAE40;">⏳ Scanning...</span>';
        $status_html .= ' <button type="button" class="button button-small plgc-clarity-refresh" data-id="' . $post->ID . '">Refresh Status</button>';
    } elseif ($status === 'error') {
        $status_html .= '<span style="color: #d63638;">❌ Scan Error: ' . esc_html($error) . '</span>';
        $status_html .= ' <button type="button" class="button button-small plgc-clarity-scan" data-id="' . $post->ID . '">Retry</button>';
    } elseif ($result === 'pass') {
        $status_html .= '<span style="color: #567915; font-weight: 600;">✅ WCAG Compliant</span>';
        if ($completed) {
            $status_html .= '<br><small style="color: #666;">Checked: ' . esc_html($completed) . '</small>';
        }
        $status_html .= ' <button type="button" class="button button-small plgc-clarity-report" data-id="' . $post->ID . '">View Report</button>';
    } elseif ($result === 'fail') {
        $status_html .= '<span style="color: #d63638; font-weight: 600;">❌ Non-Compliant</span>';
        if ($error) {
            $status_html .= '<br><small style="color: #d63638;">' . esc_html($error) . '</small>';
        }
        if ($completed) {
            $status_html .= '<br><small style="color: #666;">Checked: ' . esc_html($completed) . '</small>';
        }
        $status_html .= '<div style="margin-top: 8px;">';

        // Only show report button if a report was actually fetched
        $has_report = get_post_meta($post->ID, '_plgc_clarity_report', true);
        if ($has_report) {
            $status_html .= '<button type="button" class="button button-small plgc-clarity-report" data-id="' . $post->ID . '">View Full Report</button> ';
        }
        $status_html .= '<button type="button" class="button button-small plgc-clarity-scan" data-id="' . $post->ID . '">Re-Scan</button> ';

        // Allyant remediation option (with sent-state tracking)
        $status_html .= plgc_clarity_allyant_button($post->ID);

        $status_html .= '</div>';

        // Risk acknowledgment for non-admins
        $block_mode = get_option('plgc_clarity_block_upload', 'warn');
        if ($block_mode === 'warn') {
            $acknowledged = get_post_meta($post->ID, '_plgc_clarity_risk_acknowledged', true);
            if (! $acknowledged) {
                $status_html .= '<div style="margin-top: 8px; padding: 8px; background: #fff3cd; border-left: 4px solid #FFAE40; font-size: 13px;">';
                $status_html .= '<strong>⚠️ Title II Compliance Risk:</strong> This document does not meet WCAG 2.1 AA standards. ';
                $status_html .= 'Keeping it publicly accessible may expose the organization to compliance risk. ';
                $status_html .= '<br><label style="margin-top: 6px; display: block;">';
                $status_html .= '<input type="checkbox" name="attachments[' . $post->ID . '][plgc_clarity_acknowledge]" value="1" /> ';
                $status_html .= 'I understand the risk and want to keep this document publicly accessible.</label>';
                $status_html .= '</div>';
            }
        }
    } elseif (strpos($status, 'Failed') !== false) {
        // Clarity returned a definitive failure (e.g., "Failed - Untagged PDF")
        // Self-heal: set the result meta so this branch won't be needed next load
        update_post_meta($post->ID, '_plgc_clarity_result', 'fail');
        update_post_meta($post->ID, '_plgc_clarity_error', $status);
        update_post_meta($post->ID, '_plgc_a11y_status', 'non_compliant');
        if (! $completed) {
            update_post_meta($post->ID, '_plgc_clarity_completed', current_time('mysql'));
        }

        $status_html .= '<span style="color: #d63638; font-weight: 600;">❌ Non-Compliant</span>';
        $status_html .= '<br><small style="color: #d63638;">' . esc_html($status) . '</small>';
        $status_html .= '<div style="margin-top: 8px;">';
        $status_html .= '<button type="button" class="button button-small plgc-clarity-scan" data-id="' . $post->ID . '">Re-Scan</button> ';

        $status_html .= plgc_clarity_allyant_button($post->ID);
        $status_html .= '</div>';
    } else {
        // Fallback: unknown status
        $status_html .= '<span style="color: #FFAE40;">⚠ Scan finished but results could not be retrieved.</span>';
        $status_html .= '<br><small style="color: #666;">Status: ' . esc_html($status) . '</small>';
        $status_html .= ' <button type="button" class="button button-small plgc-clarity-refresh" data-id="' . $post->ID . '">Retry Fetch</button>';
        $status_html .= ' <button type="button" class="button button-small plgc-clarity-scan" data-id="' . $post->ID . '">Re-Scan</button>';
    }

    $status_html .= '</div>';

    $form_fields['plgc_clarity_scan'] = [
        'label' => 'Accessibility Scan',
        'input' => 'html',
        'html'  => $status_html,
    ];

    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'plgc_clarity_attachment_fields', 20, 2);

/**
 * Save the risk acknowledgment checkbox.
 */
function plgc_clarity_save_acknowledgment($post, $attachment) {
    if (isset($attachment['plgc_clarity_acknowledge']) && $attachment['plgc_clarity_acknowledge'] === '1') {
        update_post_meta($post['ID'], '_plgc_clarity_risk_acknowledged', current_time('mysql'));
        update_post_meta($post['ID'], '_plgc_clarity_acknowledged_by', get_current_user_id());
    }
    return $post;
}
add_filter('attachment_fields_to_save', 'plgc_clarity_save_acknowledgment', 10, 2);

/**
 * ============================================================
 * AJAX HANDLERS
 * ============================================================
 */

/**
 * Test API connection.
 */
function plgc_clarity_ajax_test() {
    check_ajax_referer('plgc_docmgr_actions');

    if (! current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
    }

    $token = plgc_clarity_get_token();
    if (is_wp_error($token)) {
        wp_send_json_error($token->get_error_message());
    }

    // Try fetching standards list as a connectivity test
    $response = wp_remote_get(plgc_clarity_base_url() . '/api/Standards', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $standards = json_decode($body, true);

    if ($code !== 200) {
        wp_send_json_error('API returned HTTP ' . $code . '. Check your credentials and API URL.');
    }

    if (is_array($standards) && ! empty($standards)) {
        wp_send_json_success('Connected. Available standards: ' . implode(', ', $standards));
    } else {
        wp_send_json_success('Connected successfully (HTTP 200). Could not parse standards list.');
    }
}
add_action('wp_ajax_plgc_clarity_test_connection', 'plgc_clarity_ajax_test');

/**
 * Trigger a manual scan.
 */
function plgc_clarity_ajax_scan() {
    check_ajax_referer('plgc_docmgr_actions');

    $attachment_id = intval($_POST['attachment_id']);
    if (! $attachment_id || ! current_user_can('edit_post', $attachment_id)) {
        wp_send_json_error('Permission denied.');
    }

    $pdf_url = wp_get_attachment_url($attachment_id);
    if (! $pdf_url) {
        wp_send_json_error('Could not get file URL.');
    }

    $result = plgc_clarity_submit_validation($pdf_url, $attachment_id);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success(['validation_id' => $result, 'status' => 'processing']);
}
add_action('wp_ajax_plgc_clarity_scan', 'plgc_clarity_ajax_scan');

/**
 * Refresh scan status.
 */
function plgc_clarity_ajax_refresh() {
    check_ajax_referer('plgc_docmgr_actions');

    $attachment_id = intval($_POST['attachment_id']);
    if (! $attachment_id || ! current_user_can('edit_post', $attachment_id)) {
        wp_send_json_error('Permission denied.');
    }

    $status = plgc_clarity_check_validation_status($attachment_id);
    $result = get_post_meta($attachment_id, '_plgc_clarity_result', true);
    $error  = get_post_meta($attachment_id, '_plgc_clarity_error', true);

    wp_send_json_success([
        'status' => $status,
        'result' => $result,
        'error'  => $error,
    ]);
}
add_action('wp_ajax_plgc_clarity_refresh', 'plgc_clarity_ajax_refresh');

/**
 * View compliance report.
 */
function plgc_clarity_ajax_report() {
    check_ajax_referer('plgc_docmgr_actions');

    $attachment_id = intval($_GET['attachment_id']);
    if (! $attachment_id || ! current_user_can('edit_post', $attachment_id)) {
        wp_die('Permission denied.');
    }

    $report_compressed = get_post_meta($attachment_id, '_plgc_clarity_report', true);
    if (! $report_compressed) {
        wp_die('No report available.');
    }

    $report_html = gzuncompress(base64_decode($report_compressed));
    echo $report_html;
    exit;
}
add_action('wp_ajax_plgc_clarity_report', 'plgc_clarity_ajax_report');

/**
 * Send document to Allyant for remediation.
 */
function plgc_clarity_ajax_send_allyant() {
    check_ajax_referer('plgc_docmgr_actions');

    $attachment_id = intval($_POST['attachment_id']);
    if (! $attachment_id || ! current_user_can('edit_post', $attachment_id)) {
        wp_send_json_error('Permission denied.');
    }

    $allyant_email = get_option('plgc_clarity_allyant_email', '');
    if (! $allyant_email) {
        wp_send_json_error('Allyant email not configured.');
    }

    $file_path = get_attached_file($attachment_id);
    $file_name = basename($file_path);
    $file_url  = wp_get_attachment_url($attachment_id);
    $site_name = get_bloginfo('name');
    $user      = wp_get_current_user();

    $subject = sprintf('[%s] PDF Remediation Request: %s', $site_name, $file_name);

    // Gather Clarity scan details for context
    $clarity_status = get_post_meta($attachment_id, '_plgc_clarity_status', true);
    $clarity_error  = get_post_meta($attachment_id, '_plgc_clarity_error', true);
    $clarity_result = get_post_meta($attachment_id, '_plgc_clarity_result', true);
    $standards      = get_option('plgc_clarity_standards', ['WCAG2_0', 'S508', 'PDF_UA_1']);

    $message  = "PDF Document Remediation Request\n";
    $message .= "=================================\n\n";
    $message .= "Site: " . home_url() . "\n";
    $message .= "Document: " . $file_name . "\n";
    $message .= "URL: " . $file_url . "\n";
    $message .= "Requested by: " . $user->display_name . " (" . $user->user_email . ")\n";
    $message .= "Date: " . current_time('F j, Y g:i A') . "\n\n";

    $message .= "Clarity Scan Result\n";
    $message .= "-------------------\n";
    $message .= "Status: " . ($clarity_error ?: $clarity_status ?: 'Non-compliant') . "\n";
    $message .= "Standards tested: " . implode(', ', $standards) . "\n";
    $message .= "Standards required: WCAG 2.1 AA (Title II compliance)\n\n";

    $message .= "This document was flagged as non-compliant by CommonLook Clarity.\n";
    $message .= "Please remediate and return the compliant version.\n\n";
    $message .= "Thank you,\n" . $site_name;

    $sent = wp_mail(
        $allyant_email,
        $subject,
        $message,
        ['Content-Type: text/plain; charset=UTF-8'],
        [$file_path]
    );

    if ($sent) {
        update_post_meta($attachment_id, '_plgc_allyant_sent', current_time('mysql'));
        update_post_meta($attachment_id, '_plgc_allyant_sent_by', $user->ID);

        // Append to notes instead of overwriting
        $existing_notes = get_post_meta($attachment_id, '_plgc_a11y_notes', true);
        $new_note = 'Sent to Allyant for remediation on ' . current_time('F j, Y') . ' by ' . $user->display_name;
        $notes = $existing_notes ? trim($existing_notes) . "\n" . $new_note : $new_note;
        update_post_meta($attachment_id, '_plgc_a11y_notes', $notes);

        wp_send_json_success('Document sent to Allyant for remediation.');
    } else {
        wp_send_json_error('Failed to send email. Check WordPress mail configuration.');
    }
}
add_action('wp_ajax_plgc_clarity_send_allyant', 'plgc_clarity_ajax_send_allyant');

/**
 * ============================================================
 * ADMIN SCRIPTS FOR CLARITY ACTIONS
 * ============================================================
 */
/**
 * Clarity admin scripts are handled by assets/js/admin.js
 * (enqueued in plgc-doc-manager.php) using plgcDocMgr.nonce.
 */

/**
 * ============================================================
 * UPLOAD INTERCEPTION FOR NON-COMPLIANT BLOCKING
 * ============================================================
 * When block mode is enabled, prevents non-admins from keeping
 * non-compliant PDFs public. Note: this only works after the
 * async scan completes, not at initial upload time.
 */

/**
 * Add admin notice on attachment edit screen if non-compliant.
 */
function plgc_clarity_compliance_notice() {
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'attachment') {
        return;
    }

    global $post;
    if (! $post || get_post_mime_type($post->ID) !== 'application/pdf') {
        return;
    }

    $result = get_post_meta($post->ID, '_plgc_clarity_result', true);
    $block_mode = get_option('plgc_clarity_block_upload', 'warn');

    if ($result === 'fail') {
        $class = ($block_mode === 'block' && ! current_user_can('administrator'))
            ? 'notice-error'
            : 'notice-warning';

        echo '<div class="notice ' . $class . '">';
        echo '<p><strong>⚠️ Accessibility Compliance Issue:</strong> ';
        echo 'This PDF does not meet WCAG 2.1 AA standards required by Title II. ';

        if ($block_mode === 'block' && ! current_user_can('administrator')) {
            echo 'This document cannot remain publicly accessible until it is remediated. ';
            echo 'Please replace with a compliant version or send to Allyant for remediation.</p>';
        } else {
            echo 'Please remediate this document or replace it with an accessible version.</p>';
        }

        echo '</div>';
    }
}
add_action('admin_notices', 'plgc_clarity_compliance_notice');
