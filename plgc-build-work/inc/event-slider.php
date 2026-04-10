<?php
/**
 * PLGC Featured Events Slider
 *
 * Shortcode: [plgc_event_slider]
 *
 * ── Data flow ─────────────────────────────────────────────────────────────────
 *  1. An ACF "Feature in Events Carousel" toggle appears in the sidebar of every
 *     Event post. Turn it on to include the event; off to remove it.
 *  2. The query uses The Events Calendar ORM (tribe_events()) when available —
 *     ECP's fluent API handles timezone-aware date comparisons, recurring event
 *     deduplication, and proper upcoming-first ordering far better than raw
 *     get_posts() + meta_query ever could.
 *  3. Recurring events (ECP): only the next upcoming occurrence of each series
 *     is shown. Without this, a weekly event would flood the slider.
 *  4. Auto-expiry: events disappear automatically the moment their end date/time
 *     passes — no cron, no manual cleanup.
 *  5. Price is pulled via tribe_get_cost() and shown on the slide when available.
 *
 * ── CTA states ────────────────────────────────────────────────────────────────
 *  "Get Tickets for [Title]"     Tickets exist, in stock, sale window open
 *  Sold Out badge + "View Details for [Title]"   All tickets sold/sale closed
 *  "Learn More About [Title]"    No tickets on event (free / info-only)
 *  All aria-labels include the full event title — WCAG 2.4.4 compliant.
 *
 * ── Plugin dependencies ───────────────────────────────────────────────────────
 *  Required:  The Events Calendar (free) ≥ 4.9 — tribe_events() ORM, post type
 *  Preferred: Events Calendar Pro — recurring event handling, enhanced ORM
 *  Optional:  Event Tickets + WooCommerce — sold-out / get-tickets states,
 *             tribe_get_cost() price display
 *  All optional deps gated with class_exists() / function_exists().
 *
 * ── WCAG 2.1 AA ───────────────────────────────────────────────────────────────
 *  No autoplay · keyboard nav (← →) · role/aria on slides and dots
 *  All CTAs include event title in aria-label (WCAG 2.4.4)
 *  "Sold Out" badge: role="img" + aria-label (not colour alone, WCAG 1.4.1)
 *  Visible 3px focus rings · 44 px min touch targets · live region
 *
 * @package PLGC
 * @since   1.5.5
 */

defined( 'ABSPATH' ) || exit;


// ─────────────────────────────────────────────────────────────────────────────
// 1. ACF FIELD — "Feature in Events Carousel" on Event posts
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'acf/init', 'plgc_es_register_acf_fields' );

function plgc_es_register_acf_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' )
	     || ! class_exists( 'Tribe__Events__Main' ) ) {
		return;
	}

	acf_add_local_field_group( [
		'key'    => 'group_plgc_event_slider',
		'title'  => 'Events Carousel',
		'fields' => [
			[
				'key'          => 'field_plgc_event_featured',
				'label'        => 'Feature in Events Carousel',
				'name'         => 'plgc_event_featured',
				'type'         => 'true_false',
				'instructions' => 'Toggle on to show this event in the Events Carousel on the homepage. The event disappears automatically once its end date/time passes — no cleanup needed.',
				'required'     => 0,
				'default_value'=> 0,
				'ui'           => 1,
				'ui_on_text'   => 'Featured',
				'ui_off_text'  => 'Not featured',
			],
			[
				'key'          => 'field_plgc_event_sort_order',
				'label'        => 'Carousel Sort Order',
				'name'         => 'plgc_event_sort_order',
				'type'         => 'number',
				'instructions' => 'Controls this event\'s position in the homepage carousel. Lower numbers appear first. Leave blank to sort by event start date (default behavior). Tip: use 10, 20, 30… to leave room for announcements.',
				'required'     => 0,
				'default_value'=> '',
				'min'          => 0,
				'placeholder'  => 'Auto (by date)',
				'wrapper'      => [ 'width' => '50' ],
				'conditional_logic' => [
					[
						[
							'field'    => 'field_plgc_event_featured',
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
			],
			[
				'key'          => 'field_plgc_event_banner',
				'label'        => 'Homepage Banner Image',
				'name'         => 'plgc_event_banner',
				'type'         => 'image',
				'instructions' => 'Optional image for the homepage slider (recommended: 1400×1120px, 5:4 ratio). If left empty, the Featured Image will be used instead. Only needed when this event is featured in the carousel.',
				'required'     => 0,
				'return_format'=> 'url',
				'preview_size' => 'medium',
				'library'      => 'all',
				'mime_types'   => 'jpg, jpeg, png, webp',
				'conditional_logic' => [
					[
						[
							'field'    => 'field_plgc_event_featured',
							'operator' => '==',
							'value'    => '1',
						],
					],
				],
			],
		],
		'location' => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'tribe_events',
				],
			],
		],
		'menu_order'            => 5,
		'position'              => 'side',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	] );
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. ASSET REGISTRATION
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'plgc_es_register_assets' );

