<?php
/**
 * WooCommerce & Events Calendar Accessibility
 *
 * Additional WCAG 2.1 AA enhancements specific to
 * WooCommerce and The Events Calendar Pro output.
 *
 * All hooks are gated behind class_exists() / function_exists() checks
 * so this file is completely safe to load when either plugin is inactive.
 *
 * @package PLGC
 */

defined( 'ABSPATH' ) || exit;

/**
 * ============================================================
 * WOOCOMMERCE ACCESSIBILITY
 * Only registered when WooCommerce is active.
 * ============================================================
 */
if ( class_exists( 'WooCommerce' ) ) {

    /**
     * Redirect "Return to shop" button to the custom gift cards page
     * instead of the default /shop/ WooCommerce archive.
     */
    add_filter( 'woocommerce_return_to_shop_redirect', function() {
        return home_url( '/online-merchandise/' );
    } );

    /**
     * Declare WooCommerce theme support with accessible defaults.
     */
    function plgc_woocommerce_support() {
        add_theme_support( 'woocommerce', [
            'product_grid' => [
                'default_rows'    => 3,
                'default_columns' => 3,
                'min_columns'     => 1,
                'max_columns'     => 4,
            ],
        ] );

        add_theme_support( 'wc-product-gallery-zoom' );
        add_theme_support( 'wc-product-gallery-lightbox' );
        add_theme_support( 'wc-product-gallery-slider' );
    }
    add_action( 'after_setup_theme', 'plgc_woocommerce_support' );

    /**
     * Add aria-label to WooCommerce "Add to Cart" buttons.
     * (WCAG 2.4.4 - Link Purpose)
     */
    function plgc_woo_cart_button_aria( $html, $product ) {
        $product_name = $product->get_name();
        $html = str_replace(
            'class="button',
            'aria-label="' . esc_attr( sprintf( 'Add %s to cart', $product_name ) ) . '" class="button',
            $html
        );
        return $html;
    }
    add_filter( 'woocommerce_loop_add_to_cart_link', 'plgc_woo_cart_button_aria', 10, 2 );

    /**
     * Remove the product link on cart line items — show plain text name only.
     * CSS pointer-events:none is cosmetic only; this removes the <a> from the DOM.
     * (No WCAG issue — product name in cart doesn't need to be a link.)
     */
    add_filter( 'woocommerce_cart_item_name', function( $name, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];
        if ( $product instanceof WC_Product ) {
            return esc_html( $product->get_name() );
        }
        // Fallback: strip any <a> tags WooCommerce may have already built
        return wp_strip_all_tags( $name );
    }, 10, 3 );

    /**
     * Add accessible notices for cart updates.
     * (WCAG 4.1.3 - Status Messages)
     */
    function plgc_woo_notice_a11y( $notice, $type ) {
        if ( strpos( $notice, 'role="alert"' ) === false && strpos( $notice, 'aria-live' ) === false ) {
            $role   = ( $type === 'error' ) ? 'role="alert"' : 'role="status" aria-live="polite"';
            $notice = str_replace(
                'class="woocommerce-',
                $role . ' class="woocommerce-',
                $notice
            );
        }
        return $notice;
    }
    add_filter( 'woocommerce_add_message', function ( $message ) { return plgc_woo_notice_a11y( $message, 'success' ); } );
    add_filter( 'woocommerce_add_error',   function ( $message ) { return plgc_woo_notice_a11y( $message, 'error' ); } );

    /**
     * Make WooCommerce product gallery keyboard accessible.
     * is_product() is only called inside this function, which is only
     * registered when WooCommerce is active — so it's always defined here.
     */
    function plgc_woo_gallery_a11y() {
        if ( ! is_product() ) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var thumbs = document.querySelectorAll('.woocommerce-product-gallery__image');
            thumbs.forEach(function (thumb, index) {
                thumb.setAttribute('tabindex', '0');
                thumb.setAttribute('role', 'button');
                thumb.setAttribute('aria-label', 'Product image ' + (index + 1));
                thumb.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.querySelector('a').click();
                    }
                });
            });
        });
        </script>
        <?php
    }
    add_action( 'wp_footer', 'plgc_woo_gallery_a11y' );

    /**
     * Ensure WooCommerce form fields have proper labels.
     * (WCAG 1.3.1 - Info and Relationships)
     */
    function plgc_woo_form_field_args( $args, $key, $value ) {
        if ( ! empty( $args['placeholder'] ) && empty( $args['label'] ) ) {
            $args['label'] = $args['placeholder'];
        }
        if ( ! empty( $args['required'] ) ) {
            $args['custom_attributes']['aria-required'] = 'true';
        }
        return $args;
    }
    add_filter( 'woocommerce_form_field_args', 'plgc_woo_form_field_args', 10, 3 );

    /**
     * Add unique aria-label to cart remove ("×") links.
     * (WCAG 2.4.4 - Link Purpose)
     *
     * WooCommerce's default remove link uses "×" as the visible text with
     * an aria-label of "Remove this item" — identical for every line item.
     * Screen reader users navigating by links can't tell them apart.
     * This replaces the aria-label with "Remove <Product Name> from cart".
     */
    add_filter( 'woocommerce_cart_item_remove_link', function ( $link, $cart_item_key ) {
        $cart = WC()->cart;
        if ( ! $cart ) {
            return $link;
        }

        $cart_item = $cart->get_cart_item( $cart_item_key );
        if ( empty( $cart_item['data'] ) ) {
            return $link;
        }

        $product_name = $cart_item['data']->get_name();
        $new_label    = sprintf(
            /* translators: %s: product name */
            esc_attr__( 'Remove %s from cart', 'plgc' ),
            $product_name
        );

        // Replace existing aria-label (WooCommerce sets "Remove this item")
        if ( preg_match( '/aria-label="[^"]*"/', $link ) ) {
            $link = preg_replace(
                '/aria-label="[^"]*"/',
                'aria-label="' . $new_label . '"',
                $link
            );
        } else {
            // No aria-label present — add one
            $link = str_replace( '<a ', '<a aria-label="' . $new_label . '" ', $link );
        }

        return $link;
    }, 10, 2 );

} // end if WooCommerce


