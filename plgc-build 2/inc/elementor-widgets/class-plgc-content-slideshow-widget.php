<?php
/**
 * PLGC Content Slideshow Widget
 *
 * 50/50 layout: image card left or right, text + CTA opposite.
 * Multiple images cycle via dot nav, swipe, keyboard, and optional auto-rotation.
 *
 * WCAG 2.1 AA:
 *  - SC 2.2.2  Auto-advancing content must be pausable → pause/play button
 *  - SC 2.5.5  Touch targets 44×44px → dots are 44×44 buttons, visible dot via ::before
 *  - prefers-reduced-motion → auto-rotation never starts in JS
 *  - aria-live region announces current slide to screen readers
 *  - keyboard: ArrowLeft / ArrowRight navigate slides
 *
 * @package PLGC
 * @since   1.6.8
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

class PLGC_Content_Slideshow_Widget extends Widget_Base {

	public function get_name(): string    { return 'plgc_content_slideshow'; }
	public function get_title(): string   { return 'PLGC — Content + Slideshow'; }
	public function get_icon(): string    { return 'eicon-slides'; }
	public function get_categories(): array { return [ 'plgc' ]; }
	public function get_keywords(): array { return [ 'slideshow', 'content', 'text', 'image', 'split', 'ceremony', 'plgc' ]; }
	public function get_style_depends(): array  { return [ 'plgc-gallery-widgets' ]; }
	public function get_script_depends(): array { return [ 'plgc-gallery-widgets' ]; }

	protected function register_controls(): void {

		/* ── Photos ─────────────────────────────────────────────────────────── */
		$this->start_controls_section( 'section_images', [
			'label' => 'Photos',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'a11y_notice', [
			'type' => Controls_Manager::RAW_HTML,
			'raw'  => '<div style="background:#fff8e1;border-left:4px solid #FFAE40;padding:10px 12px 10px 14px;border-radius:0 4px 4px 0;font-size:12px;line-height:1.7;color:#333333;"><strong style="color:#111;font-size:12px;">📷 Alt Text reminder:</strong><br>Gallery images are <em>informative</em>, not decorative — visitors are here to see them. Add <strong>Alt Text</strong> in the Media Library for each photo. If a photo truly adds nothing (rare), leave it blank and screen readers will skip it.</div>',
		] );

		$repeater = new Repeater();
		$repeater->add_control( 'image', [
			'label'   => 'Image',
			'type'    => Controls_Manager::MEDIA,
			'default' => [ 'url' => Utils::get_placeholder_image_src() ],
		] );
		$repeater->add_control( 'caption', [
			'label'       => 'Caption (lightbox only)',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
			'condition'   => [],  // always show — only visible in lightbox
		] );

		$this->add_control( 'images', [
			'label'       => 'Photos',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => [
				[ 'image' => [ 'url' => Utils::get_placeholder_image_src() ] ],
				[ 'image' => [ 'url' => Utils::get_placeholder_image_src() ] ],
				[ 'image' => [ 'url' => Utils::get_placeholder_image_src() ] ],
			],
			'title_field' => 'Photo',
		] );

		$this->end_controls_section();

		/* ── Text Content ────────────────────────────────────────────────────── */
		$this->start_controls_section( 'section_text', [
			'label' => 'Text Content',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'heading', [
			'label'       => 'Heading',
			'type'        => Controls_Manager::TEXT,
			'default'     => 'Section Title',
			'label_block' => true,
		] );

		$this->add_control( 'heading_tag', [
			'label'   => 'Heading Tag',
			'type'    => Controls_Manager::SELECT,
			'default' => 'h2',
			'options' => [ 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ],
		] );

		$this->add_control( 'body_text', [
			'label'   => 'Body Text',
			'type'    => Controls_Manager::WYSIWYG,
			'default' => 'Enter your text here.',
		] );

		$this->add_control( 'cta_label', [
			'label'       => 'Button Label',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => 'e.g. Book Your Wedding Venue',
		] );

		$this->add_control( 'cta_url', [
			'label'         => 'Button URL',
			'type'          => Controls_Manager::URL,
			'placeholder'   => 'https://',
			'show_external' => true,
			'default'       => [ 'url' => '' ],
		] );

		$this->end_controls_section();

		/* ── Layout & Behavior ───────────────────────────────────────────────── */
		$this->start_controls_section( 'section_layout', [
			'label' => 'Layout & Behavior',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'image_side', [
			'label'   => 'Image Position',
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'left'  => [ 'title' => 'Left',  'icon' => 'eicon-h-align-left' ],
				'right' => [ 'title' => 'Right', 'icon' => 'eicon-h-align-right' ],
			],
			'default' => 'left',
			'toggle'  => false,
		] );

		$this->add_control( 'background_color', [
			'label'   => 'Section Background',
			'type'    => Controls_Manager::COLOR,
			'default' => '#FFFFFF',
		] );

		$this->add_control( 'enable_lightbox', [
			'label'        => 'Enable Lightbox',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Opens full-size photo in a lightbox overlay when clicked.',
		] );

		$this->add_control( 'auto_rotate', [
			'label'        => 'Auto-Rotate Slides',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'WCAG 2.2.2: a pause/play button will appear automatically. Auto-rotation is disabled for users who have "reduce motion" enabled in their OS.',
		] );

		$this->add_control( 'rotate_interval', [
			'label'      => 'Rotation Speed (seconds)',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 3, 'max' => 15, 'step' => 1 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 5 ],
			'condition'  => [ 'auto_rotate' => 'yes' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s           = $this->get_settings_for_display();
		$images      = $s['images'] ?? [];
		$widget_id   = $this->get_id();
		$total       = count( $images );
		$image_side  = $s['image_side'] ?? 'left';
		$bg_color    = $s['background_color'] ?? '#FFFFFF';
		$slider_id   = 'plgc-cs-' . esc_attr( $widget_id );
		$has_slider  = $total > 1;
		$auto_rotate = $has_slider && ( $s['auto_rotate'] ?? '' ) === 'yes';
		$interval    = $auto_rotate ? max( 3, (int) ( $s['rotate_interval']['size'] ?? 5 ) ) : 0;

		$lightbox    = ( $s['enable_lightbox'] ?? '' ) === 'yes';
		$heading     = $s['heading']     ?? '';
		$heading_tag = in_array( $s['heading_tag'] ?? 'h2', [ 'h1','h2','h3','h4' ], true ) ? $s['heading_tag'] : 'h2';
		$body_text   = $s['body_text']   ?? '';
		$cta_label   = $s['cta_label']   ?? '';
		$cta_url     = $s['cta_url']['url'] ?? '';
		$cta_ext     = ! empty( $s['cta_url']['is_external'] );

		if ( empty( $images ) ) return;
		?>

		<div
			class="plgc-content-slideshow plgc-content-slideshow--img-<?php echo esc_attr( $image_side ); ?>"
			style="background-color:<?php echo esc_attr( $bg_color ); ?>;"
		>
			<!-- Image/Slider column -->
			<div
				class="plgc-content-slideshow__img-col<?php echo $lightbox ? ' has-lightbox' : ''; ?>"
				<?php if ( $has_slider ) : ?>
				data-plgc-cs-slider
				role="region"
				aria-label="<?php echo esc_attr( $heading ); ?> photos"
				id="<?php echo $slider_id; ?>"
				<?php if ( $auto_rotate ) echo 'data-plgc-cs-autorotate="' . $interval . '"'; ?>
				<?php endif; ?>
			>
				<?php if ( $has_slider ) : ?>
				<!-- Dot navigation -->
				<div class="plgc-cs-dots" role="group" aria-label="Photo navigation">
					<div class="plgc-cs-dots__sr-live" aria-live="polite" aria-atomic="true"></div>
					<?php for ( $i = 0; $i < $total; $i++ ) : ?>
					<button
						class="plgc-cs-dots__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
						type="button"
						aria-label="<?php echo esc_attr( sprintf( __( 'Photo %1$d of %2$d', 'plgc' ), $i + 1, $total ) ); ?>"
						aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-plgc-cs-dot="<?php echo $i; ?>"
					></button>
					<?php endfor; ?>
				</div>

				<?php if ( $auto_rotate ) : ?>
				<!-- WCAG 2.2.2: pause/play button (required when content auto-advances) -->
				<button class="plgc-cs-pause" type="button" aria-label="Pause slideshow" data-plgc-cs-pause>
					<svg class="plgc-cs-pause__icon-pause" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
						<rect x="6" y="4" width="4" height="16" rx="1"/>
						<rect x="14" y="4" width="4" height="16" rx="1"/>
					</svg>
					<svg class="plgc-cs-pause__icon-play" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
						<path d="M8 5L19 12L8 19V5Z"/>
					</svg>
				</button>
				<!-- Progress bar showing time until next slide -->
				<div class="plgc-cs-progress" data-plgc-cs-progress aria-hidden="true"></div>
				<?php endif; ?>
				<?php endif; ?>

				<!-- Slides -->
				<?php foreach ( $images as $idx => $item ) :
					$img_id   = (int) ( $item['image']['id'] ?? 0 );
					$img_url  = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : ( $item['image']['url'] ?? '' );
					$full_url = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : $img_url;
					$alt      = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';
					if ( ! $alt ) $alt = $heading ?: sprintf( __( 'Photo %d', 'plgc' ), $idx + 1 );
					$caption  = $item['caption'] ?? '';
				?>
				<div
					class="plgc-cs-slide<?php echo $idx === 0 ? ' is-active' : ''; ?>"
					data-plgc-cs-slide="<?php echo $idx; ?>"
					<?php echo $idx !== 0 ? 'aria-hidden="true"' : ''; ?>
				>
					<?php if ( $lightbox ) : ?>
					<button
						class="plgc-cs-slide__lb-trigger"
						type="button"
						aria-label="<?php echo esc_attr( sprintf( __( 'Open full size: %s', 'plgc' ), $alt ) ); ?>"
						data-plgc-lb-src="<?php echo esc_url( $full_url ); ?>"
						data-plgc-lb-alt="<?php echo esc_attr( $alt ); ?>"
						data-plgc-lb-caption="<?php echo esc_attr( $caption ); ?>"
						data-plgc-lb-index="<?php echo $idx; ?>"
						data-plgc-lb-total="<?php echo $total; ?>"
					>
					<?php endif; ?>

					<?php if ( $img_id ) :
						echo wp_get_attachment_image( $img_id, 'large', false, [
							'class'      => 'plgc-cs-slide__img',
							'loading'  => $idx === 0 ? 'eager' : 'lazy',
							'decoding' => 'async',
							'draggable' => 'false',
						] );
					else : ?>
					<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" class="plgc-cs-slide__img" loading="<?php echo $idx === 0 ? 'eager' : 'lazy'; ?>" decoding="async" draggable="false">
					<?php endif; ?>

					<?php if ( $lightbox ) : ?>
					<span class="plgc-filmstrip__hover-overlay" aria-hidden="true">
						<svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
							<circle cx="16" cy="16" r="16" fill="rgba(0,0,0,0.5)"/>
							<path d="M14 9H9v5M23 18v5h-5M9 14l6 6M23 18l-6-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					</button>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- Text column -->
			<div class="plgc-content-slideshow__text-col">
				<?php if ( $heading ) : ?>
				<<?php echo $heading_tag; ?> class="plgc-cs-heading"><?php echo esc_html( $heading ); ?></<?php echo $heading_tag; ?>>
				<?php endif; ?>
				<?php if ( $body_text ) : ?>
				<div class="plgc-cs-body"><?php echo wp_kses_post( $body_text ); ?></div>
				<?php endif; ?>
				<?php if ( $cta_label && $cta_url ) : ?>
				<a
					href="<?php echo esc_url( $cta_url ); ?>"
					class="plgc-btn--tee-time plgc-cs-cta"
					<?php if ( $cta_ext ) : ?>target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $cta_label . ' (opens in new tab)' ); ?>"<?php endif; ?>
				><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php
	}
}
