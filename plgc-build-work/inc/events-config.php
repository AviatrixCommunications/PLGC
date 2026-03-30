<?php
/**
 * PLGC Events Configuration
 *
 * Configures The Events Calendar Pro and Event Tickets Plus behavior for
 * Prairie Landing Golf Club's specific context (single venue, no organizers).
 *
 * Registered in functions.php alongside the other inc/ modules.
 *
 * @package PLGC
 * @since   1.6.58
 */

defined( 'ABSPATH' ) || exit;


// ─────────────────────────────────────────────────────────────────────────────
// 1. GOOGLE MAPS — using site's own API key configured in Events → Settings
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Google Maps is enabled. The site's own API key is set in
 * Events → Settings → Integrations → Google Maps API Key.
 * This removes the deprecation warnings and rate-limit errors from the
 * shared TEC default key.
 *
 * Map suppression filters (tribe_get_embedded_map, tribe_events_enable_event_maps)
 * have been removed — the map renders on single event pages in the venue block.
 */


// ─────────────────────────────────────────────────────────────────────────────
// 2. ORGANIZER — shown in single event meta
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Organizer records are now configured in Events → Organizers.
 * The organizer section renders in the secondary meta block alongside venue.
 * Filter tribe_events_show_organizer_in_meta removed — default behaviour (show) applies.
 */


// ─────────────────────────────────────────────────────────────────────────────
// 3. STRUCTURED DATA — ADD VENUE TO LD+JSON
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The default LD+JSON outputs "location": false because no venue record
 * has been saved against the events. This filter injects PLGC's address
 * into all event schema, improving Google Search Event rich results.
 *
 * @param  array    $data  Existing JSON-LD event object.
 * @param  WP_Post  $post  The event post.
 * @return array
 */
add_filter( 'tribe_events_json_ld_event_object', 'plgc_events_add_venue_schema', 10, 2 );

function plgc_events_add_venue_schema( array $data, WP_Post $post ): array {
    // Only overwrite if no location is already set (avoids clobbering
    // if a specific venue is ever added to an individual event).
    if ( empty( $data['location'] ) || $data['location'] === false ) {
        $data['location'] = [
            '@type'   => 'Place',
            'name'    => 'Prairie Landing Golf Club',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => '2325 Longest Drive',
                'addressLocality' => 'West Chicago',
                'addressRegion'   => 'IL',
                'postalCode'      => '60185',
                'addressCountry'  => 'US',
            ],
            'url' => home_url( '/' ),
        ];
    }
    return $data;
}


// ─────────────────────────────────────────────────────────────────────────────
// 4. STRIP INLINE EDITOR CLASSES FROM EVENT CONTENT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The client pastes event descriptions from Canva and social media editors
 * that inject classes like .a_GcMg, .cvGsUA, and font-feature-* onto <span>
 * elements. These classes are meaningless in our theme context and can
 * interfere with our typography cascade.
 *
 * This filter strips class and style attributes from <span> elements in
 * event content — text and markup structure are untouched.
 *
 * Scoped to tribe_events singular pages only.
 */
add_filter( 'the_content', 'plgc_events_strip_editor_spans', 10 );

function plgc_events_strip_editor_spans( string $content ): string {
    // Only run on single event pages
    if ( ! is_singular( 'tribe_events' ) ) {
        return $content;
    }

    return preg_replace_callback(
        '/<span([^>]*)>/i',
        static function ( array $matches ): string {
            $attrs = $matches[1];

            // Patterns that identify injected editor classes
            $editor_patterns = [
                'a_GcMg', 'cvGsUA', 'font-feature-', 'text-decoration-none',
                'text-strikethrough', 'direction-ltr', 'para-style', 'align-center',
            ];

            // Check if this span's class attribute contains only editor noise
            if ( preg_match( '/\s*class="([^"]*)"/i', $attrs, $class_match ) ) {
                $has_real_class = true;
                $classes        = $class_match[1];

                // If every class in the attribute matches editor patterns, remove it
                $individual_classes = preg_split( '/\s+/', trim( $classes ) );
                $all_editor         = ! empty( $individual_classes ) && array_reduce(
                    $individual_classes,
                    static function ( bool $carry, string $cls ) use ( $editor_patterns ): bool {
                        if ( ! $carry ) return false;
                        foreach ( $editor_patterns as $pattern ) {
                            if ( str_contains( $cls, $pattern ) ) return true;
                        }
                        return false; // Found a non-editor class — keep the attribute
                    },
                    true
                );

                if ( $all_editor ) {
                    $attrs = preg_replace( '/\s*class="[^"]*"/i', '', $attrs );
                }
            }

            // Remove empty style attributes
            $attrs = preg_replace( '/\s*style="\s*"/i', '', $attrs );

            return '<span' . $attrs . '>';
        },
        $content
    );
}


// ─────────────────────────────────────────────────────────────────────────────
// 5. SUPPRESS FACEBOOK SDK CONSOLE ERROR
// ─────────────────────────────────────────────────────────────────────────────

/**
 * TEC/ECP's Open Graph / social share feature attempts to load the Facebook
 * SDK, which is blocked by the site's CSP (connect.facebook.net is not in
 * script-src). This generates a noisy console error on every event page.
 *
 * Removing the action prevents the SDK from being enqueued at all.
 * If social sharing needs to be re-enabled later, add connect.facebook.net
 * to WP Engine's CSP header settings instead of reverting this.
 */
add_action( 'wp', 'plgc_events_remove_facebook_opengraph', 1 );