function plgc_es_register_assets(): void {
	if ( ! wp_script_is( 'swiper', 'registered' ) ) {
		wp_register_style( 'swiper',
			'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.2.5' );
		wp_register_script( 'swiper',
			'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.2.5', true );
	}

	wp_register_style( 'plgc-event-slider',
		PLGC_URI . '/assets/css/event-slider.css', [ 'plgc-theme', 'swiper' ], PLGC_VERSION );

	wp_register_script( 'plgc-event-slider',
		PLGC_URI . '/assets/js/event-slider.js', [ 'swiper' ], PLGC_VERSION, true );
}


// ─────────────────────────────────────────────────────────────────────────────
// 3. QUERY — FETCH FEATURED UPCOMING EVENTS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns featured events that haven't ended yet, ordered by start date.
 *
 * Strategy:
 *  A) tribe_events() ORM (TEC free ≥ 4.9, enhanced by ECP) — preferred path.
 *     The ORM handles timezone-aware date comparisons, recurring event
 *     instance resolution, and proper SQL ordering natively.
 *  B) get_posts() fallback — used only if TEC isn't active yet, so the file
 *     never fatal-errors during the install window.
 *
 * Recurring events (ECP):
 *  tribe_events()->where('ends_after', 'now') on an ORM query with
 *  'upcoming' implicitly de-duplicates recurring series to their next
 *  occurrence when ECP is active. If for any reason duplicates slip through
 *  (e.g. admin previewing future instances) we deduplicate by parent series
 *  post ID in PHP before returning.
 *
 * @param  int  $limit  0 = no limit
 * @return WP_Post[]
 */
function plgc_es_get_events( int $limit = 0 ): array {
	if ( ! class_exists( 'Tribe__Events__Main' ) ) {
		return [];
	}

	// ── Path A: TEC ORM (preferred) ──────────────────────────────────────────
	if ( function_exists( 'tribe_events' ) ) {
		try {
			$query = tribe_events()
				->where( 'meta_equals', 'plgc_event_featured', '1' )
				->where( 'ends_after',  'now' )           // auto-expiry via end date
				->order_by( 'start_date', 'ASC' )
				->posts();

			$results = $query instanceof WP_Query ? $query->posts : ( is_array( $query ) ? $query : [] );

			if ( $limit > 0 ) {
				$results = array_slice( $results, 0, $limit );
			}

			// Deduplicate recurring series: keep only the first occurrence
			// of each unique parent series (ECP sets _EventRecurrence on recurrences)
			$results = plgc_es_dedupe_recurring( $results );

			return $results;

		} catch ( \Throwable $e ) {
			// ORM threw — fall through to get_posts()
		}
	}

	// ── Path B: get_posts() fallback ─────────────────────────────────────────
	$now = current_time( 'Y-m-d H:i:s' );

	$posts = get_posts( [
		'post_type'      => Tribe__Events__Main::POSTTYPE,
		'post_status'    => 'publish',
		'posts_per_page' => $limit > 0 ? $limit : -1,
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => 'plgc_event_featured', 'value' => '1' ],
			[
				'key'     => '_EventEndDate',
				'value'   => $now,
				'compare' => '>=',
				'type'    => 'DATETIME',
			],
		],
		'orderby'  => 'meta_value',
		'meta_key' => '_EventStartDate',
		'order'    => 'ASC',
	] );

	$posts = plgc_es_dedupe_recurring( $posts ?: [] );

	return $limit > 0 ? array_slice( $posts, 0, $limit ) : $posts;
}

/**
 * Remove duplicate recurring-event occurrences, keeping only the soonest one
 * per unique series. ECP recurring instances share a "_EventRecurrence" meta
 * key that points to a parent config. We track by the post's recurring parent
 * to ensure each series appears at most once.
 *
 * @param  WP_Post[]  $posts
 * @return WP_Post[]
 */
