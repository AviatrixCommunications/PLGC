<?php
/**
 * Admin Cleanup & Dashboard UX
 *
 * Strips WordPress down to only what PLGC staff actually need.
 * Removes comments, reorganizes menus, adds helpful dashboard
 * widgets, and cleans up WooCommerce/Events Calendar clutter.
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * DISABLE UNUSED FEATURES
 * ============================================================
 */

/**
 * Disable comments site-wide.
 */

// Remove comment support from all post types
function plgc_disable_comments_post_types() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}
add_action('admin_init', 'plgc_disable_comments_post_types');

// Close comments on the front-end
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Hide existing comments
add_filter('comments_array', '__return_empty_array', 10, 2);

// Remove Comments from admin menu
function plgc_remove_comments_menu() {
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'plgc_remove_comments_menu');

// Remove Comments from admin bar
function plgc_remove_comments_admin_bar($wp_admin_bar) {
    $wp_admin_bar->remove_node('comments');
}
add_action('admin_bar_menu', 'plgc_remove_comments_admin_bar', 999);

// Remove Comments column from post/page lists
function plgc_remove_comments_columns($columns) {
    unset($columns['comments']);
    return $columns;
}
add_filter('manage_posts_columns', 'plgc_remove_comments_columns');
add_filter('manage_pages_columns', 'plgc_remove_comments_columns');

// Remove comment count from dashboard "Right Now" widget
function plgc_remove_comments_dashboard() {
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}
add_action('admin_init', 'plgc_remove_comments_dashboard');

/**
 * Disable XML-RPC (security hardening, not needed)
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Disable Gutenberg / Block Editor for Pages (Elementor-only).
 * Posts keep the block editor for news/blog content.
 * Also hides the "Back to WordPress Editor" button in Elementor
 * for Pages so editors don't accidentally leave the page builder.
 */
function plgc_disable_gutenberg_for_pages($use_block_editor, $post) {
    if (! empty($post->post_type) && $post->post_type === 'page') {
        return false;
    }
    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'plgc_disable_gutenberg_for_pages', 10, 2);

/**
 * For Pages: default "Edit" link goes to Elementor, not classic editor.
 * Posts keep both Gutenberg and Elementor as options.
 */

/**
 * Remove WordPress version from head (security)
 */
remove_action('wp_head', 'wp_generator');

/**
 * Disable self-pingbacks
 */
function plgc_disable_self_pingbacks(&$links) {
    $home = get_option('home');
    foreach ($links as $l => $link) {
        if (0 === strpos($link, $home)) {
            unset($links[$l]);
        }
    }
}
add_action('pre_ping', 'plgc_disable_self_pingbacks');

/**
 * ============================================================
 * ADMIN MENU ORGANIZATION
 * ============================================================
 * Adds labeled section dividers and reorders the sidebar into
 * logical groups so the admin feels organized and intentional.
 */

/**
 * Register non-clickable section header menu items.
 */
function plgc_add_menu_section_headers() {
    global $menu;

    // Section headers: slug => label
    // Organized by how a golf club staff member thinks about their site.
    $sections = [
        'plgc-section-content'     => 'Content',
        'plgc-section-events'      => 'Events & Dining',
        'plgc-section-shop'        => 'Shop',
        'plgc-section-manage'      => 'Manage',
        'plgc-section-compliance'  => 'Compliance',
        'plgc-section-admin'       => 'Developer',
    ];

    foreach ($sections as $slug => $label) {
        add_menu_page(
            '',               // page title (unused)
            $label,           // menu label
            'read',           // capability
            $slug,            // slug
            '__return_false',
            'none',           // no icon
            0.1               // position (reordered later)
        );
    }

    // Mark section headers with a CSS class so we can style them
    foreach ($menu as $key => &$item) {
        if (isset($sections[$item[2]])) {
            $item[4] = ($item[4] ?? '') . ' plgc-menu-section-header';
        }
    }
    unset($item);
}
add_action('admin_menu', 'plgc_add_menu_section_headers', 5);

/**
 * Reorganize admin menu order for all users.
 * Items NOT listed here get appended at the end, so we include
 * known third-party items (WP Engine, Elementor, Hello) to
 * control their position or they float loose.
 */
function plgc_custom_menu_order($menu_order) {
    if (! $menu_order) {
        return true;
    }

    return [
        'index.php',                           // Dashboard

        // ── Content ──
        'plgc-section-content',
        'edit.php?post_type=page',             // Pages
        'upload.php',                          // Media
        'edit.php',                            // News & Updates

        // ── Events & Dining ──
        'plgc-section-events',
        'edit.php?post_type=tribe_events',     // Events
        'tec-tickets',                         // Tickets
        'edit.php?post_type=plgc_menu_item',   // Restaurant Menu

        // ── Shop ──
        'plgc-section-shop',
        'edit.php?post_type=product',          // Products
        'woocommerce',                         // Orders

        // ── Manage ──
        'plgc-section-manage',
        'gf_edit_forms',                       // Forms
        'edit.php?post_type=awsm_job_openings', // Job Openings
        'alert-bar-settings',                  // Alert Bar
        'plgc-particles',                      // Particles
        'plgc-settings',                       // PL Settings
        'users.php',                           // Users
        'themes.php',                          // Menus (Appearance)

        // ── Compliance ──
        'plgc-section-compliance',
        'plgc-accessibility',                  // ♿ Accessibility Dashboard

        // ── Developer (admin only) ──
        'plgc-section-admin',
        'plugins.php',
        'options-general.php',
        'tools.php',
        'wpengine-common',                     // WP Engine
        'rank-math',                           // Rank Math SEO
        'edit.php?post_type=acf-field-group',  // ACF
        'gravitysmtp-dashboard',               // SMTP
        'wpengine-ai-toolkit',                 // WP Engine AI ToolKit
        'separator-elementor',                 // Elementor separator
        'elementor',                           // Elementor
        'edit.php?post_type=elementor_library', // Templates
        'elementor-home',                      // Elementor Home
        'separator1',                          // WP separator
        'hello-elementor',                     // Hello
    ];
}
add_filter('custom_menu_order', 'plgc_custom_menu_order');
add_filter('menu_order', 'plgc_custom_menu_order');

/**
 * Clean up admin menu for non-admin users.
 * Removes items that content editors and clients don't need.
 */
