/**
 * PLGC Events Carousel — event-slider.js
 *
 * Initialises Swiper on .plgc-es__swiper elements.
 * Handles both event slides and announcement slides (including video).
 *
 * Video handling (WCAG 2.1 AA):
 *   SC 2.2.2 — No autoplay: video plays only when user clicks the play button
 *   SC 2.1.1 — Play button is a native <button>, keyboard accessible
 *   SC 2.5.5 — Play button is 56×56px (48px on mobile), exceeds 44px min
 *   SC 4.1.2 — Play button has aria-label "Play video: [headline]"
 *   SC 2.4.7 — Focus ring on play button (CSS)
 *   prefers-reduced-motion — Play button hidden via CSS, poster stays static
 *
 * When the user swipes away from a video slide that's playing, the video
 * is paused (MP4) or destroyed (iframe embed) and the poster restored.
 *
 * @package PLGC
 * @since   1.7.52
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

            // ── Video: attach play-button handlers ──────────────────────────
            initVideoSlides( el );

            if ( total <= 1 ) {
                if ( dotsEl ) dotsEl.style.display = 'none';
                return; // No Swiper needed for a single slide
            }

            var swiper = new Swiper( el, {
                loop:          false,
                slidesPerView: 1,
                spaceBetween:  0,
                grabCursor:    true,
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
                        var label   = 'Go to slide ' + ( index + 1 ) + ' of ' + total;
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
                    prevSlideMessage:    'Previous slide',
                    nextSlideMessage:    'Next slide',
                    firstSlideMessage:   'This is the first slide',
                    lastSlideMessage:    'This is the last slide',
                },

                on: {
                    slideChange: function () {
                        updateDotAria( this );
                        announceSlide( this, liveEl, total );
                        // Stop any video playing on the previous slide
                        stopAllVideos( el );
                    },
                },
            } );

            updateDotAria( swiper );
        } );
    } );


    /* ─────────────────────────────────────────────────────────────────────
       DOT ARIA — keep aria-current in sync with active slide
       ───────────────────────────────────────────────────────────────────── */
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


    /* ─────────────────────────────────────────────────────────────────────
       LIVE REGION — screen reader announcement on slide change
       ───────────────────────────────────────────────────────────────────── */
    function announceSlide( swiper, liveEl, total ) {
        if ( ! liveEl ) return;
        liveEl.textContent = '';
        setTimeout( function () {
            liveEl.textContent = 'Slide ' + ( swiper.realIndex + 1 ) + ' of ' + total;
        }, 50 );
    }


    /* ─────────────────────────────────────────────────────────────────────
       VIDEO — Facade pattern: poster + play button → inject on demand

       Three video types:
         'mp4'     → <video> tag with src, controls, playsinline
         'youtube' → <iframe> with youtube-nocookie.com embed URL
         'vimeo'   → <iframe> with player.vimeo.com embed URL

       Respects prefers-reduced-motion: play button is hidden via CSS,
       so this code never fires for reduced-motion users.
       ───────────────────────────────────────────────────────────────────── */

    /**
     * Attach click handlers to all video play buttons within a slider.
     */
    function initVideoSlides( swiperEl ) {
        var playBtns = swiperEl.querySelectorAll( '.plgc-es__play-btn' );

        playBtns.forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var slide     = btn.closest( '.plgc-es__slide--video' );
                if ( ! slide ) return;

                var container = slide.querySelector( '.plgc-es__video-container' );
                var videoType = slide.getAttribute( 'data-video-type' ) || '';
                var videoSrc  = slide.getAttribute( 'data-video-src' )  || '';

                if ( ! container || ! videoSrc ) return;

                // Clear any existing video content
                container.innerHTML = '';

                if ( videoType === 'mp4' ) {
                    var video = document.createElement( 'video' );
                    video.src         = videoSrc;
                    video.autoplay    = true;
                    video.controls    = true;   // Native controls for pause/seek/volume
                    video.muted       = false;
                    video.playsInline = true;
                    video.setAttribute( 'playsinline', '' );
                    // When video ends, restore the poster state
                    video.addEventListener( 'ended', function () {
                        resetVideoSlide( slide );
                    } );
                    container.appendChild( video );

                } else if ( videoType === 'youtube' || videoType === 'vimeo' ) {
                    var iframe = document.createElement( 'iframe' );
                    iframe.src             = videoSrc;
                    iframe.allow           = 'autoplay; encrypted-media; picture-in-picture';
                    iframe.allowFullscreen = true;
                    iframe.setAttribute( 'allowfullscreen', '' );
                    iframe.setAttribute( 'frameborder', '0' );
                    iframe.title = btn.getAttribute( 'aria-label' ) || 'Video';
                    container.appendChild( iframe );
                }

                // Toggle playing state — hides poster, play btn, overlay
                slide.classList.add( 'plgc-es__slide--video-playing' );
                container.setAttribute( 'aria-hidden', 'false' );
            } );
        } );
    }


    /**
     * Stop all playing videos within a slider container.
     * Called on slide change so leaving a video slide pauses/destroys it.
     */
    function stopAllVideos( swiperEl ) {
        var playingSlides = swiperEl.querySelectorAll( '.plgc-es__slide--video-playing' );

        playingSlides.forEach( function ( slide ) {
            resetVideoSlide( slide );
        } );
    }


    /**
     * Reset a video slide to its poster state.
     * Pauses <video> or destroys <iframe>, removes playing class.
     */
    function resetVideoSlide( slide ) {
        var container = slide.querySelector( '.plgc-es__video-container' );
        if ( ! container ) return;

        // Pause native video
        var video = container.querySelector( 'video' );
        if ( video ) {
            video.pause();
            video.removeAttribute( 'src' );
            video.load(); // Release the media resource
        }

        // Destroy iframe (no reliable pause API for cross-origin embeds)
        var iframe = container.querySelector( 'iframe' );
        if ( iframe ) {
            iframe.src = 'about:blank';
        }

        // Clear container
        container.innerHTML = '';
        container.setAttribute( 'aria-hidden', 'true' );

        // Restore poster state
        slide.classList.remove( 'plgc-es__slide--video-playing' );
    }

}() );
