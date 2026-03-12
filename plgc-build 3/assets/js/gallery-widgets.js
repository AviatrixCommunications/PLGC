/**
 * PLGC Gallery Widgets — JS v1.6.15
 *
 * Root-cause fix: setPointerCapture() was the source of every click/lightbox
 * failure. When a swipe handler called setPointerCapture() on pointerdown,
 * all subsequent pointer events were re-targeted to the capturing element.
 * Browsers then couldn't match a clean mousedown+mouseup pair on the same
 * target, so they suppressed the resulting click event entirely — meaning
 * lightbox triggers, close buttons, and nav arrows all silently failed.
 *
 * Solution: removed setPointerCapture entirely. We now track swipes using
 * document-level pointerup/pointermove listeners keyed to the element that
 * received pointerdown. No capture = no click interference.
 *
 * Additional fixes:
 *   - pointer-events: all → auto in CSS (invalid value)
 *   - Three-layer init (DOMContentLoaded + load + MutationObserver)
 *   - Double-init guards on every widget element
 *
 * WCAG 2.1 AA:
 *   SC 2.2.2  — auto-advancing content has visible pause/play control
 *   SC 2.5.5  — 44×44px touch targets
 *   prefers-reduced-motion — auto features disabled
 *   aria-live — announces current slide to screen readers
 *   focus trap + Escape in lightbox
 *
 * @package PLGC
 * @since   1.6.15
 */