/**
 * ============================================================
 * EVENTS CALENDAR ACCESSIBILITY
 * Only registered when The Events Calendar is active.
 * ============================================================
 */
if ( class_exists( 'Tribe__Events__Main' ) ) {

    /**
     * Add ARIA landmarks to Events Calendar views.
     * (WCAG 1.3.1 - Info and Relationships)
     */
    add_filter( 'tribe_events_before_html', function ( $html ) {
        return '<div role="region" aria-label="Events">' . $html;
    } );
    add_filter( 'tribe_events_after_html', function ( $html ) {
        return $html . '</div>';
    } );

    /**
     * Add screen-reader-friendly date formatting to abbreviated dates.
     */
    function plgc_events_date_a11y( $html ) {
        $html = preg_replace_callback(
            '/<abbr[^>]*class="[^"]*tribe-events-abbr[^"]*"[^>]*>(.*?)<\/abbr>/i',
            function ( $matches ) {
                if ( strpos( $matches[0], 'aria-label' ) !== false ) {
                    return $matches[0];
                }
                return str_replace(
                    '<abbr',
                    '<abbr aria-label="' . esc_attr( strip_tags( $matches[1] ) ) . '"',
                    $matches[0]
                );
            },
            $html
        );
        return $html;
    }
    add_filter( 'the_content', 'plgc_events_date_a11y', 25 );

    /**
     * Add focus styles and accessible touch targets to Events Calendar navigation.
     */
    function plgc_events_focus_styles() {
        if ( ! function_exists( 'tribe_is_event_query' ) || ! tribe_is_event_query() ) {
            return;
        }
        ?>
        <style>
            .tribe-events-nav-previous a:focus-visible,
            .tribe-events-nav-next a:focus-visible,
            .tribe-events-calendar td a:focus-visible,
            .tribe-events-sub-nav a:focus-visible {
                outline: var(--plgc-focus-width, 0.125rem) solid var(--plgc-focus-color, #567915);
                outline-offset: var(--plgc-focus-offset, 0.125rem);
            }
            .tribe-tickets .tribe-button:focus-visible,
            .tribe-tickets__buy:focus-visible {
                outline: var(--plgc-focus-width, 0.125rem) solid var(--plgc-focus-color, #567915);
                outline-offset: var(--plgc-focus-offset, 0.125rem);
            }
            .tribe-events-calendar td {
                min-height: 2.75rem;
            }
            .tribe-events-calendar td a {
                min-height: 2.75rem;
                min-width: 2.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        </style>
        <?php
    }
    add_action( 'wp_head', 'plgc_events_focus_styles' );

    /**
     * Add accessible labels to ticket quantity fields.
     */
    function plgc_ticket_quantity_label( $html ) {
        if ( strpos( $html, 'tribe-tickets-quantity' ) !== false ) {
            $html = str_replace(
                'class="tribe-tickets-quantity"',
                'class="tribe-tickets-quantity" aria-label="Ticket quantity"',
                $html
            );
        }
        return $html;
    }
    add_filter( 'tribe_tickets_ticket_quantity_field', 'plgc_ticket_quantity_label' );

} // end if Events Calendar