function plgc_es_dedupe_recurring( array $posts ): array {
	if ( ! class_exists( 'Tribe__Events__Pro__Main' ) ) {
		return $posts; // Not ECP — nothing to deduplicate
	}

	$seen    = [];
	$results = [];

	foreach ( $posts as $post ) {
		// ECP stores '_EventRecurrence' on the parent event post,
		// and '_EventRecurrenceID' or a parent link on child occurrences.
		// The simplest stable key is the post_parent (0 for non-recurring / parents).
		$parent_id = (int) $post->post_parent;
		$series_key = $parent_id > 0 ? $parent_id : $post->ID;

		if ( isset( $seen[ $series_key ] ) ) {
			continue; // Already have an occurrence from this series
		}

		$seen[ $series_key ] = true;
		$results[] = $post;
	}

	return $results;
}


// ─────────────────────────────────────────────────────────────────────────────
// 4. HELPER — TICKET STATE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns 'none' | 'available' | 'sold_out'.
 *
 * Works with Event Tickets (free) and Event Tickets + WooCommerce (premium).
 * Uses tribe_tickets_get_ticket_provider() when available (modern Tickets API).
 */
function plgc_es_ticket_state( int $event_id ): string {
	if ( ! function_exists( 'tribe_events_has_tickets' )
	     || ! tribe_events_has_tickets( $event_id ) ) {
		return 'none';
	}

	$tickets = [];

	if ( class_exists( 'Tribe__Tickets__Tickets' ) ) {
		$tickets = Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );
	}

	if ( empty( $tickets ) ) {
		return 'none';
	}

	$now = time();

	foreach ( $tickets as $ticket ) {
		// ── WooCommerce-backed ticket ────────────────────────────────────────
		if ( class_exists( 'WooCommerce' ) && ! empty( $ticket->ID ) ) {
			$product = wc_get_product( $ticket->ID );
			if ( ! $product || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
				continue;
			}
			$end   = get_post_meta( $ticket->ID, '_ticket_end_date',   true );
			$start = get_post_meta( $ticket->ID, '_ticket_start_date', true );
			if ( $end   && strtotime( $end )   < $now ) continue;
			if ( $start && strtotime( $start ) > $now ) continue;
			return 'available';
		}

		// ── RSVP / non-WC ticket ────────────────────────────────────────────
		if ( method_exists( $ticket, 'available' ) && $ticket->available() > 0 ) {
			return 'available';
		}
	}

	return 'sold_out';
}


// ─────────────────────────────────────────────────────────────────────────────
// 5. HELPERS — DATE, TIME, PRICE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Human-readable start date string. Uses TEC helpers when available.
 * Returns empty string for all-day events (so we don't output "12:00 AM").
 *
 * @return string  e.g. "Saturday, April 12, 2026 · 10:00 AM"
 *                 or   "Saturday, April 12, 2026"  (all-day)
 */
function plgc_es_format_date( int $event_id ): string {
	// tribe_is_all_day() requires TEC free
	$all_day = function_exists( 'tribe_get_all_day' ) && tribe_get_all_day( $event_id );

	if ( function_exists( 'tribe_get_start_date' ) ) {
		$date = tribe_get_start_date( $event_id, false, 'l, F j, Y' );
		if ( $all_day ) {
			return $date;
		}
		$time = tribe_get_start_date( $event_id, false, 'g:i A' );
		return $date . ' &middot; ' . $time;
	}

	$raw = get_post_meta( $event_id, '_EventStartDate', true );
	if ( ! $raw ) return '';

	$ts   = strtotime( $raw );
	$date = date_i18n( 'l, F j, Y', $ts );
	if ( $all_day || date( 'Hi', $ts ) === '0000' ) return $date;

	return $date . ' &middot; ' . date_i18n( 'g:i A', $ts );
}

/**
 * Returns a formatted price string if ECP/Tickets reports one.
 * Examples: "Free", "From $25", "$45 – $120"
 *
 * tribe_get_cost() is available in TEC free when Event Tickets is active.
 * Returns empty string if no price info.
 */
function plgc_es_format_price( int $event_id ): string {
	if ( ! function_exists( 'tribe_get_cost' ) ) {
		return '';
	}

	$cost = tribe_get_cost( $event_id, true ); // true = with currency symbol
	return $cost ? wp_strip_all_tags( $cost ) : '';
}


// ─────────────────────────────────────────────────────────────────────────────
// 6. ANNOUNCEMENTS — QUERY & MERGE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns active announcements from the ACF repeater on the options page.
 *
 * Each returned item is a stdClass with properties matching the ACF sub-field
 * names: sort_order, media_type, image, video_url, headline, body_text,
 * cta_label, cta_url, start_date, end_date.
 *
 * "Active" means: today >= start_date AND today <= end_date.
 *
 * @return stdClass[]
 */
function plgc_es_get_announcements(): array {
	if ( ! function_exists( 'get_field' ) ) {
		return [];
	}

	$rows = get_field( 'plgc_announcements', 'option' );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return [];
	}

	$today  = (int) current_time( 'Ymd' );
	$active = [];

	foreach ( $rows as $row ) {
		$start = isset( $row['start_date'] ) ? (int) $row['start_date'] : 0;
		$end   = isset( $row['end_date'] )   ? (int) $row['end_date']   : 0;

		// Skip if scheduling data is incomplete
		if ( ! $start || ! $end ) {
			continue;
		}

		// Check date window
		if ( $today < $start || $today > $end ) {
			continue;
		}

		// Must have at least an image or headline
		$has_image = is_array( $row['image'] ?? null ) && ! empty( $row['image']['url'] );
		$has_headline = ! empty( $row['headline'] );
		if ( ! $has_image && ! $has_headline ) {
			continue;
		}

		$ann = (object) $row;
		$ann->_slide_type = 'announcement';
		$active[] = $ann;
	}

	return $active;
}


