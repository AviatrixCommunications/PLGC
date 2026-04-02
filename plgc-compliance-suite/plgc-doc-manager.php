<?php
/**
 * Plugin Name: Aviatrix Compliance Suite
 * Plugin URI: https://aviatrixcommunications.com
 * Description: Document lifecycle management, accessibility tracking, CommonLook Clarity API scanning, and Monsido web governance for Title II WCAG 2.1 AA compliance. Built by Aviatrix Communications for airport authorities and public entities.
 * Version: 1.6.0
 * Author: Aviatrix Communications
 * Author URI: https://aviatrixcommunications.com
 * License: Proprietary
 * Text Domain: plgc-docmgr
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('PLGC_DOCMGR_VERSION', '1.6.0');
define('PLGC_DOCMGR_DIR', plugin_dir_path(__FILE__));
define('PLGC_DOCMGR_URI', plugin_dir_url(__FILE__));
define('PLGC_DOCMGR_FILE', __FILE__);
define('PLGC_DOCMGR_BASENAME', plugin_basename(__FILE__));

/**
 * Load plugin modules.
 */
function plgc_docmgr_init() {
    require_once PLGC_DOCMGR_DIR . 'includes/settings.php';
    require_once PLGC_DOCMGR_DIR . 'includes/retention.php';
    require_once PLGC_DOCMGR_DIR . 'includes/media-fields.php';
    require_once PLGC_DOCMGR_DIR . 'includes/media-columns.php';
    require_once PLGC_DOCMGR_DIR . 'includes/archive-redirect.php';
    require_once PLGC_DOCMGR_DIR . 'includes/clarity-api.php';
    require_once PLGC_DOCMGR_DIR . 'includes/versioning.php';
    require_once PLGC_DOCMGR_DIR . 'includes/archive-page.php';
    require_once PLGC_DOCMGR_DIR . 'includes/dashboard-widget.php';
    require_once PLGC_DOCMGR_DIR . 'includes/monsido-integration.php';
}
add_action('plugins_loaded', 'plgc_docmgr_init');

/**
 * Run lightweight migrations on version change.
 */
function plgc_docmgr_maybe_migrate() {
    $db_version = get_option('plgc_docmgr_db_version', '0');

    // v1.4.0: Consolidate a11y statuses (remediated → compliant, needs_work → non_compliant)
    if (version_compare($db_version, '1.4.0', '<')) {
        global $wpdb;
        $wpdb->update($wpdb->postmeta, ['meta_value' => 'compliant'], ['meta_key' => '_plgc_a11y_status', 'meta_value' => 'remediated']);
        $wpdb->update($wpdb->postmeta, ['meta_value' => 'non_compliant'], ['meta_key' => '_plgc_a11y_status', 'meta_value' => 'needs_work']);
        update_option('plgc_docmgr_db_version', '1.4.0');
    }

    // v1.6.0: Remove social_media exception (no longer a valid exception for WP documents)
    if (version_compare($db_version, '1.6.0', '<')) {
        global $wpdb;
        $wpdb->update($wpdb->postmeta, ['meta_value' => ''], ['meta_key' => '_plgc_title2_exception', 'meta_value' => 'social_media']);
        update_option('plgc_docmgr_db_version', '1.6.0');
    }
}
add_action('admin_init', 'plgc_docmgr_maybe_migrate');

/**
 * Activation hook — set defaults.
 */
function plgc_docmgr_activate() {
    // Default categories if none exist
    if (! get_option('plgc_docmgr_categories')) {
        update_option('plgc_docmgr_categories', [
            ['slug' => 'board_minutes',  'label' => 'Board Minutes',     'retention' => '7 years'],
            ['slug' => 'financial',      'label' => 'Financial Report',   'retention' => '7 years'],
            ['slug' => 'policy',         'label' => 'Policy Document',    'retention' => '5 years'],
            ['slug' => 'contract',       'label' => 'Contract/Agreement', 'retention' => '3 years'],
            ['slug' => 'marketing',      'label' => 'Marketing Material', 'retention' => '1 year'],
            ['slug' => 'menu',           'label' => 'Menu/Price List',    'retention' => '6 months'],
            ['slug' => 'form',           'label' => 'Application/Form',   'retention' => '2 years'],
            ['slug' => 'newsletter',     'label' => 'Newsletter',         'retention' => '2 years'],
            ['slug' => 'event',          'label' => 'Event Document',     'retention' => '1 year'],
            ['slug' => 'other',          'label' => 'Other',              'retention' => '2 years'],
        ]);
    }

    // Default settings
    if (! get_option('plgc_docmgr_settings')) {
        update_option('plgc_docmgr_settings', [
            'archive_behavior'  => 'redirect',   // redirect | private | noindex
            'archive_page'      => 0,            // Page ID for archive landing page
            'notify_email'      => get_option('admin_email'),
            'notify_days_before' => 30,
            'auto_archive'      => false,
        ]);
    }

    // Schedule daily retention check
    if (! wp_next_scheduled('plgc_docmgr_daily_check')) {
        wp_schedule_event(time(), 'daily', 'plgc_docmgr_daily_check');
    }

    // Flush rewrite rules for the archive redirect endpoint
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'plgc_docmgr_activate');

/**
 * Deactivation hook.
 */
function plgc_docmgr_deactivate() {
    wp_clear_scheduled_hook('plgc_docmgr_daily_check');
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'plgc_docmgr_deactivate');

/**
 * Enqueue admin assets on relevant screens.
 */
function plgc_docmgr_admin_assets($hook) {
    if (in_array($hook, ['upload.php', 'post.php', 'settings_page_plgc-docmgr'])) {
        wp_enqueue_style(
            'plgc-docmgr-admin',
            PLGC_DOCMGR_URI . 'assets/css/admin.css',
            [],
            PLGC_DOCMGR_VERSION
        );
        wp_enqueue_script(
            'plgc-docmgr-admin',
            PLGC_DOCMGR_URI . 'assets/js/admin.js',
            ['jquery'],
            PLGC_DOCMGR_VERSION,
            true
        );
        wp_localize_script('plgc-docmgr-admin', 'plgcDocMgr', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('plgc_docmgr_actions'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'plgc_docmgr_admin_assets');

/**
 * Add settings link to plugins page.
 */
function plgc_docmgr_plugin_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=plgc-docmgr') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . PLGC_DOCMGR_BASENAME, 'plgc_docmgr_plugin_links');
