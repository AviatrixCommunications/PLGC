<?php
/**
 * PLGC Featured Events Slider
 *
 * Shortcode: [plgc_event_slider]
 *
 * ── Data flow ─────────────────────────────────────────────────────────────────
 *  1. An ACF "Feature on Homepage Slider" toggle appears in the sidebar of every
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
// 1. ACF FIELD — "Feature on Homepage Slider" on Event posts
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'acf/init', 'plgc_es_register_acf_fields' );

function plgc_es_register_acf_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' )
	     || ! class_exists( 'Tribe__Events__Main' ) ) {
		return;
	}

	acf_add_local_field_group( [
		'key'    => 'group_plgc_event_slider',
		'title'  => 'Homepage Slider',
		'fields' => [
			[
				'key'          => 'field_plgc_event_featured',
				'label'        => 'Feature on Homepage Slider',
				'name'         => 'plgc_event_featured',
				'type'         => 'true_false',
				'instructions' => 'Toggle on to show this event in the Championship Golf slider on the homepage. The event disappears automatically once its end date/time passes — no cleanup needed.',
				'required'     => 0,
				'default_value'=> 0,
				'ui'           => 1,
				'ui_on_text'   => 'Featured',
				'ui_off_text'  => 'Not featured',
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
// 6. SHORTCODE
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'plgc_event_slider', 'plgc_es_shortcode' );

function plgc_es_shortcode( array $atts = [] ): string {
	// Silent on frontend if TEC not yet installed; admin sees an install prompt
	if ( ! class_exists( 'Tribe__Events__Main' ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div role="note" style="padding:16px 20px;background:#fff3cd;border-left:4px solid #ffc107;font-family:sans-serif;font-size:14px;">'
			     . '<strong>PLGC Event Slider:</strong> Install and activate The Events Calendar to enable this slider.</div>';
		}
		return '';
	}

	$atts   = shortcode_atts( [ 'limit' => 0 ], $atts, 'plgc_event_slider' );
	$events = plgc_es_get_events( (int) $atts['limit'] );

	if ( empty( $events ) ) {
		return '<div class="plgc-es plgc-es--empty" role="region" aria-label="'
		     . esc_attr__( 'Featured Events', 'plgc' ) . '">'
		     . '<div class="plgc-es__empty-inner">'
		     . '<p class="plgc-es__empty-msg">'
		     . esc_html__( 'Stay tuned — upcoming events will be announced here.', 'plgc' )
		     . '</p></div></div>';
	}

	wp_enqueue_style( 'swiper' );
	wp_enqueue_script( 'swiper' );
	wp_enqueue_style( 'plgc-event-slider' );
	wp_enqueue_script( 'plgc-event-slider' );

	$total     = count( $events );
	$is_single = ( $total === 1 );
	$slider_id = 'plgc-es-' . wp_unique_id();

	ob_start();
?>
<div
	id="<?php echo esc_attr( $slider_id ); ?>"
	class="plgc-es<?php echo $is_single ? ' plgc-es--single' : ''; ?>"
	role="region"
	aria-label="<?php esc_attr_e( 'Featured Events', 'plgc' ); ?>"
	<?php if ( ! $is_single ) echo 'aria-roledescription="carousel"'; ?>
>
	<div class="plgc-es__sr-live" aria-live="polite" aria-atomic="true" role="status"></div>

	<div class="swiper plgc-es__swiper">
		<div class="swiper-wrapper">
<?php
	foreach ( $events as $i => $event ) :
		$event_id = $event->ID;
		$title    = get_the_title( $event_id );
		$url      = get_permalink( $event_id );
		$img_url  = get_the_post_thumbnail_url( $event_id, 'large' ) ?: '';
		$date_str = plgc_es_format_date( $event_id );
		$price    = plgc_es_format_price( $event_id );
		$state    = plgc_es_ticket_state( $event_id );

		// ── CTA — aria-labels include event title (WCAG 2.4.4) ──────────────
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
				class="swiper-slide plgc-es__slide"
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
					</div><!-- /.plgc-es__text -->

					<div class="plgc-es__divider" aria-hidden="true"></div>

					<a
						href="<?php echo $cta_url; ?>"
						class="plgc-es__cta plgc-es__cta--<?php echo esc_attr( $cta_mod ); ?>"
						aria-label="<?php echo esc_attr( $cta_aria ); ?>"
					><?php echo esc_html( $cta_text ); ?></a>
				</div>
			</div>
<?php endforeach; ?>
		</div><?php /* .swiper-wrapper */ ?>

		<?php if ( ! $is_single ) : ?>
		<div
			class="swiper-pagination plgc-es__dots"
			role="tablist"
			aria-label="<?php esc_attr_e( 'Event slides', 'plgc' ); ?>"
		></div>
		<?php endif; ?>

	</div><?php /* .plgc-es__swiper */ ?>
</div><?php /* .plgc-es */ ?>
<?php
	wp_reset_postdata();
	return ob_get_clean();
}