function plgc_cleanup_admin_menu() {
    // For everyone: remove items that aren't needed
    remove_menu_page('edit-comments.php');

    // WooCommerce Marketing & Payments — disabled features that link to dead pages.
    // Must be removed for ALL users (including admins) to avoid "not allowed" errors.
    remove_menu_page('woocommerce-marketing');
    remove_menu_page('admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM');

    // For non-admins: remove developer tools + their section header
    if (! current_user_can('administrator')) {
        // Core WP settings
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('plugins.php');

        // Appearance: keep for Site Managers (they need Menus), hide for others
        if (! current_user_can('plgc_site_manager')) {
            remove_menu_page('themes.php');
        } else {
            // Site Managers: hide theme/customizer sub-items but keep Menus
            remove_submenu_page('themes.php', 'themes.php');
            remove_submenu_page('themes.php', 'customize.php');
            remove_submenu_page('themes.php', 'theme-editor.php');
        }

        // Hide the "Developer" section header entirely
        remove_menu_page('plgc-section-admin');

        // Third-party dev tools — admin only
        remove_menu_page('elementor');
        remove_menu_page('elementor-home');
        remove_menu_page('edit.php?post_type=elementor_library');
        remove_menu_page('elementor-app');
        remove_menu_page('wpengine-common');
        remove_menu_page('hello-elementor');
        remove_menu_page('rank-math');
        remove_menu_page('gravitysmtp-dashboard');
        remove_menu_page('wpengine-ai-toolkit');

        // ACF options — dev only (PL Settings uses it but has its own menu)
        remove_menu_page('edit.php?post_type=acf-field-group');

        // WooCommerce sub-items that are settings/dev-only
        remove_submenu_page('woocommerce', 'wc-settings');
        remove_submenu_page('woocommerce', 'wc-status');
        remove_submenu_page('woocommerce', 'wc-addons');
        remove_submenu_page('woocommerce', 'wc-admin&path=/marketing');

        // Events Calendar settings
        remove_submenu_page('edit.php?post_type=tribe_events', 'tec-events-settings');
    }

    // Hide Compliance section for users who can't see the a11y dashboard
    if (! current_user_can('edit_pages')) {
        remove_menu_page('plgc-section-compliance');
    }

    // For PLGC Client role specifically: even more restricted
    if (current_user_can('plgc_client') && ! current_user_can('administrator')) {
        remove_menu_page('users.php');
        remove_menu_page('profile.php');
        remove_menu_page('themes.php');
        remove_menu_page('plgc-section-manage'); // hide Manage section entirely for read-only clients
        remove_submenu_page('woocommerce', 'wc-reports');
    }
}
add_action('admin_menu', 'plgc_cleanup_admin_menu', 999);

/**
 * Rename menu labels for clarity.
 * Makes the admin more intuitive for golf club staff.
 */
function plgc_rename_menu_labels() {
    global $menu, $submenu;

    foreach ($menu as $key => $item) {
        switch ($item[2]) {
            // "Posts" → "News & Updates"
            case 'edit.php':
                $menu[$key][0] = 'News & Updates';
                break;

            // "WooCommerce" → "Orders" (Products is already a separate menu)
            case 'woocommerce':
                $menu[$key][0] = 'Orders';
                break;

            // For Site Managers: "Appearance" → "Menus" since that's all they see
            case 'themes.php':
                if (! current_user_can('administrator')) {
                    $menu[$key][0] = 'Menus';
                }
                break;
        }
    }
}
add_action('admin_menu', 'plgc_rename_menu_labels', 998);

/**
 * Rename "Posts" post type labels throughout the admin.
 */
function plgc_rename_post_labels() {
    global $wp_post_types;

    if (! isset($wp_post_types['post'])) {
        return;
    }

    $labels = &$wp_post_types['post']->labels;
    $labels->name               = 'News & Updates';
    $labels->singular_name      = 'News Article';
    $labels->add_new            = 'Add Article';
    $labels->add_new_item       = 'Add New Article';
    $labels->edit_item          = 'Edit Article';
    $labels->new_item           = 'New Article';
    $labels->view_item          = 'View Article';
    $labels->search_items       = 'Search Articles';
    $labels->not_found          = 'No articles found';
    $labels->not_found_in_trash = 'No articles found in Trash';
    $labels->all_items          = 'All Articles';
    $labels->menu_name          = 'News & Updates';
    $labels->name_admin_bar     = 'News Article';
}
add_action('init', 'plgc_rename_post_labels');

/**
 * ============================================================
 * DASHBOARD CLEANUP
 * ============================================================
 */

/**
 * Remove default dashboard widgets that aren't useful.
 */
function plgc_remove_dashboard_widgets() {
    // WordPress defaults
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
    remove_meta_box('dashboard_activity', 'dashboard', 'normal');
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    remove_meta_box('dashboard_primary', 'dashboard', 'side');       // WordPress News
    remove_meta_box('dashboard_secondary', 'dashboard', 'side');
    remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

    // WooCommerce dashboard widgets for non-admins
    if (! current_user_can('administrator')) {
        remove_meta_box('woocommerce_dashboard_status', 'dashboard', 'normal');
        remove_meta_box('woocommerce_dashboard_recent_reviews', 'dashboard', 'normal');
        remove_meta_box('woocommerce_dashboard_setup', 'dashboard', 'normal');
    }

    // Elementor dashboard widget
    remove_meta_box('e-dashboard-overview', 'dashboard', 'normal');

    // Yoast / Rank Math
    remove_meta_box('wpseo-dashboard-overview', 'dashboard', 'normal');
    remove_meta_box('rank_math_dashboard_widget', 'dashboard', 'normal');

    // Events Calendar news — plugin changelogs, not useful for anyone
    remove_meta_box('tribe_dashboard_widget', 'dashboard', 'normal');
    remove_meta_box('tribe_dashboard_widget', 'dashboard', 'side');

    // Gravity Forms dashboard widget — low value on dashboard
    remove_meta_box('rg_forms_dashboard', 'dashboard', 'normal');
    remove_meta_box('rg_forms_dashboard', 'dashboard', 'side');
}
add_action('wp_dashboard_setup', 'plgc_remove_dashboard_widgets', 999);

/**
 * Add custom PLGC dashboard widgets.
 */
function plgc_add_dashboard_widgets() {
    // Welcome widget
    wp_add_dashboard_widget(
        'plgc_welcome',
        '⛳ Prairie Landing Golf Club',
        'plgc_welcome_widget',
        null,
        null,
        'normal',
        'high'
    );

    // Quick links widget
    wp_add_dashboard_widget(
        'plgc_quick_links',
        '🔗 Quick Links',
        'plgc_quick_links_widget',
        null,
        null,
        'side',
        'high'
    );

    // Accessibility reminders (for editors)
    if (current_user_can('edit_pages')) {
        wp_add_dashboard_widget(
            'plgc_a11y_tips',
            '♿ Accessibility Reminders',
            'plgc_a11y_tips_widget',
            null,
            null,
            'side',
            'default'
        );
    }
}
add_action('wp_dashboard_setup', 'plgc_add_dashboard_widgets');

/**
 * Welcome widget content.
 */
