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
define('PLGC_VERSION', '1.7.55');
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
        'primary'      => __('Primary Navigation', 'plgc'),
        'footer'       => __('Footer Navigation', 'plgc'),
        'footer-legal' => __('Footer Legal / Policy Links', 'plgc'),
        'utility'      => __('Utility Navigation', 'plgc'),
    ]);
}
add_action('after_setup_theme', 'plgc_setup');

/**
 * ============================================================
 * FOOTER LEGAL NAV — Custom Walker
 * ============================================================
 * Renders the "Footer Legal / Policy Links" menu in the sub-footer.
 *
 * Magic URLs:
 *
 *   #privacy-settings   (recommended — Termageddon)
 *     Renders the [uc-privacysettings] shortcode so the Termageddon
 *     plugin controls SDK readiness, geolocation, and visibility.
 *     Falls back to a plain Cookie Policy link if the plugin is off.
 *
 *   #manage-cookies   (generic fallback)
 *     Renders a <button> that fires the JS expression from
 *     PL Settings → Cookie & Legal → "Cookie Settings Button".
 *     Useful for non-Termageddon consent tools.
 */
class PLGC_Legal_Nav_Walker extends Walker_Nav_Menu {

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $output .= '<li>';
        $url = trim( $item->url );

        // ── Termageddon / Usercentrics privacy-settings shortcode ──
        // Menu item URL: #privacy-settings
        // Renders the [uc-privacysettings] shortcode so the plugin
        // handles SDK readiness, geolocation, and visibility.
        if ( $url === '#privacy-settings' ) {
            if ( shortcode_exists( 'uc-privacysettings' ) ) {
                $output .= do_shortcode(
                    '[uc-privacysettings text="' . esc_attr( $item->title ) . '"]'
                );
            } else {
                // Plugin not active — render a plain link to the cookie policy page.
                $fallback_url = plgc_option( 'plgc_cookie_policy_url' );
                if ( $fallback_url ) {
                    $output .= sprintf(
                        '<a href="%s" class="plgc-footer__legal-link">%s</a>',
                        esc_url( $fallback_url ),
                        esc_html( $item->title )
                    );
                }
            }
            return;
        }

        // ── Generic cookie-consent button (non-Termageddon) ──
        // Menu item URL: #manage-cookies
        // Fires the JS expression from PL Settings → Cookie & Legal.
        if ( $url === '#manage-cookies' ) {
            $cookie_js = plgc_option(
                'plgc_cookie_js_method',
                "var el=document.querySelector('[id*=\"mcm\"][role=\"button\"], [class*=\"mcm-consent\"], [id*=\"monsido-consent\"], [aria-label*=\"cookie\"][aria-label*=\"consent\"]'); if(el) el.click();"
            );
            $output .= sprintf(
                '<button type="button" class="plgc-footer__legal-link plgc-footer__cookie-btn" onclick="%s" aria-label="%s">%s</button>',
                esc_attr( $cookie_js ),
                esc_attr__( 'Open cookie consent settings', 'plgc' ),
                esc_html( $item->title )
            );

            $cookie_url = plgc_option( 'plgc_cookie_policy_url' );
            if ( $cookie_url ) {
                $output .= sprintf(
                    '<noscript><a href="%s" class="plgc-footer__legal-link">%s</a></noscript>',
                    esc_url( $cookie_url ),
                    esc_html( $item->title )
                );
            }
            return;
        }

        // ── Regular link ──
        $atts = '';
        if ( ! empty( $item->target ) ) {
            $atts .= ' target="' . esc_attr( $item->target ) . '"';
            if ( $item->target === '_blank' ) {
                $atts .= ' rel="noopener noreferrer"';
            }
        }
        $output .= sprintf(
            '<a href="%s" class="plgc-footer__legal-link"%s>%s</a>',
            esc_url( $item->url ),
            $atts,
            esc_html( $item->title )
        );
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        $output .= '</li>';
    }
}

/**
 * Fallback for the footer-legal menu when no menu is assigned.
 * Renders the original hardcoded links so the sub-footer isn't
 * empty before the menu is set up.
 */
