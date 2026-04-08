<?php
/**
 * PLGC Floating Mini-Cart
 *
 * Renders a fixed-position cart icon in the lower-left corner when
 * the WooCommerce cart has items. Updates dynamically via AJAX
 * fragments so it reflects add/remove actions without a page reload.
 *
 * WCAG 2.1 AA compliance:
 *   - 44×44 px minimum touch target (SC 2.5.5)
 *   - Descriptive aria-label with live item count (SC 4.1.2, 4.1.3)
 *   - aria-live="polite" so screen readers announce count changes
 *   - Sufficient contrast: dark green (#233C26) on white badge
 *   - Focus-visible ring meets 3:1 contrast (SC 2.4.7)
 *   - prefers-reduced-motion: disables bounce/pulse animations
 *
 * @package PLGC
 * @since   1.7.51
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// 1. RENDER THE FLOATING CART ICON
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_footer', 'plgc_render_mini_cart', 20 );

function plgc_render_mini_cart(): void {
    // Don't show on cart or checkout pages (user is already there)
    if ( is_cart() || is_checkout() ) {
        return;
    }

    // Don't render if WooCommerce isn't active
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }

    $count    = WC()->cart->get_cart_contents_count();
    $cart_url = wc_get_cart_url();
    $hidden   = $count === 0 ? ' plgc-mini-cart--hidden' : '';

    // aria-label is dynamically updated via AJAX fragment
    $aria_label = sprintf(
        _n( 'View cart, %d item', 'View cart, %d items', $count, 'plgc' ),
        $count
    );
    ?>
    <div id="plgc-mini-cart" class="plgc-mini-cart<?php echo esc_attr( $hidden ); ?>" aria-live="polite">
        <a href="<?php echo esc_url( $cart_url ); ?>"
           class="plgc-mini-cart__link"
           aria-label="<?php echo esc_attr( $aria_label ); ?>">
            <svg class="plgc-mini-cart__icon" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 18C5.9 18 5.01 18.9 5.01 20C5.01 21.1 5.9 22 7 22C8.1 22 9 21.1 9 20C9 18.9 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5.48C20.96 5.34 21 5.17 21 5C21 4.45 20.55 4 20 4H5.21L4.27 2H1ZM17 18C15.9 18 15.01 18.9 15.01 20C15.01 21.1 15.9 22 17 22C18.1 22 19 21.1 19 20C19 18.9 18.1 18 17 18Z" fill="currentColor"/>
            </svg>
            <span class="plgc-mini-cart__count" aria-hidden="true"><?php echo esc_html( $count ); ?></span>
        </a>
    </div>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. REGISTER AS WOOCOMMERCE AJAX FRAGMENT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * WooCommerce's add_to_cart_fragments filter lets us update HTML fragments
 * after any AJAX cart operation (add, remove, update qty). We replace the
 * entire #plgc-mini-cart container so the count and visibility stay in sync.
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'plgc_mini_cart_fragment' );

function plgc_mini_cart_fragment( array $fragments ): array {
    if ( ! WC()->cart ) {
        return $fragments;
    }

    $count    = WC()->cart->get_cart_contents_count();
    $cart_url = wc_get_cart_url();
    $hidden   = $count === 0 ? ' plgc-mini-cart--hidden' : '';

    $aria_label = sprintf(
        _n( 'View cart, %d item', 'View cart, %d items', $count, 'plgc' ),
        $count
    );

    ob_start();
    ?>
    <div id="plgc-mini-cart" class="plgc-mini-cart<?php echo esc_attr( $hidden ); ?>" aria-live="polite">
        <a href="<?php echo esc_url( $cart_url ); ?>"
           class="plgc-mini-cart__link"
           aria-label="<?php echo esc_attr( $aria_label ); ?>">
            <svg class="plgc-mini-cart__icon" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 18C5.9 18 5.01 18.9 5.01 20C5.01 21.1 5.9 22 7 22C8.1 22 9 21.1 9 20C9 18.9 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5.48C20.96 5.34 21 5.17 21 5C21 4.45 20.55 4 20 4H5.21L4.27 2H1ZM17 18C15.9 18 15.01 18.9 15.01 20C15.01 21.1 15.9 22 17 22C18.1 22 19 21.1 19 20C19 18.9 18.1 18 17 18Z" fill="currentColor"/>
            </svg>
            <span class="plgc-mini-cart__count" aria-hidden="true"><?php echo esc_html( $count ); ?></span>
        </a>
    </div>
    <?php
    $fragments['#plgc-mini-cart'] = ob_get_clean();

    return $fragments;
}


// ─────────────────────────────────────────────────────────────────────────────
// 3. ENQUEUE ASSETS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'plgc_mini_cart_assets' );

function plgc_mini_cart_assets(): void {
    // Only load when WooCommerce is active and we're not on cart/checkout
    if ( ! function_exists( 'WC' ) || is_cart() || is_checkout() ) {
        return;
    }

    wp_enqueue_style(
        'plgc-mini-cart',
        PLGC_URI . '/assets/css/mini-cart.css',
        [],
        PLGC_VERSION
    );

    wp_enqueue_script(
        'plgc-mini-cart',
        PLGC_URI . '/assets/js/mini-cart.js',
        [],
        PLGC_VERSION,
        true
    );
}
