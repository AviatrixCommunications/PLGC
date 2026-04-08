/**
 * Alert Banner — standalone plugin JS.
 * Handles notification banner display, dismissal, and animations.
 */
(function () {
  function slideDown(el, duration, onFinish) {
    var height = el.scrollHeight;
    var anim = el.animate([{ height: "0px" }, { height: height + "px" }], {
      duration: duration || 400,
      easing: "ease-out",
      fill: "forwards",
    });
    anim.onfinish = function () {
      el.style.height = "auto";
      el.style.overflow = "";
      anim.cancel();
      if (onFinish) onFinish();
    };
  }

  function slideUp(el, duration, onFinish) {
    el.style.overflow = "hidden";
    var cs = getComputedStyle(el);
    el.animate(
      [
        { height: cs.height, paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom },
        { height: "0px", paddingTop: "0px", paddingBottom: "0px" },
      ],
      { duration: duration || 350, easing: "ease-out", fill: "forwards" }
    ).onfinish = function () {
      if (onFinish) onFinish();
    };
  }

  document.addEventListener("DOMContentLoaded", function () {
    var tray = document.getElementById("custom-notification-tray");
    if (!tray) return;

    var header =
      document.querySelector("header") ||
      document.querySelector('[role="banner"]');
    var observer = null;
    var ONE_WEEK_MS = 7 * 24 * 60 * 60 * 1000;
    var now = Date.now();

    // Keep sticky header offset in sync with banner height at all times
    if (header) {
      observer = new ResizeObserver(function () {
        header.style.top = tray.offsetHeight + "px";
      });
      observer.observe(tray);
    }

    tray.querySelectorAll(".site-banner").forEach(function (banner) {
      var id = banner.getAttribute("data-alert-id");

      if (banner.classList.contains("js-banner-once")) {
        if (localStorage.getItem("alert_dismissed_" + id)) {
          banner.remove();
        }
      } else if (banner.classList.contains("js-banner-weekly")) {
        var muteUntil = localStorage.getItem("alert_mute_" + id);
        if (muteUntil && now < parseInt(muteUntil)) {
          banner.remove();
        }
      }
    });

    if (!tray.querySelectorAll(".site-banner").length) {
      if (observer) observer.disconnect();
      tray.remove();
      document.body.classList.remove("has-notification-banner");
      if (header) header.style.top = "";
      return;
    }

    setTimeout(function () {
      tray.removeAttribute("aria-hidden");
      slideDown(tray, 400);
    }, 2000);

    tray.addEventListener("click", function (e) {
      var closeBtn = e.target.closest(".alert-close");
      if (!closeBtn) return;

      var banner = closeBtn.closest(".site-banner");
      var alertId = banner.getAttribute("data-alert-id");

      if (banner.classList.contains("js-banner-once") && alertId) {
        localStorage.setItem("alert_dismissed_" + alertId, "true");
      } else if (banner.classList.contains("js-banner-weekly") && alertId) {
        localStorage.setItem("alert_mute_" + alertId, now + ONE_WEEK_MS);
      }

      var nextBanner =
        banner.nextElementSibling || banner.previousElementSibling;
      var focusTarget = nextBanner
        ? nextBanner.querySelector(".alert-close")
        : null;
      var isLast = !nextBanner;

      slideUp(banner, 350, function () {
        banner.remove();

        if (isLast) {
          if (observer) observer.disconnect();
          tray.remove();
          document.body.classList.remove("has-notification-banner");
          if (header) header.style.top = "";

          var firstInteractive = header
            ? header.querySelector("a, button")
            : null;
          if (firstInteractive) firstInteractive.focus();
        } else if (focusTarget) {
          focusTarget.focus();
        }
      });
    });
  });
})();
