/**
 * PLGC Featured Events Slider — event-slider.js
 *
 * Initialises Swiper on .plgc-es__swiper elements.
 * Reuses the same accessible dot pattern as gallery-sections.js.
 *
 * @package PLGC
 * @since   1.5.5
 */

( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        if ( typeof Swiper === 'undefined' ) {
            console.warn( 'PLGC Event Slider: Swiper not loaded.' );
            return;
        }

        document.querySelectorAll( '.plgc-es__swiper' ).forEach( function ( el ) {
            var slides  = el.querySelectorAll( '.plgc-es__slide' );
            var total   = slides.length;
            var liveEl  = el.closest( '.plgc-es' )
                            ? el.closest( '.plgc-es' ).querySelector( '.plgc-es__sr-live' )
                            : null;
            var dotsEl  = el.querySelector( '.plgc-es__dots' );

            if ( total <= 1 ) {
                if ( dotsEl ) dotsEl.style.display = 'none';
                return; // No Swiper needed for a single slide
            }

            var swiper = new Swiper( el, {
                loop:          false,
                slidesPerView: 1,
                spaceBetween:  0,
                grabCursor:    false,
                autoplay:      false,   // No autoplay — WCAG 2.2.2

                keyboard: {
                    enabled:        true,
                    onlyInViewport: true,
                },

                pagination: {
                    el:            '.plgc-es__dots',
                    clickable:     true,
                    bulletElement: 'button',
                    renderBullet: function ( index, className ) {
                        var label   = 'Go to event slide ' + ( index + 1 ) + ' of ' + total;
                        var current = index === 0 ? ' aria-current="true"' : '';
                        return (
                            '<button class="' + className + '"'
                            + ' aria-label="' + label + '"'
                            + current
                            + '></button>'
                        );
                    },
                },

                a11y: {
                    enabled:             true,
                    prevSlideMessage:    'Previous event',
                    nextSlideMessage:    'Next event',
                    firstSlideMessage:   'This is the first event',
                    lastSlideMessage:    'This is the last event',
                },

                on: {
                    slideChange: function () {
                        updateDotAria( this );
                        announceSlide( this, liveEl, total );
                    },
                },
            } );

            updateDotAria( swiper );
        } );
    } );


    function updateDotAria( swiper ) {
        var dots = swiper.el.querySelectorAll( '.plgc-es__dots button' );
        dots.forEach( function ( btn, i ) {
            if ( i === swiper.realIndex ) {
                btn.setAttribute( 'aria-current', 'true' );
            } else {
                btn.removeAttribute( 'aria-current' );
            }
        } );
    }


    function announceSlide( swiper, liveEl, total ) {
        if ( ! liveEl ) return;
        liveEl.textContent = '';
        setTimeout( function () {
            liveEl.textContent = 'Event ' + ( swiper.realIndex + 1 ) + ' of ' + total;
        }, 50 );
    }

}() );
