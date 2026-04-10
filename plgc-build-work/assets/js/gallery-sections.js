/**
 * PLGC Gallery Sections — gallery-sections.js
 *
 * Initialises Swiper 11 on each .plgc-gs__slider element.
 *
 * WCAG 2.1 AA compliance:
 *   - No autoplay (eliminates WCAG 2.2.2 pause/stop/hide requirement)
 *   - Keyboard navigation:  ← → cycle slides,  Tab moves through dots
 *   - Each dot is rendered as a <button> with aria-label="Go to slide X of Y"
 *   - Active dot carries aria-current="true"
 *   - Screen-reader live region (.plgc-gs__sr-live) announces slide changes
 *   - Swiper's built-in keyboard module handles ← → natively
 *
 * @package PLGC
 * @since   1.5.0
 */

( function () {
    'use strict';

    // WordPress footer scripts execute AFTER DOMContentLoaded has already fired,
    // so a plain addEventListener( 'DOMContentLoaded' ) listener is never called.
    // We mirror the same three-layer init pattern used in gallery-widgets.js:
    //   Layer 1 — immediate if DOM is ready, otherwise wait for DOMContentLoaded
    //   Layer 2 — window.load (Elementor may render widgets after DOMContentLoaded)
    //   Layer 3 — Elementor frontend hooks (widget injected post-load)

    /**
     * Top-level init: queries all sliders on the page.
     * Has-init guard on each element prevents double-initialisation.
     */
    function init() {
        if ( typeof Swiper === 'undefined' ) {
            // Swiper CDN script hasn't executed yet — retry once after load
            window.addEventListener( 'load', initOnce );
            return;
        }
        document.querySelectorAll( '.plgc-gs__slider:not([data-plgc-gs-init])' ).forEach( function ( el ) {
            el.dataset.plgcGsInit = '1';
            initGallerySlider( el );
        } );
    }

    var _loadFired = false;
    function initOnce() {
        if ( _loadFired ) return;
        _loadFired = true;
        init();
    }

    // Layer 1: immediate / DOMContentLoaded
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Layer 2: window load
    window.addEventListener( 'load', initOnce );


    /**
     * Initialise one gallery slider.
     *
     * @param {HTMLElement} el  The .plgc-gs__slider element.
     */
    function initGallerySlider( el ) {
        var slides   = el.querySelectorAll( '.plgc-gs__slide' );
        var total    = slides.length;
        var liveEl   = el.querySelector( '.plgc-gs__sr-live' );
        var dotsEl   = el.querySelector( '.plgc-gs__dots' );

        // Only 1 slide — no Swiper needed, hide pagination
        if ( total <= 1 ) {
            if ( dotsEl ) dotsEl.style.display = 'none';
            return;
        }

        var swiper = new Swiper( el, {
            // Layout
            loop:           false,
            slidesPerView:  1,
            spaceBetween:   0,
            grabCursor:     true,   // shows grab cursor; enables click-drag on desktop

            // simulateTouch is true by default — allows mouse drag in addition to touch.
            // cssMode was previously set here to work around an iOS Safari bug where
            // GPU-composited (transform) children inside overflow:hidden could go
            // invisible on real devices. That bug affects older iOS (pre-2022). Removing
            // cssMode restores Swiper's native pointer-event handling, which is required
            // for desktop mouse drag. Modern iOS handles this correctly; if the invisible-
            // content bug resurfaces on a specific device, re-enable cssMode and add a
            // custom pointer-drag shim for desktop.
            cssMode:        false,

            // NO autoplay (WCAG 2.2.2)
            autoplay: false,

            // Accessible keyboard navigation (← → arrow keys)
            keyboard: {
                enabled:    true,
                onlyInViewport: true,
            },

            // Render dots as <button> elements for keyboard access
            pagination: {
                el:              '.plgc-gs__dots',
                clickable:       true,
                bulletElement:   'button',

                // Build each dot with proper aria attributes
                renderBullet: function ( index, className ) {
                    var label = 'Go to slide ' + ( index + 1 ) + ' of ' + total;
                    var current = index === 0 ? ' aria-current="true"' : '';
                    return (
                        '<button class="' + className + '"'
                        + ' aria-label="' + label + '"'
                        + current
                        + '></button>'
                    );
                },
            },

            // Accessibility module (adds role/aria to slides automatically)
            a11y: {
                enabled:                true,
                prevSlideMessage:       'Previous slide',
                nextSlideMessage:       'Next slide',
                firstSlideMessage:      'This is the first slide',
                lastSlideMessage:       'This is the last slide',
                paginationBulletMessage: 'Go to slide {{index}}',
            },

            // Events
            on: {
                slideChange: function () {
                    updateDotAria( this );
                    announceSlide( this, liveEl, total );
                },
            },
        } );

        // Run once on init so first dot has aria-current
        updateDotAria( swiper );
    }


    /**
     * Update aria-current on pagination dots after a slide change.
     *
     * @param {Swiper} swiper
     */
    function updateDotAria( swiper ) {
        var dots = swiper.el.querySelectorAll( '.plgc-gs__dots button' );
        dots.forEach( function ( btn, i ) {
            if ( i === swiper.realIndex ) {
                btn.setAttribute( 'aria-current', 'true' );
            } else {
                btn.removeAttribute( 'aria-current' );
            }
        } );
    }


    /**
     * Announce the current slide to screen readers via the live region.
     *
     * @param {Swiper}      swiper
     * @param {HTMLElement} liveEl
     * @param {number}      total
     */
    function announceSlide( swiper, liveEl, total ) {
        if ( ! liveEl ) return;
        var current = swiper.realIndex + 1;
        // Clear first so repeated same-index announcements still fire
        liveEl.textContent = '';
        // Small timeout ensures the clear is rendered before the update
        setTimeout( function () {
            liveEl.textContent = 'Slide ' + current + ' of ' + total;
        }, 50 );
    }


    /* ─────────────────────────────────────────────────────────────────────
       GALLERY TOGGLE — "View Full Image" / "Show Details"

       Desktop only. Collapses the card body+CTA so more of the gallery
       image is visible. On tablet/mobile the toggle button is display:none
       and the collapsible region has no max-height constraint, so this JS
       is harmless if the button doesn't exist.

       WCAG 2.1 AA:
         SC 4.1.2 — aria-expanded toggles between "true" and "false"
         SC 2.1.1 — Native <button> fires on Enter/Space automatically
         SC 4.1.3 — Status change announced via aria-expanded state
         SC 2.4.7 — Focus ring defined in CSS
       ─────────────────────────────────────────────────────────────────── */
    function initGalleryToggles() {
        document.querySelectorAll( '.plgc-gs__toggle' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var card        = btn.closest( '.plgc-gs__card' );
                var expanded    = btn.getAttribute( 'aria-expanded' ) === 'true';
                var labelEl     = btn.querySelector( '.plgc-gs__toggle-label' );
                var collapsible = card ? card.querySelector( '.plgc-gs__collapsible' ) : null;

                // Toggle state
                var newExpanded = ! expanded;
                btn.setAttribute( 'aria-expanded', newExpanded ? 'true' : 'false' );

                // Toggle card class
                if ( card ) {
                    card.classList.toggle( 'plgc-gs__card--collapsed', ! newExpanded );
                }

                // Swap button label text
                if ( labelEl ) {
                    labelEl.textContent = newExpanded
                        ? ( labelEl.dataset.expanded || 'View Full Image' )
                        : ( labelEl.dataset.collapsed || 'Show Details' );
                }

                // When expanding, restore visibility after the CSS transition starts
                if ( newExpanded && collapsible ) {
                    collapsible.style.visibility = 'visible';
                }
            } );
        } );
    }

    // Run toggle init alongside the slider init
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initGalleryToggles );
    } else {
        initGalleryToggles();
    }

}() );
