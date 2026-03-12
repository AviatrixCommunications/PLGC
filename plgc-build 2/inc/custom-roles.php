<?php
/**
 * Custom Roles
 *
 * Creates a 'PLGC Client' role with restricted capabilities.
 * Clients can edit content but not install plugins, switch themes,
 * or access settings that could break accessibility compliance.
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * Register the PLGC Client role on theme activation.
 *
 * Capabilities:
 * - CAN: edit pages, edit posts, upload media, moderate comments
 * - CANNOT: install plugins/themes, edit theme options, manage options,
 *           edit other users, import, export, edit custom CSS
 */
function plgc_register_client_role() {
    // Remove if exists (for clean re-registration)
    remove_role('plgc_client');

    add_role('plgc_client', __('PLGC Client', 'plgc'), [
        // Content editing
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

        // Media
        'upload_files'           => true,

        // Comments
        'moderate_comments'      => true,

        // Menus (allow editing nav menus)
        'edit_theme_options'     => false,

        // Explicitly denied
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
}
add_action('after_switch_theme', 'plgc_register_client_role');

/**
 * Hide admin menu items for PLGC Client role.
 * Removes menu items that could lead to compliance-breaking changes.
 */
function plgc_restrict_client_admin_menu() {
    if (! current_user_can('administrator') && ! current_user_can('editor')) {
        // Hide items that aren't relevant to content editing
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('edit.php?post_type=elementor_library'); // Elementor templates
        remove_submenu_page('themes.php', 'themes.php');
        remove_submenu_page('themes.php', 'customize.php');

        // Hide Elementor's settings pages
        remove_menu_page('elementor');
    }
}
add_action('admin_menu', 'plgc_restrict_client_admin_menu', 999);

/**
 * Hide the admin bar "Customize" link for non-admins.
 */
function plgc_remove_customize_admin_bar($wp_admin_bar) {
    if (! current_user_can('administrator')) {
        $wp_admin_bar->remove_node('customize');
    }
}
add_action('admin_bar_menu', 'plgc_remove_customize_admin_bar', 999);

/**
 * Redirect PLGC Client away from restricted admin pages.
 */
function plgc_redirect_restricted_pages() {
    if (current_user_can('administrator') || current_user_can('editor')) {
        return;
    }

    $screen = get_current_screen();
    if (! $screen) {
        return;
    }

    $restricted_screens = [
        'options-general',
        'options-writing',
        'options-reading',
        'options-discussion',
        'options-media',
        'options-permalink',
        'tools',
        'import',
        'export',
        'themes',
        'customize',
    ];

    if (in_array($screen->id, $restricted_screens, true)) {
        wp_redirect(admin_url('edit.php?post_type=page'));
        exit;
    }
}
add_action('current_screen', 'plgc_redirect_restricted_pages');

/**
 * Set the default landing page for PLGC Client to Pages.
 */
function plgc_client_login_redirect($redirect_to, $request, $user) {
    if (! is_wp_error($user) && is_a($user, 'WP_User')) {
        if (in_array('plgc_client', $user->roles, true)) {
            return admin_url('edit.php?post_type=page');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'plgc_client_login_redirect', 10, 3);
