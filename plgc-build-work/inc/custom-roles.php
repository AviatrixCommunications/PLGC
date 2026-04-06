<?php
/**
 * Custom Roles & Capability Management
 *
 * Creates a 'Site Manager' role for Prairie Landing Golf Club staff.
 * Site Managers can manage all content, WooCommerce, Events, Forms,
 * Users, and Job Openings — but cannot install plugins, switch themes,
 * edit code, or access WordPress core settings.
 *
 * Also retains the legacy 'PLGC Client' role (read-only + basic edits)
 * for external stakeholders who need limited dashboard access.
 *
 * Safety rails:
 *   - editable_roles filter prevents assigning Administrator
 *   - map_meta_cap prevents editing/deleting admin accounts
 *   - Redirect guards block direct URL access to restricted screens
 *   - Login redirect sends Site Managers to the Dashboard
 *
 * @package PLGC
 * @since 1.7.37
 */

defined('ABSPATH') || exit;


/* ═══════════════════════════════════════════════════════════════════════
 * ROLE DEFINITIONS
 * ═══════════════════════════════════════════════════════════════════════ */

/**
 * Register (or update) the Site Manager and PLGC Client roles.
 *
 * Runs on every admin page load, but only writes to the DB when the
 * stored role version doesn't match. Bump PLGC_ROLE_VERSION whenever
 * capabilities change so roles get re-registered on next page load.
 */
define('PLGC_ROLE_VERSION', '2.0.3');

function plgc_register_custom_roles() {
    $stored = get_option('plgc_role_version', '0');
    if ($stored === PLGC_ROLE_VERSION) {
        return;
    }

    // ── Site Manager ─────────────────────────────────────────────────
    // Full content control + WooCommerce + Events + Forms + Users.
    // Does NOT get: install_plugins, switch_themes, edit_themes,
    // manage_options, unfiltered_html, unfiltered_upload, import, export.

    remove_role('plgc_site_manager');
    add_role('plgc_site_manager', __('Site Manager', 'plgc'), [

        // ── Core WordPress ──────────────────────────────────────────
        'read'                       => true,

        // Pages
        'edit_pages'                 => true,
        'edit_published_pages'       => true,
        'edit_others_pages'          => true,
        'publish_pages'              => true,
        'delete_pages'               => true,
        'delete_published_pages'     => true,
        'delete_others_pages'        => true,
        'delete_private_pages'       => true,
        'edit_private_pages'         => true,
        'read_private_pages'         => true,

        // Posts (News & Updates)
        'edit_posts'                 => true,
        'edit_published_posts'       => true,
        'edit_others_posts'          => true,
        'publish_posts'              => true,
        'delete_posts'               => true,
        'delete_published_posts'     => true,
        'delete_others_posts'        => true,
        'delete_private_posts'       => true,
        'edit_private_posts'         => true,
        'read_private_posts'         => true,

        // Media
        'upload_files'               => true,

        // Users — controlled via filters below
        'list_users'                 => true,
        'create_users'               => true,
        'edit_users'                 => true,
        'delete_users'               => true,
        'promote_users'              => true,

        // Theme options — needed for nav menu management
        'edit_theme_options'         => true,

        // ── WooCommerce ─────────────────────────────────────────────
        'manage_woocommerce'         => true,
        'view_woocommerce_reports'   => true,
        'edit_shop_orders'           => true,
        'read_shop_orders'           => true,
        'delete_shop_orders'         => true,
        'publish_shop_orders'        => true,
        'edit_others_shop_orders'    => true,
        'delete_others_shop_orders'  => true,
        'edit_shop_coupons'          => true,
        'read_shop_coupons'          => true,
        'delete_shop_coupons'        => true,
        'publish_shop_coupons'       => true,
        'edit_others_shop_coupons'   => true,
        'delete_others_shop_coupons' => true,
        'edit_products'              => true,
        'read_products'              => true,
        'delete_products'            => true,
        'publish_products'           => true,
        'edit_others_products'       => true,
        'delete_others_products'     => true,
        'edit_published_products'    => true,
        'delete_published_products'  => true,

        // ── Gravity Forms ───────────────────────────────────────────
        // Full form management — create, edit, delete forms + entries
        'gravityforms_view_entries'     => true,
        'gravityforms_edit_entries'     => true,
        'gravityforms_delete_entries'   => true,
        'gravityforms_export_entries'   => true,
        'gravityforms_view_entry_notes' => true,
        'gravityforms_edit_entry_notes' => true,
        'gravityforms_preview_forms'    => true,
        'gravityforms_edit_forms'       => true,
        'gravityforms_create_form'      => true,
        'gravityforms_delete_forms'     => true,
        // Admin-only: plugin settings, add-ons, uninstall
        'gravityforms_edit_settings'    => false,
        'gravityforms_uninstall'        => false,
        'gravityforms_view_addons'      => false,

        // ── Events Calendar ─────────────────────────────────────────
        'edit_tribe_events'              => true,
        'edit_published_tribe_events'    => true,
        'edit_others_tribe_events'       => true,
        'publish_tribe_events'           => true,
        'delete_tribe_events'            => true,
        'delete_published_tribe_events'  => true,
        'delete_others_tribe_events'     => true,
        'read_private_tribe_events'      => true,
        'edit_private_tribe_events'      => true,

        // ── Event Tickets ───────────────────────────────────────────
        'edit_tribe_rsvp_tickets'        => true,
        'edit_tribe_tickets'             => true,

        // ── Explicitly denied ───────────────────────────────────────
        'install_plugins'            => false,
        'activate_plugins'           => false,
        'update_plugins'             => false,
        'edit_plugins'               => false,
        'delete_plugins'             => false,
        'install_themes'             => false,
        'switch_themes'              => false,
        'update_themes'              => false,
        'edit_themes'                => false,
        'manage_options'             => false,
        'update_core'                => false,
        'import'                     => false,
        'export'                     => false,
        'unfiltered_html'            => false,
        'unfiltered_upload'          => false,
        'edit_files'                 => false,
    ]);


    // ── PLGC Client (legacy — limited read/edit) ────────────────────
    remove_role('plgc_client');
    add_role('plgc_client', __('PLGC Client', 'plgc'), [
        'read'                   => true,
        'edit_pages'             => true,
        'edit_published_pages'   => true,
        'edit_others_pages'      => true,
        'publish_pages'          => true,
        'edit_posts'             => true,
        'edit_published_posts'   => true,
        'edit_others_posts'      => true,
        'publish_posts'          => true,
        'delete_posts'           => true,
        'delete_published_posts' => true,
        'upload_files'           => true,
        'moderate_comments'      => true,
        'edit_theme_options'     => false,
        'install_plugins'        => false,
        'activate_plugins'       => false,
        'edit_plugins'           => false,
        'install_themes'         => false,
        'switch_themes'          => false,
        'edit_themes'            => false,
        'manage_options'         => false,
        'edit_users'             => false,
        'create_users'           => false,
        'delete_users'           => false,
        'import'                 => false,
        'export'                 => false,
        'unfiltered_html'        => false,
        'unfiltered_upload'      => false,
    ]);

    update_option('plgc_role_version', PLGC_ROLE_VERSION);
}
add_action('admin_init', 'plgc_register_custom_roles');

