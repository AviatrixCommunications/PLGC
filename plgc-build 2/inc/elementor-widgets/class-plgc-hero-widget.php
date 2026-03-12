<?php
/**
 * PLGC Hero Widget
 *
 * Full-width hero section matching Figma node 42551:9293.
 * Supports four media modes:
 *   - static    : single background image
 *   - slideshow : multiple images cycle with Ken Burns effect option
 *   - video     : background <video> (self-hosted MP4/WebM) with image fallback
 *   - youtube   : YouTube background video via IFrame API (nocookie domain)
 *
 * Structure (matches Figma):
 *   - Full-width background media (image / slideshow / video / YouTube), ~700px tall
 *   - Dark green gradient overlay at bottom (brand colour #233C26)
 *   - Centered headline + subheading text over the background
 *
 * WCAG 2.1 AA:
 *   SC 2.2.2  Auto-advancing slideshows + video must be pausable
 *   SC 1.4.2  No audio autoplay
 *   SC 1.4.3  Text on hero: white on dark overlay must pass 4.5:1
 *   SC 2.5.5  All controls 44×44px
 *   prefers-reduced-motion: no autoplay, no Ken Burns
 *
 * @package PLGC
 * @since   1.6.13
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

class PLGC_Hero_Widget extends Widget_Base {

	public function get_name(): string    { return 'plgc_hero'; }
	public function get_title(): string   { return 'PLGC — Hero Section'; }
	public function get_icon(): string    { return 'eicon-banner'; }
	public function get_categories(): array { return [ 'plgc' ]; }
	public function get_keywords(): array { return [ 'hero', 'banner', 'slideshow', 'video', 'youtube', 'header', 'plgc' ]; }
	public function get_style_depends(): array  { return [ 'plgc-hero' ]; }
	public function get_script_depends(): array { return [ 'plgc-hero' ]; }

	protected function register_controls(): void {

		/* ── Media Mode ───────────────────────────────────────────────────── */
		$this->start_controls_section( 'section_media', [
			'label' => 'Background Media',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'media_mode', [
			'label'   => 'Media Type',
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'static'    => [ 'title' => 'Single Image',  'icon' => 'eicon-image' ],
				'slideshow' => [ 'title' => 'Slideshow',     'icon' => 'eicon-slides' ],
				'video'     => [ 'title' => 'Self-hosted Video', 'icon' => 'eicon-video-camera' ],
				'youtube'   => [ 'title' => 'YouTube Video', 'icon' => 'eicon-youtube' ],
			],
			'default' => 'static',
			'toggle'  => false,
		] );

		// ── Static image ─────────────────────────────────────────────────
		$this->add_control( 'static_image', [
			'label'     => 'Background Image',
			'type'      => Controls_Manager::MEDIA,
			'default'   => [ 'url' => Utils::get_placeholder_image_src() ],
			'condition' => [ 'media_mode' => 'static' ],
		] );

		// ── Slideshow images ──────────────────────────────────────────────
		$repeater = new Repeater();
		$repeater->add_control( 'slide_image', [
			'label'   => 'Image',
			'type'    => Controls_Manager::MEDIA,
			'default' => [ 'url' => Utils::get_placeholder_image_src() ],
		] );
		$repeater->add_control( 'slide_alt', [
			'label'       => 'Alt Text',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
			'description' => 'Required for accessibility.',
		] );

		$this->add_control( 'slides', [
			'label'       => 'Slides',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => [
				[ 'slide_image' => [ 'url' => Utils::get_placeholder_image_src() ], 'slide_alt' => 'Prairie Landing Golf Club' ],
				[ 'slide_image' => [ 'url' => Utils::get_placeholder_image_src() ], 'slide_alt' => 'Prairie Landing Golf Club' ],
				[ 'slide_image' => [ 'url' => Utils::get_placeholder_image_src() ], 'slide_alt' => 'Prairie Landing Golf Club' ],
			],
			'title_field' => 'Slide',
			'condition'   => [ 'media_mode' => 'slideshow' ],
		] );

		$this->add_control( 'ken_burns', [
			'label'        => 'Ken Burns Effect',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Slow zoom on each slide. Disabled for users with "reduce motion" enabled.',
			'condition'    => [ 'media_mode' => 'slideshow' ],
		] );

		$this->add_control( 'slideshow_interval', [
			'label'      => 'Slide Duration (seconds)',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 3, 'max' => 15, 'step' => 1 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 6 ],
			'condition'  => [ 'media_mode' => 'slideshow' ],
		] );

		// ── Self-hosted video ─────────────────────────────────────────────
		$this->add_control( 'video_url', [
			'label'       => 'Video URL (MP4)',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
			'description' => 'Self-hosted MP4. Background video is always muted (WCAG SC 1.4.2).',
			'condition'   => [ 'media_mode' => 'video' ],
		] );

		$this->add_control( 'video_webm', [
			'label'       => 'Video URL (WebM, optional)',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
			'description' => 'WebM is smaller/faster. Falls back to MP4 if not provided.',
			'condition'   => [ 'media_mode' => 'video' ],
		] );

		$this->add_control( 'video_fallback', [
			'label'       => 'Fallback Image',
			'type'        => Controls_Manager::MEDIA,
			'default'     => [ 'url' => Utils::get_placeholder_image_src() ],
			'description' => 'Shown while video loads, on mobile, and for prefers-reduced-motion users.',
			'condition'   => [ 'media_mode' => 'video' ],
		] );

		$this->add_control( 'video_autoplay', [
			'label'        => 'Autoplay Video',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'description'  => 'WCAG 2.2.2: A pause button will always appear for auto-playing video.',
			'condition'    => [ 'media_mode' => 'video' ],
		] );

		$this->add_control( 'video_native_ratio', [
			'label'        => 'Fit Hero to Video (16:9)',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Makes the hero height match 16:9 instead of the fixed design height. Use when the video crops too heavily at the default size.',
			'condition'    => [ 'media_mode' => 'video' ],
		] );

		// ── YouTube video ─────────────────────────────────────────────────
		$this->add_control( 'yt_notice', [
			'type' => Controls_Manager::RAW_HTML,
			'raw'  => '<div style="background:#e8f5e9;border-left:4px solid #567915;padding:10px 12px;border-radius:0 4px 4px 0;font-size:12px;line-height:1.7;color:#333;"><strong>YouTube Background Video</strong><br>
Paste a full YouTube URL (<code>youtube.com/watch?v=…</code> or <code>youtu.be/…</code>).<br>
The video plays muted, looped, and cropped to fill the hero.<br>
<strong>WCAG 2.2.2:</strong> YouTube\'s built-in controls satisfy the "user can pause" requirement. Our custom pause button adds additional keyboard-accessible control.</div>',
			'condition' => [ 'media_mode' => 'youtube' ],
		] );

		$this->add_control( 'yt_url', [
			'label'       => 'YouTube URL',
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
			'placeholder' => 'https://www.youtube.com/watch?v=XXXXXXXXXXX',
			'description' => 'Supports youtube.com/watch?v=… and youtu.be/… formats.',
			'condition'   => [ 'media_mode' => 'youtube' ],
		] );

		$this->add_control( 'yt_fallback', [
			'label'       => 'Fallback Image',
			'type'        => Controls_Manager::MEDIA,
			'default'     => [ 'url' => Utils::get_placeholder_image_src() ],
			'description' => 'Shown while YouTube loads, on mobile (autoplay blocked), and for prefers-reduced-motion users.',
			'condition'   => [ 'media_mode' => 'youtube' ],
		] );

		$this->add_control( 'yt_autoplay', [
			'label'        => 'Autoplay',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'description'  => 'YouTube always requires mute for autoplay. A pause button will appear.',
			'condition'    => [ 'media_mode' => 'youtube' ],
		] );

		$this->add_control( 'yt_fit', [
			'label'     => 'Video Fit',
			'type'      => Controls_Manager::SELECT,
			'default'   => 'cover',
			'options'   => [
				'cover'   => 'Cover — fill hero, edges may crop',
				'contain' => 'Contain — full video visible, letterboxed',
			],
			'description' => '"Cover" fills the hero but crops some of the video top/bottom. "Contain" shows the full video but adds black bars.',
			'condition'   => [ 'media_mode' => 'youtube' ],
		] );

		$this->add_control( 'yt_position', [
			'label'       => 'Vertical Position',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'center',
			'options'     => [
				'top'    => 'Top — show top of video, crop bottom',
				'center' => 'Center',
				'bottom' => 'Bottom — show bottom of video, crop top',
				'custom' => 'Custom…',
			],
			'description' => 'Controls which part of the video is visible when cropped to fill the hero.',
			'condition'   => [ 'media_mode' => 'youtube', 'yt_fit' => 'cover' ],
		] );

		$this->add_control( 'yt_focal_y', [
			'label'      => 'Custom — Vertical (%)',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ '%' ],
			'range'      => [ '%' => [ 'min' => 0, 'max' => 100, 'step' => 1 ] ],
			'default'    => [ 'unit' => '%', 'size' => 50 ],
			'condition'  => [ 'media_mode' => 'youtube', 'yt_fit' => 'cover', 'yt_position' => 'custom' ],
		] );

		$this->add_control( 'yt_interactive', [
			'label'        => 'Enable Player Controls',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Allow clicking into the YouTube player to use native controls (pause, seek, volume). Off by default for a clean background-video look — the custom pause button in the corner still works for accessibility.',
			'condition'    => [ 'media_mode' => 'youtube' ],
		] );

		$this->add_control( 'yt_native_ratio', [
			'label'        => 'Fit Hero to Video (16:9)',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'description'  => 'Makes the hero height match 16:9 instead of the fixed design height. Use when the video crops too heavily at the default size.',
			'condition'    => [ 'media_mode' => 'youtube' ],
		] );

		$this->end_controls_section();

		/* ── Hero Text ────────────────────────────────────────────────────── */
		$this->start_controls_section( 'section_text', [
			'label' => 'Hero Text',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'heading', [
			'label'       => 'Heading',
			'type'        => Controls_Manager::TEXT,
			'default'     => 'Welcome to Prairie Landing Golf Club',
			'label_block' => true,
		] );

		$this->add_control( 'heading_tag', [
			'label'   => 'Heading Tag',
			'type'    => Controls_Manager::SELECT,
			'default' => 'h1',
			'options' => [ 'h1' => 'H1', 'h2' => 'H2' ],
		] );

		$this->add_control( 'subheading', [
			'label'       => 'Subheading',
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => 'A Robert Trent Jones Jr. designed golf course and premier destination for weddings and events.',
			'label_block' => true,
		] );

		$this->end_controls_section();

		/* ── Layout ───────────────────────────────────────────────────────── */
		$this->start_controls_section( 'section_layout', [
			'label' => 'Layout',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'hero_height', [
			'label'      => 'Hero Height',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'vh' ],
			'range'      => [
				'px' => [ 'min' => 300, 'max' => 900 ],
				'vh' => [ 'min' => 30,  'max' => 100 ],
			],
			'default'    => [ 'unit' => 'px', 'size' => 698 ],
		] );

		$this->add_control( 'overlay_opacity', [
			'label'   => 'Gradient Strength',
			'type'    => Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
			'default' => [ 'size' => 88 ],
			'description' => 'Controls the opacity of the dark green gradient at the bottom. Keep ≥60% to pass WCAG 4.5:1 for white text.',
		] );

		$this->end_controls_section();
	}

	/** Extract YouTube video ID from any standard URL format. */
	private function extract_youtube_id( string $url ): string {
		if ( empty( $url ) ) return '';
		// youtu.be/ID
		if ( preg_match( '#youtu\.be/([^?&\s]+)#', $url, $m ) ) return $m[1];
		// youtube.com/watch?v=ID
		if ( preg_match( '#[?&]v=([^&\s]+)#', $url, $m ) ) return $m[1];
		// youtube.com/embed/ID
		if ( preg_match( '#/embed/([^?&\s]+)#', $url, $m ) ) return $m[1];
		// youtube.com/shorts/ID
		if ( preg_match( '#/shorts/([^?&\s]+)#', $url, $m ) ) return $m[1];
		return '';
	}



	/**
	 * Resolve a YouTube vertical translate offset from the yt_position SELECT.
	 * The YouTube oversize technique uses CSS translate(-50%, -50%) from top/left 50%.
	 * We shift "top" from 50% → 0%, "bottom" → 100%, custom → user %.
	 *
	 * @param string $position  'top' | 'center' | 'bottom' | 'custom'
	 * @param float  $custom_y  Used only when $position === 'custom'
	 * @return string  e.g. "0%" | "50%" | "100%" | "35%"
	 */
	private function resolve_yt_position( string $position, float $custom_y = 50 ): string {
		return match( $position ) {
			'top'    => '0%',
			'center' => '50%',
			'bottom' => '100%',
			'custom' => "{$custom_y}%",
			default  => '50%',
		};
	}

	protected function render(): void {
		$s           = $this->get_settings_for_display();
		$widget_id   = $this->get_id();
		$mode        = $s['media_mode'] ?? 'static';
		$heading     = $s['heading'] ?? '';
		$heading_tag = in_array( $s['heading_tag'] ?? 'h1', [ 'h1', 'h2' ], true ) ? $s['heading_tag'] : 'h1';
		$subheading  = $s['subheading'] ?? '';
		$height_val  = $s['hero_height']['size'] ?? 698;
		$height_unit = $s['hero_height']['unit'] ?? 'px';
		$overlay_op  = ( $s['overlay_opacity']['size'] ?? 88 ) / 100;
		$hero_id     = 'plgc-hero-' . esc_attr( $widget_id );

		// Image/video always centered — no position control (object-position: center in CSS)

		// YouTube vertical position (cover mode only)
		$yt_fit      = $s['yt_fit']      ?? 'cover';
		$yt_pos_y    = $this->resolve_yt_position(
			$s['yt_position'] ?? 'center',
			(float) ( $s['yt_focal_y']['size'] ?? 50 )
		);
		$yt_iactive  = ( $s['yt_interactive'] ?? '' ) === 'yes';

		// 16:9 native ratio toggle — opt-in per widget
		$native_ratio = ( ( $s['yt_native_ratio'] ?? '' ) === 'yes' && $mode === 'youtube' )
		             || ( ( $s['video_native_ratio'] ?? '' ) === 'yes' && $mode === 'video' );
		?>

		<section
			class="plgc-hero plgc-hero--<?php echo esc_attr( $mode ); ?><?php echo $native_ratio ? ' plgc-hero--ratio-16-9' : ''; ?>"
			id="<?php echo $hero_id; ?>"
			aria-label="<?php echo esc_attr( $heading ?: 'Hero' ); ?>"
			style="--plgc-hero-height:<?php echo $height_val . $height_unit; ?>;--plgc-hero-overlay:<?php echo $overlay_op; ?>;"
		>
			<!-- ── Background media ─────────────────────────────────── -->
			<div class="plgc-hero__media" aria-hidden="true">

				<?php if ( $mode === 'static' ) :
					$img_id  = (int) ( $s['static_image']['id'] ?? 0 );
					$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : ( $s['static_image']['url'] ?? '' );
					$alt     = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';
				?>
				<img
					src="<?php echo esc_url( $img_url ); ?>"
					alt="<?php echo esc_attr( $alt ); ?>"
					class="plgc-hero__bg-img"
					loading="eager"
					decoding="async"
					fetchpriority="high"
					draggable="false"
				>

				<?php elseif ( $mode === 'slideshow' ) :
					$slides   = $s['slides'] ?? [];
					$total    = count( $slides );
					$interval = max( 3, (int) ( $s['slideshow_interval']['size'] ?? 6 ) );
					$kb       = ( $s['ken_burns'] ?? '' ) === 'yes';
				?>
				<div
					class="plgc-hero__slides"
					data-plgc-hero-slides
					data-interval="<?php echo $interval; ?>"
					<?php if ( $kb ) echo 'data-ken-burns="1"'; ?>
					role="region"
					aria-label="Hero slideshow"
				>
					<?php foreach ( $slides as $idx => $slide ) :
						$img_id    = (int) ( $slide['slide_image']['id'] ?? 0 );
						$img_url   = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : ( $slide['slide_image']['url'] ?? '' );
						$alt       = trim( $slide['slide_alt'] ?? '' );
						if ( ! $alt ) $alt = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';
						// position always center center (CSS controlled)
					?>
					<div
						class="plgc-hero__slide<?php echo $idx === 0 ? ' is-active' : ''; ?>"
						data-plgc-hero-slide="<?php echo $idx; ?>"
						aria-hidden="<?php echo $idx === 0 ? 'false' : 'true'; ?>"
					>
						<?php if ( $img_id ) :
							echo wp_get_attachment_image( $img_id, 'full', false, [
								'class'         => 'plgc-hero__bg-img',
								'loading'       => $idx === 0 ? 'eager' : 'lazy',
								'fetchpriority' => $idx === 0 ? 'high' : 'auto',
								'decoding'      => 'async',
								'draggable'     => 'false',
								// object-position: center center (set in CSS, not inline style)
							] );
						else : ?>
						<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" class="plgc-hero__bg-img" loading="<?php echo $idx === 0 ? 'eager' : 'lazy'; ?>" draggable="false">
						<?php endif; ?>
					</div>
					<?php endforeach; ?>

					<?php if ( $total > 1 ) : ?>
					<!-- Dot nav -->
					<div class="plgc-hero__dots" role="group" aria-label="Slide navigation">
						<div class="plgc-hero__dots-live" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);"></div>
						<?php for ( $i = 0; $i < $total; $i++ ) : ?>
						<button
							class="plgc-hero__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
							type="button"
							aria-label="<?php echo esc_attr( sprintf( 'Go to slide %1$d of %2$d', $i + 1, $total ) ); ?>"
							aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>"
							data-plgc-hero-dot="<?php echo $i; ?>"
						></button>
						<?php endfor; ?>
					</div>

					<!-- WCAG 2.2.2: pause button (required for auto-advancing content) -->
					<button class="plgc-hero__pause" type="button" aria-label="Pause slideshow" data-plgc-hero-pause>
						<svg class="plgc-hero__pause-icon--pause" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
							<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>
						</svg>
						<svg class="plgc-hero__pause-icon--play" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
							<path d="M8 5L19 12L8 19V5Z"/>
						</svg>
					</button>

					<!-- Progress bar -->
					<div class="plgc-hero__progress" data-plgc-hero-progress aria-hidden="true"></div>
					<?php endif; ?>
				</div>

				<?php elseif ( $mode === 'video' ) :
					$video_mp4    = $s['video_url']  ?? '';
					$video_webm   = $s['video_webm'] ?? '';
					$fallback_id  = (int) ( $s['video_fallback']['id'] ?? 0 );
					$fallback_url = $fallback_id ? wp_get_attachment_image_url( $fallback_id, 'full' ) : ( $s['video_fallback']['url'] ?? '' );
					$fallback_alt = $fallback_id ? get_post_meta( $fallback_id, '_wp_attachment_image_alt', true ) : '';
					$autoplay     = ( $s['video_autoplay'] ?? 'yes' ) === 'yes';
				?>
				<!-- Fallback image shown while video loads / on reduced-motion / mobile -->
				<img
					src="<?php echo esc_url( $fallback_url ); ?>"
					alt="<?php echo esc_attr( $fallback_alt ); ?>"
					class="plgc-hero__bg-img plgc-hero__video-fallback"
					loading="eager"
					fetchpriority="high"
					draggable="false"
				>

				<?php if ( $video_mp4 ) : ?>
				<video
					class="plgc-hero__video"
					data-plgc-hero-video
					<?php if ( $autoplay ) echo 'autoplay'; ?>
					muted
					loop
					playsinline
					preload="metadata"
					poster="<?php echo esc_url( $fallback_url ); ?>"
					aria-hidden="true"
				>
					<?php if ( $video_webm ) : ?>
					<source src="<?php echo esc_url( $video_webm ); ?>" type="video/webm">
					<?php endif; ?>
					<source src="<?php echo esc_url( $video_mp4 ); ?>" type="video/mp4">
				</video>

				<?php if ( $autoplay ) : ?>
				<!-- WCAG 2.2.2: video pause button (always shown for auto-playing video) -->
				<button class="plgc-hero__pause" type="button" aria-label="Pause video" data-plgc-hero-vidpause>
					<svg class="plgc-hero__pause-icon--pause" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
						<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>
					</svg>
					<svg class="plgc-hero__pause-icon--play" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
						<path d="M8 5L19 12L8 19V5Z"/>
					</svg>
				</button>
				<?php endif; ?>
				<?php endif; ?>

				<?php elseif ( $mode === 'youtube' ) :
					$yt_url      = $s['yt_url'] ?? '';
					$yt_id       = $this->extract_youtube_id( $yt_url );
					$yt_autoplay = ( $s['yt_autoplay'] ?? 'yes' ) === 'yes';
					$fb_id       = (int) ( $s['yt_fallback']['id'] ?? 0 );
					$fb_url      = $fb_id ? wp_get_attachment_image_url( $fb_id, 'full' ) : ( $s['yt_fallback']['url'] ?? '' );
					$fb_alt      = $fb_id ? get_post_meta( $fb_id, '_wp_attachment_image_alt', true ) : '';

					// Build wrap classes and styles
					$yt_wrap_classes = 'plgc-hero__yt-wrap';
					$yt_wrap_classes .= $yt_fit === 'contain' ? ' is-contain' : ' is-cover';
					if ( $yt_iactive ) $yt_wrap_classes .= ' is-interactive';

					// Vertical position only applies to cover mode
					$yt_wrap_style = $yt_fit === 'cover'
						? "--plgc-yt-pos-y:{$yt_pos_y};"
						: '';

					// controls=1 shows native UI; only useful when interactive
					$yt_controls = $yt_iactive ? '1' : '0';
				?>
				<!-- Fallback image (shows until YouTube API fires / on reduced-motion) -->
				<img
					src="<?php echo esc_url( $fb_url ); ?>"
					alt="<?php echo esc_attr( $fb_alt ); ?>"
					class="plgc-hero__bg-img plgc-hero__yt-fallback"
					loading="eager"
					fetchpriority="high"
					draggable="false"
				>

				<?php if ( $yt_id ) : ?>
				<!-- YouTube background embed
				     - youtube-nocookie.com for privacy (no tracking cookies on load)
				     - enablejsapi=1 allows pause/play via postMessage (our pause btn)
				     - controls=0 by default: cleaner look; our pause btn handles WCAG 2.2.2.
				       controls=1 when "Enable Player Controls" is on (yt_interactive).
				     - mute=1      required for autoplay across all browsers
				     - playsinline=1 prevents full-screen on iOS
				     - is-cover / is-contain class drives fit mode in CSS
				     - is-interactive class enables pointer-events so native controls work
				     - --plgc-yt-pos-y shifts the vertical crop point in cover mode
				-->
				<div
					class="<?php echo esc_attr( $yt_wrap_classes ); ?>"
					data-plgc-hero-yt
					data-yt-id="<?php echo esc_attr( $yt_id ); ?>"
					data-yt-autoplay="<?php echo $yt_autoplay ? '1' : '0'; ?>"
					<?php if ( $yt_wrap_style ) echo 'style="' . esc_attr( $yt_wrap_style ) . '"'; ?>
				>
					<iframe
						class="plgc-hero__yt-frame"
						id="plgc-yt-<?php echo esc_attr( $widget_id ); ?>"
						src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $yt_id ); ?>?autoplay=<?php echo $yt_autoplay ? '1' : '0'; ?>&mute=1&loop=1&playlist=<?php echo esc_attr( $yt_id ); ?>&controls=<?php echo $yt_controls; ?>&enablejsapi=1&playsinline=1&rel=0&modestbranding=1"
						title="Background video"
						allow="autoplay; encrypted-media; accelerometer; gyroscope; picture-in-picture; fullscreen"
						allowfullscreen
						loading="lazy"
					></iframe>
				</div>

				<?php if ( $yt_autoplay ) : ?>
				<!-- WCAG 2.2.2: custom pause/play button so keyboard users
				     don't have to navigate into the iframe to pause.
				     Also communicates with YouTube API via postMessage. -->
				<button
					class="plgc-hero__pause"
					type="button"
					aria-label="Pause video"
					data-plgc-hero-ytpause
					data-yt-target="plgc-yt-<?php echo esc_attr( $widget_id ); ?>"
				>
					<svg class="plgc-hero__pause-icon--pause" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
						<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>
					</svg>
					<svg class="plgc-hero__pause-icon--play" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
						<path d="M8 5L19 12L8 19V5Z"/>
					</svg>
				</button>
				<?php endif; ?>
				<?php endif; // $yt_id ?>
				<?php endif; // mode === youtube ?>

			</div><!-- /.plgc-hero__media -->

			<!-- ── Gradient overlay — improves text contrast ────────────── -->
			<div class="plgc-hero__overlay" aria-hidden="true"></div>

			<!-- ── Hero text ────────────────────────────────────────────── -->
			<div class="plgc-hero__content">
				<?php if ( $heading ) : ?>
				<<?php echo $heading_tag; ?> class="plgc-hero__heading">
					<?php echo esc_html( $heading ); ?>
				</<?php echo $heading_tag; ?>>
				<?php endif; ?>
				<?php if ( $subheading ) : ?>
				<p class="plgc-hero__subheading"><?php echo esc_html( $subheading ); ?></p>
				<?php endif; ?>
			</div>

		</section>

		<?php
	}
}
