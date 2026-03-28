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
// 1. HIDE GOOGLE MAPS EMBED ON SINGLE EVENT PAGES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Remove the Google Maps embed from single event pages.
 *
 * All events are at Prairie Landing Golf Club — the map adds no navigational
 * value, and TEC's default bundled API key is shared/rate-limited, which
 * causes console errors. The venue section itself is also hidden via CSS
 * (.tribe-events-single-section.secondary { display: none }).
 */
add_filter( 'tribe_get_embedded_map', '__return_empty_string' );
add_filter( 'tribe_events_enable_event_maps', '__return_false' );


// ─────────────────────────────────────────────────────────────────────────────
// 2. HIDE ORGANIZER FROM SINGLE EVENT META
// ─────────────────────────────────────────────────────────────────────────────

/**
 * No organizer records are configured. The empty Organizer section
 * renders an empty <div> with a heading — hiding it removes dead markup.
 */
add_filter( 'tribe_events_show_organizer_in_meta', '__return_false' );


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
