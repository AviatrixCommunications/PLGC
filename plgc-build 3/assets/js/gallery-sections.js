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
            grabCursor:     false,

            // cssMode: uses native overflow-x scroll + scrollLeft instead of
            // CSS translate3d transforms. This prevents the iOS Safari invisible-
            // content bug where a GPU-composited (transform) child inside an
            // overflow:hidden parent renders nothing on real iOS devices.
            // Trade-off: no crossfade/cube effects (not used here), and touch
            // swipe uses native momentum scrolling (actually better on iOS).
            cssMode:        true,

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

}() );
