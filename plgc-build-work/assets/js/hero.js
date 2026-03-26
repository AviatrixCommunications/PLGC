/**
 * PLGC Hero Widget — JS v1.6.13
 *
 * Handles:
 *   1. Slideshow: dot nav, auto-advance, Ken Burns, pause/play, swipe
 *   2. Self-hosted video: pause/play button, prefers-reduced-motion guard
 *   3. YouTube background: postMessage pause/play, prefers-reduced-motion guard
 *
 * WCAG 2.1 AA:
 *   SC 2.2.2  — pause/play required for auto-advancing content
 *   SC 2.5.5  — 44×44px pause button and dots
 *   prefers-reduced-motion — no autoplay, no Ken Burns
 *
 * YouTube notes:
 *   - We use the postMessage interface (enablejsapi=1) instead of the
 *     YT IFrame API library to avoid loading an extra script on every page.
 *   - Commands: {"event":"command","func":"pauseVideo","args":""}
 *               {"event":"command","func":"playVideo","args":""}
 *   - YouTube provides its own controls (controls=1) which satisfies WCAG
 *     SC 2.2.2. Our button adds keyboard-accessible control outside the iframe.
 *
 * @package PLGC
 * @since   1.6.13
 */

( function () {
    'use strict';

    var prefersReducedMotion = window.matchMedia &&
        window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;


    /* ══════════════════════════════════════════════════════════════════════
       1. HERO SLIDESHOW
       ══════════════════════════════════════════════════════════════════════ */
    function initHeroSlideshows() {
        document.querySelectorAll( '[data-plgc-hero-slides]' ).forEach( function ( wrapper ) {
            var slides      = Array.from( wrapper.querySelectorAll( '[data-plgc-hero-slide]' ) );
            var dots        = Array.from( wrapper.querySelectorAll( '[data-plgc-hero-dot]' ) );
            var liveRegion  = wrapper.querySelector( '.plgc-hero__dots-live' );
            var pauseBtn    = wrapper.querySelector( '[data-plgc-hero-pause]' );
            var progressEl  = wrapper.querySelector( '[data-plgc-hero-progress]' );
            var total       = slides.length;

            if ( total < 2 ) return;

            var current      = 0;
            var timer        = null;
            var isUserPaused = false;
            var intervalSec  = parseInt( wrapper.getAttribute( 'data-interval' ) || '6', 10 );
            var kenBurns     = wrapper.getAttribute( 'data-ken-burns' ) === '1' && ! prefersReducedMotion;

            if ( kenBurns ) wrapper.classList.add( 'is-ken-burns' );

            /* ── Navigate ─────────────────────────────────────────────── */
            function goTo( index ) {
                index = ( ( index % total ) + total ) % total;

                slides[ current ].classList.remove( 'is-active' );
                slides[ current ].setAttribute( 'aria-hidden', 'true' );
                if ( dots[ current ] ) {
                    dots[ current ].classList.remove( 'is-active' );
                    dots[ current ].setAttribute( 'aria-pressed', 'false' );
                }

                current = index;
                slides[ current ].classList.add( 'is-active' );
                slides[ current ].setAttribute( 'aria-hidden', 'false' );
                if ( dots[ current ] ) {
                    dots[ current ].classList.add( 'is-active' );
                    dots[ current ].setAttribute( 'aria-pressed', 'true' );
                }

                if ( liveRegion ) {
                    liveRegion.textContent = '';
                    setTimeout( function () {
                        liveRegion.textContent = 'Slide ' + ( current + 1 ) + ' of ' + total;
                    }, 50 );
                }

                resetProgress();
                if ( ! isUserPaused ) animateProgress();
            }

            /* ── Timer ────────────────────────────────────────────────── */
            function startTimer() {
                if ( isUserPaused || prefersReducedMotion ) return;
                stopTimer();
                timer = setTimeout( function () {
                    goTo( current + 1 );
                    startTimer();
                }, intervalSec * 1000 );
            }
            function stopTimer() {
                if ( timer ) { clearTimeout( timer ); timer = null; }
            }

            function resetProgress() {
                if ( ! progressEl ) return;
                progressEl.classList.remove( 'is-animating' );
                progressEl.style.width = '0%';
                progressEl.style.transitionDuration = '';
            }
            function animateProgress() {
                if ( ! progressEl || prefersReducedMotion ) return;
                void progressEl.offsetWidth;
                progressEl.style.transitionDuration = intervalSec + 's';
                progressEl.classList.add( 'is-animating' );
                progressEl.style.width = '100%';
            }

            /* ── Pause button ─────────────────────────────────────────── */
            if ( pauseBtn ) {
                var iconPause = pauseBtn.querySelector( '.plgc-hero__pause-icon--pause' );
                var iconPlay  = pauseBtn.querySelector( '.plgc-hero__pause-icon--play' );

                pauseBtn.addEventListener( 'click', function () {
                    isUserPaused = ! isUserPaused;
                    if ( isUserPaused ) {
                        stopTimer(); resetProgress();
                        pauseBtn.setAttribute( 'aria-label', 'Play slideshow' );
                        if ( iconPause ) iconPause.style.display = 'none';
                        if ( iconPlay )  iconPlay.style.display  = '';
                    } else {
                        pauseBtn.setAttribute( 'aria-label', 'Pause slideshow' );
                        if ( iconPause ) iconPause.style.display = '';
                        if ( iconPlay )  iconPlay.style.display  = 'none';
                        startTimer(); animateProgress();
                    }
                } );
            }

            /* ── Dot clicks ───────────────────────────────────────────── */
            dots.forEach( function ( dot ) {
                dot.addEventListener( 'click', function () {
                    stopTimer();
                    goTo( parseInt( dot.getAttribute( 'data-plgc-hero-dot' ), 10 ) );
                    if ( ! isUserPaused ) startTimer();
                } );
            } );

            /* ── Keyboard arrows ─────────────────────────────────────── */
            wrapper.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'ArrowLeft' )  { stopTimer(); goTo( current - 1 ); if ( ! isUserPaused ) startTimer(); e.preventDefault(); }
                if ( e.key === 'ArrowRight' ) { stopTimer(); goTo( current + 1 ); if ( ! isUserPaused ) startTimer(); e.preventDefault(); }
            } );

            /* ── Touch / pointer swipe ────────────────────────────────── */
            addPointerSwipe( wrapper, function ( dir ) {
                stopTimer();
                goTo( current + dir );
                if ( ! isUserPaused ) startTimer();
            } );

            /* ── Start ────────────────────────────────────────────────── */
            if ( ! prefersReducedMotion ) {
                startTimer();
                animateProgress();
            }
        } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       2. SELF-HOSTED VIDEO PAUSE
       ══════════════════════════════════════════════════════════════════════ */
    function initHeroVideos() {
        document.querySelectorAll( '[data-plgc-hero-vidpause]' ).forEach( function ( btn ) {
            var section = btn.closest( '.plgc-hero' );
            var video   = section ? section.querySelector( '[data-plgc-hero-video]' ) : null;
            if ( ! video ) return;

            var iconPause = btn.querySelector( '.plgc-hero__pause-icon--pause' );
            var iconPlay  = btn.querySelector( '.plgc-hero__pause-icon--play' );

            if ( prefersReducedMotion ) {
                video.pause();
                btn.setAttribute( 'aria-label', 'Play video' );
                if ( iconPause ) iconPause.style.display = 'none';
                if ( iconPlay )  iconPlay.style.display  = '';
            }

            btn.addEventListener( 'click', function () {
                if ( video.paused ) {
                    video.play();
                    btn.setAttribute( 'aria-label', 'Pause video' );
                    if ( iconPause ) iconPause.style.display = '';
                    if ( iconPlay )  iconPlay.style.display  = 'none';
                } else {
                    video.pause();
                    btn.setAttribute( 'aria-label', 'Play video' );
                    if ( iconPause ) iconPause.style.display = 'none';
                    if ( iconPlay )  iconPlay.style.display  = '';
                }
            } );
        } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       3. YOUTUBE BACKGROUND VIDEO PAUSE
       Communicates with the YouTube iframe via postMessage.
       Commands: pauseVideo / playVideo
       This works because we added enablejsapi=1 to the embed URL.
       ══════════════════════════════════════════════════════════════════════ */
    function initHeroYouTube() {
        document.querySelectorAll( '[data-plgc-hero-ytpause]' ).forEach( function ( btn ) {
            var iframeId = btn.getAttribute( 'data-yt-target' );
            var iframe   = iframeId ? document.getElementById( iframeId ) : null;
            if ( ! iframe ) return;

            var iconPause = btn.querySelector( '.plgc-hero__pause-icon--pause' );
            var iconPlay  = btn.querySelector( '.plgc-hero__pause-icon--play' );
            var playing   = true; // optimistic — autoplay is on

            function ytCommand( cmd ) {
                try {
                    iframe.contentWindow.postMessage(
                        JSON.stringify( { event: 'command', func: cmd, args: '' } ),
                        '*'
                    );
                } catch (x) {}
            }

            // prefers-reduced-motion: pause immediately on load
            if ( prefersReducedMotion ) {
                // Wait a beat for the iframe src to load
                setTimeout( function () { ytCommand( 'pauseVideo' ); }, 1500 );
                playing = false;
                btn.setAttribute( 'aria-label', 'Play video' );
                if ( iconPause ) iconPause.style.display = 'none';
                if ( iconPlay )  iconPlay.style.display  = '';
            }

            btn.addEventListener( 'click', function () {
                if ( playing ) {
                    ytCommand( 'pauseVideo' );
                    playing = false;
                    btn.setAttribute( 'aria-label', 'Play video' );
                    if ( iconPause ) iconPause.style.display = 'none';
                    if ( iconPlay )  iconPlay.style.display  = '';
                } else {
                    ytCommand( 'playVideo' );
                    playing = true;
                    btn.setAttribute( 'aria-label', 'Pause video' );
                    if ( iconPause ) iconPause.style.display = '';
                    if ( iconPlay )  iconPlay.style.display  = 'none';
                }
            } );
        } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       SHARED: POINTER SWIPE — NO setPointerCapture
       ══════════════════════════════════════════════════════════════════════ */
    function addPointerSwipe( el, callback ) {
        var startX = null, startY = null;
        var MIN_DRAG = 50, MAX_VERT = 80;

        el.addEventListener( 'pointerdown', function ( e ) {
            if ( e.button !== 0 && e.pointerType === 'mouse' ) return;
            startX = e.clientX; startY = e.clientY;
        }, { passive: true } );

        document.addEventListener( 'pointerup', function ( e ) {
            if ( startX === null ) return;
            var dx = e.clientX - startX, dy = e.clientY - startY;
            startX = null; startY = null;
            if ( Math.abs( dx ) < MIN_DRAG || Math.abs( dy ) > MAX_VERT ) return;
            callback( dx < 0 ? 1 : -1 );
        }, { passive: true } );

        document.addEventListener( 'pointercancel', function () {
            startX = null; startY = null;
        }, { passive: true } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       INIT
       ══════════════════════════════════════════════════════════════════════ */
    function init() {
        initHeroSlideshows();
        initHeroVideos();
        initHeroYouTube();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Layer 1
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Layer 2
    window.addEventListener( 'load', init );

    // Layer 3: Elementor hooks — try/catch so errors never surface
    function tryElementorHooks() {
        try {
            if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
                window.elementorFrontend.hooks.addAction( 'frontend/element_ready/plgc_hero/default', init );
            }
        } catch (e) {}
    }
    tryElementorHooks();
    window.addEventListener( 'elementor/frontend/init', tryElementorHooks );

}() );