function plgc_legal_nav_fallback() {
    $privacy_url = plgc_option( 'plgc_privacy_policy_url' ) ?: get_privacy_policy_url();
    $a11y_url    = home_url( '/accessibility-statement/' );
    $cookie_js   = plgc_option(
        'plgc_cookie_js_method',
        "var el=document.querySelector('[id*=\"mcm\"][role=\"button\"], [class*=\"mcm-consent\"], [id*=\"monsido-consent\"], [aria-label*=\"cookie\"][aria-label*=\"consent\"]'); if(el) el.click();"
    );
    $cookie_url  = plgc_option( 'plgc_cookie_policy_url' );

    echo '<ul class="plgc-footer__legal-list" role="list">';

    if ( $privacy_url ) {
        printf( '<li><a href="%s" class="plgc-footer__legal-link">Privacy Policy</a></li>', esc_url( $privacy_url ) );
    }

    printf( '<li><a href="%s" class="plgc-footer__legal-link">Accessibility Statement</a></li>', esc_url( $a11y_url ) );

    if ( $cookie_js ) {
        echo '<li>';
        printf(
            '<button type="button" class="plgc-footer__legal-link plgc-footer__cookie-btn" onclick="%s" aria-label="Open cookie consent settings">Manage Cookie Settings</button>',
            esc_attr( $cookie_js )
        );
        if ( $cookie_url ) {
            printf( '<noscript><a href="%s" class="plgc-footer__legal-link">Cookie Policy</a></noscript>', esc_url( $cookie_url ) );
        }
        echo '</li>';
    } elseif ( $cookie_url ) {
        printf( '<li><a href="%s" class="plgc-footer__legal-link">Cookie Policy</a></li>', esc_url( $cookie_url ) );
    }

    echo '</ul>';
}

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
        'restUrl' => esc_url_raw(rest_url('wp/v2/')),
    ]);

    // Search results page
    if (is_search()) {
        wp_enqueue_style(
            'plgc-search-results',
            PLGC_URI . '/assets/css/search-results.css',
            ['plgc-theme'],
            PLGC_VERSION
        );
    }

    // News & Updates — archive listing and single articles
    if (is_home() || is_archive() || is_singular('post')) {
        wp_enqueue_style(
            'plgc-news',
            PLGC_URI . '/assets/css/news.css',
            ['plgc-theme'],
            PLGC_VERSION
        );
    }

    // 404 page
    if (is_404()) {
        wp_enqueue_style(
            'plgc-404',
            PLGC_URI . '/assets/css/404.css',
            ['plgc-theme'],
            PLGC_VERSION
        );
    }
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
 * Conditional noindex for News & Updates while empty.
 *
 * Prevents search engines from indexing the blog archive and single
 * post pages while there are zero published posts. Automatically
 * lifts the restriction the moment the first article is published —
 * no manual action required.
 *
 * Uses a transient to avoid a COUNT query on every page load.
 */
function plgc_news_conditional_noindex() {
    if (! is_home() && ! is_singular('post') && ! (is_archive() && ! is_post_type_archive())) {
        return;
    }

    // Cache the post count for 1 hour.
    $count = get_transient('plgc_published_post_count');
    if ($count === false) {
        $count = (int) wp_count_posts('post')->publish;
        set_transient('plgc_published_post_count', $count, HOUR_IN_SECONDS);
    }

    if ($count < 1) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}
add_action('wp_head', 'plgc_news_conditional_noindex', 2);

/**
 * Flush the post count transient when a post is published or trashed.
 */
function plgc_flush_post_count_transient($new_status, $old_status, $post) {
    if ($post->post_type === 'post' && ($new_status === 'publish' || $old_status === 'publish')) {
        delete_transient('plgc_published_post_count');
    }
}
add_action('transition_post_status', 'plgc_flush_post_count_transient', 10, 3);

/**
 * ============================================================
 * ACQUIA WEB GOVERNANCE / MONSIDO
 * ============================================================
 * Outputs the full script block pasted into PL Settings →
 * Cookie & Legal → "Acquia Web Governance — Script".
 *
 * Placement is controlled by the "Script Placement" dropdown:
 *   body  →  wp_body_open  (right after <body>, Acquia default)
 *   head  →  wp_head       (before other scripts, for Consent Mgr)
 *
 * Only administrators can edit the options page, so the
 * unescaped output is intentional.
 */