function plgc_events_remove_facebook_opengraph(): void {
    // TEC free ≤ 6.x
    remove_action( 'wp_head', [ 'Tribe__Events__API', 'add_facebook_opengraph_tags' ], 10 );

    // TEC 6.x+ uses a container-resolved instance
    if ( function_exists( 'tribe' ) ) {
        try {
            $main = tribe( 'tec.main' );
            if ( is_object( $main ) ) {
                remove_action( 'wp_head', [ $main, 'add_facebook_opengraph_tags' ], 10 );
            }
        } catch ( \Throwable $e ) {
            // Container not ready — safe to ignore
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// 6. ALT TEXT ENFORCEMENT — COVERAGE REMINDER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Reminder: verify that inc/alt-text-enforcement.php covers tribe_events
 * post type images. WCAG 1.1.1 applies equally to event featured images.
 *
 * The client's current images include `alt=""` (correct — they are decorative
 * flyers with information duplicated in the event text fields).
 * If an image contains information NOT present in the HTML description,
 * it must have a meaningful alt text instead.
 */


// ─────────────────────────────────────────────────────────────────────────────
// 7. BACK LINK — "« All Events" → /calendar/ instead of /events/
// ─────────────────────────────────────────────────────────────────────────────

/**
 * TEC's "« All Events" back link on single event pages points to /events/ by
 * default (the auto-generated archive URL). We want it to go to /calendar/
 * which is the Elementor-wrapped page with the shortcode.
 *
 * tribe_get_events_link is the filter for tribe_get_events_link(). Only runs
 * on single event pages to avoid affecting any admin pagination links.
 */
add_filter( 'tribe_get_events_link', 'plgc_events_back_link' );

function plgc_events_back_link( string $link ): string {
	if ( is_singular( 'tribe_events' ) ) {
		return home_url( '/calendar/' );
	}
	return $link;
}


// ─────────────────────────────────────────────────────────────────────────────
// 8. SEO — NOINDEX CATEGORY ARCHIVES + REDIRECT /events/ → /calendar/
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Adds noindex to TEC category archive pages (/events/category/golf/ etc.).
 *
 * We link to these from restaurant/golf pages for filtered views, but don't
 * want them indexed independently. The main /calendar/ page is canonical.
 * "noindex, follow" lets Google still crawl individual event links on the page.
 *
 * Note: if Yoast or RankMath is ever installed, use their archive noindex
 * settings instead to avoid duplicate meta tags.
 */
add_action( 'wp_head', 'plgc_events_cat_noindex', 1 );

function plgc_events_cat_noindex(): void {
    if ( is_tax( 'tribe_events_cat' ) ) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}

/**
 * PHP fallback redirect: /events/ → /calendar/
 *
 * The primary redirect should be a server-level rule in WP Engine's redirect
 * manager (/events/* → /calendar/) which fires before WordPress loads.
 * Once that WP Engine rule is in place this becomes a no-op.
 * Preserves query strings: /events/?tribe-bar-search=scramble → /calendar/?...
 */
add_action( 'template_redirect', 'plgc_events_archive_redirect' );

function plgc_events_archive_redirect(): void {
    if ( ! is_post_type_archive( 'tribe_events' ) ) {
        return;
    }
    $qs = ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '';
    wp_redirect( home_url( '/calendar/' . $qs ), 301 );
    exit;
}


// ─────────────────────────────────────────────────────────────────────────────
// 9. FILTERED SHORTCODE — add class when category attribute is present
// ─────────────────────────────────────────────────────────────────────────────

/**
 * When [tribe_events category="golf-calendar"] is used, the view selector tabs
 * let visitors switch to Month view which ignores the category filter.
 *
 * This adds a 'plgc-filtered' class to those shortcode containers so events.css
 * can hide the view selector on filtered embeds only. Main /calendar/ page
 * (no category attribute) is completely unaffected.
 *
 * Usage on any page:
 *   [tribe_events view="list" category="mcchesneys-pub-grill"]
 *   [tribe_events view="list" category="golf-calendar"]
 */
add_filter( 'tribe_events_views_v2_view_container_classes', 'plgc_events_filtered_class', 10, 2 );

function plgc_events_filtered_class( array $classes, $view ): array {
    $has_cat = false;

    // Check URL/request variable (set by TEC when shortcode category attr is active)
    if ( ! empty( tribe_get_request_var( 'tribe_events_cat', '' ) ) ) {
        $has_cat = true;
    }

    // Also check via the view context object (TEC 6+)
    if ( ! $has_cat && method_exists( $view, 'get_context' ) ) {
        try {
            $cat     = $view->get_context()->get( 'event_category', '' );
            $has_cat = ! empty( $cat );
        } catch ( \Throwable $e ) {
            // Context API unavailable — safe to ignore
        }
    }

    if ( $has_cat ) {
        $classes[] = 'plgc-filtered';
    }

    return $classes;
}


// ─────────────────────────────────────────────────────────────────────────────
// 10. MONTH VIEW — Full day names, sentence case (not abbreviated, not all-caps)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * TEC renders month view column headers as abbreviated day names (Mon, Tue…)
 * pulled from the site's locale. This filter replaces them with full names
 * in sentence case (Monday, Tuesday…) per client preference.
 *
 * WCAG 1.3.1 note: abbreviated day names without an <abbr title=""> cause
 * ambiguity for screen reader users — full names remove this concern entirely.
 *
 * The filter runs on tribe_events_month_grid_header_title which ECP exposes
 * for exactly this purpose.
 */
add_filter( 'tribe_events_month_grid_header_title', 'plgc_events_full_day_names', 10, 2 );

function plgc_events_full_day_names( string $title, int $day_num ): string {
    // $day_num: 0 = Sunday … 6 = Saturday (matches PHP date('w'))
    $full_names = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];
    return $full_names[ $day_num ] ?? $title;
}
