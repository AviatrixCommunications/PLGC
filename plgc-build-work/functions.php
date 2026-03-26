<?php
/**
 * Prairie Landing Golf Club - Hello Elementor Child Theme
 *
 * WCAG 2.1 AA compliant child theme with locked-down design system
 * and role-based restrictions for Title II accessibility.
 *
 * @package PLGC
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Constants
 */
define('PLGC_VERSION', '1.6.31');
define('PLGC_DIR', get_stylesheet_directory());
define('PLGC_URI', get_stylesheet_directory_uri());

/**
 * Theme Setup
 */
function plgc_setup() {
    add_theme_support('custom-logo', [
        'height'      => 100,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');

    register_nav_menus([
        'primary' => __('Primary Navigation', 'plgc'),
        'footer'  => __('Footer Navigation', 'plgc'),
        'utility' => __('Utility Navigation', 'plgc'),
    ]);
}
add_action('after_setup_theme', 'plgc_setup');

/**
 * Enqueue Parent + Child Styles & Scripts
 */
function plgc_enqueue_assets() {
    // Parent theme
    wp_enqueue_style(
        'hello-elementor',
        get_template_directory_uri() . '/style.css',
        [],
        PLGC_VERSION
    );

    // Child theme - design system & accessibility
    wp_enqueue_style(
        'plgc-theme',
        PLGC_URI . '/assets/css/theme.css',
        ['hello-elementor'],
        PLGC_VERSION
    );

    // Google Fonts — Libre Baskerville + Open Sans
    // Elementor Pro also loads these; WordPress deduplicates so no double-load.
    wp_enqueue_style(
        'plgc-fonts',
        'https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Open+Sans:wght@400;600;700&display=swap',
        [],
        null
    );

    // Navigation styles
    wp_enqueue_style(
        'plgc-nav',
        PLGC_URI . '/assets/css/nav.css',
        ['plgc-theme'],
        PLGC_VERSION
    );

    // Footer styles
    wp_enqueue_style(
        'plgc-footer',
        PLGC_URI . '/assets/css/footer.css',
        ['plgc-theme'],
        PLGC_VERSION
    );

    // WooCommerce styles — loads globally so notices + cart + checkout work everywhere
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style(
            'plgc-woocommerce',
            PLGC_URI . '/assets/css/woocommerce.css',
            ['plgc-theme'],
            PLGC_VERSION
        );
    }

    // Accessibility enhancements
    wp_enqueue_script(
        'plgc-a11y',
        PLGC_URI . '/assets/js/accessibility.js',
        [],
        PLGC_VERSION,
        true
    );

    // Navigation JS
    wp_enqueue_script(
        'plgc-nav',
        PLGC_URI . '/assets/js/nav.js',
        [],
        PLGC_VERSION,
        true
    );
    wp_localize_script('plgc-nav', 'plgcNav', [
        'restUrl' => esc_url_raw(rest_url()),
    ]);
}
add_action('wp_enqueue_scripts', 'plgc_enqueue_assets');

/**
 * Enqueue Elementor Editor Overrides
 */
function plgc_enqueue_elementor_editor() {
    wp_enqueue_style(
        'plgc-elementor-editor',
        PLGC_URI . '/assets/css/elementor-editor.css',
        [],
        PLGC_VERSION
    );
}
add_action('elementor/editor/after_enqueue_styles', 'plgc_enqueue_elementor_editor');

/**
 * Enqueue Elementor Preview Styles
 */
function plgc_enqueue_elementor_preview() {
    wp_enqueue_style(
        'plgc-fonts',
        'https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Open+Sans:wght@400;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style('plgc-theme', PLGC_URI . '/assets/css/theme.css', [], PLGC_VERSION);
}
add_action('elementor/preview/enqueue_styles', 'plgc_enqueue_elementor_preview');

/**
 * Skip-to-Content Link
 */
function plgc_skip_link() {
    echo '<a class="plgc-skip-link screen-reader-text" href="#main-content">' .
         esc_html__('Skip to main content', 'plgc') .
         '</a>';
}
add_action('wp_body_open', 'plgc_skip_link', 1);

/**
 * Accessibility Meta
 * Ensure viewport allows user scaling (WCAG 1.4.4)
 */
function plgc_a11y_meta() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
}
add_action('wp_head', 'plgc_a11y_meta', 1);

/**
 * ============================================================
 * WOOCOMMERCE
 * ============================================================
 */

// Note: woocommerce_return_to_shop_redirect is registered in inc/plugin-accessibility.php

/**
 * ============================================================
 * SEARCH — REST API & WP Engine Smart Search
 * ============================================================
 *
 * WP Engine Smart Search hooks into WP_Query automatically, so
 * the standard REST search endpoint (/wp-json/wp/v2/search) uses
 * its Elasticsearch backend without any extra config.
 *
 * The filter below ensures attachment/document results are also
 * returned when the nav JS queries type=attachment.
 */
add_filter('rest_attachment_query', function ($args, $request) {
    if (! empty($request->get_param('search'))) {
        $args['post_status'] = 'inherit';
    }
    return $args;
}, 10, 2);

/**
 * ============================================================
 * Include Modules
 * ============================================================
 */

// Navigation — mega menu builder
require_once PLGC_DIR . '/inc/nav-mega-menu.php';

// ACF options page — site-wide settings (contact, social, legal, branding)
// Weather widget is now the standalone PLGC Weather Widget plugin.
require_once PLGC_DIR . '/inc/acf-options.php';

// Design system enforcement for Elementor
require_once PLGC_DIR . '/inc/elementor-lockdowns.php';

// Custom client role
require_once PLGC_DIR . '/inc/custom-roles.php';

// Accessibility enhancements
require_once PLGC_DIR . '/inc/accessibility.php';

// Admin cleanup & dashboard UX
require_once PLGC_DIR . '/inc/admin-cleanup.php';

// WooCommerce & Events Calendar accessibility
require_once PLGC_DIR . '/inc/plugin-accessibility.php';

// Content guardrails — catches WCAG violations on save
require_once PLGC_DIR . '/inc/content-guardrails.php';

// Media embed accessibility — video captions, iframe titles, audio transcripts
require_once PLGC_DIR . '/inc/media-embed-a11y.php';

// Accessibility dashboard — single overview of site compliance
require_once PLGC_DIR . '/inc/a11y-dashboard.php';

// Accessibility statement shortcode
require_once PLGC_DIR . '/inc/a11y-statement.php';

// Alt text enforcement — upload prompts, publish blocks, PDF link enhancement, bulk scanner
require_once PLGC_DIR . '/inc/alt-text-enforcement.php';

// Homepage gallery sections — ACF fields + [plgc_gallery_section] shortcode
require_once PLGC_DIR . '/inc/gallery-sections.php';

// "Grass Is Greener" homepage section — ACF tile images + [plgc_greener_section] shortcode
require_once PLGC_DIR . '/inc/greener-section.php';

// Featured Events Slider — [plgc_event_slider] shortcode + ECP + Woo Tickets integration
require_once PLGC_DIR . '/inc/event-slider.php';

// [plgc_social_icons] shortcode — used in Section 2 and anywhere else needed
require_once PLGC_DIR . '/inc/social-icons-shortcode.php';

// ─────────────────────────────────────────────────────────────────────────────
// CUSTOM ELEMENTOR GALLERY WIDGETS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Register the PLGC Elementor widget category so both gallery widgets
 * appear in their own "PLGC" section in the widget panel.
 */
add_action( 'elementor/elements/categories_registered', function ( $elements_manager ) {
    $elements_manager->add_category( 'plgc', [
        'title' => 'Prairie Landing',
        'icon'  => 'eicon-gallery-grid',
    ] );
} );

/**
 * Register the Gallery Grid and Gallery Strip widgets.
 * Loads after Elementor is ready so Widget_Base is available.
 */
add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
    require_once PLGC_DIR . '/inc/elementor-widgets/class-plgc-gallery-filmstrip-widget.php';
    require_once PLGC_DIR . '/inc/elementor-widgets/class-plgc-content-slideshow-widget.php';
    require_once PLGC_DIR . '/inc/elementor-widgets/class-plgc-hero-widget.php';
    require_once PLGC_DIR . '/inc/elementor-widgets/class-plgc-two-col-accordion-widget.php';

    $widgets_manager->register( new PLGC_Gallery_Filmstrip_Widget() );
    $widgets_manager->register( new PLGC_Content_Slideshow_Widget() );
    $widgets_manager->register( new PLGC_Hero_Widget() );
    $widgets_manager->register( new PLGC_Two_Col_Accordion_Widget() );
} );