function plgc_acquia_script_output() {
    static $done = false;
    if ( $done ) {
        return;
    }

    if ( ! function_exists( 'plgc_option' ) ) {
        return;
    }

    // Check placement matches the current hook.
    $placement = plgc_option( 'plgc_acquia_script_placement', 'body' );
    $hook      = current_action();
    if ( ( $placement === 'head' && $hook !== 'wp_head' ) ||
         ( $placement !== 'head' && $hook !== 'wp_body_open' ) ) {
        return;
    }

    $script = trim( plgc_option( 'plgc_acquia_script' ) );
    if ( empty( $script ) ) {
        return;
    }

    $done = true;

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-only script block
    echo "\n<!-- Acquia Web Governance -->\n" . $script . "\n<!-- / Acquia -->\n";
}
add_action( 'wp_head',      'plgc_acquia_script_output', 5 );
add_action( 'wp_body_open', 'plgc_acquia_script_output', 5 );

/**
 * ============================================================
 * GOOGLE ANALYTICS 4
 * ============================================================
 * Outputs the standard gtag.js snippet when a GA4 Measurement ID
 * is set in PL Settings → Analytics. Loads async in <head> at
 * priority 2 (early, before other scripts).
 *
 * The ID is validated to match the G-XXXXXXXXXX format.
 * Only loads on the front end (not admin, not login, not previews).
 */
function plgc_ga4_output() {
    if ( is_admin() || is_preview() || wp_doing_ajax() ) {
        return;
    }

    if ( ! function_exists( 'plgc_option' ) ) {
        return;
    }

    $ga4_id = trim( plgc_option( 'plgc_ga4_id' ) );
    if ( empty( $ga4_id ) || ! preg_match( '/^G-[A-Z0-9]{4,15}$/i', $ga4_id ) ) {
        return;
    }

    // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- gtag.js must be inline per Google's spec
    $id = esc_attr( $ga4_id );
    echo "\n<!-- Google Analytics 4 -->\n"
        . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $id . '"></script>' . "\n"
        . "<script>\n"
        . "window.dataLayer = window.dataLayer || [];\n"
        . "function gtag(){dataLayer.push(arguments);}\n"
        . "gtag('js', new Date());\n"
        . "gtag('config', '" . $id . "');\n"
        . "</script>\n"
        . "<!-- / GA4 -->\n";
    // phpcs:enable
}
add_action( 'wp_head', 'plgc_ga4_output', 2 );

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
 * Exclude noindexed pages from search results.
 *
 * Checks Rank Math's `rank_math_robots` post meta for 'noindex'.
 * Applies to both the front-end search results page (search.php)
 * and REST API search queries used by the AJAX nav search.
 *
 * Common pages excluded: Cart, Checkout, My Account, Privacy Policy,
 * Terms of Service, Disclaimer, Shop, Membership Application, etc.
 */
add_action('pre_get_posts', function ($query) {
    // Only filter search queries on the front end
    if (is_admin()) {
        return;
    }

    // Must be a search query (covers both search.php and REST /search endpoint)
    if (! $query->is_search() && empty($query->get('s'))) {
        return;
    }

    $excluded = plgc_get_noindex_post_ids();
    if (! empty($excluded)) {
        $existing = $query->get('post__not_in');
        $query->set('post__not_in', array_merge((array) $existing, $excluded));
    }
});

/**
 * Belt-and-suspenders: also filter via rest_post_query for any
 * REST endpoints that build their own WP_Query args.
 */
add_filter('rest_post_query', function ($args, $request) {
    if (! empty($request->get_param('search'))) {
        $excluded = plgc_get_noindex_post_ids();
        if (! empty($excluded)) {
            $existing = isset($args['post__not_in']) ? (array) $args['post__not_in'] : [];
            $args['post__not_in'] = array_merge($existing, $excluded);
        }
    }
    return $args;
}, 10, 2);

/**
 * Get all post IDs that have Rank Math noindex set.
 * Cached per-request via static variable. No transient —
 * the query is fast and this avoids stale cache issues when
 * Rank Math updates noindex via post meta (which doesn't
 * trigger save_post).
 */
