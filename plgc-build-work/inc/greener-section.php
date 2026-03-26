<?php
/**
 * PLGC "The Grass Really is Greener Here" Section
 *
 * Shortcode: [plgc_greener_section]
 *
 * All content is managed via ACF fields on the PL Settings options page
 * (WP Admin → PL Settings → "Grass Is Greener" tab).
 *
 * Editable fields:
 *   - Section title & intro text
 *   - Background image
 *   - Per-tile: image, link label, link URL
 *   - Testimonials: repeater (quote text, attribution) — slider if > 1
 *
 * WCAG 2.1 AA compliance:
 *   - role="region" + aria-labelledby on the section
 *   - Entire tile is an <a> (clickable block), link text underlined
 *   - Tile images use ACF alt text; decorative overlays are aria-hidden
 *   - Testimonial slider: no autoplay, prev/next + dot buttons with aria-labels,
 *     live region announces slide changes
 *   - <blockquote> + <cite> for quotes
 *   - All interactive elements meet 44×44 px minimum touch target
 *   - Feather graphic aria-hidden (decorative)
 *   - prefers-reduced-motion: Ken Burns and transitions disabled
 *
 * @package PLGC
 * @since   1.6.0
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// 1. TILE CONFIGURATION (defaults — overridden by ACF)
// ─────────────────────────────────────────────────────────────────────────────

function plgc_greener_tiles(): array {
	return [
		[
			'acf_image'      => 'plgc_greener_tile_1_image',
			'acf_link_label' => 'plgc_greener_tile_1_label',
			'acf_link_url'   => 'plgc_greener_tile_1_url',
			'default_label'  => 'View Golf Rates',
			'default_url'    => '/golf/golf-rates/',
			'default_alt'    => 'Golfer on the Prairie Landing course',
			'admin_label'    => 'Tile 1 — Golf Rates',
		],
		[
			'acf_image'      => 'plgc_greener_tile_2_image',
			'acf_link_label' => 'plgc_greener_tile_2_label',
			'acf_link_url'   => 'plgc_greener_tile_2_url',
			'default_label'  => 'View Golf Outings',
			'default_url'    => '/golf/golf-outings/',
			'default_alt'    => 'Golfers enjoying an outing at Prairie Landing',
			'admin_label'    => 'Tile 2 — Golf Outings',
		],
		[
			'acf_image'      => 'plgc_greener_tile_3_image',
			'acf_link_label' => 'plgc_greener_tile_3_label',
			'acf_link_url'   => 'plgc_greener_tile_3_url',
			'default_label'  => 'View Golf Lessons',
			'default_url'    => '/golf/golf-lessons/',
			'default_alt'    => 'Prairie Landing branded golf ball near the cup',
			'admin_label'    => 'Tile 3 — Golf Lessons',
		],
		[
			'acf_image'      => 'plgc_greener_tile_4_image',
			'acf_link_label' => 'plgc_greener_tile_4_label',
			'acf_link_url'   => 'plgc_greener_tile_4_url',
			'default_label'  => 'View Events',
			'default_url'    => '/weddings-events/',
			'default_alt'    => 'Elegant event setup at Prairie Landing',
			'admin_label'    => 'Tile 4 — Events',
		],
	];
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. ACF FIELD REGISTRATION
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'acf/init', 'plgc_greener_register_acf_fields' );

function plgc_greener_register_acf_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$tiles = plgc_greener_tiles();

	$fields = [
		// ── Left-side tab ────────────────────────────────────────────────────
		[
			'key'       => 'field_plgc_greener_tab',
			'label'     => 'Grass Is Greener',
			'name'      => '',
			'type'      => 'tab',
			'placement' => 'left',
			'endpoint'  => 0,
		],

		// ── Section header ───────────────────────────────────────────────────
		[
			'key'          => 'field_plgc_greener_title',
			'label'        => 'Section Title',
			'name'         => 'plgc_greener_title',
			'type'         => 'text',
			'instructions' => 'Leave blank for default: "The Grass Really is Greener Here"',
			'placeholder'  => 'The Grass Really is Greener Here',
			'wrapper'      => [ 'width' => '50' ],
		],
		[
			'key'          => 'field_plgc_greener_subtitle',
			'label'        => 'Intro Text',
			'name'         => 'plgc_greener_subtitle',
			'type'         => 'text',
			'instructions' => 'Leave blank for default: "Explore all that Prairie Landing Golf Club has to offer."',
			'placeholder'  => 'Explore all that Prairie Landing Golf Club has to offer.',
			'wrapper'      => [ 'width' => '50' ],
		],
		[
			'key'           => 'field_plgc_greener_bg_image',
			'label'         => 'Background Image',
			'name'          => 'plgc_greener_bg_image',
			'type'          => 'image',
			'instructions'  => 'Optional. Overlaid on top of the green gradient. Leave blank to use gradient only. Recommended: wide landscape image, 1440 px or wider.',
			'required'      => 0,
			'return_format' => 'array',
			'preview_size'  => 'thumbnail',
			'library'       => 'all',
			'wrapper'       => [ 'width' => '50' ],
		],

		// ── Tile intro message ───────────────────────────────────────────────
		[
			'key'       => 'field_plgc_greener_tiles_heading',
			'label'     => '',
			'name'      => 'plgc_greener_tiles_heading',
			'type'      => 'message',
			'message'   => '<hr><h3 style="margin:1em 0 0.25em">Image Tiles</h3>'
			             . '<p>Each tile is fully clickable. The link label appears underlined at the bottom of the tile. '
			             . 'Upload images at <strong>338 × 453 px or larger</strong> and fill in <strong>Alt Text</strong> in the Media Library.</p>',
			'new_lines' => 'wpautop',
			'esc_html'  => 0,
		],
	];

	// ── Per-tile fields ──────────────────────────────────────────────────────
	foreach ( $tiles as $tile ) {
		$fields[] = [
			'key'     => 'field_' . $tile['acf_image'] . '_msg',
			'label'   => '',
			'name'    => '',
			'type'    => 'message',
			'message' => '<strong>' . $tile['admin_label'] . '</strong>',
			'new_lines' => 'wpautop',
			'esc_html'  => 0,
			'wrapper'   => [ 'width' => '100' ],
		];
		$fields[] = [
			'key'           => 'field_' . $tile['acf_image'],
			'label'         => 'Image',
			'name'          => $tile['acf_image'],
			'type'          => 'image',
			'instructions'  => 'Recommended: 338 × 453 px or larger. Set Alt Text in Media Library.',
			'required'      => 0,
			'return_format' => 'array',
			'preview_size'  => 'thumbnail',
			'library'       => 'all',
			'wrapper'       => [ 'width' => '34' ],
		];
		$fields[] = [
			'key'          => 'field_' . $tile['acf_link_label'],
			'label'        => 'Link Label',
			'name'         => $tile['acf_link_label'],
			'type'         => 'text',
			'instructions' => 'Default: "' . $tile['default_label'] . '"',
			'placeholder'  => $tile['default_label'],
			'wrapper'      => [ 'width' => '33' ],
		];
		$fields[] = [
			'key'          => 'field_' . $tile['acf_link_url'],
			'label'        => 'Link URL',
			'name'         => $tile['acf_link_url'],
			'type'         => 'url',
			'instructions' => 'Default: "' . $tile['default_url'] . '"',
			'placeholder'  => $tile['default_url'],
			'wrapper'      => [ 'width' => '33' ],
		];
	}

	// ── Testimonials repeater ─────────────────────────────────────────────────
	$fields[] = [
		'key'     => 'field_plgc_greener_testimonials_heading',
		'label'   => '',
		'name'    => 'plgc_greener_testimonials_heading',
		'type'    => 'message',
		'message' => '<hr><h3 style="margin:1em 0 0.25em">Testimonials</h3>'
		           . '<p>Add one or more testimonials. If more than one is active, they display as an '
		           . '<strong>accessible slider</strong> (no autoplay — visitors click the arrows). '
		           . 'If the repeater is left empty, a default testimonial is shown.</p>',
		'new_lines' => 'wpautop',
		'esc_html'  => 0,
	];
	$fields[] = [
		'key'          => 'field_plgc_greener_testimonials',
		'label'        => 'Testimonials',
		'name'         => 'plgc_greener_testimonials',
		'type'         => 'repeater',
		'instructions' => '',
		'min'          => 0,
		'max'          => 0,
		'layout'       => 'table',
		'button_label' => 'Add Testimonial',
		'sub_fields'   => [
			[
				'key'          => 'field_plgc_greener_testimonial_quote',
				'label'        => 'Quote',
				'name'         => 'quote',
				'type'         => 'textarea',
				'instructions' => 'No need to add quotation marks — added automatically.',
				'rows'         => 3,
				'wrapper'      => [ 'width' => '70' ],
			],
			[
				'key'         => 'field_plgc_greener_testimonial_attribution',
				'label'       => 'Attribution',
				'name'        => 'attribution',
				'type'        => 'text',
				'placeholder' => 'Name, Source',
				'wrapper'     => [ 'width' => '30' ],
			],
		],
	];

	acf_add_local_field_group( [
		'key'      => 'group_plgc_greener_section',
		'title'    => '"Grass Is Greener" Section',
		'fields'   => $fields,
		'location' => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'plgc-settings',
				],
			],
		],
		'menu_order'            => 30,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	] );
}


// ─────────────────────────────────────────────────────────────────────────────
// 3. ASSET ENQUEUEING
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'plgc_greener_register_assets' );

function plgc_greener_register_assets(): void {
	wp_register_style(
		'plgc-greener-section',
		PLGC_URI . '/assets/css/greener-section.css',
		[ 'plgc-theme' ],
		PLGC_VERSION
	);
	wp_register_script(
		'plgc-greener-section',
		PLGC_URI . '/assets/js/greener-section.js',
		[],
		PLGC_VERSION,
		[ 'strategy' => 'defer', 'in_footer' => true ]
	);
}


// ─────────────────────────────────────────────────────────────────────────────
// 4. SHORTCODE
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'plgc_greener_section', 'plgc_greener_shortcode' );

function plgc_greener_shortcode(): string {
	wp_enqueue_style( 'plgc-greener-section' );
	wp_enqueue_script( 'plgc-greener-section' );

	$tiles   = plgc_greener_tiles();
	$options = 'option';

	// ── Section header content ───────────────────────────────────────────────
	$title    = plgc_option( 'plgc_greener_title',    'The Grass Really is Greener Here' );
	$subtitle = plgc_option( 'plgc_greener_subtitle', 'Explore all that Prairie Landing Golf Club has to offer.' );
	$bg_image = function_exists( 'get_field' ) ? get_field( 'plgc_greener_bg_image', $options ) : null;
	$bg_url   = ! empty( $bg_image['url'] ) ? $bg_image['url'] : '';

	// ── Testimonials ─────────────────────────────────────────────────────────
	$testimonials = function_exists( 'get_field' )
		? ( get_field( 'plgc_greener_testimonials', $options ) ?: [] )
		: [];

	if ( empty( $testimonials ) ) {
		$testimonials = [
			[
				'quote'       => 'One of the best links style golf courses in the Fox Valley area! The course is always in great shape and the staff is always friendly!',
				'attribution' => 'Matthew P., Google Review',
			],
		];
	}

	$has_slider      = count( $testimonials ) > 1;
	$total_slides    = count( $testimonials );

	// ── Section inline bg style ──────────────────────────────────────────────
	$section_style = '';
	if ( $bg_url ) {
		$section_style      = ' style="background-image: url(\'' . esc_url( $bg_url ) . '\')"';
		$section_extra_class = ' plgc-greener--has-bg';
	} else {
		$section_extra_class = '';
	}

	ob_start();
	?>
<section
	class="plgc-greener<?php echo esc_attr( $section_extra_class ); ?>"
	aria-labelledby="plgc-greener-heading"<?php echo $section_style; ?>
>

	<?php /* ── Feather accent — decorative, aria-hidden ──────────────────── */ ?>
	<img
		src="https://plgc2.wpenginepowered.com/wp-content/uploads/2026/03/feather_white-1.png"
		alt=""
		aria-hidden="true"
		role="presentation"
		class="plgc-greener__feather"
		loading="lazy"
		decoding="async"
	>

	<?php /* ── Section header ──────────────────────────────────────────────── */ ?>
	<div class="plgc-greener__header">
		<h2 id="plgc-greener-heading" class="plgc-greener__title">
			<?php echo esc_html( $title ); ?>
		</h2>
		<p class="plgc-greener__subtitle">
			<?php echo esc_html( $subtitle ); ?>
		</p>
	</div>

	<?php /* ── Tile grid ───────────────────────────────────────────────────── */ ?>
	<ul class="plgc-greener__grid" role="list">
		<?php foreach ( $tiles as $i => $tile ) :
			$image = function_exists( 'get_field' )
				? get_field( $tile['acf_image'], $options )
				: null;

			$img_url = $image['url'] ?? '';
			$img_alt = ( ! empty( $image['alt'] ) )
				? $image['alt']
				: $tile['default_alt'];

			$link_label = plgc_option( $tile['acf_link_label'], $tile['default_label'] );
			$link_url   = plgc_option( $tile['acf_link_url'],   $tile['default_url'] );

			$has_image = ! empty( $img_url );
		?>
		<li class="plgc-greener__tile<?php echo $has_image ? '' : ' plgc-greener__tile--placeholder'; ?>">

			<a
				href="<?php echo esc_url( $link_url ); ?>"
				class="plgc-greener__tile-link"
			>
				<?php if ( $has_image ) : ?>
				<img
					src="<?php echo esc_url( $img_url ); ?>"
					alt="<?php echo esc_attr( $img_alt ); ?>"
					loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
					decoding="async"
					class="plgc-greener__tile-img"
				>
				<?php endif; ?>

				<?php /* Gradient overlay — decorative */ ?>
				<span class="plgc-greener__tile-overlay" aria-hidden="true"></span>

				<span class="plgc-greener__tile-label">
					<?php echo esc_html( $link_label ); ?>
				</span>

			</a>

		</li>
		<?php endforeach; ?>
	</ul>

	<?php /* ── Testimonial(s) ────────────────────────────────────────────── */ ?>
	<div class="plgc-greener__testimonial-wrap">

		<?php if ( $has_slider ) : ?>

		<div
			class="plgc-greener__testimonial-slider"
			role="region"
			aria-label="Customer testimonials"
			data-greener-slider
		>
			<?php /* Live region — screen readers announce slide changes */ ?>
			<div class="plgc-greener__sr-live" aria-live="polite" aria-atomic="true" aria-relevant="text"></div>

			<div class="plgc-greener__slider-row">

				<button
					class="plgc-greener__slider-btn plgc-greener__slider-btn--prev"
					aria-label="Previous testimonial"
					type="button"
					data-greener-prev
				>
					<svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

				<div class="plgc-greener__slides-track">
					<?php foreach ( $testimonials as $t_index => $t ) : ?>
					<div
						class="plgc-greener__slide<?php echo $t_index === 0 ? ' is-active' : ''; ?>"
						role="group"
						aria-label="Testimonial <?php echo ( $t_index + 1 ); ?> of <?php echo $total_slides; ?>"
						<?php echo $t_index !== 0 ? 'aria-hidden="true"' : ''; ?>
					>
						<blockquote class="plgc-greener__quote">
							<p>&#8220;<?php echo esc_html( $t['quote'] ); ?>&#8221;</p>
							<footer>
								<cite class="plgc-greener__cite">&#8211; <?php echo esc_html( $t['attribution'] ); ?></cite>
							</footer>
						</blockquote>
					</div>
					<?php endforeach; ?>
				</div>

				<button
					class="plgc-greener__slider-btn plgc-greener__slider-btn--next"
					aria-label="Next testimonial"
					type="button"
					data-greener-next
				>
					<svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

			</div>

		</div>

		<?php else : ?>

		<div class="plgc-greener__testimonial">
			<?php $t = $testimonials[0]; ?>
			<blockquote class="plgc-greener__quote">
				<p>&#8220;<?php echo esc_html( $t['quote'] ); ?>&#8221;</p>
				<footer>
					<cite class="plgc-greener__cite">&#8211; <?php echo esc_html( $t['attribution'] ); ?></cite>
				</footer>
			</blockquote>
		</div>

		<?php endif; ?>

	</div>

</section>
	<?php
	return ob_get_clean();
}
