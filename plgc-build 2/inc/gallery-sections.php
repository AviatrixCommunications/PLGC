<?php
/**
 * PLGC Gallery Sections
 *
 * Provides [plgc_gallery_section] shortcode for the three homepage
 * overlapping-card sections: Golf Outings, Weddings & Events, McChesney's.
 *
 * Images and content are managed via ACF fields on the PL Settings options
 * page (WP Admin → PL Settings → Gallery Sections tab).
 *
 * Shortcode usage:
 *   [plgc_gallery_section section="golf-outings"]
 *   [plgc_gallery_section section="weddings"]
 *   [plgc_gallery_section section="mcchesneys"]
 *
 * WCAG 2.1 AA compliant:
 *   - No autoplay
 *   - Keyboard navigation (← → keys)
 *   - Each dot is a focusable button with descriptive aria-label
 *   - aria-current="true" on the active dot
 *   - role="region" + aria-label on the slider wrapper
 *   - Each slide: role="group" + aria-label="Slide X of Y"
 *   - Screen reader live region announces slide changes
 *   - Images use ACF alt text field; falls back to a generated label
 *   - All interactive elements meet 44×44px minimum touch target
 *   - Yellow accent line colour (#FFAE40 on #FFFFFF) = 2.37:1 — decorative only,
 *     exempt from WCAG 1.4.11 (it conveys no information, is non-text UI decoration)
 *
 * @package PLGC
 * @since   1.5.0
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// 1. SECTION CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns the static config for each gallery section.
 * Text defaults are used if the ACF field is empty.
 */
function plgc_gs_config( string $section ): array {
	$configs = [
		'golf-outings' => [
			'label'           => 'Golf Outings',
			'aria_label'      => 'Golf Outings gallery section',
			'image_side'      => 'left',   // image left, card overlaps from right
			'dot_position'    => 'left',   // dots at top-left of image
			'acf_gallery'     => 'plgc_gs_golf_outings_images',
			'acf_heading'     => 'plgc_gs_golf_outings_heading',
			'acf_body'        => 'plgc_gs_golf_outings_body',
			'acf_btn_label'   => 'plgc_gs_golf_outings_btn_label',
			'acf_btn_url'     => 'plgc_gs_golf_outings_btn_url',
			'acf_badge'       => '',
			'default_heading' => 'Golf Outings',
			'default_body'    => 'Impress your guests by choosing the ideal venue for your golf outing. Customize the event to suit the specific needs of your group.',
			'default_btn'     => 'View Our Packages',
			'default_url'     => '/golf/golf-outings/',
		],
		'weddings' => [
			'label'           => 'Weddings &amp; Events',
			'aria_label'      => 'Weddings and Events gallery section',
			'image_side'      => 'right',  // image right, card overlaps from left
			'dot_position'    => 'right',  // dots at top-right of image
			'acf_gallery'     => 'plgc_gs_weddings_images',
			'acf_heading'     => 'plgc_gs_weddings_heading',
			'acf_body'        => 'plgc_gs_weddings_body',
			'acf_btn_label'   => 'plgc_gs_weddings_btn_label',
			'acf_btn_url'     => 'plgc_gs_weddings_btn_url',
			'acf_badge'       => '',
			'default_heading' => 'Weddings &amp; Events',
			'default_body'    => 'Our recently renovated, award-winning ballroom, along with stunning landscapes, creates the ideal setting for weddings and events.',
			'default_btn'     => 'Inquire About Your Event',
			'default_url'     => '/weddings-events/',
		],
		'mcchesneys' => [
			'label'           => "McChesney&#8217;s Pub &amp; Grill",
			'aria_label'      => "McChesney's Pub and Grill gallery section",
			'image_side'      => 'left',
			'dot_position'    => 'left',
			'acf_gallery'     => 'plgc_gs_mcchesneys_images',
			'acf_heading'     => 'plgc_gs_mcchesneys_heading',
			'acf_body'        => 'plgc_gs_mcchesneys_body',
			'acf_btn_label'   => 'plgc_gs_mcchesneys_btn_label',
			'acf_btn_url'     => 'plgc_gs_mcchesneys_btn_url',
			'acf_badge'       => 'plgc_gs_mcchesneys_badge',
			'default_heading' => "McChesney&#8217;s Pub &amp; Grill",
			'default_body'    => "McChesney&#8217;s Pub &amp; Grill is open to the public, offering food and drink specials in our dining room or on the patio.",
			'default_btn'     => 'View Our Specials',
			'default_url'     => '/mcchesneys-pub-grill/',
		],
	];

	return $configs[ $section ] ?? [];
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. ACF FIELD GROUP REGISTRATION
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'acf/init', 'plgc_gs_register_acf_fields' );

