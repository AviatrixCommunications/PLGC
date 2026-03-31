<?php
/**
 * Single Event Template — PLGC Custom Override
 *
 * Two-column hero: image left, details right (Ace Hotel style).
 * Full-width description + venue/meta below.
 *
 * Override of the default TEC V1 single-event.php.
 * Preserves all TEC action hooks for plugin compatibility.
 *
 * WCAG 2.1 AA: logical heading hierarchy, landmark regions, 44px targets.
 *
 * @package PLGC
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

$events_label = function_exists( 'tribe_get_events_label_plural' ) ? tribe_get_events_label_plural() : 'Events';
$events_url   = function_exists( 'tribe_get_events_link' ) ? tribe_get_events_link() : home_url( '/calendar/' );
$event_id     = get_the_ID();
?>

<div id="tribe-events-content" class="tribe-events-single">

	<!-- Back link -->
	<p class="tribe-events-back">
		<a href="<?php echo esc_url( $events_url ); ?>">
			&laquo; All <?php echo esc_html( $events_label ); ?>
		</a>
	</p>

	<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'plgc-single-event' ); ?>>

			<?php do_action( 'tribe_events_single_event_before_the_meta' ); ?>

			<!-- ═══ Two-column hero ═══ -->
			<div class="plgc-event-hero">

				<!-- Left: featured image -->
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="plgc-event-hero__image">
						<?php echo tribe_event_featured_image( $event_id, 'full', false ); ?>
					</div>
				<?php endif; ?>

				<!-- Right: details stack -->
				<div class="plgc-event-hero__details">

					<h1 class="tribe-events-single-event-title">
						<?php the_title(); ?>
					</h1>

					<div class="tribe-events-schedule plgc-event-hero__schedule">
						<?php echo tribe_events_event_schedule_details( $event_id ); ?>
					</div>

					<?php if ( tribe_has_venue() ) : ?>
						<div class="plgc-event-hero__venue">
							<span class="plgc-event-hero__venue-icon" aria-hidden="true">📍</span>
							<?php echo tribe_get_venue(); ?>
						</div>
					<?php endif; ?>

					<!-- Add to calendar — rendered here, JS no longer needs to move it -->
					<div class="plgc-event-hero__actions">
						<?php
						// TEC Subscribe links (add to calendar dropdown)
						if ( class_exists( 'Tribe\Events\Views\V2\iCalendar\Links\Link_Abstract' ) || function_exists( 'tribe_get_single_option' ) ) {
							// The subscribe block is rendered via TEC's action hooks below
						}
						?>
					</div>

					<?php do_action( 'tribe_events_single_event_after_the_meta' ); ?>

					<!-- Ticket form (ETP) — if tickets exist they render here -->
					<?php
					if ( function_exists( 'tribe_events_ticket_form' ) ) {
						tribe_events_ticket_form();
					}
					?>

				</div>
			</div>

			<!-- ═══ Description — full width ═══ -->
			<?php if ( get_the_content() ) : ?>
				<div class="tribe-events-single-event-description tribe-events-content">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<!-- ═══ Event meta (venue/map, organizer) ═══ -->
			<?php do_action( 'tribe_events_single_event_before_the_content' ); ?>

			<div class="tribe-events-single-section tribe-events-event-meta secondary">
				<?php
				// Venue
				if ( tribe_has_venue() ) {
					tribe_get_template_part( 'modules/meta/venue' );
				}

				// Organizer
				if ( tribe_has_organizer() ) {
					tribe_get_template_part( 'modules/meta/organizer' );
				}
				?>
			</div>

			<?php do_action( 'tribe_events_single_event_after_the_content' ); ?>

		</article>

		<!-- Related events -->
		<?php tribe_get_template_part( 'modules/related-events' ); ?>

		<!-- Event navigation -->
		<div id="tribe-events-footer">
			<nav class="tribe-events-cal-links" aria-label="Event navigation">
				<ul class="tribe-events-sub-nav">
					<li class="tribe-events-nav-previous"><?php tribe_the_prev_event_link( '<span aria-hidden="true">&lsaquo;</span> %title%' ); ?></li>
					<li class="tribe-events-nav-next"><?php tribe_the_next_event_link( '%title% <span aria-hidden="true">&rsaquo;</span>' ); ?></li>
				</ul>
			</nav>
		</div>

	<?php endwhile; ?>

</div>
