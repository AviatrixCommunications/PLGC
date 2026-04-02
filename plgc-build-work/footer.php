<?php
/**
 * Prairie Landing Golf Club — Footer
 * @package PLGC
 */

defined( 'ABSPATH' ) || exit;

// ── Pull options ─────────────────────────────────────────────────────────────
$address       = plgc_option( 'plgc_address',        '2325 Longest Drive, West Chicago, IL 60185' );
$maps_api_key  = plgc_option( 'plgc_maps_api_key' );
$maps_place_id = plgc_option( 'plgc_maps_place_id' );
$phone_pro     = plgc_option( 'plgc_phone_pro_shop', '(630) 208-7600' );
$phone_events  = plgc_option( 'plgc_phone_events',   '(630) 208-7629' );
$copyright     = plgc_option( 'plgc_copyright_text', 'Prairie Landing Golf Club. All rights reserved.' );

// Footer logo
$footer_logo_data = plgc_option( 'plgc_footer_logo' );
if ( ! empty( $footer_logo_data['url'] ) ) {
    $logo_url = esc_url( $footer_logo_data['url'] );
    $logo_alt = esc_attr( $footer_logo_data['alt'] ?: 'Prairie Landing Golf Club' );
} else {
    $logo_id  = get_theme_mod( 'custom_logo' );
    $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
    $logo_alt = 'Prairie Landing Golf Club';
}

// Social platforms
$socials = [
    'facebook'  => [ 'url' => plgc_option( 'plgc_social_facebook' ),  'label' => 'Facebook' ],
    'instagram' => [ 'url' => plgc_option( 'plgc_social_instagram' ), 'label' => 'Instagram' ],
    'tiktok'    => [ 'url' => plgc_option( 'plgc_social_tiktok' ),    'label' => 'TikTok' ],
    'x'         => [ 'url' => plgc_option( 'plgc_social_x' ),         'label' => 'X (Twitter)' ],
    'linkedin'  => [ 'url' => plgc_option( 'plgc_social_linkedin' ),  'label' => 'LinkedIn' ],
    'theknot'   => [ 'url' => plgc_option( 'plgc_social_theknot' ),   'label' => 'The Knot' ],
];

// CTA from nav menu (shared with header)
$cta_item = plgc_get_nav_cta();
$cta_url  = $cta_item ? $cta_item['url']   : '#';
$cta_label= $cta_item ? $cta_item['label'] : 'Book a Tee Time';

// Google Maps Static image
$maps_img_url = '';
if ( $maps_api_key && $address ) {
    $enc = urlencode( $address );
    $maps_img_url = 'https://maps.googleapis.com/maps/api/staticmap?'
        . http_build_query( [
            'center'  => $address,
            'zoom'    => '15',
            'size'    => '600x280',
            'scale'   => '2',
            'markers' => 'color:0x567915|' . $address,
            'style'   => 'feature:poi.business|visibility:off',
            'key'     => $maps_api_key,
        ] );
}

$maps_link = $maps_place_id
    ? 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode( $maps_place_id )
    : 'https://maps.google.com/?q=' . rawurlencode( $address );
?>
</main><!-- /#main-content -->

