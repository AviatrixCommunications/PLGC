<?php
/**
 * PLGC Social Icons Shortcode
 *
 * [plgc_social_icons]
 *
 * Outputs the same 6-icon row used in the footer, reading URLs from
 * WP Admin → PL Settings → Social Media.
 *
 * Matches Figma node 34039:627 — dark green (#2D5032) circle buttons,
 * white SVG icons, 53px height, 6 icons, gaps between.
 *
 * WCAG 2.1 AA:
 *   Each link has aria-label describing the platform and "opens in new tab".
 *   Icons are aria-hidden. Meets 44×44px touch target.
 *
 * @package PLGC
 * @since   1.5.6
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'plgc_social_icons', 'plgc_social_icons_shortcode' );

function plgc_social_icons_shortcode(): string {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	// Same field names as registered in inc/acf-options.php
	$networks = [
		'facebook'  => [ 'label' => 'Facebook',  'field' => 'plgc_social_facebook',  'svg' => 'facebook'  ],
		'instagram' => [ 'label' => 'Instagram',  'field' => 'plgc_social_instagram', 'svg' => 'instagram' ],
		'tiktok'    => [ 'label' => 'TikTok',     'field' => 'plgc_social_tiktok',    'svg' => 'tiktok'    ],
		'x'         => [ 'label' => 'X (Twitter)','field' => 'plgc_social_x',         'svg' => 'x'         ],
		'linkedin'  => [ 'label' => 'LinkedIn',   'field' => 'plgc_social_linkedin',  'svg' => 'linkedin'  ],
		'theknot'   => [ 'label' => 'The Knot',   'field' => 'plgc_social_theknot',   'svg' => 'theknot'   ],
	];

	$svgs = plgc_social_svgs();

	$items = '';
	foreach ( $networks as $key => $net ) {
		$url = get_field( $net['field'], 'option' );
		if ( empty( $url ) ) continue;

		$svg  = $svgs[ $net['svg'] ] ?? '';
		$items .= sprintf(
			'<li class="plgc-si__item">'
			. '<a href="%s" class="plgc-si__link plgc-si__link--%s" '
			. 'aria-label="%s (opens in new tab)" '
			. 'target="_blank" rel="noopener noreferrer">'
			. '<span aria-hidden="true">%s</span>'
			. '</a></li>',
			esc_url( $url ),
			esc_attr( $key ),
			esc_attr( $net['label'] . ' — Prairie Landing Golf Club' ),
			$svg
		);
	}

	if ( ! $items ) return '';

	return '<ul class="plgc-si" role="list" aria-label="Prairie Landing Golf Club on social media">'
	     . $items
	     . '</ul>';
}

/**
 * Inline SVGs for each platform. White paths, optimised, no IDs.
 * Sized at 22×22 viewBox to fit comfortably inside the 53px circle button.
 */
function plgc_social_svgs(): array {
	return [
		'facebook' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" aria-hidden="true" focusable="false"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.887v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>',

		'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" aria-hidden="true" focusable="false"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',

		'tiktok' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" aria-hidden="true" focusable="false"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.27 6.27 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.79a4.85 4.85 0 01-1.01-.1z"/></svg>',

		'x' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" aria-hidden="true" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.747l7.73-8.835L1.254 2.25H8.08l4.259 5.63L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>',

		'linkedin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" aria-hidden="true" focusable="false"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',

		'theknot' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 20" width="52" height="18" fill="#ffffff" aria-hidden="true" focusable="false"><text x="0" y="16" font-family="Georgia,serif" font-size="16" font-style="italic" letter-spacing="1">the knot</text></svg>',
	];
}