// Also run on theme activation for fresh installs.
add_action('after_switch_theme', function () {
    delete_option('plgc_role_version');
    plgc_register_custom_roles();
});


/* ═══════════════════════════════════════════════════════════════════════
 * SAFETY RAILS — PREVENT PRIVILEGE ESCALATION
 * ═══════════════════════════════════════════════════════════════════════ */

/**
 * Remove Administrator from the roles dropdown for non-admin users.
 * Prevents Site Managers from creating or promoting anyone to Admin.
 */
function plgc_restrict_editable_roles($roles) {
    if (! current_user_can('administrator')) {
        unset($roles['administrator']);
    }
    return $roles;
}
add_filter('editable_roles', 'plgc_restrict_editable_roles');

/**
 * Prevent non-admins from editing, deleting, or promoting admin accounts.
 *
 * WordPress's map_meta_cap translates 'edit_user' / 'delete_user' into
 * primitive caps. We intercept and block if the target user is an admin.
 */
function plgc_protect_admin_accounts($caps, $cap, $user_id, $args) {
    if (! in_array($cap, ['edit_user', 'delete_user', 'promote_user'], true)) {
        return $caps;
    }

    if (empty($args[0])) {
        return $caps;
    }

    $target_user = get_userdata($args[0]);
    if (! $target_user) {
        return $caps;
    }

    // If target is an admin and current user is NOT an admin, block it.
    if (
        in_array('administrator', $target_user->roles, true)
        && ! current_user_can('administrator')
    ) {
        return ['do_not_allow'];
    }

    return $caps;
}
add_filter('map_meta_cap', 'plgc_protect_admin_accounts', 10, 4);

/**
 * Block self-promotion to Administrator via form submission.
 */
function plgc_prevent_self_promotion($user_id) {
    if (current_user_can('administrator')) {
        return;
    }

    if (isset($_POST['role']) && $_POST['role'] === 'administrator') {
        wp_die(
            __('You do not have permission to assign the Administrator role.', 'plgc'),
            __('Forbidden', 'plgc'),
            ['response' => 403, 'back_link' => true]
        );
    }
}
add_action('edit_user_profile_update', 'plgc_prevent_self_promotion');
add_action('personal_options_update', 'plgc_prevent_self_promotion');


/* ═══════════════════════════════════════════════════════════════════════
 * ADMIN MENU RESTRICTIONS (ROLE-SPECIFIC)
 * ═══════════════════════════════════════════════════════════════════════ */

/**
 * Hide admin menu items based on custom roles.
 *
 * This supplements admin-cleanup.php which handles general non-admin
 * cleanup. This function adds granular restrictions per custom role.
 */
