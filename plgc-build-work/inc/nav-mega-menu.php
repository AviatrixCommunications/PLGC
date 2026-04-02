<?php
/**
 * PLGC Mega Menu
 *
 * Builds the primary navigation automatically from the WordPress menu
 * structure. No complex CSS classes required — the client just nests
 * items in Appearance → Menus and this code handles the rest.
 *
 * ============================================================
 * HOW TO STRUCTURE THE MENU (for the client)
 * ============================================================
 * In Appearance → Menus, drag items to nest them:
 *
 *   Golf                        ← top-level (no URL needed — use Custom Link → #)
 *     Pro Shop                  ← indent once = column heading
 *       Facilities & Amenities  ← indent twice = sub-link under Pro Shop
 *     Scorecard                 ← another column heading
 *     Golf Rates
 *     Golf Membership
 *       Membership Questions
 *       Membership Application
 *       Membership Agreement & Policies
 *     Golf Lessons
 *
 *   Weddings & Events           ← top-level, links to a page directly
 *     Weddings                  ← simple sub-link
 *     Showers & Events
 *     Meetings
 *
 * ============================================================
 * THE CTA BUTTON — fully client-manageable
 * ============================================================
 * The "Book a Tee Time" button (or whatever it's called at the time)
 * lives in the menu just like any other item. To make a menu item
 * render as the yellow pill button:
 *
 *  1. Go to Appearance → Menus
 *  2. Click "Screen Options" at the top-right of the page
 *  3. Check the box labelled "CSS Classes" — this reveals a hidden field
 *  4. Find the menu item and expand it
 *  5. In the "CSS Classes" field type:  plgc-nav-cta
 *  6. Save Menu
 *
 * After that the Navigation Label becomes the button text and the URL
 * becomes the button link. Updating either one (e.g. changing to
 * "See Upcoming Events" in the off-season) is just editing those two
 * fields and saving — no developer needed.
 *
 * ============================================================
 * AUTOMATIC LAYOUT RULES
 * ============================================================
 *  - Item with class plgc-nav-cta  → yellow pill CTA button
 *  - Item with children            → mega panel trigger + chevron
 *  - Item without children         → plain navigation link
 *  - Each indent-once child        → one column in the mega panel
 *  - Indent-twice items            → sub-links in that column
 *  - Column count                  → automatic (no CSS needed)
 *  - 1–3 columns                   → compact dropdown
 *  - 4+ columns                    → full-width mega panel
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * Render the primary <nav> element.
 * Called from header.php.
 */
