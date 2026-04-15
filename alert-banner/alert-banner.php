<?php
/**
 * Plugin Name: PLGC Alert Banners
 * Description: Dismissable notification banners managed via ACF options page. Requires Advanced Custom Fields Pro.
 * Version: 1.0.0
 * Author: Aviatrix Communications
 * Requires Plugins: advanced-custom-fields-pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ALERT_BANNER_VERSION', '1.1.0' );
define( 'ALERT_BANNER_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALERT_BANNER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register ACF options page.
 */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) ) return;

	acf_add_options_page( [
		'page_title'  => 'Alert Bar Settings',
		'menu_title'  => 'Alert Bar Settings',
		'menu_slug'   => 'alert-bar-settings',
		'capability'  => 'edit_posts',
		'redirect'    => false,
		'icon_url'    => 'dashicons-warning',
		'position'    => 80,
		'autoload'    => false,
	] );
} );

/**
 * Register ACF field group.
 */
add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

	acf_add_local_field_group( [
		'key'      => 'group_alert_banner_plugin',
		'title'    => 'Alert Bars',
		'fields'   => [
			[
				'key'          => 'field_abp_bar_alerts',
				'label'        => 'Alerts',
				'name'         => 'bar_alerts',
				'type'         => 'repeater',
				'layout'       => 'row',
				'min'          => 0,
				'max'          => 3,
				'button_label' => 'Add Alert',
				'sub_fields'   => [
					[
						'key'           => 'field_abp_display_alert',
						'label'         => 'Display Alert',
						'name'          => 'display_alert',
						'type'          => 'select',
						'choices'       => [
							'off'      => 'Off',
							'on'       => 'On',
							'schedule' => 'Schedule Date Range',
						],
						'default_value' => 'off',
					],
					[
						'key'               => 'field_abp_start_date',
						'label'             => 'Start Date',
						'name'              => 'alert_start_date',
						'type'              => 'date_picker',
						'required'          => 1,
						'display_format'    => 'F j, Y',
						'return_format'     => 'Ymd',
						'conditional_logic' => [ [ [
							'field'    => 'field_abp_display_alert',
							'operator' => '==',
							'value'    => 'schedule',
						] ] ],
					],
					[
						'key'               => 'field_abp_end_date',
						'label'             => 'End Date',
						'name'              => 'alert_end_date',
						'type'              => 'date_picker',
						'required'          => 1,
						'display_format'    => 'F j, Y',
						'return_format'     => 'Ymd',
						'conditional_logic' => [ [ [
							'field'    => 'field_abp_display_alert',
							'operator' => '==',
							'value'    => 'schedule',
						] ] ],
					],
					[
						'key'               => 'field_abp_content',
						'label'             => 'Content',
						'name'              => 'alert_content',
						'type'              => 'wysiwyg',
						'required'          => 1,
						'tabs'              => 'all',
						'toolbar'           => 'basic',
						'media_upload'      => 0,
						'conditional_logic' => [ [ [
							'field'    => 'field_abp_display_alert',
							'operator' => '!=',
							'value'    => 'off',
						] ] ],
					],
					[
						'key'               => 'field_abp_button',
						'label'             => 'CTA Button',
						'name'              => 'alert_button',
						'type'              => 'link',
						'return_format'     => 'array',
						'conditional_logic' => [ [ [
							'field'    => 'field_abp_display_alert',
							'operator' => '!=',
							'value'    => 'off',
						] ] ],
					],
					[
						'key'               => 'field_abp_severity',
						'label'             => 'Alert Severity',
						'name'              => 'alert_severity',
						'type'              => 'select',
						'choices'           => [
							'normal' => 'Normal',
							'urgent' => 'Urgent',
						],
						'default_value'     => 'normal',
						'conditional_logic' => [ [ [
							'field'    => 'field_abp_display_alert',
							'operator' => '!=',
							'value'    => 'off',
						] ] ],
					],
					[
						'key'               => 'field_abp_frequency',
						'label'             => 'Display Frequency',
						'name'              => 'display_frequency',
						'type'              => 'select',
						'choices'           => [
							'always' => 'Every Page Load',
							'weekly' => 'Weekly',
							'once'   => 'Once',
						],
						'default_value'     => 'weekly',
						'conditional_logic' => [ [ [
							'field'    => 'field_abp_display_alert',
							'operator' => '!=',
							'value'    => 'off',
						] ] ],
					],
				],
			],
		],
		'location' => [ [ [
			'param'    => 'options_page',
			'operator' => '==',
			'value'    => 'alert-bar-settings',
		] ] ],
		'style'    => 'default',
	] );
} );