/**
 * Merges featured events and active announcements into a single sorted array.
 *
 * Sort logic:
 *   - Items with an explicit sort_order number sort by that number (ascending).
 *   - Events without a sort_order derive one from their start date as a Unix
 *     timestamp shifted to a high range (999000000+), so they naturally fall
 *     after explicitly-ordered items but remain in chronological order among
 *     themselves.
 *   - Stable sort: items with the same sort_key preserve their original order.
 *
 * Each item in the returned array is a stdClass with at minimum:
 *   ->_slide_type   'event' | 'announcement'
 *   ->_sort_key     numeric value used for ordering
 *
 * Event items additionally carry: ->_event_id, ->_event_post (WP_Post)
 * Announcement items carry the ACF sub-field values directly.
 *
 * @param  WP_Post[]   $events
 * @param  stdClass[]  $announcements
 * @return stdClass[]
 */
function plgc_es_merge_slides( array $events, array $announcements ): array {
	$merged = [];

	// ── Wrap events ──────────────────────────────────────────────────────────
	foreach ( $events as $event ) {
		$item = new stdClass();
		$item->_slide_type = 'event';
		$item->_event_id   = $event->ID;
		$item->_event_post = $event;

		// Custom sort order from ACF
		$custom_order = function_exists( 'get_field' )
			? get_field( 'plgc_event_sort_order', $event->ID )
			: '';

		if ( $custom_order !== '' && $custom_order !== null && $custom_order !== false ) {
			$item->_sort_key = (float) $custom_order;
		} else {
			// Derive from start date — high range so un-ordered events sort last
			$start_ts = (int) strtotime(
				get_post_meta( $event->ID, '_EventStartDate', true ) ?: 'now'
			);
			// Normalize to a range that sorts after explicit orders (0–999)
			// but still preserves chronological order among events.
			$item->_sort_key = 999000000 + $start_ts;
		}

		$merged[] = $item;
	}

	// ── Wrap announcements ───────────────────────────────────────────────────
	foreach ( $announcements as $ann ) {
		$ann->_sort_key = (float) ( $ann->sort_order ?? 10 );
		$merged[] = $ann;
	}

	// ── Stable sort by _sort_key ─────────────────────────────────────────────
	// PHP's usort is not stable, so we use a secondary index to preserve
	// insertion order for items with equal sort keys.
	$i = 0;
	foreach ( $merged as $item ) {
		$item->_stable_idx = $i++;
	}

	usort( $merged, function ( $a, $b ) {
		$diff = $a->_sort_key - $b->_sort_key;
		if ( abs( $diff ) < 0.0001 ) {
			return $a->_stable_idx - $b->_stable_idx;
		}
		return $diff < 0 ? -1 : 1;
	} );

	return $merged;
}


/**
 * Detect video type from a URL.
 *
 * @param  string  $url
 * @return string  'youtube' | 'vimeo' | 'mp4' | 'unknown'
 */
function plgc_es_video_type( string $url ): string {
	if ( preg_match( '/youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\//i', $url ) ) {
		return 'youtube';
	}
	if ( preg_match( '/vimeo\.com\//i', $url ) ) {
		return 'vimeo';
	}
	if ( preg_match( '/\.mp4(\?|$)/i', $url ) ) {
		return 'mp4';
	}
	return 'unknown';
}