function plgc_render_primary_nav() {
    $menu_locations = get_nav_menu_locations();

    if (empty($menu_locations['primary'])) {
        return;
    }

    $menu_obj = wp_get_nav_menu_object($menu_locations['primary']);

    if (! $menu_obj) {
        return;
    }

    $items = wp_get_nav_menu_items($menu_obj->term_id);

    if (! $items) {
        return;
    }

    // wp_nav_menu_objects() is defined in nav-menu-template.php which WordPress
    // loads lazily — it's unavailable when header.php fires. Instead we run
    // wp_setup_nav_menu_item() (always available) to normalise each item, then
    // do our own current-page detection by comparing object IDs against the
    // current queried object and walking up the parent chain to stamp ancestors.
    $items = array_map('wp_setup_nav_menu_item', $items);

    $current_object_id  = get_queried_object_id();
    $current_object     = get_queried_object();
    $current_post_type  = is_singular() ? get_post_type() : '';
    $current_tax        = (is_category() || is_tag() || is_tax()) ? get_queried_object() : null;

    // Index by ID first so we can walk up the parent chain
    $id_map = [];
    foreach ($items as $item) {
        $item->classes   = is_array($item->classes) ? array_filter($item->classes) : [];
        $id_map[$item->ID] = $item;
    }

    // Pass 1: mark current-menu-item on exact matches
    foreach ($items as $item) {
        $is_current = false;

        if ( $item->object === 'page' || $item->object === 'post' || $item->type === 'post_type' ) {
            $is_current = ( (int) $item->object_id === (int) $current_object_id );
        } elseif ( $item->type === 'post_type_archive' ) {
            $is_current = is_post_type_archive( $item->object );
        } elseif ( $item->type === 'taxonomy' ) {
            $is_current = ( $current_tax && (int) $item->object_id === (int) $current_object_id );
        } elseif ( $item->type === 'custom' ) {
            // Custom links: match by URL stripping query strings
            $item_url   = strtok( $item->url,   '?' );
            $current_url = strtok( (is_singular() ? get_permalink() : home_url( add_query_arg( [] ) )), '?' );
            $is_current  = ( $item_url && $item_url !== '#' && rtrim($item_url,'/') === rtrim($current_url,'/') );
        }

        if ( $is_current ) {
            $item->classes[] = 'current-menu-item';
        }
    }

    // Pass 2: bubble up current-menu-ancestor to all parents of current items
    foreach ($items as $item) {
        if ( in_array('current-menu-item', $item->classes, true) ) {
            $parent_id = (int) $item->menu_item_parent;
            while ( $parent_id && isset($id_map[$parent_id]) ) {
                $parent = $id_map[$parent_id];
                if ( ! in_array('current-menu-ancestor', $parent->classes, true) ) {
                    $parent->classes[] = 'current-menu-ancestor';
                }
                $parent_id = (int) $parent->menu_item_parent;
            }
        }
    }

    // Build a nested tree
    $index = [];
    $tree  = [];

    foreach ($items as $item) {
        $item->children = [];
        $index[$item->ID] = $item;
    }

    foreach ($items as $item) {
        if ($item->menu_item_parent && isset($index[$item->menu_item_parent])) {
            $index[$item->menu_item_parent]->children[] = $item;
        } else {
            $tree[] = $item;
        }
    }

    $current_page_id = $current_object_id;

    echo '<nav class="plgc-nav" aria-label="Main navigation">';
    echo '<ul class="plgc-nav__list" role="list">';

    foreach ($tree as $item) {
        $has_children = ! empty($item->children);
        $is_current   = ($item->object_id == $current_page_id);
        $item_classes = is_array($item->classes) ? $item->classes : [];
        $is_cta       = in_array('plgc-nav-cta', $item_classes, true);
        $is_ancestor  = in_array('current-menu-ancestor', $item_classes, true);

        // CTA is rendered in .plgc-header__actions (header.php), not inside the nav list.
        // This keeps the button adjacent to the search icon on the right side.
        if ($is_cta) continue;

        $li_classes = ['plgc-nav__item'];
        if ($has_children) $li_classes[] = 'plgc-nav__item--has-mega';
        if ($is_current)   $li_classes[] = 'plgc-nav__item--current';
        if ($is_ancestor)  $li_classes[] = 'plgc-nav__item--ancestor';

        echo '<li class="' . esc_attr(implode(' ', $li_classes)) . '">';

        if ($has_children) {
            // ── Mega Panel Trigger ──────────────────────────────────────
            $panel_id  = 'plgc-mega-' . $item->ID;
            $is_hash   = (empty($item->url) || $item->url === '#');

            $chevron = '<svg class="plgc-nav__chevron" aria-hidden="true" focusable="false"'
                . ' width="12" height="12" viewBox="0 0 12 12" fill="none">'
                . '<path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8"'
                . ' stroke-linecap="round" stroke-linejoin="round"/>'
                . '</svg>';

            if ($is_hash) {
                // No page link — single button for label + chevron (Golf, Weddings & Events)
                echo '<button class="plgc-nav__trigger"'
                    . ' aria-expanded="false"'
                    . ' aria-haspopup="true"'
                    . ' aria-controls="' . esc_attr($panel_id) . '"'
                    . ($is_ancestor ? ' aria-current="true"' : '')
                    . '>';
                echo esc_html($item->title);
                echo $chevron;
                echo '</button>';
            } else {
                // Real page link — split: <a> navigates, <button> toggles panel
                // (Contact Us, McChesney's Pub & Grill)
                echo '<div class="plgc-nav__split">';
                echo '<a class="plgc-nav__link plgc-nav__link--parent"'
                    . ' href="' . esc_url($item->url) . '"'
                    . ($is_current ? ' aria-current="page"' : '')
                    . '>';
                echo esc_html($item->title);
                echo '</a>';
                echo '<button class="plgc-nav__trigger plgc-nav__trigger--chevron"'
                    . ' aria-expanded="false"'
                    . ' aria-haspopup="true"'
                    . ' aria-controls="' . esc_attr($panel_id) . '"'
                    . ' aria-label="' . esc_attr(sprintf('Show %s submenu', $item->title)) . '"'
                    . '>';
                echo $chevron;
                echo '</button>';
                echo '</div>';
            }

            plgc_render_mega_panel($item, $panel_id);

        } else {
            // ── Plain Navigation Link ───────────────────────────────────
            echo '<a class="plgc-nav__link"'
                . ' href="' . esc_url($item->url) . '"'
                . ($is_current ? ' aria-current="page"' : '')
                . '>';
            echo esc_html($item->title);
            echo '</a>';
        }

        echo '</li>';
    }

    echo '</ul>';
    echo '</nav>';
}