/**
 * Get active alerts — cached per request.
 */
function alert_banner_get_active_alerts() {
	static $alerts = null;
	if ( $alerts !== null ) return $alerts;

	$alerts = [];
	$today  = date( 'Ymd' );

	if ( ! have_rows( 'bar_alerts', 'option' ) ) return $alerts;

	while ( have_rows( 'bar_alerts', 'option' ) ) : the_row();
		$display = get_sub_field( 'display_alert' );
		$show    = false;

		if ( $display === 'on' ) {
			$show = true;
		} elseif ( $display === 'schedule' ) {
			$start = get_sub_field( 'alert_start_date' );
			$end   = get_sub_field( 'alert_end_date' );
			if ( $start && $end && $today >= $start && $today <= $end ) {
				$show = true;
			}
		}

		if ( $show ) {
			$content   = get_sub_field( 'alert_content' );
			$frequency = get_sub_field( 'display_frequency' );
			$severity  = get_sub_field( 'alert_severity' );

			$alerts[] = [
				'alert_content'     => $content,
				'display_frequency' => $frequency,
				'alert_id'          => substr( md5( $content . $frequency ), 0, 8 ),
				'severity_color'    => $severity === 'urgent' ? '#EF3340' : '#FFAE40',
				'aria'              => $severity === 'urgent' ? 'role="alert"' : 'role="status" aria-live="polite"',
				'alert_button'      => get_sub_field( 'alert_button' ),
			];
		}
	endwhile;

	return $alerts;
}

/**
 * Add body class when alerts are active.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( alert_banner_get_active_alerts() ) {
		$classes[] = 'has-notification-banner';
	}
	return $classes;
} );

/**
 * Render the alert banner after the opening body tag.
 */
add_action( 'wp_body_open', function () {
	$alerts = alert_banner_get_active_alerts();
	if ( empty( $alerts ) ) return;
	?>
	<aside id="custom-notification-tray" class="notification-banner" aria-label="Site Notifications" aria-hidden="true"><?php
			foreach ( $alerts as $alert ) : ?>
			<div class="site-banner js-banner-<?php echo esc_attr( $alert['display_frequency'] ); ?>" data-alert-id="alert-<?php echo esc_attr( $alert['alert_id'] ); ?>" <?php echo $alert['aria']; ?> style="background-color:<?php echo esc_attr( $alert['severity_color'] ); ?>;">
				<div class="alert-banner-inner">
					<div class="banner-content">
						<div><?php echo $alert['alert_content']; ?></div><?php
						if ( $alert['alert_button'] ) { ?>
						<a class="alert-banner-btn" href="<?php echo esc_url( $alert['alert_button']['url'] ); ?>" target="<?php echo esc_attr( $alert['alert_button']['target'] ); ?>"><?php echo esc_html( $alert['alert_button']['title'] ); ?></a><?php
						} ?>
					</div>
					<button class="alert-close" aria-label="Dismiss alert">
						<span aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M11 9.39628L1.60372 0L0 1.60372L9.39628 11L0 20.3963L1.60372 22L11 12.6037L20.3963 22L22 20.3963L12.6037 11L22 1.60372L20.3963 0L11 9.39628Z" fill="black"/>
							</svg>
						</span>
					</button>
				</div>
			</div><?php
			endforeach; ?>
	</aside>
	<?php
} );

/**
 * Enqueue plugin assets on the frontend.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! alert_banner_get_active_alerts() ) return;

	wp_enqueue_style(
		'alert-banner',
		ALERT_BANNER_URL . 'assets/css/alert-banner.css',
		[],
		ALERT_BANNER_VERSION
	);

	wp_enqueue_script(
		'alert-banner',
		ALERT_BANNER_URL . 'assets/js/alert-banner.js',
		[],
		ALERT_BANNER_VERSION,
		true
	);
} );