function plgc_welcome_widget() {
    $user = wp_get_current_user();
    ?>
    <div style="padding: 8px 0;">
        <p style="font-size: 15px;">Welcome back, <strong><?php echo esc_html($user->display_name); ?></strong>!</p>
        <p>Here's where you can manage content for the Prairie Landing Golf Club website. Use the sidebar menu to navigate to the section you need.</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
            <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button" style="text-align: center;">Edit Pages</a>
            <a href="<?php echo admin_url('upload.php'); ?>" class="button" style="text-align: center;">Media Library</a>
            <a href="<?php echo admin_url('edit.php?post_type=tribe_events'); ?>" class="button" style="text-align: center;">Manage Events</a>
            <a href="<?php echo admin_url('edit.php'); ?>" class="button" style="text-align: center;">News & Updates</a>
        </div>
        <?php if (current_user_can('plgc_site_manager') || current_user_can('administrator')) : ?>
            <p style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
                <strong>Management:</strong>
                <a href="<?php echo admin_url('edit.php?post_type=product'); ?>">Products</a> |
                <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>">Orders</a> |
                <a href="<?php echo admin_url('users.php'); ?>">Users</a>
                <?php if (current_user_can('administrator')) : ?>
                    | <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>">Forms</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Quick links widget content.
 */
function plgc_quick_links_widget() {
    ?>
    <ul style="margin: 0; padding: 0; list-style: none;">
        <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">
                🌐 View Live Site <span class="screen-reader-text">(opens in new tab)</span>
            </a>
        </li>
        <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <a href="<?php echo admin_url('post-new.php?post_type=tribe_events'); ?>">
                📅 Add New Event
            </a>
        </li>
        <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <a href="<?php echo admin_url('post-new.php'); ?>">
                📝 Write News Article
            </a>
        </li>
        <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>">
                🛒 View Orders
            </a>
        </li>
        <li style="padding: 8px 0;">
            <a href="mailto:support@aviatrixcommunications.com">
                💬 Contact Aviatrix Support
            </a>
        </li>
    </ul>
    <?php
}

/**
 * Accessibility tips widget content.
 */
function plgc_a11y_tips_widget() {
    ?>
    <div style="font-size: 13px;">
        <p style="margin-top: 0;"><strong>When editing pages, please remember:</strong></p>
        <ul style="margin-left: 16px;">
            <li><strong>Images:</strong> Always add descriptive alt text. Describe what the image shows, not just "golf course photo."</li>
            <li><strong>Headings:</strong> Use headings in order (H2, then H3, then H4). Never skip levels. Never use headings just to make text bigger.</li>
            <li><strong>Links:</strong> Use descriptive text like "View Golf Rates" instead of "Click Here" or "Read More."</li>
            <li><strong>Colors:</strong> Only use the brand color swatches provided — they've been tested for contrast compliance.</li>
            <li><strong>Documents:</strong> PDFs and downloads should include the file type and size, e.g., "Course Map (PDF, 1.2 MB)."</li>
        </ul>
        <p style="color: #666; font-size: 12px;">This site must meet <strong>WCAG 2.1 AA</strong> accessibility standards. Questions? Contact Aviatrix Communications.</p>
    </div>
    <?php
}

/**
 * ============================================================
 * ADMIN BAR CLEANUP
 * ============================================================
 */

/**
 * Clean up the admin bar for non-admins.
 */
function plgc_cleanup_admin_bar($wp_admin_bar) {
    if (current_user_can('administrator')) {
        return;
    }

    // Remove items non-admins don't need
    $wp_admin_bar->remove_node('wp-logo');        // WordPress logo menu
    $wp_admin_bar->remove_node('customize');       // Customize link
    $wp_admin_bar->remove_node('updates');         // Updates notification
    $wp_admin_bar->remove_node('new-user');        // New User
    $wp_admin_bar->remove_node('search');          // Search
}
add_action('admin_bar_menu', 'plgc_cleanup_admin_bar', 999);

/**
 * ============================================================
 * ADMIN FOOTER
 * ============================================================
 */

/**
 * Replace the default admin footer text.
 */
function plgc_admin_footer_text() {
    return '<span style="color: #666;">Prairie Landing Golf Club — Managed by <a href="https://aviatrixcommunications.com" target="_blank">Aviatrix Communications</a></span>';
}
add_filter('admin_footer_text', 'plgc_admin_footer_text');

/**
 * Remove WordPress version from admin footer.
 */
add_filter('update_footer', '__return_empty_string', 11);

/**
 * ============================================================
 * MEDIA LIBRARY IMPROVEMENTS
 * ============================================================
 */

/**
 * Encourage alt text on upload.
 * Note: The actual missing-alt-text notice and filter query are handled
 * by the Compliance Suite plugin. It only targets images (not PDFs/docs).
 */

/**
 * Add filter button to media library.
 */
function plgc_media_filter_button() {
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'upload') {
        return;
    }

    $is_filtered = isset($_GET['plgc_no_alt']);
    $class = $is_filtered ? 'button-primary' : 'button';
    $url = $is_filtered
        ? admin_url('upload.php')
        : admin_url('upload.php?plgc_no_alt=1&mode=list');

    echo '<script>
        jQuery(document).ready(function($) {
            $(".wp-list-table .tablenav .actions:first").append(
                \'<a href="' . esc_url($url) . '" class="' . $class . '" style="margin-left: 8px;">' .
                ($is_filtered ? '✓ Showing: Missing Alt Text' : '⚠ Show Missing Alt Text') .
                '</a>\'
            );
        });
    </script>';
}
add_action('admin_footer-upload.php', 'plgc_media_filter_button');

/**
 * ============================================================
 * WOOCOMMERCE CLEANUP
 * ============================================================
 */

/**
 * Remove WooCommerce marketing hub and unnecessary features.
 */
function plgc_woocommerce_cleanup() {
    // Disable WooCommerce marketing hub
    add_filter('woocommerce_marketing_menu_items', '__return_empty_array');
    add_filter('woocommerce_admin_features', function ($features) {
        return array_filter($features, function ($feature) {
            $disabled = [
                'marketing',
                'analytics',
                'remote-inbox-notifications',
                'homescreen',
            ];
            return ! in_array($feature, $disabled, true);
        });
    });
}
add_action('init', 'plgc_woocommerce_cleanup');

/**
 * Remove WooCommerce admin notices/nags for non-admins.
 */
function plgc_remove_woo_nags() {
    if (! current_user_can('administrator')) {
        remove_action('admin_notices', 'woothemes_updater_notice');
    }
}
add_action('admin_init', 'plgc_remove_woo_nags');

/**
 * Disable WooCommerce reviews (uses comments system).
 */
add_filter('woocommerce_product_tabs', function ($tabs) {
    unset($tabs['reviews']);
    return $tabs;
});

