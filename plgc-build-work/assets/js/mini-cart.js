/**
 * Floating Mini-Cart — client-side enhancements.
 *
 * WooCommerce updates the #plgc-mini-cart fragment via AJAX after add/remove.
 * This script watches for those DOM changes and triggers a pulse animation
 * so users notice the cart count changed.
 *
 * Also handles the edge case where WooCommerce replaces the element via
 * innerHTML — the MutationObserver catches the replacement and re-applies
 * the animation class.
 */
(function () {
    'use strict';

    /**
     * Trigger pulse animation on the mini-cart.
     * Briefly adds the --pulse modifier class, then removes it so it can
     * fire again on the next update.
     */
    function pulse() {
        var cart = document.getElementById('plgc-mini-cart');
        if (!cart) return;

        cart.classList.remove('plgc-mini-cart--pulse');
        // Force reflow so removing + re-adding the class triggers animation
        void cart.offsetWidth;
        cart.classList.add('plgc-mini-cart--pulse');

        // Clean up after animation completes
        setTimeout(function () {
            cart.classList.remove('plgc-mini-cart--pulse');
        }, 500);
    }

    /**
     * Listen for WooCommerce's fragment replacement events.
     *
     * WooCommerce fires these jQuery events after an AJAX cart update:
     *   - wc_fragments_refreshed
     *   - wc_fragments_loaded
     *   - added_to_cart
     *   - removed_from_cart
     *
     * We attach via jQuery if available, with a vanilla fallback observer.
     */
    function init() {
        // jQuery event approach (preferred — WooCommerce uses jQuery)
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on(
                'wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart',
                function () {
                    pulse();
                }
            );
        }

        // MutationObserver fallback — watches the body for #plgc-mini-cart changes
        // in case WooCommerce replaces the element outside the jQuery events
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var mutation = mutations[i];
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var node = mutation.addedNodes[j];
                        if (node.id === 'plgc-mini-cart' || (node.querySelector && node.querySelector('#plgc-mini-cart'))) {
                            pulse();
                            return;
                        }
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
