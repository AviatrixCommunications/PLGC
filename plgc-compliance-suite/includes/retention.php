<?php
/**
 * Retention Logic
 *
 * Daily cron to check review dates, flag/archive documents,
 * and send notification emails.
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

/**
 * Get the default retention period for a category.
 *
 * @param string $category_slug Category slug.
 * @return string PHP strtotime-compatible string (e.g., "+3 years").
 */
function plgc_docmgr_get_retention_period($category_slug) {
    $categories = get_option('plgc_docmgr_categories', []);

    foreach ($categories as $cat) {
        if ($cat['slug'] === $category_slug && ! empty($cat['retention'])) {
            return '+' . $cat['retention'];
        }
    }

    return '+2 years'; // Fallback default
}

/**
 * Calculate a review date from a retention period string.
 *
 * @param string $retention e.g., "3 years", "6 months", "18 months"
 * @param string $from_date Date to calculate from (Y-m-d). Defaults to today.
 * @return string Y-m-d formatted date.
 */
function plgc_docmgr_calculate_review_date($retention, $from_date = '') {
    if (empty($from_date)) {
        $from_date = wp_date('Y-m-d');
    }
    return wp_date('Y-m-d', strtotime('+' . $retention, strtotime($from_date)));
}

/**
 * Daily retention check (runs via WP-Cron).
 */
function plgc_docmgr_daily_check() {
    $settings = get_option('plgc_docmgr_settings', []);
    $today    = wp_date('Y-m-d');

    // --- Check for documents past their review date ---
    $expired = new WP_Query([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_plgc_retention_date',
                'value'   => $today,
                'compare' => '<=',
                'type'    => 'DATE',
            ],
            [
                'key'     => '_plgc_lifecycle',
                'value'   => 'active',
            ],
        ],
    ]);

    $flagged  = 0;
    $archived = 0;

    if ($expired->have_posts()) {
        $auto_archive = ! empty($settings['auto_archive']);

        while ($expired->have_posts()) {
            $expired->the_post();
            $id = get_the_ID();

            if ($auto_archive) {
                plgc_docmgr_archive_document($id);
                $archived++;
            } else {
                update_post_meta($id, '_plgc_lifecycle', 'review');
                $flagged++;
            }
        }
        wp_reset_postdata();
    }

    // --- Advance warning for documents expiring soon ---
    $warn_days = absint($settings['notify_days_before'] ?? 30);
    $warn_date = wp_date('Y-m-d', strtotime('+' . $warn_days . ' days'));

    $expiring_soon = new WP_Query([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_plgc_retention_date',
                'value'   => [$today, $warn_date],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ],
            [
                'key'     => '_plgc_lifecycle',
                'value'   => 'active',
            ],
            [
                'key'     => '_plgc_advance_warning_sent',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);

    $warned = 0;
    if ($expiring_soon->have_posts()) {
        while ($expiring_soon->have_posts()) {
            $expiring_soon->the_post();
            update_post_meta(get_the_ID(), '_plgc_advance_warning_sent', $today);
            $warned++;
        }
        wp_reset_postdata();
    }

    // --- Send consolidated notification ---
    if ($flagged > 0 || $archived > 0 || $warned > 0) {
        plgc_docmgr_send_notification($flagged, $archived, $warned, $settings);
    }
}
add_action('plgc_docmgr_daily_check', 'plgc_docmgr_daily_check');

/**
 * Archive a document.
 *
 * @param int $attachment_id Attachment post ID.
 */
function plgc_docmgr_archive_document($attachment_id) {
    $settings = get_option('plgc_docmgr_settings', []);
    $behavior = $settings['archive_behavior'] ?? 'redirect';

    update_post_meta($attachment_id, '_plgc_lifecycle', 'archived');
    update_post_meta($attachment_id, '_plgc_archived_date', wp_date('Y-m-d'));

    switch ($behavior) {
        case 'private':
            wp_update_post([
                'ID'          => $attachment_id,
                'post_status' => 'private',
            ]);
            break;

        case 'noindex':
            update_post_meta($attachment_id, '_plgc_noindex', '1');
            break;

        case 'redirect':
        default:
            // Document stays public but the redirect handler intercepts access
            update_post_meta($attachment_id, '_plgc_archived_redirect', '1');
            break;
    }
}

/**
 * Restore a document to active status.
 *
 * @param int $attachment_id Attachment post ID.
 * @param string $new_review_date Optional new review date (Y-m-d).
 */
function plgc_docmgr_restore_document($attachment_id, $new_review_date = '') {
    update_post_meta($attachment_id, '_plgc_lifecycle', 'active');
    delete_post_meta($attachment_id, '_plgc_archived_date');
    delete_post_meta($attachment_id, '_plgc_archived_redirect');
    delete_post_meta($attachment_id, '_plgc_noindex');
    delete_post_meta($attachment_id, '_plgc_advance_warning_sent');

    // Restore to public if it was made private
    $post = get_post($attachment_id);
    if ($post && $post->post_status === 'private') {
        wp_update_post([
            'ID'          => $attachment_id,
            'post_status' => 'inherit',
        ]);
    }

    // Set new review date if provided
    if ($new_review_date) {
        update_post_meta($attachment_id, '_plgc_retention_date', $new_review_date);
    }
}

/**
 * Send notification email about retention actions.
 */
function plgc_docmgr_send_notification($flagged, $archived, $warned, $settings) {
    $email     = $settings['notify_email'] ?? get_option('admin_email');
    $site_name = get_bloginfo('name');

    $subject = sprintf('[%s] Document Retention Report', $site_name);

    $message  = "Document Retention Report — " . wp_date('F j, Y') . "\n";
    $message .= str_repeat('=', 50) . "\n\n";

    if ($warned > 0) {
        $message .= sprintf("📅 %d document(s) have review dates approaching within %d days.\n\n",
            $warned, $settings['notify_days_before'] ?? 30);
    }

    if ($flagged > 0) {
        $message .= sprintf("⚠️ %d document(s) have reached their review date and need a decision.\n", $flagged);
        $message .= "   → Review: " . admin_url('upload.php?plgc_lifecycle=review&mode=list') . "\n\n";
    }

    if ($archived > 0) {
        $message .= sprintf("📦 %d document(s) were automatically archived.\n", $archived);
        $message .= "   → Archived: " . admin_url('upload.php?plgc_lifecycle=archived&mode=list') . "\n\n";
    }

    $message .= "Manage settings: " . admin_url('options-general.php?page=plgc-docmgr') . "\n\n";
    $message .= "— " . $site_name . " Compliance Suite";

    wp_mail($email, $subject, $message);
}
