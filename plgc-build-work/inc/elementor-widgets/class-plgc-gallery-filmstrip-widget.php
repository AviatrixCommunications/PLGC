<?php
/**
 * PLGC Gallery Filmstrip Widget
 *
 * Horizontal scroll strip of portrait-format photos.
 * Matches Figma node 42551:9527: 309×414px cards, 27px gap, full-bleed.
 * Prev/next: dark green circle arrow buttons, bottom-right.
 *
 * Features:
 *  - Touch swipe (handled in JS)
 *  - Optional auto-rotation with WCAG-compliant pause button
 *  - Lightbox on click
 *  - Keyboard: ArrowLeft/Right scrolls, Enter/Space opens lightbox
 *  - prefers-reduced-motion: auto-rotation disabled in JS
 *
 * WCAG 2.1 AA: SC 2.2.2 (pause), SC 2.5.5 (44px targets on nav btns)
 *
 * @package PLGC
 * @since   1.6.8
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Group_Control_Image_Size;

class PLGC_Gallery_Filmstrip_Widget extends Widget_Base {

	public function get_name(): string    { return 'plgc_gallery_filmstrip'; }
	public function get_title(): string   { return 'PLGC — Photo Filmstrip'; }
	public function get_icon(): string    { return 'eicon-gallery-justified'; }
	public function get_categories(): array { return [ 'plgc' ]; }
	public function get_keywords(): array { return [ 'gallery', 'filmstrip', 'strip', 'scroll', 'photos', 'plgc' ]; }
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
		$repeater->add_control( 'focal_point', [
			'label'       => 'Crop Focus',
			'type'        => Controls_Manager::SELECT,
			'description' => 'Where should the filmstrip card center the crop? Upload full-size originals — this controls what\'s visible in the strip. Lightbox always shows the full image.',
			'default'     => 'center center',
			'options'     => [
				'center center' => 'Center (default)',
				'center top'    => 'Top',
				'center bottom' => 'Bottom',
				'left center'   => 'Left',
				'right center'  => 'Right',
				'left top'      => 'Top Left',
				'right top'     => 'Top Right',
				'left bottom'   => 'Bottom Left',
				'right bottom'  => 'Bottom Right',
			],
		] );
		$repeater->add_control( 'focal_point_custom', [
			'label'       => 'Custom Focus (X% Y%)',
			'type'        => Controls_Manager::TEXT,
			'description' => 'Fine-tune: e.g. "30% 20%" positions the crop 30% from left, 20% from top. Leave blank to use the preset above.',
			'default'     => '',
			'placeholder' => '50% 50%',
			'label_block' => true,
		] );
		$repeater->add_control( 'caption', [
			'label'       => 'Caption (shows in lightbox)',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
		] );

		$this->add_control( 'images', [
			'label'       => 'Photos',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => array_fill( 0, 6, [ 'image' => [ 'url' => Utils::get_placeholder_image_src() ], 'caption' => '' ] ),
			'title_field' => 'Photo',
		] );

		$this->add_group_control( Group_Control_Image_Size::get_type(), [
			'name'    => 'image',
			'default' => 'large',
		] );

		$this->end_controls_section();

		/* ── Layout & Behavior ───────────────────────────────────────────────── */
		$this->start_controls_section( 'section_layout', [
			'label' => 'Layout & Behavior',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'card_height', [
			'label'      => 'Card Height',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 200, 'max' => 600 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 414 ],
		] );

		$this->add_control( 'card_width', [
			'label'      => 'Card Width',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 160, 'max' => 500 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 309 ],
		] );

		$this->add_control( 'card_gap', [
			'label'      => 'Gap Between Cards',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 27 ],
		] );

		$this->add_control( 'enable_lightbox', [
			'label'        => 'Enable Lightbox',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'auto_scroll', [
			'label'        => 'Auto-Scroll Strip',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Slowly scrolls the filmstrip automatically. WCAG 2.2.2: a pause button will appear. Disabled for users with "reduce motion" enabled.',
		] );

		$this->add_control( 'scroll_speed', [
			'label'      => 'Scroll Speed (px/second)',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 10, 'max' => 120, 'step' => 10 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 40 ],
			'condition'  => [ 'auto_scroll' => 'yes' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s           = $this->get_settings_for_display();
		$images      = $s['images'] ?? [];
		$widget_id   = $this->get_id();
		$total       = count( $images );

		if ( empty( $images ) ) {
			echo '<p style="padding:2rem;text-align:center;color:#888;">Add photos in the widget panel.</p>';
			return;
		}

		$lightbox    = ( $s['enable_lightbox'] ?? 'yes' ) === 'yes';
		$height      = (int) ( $s['card_height']['size'] ?? 414 );
		$width       = (int) ( $s['card_width']['size']  ?? 309 );
		$gap         = (int) ( $s['card_gap']['size']     ?? 27  );
		$auto_scroll = ( $s['auto_scroll'] ?? '' ) === 'yes';
		$speed       = max( 10, (int) ( $s['scroll_speed']['size'] ?? 40 ) );
		?>

		<div
			class="plgc-filmstrip"
			data-plgc-gallery-strip
			style="--plgc-fs-height:<?php echo $height; ?>px;--plgc-fs-width:<?php echo $width; ?>px;--plgc-fs-gap:<?php echo $gap; ?>px;"
			role="region"
			aria-label="<?php echo esc_attr( sprintf( _n( 'Photo gallery, %d photo', 'Photo gallery, %d photos', $total, 'plgc' ), $total ) ); ?>"
			<?php if ( $auto_scroll ) echo 'data-plgc-fs-autoscroll="' . $speed . '"'; ?>
		>
			<div class="plgc-filmstrip__track" data-plgc-fs-track>
				<ul class="plgc-filmstrip__list" role="list">
					<?php foreach ( $images as $idx => $item ) :
						$img_id   = (int) ( $item['image']['id'] ?? 0 );
						$img_url  = $img_id ? wp_get_attachment_image_url( $img_id, $s['image_size'] ?? 'large' ) : ( $item['image']['url'] ?? '' );
						$full_url = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : $img_url;
						$alt      = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';
						if ( ! $alt ) $alt = $item['caption'] ?: sprintf( __( 'Gallery photo %d', 'plgc' ), $idx + 1 );
						$caption  = $item['caption'] ?? '';

						// Per-image focal point for filmstrip crop control
						$fp_custom = trim( $item['focal_point_custom'] ?? '' );
						$fp_preset = $item['focal_point'] ?? 'center center';
						$focal     = $fp_custom ?: $fp_preset;
						// Sanitize: only allow values matching CSS object-position patterns
						$focal     = preg_match( '/^[\d.]+%?\s+[\d.]+%?$|^(?:left|right|center)\s+(?:top|bottom|center)$/', $focal ) ? $focal : 'center center';
						$fp_style  = $focal !== 'center center' ? 'object-position:' . esc_attr( $focal ) . ';' : '';
					?>
					<li class="plgc-filmstrip__item">
						<?php if ( $lightbox ) : ?>
						<button
							class="plgc-filmstrip__trigger"
							type="button"
							aria-label="<?php echo esc_attr( sprintf( __( 'Open photo %1$d of %2$d: %3$s', 'plgc' ), $idx + 1, $total, $alt ) ); ?>"
							data-plgc-lb-src="<?php echo esc_url( $full_url ); ?>"
							data-plgc-lb-alt="<?php echo esc_attr( $alt ); ?>"
							data-plgc-lb-caption="<?php echo esc_attr( $caption ); ?>"
							data-plgc-lb-index="<?php echo $idx; ?>"
							data-plgc-lb-total="<?php echo $total; ?>"
						>
						<?php else : ?>
						<div class="plgc-filmstrip__trigger">
						<?php endif; ?>

							<?php if ( $img_id ) :
								// sizes hint tells the browser the actual display width so it
								// picks the right srcset candidate. Without this, WordPress
								// defaults to sizes="100vw" and the browser may pick a small
								// intermediate (e.g. 300px medium) that looks blurry in the
								// 309px card on retina displays.
								$sizes_attr = sprintf( '(min-resolution: 2dppx) %dpx, %dpx', $width * 2, $width );
								echo wp_get_attachment_image( $img_id, $s['image_size'] ?? 'large', false, [
									'class'    => 'plgc-filmstrip__img',
									'style'    => $fp_style,
									'sizes'    => $sizes_attr,
									'loading'  => $idx < 4 ? 'eager' : 'lazy',
									'decoding' => 'async',
								] );
							else : ?>
							<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" class="plgc-filmstrip__img" <?php if ( $fp_style ) echo 'style="' . esc_attr( $fp_style ) . '"'; ?> loading="<?php echo $idx < 4 ? 'eager' : 'lazy'; ?>" decoding="async" draggable="false">
							<?php endif; ?>

							<?php if ( $lightbox ) : ?>
							<span class="plgc-filmstrip__hover-overlay" aria-hidden="true">
								<svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
									<circle cx="16" cy="16" r="16" fill="rgba(0,0,0,0.5)"/>
									<path d="M14 9H9v5M23 18v5h-5M9 14l6 6M23 18l-6-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
							<?php endif; ?>

						<?php if ( $lightbox ) : ?></button><?php else : ?></div><?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Prev/Next nav + optional pause button -->
			<div class="plgc-filmstrip__nav" role="group" aria-label="Scroll gallery">
				<?php if ( $auto_scroll ) : ?>
				<button class="plgc-filmstrip__pause" type="button" aria-label="Pause scrolling" data-plgc-fs-pause>
					<svg class="plgc-fs-pause__icon-pause" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
						<rect x="6" y="4" width="4" height="16" rx="1"/>
						<rect x="14" y="4" width="4" height="16" rx="1"/>
					</svg>
					<svg class="plgc-fs-pause__icon-play" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
						<path d="M8 5L19 12L8 19V5Z"/>
					</svg>
				</button>
				<?php endif; ?>
				<button class="plgc-filmstrip__nav-btn plgc-filmstrip__nav-btn--prev" type="button" aria-label="Scroll to previous photos" data-plgc-fs-prev>
					<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button class="plgc-filmstrip__nav-btn plgc-filmstrip__nav-btn--next" type="button" aria-label="Scroll to next photos" data-plgc-fs-next>
					<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			</div>
		</div>

		<?php
	}
}