function plgc_gs_register_acf_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	// Helper: build fields for one section
	$make_section_fields = function( string $key, string $label, bool $has_badge = false ): array {
		$fields = [
			[
				'key'           => "field_{$key}_tab",
				'label'         => $label,
				'name'          => '',
				'type'          => 'tab',
				'placement'     => 'left',
				'endpoint'      => 0,
			],
			[
				'key'           => "field_{$key}_images",
				'label'         => 'Gallery Images',
				'name'          => "plgc_gs_{$key}_images",
				'type'          => 'gallery',
				'instructions'  => 'Add one or more photos for the slider. First image shown by default. Always fill in the “Alt Text” field when uploading in the Media Library — this is what screen readers announce. (This is the Alt Text box, not the Caption.)',
				'required'      => 0,
				'min'           => 0,
				'max'           => 0,
				'insert'        => 'append',
				'library'       => 'all',
				'mime_types'    => 'jpg,jpeg,png,webp',
				'preview_size'  => 'medium',
				'return_format' => 'array',
			],
			[
				'key'           => "field_{$key}_heading",
				'label'         => 'Section Heading',
				'name'          => "plgc_gs_{$key}_heading",
				'type'          => 'text',
				'instructions'  => 'Leave blank to use the default heading.',
				'required'      => 0,
				'placeholder'   => $label,
			],
			[
				'key'           => "field_{$key}_body",
				'label'         => 'Body Text',
				'name'          => "plgc_gs_{$key}_body",
				'type'          => 'wysiwyg',
				'instructions'  => 'Leave blank to use the default body text.',
				'required'      => 0,
				'tabs'          => 'all',
				'toolbar'       => 'basic',
				'media_upload'  => 0,
				'default_value' => '',
				'delay'         => 0,
			],
			[
				'key'           => "field_{$key}_btn_label",
				'label'         => 'Button Label',
				'name'          => "plgc_gs_{$key}_btn_label",
				'type'          => 'text',
				'required'      => 0,
				'placeholder'   => 'e.g. View Our Packages',
			],
			[
				'key'           => "field_{$key}_btn_url",
				'label'         => 'Button URL',
				'name'          => "plgc_gs_{$key}_btn_url",
				'type'          => 'url',
				'required'      => 0,
				'placeholder'   => 'e.g. /golf/golf-outings/',
			],
		];

		if ( $has_badge ) {
			$fields[] = [
				'key'           => "field_{$key}_badge",
				'label'         => 'Badge Image',
				'name'          => "plgc_gs_{$key}_badge",
				'type'          => 'image',
				'instructions'  => 'Circular badge displayed below the button (126×126 px). Leave blank to hide.',
				'required'      => 0,
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
				'library'       => 'all',
			];
		}

		return $fields;
	};

	$all_fields = array_merge(
		[
			[
				'key'      => 'field_plgc_gs_intro_tab',
				'label'    => 'Gallery Sections',
				'name'     => '',
				'type'     => 'tab',
				'placement'=> 'left',
				'endpoint' => 0,
			],
			[
				'key'      => 'field_plgc_gs_intro_message',
				'label'    => '',
				'name'     => 'plgc_gs_intro_message',
				'type'     => 'message',
				'message'  => '<p><strong>Homepage Gallery Sections</strong></p>'
				            . '<p>Upload photos for each homepage gallery section here. '
				            . 'Each section supports multiple images — they cycle through a slider with keyboard navigation. '
				            . 'Always fill in the <strong>Alt Text</strong> field when uploading in the Media Library — this is what screen readers announce. (Alt Text is the dedicated accessibility field, separate from the Caption box.)</p>',
				'new_lines'=> 'wpautop',
				'esc_html' => 0,
			],
		],
		$make_section_fields( 'golf_outings', 'Golf Outings' ),
		$make_section_fields( 'weddings',     'Weddings &amp; Events' ),
		$make_section_fields( 'mcchesneys',   "McChesney&#8217;s Pub &amp; Grill", true )
	);

	acf_add_local_field_group( [
		'key'      => 'group_plgc_gallery_sections',
		'title'    => 'Homepage Gallery Sections',
		'fields'   => $all_fields,
		'location' => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'plgc-settings',
				],
			],
		],
		'menu_order'            => 25,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen'        => '',
		'active'                => true,
	] );
}