function plgc_get_noindex_post_ids() {
    static $ids = null;

    if ($ids !== null) {
        return $ids;
    }

    global $wpdb;

    // Rank Math stores robots as a serialized array in post meta.
    // A noindexed post will have 'noindex' somewhere in the value.
    $results = $wpdb->get_col(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = 'rank_math_robots'
         AND meta_value LIKE '%noindex%'"
    );

    $ids = array_map('intval', $results);

    return $ids;
}

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

// Events Calendar Pro + Event Tickets Plus — branding, behavior, schema
require_once PLGC_DIR . '/inc/events-config.php';

// [plgc_social_icons] shortcode — used in Section 2 and anywhere else needed
require_once PLGC_DIR . '/inc/social-icons-shortcode.php';

// Restaurant Menu CPT — [plgc_menu] shortcode, CSV importer, Schema.org
require_once PLGC_DIR . '/inc/menu-cpt.php';

// Floating Mini-Cart — sticky cart icon when WooCommerce cart has items
require_once PLGC_DIR . '/inc/woocommerce-mini-cart.php';

// Search enhancements — punctuation-insensitive matching (apostrophes, curly quotes)
require_once PLGC_DIR . '/inc/search-config.php';

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

/**
 * Gravity Forms — Brand Stylesheet
 *
 * Enqueued globally on all front-end pages (GF forms can appear anywhere).
 * Also enqueued in the Elementor editor preview so form embeds look correct
 * while editing. Loaded after GF's own styles so our overrides win cleanly.
 *
 * GF renders its own stylesheet with handle 'gforms_reset_css' and
 * 'gforms_formsmain_css'. We declare those as optional dependencies so our
 * sheet always loads after them when they're present.
 */
add_action( 'wp_enqueue_scripts', function () {
    // Only load if Gravity Forms is active
    if ( ! class_exists( 'GFForms' ) ) {
        return;
    }

    wp_enqueue_style(
        'plgc-gravity-forms',
        PLGC_URI . '/assets/css/gravity-forms.css',
        [ 'plgc-theme' ],           // load after our design tokens
        PLGC_VERSION
    );
}, 20 ); // priority 20 — after GF's own wp_enqueue_scripts at priority 10

add_action( 'elementor/preview/enqueue_styles', function () {
    if ( ! class_exists( 'GFForms' ) ) {
        return;
    }

    wp_enqueue_style(
        'plgc-gravity-forms',
        PLGC_URI . '/assets/css/gravity-forms.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
} );

/**
 * Events Calendar Pro — Brand Stylesheet
 *
 * Loaded at priority 20 so it enqueues after TEC's own stylesheets,
 * ensuring our overrides win without needing excessive !important usage.
 * Gated on TEC being active so it doesn't load on non-events pages when
 * TEC is deactivated.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! class_exists( 'Tribe__Events__Main' ) ) {
        return;
    }

    wp_enqueue_style(
        'plgc-events',
        PLGC_URI . '/assets/css/events.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
}, 20 );

/**
 * WP Job Openings — Brand Stylesheet
 *
 * Loaded at priority 20 so it enqueues after the plugin's own stylesheets,
 * ensuring our overrides win without needing excessive !important usage.
 * Gated on the plugin's post type being registered so it doesn't load
 * when the plugin is deactivated.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! post_type_exists( 'awsm_job_openings' ) ) {
        return;
    }

    wp_enqueue_style(
        'plgc-job-openings',
        PLGC_URI . '/assets/css/job-openings.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
}, 20 );

/**
 * WP Job Openings — Inject H1 Title on Single Job Pages
 *
 * Hello Elementor's single template doesn't output the_title() for
 * custom post types. This prepends a proper H1 before the job description.
 *
 * Back link and specs bar are handled by the plugin's native settings:
 *   - Settings → Appearance → "Back to listings link" = enabled
 *   - Settings → Appearance → Job spec position = "Above job description"
 */
add_filter( 'the_content', function ( $content ) {
    if ( ! is_singular( 'awsm_job_openings' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $title = get_the_title();
    if ( empty( $title ) ) {
        return $content;
    }

    $h1 = '<h1 class="awsm-job-page-title">' . esc_html( $title ) . '</h1>';

    return $h1 . $content;
}, 5 );
