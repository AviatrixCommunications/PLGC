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
 * @since   1.7.0
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
// 5b. DEQUEUE FACEBOOK SDK SCRIPT ENTIRELY
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The wp_head hook approach above removes the meta tag but TEC may still
 * enqueue the Facebook SDK JS via wp_enqueue_scripts. This dequeues it
 * directly, which is what stops the CSP console error on event pages.
 */
add_action( 'wp_enqueue_scripts', 'plgc_events_dequeue_facebook_sdk', 20 );

function plgc_events_dequeue_facebook_sdk(): void {
    wp_dequeue_script( 'facebook-sdk' );
    wp_deregister_script( 'facebook-sdk' );
    // TEC may also register it under these handles:
    wp_dequeue_script( 'tribe-events-facebook-sdk' );
    wp_deregister_script( 'tribe-events-facebook-sdk' );
}

// Also catch it at print time in case it's re-enqueued after priority 20
add_action( 'wp_print_scripts', 'plgc_events_dequeue_facebook_sdk', 99 );

/**
 * Belt-and-suspenders: if the SDK still makes it through via script_loader_tag,
 * block any script tag pointing to connect.facebook.net.
 */
add_filter( 'script_loader_tag', 'plgc_events_block_facebook_script_tag', 10, 2 );

function plgc_events_block_facebook_script_tag( string $tag, string $handle ): string {
    if ( stripos( $tag, 'connect.facebook.net' ) !== false ) {
        return '<!-- Facebook SDK blocked by PLGC theme (CSP) -->';
    }
    return $tag;
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


// ─────────────────────────────────────────────────────────────────────────────
// 11. MOVE "SUBSCRIBE TO CALENDAR" — to hero on single, to top bar on list
// ─────────────────────────────────────────────────────────────────────────────

/**
 * On single event pages: moves the subscribe dropdown into .plgc-event-hero__actions.
 * On list/calendar views: moves it from the footer area to just after the top bar
 * so it doesn't get lost at the bottom of the page.
 *
 * Falls back gracefully if JS is disabled (element stays in original position).
 */
add_action( 'wp_footer', 'plgc_events_move_add_to_calendar', 5 );

function plgc_events_move_add_to_calendar(): void {
    // Only run if TEC is active — the JS handles element detection
    if ( ! class_exists( 'Tribe__Events__Main' ) ) {
        return;
    }
    ?>
    <script>
    (function() {
        // Single event page — move into hero actions
        var heroTarget = document.querySelector( '.plgc-event-hero__actions' );
        if ( heroTarget ) {
            var subscribe = null;
            var allContainers = document.querySelectorAll( '.tribe-events-c-subscribe-dropdown__container' );
            allContainers.forEach( function( el ) {
                if ( el.closest( '#tribe-events-content' ) ) {
                    subscribe = el;
                }
            });
            if ( subscribe && ! heroTarget.contains( subscribe ) ) {
                heroTarget.appendChild( subscribe );
            }
            return;
        }

        // List/calendar view — move subscribe INTO the top bar row (right-aligned via CSS margin-left:auto)
        var topBar = document.querySelector( '.tribe-events-c-top-bar' );
        if ( ! topBar ) return;

        var subscribeDropdown = document.querySelector( '.tribe-events-c-subscribe-dropdown' );
        if ( ! subscribeDropdown ) return;

        // Don't move if it's already inside the top bar
        if ( topBar.contains( subscribeDropdown ) ) return;

        // Append inside the top bar — CSS margin-left:auto pushes it right
        topBar.appendChild( subscribeDropdown );
    })();
    </script>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// 12. LIST VIEW — "Purchase Tickets →" / "Learn More →" card action link
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Injects a contextual action link at the bottom of each event card in the
 * V2 list view. Shows "Purchase Tickets →" if ETP tickets are available for
 * the event, otherwise shows "Learn More →".
 *
 * Uses JavaScript injection because TEC's V2 template hook system varies
 * across versions and the JS approach is the most reliable across all
 * ECP releases.
 *
 * WCAG: links have descriptive accessible names including the event title.
 */
add_action( 'wp_footer', 'plgc_events_card_action_links', 10 );

function plgc_events_card_action_links(): void {
    // Only run on pages that have TEC list/photo views (not single events)
    if ( is_singular( 'tribe_events' ) ) {
        return;
    }

    // Check if TEC is active
    if ( ! class_exists( 'Tribe__Events__Main' ) ) {
        return;
    }

    // Build a map of event IDs that have ETP tickets
    $ticketed_events = [];
    if ( function_exists( 'tribe_tickets_get_ticket_ids' ) ) {
        // Get all upcoming event IDs on the current page
        $events = tribe_get_events( [
            'posts_per_page' => 50,
            'start_date'     => 'now',
        ] );
        foreach ( $events as $event ) {
            $ticket_ids = tribe_tickets_get_ticket_ids( $event->ID );
            if ( ! empty( $ticket_ids ) ) {
                $ticketed_events[] = $event->ID;
            }
        }
    }
    $ticketed_json = wp_json_encode( $ticketed_events );
    ?>
    <script>
    (function() {
        var ticketedIds = <?php echo $ticketed_json; ?>;

        function addCardLinks() {
            var cards = document.querySelectorAll('.tribe-events-calendar-list__event');
            cards.forEach(function(card) {
                // Don't add twice
                if (card.querySelector('.plgc-card-action')) return;

                var details = card.querySelector('.tribe-events-calendar-list__event-details');
                if (!details) return;

                var titleLink = card.querySelector('.tribe-events-calendar-list__event-title-link');
                if (!titleLink) return;

                var href = titleLink.getAttribute('href');
                var eventTitle = titleLink.textContent.trim();

                // Check if this event has tickets by looking for the event ID in data attributes
                // TEC stores the event ID on the article or wrapper
                var wrapper = card.closest('[data-tribe-event-id]');
                var eventId = wrapper ? parseInt(wrapper.getAttribute('data-tribe-event-id'), 10) : 0;
                var hasTickets = ticketedIds.indexOf(eventId) !== -1;

                var link = document.createElement('a');
                link.href = href;
                link.className = 'plgc-card-action' + (hasTickets ? ' plgc-card-action--tickets' : '');
                link.setAttribute('aria-label', (hasTickets ? 'Purchase tickets for ' : 'Learn more about ') + eventTitle);
                link.innerHTML = (hasTickets ? 'Purchase Tickets' : 'Learn More') + ' <span aria-hidden="true">&rarr;</span>';

                details.appendChild(link);
            });
        }

        // Run on load and after TEC AJAX navigation
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addCardLinks);
        } else {
            addCardLinks();
        }

        // Re-run after TEC's AJAX view updates
        document.addEventListener('afterSetup.tribeEvents', addCardLinks);
        // MutationObserver fallback for non-standard TEC updates
        var listContainer = document.querySelector('.tribe-events-calendar-list');
        if (listContainer) {
            var observer = new MutationObserver(function(mutations) {
                // Debounce — only run once per batch of mutations
                clearTimeout(observer._timeout);
                observer._timeout = setTimeout(addCardLinks, 100);
            });
            observer.observe(listContainer.parentNode || listContainer, {
                childList: true, subtree: true
            });
        }
    })();
    </script>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// 13. CATEGORY FILTER — REMOVED
// ─────────────────────────────────────────────────────────────────────────────
// TEC V2 views use AJAX for page transitions and don't read URL params on
// subsequent navigations. Category filtering works via shortcodes:
//   [tribe_events view="list" category="mcchesneys-pub-grill"]
//   [tribe_events view="list" category="golf-calendar"]
// Or use the TEC Filter Bar addon for a built-in dropdown.
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// 14. ORGANIZER META CLEANUP — hide website link
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Hide the "View Organizer Website" link from organizer meta on single events.
 * TEC renders this from the organizer record's "Website" field.
 *
 * Phone and email linking is handled directly in the custom
 * tribe-events/single-event.php template (v3) using raw PHP output with
 * proper <a href="tel:"> and <a href="mailto:"> tags. The previous approach
 * of using filters on tribe_get_organizer_phone/email didn't work because
 * TEC's organizer template runs esc_html() on the output, which escaped the
 * HTML tags into visible text.
 *
 * @since 1.7.4
 */
add_filter( 'tribe_get_organizer_website_link', '__return_empty_string' );