( function () {
    'use strict';

    var prefersReducedMotion = ( window.matchMedia &&
        window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );


    /* ══════════════════════════════════════════════════════════════════════
       SHARED: SWIPE DETECTION — NO setPointerCapture
       ══════════════════════════════════════════════════════════════════════

       Why no setPointerCapture:
         setPointerCapture() re-routes ALL pointer events to the capturing
         element. The browser then sees pointerdown on child A but pointerup
         on parent B (the capturer), which breaks its internal click-detection
         logic — no click event fires. This killed lightbox triggers, close
         buttons, and nav arrows across the board.

       How we detect swipes without it:
         - Track pointerdown on the target element (startX, el reference)
         - Listen for pointermove and pointerup on DOCUMENT
         - On pointerup, only act if our tracked element matches
         - A real tap (dx < MIN_DRAG) never sets `swiped` so no ghost-click
           suppressor is ever installed
       ══════════════════════════════════════════════════════════════════════ */

    // One shared set of document listeners — avoids layering multiple handlers
    var _swipeStart = null; // { el, startX, startY, callback }

    document.addEventListener( 'pointermove', function ( e ) {
        if ( ! _swipeStart ) return;
        _swipeStart.dx = e.clientX - _swipeStart.startX;
    }, { passive: true } );

    document.addEventListener( 'pointerup', function ( e ) {
        if ( ! _swipeStart ) return;
        var s   = _swipeStart;
        _swipeStart = null;

        var dx = e.clientX - s.startX;
        var dy = e.clientY - s.startY;

        if ( Math.abs( dx ) < 40 || Math.abs( dy ) > 75 ) return; // not a swipe

        s.callback( dx < 0 ? 1 : -1 );

        // Suppress the synthetic click that fires ~300ms after pointerup on touch.
        // Only runs after a confirmed 40px swipe — normal taps never reach here.
        s.el.addEventListener( 'click', function ( ev ) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
        }, { capture: true, once: true } );

    }, { passive: true } );

    document.addEventListener( 'pointercancel', function () {
        _swipeStart = null;
    }, { passive: true } );

    function addSwipe( el, callback ) {
        el.addEventListener( 'pointerdown', function ( e ) {
            if ( e.button !== 0 && e.pointerType === 'mouse' ) return;
            _swipeStart = { el: el, startX: e.clientX, startY: e.clientY, callback: callback };
        }, { passive: true } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       1. FILMSTRIP — scroll, swipe, auto-scroll, lightbox
       ══════════════════════════════════════════════════════════════════════ */
    function initFilmstrips() {
        document.querySelectorAll( '[data-plgc-gallery-strip]' ).forEach( function ( fs ) {
            if ( fs.dataset.plgcFsInit ) return;
            fs.dataset.plgcFsInit = '1';

            var track     = fs.querySelector( '[data-plgc-fs-track]' );
            var prevBtn   = fs.querySelector( '[data-plgc-fs-prev]' );
            var nextBtn   = fs.querySelector( '[data-plgc-fs-next]' );
            var pauseBtn  = fs.querySelector( '[data-plgc-fs-pause]' );
            var firstCard = fs.querySelector( '.plgc-filmstrip__item' );
            if ( ! track || ! firstCard ) return;

            /* ── Lightbox ─────────────────────────────────────────────── */
            wireLightbox( fs );

            /* ── Scroll helpers ───────────────────────────────────────── */
            function cardWidth() {
                var gap = parseFloat( getComputedStyle( fs ).getPropertyValue( '--plgc-fs-gap' ) ) || 27;
                return firstCard.offsetWidth + gap;
            }

            function updateNavButtons() {
                if ( ! prevBtn || ! nextBtn ) return;
                var sl  = track.scrollLeft;
                var max = track.scrollWidth - track.clientWidth;
                prevBtn.disabled = sl <= 1;
                nextBtn.disabled = sl >= max - 1;
            }

            if ( prevBtn ) {
                prevBtn.addEventListener( 'click', function () {
                    stopAutoScroll();
                    track.scrollBy( { left: -cardWidth(), behavior: 'smooth' } );
                    if ( autoScrollActive ) setTimeout( startAutoScroll, 2000 );
                } );
            }
            if ( nextBtn ) {
                nextBtn.addEventListener( 'click', function () {
                    stopAutoScroll();
                    track.scrollBy( { left: cardWidth(), behavior: 'smooth' } );
                    if ( autoScrollActive ) setTimeout( startAutoScroll, 2000 );
                } );
            }

            track.addEventListener( 'scroll',    updateNavButtons, { passive: true } );
            track.addEventListener( 'scrollend', updateNavButtons, { passive: true } );
            updateNavButtons();

            /* ── Keyboard ─────────────────────────────────────────────── */
            fs.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'ArrowLeft' )  { track.scrollBy( { left: -cardWidth(), behavior: 'smooth' } ); e.preventDefault(); }
                if ( e.key === 'ArrowRight' ) { track.scrollBy( { left:  cardWidth(), behavior: 'smooth' } ); e.preventDefault(); }
            } );

            /* ── Swipe ────────────────────────────────────────────────── */
            addSwipe( track, function ( dir ) {
                track.scrollBy( { left: dir * cardWidth(), behavior: 'smooth' } );
            } );

            /* ── Auto-scroll ──────────────────────────────────────────── */
            var autoScrollSpeed  = parseInt( fs.getAttribute( 'data-plgc-fs-autoscroll' ) || '0', 10 );
            var autoScrollActive = autoScrollSpeed > 0 && ! prefersReducedMotion;
            var rafId            = null;
            var isUserPaused     = false;
            var lastTime         = null;

            function autoScrollStep( ts ) {
                if ( ! lastTime ) lastTime = ts;
                var dt  = ts - lastTime; lastTime = ts;
                var px  = ( autoScrollSpeed * dt ) / 1000;
                var max = track.scrollWidth - track.clientWidth;
                if ( track.scrollLeft >= max - 1 ) {
                    track.scrollTo( { left: 0, behavior: 'smooth' } );
                } else {
                    track.scrollLeft += px;
                }
                updateNavButtons();
                rafId = requestAnimationFrame( autoScrollStep );
            }
            function startAutoScroll() {
                if ( ! autoScrollActive || isUserPaused ) return;
                lastTime = null;
                rafId = requestAnimationFrame( autoScrollStep );
            }
            function stopAutoScroll() {
                if ( rafId ) { cancelAnimationFrame( rafId ); rafId = null; lastTime = null; }
            }

            if ( pauseBtn ) {
                var iconPause = pauseBtn.querySelector( '.plgc-fs-pause__icon-pause' );
                var iconPlay  = pauseBtn.querySelector( '.plgc-fs-pause__icon-play' );
                pauseBtn.addEventListener( 'click', function () {
                    isUserPaused = ! isUserPaused;
                    if ( isUserPaused ) {
                        stopAutoScroll();
                        pauseBtn.setAttribute( 'aria-label', 'Play scrolling' );
                        if ( iconPause ) iconPause.style.display = 'none';
                        if ( iconPlay  ) iconPlay.style.display  = '';
                    } else {
                        pauseBtn.setAttribute( 'aria-label', 'Pause scrolling' );
                        if ( iconPause ) iconPause.style.display = '';
                        if ( iconPlay  ) iconPlay.style.display  = 'none';
                        startAutoScroll();
                    }
                } );
                fs.addEventListener( 'mouseenter', stopAutoScroll );
                fs.addEventListener( 'mouseleave', function () { if ( ! isUserPaused ) startAutoScroll(); } );
                fs.addEventListener( 'focusin',    stopAutoScroll );
                fs.addEventListener( 'focusout',   function () { if ( ! isUserPaused ) startAutoScroll(); } );
            }

            if ( autoScrollActive ) startAutoScroll();
        } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       2. CONTENT SLIDESHOW — dots, swipe, keyboard, auto-rotate, lightbox
       ══════════════════════════════════════════════════════════════════════ */
    function initContentSlideshows() {
        document.querySelectorAll( '[data-plgc-cs-slider]' ).forEach( function ( slider ) {
            if ( slider.dataset.plgcCsInit ) return;
            slider.dataset.plgcCsInit = '1';

            var slides     = Array.from( slider.querySelectorAll( '[data-plgc-cs-slide]' ) );
            var dots       = Array.from( slider.querySelectorAll( '[data-plgc-cs-dot]' ) );
            var liveRegion = slider.querySelector( '.plgc-cs-dots__sr-live' );
            var pauseBtn   = slider.querySelector( '[data-plgc-cs-pause]' );
            var progressEl = slider.querySelector( '[data-plgc-cs-progress]' );
            var current    = 0;
            var timer      = null;
            var isUserPaused = false;

            wireLightbox( slider );

            if ( slides.length < 2 ) return;

            var intervalSec = parseInt( slider.getAttribute( 'data-plgc-cs-autorotate' ) || '0', 10 );
            var autoRotate  = intervalSec > 0 && ! prefersReducedMotion;

            /* ── Navigate ─────────────────────────────────────────────── */
            function goTo( index, userInitiated ) {
                index = ( ( index % slides.length ) + slides.length ) % slides.length;
                slides[ current ].classList.remove( 'is-active' );
                slides[ current ].setAttribute( 'aria-hidden', 'true' );
                if ( dots[ current ] ) { dots[ current ].classList.remove( 'is-active' ); dots[ current ].setAttribute( 'aria-pressed', 'false' ); }
                current = index;
                slides[ current ].classList.add( 'is-active' );
                slides[ current ].removeAttribute( 'aria-hidden' );
                if ( dots[ current ] ) { dots[ current ].classList.add( 'is-active' ); dots[ current ].setAttribute( 'aria-pressed', 'true' ); }
                if ( liveRegion ) {
                    liveRegion.textContent = '';
                    setTimeout( function () { liveRegion.textContent = 'Photo ' + ( current + 1 ) + ' of ' + slides.length; }, 50 );
                }
                if ( userInitiated && autoRotate && ! isUserPaused ) { stopTimer(); startTimer(); }
                resetProgress();
                if ( autoRotate && ! isUserPaused ) animateProgress();
            }

            /* ── Auto-rotation ────────────────────────────────────────── */
            function startTimer() {
                if ( ! autoRotate || isUserPaused ) return;
                stopTimer();
                timer = setTimeout( function () { goTo( current + 1, false ); startTimer(); }, intervalSec * 1000 );
            }
            function stopTimer() { if ( timer ) { clearTimeout( timer ); timer = null; } }

            function resetProgress() {
                if ( ! progressEl ) return;
                progressEl.classList.remove( 'is-animating' );
                progressEl.style.width = '0%';
                progressEl.style.transitionDuration = '';
            }
            function animateProgress() {
                if ( ! progressEl ) return;
                void progressEl.offsetWidth;
                progressEl.style.transitionDuration = intervalSec + 's';
                progressEl.classList.add( 'is-animating' );
                progressEl.style.width = '100%';
            }

            /* ── Pause button ─────────────────────────────────────────── */
            if ( pauseBtn ) {
                var iconPause = pauseBtn.querySelector( '.plgc-cs-pause__icon-pause' );
                var iconPlay  = pauseBtn.querySelector( '.plgc-cs-pause__icon-play' );
                pauseBtn.addEventListener( 'click', function () {
                    isUserPaused = ! isUserPaused;
                    if ( isUserPaused ) {
                        stopTimer(); resetProgress();
                        pauseBtn.setAttribute( 'aria-label', 'Play slideshow' );
                        if ( iconPause ) iconPause.style.display = 'none';
                        if ( iconPlay  ) iconPlay.style.display  = '';
                    } else {
                        pauseBtn.setAttribute( 'aria-label', 'Pause slideshow' );
                        if ( iconPause ) iconPause.style.display = '';
                        if ( iconPlay  ) iconPlay.style.display  = 'none';
                        startTimer(); animateProgress();
                    }
                } );
                slider.addEventListener( 'mouseenter', function () { if ( ! isUserPaused ) { stopTimer(); resetProgress(); } } );
                slider.addEventListener( 'mouseleave', function () { if ( ! isUserPaused ) { startTimer(); animateProgress(); } } );
                slider.addEventListener( 'focusin',    function () { if ( ! isUserPaused ) { stopTimer(); resetProgress(); } } );
                slider.addEventListener( 'focusout',   function () { if ( ! isUserPaused ) { startTimer(); animateProgress(); } } );
            }

            /* ── Dot clicks ───────────────────────────────────────────── */
            dots.forEach( function ( dot ) {
                dot.addEventListener( 'click', function ( e ) {
                    e.stopPropagation();
                    goTo( parseInt( dot.getAttribute( 'data-plgc-cs-dot' ), 10 ), true );
                } );
            } );

            /* ── Keyboard ─────────────────────────────────────────────── */
            slider.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'ArrowLeft' )  { goTo( current - 1, true ); e.preventDefault(); }
                if ( e.key === 'ArrowRight' ) { goTo( current + 1, true ); e.preventDefault(); }
            } );

            /* ── Swipe — attached to the image column, not the whole slider ── */
            var imgCol = slider.querySelector( '.plgc-content-slideshow__img-col' );
            if ( imgCol ) {
                addSwipe( imgCol, function ( dir ) { goTo( current + dir, true ); } );
            }

            /* ── Start ────────────────────────────────────────────────── */
            if ( autoRotate ) { startTimer(); animateProgress(); }
        } );
    }


    /* ══════════════════════════════════════════════════════════════════════
       LIGHTBOX — build once, wire triggers per gallery
       ══════════════════════════════════════════════════════════════════════ */
    var lb = null, lbImg, lbCaption, lbCount, lbClose, lbPrev, lbNext, lbAnnounce;
    var currentIndex = 0, siblingTriggers = [], lastFocused = null;

    function wireLightbox( el ) {
        var triggers = Array.from( el.querySelectorAll( '[data-plgc-lb-src]' ) );
        triggers.forEach( function ( t ) {
            if ( t.dataset.plgcLbInit ) return;
            t.dataset.plgcLbInit = '1';
            t.addEventListener( 'click', function () {
                openLightbox( t, triggers );
            } );
        } );
    }

    function buildLightbox() {
        lb = document.createElement( 'div' );
        lb.id        = 'plgc-lightbox';
        lb.className = 'plgc-lb';
        lb.setAttribute( 'role',       'dialog' );
        lb.setAttribute( 'aria-modal', 'true' );
        lb.setAttribute( 'aria-label', 'Image lightbox' );
        lb.setAttribute( 'aria-hidden','true' );
        lb.innerHTML = [
            '<div class="plgc-lb__backdrop"></div>',
            '<div class="plgc-lb__inner">',
            '  <div class="plgc-lb__sr-live" aria-live="polite" aria-atomic="true"></div>',
            '  <button class="plgc-lb__close" type="button" aria-label="Close lightbox (Escape)">',
            '    <svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none">',
            '      <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            '    </svg>',
            '  </button>',
            '  <button class="plgc-lb__prev" type="button" aria-label="Previous photo">',
            '    <svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none">',
            '      <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>',
            '    </svg>',
            '  </button>',
            '  <figure class="plgc-lb__figure">',
            '    <img class="plgc-lb__img" src="" alt="" loading="eager">',
            '    <figcaption class="plgc-lb__caption"></figcaption>',
            '  </figure>',
            '  <button class="plgc-lb__next" type="button" aria-label="Next photo">',
            '    <svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none">',
            '      <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>',
            '    </svg>',
            '  </button>',
            '  <div class="plgc-lb__count" aria-hidden="true"></div>',
            '</div>',
        ].join( '' );
        document.body.appendChild( lb );

        lbImg      = lb.querySelector( '.plgc-lb__img' );
        lbCaption  = lb.querySelector( '.plgc-lb__caption' );
        lbCount    = lb.querySelector( '.plgc-lb__count' );
        lbClose    = lb.querySelector( '.plgc-lb__close' );
        lbPrev     = lb.querySelector( '.plgc-lb__prev' );
        lbNext     = lb.querySelector( '.plgc-lb__next' );
        lbAnnounce = lb.querySelector( '.plgc-lb__sr-live' );

        // Click handlers — direct, no swipe interference
        lbClose.addEventListener( 'click', closeLightbox );
        lb.querySelector( '.plgc-lb__backdrop' ).addEventListener( 'click', closeLightbox );
        lbPrev.addEventListener( 'click', function () { navigate( -1 ); } );
        lbNext.addEventListener( 'click', function () { navigate(  1 ); } );

        // Swipe inside lightbox for prev/next — attached to the figure only
        // so button clicks aren't on the swipe element at all
        var figure = lb.querySelector( '.plgc-lb__figure' );
        addSwipe( figure, navigate );

        // Keyboard
        document.addEventListener( 'keydown', function ( e ) {
            if ( ! lb || lb.getAttribute( 'aria-hidden' ) === 'true' ) return;
            if ( e.key === 'Escape' )                    { closeLightbox(); }
            else if ( e.key === 'ArrowLeft' )            { navigate( -1 ); e.preventDefault(); }
            else if ( e.key === 'ArrowRight' )           { navigate(  1 ); e.preventDefault(); }
            else if ( e.key === 'Tab' )                  { trapFocus( e ); }
        } );
    }

    function trapFocus( e ) {
        var focusable = Array.from( lb.querySelectorAll( 'button:not([disabled])' ) );
        if ( ! focusable.length ) return;
        var first = focusable[0], last = focusable[ focusable.length - 1 ];
        if ( e.shiftKey && document.activeElement === first ) { last.focus(); e.preventDefault(); }
        else if ( ! e.shiftKey && document.activeElement === last ) { first.focus(); e.preventDefault(); }
    }

    function openLightbox( trigger, triggers ) {
        if ( ! lb ) buildLightbox();
        lastFocused     = document.activeElement;
        siblingTriggers = triggers;
        currentIndex    = parseInt( trigger.getAttribute( 'data-plgc-lb-index' ) || '0', 10 );
        loadSlide( currentIndex );
        lb.setAttribute( 'aria-hidden', 'false' );
        document.body.style.overflow = 'hidden';
        // Small delay so the lightbox is visible before we move focus into it
        setTimeout( function () { lbClose.focus(); }, 50 );
    }

    function loadSlide( index ) {
        var trigger = siblingTriggers[ index ];
        if ( ! trigger ) return;
        var src     = trigger.getAttribute( 'data-plgc-lb-src' )     || '';
        var alt     = trigger.getAttribute( 'data-plgc-lb-alt' )     || '';
        var caption = trigger.getAttribute( 'data-plgc-lb-caption' ) || '';
        var total   = siblingTriggers.length;
        lbImg.src             = src;
        lbImg.alt             = alt;
        lbCaption.textContent = caption;
        lbCaption.hidden      = ! caption;
        lbCount.textContent   = ( index + 1 ) + ' / ' + total;
        lbPrev.hidden         = total <= 1;
        lbNext.hidden         = total <= 1;
        lbAnnounce.textContent = '';
        setTimeout( function () {
            lbAnnounce.textContent = 'Photo ' + ( index + 1 ) + ' of ' + total + ( alt ? ': ' + alt : '' );
        }, 50 );
    }

    function closeLightbox() {
        if ( ! lb ) return;
        lb.setAttribute( 'aria-hidden', 'true' );
        document.body.style.overflow = '';
        lbImg.src = '';
        if ( lastFocused && lastFocused.focus ) { lastFocused.focus(); lastFocused = null; }
    }

    function navigate( dir ) {
        if ( ! siblingTriggers.length ) return;
        currentIndex = ( ( currentIndex + dir ) % siblingTriggers.length + siblingTriggers.length ) % siblingTriggers.length;
        loadSlide( currentIndex );
    }


    /* ══════════════════════════════════════════════════════════════════════
       INIT — four layers so widgets always initialize
       ══════════════════════════════════════════════════════════════════════ */
    function init() {
        initFilmstrips();
        initContentSlideshows();
    }

    // Layer 1: immediate / DOMContentLoaded
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Layer 2: window load (Elementor may finish rendering after DOMContentLoaded)
    window.addEventListener( 'load', init );

    // Layer 3: MutationObserver (Elementor widget injection after load)
    if ( window.MutationObserver ) {
        var _mo = new MutationObserver( function ( mutations ) {
            for ( var i = 0; i < mutations.length; i++ ) {
                var nodes = mutations[i].addedNodes;
                for ( var j = 0; j < nodes.length; j++ ) {
                    var n = nodes[j];
                    if ( n.nodeType !== 1 ) continue;
                    if ( n.querySelector( '[data-plgc-gallery-strip],[data-plgc-cs-slider]' ) ||
                         ( n.matches && ( n.matches( '[data-plgc-gallery-strip]' ) || n.matches( '[data-plgc-cs-slider]' ) ) ) ) {
                        init();
                        return;
                    }
                }
            }
        } );
        _mo.observe( document.documentElement, { childList: true, subtree: true } );
    }

    // Layer 4: Elementor hooks — fully try/catch guarded
    function tryElementorHooks() {
        try {
            if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
                [ 'plgc_gallery_filmstrip', 'plgc_content_slideshow' ].forEach( function ( name ) {
                    window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '/default', init );
                } );
            }
        } catch (e) {}
    }
    tryElementorHooks();
    window.addEventListener( 'elementor/frontend/init', tryElementorHooks );

}() );