/**
 * Render the mega panel for a top-level item that has children.
 *
 * @param object $parent   The top-level nav item.
 * @param string $panel_id The id attribute for this panel.
 */
function plgc_render_mega_panel($parent, $panel_id) {
    $children    = $parent->children;
    $child_count = count($children);

    // All panels are full-width per design. For small menus (≤3 items) we cap
    // the columns so items don't stretch across the entire bar, but the panel
    // background always spans edge-to-edge.
    $panel_class = 'plgc-mega';

    echo '<div'
        . ' id="' . esc_attr($panel_id) . '"'
        . ' class="' . esc_attr($panel_class) . '"'
        . ' role="region"'
        . ' aria-label="' . esc_attr($parent->title) . ' submenu"'
        . ' hidden'
        . ' style="--mega-cols:' . $child_count . ';"'
        . '>';

    echo '<ul class="plgc-mega__grid" role="list" data-cols="' . $child_count . '">';

    foreach ($children as $child) {
        $has_sub     = ! empty($child->children);
        $child_class = $has_sub ? 'plgc-mega__col plgc-mega__col--group' : 'plgc-mega__col';

        echo '<li class="' . esc_attr($child_class) . '">';

        $is_real_link  = (! empty($child->url) && $child->url !== '#');
        $child_classes = is_array($child->classes) ? $child->classes : [];
        $is_current    = in_array('current-menu-item', $child_classes, true)
                      || in_array('current-menu-ancestor', $child_classes, true)
                      || in_array('current-menu-parent', $child_classes, true);
        $is_silent     = in_array('plgc-col-silent', $child_classes, true);
        $heading_extra = $is_current ? ' current-menu-item' : '';

        if ($is_real_link && ! $is_silent) {
            echo '<a class="plgc-mega__heading' . esc_attr($heading_extra) . '" href="' . esc_url($child->url) . '"'
                . ($is_current ? ' aria-current="page"' : '') . '>'
                . esc_html($child->title) . '</a>';
        } elseif ($is_silent) {
            // Silent heading: render nothing visible (used to group items without a label)
            echo '<span class="plgc-mega__heading plgc-mega__heading--silent" aria-hidden="true"></span>';
        } else {
            echo '<span class="plgc-mega__heading plgc-mega__heading--label">'
                . esc_html($child->title) . '</span>';
        }

        if ($has_sub) {
            echo '<ul class="plgc-mega__sub" role="list">';
            foreach ($child->children as $grandchild) {
                $gc_classes = is_array($grandchild->classes) ? $grandchild->classes : [];
                $gc_current = in_array('current-menu-item', $gc_classes, true)
                           || in_array('current-menu-ancestor', $gc_classes, true)
                           || in_array('current-menu-parent', $gc_classes, true);
                $gc_extra   = $gc_current ? ' current-menu-item' : '';

                echo '<li class="plgc-mega__sub-item">'
                    . '<a class="plgc-mega__sub-link' . esc_attr($gc_extra) . '" href="' . esc_url($grandchild->url) . '"'
                    . ($gc_current ? ' aria-current="page"' : '') . '>'
                    . esc_html($grandchild->title) . '</a>'
                    . '</li>';
            }
            echo '</ul>';
        }

        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

/**
 * Mobile nav walker.
 *
 * Items with plgc-nav-cta are skipped here — the mobile drawer
 * always shows a CTA block at the bottom, pulled from plgc_get_nav_cta().
 *
 * Depth 0 with children  → row with link/label + expand button
 * Depth 0 without children → same link style (consistent appearance)
 * Depth 1                → sub-link (--sub class), 15px SemiBold
 * Depth 1 with children  → sub-link label + children always visible below
 * Depth 2+               → deep sub-link (--deep class), 14px Regular, indented
 */
class PLGC_Mobile_Walker extends Walker_Nav_Menu {

    /**
     * Stores the panel ID for the current depth-0 item so start_lvl()
     * can pick it up and stamp it onto the <ul> id attribute.
     *
     * Declared explicitly to avoid the PHP 8.2+ dynamic property deprecation
     * (and fatal error in 8.3) that would cause wp_nav_menu() to produce no
     * output when the fallback_cb is false.
     *
     * @var string
     */
    protected $_current_panel_id = '';

    public function start_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            // id matches aria-controls on the toggle button above
            $id = ! empty($this->_current_panel_id) ? $this->_current_panel_id : '';
            $output .= '<ul class="plgc-mobile-nav__sub" role="list"'
                . ($id ? ' id="' . esc_attr($id) . '"' : '')
                . ' hidden>';
            $this->_current_panel_id = '';
        } else {
            $output .= '<ul class="plgc-mobile-nav__sub plgc-mobile-nav__sub--nested" role="list">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $item_classes = is_array($item->classes) ? $item->classes : [];

        // CTA items excluded — drawer has its own CTA
        if ($depth === 0 && in_array('plgc-nav-cta', $item_classes, true)) {
            return;
        }

        $has_children = in_array('menu-item-has-children', $item_classes, true);
        $is_current   = in_array('current-menu-item',     $item_classes, true)
                     || in_array('current-menu-ancestor',  $item_classes, true)
                     || in_array('current-menu-parent',    $item_classes, true);
        $is_hash      = (empty($item->url) || $item->url === '#');

        $li_class = 'plgc-mobile-nav__item';
        if ($depth === 0) $li_class .= ' plgc-mobile-nav__item--top';
        $output .= '<li class="' . esc_attr($li_class) . '">';

        if ($depth === 0 && $has_children) {
            $panel_id = 'mobile-sub-' . $item->ID;

            if ($is_hash) {
                // ── Hash item: entire row IS the toggle button ─────────────
                // Single focusable element = full-width touch target.
                // Meets WCAG 2.5.5 (44×44 min) and eliminates the tiny-chevron UX problem.
                $output .= '<button class="plgc-mobile-nav__row-btn"'
                    . ' aria-expanded="false"'
                    . ' aria-controls="' . esc_attr($panel_id) . '">'
                    . '<span class="plgc-mobile-nav__row-label">' . esc_html($item->title) . '</span>'
                    . '<svg class="plgc-mobile-nav__chevron" aria-hidden="true" focusable="false"'
                    . ' width="14" height="14" viewBox="0 0 12 12" fill="none">'
                    . '<path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8"'
                    . ' stroke-linecap="round" stroke-linejoin="round"/>'
                    . '</svg>'
                    . '</button>';
            } else {
                // ── Real URL: link navigates, chevron expands, row is also clickable ──
                $output .= '<div class="plgc-mobile-nav__row" data-expand-row>'
                    . '<a class="plgc-mobile-nav__link" href="' . esc_url($item->url) . '"'
                    . ($is_current ? ' aria-current="page"' : '') . '>'
                    . esc_html($item->title) . '</a>'
                    . '<button class="plgc-mobile-nav__expand"'
                    . ' aria-expanded="false"'
                    . ' aria-controls="' . esc_attr($panel_id) . '"'
                    . ' aria-label="Expand ' . esc_attr($item->title) . ' submenu">'
                    . '<svg aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 12 12" fill="none">'
                    . '<path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8"'
                    . ' stroke-linecap="round" stroke-linejoin="round"/>'
                    . '</svg>'
                    . '</button>'
                    . '</div>';
            }

            // The Walker's start_lvl() adds the <ul> immediately after this —
            // we store the panel_id on the <li> so start_lvl can pick it up.
            // (PHP walkers process depth-0 end before start_lvl, so we embed
            //  a data attribute that start_lvl patches into the <ul id>.)
            // Simpler: just store as instance variable and use in start_lvl.
            $this->_current_panel_id = $panel_id;


        } elseif ($depth === 0) {
            // ── Top-level plain link ───────────────────────────────────
            $output .= '<a class="plgc-mobile-nav__link" href="' . esc_url($item->url) . '"'
                . ($is_current ? ' aria-current="page"' : '') . '>'
                . esc_html($item->title) . '</a>';

        } elseif ($depth === 1) {
            // ── Sub-item (column heading equivalent) ──────────────────
            $link_class = 'plgc-mobile-nav__link plgc-mobile-nav__link--sub';
            if ($is_hash) {
                // Non-linking label — no underline, no colour change on tap
                $output .= '<span class="' . esc_attr($link_class) . ' plgc-mobile-nav__link--label">'
                    . esc_html($item->title) . '</span>';
            } else {
                $active_class = $is_current ? ' current-mobile-item' : '';
                $output .= '<a class="' . esc_attr($link_class . $active_class) . '" href="' . esc_url($item->url) . '"'
                    . ($is_current ? ' aria-current="page"' : '') . '>'
                    . esc_html($item->title) . '</a>';
            }

        } else {
            // ── Deep sub-item (Membership Questions, etc.) ─────────────
            $link_class = 'plgc-mobile-nav__link plgc-mobile-nav__link--deep';
            if ($is_hash) {
                $output .= '<span class="' . esc_attr($link_class) . ' plgc-mobile-nav__link--label">'
                    . esc_html($item->title) . '</span>';
            } else {
                $active_class = $is_current ? ' current-mobile-item' : '';
                $output .= '<a class="' . esc_attr($link_class . $active_class) . '" href="' . esc_url($item->url) . '"'
                    . ($is_current ? ' aria-current="page"' : '') . '>'
                    . esc_html($item->title) . '</a>';
            }
        }
    }

    public function end_el(&$output, $item, $depth = 0, $args = null) {
        $item_classes = is_array($item->classes) ? $item->classes : [];
        if ($depth === 0 && in_array('plgc-nav-cta', $item_classes, true)) {
            return;
        }
        $output .= '</li>';
    }
}

/**
 * Get the CTA button details.
 *
 * Checks the primary menu for an item with the class plgc-nav-cta.
 * Falls back to a sensible default if none is found.
 *
 * Used in header.php for the mobile drawer CTA block.
 *
 * @return array { url: string, label: string, target: string }
 */
function plgc_get_nav_cta() {
    // Default URL auto-appends today's date so the booking widget opens on the right day
    $default_url   = apply_filters(
        'plgc_tee_time_url',
        'https://golfback.com/#/course/dba9546a-1cdf-4c55-8abb-e8bfcb7c6c84/date/' . date('Y-m-d')
    );
    $default_label = apply_filters('plgc_tee_time_label', 'Book a Tee Time');

    $menu_locations = get_nav_menu_locations();
    if (empty($menu_locations['primary'])) {
        return ['url' => $default_url, 'label' => $default_label, 'target' => ''];
    }

    $menu_obj = wp_get_nav_menu_object($menu_locations['primary']);
    if (! $menu_obj) {
        return ['url' => $default_url, 'label' => $default_label, 'target' => ''];
    }

    $items = wp_get_nav_menu_items($menu_obj->term_id);
    if (! $items) {
        return ['url' => $default_url, 'label' => $default_label, 'target' => ''];
    }

    foreach ($items as $item) {
        $classes = is_array($item->classes) ? $item->classes : [];
        if (in_array('plgc-nav-cta', $classes, true)) {
            return [
                'url'    => ! empty($item->url)   ? $item->url   : $default_url,
                'label'  => ! empty($item->title) ? $item->title : $default_label,
                'target' => ($item->target === '_blank') ? '_blank' : '',
            ];
        }
    }

    return ['url' => $default_url, 'label' => $default_label, 'target' => ''];
}
