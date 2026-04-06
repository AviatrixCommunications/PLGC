/**
 * PLGC Navigation — Interaction Script
 *
 * Handles:
 *  - Mega panel open/close (hover + click + keyboard)
 *  - Arrow-key navigation within and between panels
 *  - Search panel (Figma design: full-width grey, pill input, green button)
 *  - WP Engine Smart Search AJAX with live results + document indexing
 *  - Mobile drawer open/close + sub-menu expand/collapse
 */

(function () {
    'use strict';

    // ============================================================
    // MEGA MENU
    // ============================================================

    const header   = document.querySelector('.plgc-header');
    const triggers = header ? Array.from(header.querySelectorAll('.plgc-nav__trigger')) : [];

    // Items that have mega panels (li.plgc-nav__item--has-mega)
    const megaItems = header ? Array.from(header.querySelectorAll('.plgc-nav__item--has-mega')) : [];

    function getPanelForTrigger(trigger) {
        const id = trigger.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;
    }

    /**
     * For a mega <li>, find the trigger <button> inside it.
     */
    function getTriggerForItem(li) {
        return li.querySelector('.plgc-nav__trigger');
    }

    // ── State: track which trigger (if any) was explicitly clicked open
    let lockedTrigger = null;

    function openMega(trigger) {
        closeAll(trigger);
        const panel = getPanelForTrigger(trigger);
        if (!panel) return;
        trigger.setAttribute('aria-expanded', 'true');
        panel.removeAttribute('hidden');
    }

    function closeMega(trigger) {
        const panel = getPanelForTrigger(trigger);
        if (!panel) return;
        trigger.setAttribute('aria-expanded', 'false');
        panel.setAttribute('hidden', '');
        if (lockedTrigger === trigger) lockedTrigger = null;
    }

    function closeAll(exceptTrigger) {
        triggers.forEach(t => { if (t !== exceptTrigger) closeMega(t); });
    }

    function isOpen(trigger) {
        return trigger.getAttribute('aria-expanded') === 'true';
    }

    // Hover: open on mouseenter of the parent <li>, not just the button.
    // This way hovering the link text in a split trigger also reveals the panel.
    megaItems.forEach(li => li.addEventListener('mouseenter', () => {
        const trigger = getTriggerForItem(li);
        if (!trigger) return;
        if (lockedTrigger && lockedTrigger !== trigger) return;
        openMega(trigger);
    }));

    // Mouse leave: only close if no panel is locked
    if (header) header.addEventListener('mouseleave', () => {
        if (!lockedTrigger) closeAll();
    });

    // Click: toggle lock state (on the <button> trigger only)
    triggers.forEach(trigger => {
        trigger.addEventListener('click', e => {
            e.stopPropagation();
            if (isOpen(trigger) && lockedTrigger === trigger) {
                lockedTrigger = null;
                closeMega(trigger);
            } else {
                openMega(trigger);
                lockedTrigger = trigger;
            }
        });
    });

    // Click outside: close and clear lock
    document.addEventListener('click', e => {
        if (header && !header.contains(e.target)) {
            lockedTrigger = null;
            closeAll();
        }
    });

    // Keyboard
    triggers.forEach((trigger, i) => {
        trigger.addEventListener('keydown', e => {
            const panel = getPanelForTrigger(trigger);
            const focusable = panel
                ? Array.from(panel.querySelectorAll('a[href], button:not([disabled])'))
                : [];

            switch (e.key) {
                case 'Enter': case ' ':
                    e.preventDefault();
                    if (isOpen(trigger)) { closeMega(trigger); }
                    else { openMega(trigger); if (focusable[0]) focusable[0].focus(); }
                    break;
                case 'Escape':
                    closeMega(trigger); trigger.focus();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (!isOpen(trigger)) openMega(trigger);
                    if (focusable[0]) focusable[0].focus();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    if (triggers[i + 1]) triggers[i + 1].focus();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    if (triggers[i - 1]) triggers[i - 1].focus();
                    break;
            }
        });
    });

    triggers.forEach(trigger => {
        const panel = getPanelForTrigger(trigger);
        if (!panel) return;
        panel.addEventListener('keydown', e => {
            const focusable = Array.from(panel.querySelectorAll('a[href], button:not([disabled])'));
            const idx = focusable.indexOf(document.activeElement);
            switch (e.key) {
                case 'Escape':
                    e.preventDefault(); closeMega(trigger); trigger.focus(); break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (idx < focusable.length - 1) focusable[idx + 1].focus();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (idx > 0) { focusable[idx - 1].focus(); }
                    else { closeMega(trigger); trigger.focus(); }
                    break;
                case 'Tab':
                    if (!e.shiftKey && idx === focusable.length - 1) closeMega(trigger);
                    if (e.shiftKey && idx === 0) {
                        e.preventDefault(); closeMega(trigger); trigger.focus();
                    }
                    break;
            }
        });
    });

    // ── Split-trigger parent links: ArrowDown opens sibling panel ──────
    // When a user tabs to "Contact Us" (the link) and presses ArrowDown,
    // open the submenu and move focus into it — mirrors the trigger behaviour.
    if (header) {
        header.querySelectorAll('.plgc-nav__link--parent').forEach(link => {
            link.addEventListener('keydown', e => {
                if (e.key !== 'ArrowDown') return;
                const split = link.closest('.plgc-nav__split');
                const trigger = split ? split.querySelector('.plgc-nav__trigger') : null;
                if (!trigger) return;
                e.preventDefault();
                const panel = getPanelForTrigger(trigger);
                if (!panel) return;
                openMega(trigger);
                const focusable = Array.from(panel.querySelectorAll('a[href], button:not([disabled])'));
                if (focusable[0]) focusable[0].focus();
            });
        });
    }

    // ============================================================
    // SEARCH PANEL — WP ENGINE SMART SEARCH AJAX
    // ============================================================

    const searchToggle  = document.querySelector('.plgc-header__search-toggle');
    const searchPanel   = document.getElementById('plgc-search-panel');
    const searchClose   = searchPanel ? searchPanel.querySelector('.plgc-search-panel__close') : null;
    const searchInput   = searchPanel ? searchPanel.querySelector('.plgc-search-form__input') : null;
    const resultsBox    = document.getElementById('plgc-search-results');

    // Icons inside the toggle button
    const iconSearch = searchToggle ? searchToggle.querySelector('.plgc-icon--search') : null;
    const iconClose  = searchToggle ? searchToggle.querySelector('.plgc-icon--close')  : null;

    // WP REST API base — works with WP Engine Smart Search since it hooks into WP_Query
    const REST_BASE = (window.plgcNav && window.plgcNav.restUrl)
        ? window.plgcNav.restUrl
        : '/wp-json/wp/v2/';

    let searchTimeout = null;
    let activeRequest = null;
    let currentQuery  = '';

    function openSearch() {
        if (!searchPanel) return;
        closeAll();
        searchPanel.removeAttribute('hidden');
        if (searchToggle) {
            searchToggle.setAttribute('aria-expanded', 'true');
            searchToggle.setAttribute('aria-label', 'Close search');
        }
        // Swap to X icon
        if (iconSearch) iconSearch.style.display = 'none';
        if (iconClose)  iconClose.style.display  = 'block';

        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    function closeSearch() {
        if (!searchPanel) return;
        searchPanel.setAttribute('hidden', '');
        if (searchToggle) {
            searchToggle.setAttribute('aria-expanded', 'false');
            searchToggle.setAttribute('aria-label', 'Open search');
        }
        // Swap back to magnifier icon
        if (iconSearch) iconSearch.style.display = '';
        if (iconClose)  iconClose.style.display  = 'none';

        clearResults();
        searchToggle && searchToggle.focus();
    }

    function clearResults() {
        if (!resultsBox) return;
        resultsBox.innerHTML = '';
        resultsBox.setAttribute('hidden', '');
    }

    searchToggle && searchToggle.addEventListener('click', () => {
        searchPanel && !searchPanel.hasAttribute('hidden') ? closeSearch() : openSearch();
    });

    searchClose && searchClose.addEventListener('click', closeSearch);

    searchPanel && searchPanel.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeSearch();
        }
    });

    // Click outside search panel to close
    document.addEventListener('click', e => {
        if (searchPanel && !searchPanel.hasAttribute('hidden') &&
            !searchPanel.contains(e.target) &&
            e.target !== searchToggle) {
            closeSearch();
        }
    });

    // ── Live Search ──────────────────────────────────────────────

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim();
            if (q === currentQuery) return;
            currentQuery = q;

            clearTimeout(searchTimeout);
            if (q.length < 2) {
                clearResults();
                return;
            }

            showStatus('Searching…');

            searchTimeout = setTimeout(() => {
                runSearch(q);
            }, 300);
        });

        // Keyboard navigation between results
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown' && resultsBox && !resultsBox.hasAttribute('hidden')) {
                e.preventDefault();
                const first = resultsBox.querySelector('.plgc-search-results__item a');
                if (first) first.focus();
            }
        });
    }

    // Intercept form submit — run AJAX search instead of navigating away.
    // The green button is purely a design affordance; results appear inline.
    if (searchPanel) {
        const form = searchPanel.querySelector('.plgc-search-form');
        form && form.addEventListener('submit', e => {
            e.preventDefault();
            const q = searchInput ? searchInput.value.trim() : '';
            if (q.length >= 2) {
                clearTimeout(searchTimeout);
                runSearch(q);
            } else if (searchInput) {
                searchInput.focus();
            }
        });
    }

    /**
     * Run a search via WP REST API.
     * WP Engine Smart Search hooks into WP_Query, so the REST search
     * endpoint automatically uses its Elasticsearch backend.
     *
     * We run two parallel requests:
     *   1. Pages + posts (content)
     *   2. Attachments / documents (PDFs, etc.)
     */
    function runSearch(q) {
        if (activeRequest) {
            activeRequest.abort && activeRequest.abort();
        }

        const controller = new AbortController();
        activeRequest = controller;

        const contentParams = new URLSearchParams({
            search: q, per_page: 10, _fields: 'id,title,link,subtype,type',
        });

        // Pull documents directly from the media library — no WP Engine
        // document indexing needed. Targets PDFs and common office formats.
        const mediaParams = new URLSearchParams({
            search: q,
            per_page: 4,
            media_type: 'application',
            _fields: 'id,title,source_url,link,mime_type',
        });

        const contentUrl = REST_BASE + 'search?' + contentParams.toString() + '&type=post&subtype=any';
        const mediaUrl   = REST_BASE + 'media?'  + mediaParams.toString();

        Promise.all([
            fetch(contentUrl, { signal: controller.signal }).then(r => r.ok ? r.json() : []),
            fetch(mediaUrl,   { signal: controller.signal }).then(r => r.ok ? r.json() : []),
        ])
        .then(([pages, rawDocs]) => {
            // Filter documents: only keep items that are actual files
            // (have a real file source_url with an extension, not page URLs
            // injected by the AI Toolkit Custom Search Results feature)
            const fileExtPattern = /\.(pdf|doc|docx|xls|xlsx|csv|ppt|pptx|zip|txt)(\?|$)/i;
            const validDocs = (rawDocs || []).filter(doc => {
                // Must have a source_url pointing to an actual file
                if (!doc.source_url || !fileExtPattern.test(doc.source_url)) return false;
                // Must have a valid document mime_type
                if (!doc.mime_type || !doc.mime_type.startsWith('application/')) return false;
                return true;
            });

            // Deduplicate: remove docs whose title matches a content result
            const contentTitles = new Set(
                (pages || []).map(p => {
                    const t = p.title && p.title.rendered ? p.title.rendered : (p.title || '');
                    return stripHtml(t).toLowerCase().trim();
                })
            );
            const docs = validDocs.filter(doc => {
                const t = doc.title && doc.title.rendered ? doc.title.rendered : (doc.title || '');
                return !contentTitles.has(stripHtml(t).toLowerCase().trim());
            });

            renderResults(q, pages || [], docs);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                showStatus('Search unavailable. Press Enter to search.');
            }
        });
    }

    /**
     * Post-type → friendly category name mapping.
     * Order here determines display order in results dropdown.
     */
    const CATEGORY_MAP = {
        page:               'Pages',
        post:               'News & Updates',
        tribe_events:       'Events',
        awsm_job_openings:  'Job Openings',
        product:            'Products',
    };

    /** SVG icons for result items */
    const ICONS = {
        page:     '<svg class="plgc-search-results__item-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>',
        event:    '<svg class="plgc-search-results__item-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        job:      '<svg class="plgc-search-results__item-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
        product:  '<svg class="plgc-search-results__item-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
        document: '<svg class="plgc-search-results__item-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>',
    };

    function iconForSubtype(subtype) {
        if (subtype === 'tribe_events')      return ICONS.event;
        if (subtype === 'awsm_job_openings') return ICONS.job;
        if (subtype === 'product')           return ICONS.product;
        return ICONS.page;
    }

    function renderResults(q, pages, docs) {
        if (!resultsBox) return;

        const totalCount = pages.length + docs.length;

        if (totalCount === 0) {
            showStatus('No results found for \u201c' + escHtml(q) + '\u201d.');
            return;
        }

        // Group content results by post type
        const groups = {};
        const groupOrder = Object.keys(CATEGORY_MAP);

        pages.forEach(item => {
            const sub = item.subtype || 'page';
            if (!groups[sub]) groups[sub] = [];
            groups[sub].push(item);
        });

        let html = '';

        // Render each category in the defined order
        groupOrder.forEach(key => {
            if (!groups[key] || groups[key].length === 0) return;
            const label = CATEGORY_MAP[key] || capitalise(key);

            html += '<span class="plgc-search-results__group-label">' + escHtml(label) + '</span>';
            html += '<ul class="plgc-search-results__list" role="list">';
            groups[key].forEach(item => {
                html += resultItem(item, 'content');
            });
            html += '</ul>';
        });

        // Catch any post types not in CATEGORY_MAP (future-proofing)
        Object.keys(groups).forEach(key => {
            if (groupOrder.includes(key)) return;
            if (groups[key].length === 0) return;
            const label = capitalise(key.replace(/_/g, ' '));

            html += '<span class="plgc-search-results__group-label">' + escHtml(label) + '</span>';
            html += '<ul class="plgc-search-results__list" role="list">';
            groups[key].forEach(item => {
                html += resultItem(item, 'content');
            });
            html += '</ul>';
        });

        // Documents section (from media library)
        if (docs.length > 0) {
            html += '<span class="plgc-search-results__group-label">Documents</span>';
            html += '<ul class="plgc-search-results__list" role="list">';
            docs.slice(0, 4).forEach(item => {
                html += resultItem(item, 'document');
            });
            html += '</ul>';
        }

        // "See all results" footer
        const searchPageUrl = '/?s=' + encodeURIComponent(q);
        html += '<div class="plgc-search-results__footer">';
        html += '<a href="' + escHtml(searchPageUrl) + '">See all results for \u201c' + escHtml(q) + '\u201d \u2192</a>';
        html += '</div>';

        resultsBox.innerHTML = html;
        resultsBox.removeAttribute('hidden');

        // Keyboard navigation within results
        wireResultKeyboard();
    }

    function resultItem(item, type) {
        const title = item.title && item.title.rendered
            ? item.title.rendered
            : (item.title || 'Untitled');

        // Media library items use source_url (direct file) or link (attachment page)
        const url = item.source_url || item.url || item.link || '#';

        // For documents, show the file type as a subtle label (PDF, Word, etc.)
        let label = '';
        if (type === 'document') {
            const mime = item.mime_type || '';
            if (mime.includes('pdf'))   label = 'PDF';
            else if (mime.includes('word') || mime.includes('document')) label = 'Word';
            else if (mime.includes('excel') || mime.includes('sheet'))   label = 'Spreadsheet';
            else label = 'Document';
        }

        // For documents, link directly to file and signal it opens in new tab
        const targetAttr = type === 'document'
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        const icon = type === 'document'
            ? ICONS.document
            : iconForSubtype(item.subtype || 'page');

        // Document items get a file-type badge; content items don't need a label
        // since the group header identifies them
        const labelHtml = label
            ? '<span class="plgc-search-results__item-type">' + escHtml(label) + '</span>'
            : '';

        return '<li class="plgc-search-results__item">'
            + '<a href="' + escHtml(url) + '"' + targetAttr + '>'
            + icon
            + '<span class="plgc-search-results__item-text">'
            + '<span class="plgc-search-results__item-title">' + escHtml(stripHtml(title)) + '</span>'
            + labelHtml
            + '</span>'
            + '</a>'
            + '</li>';
    }

    function showStatus(msg) {
        if (!resultsBox) return;
        resultsBox.innerHTML = '<p class="plgc-search-results__status">' + escHtml(msg) + '</p>';
        resultsBox.removeAttribute('hidden');
    }

    // Arrow-key navigation between result links
    function wireResultKeyboard() {
        if (!resultsBox) return;
        const links = Array.from(resultsBox.querySelectorAll('a'));

        links.forEach((link, idx) => {
            link.addEventListener('keydown', e => {
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (links[idx + 1]) links[idx + 1].focus();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        if (idx === 0) { searchInput && searchInput.focus(); }
                        else { links[idx - 1].focus(); }
                        break;
                    case 'Escape':
                        closeSearch();
                        break;
                }
            });
        });
    }

    // ── Utilities ────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function stripHtml(str) {
        const tmp = document.createElement('div');
        tmp.innerHTML = str;
        return tmp.textContent || tmp.innerText || str;
    }

    function capitalise(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
    }

    // ============================================================
    // MOBILE DRAWER
    // ============================================================

    const hamburger   = document.querySelector('.plgc-hamburger');
    const mobileNav   = document.getElementById('plgc-mobile-nav');
    const mobileClose = document.querySelector('.plgc-mobile-nav__close');
    const overlay     = document.querySelector('.plgc-mobile-nav__overlay');
    let lastFocus;

    function openDrawer() {
        if (!mobileNav) return;
        lastFocus = document.activeElement;
        mobileNav.classList.add('is-open');
        mobileNav.setAttribute('aria-hidden', 'false');
        overlay && overlay.classList.add('is-visible');
        hamburger && hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        mobileClose && mobileClose.focus();
    }

    function closeDrawer() {
        if (!mobileNav) return;
        mobileNav.classList.remove('is-open');
        mobileNav.setAttribute('aria-hidden', 'true');
        overlay && overlay.classList.remove('is-visible');
        hamburger && hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        if (lastFocus) lastFocus.focus();
    }

    hamburger   && hamburger.addEventListener('click', openDrawer);
    mobileClose && mobileClose.addEventListener('click', closeDrawer);
    overlay     && overlay.addEventListener('click', closeDrawer);
    mobileNav   && mobileNav.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

    // ── Mobile sub-menu expand/collapse ─────────────────────────────────
    //
    // Two button types handle sub-menu toggling:
    //
    //  1. .plgc-mobile-nav__row-btn  — hash (#) items: the ENTIRE row is one
    //     button (label + chevron). Tapping anywhere on the row toggles the sub.
    //
    //  2. .plgc-mobile-nav__expand   — real-URL items: discrete chevron button.
    //     The [data-expand-row] div also delegates clicks to this button so
    //     tapping the label area has the same effect as the chevron.
    //
    // Both types: aria-expanded tracks state; chevron rotates via CSS.

    function toggleSub(btn) {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        const li       = btn.closest('.plgc-mobile-nav__item');
        const sub      = li && li.querySelector('.plgc-mobile-nav__sub');
        btn.setAttribute('aria-expanded', String(!expanded));
        if (sub) {
            expanded ? sub.setAttribute('hidden', '') : sub.removeAttribute('hidden');
        }
    }

    if (mobileNav) {
        // Full-row buttons (hash items)
        mobileNav.querySelectorAll('.plgc-mobile-nav__row-btn').forEach(btn => {
            btn.addEventListener('click', () => toggleSub(btn));
        });

        // Discrete chevron buttons (real-URL items)
        mobileNav.querySelectorAll('.plgc-mobile-nav__expand').forEach(btn => {
            btn.addEventListener('click', () => toggleSub(btn));
        });

        // Row-level click delegation for real-URL items:
        // clicking anywhere on the row (but not on the <a> or the expand button) triggers expand.
        // The expand button already has its own handler above — if we don't bail here,
        // toggleSub fires twice in the same tick (open → immediate close = appears broken).
        mobileNav.querySelectorAll('[data-expand-row]').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('a') || e.target.closest('.plgc-mobile-nav__expand')) return;
                const btn = row.querySelector('.plgc-mobile-nav__expand');
                if (btn) toggleSub(btn);
            });
        });
    }

})();