/**
 * ============================================================
 * EVENTS CALENDAR CLEANUP
 * ============================================================
 */

/**
 * Remove Events Calendar promotional/upsell notices for non-admins.
 */
function plgc_cleanup_events_calendar() {
    if (! current_user_can('administrator')) {
        // Remove Events Calendar promotional banners
        remove_action('admin_notices', ['Tribe__Admin__Notices', 'render']);
    }
}
add_action('admin_init', 'plgc_cleanup_events_calendar', 20);


/**
 * ============================================================
 * ELEMENTOR AI — DISABLE SITE-WIDE
 * ============================================================
 * Removes all Elementor AI buttons, prompts, and notifications.
 * Uses Elementor's official user option filter — update-safe.
 */

// Disable the AI feature for all users via their user option.
add_filter('get_user_option_elementor_enable_ai', '__return_zero');

// Hide the AI preference toggle from user profiles so it can't be re-enabled.
function plgc_hide_elementor_ai_user_pref() {
    global $pagenow;
    if ($pagenow === 'profile.php' || $pagenow === 'user-edit.php') {
        echo '<style>
            tr:has(+ tr #elementor_enable_ai),
            tr:has(#elementor_enable_ai) { display: none; }
        </style>';
    }
}
add_action('admin_head', 'plgc_hide_elementor_ai_user_pref');

// Belt-and-suspenders: also hide AI buttons via editor CSS.
function plgc_hide_elementor_ai_editor() {
    echo '<style>
        .e-ai-button,
        .e-ai-badge,
        .elementor-ai-get-started,
        [class*="ai-promotion"],
        [class*="ai-excerpt"] { display: none !important; }
    </style>';
}
add_action('elementor/editor/before_enqueue_scripts', 'plgc_hide_elementor_ai_editor');


/**
 * ============================================================
 * ADMIN NOTIFICATION SUPPRESSION
 * ============================================================
 * Non-admin users should not see plugin update nags, WP Engine
 * notices, Elementor promos, or third-party noise. Admins still
 * see everything — they need to know about updates.
 */
function plgc_suppress_admin_notices_for_non_admins() {
    if (current_user_can('administrator')) {
        return;
    }

    // Remove all third-party admin notices for non-admin users.
    // Core WP notices (errors, warnings) still display via admin_notices.
    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');

    // Re-add only our custom PLGC notices (accessibility guardrails, etc.)
    add_action('admin_notices', 'plgc_content_guardrails_notices');
}
add_action('admin_init', 'plgc_suppress_admin_notices_for_non_admins', 999);

/**
 * Hide WP update nags and plugin promo banners for non-admins via CSS.
 * This catches things that slip through the action removal above.
 */
function plgc_hide_admin_nags_css() {
    if (current_user_can('administrator')) {
        return;
    }
    echo '<style>
        .update-nag,
        .notice:not(.plgc-notice):not(.notice-error):not(.notice-success):not(.notice-warning),
        .woocommerce-message,
        .woocommerce-StoreAlert,
        .tribe-admin-notice,
        .rank-math-notice,
        .rank-math-warning,
        #rank-math-review-plugin-notice,
        .shortpixel-notice,
        .wp-mail-smtp-admin-notice,
        .yoast-notice,
        [id*="elementor-notice"],
        [id*="elementor-pro-notice"],
        .e-notice,
        .wpengine-notification,
        #wpe-ab-test-notice {
            display: none !important;
        }
    </style>';
}
add_action('admin_head', 'plgc_hide_admin_nags_css', 999);

/**
 * Dismiss WooCommerce setup wizard permanently.
 * Prevents the "Complete store setup" nag from showing.
 */
function plgc_dismiss_woo_setup_wizard() {
    if (class_exists('WooCommerce')) {
        // Mark setup as complete
        update_option('woocommerce_task_list_hidden', 'yes');
        update_option('woocommerce_task_list_complete', 'yes');
    }
}
add_action('admin_init', 'plgc_dismiss_woo_setup_wizard', 5);

/**
 * ============================================================
 * LOGIN PAGE BRANDING
 * ============================================================
 * White-label the login page for the client.
 */

/**
 * Custom login logo and branded styling.
 *
 * Matches the PLGC brand: dark green background, Open Sans typography,
 * yellow accent buttons, and pill-shaped CTA consistent with the site.
 *
 * @since 1.0.0
 * @since 1.7.12  Enhanced styling — fonts, inputs, button shape, focus states.
 */
function plgc_login_logo() {
    $logo_id = get_theme_mod('custom_logo');
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

    if (! $logo_url) {
        return;
    }

    // Enqueue Open Sans for the login page
    wp_enqueue_style(
        'plgc-login-fonts',
        'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap',
        [],
        null
    );

    ?>
    <style>

        /* ── Page background — clean, light, modern ── */
        body.login {
            background-color: #F2F2F2;
            font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* ── Accent bar at top of page ── */
        body.login::before {
            content: '';
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #233C26 0%, #567915 50%, #FFAE40 100%);
            z-index: 9999;
        }

        /* ── Logo ── */
        #login h1 a {
            background-image: url('<?php echo esc_url($logo_url); ?>');
            background-size: contain;
            width: 280px;
            height: 100px;
            background-repeat: no-repeat;
            background-position: center;
            margin-bottom: 20px;
        }

        /* ── Form card ── */
        .login form {
            border-radius: 10px;
            border: none !important;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
            padding: 26px 24px 34px;
            background: #fff;
        }

        /* ── Labels ── */
        .login form .forgetmenot label,
        .login label {
            font-family: 'Open Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #000;
        }

        /* ── Inputs ── */
        .login form input[type="text"],
        .login form input[type="password"] {
            font-family: 'Open Sans', sans-serif;
            font-size: 16px;
            border: 2px solid #E7E4E4;
            border-radius: 6px;
            padding: 10px 14px;
            background: #fff;
            color: #000;
            transition: border-color 0.2s ease;
        }

        .login form input[type="text"]:focus,
        .login form input[type="password"]:focus {
            border-color: #567915;
            box-shadow: 0 0 0 2px rgba(86, 121, 21, 0.2);
            outline: none;
        }

        /* ── Password visibility toggle ── */
        .login .wp-pwd .button.wp-hide-pw {
            color: #567915;
        }
        .login .wp-pwd .button.wp-hide-pw:focus {
            color: #567915;
            box-shadow: 0 0 0 2px rgba(86, 121, 21, 0.3);
            outline: 2px solid #567915;
            outline-offset: 2px;
        }

        /* ── Checkbox ── */
        .login input[type="checkbox"]:checked::before {
            content: url("data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M14.83%204.89l1.34%201.22-7.16%208-4.18-3.71%201.34-1.51%202.69%202.39z%27%20fill%3D%27%23567915%27%2F%3E%3C%2Fsvg%3E");
        }
        .login input[type="checkbox"]:focus {
            border-color: #567915;
            box-shadow: 0 0 0 2px rgba(86, 121, 21, 0.2);
        }

        /* ── Submit button ── */
        .wp-core-ui .button-primary {
            background: #FFAE40 !important;
            border: none !important;
            border-radius: 100px !important;
            color: #000 !important;
            font-family: 'Open Sans', sans-serif !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            padding: 8px 28px !important;
            height: auto !important;
            line-height: 1.6 !important;
            text-shadow: none !important;
            box-shadow: none !important;
            transition: background-color 0.15s ease;
            min-height: 44px;
        }
        .wp-core-ui .button-primary:hover,
        .wp-core-ui .button-primary:focus {
            background: #FDBC69 !important;
            color: #000 !important;
        }
        .wp-core-ui .button-primary:focus {
            outline: 2px solid #567915 !important;
            outline-offset: 2px !important;
        }

        /* ── Submit row layout ── */
        .login .submit {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* ── Links below form ── */
        .login #backtoblog a,
        .login #nav a,
        .login .privacy-policy-page-link a {
            color: #567915 !important;
            font-family: 'Open Sans', sans-serif;
            font-size: 14px;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .login #backtoblog a:hover,
        .login #nav a:hover,
        .login .privacy-policy-page-link a:hover {
            color: #233C26 !important;
            text-decoration: underline;
        }
        .login #backtoblog a:focus,
        .login #nav a:focus,
        .login .privacy-policy-page-link a:focus {
            color: #233C26 !important;
            outline: 2px solid #567915;
            outline-offset: 2px;
            border-radius: 2px;
        }

        /* ── Error / message boxes ── */
        .login .message,
        .login .success {
            border-left-color: #567915;
            font-family: 'Open Sans', sans-serif;
        }
        .login #login_error {
            border-left-color: #FFAE40;
            font-family: 'Open Sans', sans-serif;
        }

        /* ── Language switcher (WP 5.9+) ── */
        .language-switcher {
            font-family: 'Open Sans', sans-serif;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'plgc_login_logo');

/**
 * Point login logo link to the site, not WordPress.org.
 */
function plgc_login_logo_url() {
    return home_url('/');
}
add_filter('login_headerurl', 'plgc_login_logo_url');

/**
 * Change login logo title.
 */
function plgc_login_logo_title() {
    return get_bloginfo('name');
}
add_filter('login_headertext', 'plgc_login_logo_title');

/**
 * ============================================================
 * ADMIN STYLES
 * ============================================================
 */

/**
 * Add subtle admin branding and cleanup styles.
 */
function plgc_admin_styles() {
    $brand_dark  = '#233C26';
    $brand_gold  = '#FFAE40';
    $brand_light = '#FDBC69';
    ?>
    <style>
        /* ============================================================
         * SIDEBAR SECTION HEADERS
         * Non-clickable labels that divide the menu into groups.
         * ============================================================ */
        #adminmenu li.plgc-menu-section-header {
            margin-top: 6px;
            margin-bottom: 0;
            pointer-events: none;
            cursor: default;
        }
        #adminmenu li.plgc-menu-section-header > a {
            padding: 6px 12px 4px !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
            color: rgba(240, 246, 252, 0.45) !important;
            background: transparent !important;
            line-height: 1.2 !important;
            min-height: 0 !important;
            height: auto !important;
            pointer-events: none;
            cursor: default;
            border-top: 1px solid rgba(240, 246, 252, 0.07);
        }
        /* First section header (Content) doesn't need top border */
        #adminmenu li.plgc-menu-section-header:first-of-type > a,
        #adminmenu li.plgc-menu-section-header + li + li.plgc-menu-section-header > a {
            /* keep borders on subsequent headers */
        }
        /* Hide the bullet/icon area for section headers */
        #adminmenu li.plgc-menu-section-header .wp-menu-image {
            display: none !important;
        }
        #adminmenu li.plgc-menu-section-header .wp-menu-name {
            padding-left: 12px !important;
        }
        /* No hover effect on section headers */
        #adminmenu li.plgc-menu-section-header > a:hover,
        #adminmenu li.plgc-menu-section-header > a:focus {
            background: transparent !important;
            color: rgba(240, 246, 252, 0.45) !important;
        }
        /* Hide default WP separators — replaced by our section headers */
        #adminmenu li.wp-menu-separator {
            display: none !important;
        }
        /* No submenu arrow or expansion on headers */
        #adminmenu li.plgc-menu-section-header .wp-submenu {
            display: none !important;
        }

        /* ============================================================
         * SIDEBAR OVERALL POLISH
         * ============================================================ */

        /* Brand accent for active menu item */
        #adminmenu .wp-has-current-submenu .wp-submenu-head,
        #adminmenu a.wp-has-current-submenu,
        #adminmenu > li.current > a.current {
            background: <?php echo $brand_dark; ?> !important;
        }

        /* Slightly softer menu item text */
        #adminmenu a {
            transition: color 0.15s ease, background 0.15s ease;
        }

        /* ============================================================
         * ADMIN BAR POLISH
         * ============================================================ */
        #wpadminbar {
            background: <?php echo $brand_dark; ?> !important;
        }
        #wpadminbar .ab-item,
        #wpadminbar a.ab-item,
        #wpadminbar > #wp-toolbar span.ab-label,
        #wpadminbar > #wp-toolbar span.noticon {
            color: rgba(255, 255, 255, 0.85) !important;
        }
        #wpadminbar .hover > .ab-item,
        #wpadminbar a.ab-item:hover {
            color: <?php echo $brand_gold; ?> !important;
            background: rgba(0, 0, 0, 0.15) !important;
        }
        #wpadminbar #adminbarsearch:before,
        #wpadminbar .ab-icon:before,
        #wpadminbar .ab-item:before {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        /* ============================================================
         * CONTENT AREA POLISH
         * ============================================================ */

        /* Softer page titles */
        .wrap > h1:first-of-type {
            font-weight: 500;
            font-size: 22px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        /* Rounded postboxes/metaboxes */
        .postbox {
            border-radius: 8px !important;
            border-color: #e0e0e0 !important;
            overflow: hidden;
        }
        .postbox .hndle {
            border-bottom-color: #f0f0f0 !important;
        }

        /* Rounded buttons */
        .wrap .page-title-action,
        .wp-core-ui .button {
            border-radius: 4px !important;
        }
        .wp-core-ui .button-primary {
            background: #FFAE40 !important;
            border-color: #FFAE40 !important;
            color: #000 !important;
            text-shadow: none !important;
        }
        .wp-core-ui .button-primary:hover {
            background: #FDBC69 !important;
            border-color: #FDBC69 !important;
            color: #000 !important;
        }
        .wp-core-ui .button-primary:focus {
            background: #FDBC69 !important;
            border-color: #FDBC69 !important;
            color: #000 !important;
            box-shadow: 0 0 0 1px #fff, 0 0 0 3px #567915 !important;
        }
        .wp-core-ui .button-primary:disabled,
        .wp-core-ui .button-primary[disabled] {
            background: #E7E4E4 !important;
            border-color: #E7E4E4 !important;
            color: #999 !important;
        }

        /* ============================================================
         * FOOTER BRANDING
         * ============================================================ */
        #wpfooter #footer-left {
            font-style: italic;
            color: #999;
        }

        /* ============================================================
         * NON-ADMIN CLEANUP
         * ============================================================ */
        <?php if (! current_user_can('administrator')) : ?>
        #screen-options-link-wrap,
        #contextual-help-link-wrap {
            display: none !important;
        }
        <?php endif; ?>

        /* Dashboard widget styling */
        #plgc_welcome .inside,
        #plgc_quick_links .inside,
        #plgc_a11y_tips .inside {
            padding: 12px;
        }
    </style>
    <?php
}
add_action('admin_head', 'plgc_admin_styles');

