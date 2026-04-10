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
       LIGHTBOX — Full-screen image viewer for gallery sections

       Opens when user clicks the expand button or directly on a gallery
       image. Shows the current slide image at full size with prev/next
       navigation, close button, and keyboard controls.

       WCAG 2.1 AA:
         SC 2.1.1 — All controls are native <button>s
         SC 2.1.2 — Escape key closes, no keyboard trap
         SC 2.4.3 — Focus trapped inside dialog while open
         SC 2.4.7 — Visible focus rings on all controls
         SC 2.5.5 — All buttons ≥ 48×48px
         SC 4.1.2 — role="dialog" aria-modal="true" aria-label
         SC 1.1.1 — Image has meaningful alt text
       ─────────────────────────────────────────────────────────────────── */

    var lightboxEl    = null;
    var lbImgEl       = null;
    var lbCloseEl     = null;
    var lbPrevEl      = null;
    var lbNextEl      = null;
    var lbCounterEl   = null;
    var lbImages      = [];
    var lbIndex       = 0;
    var lbTriggerEl   = null;  // element that opened the lightbox (return focus on close)

    /**
     * Create the lightbox DOM once and append to <body>.
     */
    function ensureLightbox() {
        if ( lightboxEl ) return;

        lightboxEl = document.createElement( 'div' );
        lightboxEl.className = 'plgc-gs-lightbox';
        lightboxEl.setAttribute( 'role', 'dialog' );
        lightboxEl.setAttribute( 'aria-modal', 'true' );
        lightboxEl.setAttribute( 'aria-label', 'Gallery image viewer' );
        lightboxEl.setAttribute( 'tabindex', '-1' );

        lightboxEl.innerHTML =
            '<button class="plgc-gs-lightbox__close" type="button" aria-label="Close gallery viewer">'
            + '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4L16 16M16 4L4 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            + '</button>'
            + '<button class="plgc-gs-lightbox__prev" type="button" aria-label="Previous image">'
            + '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            + '</button>'
            + '<div class="plgc-gs-lightbox__img-wrap">'
            + '<img class="plgc-gs-lightbox__img" src="" alt="">'
            + '</div>'
            + '<button class="plgc-gs-lightbox__next" type="button" aria-label="Next image">'
            + '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3L11 8L6 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            + '</button>'
            + '<div class="plgc-gs-lightbox__counter" aria-live="polite"></div>';

        document.body.appendChild( lightboxEl );

        lbImgEl     = lightboxEl.querySelector( '.plgc-gs-lightbox__img' );
        lbCloseEl   = lightboxEl.querySelector( '.plgc-gs-lightbox__close' );
        lbPrevEl    = lightboxEl.querySelector( '.plgc-gs-lightbox__prev' );
        lbNextEl    = lightboxEl.querySelector( '.plgc-gs-lightbox__next' );
        lbCounterEl = lightboxEl.querySelector( '.plgc-gs-lightbox__counter' );

        // Events
        lbCloseEl.addEventListener( 'click', closeLightbox );
        lbPrevEl.addEventListener( 'click', function () { navigateLightbox( -1 ); } );
        lbNextEl.addEventListener( 'click', function () { navigateLightbox( 1 ); } );

        // Click backdrop to close (not if clicking image/buttons)
        lightboxEl.addEventListener( 'click', function ( e ) {
            if ( e.target === lightboxEl ) {
                closeLightbox();
            }
        } );

        // Keyboard — Escape, arrows, Tab trap
        lightboxEl.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) {
                e.preventDefault();
                closeLightbox();
            } else if ( e.key === 'ArrowLeft' ) {
                e.preventDefault();
                navigateLightbox( -1 );
            } else if ( e.key === 'ArrowRight' ) {
                e.preventDefault();
                navigateLightbox( 1 );
            } else if ( e.key === 'Tab' ) {
                trapFocus( e );
            }
        } );
    }


    /**
     * Open lightbox with a set of images starting at a given index.
     *
     * @param {Array}       images     [{url, alt}, ...]
     * @param {number}      startIndex
     * @param {HTMLElement}  triggerEl  Element to return focus to on close
     */
    function openLightbox( images, startIndex, triggerEl ) {
        ensureLightbox();

        lbImages    = images;
        lbIndex     = startIndex || 0;
        lbTriggerEl = triggerEl || null;

        // Single image mode — hide nav
        lightboxEl.classList.toggle( 'plgc-gs-lightbox--single', images.length <= 1 );

        updateLightboxImage();

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Show
        lightboxEl.classList.add( 'plgc-gs-lightbox--open' );

        // Move focus into lightbox (WCAG 2.4.3)
        setTimeout( function () {
            lbCloseEl.focus();
        }, 50 );
    }


    /**
     * Close lightbox and return focus to trigger element (WCAG 2.4.3).
     */
    function closeLightbox() {
        if ( ! lightboxEl ) return;

        lightboxEl.classList.remove( 'plgc-gs-lightbox--open' );
        document.body.style.overflow = '';

        if ( lbTriggerEl && typeof lbTriggerEl.focus === 'function' ) {
            lbTriggerEl.focus();
        }

        lbImages    = [];
        lbIndex     = 0;
        lbTriggerEl = null;
    }


    /**
     * Navigate to prev/next image (wraps around).
     */
    function navigateLightbox( direction ) {
        if ( lbImages.length <= 1 ) return;

        lbIndex += direction;
        if ( lbIndex < 0 ) lbIndex = lbImages.length - 1;
        if ( lbIndex >= lbImages.length ) lbIndex = 0;

        updateLightboxImage();
    }


    /**
     * Update the displayed image and counter.
     */
    function updateLightboxImage() {
        if ( ! lbImages[ lbIndex ] ) return;

        var img = lbImages[ lbIndex ];
        lbImgEl.src = img.url;
        lbImgEl.alt = img.alt;

        if ( lbCounterEl && lbImages.length > 1 ) {
            lbCounterEl.textContent = ( lbIndex + 1 ) + ' of ' + lbImages.length;
        }
    }


    /**
     * Trap Tab focus inside the lightbox dialog (WCAG 2.4.3 / 2.1.2).
     */
    function trapFocus( e ) {
        var focusable = lightboxEl.querySelectorAll(
            'button:not([disabled]):not([tabindex="-1"]), [tabindex="0"]'
        );
        if ( focusable.length === 0 ) return;

        var first = focusable[ 0 ];
        var last  = focusable[ focusable.length - 1 ];

        if ( e.shiftKey ) {
            if ( document.activeElement === first ) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if ( document.activeElement === last ) {
                e.preventDefault();
                first.focus();
            }
        }
    }


    /**
     * Initialise lightbox triggers on all gallery sections.
     *
     * Two ways to open:
     *   1. Click the expand button (bottom corner of image)
     *   2. Click directly on a gallery image
     *
     * Both read data-gallery JSON from the image-col element and open
     * at the current Swiper slide index.
     */
    function initGalleryLightbox() {
        // ── Expand buttons ──────────────────────────────────────────────
        document.querySelectorAll( '.plgc-gs__expand-btn' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var imageCol = btn.closest( '.plgc-gs__image-col' );
                if ( ! imageCol ) return;

                var galleryData = imageCol.getAttribute( 'data-gallery' );
                if ( ! galleryData ) return;

                var images = [];
                try { images = JSON.parse( galleryData ); } catch ( e ) { return; }

                // Get current slide index from Swiper
                var slider = imageCol.querySelector( '.plgc-gs__slider' );
                var currentIndex = 0;
                if ( slider && slider.swiper ) {
                    currentIndex = slider.swiper.realIndex || 0;
                }

                openLightbox( images, currentIndex, btn );
            } );
        } );

        // ── Clicking gallery images directly ────────────────────────────
        document.querySelectorAll( '.plgc-gs__slide-img' ).forEach( function ( img ) {
            img.style.cursor = 'pointer';

            img.addEventListener( 'click', function ( e ) {
                // Ignore keyboard-generated "clicks" — let expand button handle those
                if ( e.detail === 0 ) return;

                var imageCol = img.closest( '.plgc-gs__image-col' );
                if ( ! imageCol ) return;

                var galleryData = imageCol.getAttribute( 'data-gallery' );
                if ( ! galleryData ) return;

                var images = [];
                try { images = JSON.parse( galleryData ); } catch ( err ) { return; }

                var slider = imageCol.querySelector( '.plgc-gs__slider' );
                var currentIndex = 0;
                if ( slider && slider.swiper ) {
                    currentIndex = slider.swiper.realIndex || 0;
                }

                var expandBtn = imageCol.querySelector( '.plgc-gs__expand-btn' );
                openLightbox( images, currentIndex, expandBtn || img );
            } );
        } );
    }

    // Run lightbox init alongside the slider init
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initGalleryLightbox );
    } else {
        initGalleryLightbox();
    }

}() );
