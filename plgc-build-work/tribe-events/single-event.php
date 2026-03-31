<?php
/**
 * Single Event Template — PLGC Custom Override v2
 *
 * Two-column hero: image left, details + description right.
 * Full-width venue/map, related events, and navigation below.
 *
 * @package PLGC
 * @since   1.7.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

$events_label = function_exists( 'tribe_get_events_label_plural' ) ? tribe_get_events_label_plural() : 'Events';
$events_url   = function_exists( 'tribe_get_events_link' ) ? tribe_get_events_link() : home_url( '/calendar/' );
$event_id     = get_the_ID();
?>

<div id="tribe-events-content" class="tribe-events-single">

	<p class="tribe-events-back">
		<a href="<?php echo esc_url( $events_url ); ?>">
			&laquo; All <?php echo esc_html( $events_label ); ?>
		</a>
	</p>

	<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'plgc-single-event' ); ?>>

			<?php do_action( 'tribe_events_single_event_before_the_meta' ); ?>

			<!-- Two-column hero -->
			<div class="plgc-event-hero">

				<!-- Left: featured image -->
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="plgc-event-hero__image">
						<?php echo tribe_event_featured_image( $event_id, 'full', false ); ?>
					</div>
				<?php endif; ?>

				<!-- Right: details + description -->
				<div class="plgc-event-hero__details">

					<h1 class="tribe-events-single-event-title">
						<?php the_title(); ?>
					</h1>

					<div class="tribe-events-schedule plgc-event-hero__schedule">
						<?php echo tribe_events_event_schedule_details( $event_id ); ?>
					</div>

					<?php if ( tribe_has_venue() ) : ?>
						<div class="plgc-event-hero__venue">
							<svg class="plgc-event-hero__venue-svg" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<path d="M8 1C5.24 1 3 3.24 3 6c0 3.75 5 9 5 9s5-5.25 5-9c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="#567915"/>
							</svg>
							<?php echo tribe_get_venue(); ?>
						</div>
					<?php endif; ?>

					<!-- Add to calendar container -->
					<div class="plgc-event-hero__actions"></div>

					<?php do_action( 'tribe_events_single_event_after_the_meta' ); ?>

					<!-- Ticket form (ETP) -->
					<?php
					if ( function_exists( 'tribe_events_ticket_form' ) ) {
						tribe_events_ticket_form();
					}
					?>

					<!-- Description -->
					<?php if ( get_the_content() ) : ?>
						<div class="tribe-events-single-event-description tribe-events-content">
							<?php the_content(); ?>
						</div>
					<?php endif; ?>

				</div>
			</div>

			<!-- Venue / Map meta — full width -->
			<?php do_action( 'tribe_events_single_event_before_the_content' ); ?>

			<?php if ( tribe_has_venue() ) : ?>
				<div class="tribe-events-single-section tribe-events-event-meta secondary">
					<?php tribe_get_template_part( 'modules/meta/venue' ); ?>
					<?php if ( tribe_has_organizer() ) {
						tribe_get_template_part( 'modules/meta/organizer' );
					} ?>
				</div>
			<?php endif; ?>

			<?php do_action( 'tribe_events_single_event_after_the_content' ); ?>

		</article>

		<!-- Related events — full width, bottom -->
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