/**
 * ============================================================
 * ADMIN BAR — ACCESSIBILITY QUICK LINK
 * ============================================================
 * Shows site health score in the admin bar with a link to the
 * accessibility dashboard. Visible on both admin and front-end.
 */
function plgc_admin_bar_a11y_link($wp_admin_bar) {
    if (! current_user_can('edit_pages')) {
        return;
    }

    $wp_admin_bar->add_node([
        'id'    => 'plgc-a11y',
        'title' => '♿ Accessibility',
        'href'  => admin_url('admin.php?page=plgc-accessibility'),
        'meta'  => ['title' => 'Accessibility Dashboard'],
    ]);

    $wp_admin_bar->add_node([
        'parent' => 'plgc-a11y',
        'id'     => 'plgc-a11y-dashboard',
        'title'  => 'Dashboard',
        'href'   => admin_url('admin.php?page=plgc-accessibility'),
    ]);

    if (current_user_can('manage_options')) {
        $wp_admin_bar->add_node([
            'parent' => 'plgc-a11y',
            'id'     => 'plgc-a11y-scanner',
            'title'  => 'Bulk Scanner',
            'href'   => admin_url('admin.php?page=plgc-bulk-scan'),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'plgc-a11y',
            'id'     => 'plgc-a11y-settings',
            'title'  => 'Compliance Suite Settings',
            'href'   => admin_url('options-general.php?page=plgc-docmgr'),
        ]);
    }

    $wp_admin_bar->add_node([
        'parent' => 'plgc-a11y',
        'id'     => 'plgc-a11y-media',
        'title'  => 'Media Library (list view)',
        'href'   => admin_url('upload.php?mode=list'),
    ]);
}
add_action('admin_bar_menu', 'plgc_admin_bar_a11y_link', 80);

