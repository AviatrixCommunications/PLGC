/**
 * Accessibility Enhancements
 *
 * Front-end keyboard navigation, focus management,
 * and accessibility improvements.
 *
 * Supports both Elementor V3 widgets and V4 atomic elements.
 *
 * @package PLGC
 */

(function () {
    'use strict';

    /**
     * Fix Elementor entrance animations hiding content on iOS/mobile.
     *
     * Elementor marks animated widgets with .elementor-invisible and sets
     * inline style="visibility: hidden; opacity: 0;" until its Intersection
     * Observer fires. On iOS Safari the observer can fail for elements that
     * are in view on load, leaving entire sections permanently hidden.
     *
     * Strategy: immediately clear all .elementor-invisible elements, then
     * watch for any that get added later (e.g. dynamic content).
     */
    function plgcRevealHiddenSections() {
        var hidden = document.querySelectorAll('.elementor-invisible');
        hidden.forEach(function (el) {
            el.classList.remove('elementor-invisible');
            // Also clear any inline visibility/opacity Elementor may have set
            if (el.style.visibility === 'hidden') {
                el.style.removeProperty('visibility');
            }
            if (el.style.opacity === '0') {
                el.style.removeProperty('opacity');
            }
        });
    }

    // Run immediately (catches elements already in the DOM at parse time)
    plgcRevealHiddenSections();

    // Run again on DOMContentLoaded in case Elementor sets the class later
    document.addEventListener('DOMContentLoaded', plgcRevealHiddenSections);

    // Watch for Elementor dynamically re-adding the class (some Pro versions do)
    var invisibleObserver = new MutationObserver(function (mutations) {
        var needsReveal = false;
        mutations.forEach(function (mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                if (mutation.target.classList.contains('elementor-invisible')) {
                    needsReveal = true;
                }
            }
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1 && node.classList && node.classList.contains('elementor-invisible')) {
                        needsReveal = true;
                    }
                });
            }
        });
        if (needsReveal) {
            plgcRevealHiddenSections();
        }
    });

    invisibleObserver.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style']
    });


    /**
     * Adds .using-keyboard class for styling focus rings.
     * Keyboard users get full-resolution focus rings; mouse users don't.
     */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
            document.body.classList.add('using-keyboard');
        }
    });

    document.addEventListener('mousedown', function () {
        document.body.classList.remove('using-keyboard');
    });

    /**
     * Escape key handler.
     * Closes Elementor popups, mobile menus, and overlays.
     * Works with both V3 popup system and V4 dialog patterns.
     */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;

        // Close Elementor popup if open (V3 + V4)
        var popup = document.querySelector(
            '.elementor-popup-modal:not([style*="display: none"]), ' +
            '[role="dialog"][open], [role="dialog"][aria-modal="true"]'
        );
        if (popup) {
            var closeBtn = popup.querySelector(
                '.dialog-close-button, [data-dismiss], button[aria-label*="close" i]'
            );
            if (closeBtn) closeBtn.click();
            return;
        }

        // Close mobile menu if open (V3 + V4)
        var mobileMenu = document.querySelector(
            '.elementor-menu-toggle[aria-expanded="true"], ' +
            '[class*="e-menu"] [aria-expanded="true"]'
        );
        if (mobileMenu) {
            mobileMenu.click();
            mobileMenu.focus();
        }
    });

    /**
     * Skip link focus fix for all browsers.
     */
    var skipLink = document.querySelector('.plgc-skip-link');
    if (skipLink) {
        skipLink.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href').replace('#', '');
            var target = document.getElementById(targetId);
            if (target) {
                if (!/^(?:a|select|input|button|textarea)$/i.test(target.tagName)) {
                    target.setAttribute('tabindex', '-1');
                }
                target.focus();
            }
        });
    }

    /**
     * Ensure accordion/toggle/tab items are keyboard accessible.
     * V3: .elementor-tab-title
     * V4: Atomic Tabs use proper ARIA by default, but we patch
     *     any elements with role="tab" that lack keyboard support.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var v3Titles = document.querySelectorAll('.elementor-tab-title');
        var v4Triggers = document.querySelectorAll('[role="tab"]:not(.elementor-tab-title)');

        var allTriggers = [].concat(
            Array.prototype.slice.call(v3Titles),
            Array.prototype.slice.call(v4Triggers)
        );

        allTriggers.forEach(function (title) {
            if (!title.getAttribute('role')) {
                title.setAttribute('role', 'button');
            }
            if (!title.getAttribute('tabindex') && title.getAttribute('tabindex') !== '0') {
                title.setAttribute('tabindex', '0');
            }

            title.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    });

    /**
     * Add aria-current="page" to active navigation items.
     * V3: .elementor-nav-menu a
     * V4: Atomic nav uses standard <nav> with <a> elements.
     * (WCAG 2.4.8 - Location)
     */
    document.addEventListener('DOMContentLoaded', function () {
        var currentUrl = window.location.href.replace(/\/$/, '');
        var navLinks = document.querySelectorAll(
            '.elementor-nav-menu a, ' +
            '.elementor-menu-toggle a, ' +
            'nav a, ' +
            '[role="navigation"] a'
        );

        navLinks.forEach(function (link) {
            var linkUrl = link.href.replace(/\/$/, '');
            if (linkUrl === currentUrl) {
                link.setAttribute('aria-current', 'page');
            }
        });
    });

    /**
     * Trap focus inside modals/popups when open.
     * Handles V3 popups and V4 dialog elements.
     * (WCAG 2.4.3 - Focus Order)
     */
    function trapFocus(element) {
        var focusable = element.querySelectorAll(
            'a[href], button:not([disabled]), textarea, input:not([disabled]), select, [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        element.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
    }

    // Observe for popups/dialogs opening (V3 + V4)
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;

                var isV3Popup = node.classList && node.classList.contains('elementor-popup-modal');
                var isV4Dialog = node.getAttribute && (
                    node.getAttribute('role') === 'dialog' ||
                    node.tagName === 'DIALOG'
                );

                if (isV3Popup || isV4Dialog) {
                    trapFocus(node);
                    var firstFocusable = node.querySelector(
                        'a[href], button:not([disabled]), input:not([disabled])'
                    );
                    if (firstFocusable) {
                        setTimeout(function () { firstFocusable.focus(); }, 100);
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

})();