function plgc_restrict_role_admin_menu() {

    // ── Site Manager restrictions ────────────────────────────────────
    if (current_user_can('plgc_site_manager') && ! current_user_can('administrator')) {
        // Core WP settings they shouldn't touch
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('plugins.php');

        // Appearance: keep Menus submenu, hide everything else
        remove_submenu_page('themes.php', 'themes.php');
        remove_submenu_page('themes.php', 'customize.php');
        remove_submenu_page('themes.php', 'theme-editor.php');

        // Third-party dev tools
        remove_menu_page('elementor');
        remove_menu_page('elementor-home');
        remove_menu_page('elementor-app');
        remove_menu_page('edit.php?post_type=elementor_library');
        remove_menu_page('wpengine-common');
        remove_menu_page('hello-elementor');

        // Plugin settings pages — dev only
        remove_menu_page('rank-math');
        remove_menu_page('gravitysmtp-dashboard');
        remove_menu_page('wpengine-ai-toolkit');
        remove_menu_page('edit.php?post_type=acf-field-group');

        // WooCommerce settings/status sub-items
        remove_submenu_page('woocommerce', 'wc-settings');
        remove_submenu_page('woocommerce', 'wc-status');
        remove_submenu_page('woocommerce', 'wc-addons');

        // Events Calendar settings
        remove_submenu_page('edit.php?post_type=tribe_events', 'tec-events-settings');

        // Gravity Forms — hide plugin admin, keep forms + entries + export
        remove_submenu_page('gf_edit_forms', 'gf_settings');
        remove_submenu_page('gf_edit_forms', 'gf_addons');
        remove_submenu_page('gf_edit_forms', 'gf_system_status');
        remove_submenu_page('gf_edit_forms', 'gf_update');
    }

    // ── PLGC Client restrictions (most restrictive) ──────────────────
    if (current_user_can('plgc_client') && ! current_user_can('administrator')) {
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('plugins.php');
        remove_menu_page('themes.php');
        remove_menu_page('users.php');
        remove_menu_page('profile.php');
        remove_menu_page('elementor');
        remove_menu_page('elementor-home');
        remove_menu_page('edit.php?post_type=elementor_library');
        remove_menu_page('elementor-app');
        remove_menu_page('wpengine-common');
        remove_menu_page('hello-elementor');
        remove_menu_page('rank-math');
        remove_menu_page('gravitysmtp-dashboard');
        remove_menu_page('alert-bar-settings');
        remove_menu_page('wpengine-ai-toolkit');
        remove_menu_page('edit.php?post_type=acf-field-group');
        remove_submenu_page('woocommerce', 'wc-settings');
        remove_submenu_page('woocommerce', 'wc-status');
        remove_submenu_page('woocommerce', 'wc-addons');
        remove_submenu_page('woocommerce', 'wc-reports');
    }
}
add_action('admin_menu', 'plgc_restrict_role_admin_menu', 999);


/* ═══════════════════════════════════════════════════════════════════════
 * REDIRECT GUARDS
 * ═══════════════════════════════════════════════════════════════════════ */

/**
 * Redirect non-admin users away from restricted admin pages.
 * Belt-and-suspenders: blocks direct URL access even if menu is hidden.
 */
function plgc_redirect_restricted_pages() {
    if (current_user_can('administrator')) {
        return;
    }

    $screen = get_current_screen();
    if (! $screen) {
        return;
    }

    $admin_only_screens = [
        'options-general',
        'options-writing',
        'options-reading',
        'options-discussion',
        'options-media',
        'options-permalink',
        'options-privacy',
        'tools',
        'import',
        'export',
        'customize',
        'themes',
        'theme-editor',
        'plugin-editor',
        'plugin-install',
        'update-core',
    ];

    // Block plugin management for Site Managers too
    if (current_user_can('plgc_site_manager')) {
        $admin_only_screens[] = 'plugins';
    }

    if (in_array($screen->id, $admin_only_screens, true)) {
        wp_safe_redirect(admin_url('edit.php?post_type=page'));
        exit;
    }
}
add_action('current_screen', 'plgc_redirect_restricted_pages');


/* ═══════════════════════════════════════════════════════════════════════
 * LOGIN REDIRECT
 * ═══════════════════════════════════════════════════════════════════════ */

/**
 * Send Site Managers and PLGC Clients to the Dashboard after login.
 */
function plgc_custom_login_redirect($redirect_to, $request, $user) {
    if (is_wp_error($user) || ! is_a($user, 'WP_User')) {
        return $redirect_to;
    }

    $custom_roles = ['plgc_site_manager', 'plgc_client'];
    foreach ($custom_roles as $role) {
        if (in_array($role, $user->roles, true)) {
            return admin_url('index.php');
        }
    }

    return $redirect_to;
}
add_filter('login_redirect', 'plgc_custom_login_redirect', 10, 3);
