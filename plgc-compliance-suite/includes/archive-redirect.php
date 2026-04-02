<?php
/**
 * Archive Redirect
 *
 * Intercepts requests for archived documents and redirects
 * to a "document archived" landing page instead of returning
 * a 404 or serving the file.
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

/**
 * Intercept requests for archived attachment files.
 *
 * Hooks into template_redirect to catch direct file URL hits
 * for archived documents and redirect to the notice page.
 */
function plgc_docmgr_archive_redirect() {
    // Only check on attachment pages
    if (! is_attachment()) {
        return;
    }

    $id       = get_the_ID();
    $lifecycle = get_post_meta($id, '_plgc_lifecycle', true);
    $redirect  = get_post_meta($id, '_plgc_archived_redirect', true);

    if ($lifecycle !== 'archived' || ! $redirect) {
        return;
    }

    $settings     = get_option('plgc_docmgr_settings', []);
    $behavior     = $settings['archive_behavior'] ?? 'redirect';
    $archive_page = absint($settings['archive_page'] ?? 0);

    if ($behavior === 'redirect' && $archive_page) {
        $redirect_url = get_permalink($archive_page);
        if ($redirect_url) {
            // Add the original document title as a query param for context
            $doc_title = get_the_title($id);
            $redirect_url = add_query_arg('document', urlencode($doc_title), $redirect_url);
            wp_redirect($redirect_url, 301);
            exit;
        }
    }
}
add_action('template_redirect', 'plgc_docmgr_archive_redirect');

/**
 * Also intercept direct file URL requests via .htaccess-style redirect.
 * This catches requests for /wp-content/uploads/file.pdf directly
 * (not just the attachment page).
 */
function plgc_docmgr_intercept_direct_file($redirect_url, $requested_url) {
    // Check if the requested URL is an attachment file
    $upload_dir = wp_get_upload_dir();
    $upload_url = $upload_dir['baseurl'];

    if (strpos($requested_url, $upload_url) === false) {
        return $redirect_url;
    }

    // Try to find the attachment by URL
    $attachment_id = attachment_url_to_postid($requested_url);
    if (! $attachment_id) {
        return $redirect_url;
    }

    $lifecycle = get_post_meta($attachment_id, '_plgc_lifecycle', true);
    if ($lifecycle !== 'archived') {
        return $redirect_url;
    }

    $settings     = get_option('plgc_docmgr_settings', []);
    $archive_page = absint($settings['archive_page'] ?? 0);

    if ($archive_page) {
        $page_url = get_permalink($archive_page);
        $doc_title = get_the_title($attachment_id);
        return add_query_arg('document', urlencode($doc_title), $page_url);
    }

    return $redirect_url;
}
add_filter('redirect_canonical', 'plgc_docmgr_intercept_direct_file', 10, 2);

/**
 * Add noindex meta tag for noindex-archived documents.
 */
function plgc_docmgr_noindex_meta() {
    if (! is_attachment()) {
        return;
    }

    $noindex = get_post_meta(get_the_ID(), '_plgc_noindex', true);
    if ($noindex) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
add_action('wp_head', 'plgc_docmgr_noindex_meta');
