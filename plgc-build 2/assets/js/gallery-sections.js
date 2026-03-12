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

    // Wait until Swiper is available and DOM is ready
    document.addEventListener( 'DOMContentLoaded', function () {
        if ( typeof Swiper === 'undefined' ) {
            console.warn( 'PLGC Gallery: Swiper not loaded.' );
            return;
        }

        document.querySelectorAll( '.plgc-gs__slider' ).forEach( function ( el ) {
            initGallerySlider( el );
        } );
    } );


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
