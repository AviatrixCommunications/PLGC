<?php
/**
 * Single Event Template — PLGC Custom Override v4
 *
 * Layout:
 *   1. Back link
 *   2. Two-column hero: image left, details + description right
 *   3. Venue / Organizer / Map (3-col grid)
 *   4. Related events (full-width row at bottom with flyer images)
 *   5. Prev/Next event navigation
 *
 * @package PLGC
 * @since   1.7.8
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

					<!-- Add to calendar — left aligned under venue -->
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

			<!-- Venue / Organizer / Map — full width -->
			<?php do_action( 'tribe_events_single_event_before_the_content' ); ?>

			<?php if ( tribe_has_venue() ) : ?>
				<div class="plgc-meta-grid">

					<!-- Venue -->
					<div class="plgc-meta-grid__venue">
						<h3 class="plgc-meta-grid__title">Venue</h3>
						<?php
						$venue_address = tribe_get_address( $event_id );
						$venue_city    = tribe_get_city( $event_id );
						$venue_state   = tribe_get_stateprovince( $event_id );
						$venue_zip     = tribe_get_zip( $event_id );
						$venue_phone   = tribe_get_phone( $event_id );
						?>
						<address class="plgc-meta-grid__address">
							<?php if ( $venue_address ) : ?>
								<span><?php echo esc_html( $venue_address ); ?></span><br>
							<?php endif; ?>
							<?php if ( $venue_city || $venue_state || $venue_zip ) : ?>
								<span>
									<?php
									$parts = array_filter( [ $venue_city, $venue_state ] );
									echo esc_html( implode( ', ', $parts ) );
									if ( $venue_zip ) echo ' ' . esc_html( $venue_zip );
									?>
								</span>
							<?php endif; ?>
						</address>
						<?php if ( tribe_show_google_map_link( $event_id ) ) : ?>
							<a href="<?php echo esc_url( tribe_get_map_link( $event_id ) ); ?>" class="plgc-meta-grid__map-link" target="_blank" rel="noopener noreferrer">
								+ Google Map <span class="screen-reader-text">(opens in new tab)</span>
							</a>
						<?php endif; ?>
						<?php if ( $venue_phone ) : ?>
							<div class="plgc-meta-grid__field">
								<strong>Phone</strong><br>
								<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $venue_phone ) ); ?>"><?php echo esc_html( $venue_phone ); ?></a>
							</div>
						<?php endif; ?>
					</div>

					<!-- Organizer -->
					<?php if ( tribe_has_organizer() ) : ?>
						<div class="plgc-meta-grid__organizer">
							<h3 class="plgc-meta-grid__title">Organizer</h3>
							<?php
							$organizer_name  = tribe_get_organizer();
							$organizer_phone = tribe_get_organizer_phone();
							$organizer_email = tribe_get_organizer_email();
							?>
							<?php if ( $organizer_name ) : ?>
								<span class="plgc-meta-grid__org-name"><?php echo esc_html( $organizer_name ); ?></span>
							<?php endif; ?>
							<?php if ( $organizer_phone ) : ?>
								<div class="plgc-meta-grid__field">
									<strong>Phone</strong><br>
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $organizer_phone ) ); ?>"><?php echo esc_html( $organizer_phone ); ?></a>
								</div>
							<?php endif; ?>
							<?php if ( $organizer_email ) : ?>
								<div class="plgc-meta-grid__field">
									<strong>Email</strong><br>
									<a href="mailto:<?php echo esc_attr( $organizer_email ); ?>"><?php echo esc_html( $organizer_email ); ?></a>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- Map -->
					<?php if ( tribe_embed_google_map( $event_id ) ) : ?>
						<div class="plgc-meta-grid__map">
							<?php echo tribe_get_embedded_map( $event_id ); ?>
						</div>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php do_action( 'tribe_events_single_event_after_the_content' ); ?>

		</article>

		<!-- Related events — FULL WIDTH at bottom, below venue/organizer -->
		<div class="plgc-related-events-full">
			<?php tribe_get_template_part( 'modules/related-events' ); ?>
		</div>

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