/**
 * ============================================================
 * EDITOR WELCOME PANEL
 * ============================================================
 * Replaces the default WordPress welcome panel with context
 * about the site setup, accessibility requirements, and
 * quick links to common tasks.
 */
function plgc_editor_welcome_widget() {
    wp_add_dashboard_widget(
        'plgc_editor_welcome',
        '🏢 Welcome to Your Website',
        'plgc_editor_welcome_content',
        null,
        null,
        'normal',
        'high'
    );
}
add_action('wp_dashboard_setup', 'plgc_editor_welcome_widget');

function plgc_editor_welcome_content() {
    $user = wp_get_current_user();
    $name = $user->first_name ?: $user->display_name;
    ?>
    <div style="font-size: 13px; line-height: 1.6;">
        <p style="font-size: 15px; margin-top: 0;">
            Hi <?php echo esc_html($name); ?> — here's what you need to know about editing your site.
        </p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div style="padding: 12px; background: #f8fff0; border-radius: 6px; border: 1px solid #e5f0d0;">
                <strong style="color: #567915;">♿ Accessibility is Built In</strong>
                <p style="margin: 4px 0 0; font-size: 12px;">
                    This site has automated accessibility checks. When you save a page, it scans
                    for common issues and shows you what needs fixing. Use the brand color palette
                    and approved fonts — they're pre-tested for contrast compliance.
                </p>
            </div>
            <div style="padding: 12px; background: #f0f4ff; border-radius: 6px; border: 1px solid #c2d7ff;">
                <strong style="color: #102B60;">📄 Document Management</strong>
                <p style="margin: 4px 0 0; font-size: 12px;">
                    PDFs are automatically scanned for accessibility when uploaded. The document
                    manager tracks retention dates and handles archiving. When uploading a new
                    version of a document, use the Version Control field to link it to the old one.
                </p>
            </div>
        </div>

        <details style="margin-bottom: 12px;">
            <summary style="cursor: pointer; font-weight: 600; color: #233C26;">
                ✅ Content Editing Checklist (click to expand)
            </summary>
            <div style="margin-top: 8px; padding: 12px; background: #f9f9f9; border-radius: 4px; font-size: 12px;">
                <p style="margin: 0 0 8px;">Before publishing any page, verify:</p>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> All images have descriptive alt text</label>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> Links describe where they go (no "click here")</label>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> Headings follow the right order (H2 → H3 → H4, never skip)</label>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> Videos have accurate captions (not just auto-generated)</label>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> PDFs linked on the page are tagged as accessible</label>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> Tables have header rows/columns defined</label>
                <label style="display: block; margin: 4px 0;"><input type="checkbox" disabled> Text uses the brand fonts (Libre Baskerville for headings, Open Sans for body)</label>
            </div>
        </details>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=plgc-accessibility'); ?>" class="button">♿ Accessibility Dashboard</a>
            <a href="<?php echo admin_url('upload.php?mode=list'); ?>" class="button">📁 Media Library</a>
            <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button">📝 All Pages</a>
        </div>
    </div>
    <?php
}

/**
 * Remove the default WordPress welcome panel.
 */
remove_action('welcome_panel', 'wp_welcome_panel');