<footer class="plgc-footer" aria-label="Site footer">

    <div class="plgc-footer__body">
        <div class="plgc-footer__inner">

            <!-- LEFT col: Logo / Contact / Social ─────────────────────────── -->
            <div class="plgc-footer__col plgc-footer__col--left">

                <?php if ( $logo_url ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"
                   class="plgc-footer__logo-wrap"
                   aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — Home">
                    <img src="<?php echo esc_url( $logo_url ); ?>"
                         alt="<?php echo $logo_alt; ?>"
                         class="plgc-footer__logo"
                         width="120" height="120"
                         loading="lazy" decoding="async">
                </a>
                <?php endif; ?>

                <address class="plgc-footer__contact">
                    <?php if ( $phone_pro ) : ?>
                    <p class="plgc-footer__phone">
                        <span class="plgc-footer__phone-label">Pro Shop: </span><a href="tel:+1<?php echo preg_replace( '/\D/', '', $phone_pro ); ?>" class="plgc-footer__phone-link"><?php echo esc_html( $phone_pro ); ?></a>
                    </p>
                    <?php endif; ?>
                    <?php if ( $phone_events ) : ?>
                    <p class="plgc-footer__phone plgc-footer__phone--events">
                        <span class="plgc-footer__phone-label">For Banquets, Weddings, Golf Outings, and Special Events: </span><a href="tel:+1<?php echo preg_replace( '/\D/', '', $phone_events ); ?>" class="plgc-footer__phone-link"><?php echo esc_html( $phone_events ); ?></a>
                    </p>
                    <?php endif; ?>
                </address>

                <?php $has_social = array_filter( $socials, fn( $s ) => ! empty( $s['url'] ) ); ?>
                <?php if ( $has_social ) : ?>
                <nav class="plgc-footer__social" aria-label="Social media links">
                    <ul class="plgc-footer__social-list" role="list">
                        <?php foreach ( $socials as $key => $social ) :
                            if ( empty( $social['url'] ) ) continue; ?>
                        <li class="plgc-footer__social-item">
                            <a href="<?php echo esc_url( $social['url'] ); ?>"
                               class="plgc-footer__social-link plgc-footer__social-link--<?php echo esc_attr( $key ); ?>"
                               aria-label="<?php echo esc_attr( $social['label'] . ' (opens in new tab)' ); ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?php echo plgc_social_icon( $key ); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            </div><!-- /.plgc-footer__col--left -->

            <!-- CENTER col: Address / Map / Weather ────────────────────────── -->
            <div class="plgc-footer__col plgc-footer__col--center">

                <p class="plgc-footer__address">
                    <span class="plgc-footer__address-label">Address: </span><a href="<?php echo esc_url( $maps_link ); ?>" class="plgc-footer__address-link" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $address . ' — opens in Google Maps' ); ?>"><?php echo esc_html( $address ); ?></a>
                </p>

                <?php if ( $maps_img_url ) : ?>
                <a href="<?php echo esc_url( $maps_link ); ?>"
                   class="plgc-footer__map-link"
                   target="_blank" rel="noopener noreferrer"
                   aria-label="View Prairie Landing Golf Club on Google Maps (opens in new tab)">
                    <img src="<?php echo esc_url( $maps_img_url ); ?>"
                         alt="Map showing Prairie Landing Golf Club at <?php echo esc_attr( $address ); ?>"
                         class="plgc-footer__map"
                         width="600" height="280"
                         loading="lazy" decoding="async">
                </a>
                <?php else : ?>
                <a href="<?php echo esc_url( $maps_link ); ?>"
                   class="plgc-footer__map-placeholder"
                   target="_blank" rel="noopener noreferrer">
                    View on Google Maps →
                </a>
                <?php endif; ?>

                <?php echo do_shortcode( '[plgc_weather compact="true" theme="dark"]' ); ?>

            </div><!-- /.plgc-footer__col--center -->

            <!-- RIGHT col: Quick Links / CTA ──────────────────────────────── -->
            <div class="plgc-footer__col plgc-footer__col--right">

                <?php if ( has_nav_menu( 'footer' ) ) : ?>
                <nav class="plgc-footer__nav" aria-label="Footer quick links">
                    <p class="plgc-footer__nav-heading" aria-hidden="true">Quick Links:</p>
                    <?php wp_nav_menu( [
                        'theme_location' => 'footer',
                        'menu_class'     => 'plgc-footer__nav-list',
                        'container'      => false,
                        'depth'          => 1,
                        'fallback_cb'    => false,
                        'items_wrap'     => '<ul id="%1$s" class="%2$s" role="list">%3$s</ul>',
                    ] ); ?>
                </nav>
                <?php endif; ?>

                <a href="<?php echo esc_url( $cta_url ); ?>"
                   class="plgc-btn plgc-btn--tee-time plgc-footer__cta"
                   <?php if ( $cta_item && $cta_item['target'] === '_blank' ) echo 'target="_blank" rel="noopener noreferrer"'; ?>
                   aria-label="Book a tee time at Prairie Landing Golf Club">
                    <?php echo esc_html( $cta_label ); ?>
                </a>

            </div><!-- /.plgc-footer__col--right -->

        </div><!-- /.plgc-footer__inner -->
    </div><!-- /.plgc-footer__body -->

    <!-- Sub-footer: Copyright / Legal links ────────────────────────────────── -->
    <div class="plgc-footer__sub">
        <div class="plgc-footer__sub-inner">

            <p class="plgc-footer__copyright">
                &copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $copyright ); ?>
            </p>

            <nav class="plgc-footer__legal-nav" aria-label="Legal links">
                <?php wp_nav_menu( [
                    'theme_location' => 'footer-legal',
                    'menu_class'     => 'plgc-footer__legal-list',
                    'container'      => false,
                    'depth'          => 1,
                    'walker'         => new PLGC_Legal_Nav_Walker(),
                    'fallback_cb'    => 'plgc_legal_nav_fallback',
                    'items_wrap'     => '<ul id="%1$s" class="%2$s" role="list">%3$s</ul>',
                ] ); ?>
            </nav>

        </div>
    </div>

</footer>

<?php wp_footer(); ?>
</body>
</html>

<?php
// ── Social Icon SVGs ─────────────────────────────────────────────────────────
function plgc_social_icon( string $platform ): string {
    // The Knot — use uploaded logo URL if set in PL Settings → Social Media
    if ( $platform === 'theknot' ) {
        $tk_url = plgc_option( 'plgc_theknot_logo' );
        if ( ! empty( $tk_url ) ) {
            return '<img src="' . esc_url( $tk_url ) . '" alt="The Knot" style="display:block;width:52px;height:auto;max-height:28px;object-fit:contain">';
        }
        // Fallback text if no logo URL set yet
        return '<span style="font-family:Georgia,serif;font-size:11px;font-weight:700;color:#fff;white-space:nowrap;line-height:1">the knot</span>';
    }

    $icons = [
        'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="white"><path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.532-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.514c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073Z"/></svg>',
        'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="white"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
        'tiktok'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="white"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
        'x'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="white"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.26 5.632 5.904-5.632zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        'linkedin'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="white"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
    ];
    return $icons[ $platform ] ?? '';
}