/**
 * Extract a YouTube embed URL from various YouTube URL formats.
 *
 * @param  string  $url
 * @return string  Embed URL or empty string
 */
function plgc_es_youtube_embed_url( string $url ): string {
	$id = '';
	if ( preg_match( '/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
		$id = $m[1];
	} elseif ( preg_match( '/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
		$id = $m[1];
	} elseif ( preg_match( '/embed\/([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
		$id = $m[1];
	}
	return $id ? 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0' : '';
}


/**
 * Extract a Vimeo embed URL.
 *
 * @param  string  $url
 * @return string  Embed URL or empty string
 */
function plgc_es_vimeo_embed_url( string $url ): string {
	if ( preg_match( '/vimeo\.com\/(\d+)/', $url, $m ) ) {
		return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1';
	}
	return '';
}




/**
 * Renders a static "fallback" version of the event slider panel when no
 * featured events exist. Pulls image, heading, message, and optional CTA
 * from the ACF options page (PL Settings → Homepage tab).
 *
 * If no fallback image has been set, returns the simple text-only treatment
 * so the panel is never completely empty.
 *
 * Enqueues event-slider CSS so the fallback shares the same visual style as
 * a live slide (panel gradient, typography, CTA button, etc.) — no extra
 * stylesheet needed.
 *
 * @return string  HTML markup.
 */
function plgc_es_fallback_slide(): string {
	$fallback_img  = function_exists( 'get_field' ) ? get_field( 'plgc_event_fallback_image', 'option' ) : null;
	$fallback_title = plgc_option( 'plgc_event_fallback_title', '' );
	$fallback_msg   = plgc_option( 'plgc_event_fallback_msg',   '' );
	$cta_text       = plgc_option( 'plgc_event_fallback_cta_text', '' );
	$cta_url        = plgc_option( 'plgc_event_fallback_cta_url',  '' );

	$img_url = '';
	$img_alt = '';
	if ( is_array( $fallback_img ) && ! empty( $fallback_img['url'] ) ) {
		$img_url = $fallback_img['url'];
		$img_alt = ! empty( $fallback_img['alt'] ) ? $fallback_img['alt'] : esc_html__( 'Events at Prairie Landing Golf Club', 'plgc' );
	}

	// Enqueue the slider stylesheet so the fallback inherits the same styles
	wp_enqueue_style( 'plgc-event-slider' );

	// ── No image set — plain text fallback ──────────────────────────────────
	if ( ! $img_url ) {
		$msg = $fallback_msg ?: __( 'Stay tuned — upcoming events will be announced here.', 'plgc' );
		return '<div class="plgc-es plgc-es--empty" role="region" aria-label="'
		     . esc_attr__( 'Featured Events', 'plgc' ) . '">'
		     . '<div class="plgc-es__empty-inner">'
		     . ( $fallback_title ? '<p class="plgc-es__title">' . esc_html( $fallback_title ) . '</p>' : '' )
		     . '<p class="plgc-es__empty-msg">' . esc_html( $msg ) . '</p>'
		     . '</div></div>';
	}

	// ── Fallback image slide ─────────────────────────────────────────────────
	ob_start();
?>
<div
	class="plgc-es plgc-es--fallback plgc-es--single"
	role="region"
	aria-label="<?php esc_attr_e( 'Events Carousel', 'plgc' ); ?>"
>
	<div class="swiper plgc-es__swiper">
		<div class="swiper-wrapper">
			<div class="swiper-slide plgc-es__slide">
				<img
					class="plgc-es__bg-img"
					src="<?php echo esc_url( $img_url ); ?>"
					alt="<?php echo esc_attr( $img_alt ); ?>"
					loading="eager"
					decoding="async"
				>

				<?php $has_content = $fallback_title || $fallback_msg || ( $cta_text && $cta_url ); ?>
				<?php if ( $has_content ) : ?>
				<div class="plgc-es__panel" aria-hidden="true"></div>

				<div class="plgc-es__content">
					<div class="plgc-es__text">
						<?php if ( $fallback_title ) : ?>
						<p class="plgc-es__title"><?php echo esc_html( $fallback_title ); ?></p>
						<?php endif; ?>

						<?php if ( $fallback_msg ) : ?>
						<p class="plgc-es__date"><?php echo esc_html( $fallback_msg ); ?></p>
						<?php endif; ?>
					</div><!-- /.plgc-es__text -->

					<?php if ( $cta_text && $cta_url ) : ?>
					<div class="plgc-es__divider" aria-hidden="true"></div>
					<a
						href="<?php echo esc_url( $cta_url ); ?>"
						class="plgc-es__cta plgc-es__cta--learn"
						aria-label="<?php echo esc_attr( $cta_text ); ?>"
					><?php echo esc_html( $cta_text ); ?></a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php
	return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────────────────────
// 8. SHORTCODE
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'plgc_event_slider', 'plgc_es_shortcode' );

/**
 * Renders the Events Carousel.
 *
 * Three-tier priority:
 *   1. Featured events (TEC posts with ACF toggle on)
 *   2. Active announcements (ACF repeater, date-gated)
 *   3. Fallback slide (single static image/text from ACF options)
 *
 * Events and announcements are merged and sorted by their sort_order field.
 * Video announcements render a poster image with a play button overlay;
 * clicking loads the video (MP4 inline, YouTube/Vimeo via iframe facade).
 *
 * WCAG 2.1 AA:
 *   SC 1.1.1 — All images have alt text; decorative elements aria-hidden
 *   SC 1.4.1 — Status (sold out) conveyed by text + colour, not colour alone
 *   SC 2.1.1 — All controls are keyboard operable (native buttons)
 *   SC 2.2.2 — No autoplay on carousel or video; user initiates playback
 *   SC 2.4.4 — All CTA aria-labels include descriptive context
 *   SC 2.4.7 — Visible focus rings on all interactive elements
 *   SC 2.5.5 — 44×44px minimum touch targets
 *   SC 4.1.2 — Proper role/aria on carousel, slides, and controls
 */
function plgc_es_shortcode( array $atts = [] ): string {
	// TEC not required for announcements — only check for events query
	$has_tec = class_exists( 'Tribe__Events__Main' );

	// If no TEC AND no ACF (can't even check announcements), bail
	if ( ! $has_tec && ! function_exists( 'get_field' ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div role="note" style="padding:16px 20px;background:#fff3cd;border-left:4px solid #ffc107;font-family:sans-serif;font-size:14px;">'
			     . '<strong>PLGC Events Carousel:</strong> Install The Events Calendar and/or configure announcements in PL Settings.</div>';
		}
		return '';
	}

	$atts = shortcode_atts( [ 'limit' => 0 ], $atts, 'plgc_event_slider' );

	// ── Tier 1: Featured events ──────────────────────────────────────────────
	$events = $has_tec ? plgc_es_get_events( (int) $atts['limit'] ) : [];

	// ── Tier 2: Active announcements ─────────────────────────────────────────
	$announcements = plgc_es_get_announcements();

	// ── Tier 3: Fallback if both empty ───────────────────────────────────────
	if ( empty( $events ) && empty( $announcements ) ) {
		return plgc_es_fallback_slide();
	}

	// ── Merge & sort ─────────────────────────────────────────────────────────
	$slides = plgc_es_merge_slides( $events, $announcements );
	$total  = count( $slides );

	if ( $total === 0 ) {
		return plgc_es_fallback_slide();
	}

	// ── Enqueue assets ───────────────────────────────────────────────────────
	wp_enqueue_style( 'swiper' );
	wp_enqueue_script( 'swiper' );
	wp_enqueue_style( 'plgc-event-slider' );
	wp_enqueue_script( 'plgc-event-slider' );

	$is_single = ( $total === 1 );
	$slider_id = 'plgc-es-' . wp_unique_id();

	ob_start();
?>
<div
	id="<?php echo esc_attr( $slider_id ); ?>"
	class="plgc-es<?php echo $is_single ? ' plgc-es--single' : ''; ?>"
	role="region"
	aria-label="<?php esc_attr_e( 'Events and Announcements', 'plgc' ); ?>"
	<?php if ( ! $is_single ) echo 'aria-roledescription="carousel"'; ?>
>
	<div class="plgc-es__sr-live" aria-live="polite" aria-atomic="true" role="status"></div>

	<div class="swiper plgc-es__swiper">
		<div class="swiper-wrapper">
<?php
	foreach ( $slides as $i => $slide ) :

		if ( $slide->_slide_type === 'event' ) :
			// ── EVENT SLIDE ──────────────────────────────────────────────────
			$event_id = $slide->_event_id;
			$title    = get_the_title( $event_id );
			$url      = get_permalink( $event_id );
			$img_url  = '';

			// Priority: ACF banner image → Featured Image → empty
			if ( function_exists( 'get_field' ) ) {
				$banner = get_field( 'plgc_event_banner', $event_id );
				if ( $banner ) {
					$img_url = is_array( $banner ) ? ( $banner['url'] ?? '' ) : (string) $banner;
				}
			}
			if ( ! $img_url ) {
				$img_url = get_the_post_thumbnail_url( $event_id, 'large' ) ?: '';
			}

			$date_str = plgc_es_format_date( $event_id );
			$price    = plgc_es_format_price( $event_id );
			$state    = plgc_es_ticket_state( $event_id );

			// CTA — aria-labels include event title (WCAG 2.4.4)
			switch ( $state ) {
				case 'available':
					$cta_text = __( 'Get Tickets', 'plgc' );
					$cta_aria = sprintf( __( 'Get tickets for %s', 'plgc' ), $title );
					$cta_url  = esc_url( $url . '#tribe-tickets' );
					$cta_mod  = 'tickets';
					break;
				case 'sold_out':
					$cta_text = __( 'View Details', 'plgc' );
					$cta_aria = sprintf( __( 'View details for %s', 'plgc' ), $title );
					$cta_url  = esc_url( $url );
					$cta_mod  = 'details';
					break;
				default:
					$cta_text = __( 'Learn More', 'plgc' );
					$cta_aria = sprintf( __( 'Learn more about %s', 'plgc' ), $title );
					$cta_url  = esc_url( $url );
					$cta_mod  = 'learn';
			}

			$slide_aria = $is_single
				? $title
				: sprintf( __( 'Slide %1$d of %2$d: %3$s', 'plgc' ), $i + 1, $total, $title );
?>
			<div
				class="swiper-slide plgc-es__slide plgc-es__slide--event"
				<?php if ( ! $is_single ) : ?>
				role="group" aria-roledescription="slide"
				aria-label="<?php echo esc_attr( $slide_aria ); ?>"
				<?php endif; ?>
			>
				<?php if ( $img_url ) : ?>
				<img
					class="plgc-es__bg-img"
					src="<?php echo esc_url( $img_url ); ?>"
					alt="<?php echo esc_attr( $title ); ?>"
					loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
					decoding="async"
				>
				<?php else : ?>
				<div class="plgc-es__bg-placeholder" aria-hidden="true"></div>
				<?php endif; ?>

				<?php if ( $state === 'sold_out' ) : ?>
				<div class="plgc-es__badge plgc-es__badge--sold-out"
					role="img" aria-label="<?php esc_attr_e( 'Sold Out', 'plgc' ); ?>"
				><?php esc_html_e( 'Sold Out', 'plgc' ); ?></div>
				<?php endif; ?>

				<div class="plgc-es__panel" aria-hidden="true"></div>

				<div class="plgc-es__content">
					<div class="plgc-es__text">
					<?php if ( $date_str ) : ?>
					<p class="plgc-es__date"><?php echo wp_kses_post( $date_str ); ?></p>
					<?php endif; ?>

					<p class="plgc-es__title"><?php echo esc_html( $title ); ?></p>

					<?php if ( $price ) : ?>
					<p class="plgc-es__price"><?php echo esc_html( $price ); ?></p>
					<?php endif; ?>
					</div>

					<div class="plgc-es__divider" aria-hidden="true"></div>

					<a
						href="<?php echo $cta_url; ?>"
						class="plgc-es__cta plgc-es__cta--<?php echo esc_attr( $cta_mod ); ?>"
						aria-label="<?php echo esc_attr( $cta_aria ); ?>"
					><?php echo esc_html( $cta_text ); ?></a>
				</div>
			</div>

<?php
		else :
			// ── ANNOUNCEMENT SLIDE ───────────────────────────────────────────
			$ann_headline  = $slide->headline ?? '';
			$ann_body      = $slide->body_text ?? '';
			$ann_cta_label = $slide->cta_label ?? '';
			$ann_cta_url   = $slide->cta_url ?? '';
			$ann_media     = $slide->media_type ?? 'image';
			$ann_image     = $slide->image ?? null;
			$ann_video_url = $slide->video_url ?? '';

			$ann_img_url = ( is_array( $ann_image ) && ! empty( $ann_image['url'] ) )
				? $ann_image['url'] : '';
			$ann_img_alt = ( is_array( $ann_image ) && ! empty( $ann_image['alt'] ) )
				? $ann_image['alt']
				: ( $ann_headline ?: __( 'Announcement', 'plgc' ) );

			$is_video     = ( $ann_media === 'video' && ! empty( $ann_video_url ) );
			$video_type   = $is_video ? plgc_es_video_type( $ann_video_url ) : '';

			$slide_label  = $ann_headline ?: __( 'Announcement', 'plgc' );
			$slide_aria   = $is_single
				? $slide_label
				: sprintf( __( 'Slide %1$d of %2$d: %3$s', 'plgc' ), $i + 1, $total, $slide_label );

			// Video embed URLs for facade pattern
			$embed_url = '';
			if ( $is_video ) {
				if ( $video_type === 'youtube' ) {
					$embed_url = plgc_es_youtube_embed_url( $ann_video_url );
				} elseif ( $video_type === 'vimeo' ) {
					$embed_url = plgc_es_vimeo_embed_url( $ann_video_url );
				}
			}
?>
			<div
				class="swiper-slide plgc-es__slide plgc-es__slide--announcement<?php echo $is_video ? ' plgc-es__slide--video' : ''; ?>"
				<?php if ( ! $is_single ) : ?>
				role="group" aria-roledescription="slide"
				aria-label="<?php echo esc_attr( $slide_aria ); ?>"
				<?php endif; ?>
				<?php if ( $is_video ) : ?>
				data-video-type="<?php echo esc_attr( $video_type ); ?>"
				data-video-src="<?php echo esc_attr( $video_type === 'mp4' ? $ann_video_url : $embed_url ); ?>"
				<?php endif; ?>
			>
				<?php /* Poster image — always rendered (doubles as video poster frame) */ ?>
				<?php if ( $ann_img_url ) : ?>
				<img
					class="plgc-es__bg-img"
					src="<?php echo esc_url( $ann_img_url ); ?>"
					alt="<?php echo esc_attr( $ann_img_alt ); ?>"
					loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
					decoding="async"
				>
				<?php else : ?>
				<div class="plgc-es__bg-placeholder" aria-hidden="true"></div>
				<?php endif; ?>

				<?php /* Video play button — WCAG 2.2.2: no autoplay, user initiates.
				   SC 2.1.1: native <button>, keyboard accessible.
				   SC 2.5.5: 56×56px meets 44px minimum.
				   SC 4.1.2: aria-label describes the action. */ ?>
				<?php if ( $is_video ) : ?>
				<button
					class="plgc-es__play-btn"
					type="button"
					aria-label="<?php echo esc_attr( sprintf( __( 'Play video: %s', 'plgc' ), $slide_label ) ); ?>"
				>
					<svg class="plgc-es__play-icon" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M8 5.14v13.72a1 1 0 001.5.86l11.04-6.86a1 1 0 000-1.72L9.5 4.28a1 1 0 00-1.5.86z"/></svg>
				</button>

				<?php /* Container where JS injects <video> or <iframe> on play */ ?>
				<div class="plgc-es__video-container" aria-hidden="true"></div>
				<?php endif; ?>

				<?php $has_overlay = $ann_headline || $ann_body || ( $ann_cta_label && $ann_cta_url ); ?>
				<?php if ( $has_overlay ) : ?>
				<div class="plgc-es__panel" aria-hidden="true"></div>

				<div class="plgc-es__content">
					<div class="plgc-es__text">
						<?php if ( $ann_headline ) : ?>
						<p class="plgc-es__title"><?php echo esc_html( $ann_headline ); ?></p>
						<?php endif; ?>

						<?php if ( $ann_body ) : ?>
						<p class="plgc-es__date"><?php echo esc_html( $ann_body ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $ann_cta_label && $ann_cta_url ) : ?>
					<div class="plgc-es__divider" aria-hidden="true"></div>
					<a
						href="<?php echo esc_url( $ann_cta_url ); ?>"
						class="plgc-es__cta plgc-es__cta--learn"
						aria-label="<?php echo esc_attr( $ann_cta_label ); ?>"
					><?php echo esc_html( $ann_cta_label ); ?></a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>

<?php
		endif;
	endforeach;
?>
		</div><?php /* .swiper-wrapper */ ?>

		<?php if ( ! $is_single ) : ?>
		<div
			class="swiper-pagination plgc-es__dots"
			role="tablist"
			aria-label="<?php esc_attr_e( 'Carousel slides', 'plgc' ); ?>"
		></div>
		<?php endif; ?>

	</div><?php /* .plgc-es__swiper */ ?>
</div><?php /* .plgc-es */ ?>
<?php
	wp_reset_postdata();
	return ob_get_clean();
}