/**
 * Register hero CSS + JS (only loads on pages using the hero widget).
 */
add_action( 'wp_enqueue_scripts', function () {
    wp_register_style(
        'plgc-hero',
        PLGC_URI . '/assets/css/hero.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
    wp_register_script(
        'plgc-hero',
        PLGC_URI . '/assets/js/hero.js',
        [],
        PLGC_VERSION,
        [ 'strategy' => 'defer', 'in_footer' => true ]
    );
} );

/**
 * Register the shared gallery CSS + JS.
 * Widgets declare these as dependencies via get_style_depends / get_script_depends,
 * so they only load on pages that actually use a gallery widget.
 */
add_action( 'wp_enqueue_scripts', function () {
    wp_register_style(
        'plgc-gallery-widgets',
        PLGC_URI . '/assets/css/gallery-widgets.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
    wp_register_script(
        'plgc-gallery-widgets',
        PLGC_URI . '/assets/js/gallery-widgets.js',
        [],
        PLGC_VERSION,
        [ 'strategy' => 'defer', 'in_footer' => true ]
    );
} );

/**
 * Also enqueue the gallery assets in the Elementor editor preview
 * so editors see the correct styles while editing.
 */
add_action( 'elementor/preview/enqueue_styles', function () {
    wp_enqueue_style(
        'plgc-gallery-widgets',
        PLGC_URI . '/assets/css/gallery-widgets.css',
        [],
        PLGC_VERSION
    );
    wp_enqueue_style(
        'plgc-hero',
        PLGC_URI . '/assets/css/hero.css',
        [],
        PLGC_VERSION
    );
    wp_enqueue_style(
        'plgc-two-col-accordion',
        PLGC_URI . '/assets/css/accordion.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
} );

/**
 * Register the Two-Column Accordion CSS + JS.
 * The widget declares these as dependencies via get_style_depends / get_script_depends,
 * so they only load on pages that actually use the accordion widget.
 */
add_action( 'wp_enqueue_scripts', function () {
    wp_register_style(
        'plgc-two-col-accordion',
        PLGC_URI . '/assets/css/accordion.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
    wp_register_script(
        'plgc-two-col-accordion',
        PLGC_URI . '/assets/js/accordion.js',
        [],
        PLGC_VERSION,
        [ 'strategy' => 'defer', 'in_footer' => true ]
    );
} );