// ─────────────────────────────────────────────────────────────────────────────
// 3. ASSET ENQUEUEING
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'plgc_gs_enqueue_assets' );

function plgc_gs_enqueue_assets(): void {
	// Swiper 11 — CSS-first, minimal, no jQuery dependency
	wp_register_style(
		'swiper',
		'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
		[],
		'11.2.5'
	);
	wp_register_script(
		'swiper',
		'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
		[],
		'11.2.5',
		true
	);

	// Our gallery styles + JS
	wp_register_style(
		'plgc-gallery-sections',
		PLGC_URI . '/assets/css/gallery-sections.css',
		[ 'plgc-theme', 'swiper' ],
		PLGC_VERSION
	);
	wp_register_script(
		'plgc-gallery-sections',
		PLGC_URI . '/assets/js/gallery-sections.js',
		[ 'swiper' ],
		PLGC_VERSION,
		true
	);
}


// ─────────────────────────────────────────────────────────────────────────────
// 4. SHORTCODE
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'plgc_gallery_section', 'plgc_gs_shortcode' );

function plgc_gs_shortcode( array $atts ): string {
	$atts = shortcode_atts( [ 'section' => '' ], $atts, 'plgc_gallery_section' );

	// Normalise: "golf-outings" or "golf_outings" both work
	$section_key = str_replace( '_', '-', sanitize_key( $atts['section'] ) );
	$config      = plgc_gs_config( $section_key );

	if ( empty( $config ) ) {
		return '<!-- [plgc_gallery_section] unknown section: ' . esc_html( $section_key ) . ' -->';
	}

	// Enqueue assets now that we know the shortcode is actually used
	wp_enqueue_style( 'swiper' );
	wp_enqueue_script( 'swiper' );
	wp_enqueue_style( 'plgc-gallery-sections' );
	wp_enqueue_script( 'plgc-gallery-sections' );

	// ── Pull ACF field values (from options page) ────────────────────────────
	$options_page = 'option';   // ACF options page slug

	$images    = function_exists( 'get_field' )
		? ( get_field( $config['acf_gallery'], $options_page ) ?: [] )
		: [];
	$heading   = function_exists( 'get_field' )
		? get_field( $config['acf_heading'], $options_page )
		: '';
	$body      = function_exists( 'get_field' )
		? get_field( $config['acf_body'], $options_page )
		: '';
	$btn_label = function_exists( 'get_field' )
		? get_field( $config['acf_btn_label'], $options_page )
		: '';
	$btn_url   = function_exists( 'get_field' )
		? get_field( $config['acf_btn_url'], $options_page )
		: '';
	$badge     = ( ! empty( $config['acf_badge'] ) && function_exists( 'get_field' ) )
		? get_field( $config['acf_badge'], $options_page )
		: null;

	// Fall back to defaults
	$heading   = $heading   ?: $config['default_heading'];
	$body      = $body      ?: '<p>' . $config['default_body'] . '</p>';
	$btn_label = $btn_label ?: $config['default_btn'];
	$btn_url   = $btn_url   ?: $config['default_url'];

	// ── Build HTML ───────────────────────────────────────────────────────────
	$image_side   = $config['image_side'];   // 'left' or 'right'
	$dot_position = $config['dot_position']; // 'left' or 'right'
	$section_id   = 'plgc-gs-' . $section_key;
	$heading_id   = $section_id . '-heading';

	ob_start();
	?>

<section
	class="plgc-gs plgc-gs--image-<?php echo esc_attr( $image_side ); ?>"
	aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
>

	<?php /* ── Image / Slider column ──────────────────────────────────── */ ?>
	<div class="plgc-gs__image-col plgc-gs__image-col--dots-<?php echo esc_attr( $dot_position ); ?>">

		<?php if ( ! empty( $images ) ) : ?>

		<div
			class="swiper plgc-gs__slider"
			id="<?php echo esc_attr( $section_id ); ?>-slider"
			role="region"
			aria-label="<?php echo esc_attr( $config['aria_label'] ); ?> — image gallery"
			aria-roledescription="carousel"
		>
			<?php
			/*
			 * Accessible live region — screen readers announce slide changes.
			 * Placed BEFORE swiper-wrapper so it's discovered immediately.
			 */
			?>
			<div
				class="plgc-gs__sr-live"
				aria-live="polite"
				aria-atomic="true"
				role="status"
			></div>

			<div class="swiper-wrapper">
				<?php
				$total = count( $images );
				foreach ( $images as $i => $image ) :
					// Use the Alt Text field from the Media Library (Edit Image -> Alt Text box).
					// Caption is for display text and should not be repurposed as alt text.
					$img_url = $image['url'] ?? '';
					$img_alt = $image['alt']
						?: sprintf(
							/* translators: 1: section label, 2: slide number */
							__( '%1$s â slide %2$d', 'plgc' ),
							wp_strip_all_tags( $config['label'] ),
							$i + 1
						);
					$slide_label = sprintf(
						/* translators: 1: current slide number, 2: total slides */
						__( 'Slide %1$d of %2$d', 'plgc' ),
						$i + 1,
						$total
					);
					?>
				<div
					class="swiper-slide plgc-gs__slide"
					role="group"
					aria-roledescription="slide"
					aria-label="<?php echo esc_attr( $slide_label ); ?>"
				>
					<img
						src="<?php echo esc_url( $img_url ); ?>"
						alt="<?php echo esc_attr( $img_alt ); ?>"
						loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
						decoding="async"
						class="plgc-gs__slide-img"
					>
				</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $total > 1 ) : ?>
			<?php /* Dots rendered by JS below, this placeholder captures them */ ?>
			<div
				class="swiper-pagination plgc-gs__dots"
				role="tablist"
				aria-label="<?php esc_attr_e( 'Gallery slides', 'plgc' ); ?>"
			></div>
			<?php endif; ?>

		</div>

		<?php else : ?>
		<?php /* Placeholder shown when no images uploaded yet */ ?>
		<div class="plgc-gs__placeholder" aria-label="<?php echo esc_attr( $config['aria_label'] ); ?>">
			<p><?php esc_html_e( 'Gallery images not yet uploaded. Add them via WP Admin → PL Settings → Gallery Sections.', 'plgc' ); ?></p>
		</div>
		<?php endif; ?>

	</div><?php /* .plgc-gs__image-col */ ?>

	<?php /* ── Content card ────────────────────────────────────────────── */ ?>
	<div class="plgc-gs__card">
		<h2
			id="<?php echo esc_attr( $heading_id ); ?>"
			class="plgc-gs__heading"
		><?php echo wp_kses_post( $heading ); ?></h2>

		<div class="plgc-gs__body">
			<?php echo wp_kses_post( $body ); ?>
		</div>

		<?php if ( $btn_label && $btn_url ) : ?>
		<div class="plgc-gs__btn-row">
		<a
			href="<?php echo esc_url( $btn_url ); ?>"
			class="plgc-gs__btn plgc-btn"
		><?php echo esc_html( $btn_label ); ?></a>

		<?php /* Badge — linked to same URL as CTA (tabindex=-1/aria-hidden so the
		   link isn't a duplicate keyboard stop; the CTA button is the real stop) */ ?>
		<?php if ( ! empty( $badge ) ) : ?>
		<a
			href="<?php echo esc_url( $btn_url ); ?>"
			class="plgc-gs__badge-link"
			aria-label="<?php echo esc_attr( "McChesney&#8217;s Pub &amp; Grill &#8212; View Our Specials" ); ?>"
			tabindex="-1"
			aria-hidden="true"
		>
			<img
				src="<?php echo esc_url( $badge['url'] ); ?>"
				alt=""
				width="126"
				height="126"
				class="plgc-gs__badge"
				loading="lazy"
				decoding="async"
			>
		</a>
		<?php endif; ?>
		<?php endif; ?>

	</div><?php /* .plgc-gs__card */ ?>

</section>

	<?php
	return ob_get_clean();
}
