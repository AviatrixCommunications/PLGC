/**
 * Alert Banner — standalone plugin JS.
 *
 * Handles notification banner display, dismissal, and animations.
 *
 * SESSION PERSISTENCE:
 *   The banner slides down with an animation on the FIRST page load of a
 *   browser session. On subsequent page navigations within the same session,
 *   it renders immediately (no delay, no animation) so it doesn't push
 *   content down and jar the user.
 *
 *   sessionStorage is used (not localStorage) so the animation plays again
 *   when the user opens a new tab or restarts the browser — giving them
 *   the "fresh visit" experience once per session.
 *
 * DISMISSAL:
 *   - "Once" banners use localStorage (permanent until cleared).
 *   - "Weekly" banners use localStorage with a 7-day TTL.
 *   - "Always" banners show every session (no storage).
 */
(function () {
  'use strict';

  /* ── Animation helpers ───────────────────────────────────── */

  function slideDown(el, duration, onFinish) {
    var height = el.scrollHeight;
    var anim = el.animate([{ height: '0px' }, { height: height + 'px' }], {
      duration: duration || 400,
      easing: 'ease-out',
      fill: 'forwards',
    });
    anim.onfinish = function () {
      el.style.height = 'auto';
      el.style.overflow = '';
      anim.cancel();
      if (onFinish) onFinish();
    };
  }

  function slideUp(el, duration, onFinish) {
    el.style.overflow = 'hidden';
    var cs = getComputedStyle(el);
    el.animate(
      [
        { height: cs.height, paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom },
        { height: '0px', paddingTop: '0px', paddingBottom: '0px' },
      ],
      { duration: duration || 350, easing: 'ease-out', fill: 'forwards' }
    ).onfinish = function () {
      if (onFinish) onFinish();
    };
  }

  /**
   * Show the tray immediately — no animation, no delay.
   * Used on return page loads within the same session.
   */
  function showInstantly(el) {
    el.style.height = 'auto';
    el.style.overflow = '';
    el.removeAttribute('aria-hidden');
  }

  /* ── Main ────────────────────────────────────────────────── */

  document.addEventListener('DOMContentLoaded', function () {
    var tray = document.getElementById('custom-notification-tray');
    if (!tray) return;

    var header =
      document.querySelector('header') ||
      document.querySelector('[role="banner"]');
    var observer = null;
    var ONE_WEEK_MS = 7 * 24 * 60 * 60 * 1000;
    var now = Date.now();

    // Session key — tracks whether the banner has been animated this session
    var SESSION_KEY = 'plgc_banner_shown';
    var hasShownThisSession = false;

    try {
      hasShownThisSession = sessionStorage.getItem(SESSION_KEY) === '1';
    } catch (e) {
      // Private browsing or storage disabled — fall back to animating
    }

    /* ── Keep sticky header offset in sync ──────────────────── */

    if (header) {
      observer = new ResizeObserver(function () {
        header.style.top = tray.offsetHeight + 'px';
      });
      observer.observe(tray);
    }

    /* ── Filter dismissed banners ───────────────────────────── */

    tray.querySelectorAll('.site-banner').forEach(function (banner) {
      var id = banner.getAttribute('data-alert-id');

      if (banner.classList.contains('js-banner-once')) {
        if (localStorage.getItem('alert_dismissed_' + id)) {
          banner.remove();
        }
      } else if (banner.classList.contains('js-banner-weekly')) {
        var muteUntil = localStorage.getItem('alert_mute_' + id);
        if (muteUntil && now < parseInt(muteUntil, 10)) {
          banner.remove();
        }
      }
    });

    /* ── Bail if no banners remain ──────────────────────────── */

    if (!tray.querySelectorAll('.site-banner').length) {
      if (observer) observer.disconnect();
      tray.remove();
      document.body.classList.remove('has-notification-banner');
      if (header) header.style.top = '';
      return;
    }

    /* ── Reveal the tray ───────────────────────────────────── */

    if (hasShownThisSession) {
      // Return visit in same session — show instantly, no jarring push
      showInstantly(tray);

      // Sync header offset immediately
      if (header) {
        header.style.top = tray.offsetHeight + 'px';
      }
    } else {
      // First page load this session — animate the reveal after a brief pause
      setTimeout(function () {
        tray.removeAttribute('aria-hidden');
        slideDown(tray, 400, function () {
          // Mark session so subsequent pages skip the animation
          try {
            sessionStorage.setItem(SESSION_KEY, '1');
          } catch (e) {
            // Storage unavailable — next page will animate again (acceptable)
          }
        });
      }, 2000);
    }

    /* ── Dismiss handling ──────────────────────────────────── */

    tray.addEventListener('click', function (e) {
      var closeBtn = e.target.closest('.alert-close');
      if (!closeBtn) return;

      var banner = closeBtn.closest('.site-banner');
      var alertId = banner.getAttribute('data-alert-id');

      if (banner.classList.contains('js-banner-once') && alertId) {
        localStorage.setItem('alert_dismissed_' + alertId, 'true');
      } else if (banner.classList.contains('js-banner-weekly') && alertId) {
        localStorage.setItem('alert_mute_' + alertId, String(now + ONE_WEEK_MS));
      }

      var nextBanner =
        banner.nextElementSibling || banner.previousElementSibling;
      var focusTarget = nextBanner
        ? nextBanner.querySelector('.alert-close')
        : null;
      var isLast = !nextBanner;

      slideUp(banner, 350, function () {
        banner.remove();

        if (isLast) {
          if (observer) observer.disconnect();
          tray.remove();
          document.body.classList.remove('has-notification-banner');
          if (header) header.style.top = '';

          // Clear session flag so banner animates again on next visit
          // if the admin re-enables it
          try {
            sessionStorage.removeItem(SESSION_KEY);
          } catch (e) {
            // Ignore
          }

          var firstInteractive = header
            ? header.querySelector('a, button')
            : null;
          if (firstInteractive) firstInteractive.focus();
        } else if (focusTarget) {
          focusTarget.focus();
        }
      });
    });
  });
})();