// ─────────────────────────────────────────────────────────────────────────────
// GLOBAL ADMIN — BACKBONE NULL-SAFETY PATCH  (early — attached to backbone handle)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Patch Backbone.View.prototype.$ globally, as early as possible.
 *
 * ── Why the previous admin_footer approach failed ───────────────────────────
 * The WooCommerce email settings crash (backbone.min.js: "Cannot read
 * properties of undefined (reading 'find')") originates from an inline
 * <script> in the page <body> — visible as "admin.php?page=wc-settings&
 * tab=email:1075" in the stack trace. WordPress outputs that inline script
 * during the page render, long before admin_footer fires. By the time our
 * admin_footer patch ran, the Backbone crash had already happened and
 * wp.Backbone.Subviews had already aborted its init chain.
 *
 * ── Correct timing: wp_add_inline_script on 'backbone' ─────────────────────
 * wp_add_inline_script('backbone', $code, 'after') outputs the patch as an
 * inline <script> immediately after backbone.min.js in the <head> — before
 * any plugin's body inline scripts execute. On WooCommerce email settings,
 * backbone is loaded as a dependency of media-views → wp-backbone → backbone,
 * all of which arrive in the <head>. Our patch therefore runs after backbone
 * but before WooCommerce's body script that triggers the crash.
 *
 * wp_enqueue_script('backbone') is called first to guarantee the handle is
 * in the queue even on pages that wouldn't otherwise load it.
 *
 * ── What the patch does ─────────────────────────────────────────────────────
 * WordPress 6.9 changed when wp.Backbone.Subviews.ready() fires — it now
 * calls each subview's ready() before the subview's `el` is attached to the
 * live DOM. Any code inside ready() that calls this.$('selector') resolves
 * to this.$el.find(selector). When this.el is undefined, this.$el is also
 * undefined and .find() throws TypeError, killing the entire init chain.
 *
 * Fix: wrap Backbone.View.prototype.$ so that if this.el is falsy it returns
 * an inert empty jQuery object rather than throwing. All downstream code
 * (.find, .on, .off, .filter, .length) then no-ops gracefully.
 */
add_action( 'admin_enqueue_scripts', 'plgc_backbone_null_safety_patch', 1 );

function plgc_backbone_null_safety_patch(): void {
    if ( wp_doing_ajax() ) return;

    // Guarantee backbone is in the script queue so wp_add_inline_script has
    // a valid handle to attach to, even on pages that don't load it otherwise.
    wp_enqueue_script( 'backbone' );

    $patch = <<<'PATCH'
(function () {
    'use strict';
    if ( typeof Backbone === 'undefined'
         || ! Backbone.View
         || ! Backbone.View.prototype
         || Backbone.View.prototype._plgcPatched ) {
        return;
    }
    Backbone.View.prototype._plgcPatched = true;

    var _orig = Backbone.View.prototype.$;
    Backbone.View.prototype.$ = function ( selector ) {
        /*
         * Guard: this.el is undefined when the view has been created but its
         * element hasn't been inserted into the live DOM yet. wp.Backbone.
         * Subviews.ready() (WP 6.9+) calls ready() on child views at this
         * stage. Any this.$('.x') call inside ready() would throw without this
         * guard. Return an inert jQuery-compatible object so callers no-op.
         */
        if ( ! this.el ) {
            if ( typeof jQuery !== 'undefined' ) {
                return jQuery();   /* empty jQuery set — all methods safe */
            }
            /* Fallback if jQuery isn't available yet (shouldn't happen in WP) */
            var noop = function () { return noop; };
            return { length: 0, on: noop, off: noop, find: noop, filter: noop,
                     prop: noop, addClass: noop, removeClass: noop, is: noop };
        }
        return _orig.call( this, selector );
    };
}());
PATCH;

    // 'after' = output inline script immediately after backbone.min.js.
    // This fires in <head>, before any plugin body inline scripts.
    wp_add_inline_script( 'backbone', $patch, 'after' );
}


// ─────────────────────────────────────────────────────────────────────────────
// GLOBAL ADMIN — MEDIA SELECT BUTTON FIX  (admin_footer — interaction-time)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Re-enable the Select button in wp.media frames after an image is clicked.
 *
 * This is interaction-time (user clicks an image), not page-load, so
 * admin_footer timing is fine — the modal isn't open yet when the page loads.
 *
 * The Select button stays disabled when ACF's selection-change listener
 * failed to bind (e.g. because the field was inside a hidden tab panel when
 * wp.media initialised). Re-enabling on any attachment click is safe: WP
 * core still validates the actual selection state before inserting media.
 */
add_action( 'admin_footer', 'plgc_media_select_button_fix', 5 );

function plgc_media_select_button_fix(): void {
    if ( wp_doing_ajax() ) return;
    ?>
<script id="plgc-media-select-fix">
(function () {
    'use strict';

    function init() {
        if ( typeof wp === 'undefined' || ! wp.media ) return;
        if ( wp.media._plgcSelectFixed ) return;
        wp.media._plgcSelectFixed = true;

        function enableSelectBtn( frame ) {
            if ( ! frame ) return;
            var btn;
            try {
                /*
                 * frame.$() uses Backbone's jQuery scope. Now that the Backbone
                 * patch (above) guards against undefined el, this is safe.
                 */
                btn = ( typeof frame.$ === 'function' )
                    ? frame.$( '.media-button-select' )
                    : ( frame.$el ? frame.$el.find( '.media-button-select' ) : jQuery() );
            } catch ( e ) { return; }
            if ( btn && btn.length ) btn.prop( 'disabled', false ).removeClass( 'disabled' );
        }

        function patchFrame( frame ) {
            if ( ! frame || frame._plgcSelectPatched ) return;
            frame._plgcSelectPatched = true;
            frame.on( 'open', function () {
                var state     = frame.state ? frame.state() : null;
                var selection = state ? state.get( 'selection' ) : null;
                if ( ! selection ) return;
                selection.on(
                    'selection:single selection:multiple add remove reset',
                    function () { enableSelectBtn( frame ); }
                );
                try {
                    frame.$el.on( 'click.plgcselect', '.attachment', function () {
                        setTimeout( function () { enableSelectBtn( frame ); }, 60 );
                    } );
                } catch ( e ) {}
            } );
        }

        if ( wp.media.frame ) patchFrame( wp.media.frame );

        if ( ! wp.media._plgcWrapped ) {
            wp.media._plgcWrapped = true;
            var _orig = wp.media;
            wp.media = function () {
                var frame = _orig.apply( this, arguments );
                patchFrame( frame );
                return frame;
            };
            for ( var k in _orig ) {
                if ( Object.prototype.hasOwnProperty.call( _orig, k ) ) {
                    wp.media[ k ] = _orig[ k ];
                }
            }
        }

        /*
         * Catch-all delegate for the WooCommerce email logo picker and any
         * other media frame that bypasses the wp.media() wrapper.
         */
        jQuery( document )
            .off( 'click.plgcselect' )
            .on( 'click.plgcselect', '.media-modal .attachment', function () {
                setTimeout( function () {
                    var btn = jQuery( '.media-modal-content .media-button-select' );
                    if ( btn.length && btn.is( ':disabled' ) ) {
                        btn.prop( 'disabled', false ).removeClass( 'disabled' );
                    }
                }, 80 );
            } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}());
</script>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// PL SETTINGS PAGE — ADMIN ENHANCEMENTS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Enqueue admin JS + CSS specifically on the PL Settings options page.
 * Handles:
 *   1. wp_enqueue_media() — pre-initialises the WP media stack so ACF's
 *      gallery/image fields work correctly on an ACF options page (which
 *      doesn't call wp_enqueue_media() by default the way a post edit
 *      screen does).
 *   2. ACF field re-init on tab click — fields hidden during page load
 *      never had their Backbone views properly attached; re-trigger ready
 *      when their tab becomes visible.
 *   3. Hide "Homepage Gallery Sections" and "Grass Is Greener" meta boxes
 *      when any tab other than "Homepage" is active.
 *   4. General settings page polish.
 *
 * Note: The Backbone null-safety patch and media Select-button fix that
 * previously lived here have been promoted to plgc_global_admin_media_patch()
 * (above) so they also cover WooCommerce settings, post edit screens, and any
 * other admin page that opens a wp.media frame.
 */
add_action( 'admin_enqueue_scripts', 'plgc_settings_page_admin_assets' );

function plgc_settings_page_admin_assets( string $hook ): void {
    // Only run on our options page
    if ( $hook !== 'toplevel_page_plgc-settings' ) {
        return;
    }

    // Pre-initialize WordPress's media stack so it's ready before ACF's
    // inline scripts run. This enqueues wp-backbone, backbone, media-views, etc.
    // ACF options pages don't call this automatically (unlike post edit screens).
    wp_enqueue_media();

    // ── ACF tab click → re-initialize hidden fields + tab toggle ─────────────
    $js = <<<'JS'
(function () {
    'use strict';

    var HOMEPAGE_GROUPS = [
        'acf-group_plgc_gallery_sections',
        'acf-group_plgc_greener_section'
    ];

    function getActiveTabLabel() {
        var active = document.querySelector(
            '.acf-tab-wrap .acf-tab-button.active, ' +
            '.acf-tab-wrap .acf-tab-button[data-active="1"], ' +
            '.acf-tab-wrap li.active a, ' +
            '.acf-tab-wrap li.-active a'
        );
        return active ? ( active.textContent || '' ).trim().toLowerCase() : '';
    }

    function toggleHomepageGroups() {
        var label       = getActiveTabLabel();
        var showHomepage = ( label === '' || label === 'homepage' );
        HOMEPAGE_GROUPS.forEach( function ( id ) {
            var el = document.getElementById( id );
            if ( ! el ) return;
            el.style.display = showHomepage ? '' : 'none';
        } );
    }

    /**
     * Re-trigger ACF's ready() on image + gallery fields that are now visible.
     *
     * Fields inside hidden panels don't receive proper Backbone initialization
     * at page load (the el exists but has no live DOM context). After the tab
     * click reveals them, we ask ACF to run their ready cycle again so the
     * "Add Image" / "Add to Gallery" buttons become functional.
     */
    function reinitVisibleMediaFields() {
        if ( typeof acf === 'undefined' ) return;

        var selector = '.acf-field[data-type="image"], .acf-field[data-type="gallery"]';
        var fields   = document.querySelectorAll( selector );

        fields.forEach( function ( fieldEl ) {
            // Skip fields that are still hidden or already initialized
            if ( fieldEl.offsetParent === null ) return;

            var fieldObj = ( typeof acf.getField === 'function' )
                ? acf.getField( fieldEl )
                : null;

            if ( ! fieldObj ) return;

            // Re-run the ACF field ready cycle
            if ( typeof fieldObj.ready === 'function' ) {
                try { fieldObj.ready(); } catch ( e ) { /* ignore */ }
            }

            // For gallery fields: also trigger ACF's internal 'ready' action
            if ( typeof acf.do_action === 'function' ) {
                try { acf.do_action( 'ready', fieldEl ); } catch ( e ) { /* ignore */ }
            }
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        toggleHomepageGroups();

        document.addEventListener( 'click', function ( e ) {
            var btn = e.target.closest( '.acf-tab-button, .acf-tab-wrap a' );
            if ( ! btn ) return;

            // ACF sets .active and shows the panel on a very short timeout;
            // give it a beat before we read state or touch fields.
            setTimeout( toggleHomepageGroups, 30 );
            setTimeout( reinitVisibleMediaFields, 80 );
        } );
    } );
}());
JS;

    wp_add_inline_script( 'acf-input', $js );

    // Inline CSS — settings page cosmetic improvements
    $css = '
        /* ── PL Settings: tighten up the layout ───────────────────────────── */

        /* Give the options page a max-width so fields don\'t stretch to 100% on wide screens */
        #plgc-settings .acf-fields > .acf-field {
            padding: 12px 16px;
        }

        /* Consistent label width — prevents fields jumping around */
        #plgc-settings .acf-fields.-left > .acf-field > .acf-label {
            width: 180px;
            min-width: 180px;
        }

        /* Slightly smaller instruction text so it doesn\'t compete with labels */
        #plgc-settings .acf-field .acf-instructions {
            font-size: 12px;
            color: #757575;
            margin-top: 2px;
            line-height: 1.4;
        }

        /* Add a subtle top border between fields for readability */
        #plgc-settings .acf-fields > .acf-field + .acf-field {
            border-top: 1px solid #f3f3f3;
        }

        /* Section separator messages (used for "Image Tiles", "Testimonials" headings) */
        #plgc-settings .acf-field-message {
            background: transparent;
            border: none;
            padding: 8px 16px 0;
        }
        #plgc-settings .acf-field-message h3 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #233C26;
            margin: 0 0 4px;
        }

        /* Repeater table rows — slightly more breathing room */
        #plgc-settings .acf-repeater .acf-row td {
            padding: 10px 12px;
        }

        /* Left-side ACF tabs — brand styling */
        #plgc-settings .acf-tab-wrap {
            background: #f8f8f8;
            border-right: 1px solid #e5e5e5;
        }
        #plgc-settings .acf-tab-wrap .acf-tab-button {
            border-radius: 0;
            font-size: 13px;
        }
        #plgc-settings .acf-tab-wrap .acf-tab-button.active,
        #plgc-settings .acf-tab-wrap .acf-tab-button:hover {
            color: #233C26;
            border-left: 3px solid #FFAE40;
            background: #fff;
        }

        /* Meta box titles for the homepage sub-sections */
        #acf-group_plgc_gallery_sections .postbox-header h2,
        #acf-group_plgc_greener_section .postbox-header h2 {
            font-size: 14px;
            color: #233C26;
        }

        /* Accordion fields inside Greener section — visual grouping */
        #plgc-settings .acf-field-accordion .acf-accordion-title {
            font-size: 13px;
            font-weight: 600;
            background: #f9f9f9;
        }
    ';

    wp_add_inline_style( 'acf-input', $css );
}
