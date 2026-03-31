<?php
/**
 * Elementor Lockdowns
 *
 * Restricts Elementor's editing capabilities for non-admin users
 * to maintain WCAG 2.1 AA compliance and brand consistency.
 *
 * APPROACH: This file uses ONLY server-side PHP hooks and Elementor's
 * official APIs (filters, Kit settings, Role Manager, widget registry).
 * No client-side CSS/JS injection into the editor UI — those hacks are
 * fragile and break with Elementor updates (e.g. the V3→V4 transition).
 *
 * @package PLGC
 * @since 1.7.12  Removed fragile client-side editor CSS/JS injections
 *                (color picker hiding, global class button hiding).
 *                Retained all server-side restrictions.
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * COLOR LOCKDOWNS
 * ============================================================
 * Forces Elementor to use only the brand palette.
 * V3: Default color scheme + Kit custom_colors.
 * V4: Kit custom_colors populate the Global Color picker
 *     and Variables can reference them.
 */

/**
 * Register brand colors as Elementor's default color scheme.
 * These show up in the color picker as preset swatches (V3).
 */
function plgc_elementor_default_colors($config) {
    $config['default_scheme_color'] = [
        '1' => '#000000',  // Primary Black
        '2' => '#FFAE40',  // Primary Yellow
        '3' => '#567915',  // Dark Green
        '4' => '#233C26',  // Dark Green Tone
    ];
    return $config;
}
add_filter('elementor/schemes/default_color', 'plgc_elementor_default_colors');

/**
 * Set brand colors as Elementor Kit default palette.
 * V3: Populates the color picker swatches.
 * V4: These become the available Global Colors in the
 *     Classes system and Variables Manager.
 */
function plgc_set_elementor_kit_colors() {
    if (! did_action('elementor/loaded')) {
        return;
    }

    $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
    if (! $kit_id) {
        return;
    }

    $kit = \Elementor\Plugin::$instance->documents->get($kit_id);
    if (! $kit) {
        return;
    }

    $custom_colors = [
        ['_id' => 'plgc_black',            'title' => 'Primary Black',        'color' => '#000000'],
        ['_id' => 'plgc_yellow',           'title' => 'Primary Yellow',       'color' => '#FFAE40'],
        ['_id' => 'plgc_white',            'title' => 'Primary White',        'color' => '#FFFFFF'],
        ['_id' => 'plgc_dark_green',       'title' => 'Dark Green',           'color' => '#567915'],
        ['_id' => 'plgc_light_green',      'title' => 'Light Green',          'color' => '#8C9B5A'],
        ['_id' => 'plgc_very_light_green', 'title' => 'Very Light Green',     'color' => '#E5F0D0'],
        ['_id' => 'plgc_light_blue',       'title' => 'Light Blue',           'color' => '#C2D7FF'],
        ['_id' => 'plgc_dark_blue',        'title' => 'Dark Blue',            'color' => '#102B60'],
        ['_id' => 'plgc_light_yellow',     'title' => 'Light Yellow',         'color' => '#FDBC69'],
        ['_id' => 'plgc_light_grey',       'title' => 'Light Grey',           'color' => '#F2F2F2'],
        ['_id' => 'plgc_medium_grey',      'title' => 'Medium Grey',          'color' => '#E7E4E4'],
        ['_id' => 'plgc_dark_green_tone',  'title' => 'Dark Green Tone',      'color' => '#233C26'],
        ['_id' => 'plgc_med_green_tone',   'title' => 'Medium Green Tone',    'color' => '#2D5032'],
    ];

    $settings = $kit->get_settings();
    $settings['custom_colors'] = $custom_colors;
    $kit->update_settings($settings);
}
add_action('after_switch_theme', 'plgc_set_elementor_kit_colors');

/**
 * ============================================================
 * TYPOGRAPHY LOCKDOWNS
 * ============================================================
 * Register brand fonts and set as Elementor Kit defaults.
 * V4: Kit typography feeds into Global Classes and Variables.
 */

/**
 * Set brand fonts as Elementor Kit typography defaults.
 */
function plgc_set_elementor_kit_fonts() {
    if (! did_action('elementor/loaded')) {
        return;
    }

    $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
    if (! $kit_id) {
        return;
    }

    $kit = \Elementor\Plugin::$instance->documents->get($kit_id);
    if (! $kit) {
        return;
    }

    $custom_fonts = [
        [
            '_id'              => 'plgc_font_primary',
            'title'            => 'Primary (Headings)',
            'font_family'      => 'Libre Baskerville',
            'font_weight'      => '700',
            'font_style'       => 'normal',
        ],
        [
            '_id'              => 'plgc_font_secondary',
            'title'            => 'Secondary (Body)',
            'font_family'      => 'Open Sans',
            'font_weight'      => '400',
            'font_style'       => 'normal',
        ],
    ];

    $settings = $kit->get_settings();
    $settings['custom_typography'] = $custom_fonts;
    $kit->update_settings($settings);
}
add_action('after_switch_theme', 'plgc_set_elementor_kit_fonts');

/**
 * Add self-hosted fonts to Elementor's font list.
 */
function plgc_add_elementor_fonts($additional_fonts) {
    $additional_fonts['Libre Baskerville'] = 'system';
    $additional_fonts['Open Sans']         = 'system';
    return $additional_fonts;
}
add_filter('elementor/fonts/additional_fonts', 'plgc_add_elementor_fonts');

/**
 * Prevent Elementor from loading Google Fonts (we self-host).
 */
add_filter('elementor/frontend/print_google_fonts', '__return_false');

/**
 * ============================================================
 * WIDGET & ELEMENT RESTRICTIONS
 * ============================================================
 * V3: Unregister problematic widgets for non-admin users.
 * V4: Atomic elements are composable (no preset widgets to block),
 *     but we still block the V3 legacy widgets that remain available.
 */

/**
 * Restrict available widgets for non-admin users.
 * V3 widgets that cause a11y issues or allow design breakage.
 */
function plgc_restrict_elementor_widgets($widget_types) {
    if (current_user_can('administrator')) {
        return;
    }

    $blocked_widgets = [
        'html',              // Raw HTML — can break accessibility
        'shortcode',         // Can inject anything
        'alert',             // Often misused, poor a11y defaults
        'counter',           // Animation-heavy, motion issues
        'progress',          // Often missing accessible labels
        'testimonial',       // Poor heading structure by default
        'tabs',              // V3 tabs have a11y issues (V4 Atomic Tabs are fine)
        'toggle',            // Similar issues to tabs
        'sound-cloud',       // Auto-play risk
        'media-carousel',    // Complex a11y, motion concerns
        'lottie',            // Animation, motion concerns
        'code-highlight',    // Not needed for content editors
        'table-of-contents', // Can break heading hierarchy
    ];

    foreach ($blocked_widgets as $widget_name) {
        $widget_types->unregister($widget_name);
    }
}
add_action('elementor/widgets/register', 'plgc_restrict_elementor_widgets', 100);

/**
 * ============================================================
 * ELEMENTOR ROLE MANAGER ENFORCEMENT
 * ============================================================
 */

/**
 * Disable Custom CSS panel for non-admin users.
 * V3: Removes custom_css_pro / custom_css controls.
 * V4: Element-level Custom CSS is a Pro feature —
 *     this removes it from both V3 and V4 contexts.
 */
add_action('elementor/element/after_section_end', function ($element, $section_id) {
    if ('section_custom_css_pro' === $section_id || 'section_custom_css' === $section_id) {
        if (! current_user_can('administrator')) {
            $element->remove_control('custom_css_pro');
            $element->remove_control('custom_css');
        }
    }
}, 10, 2);

/**
 * Set Role Manager restrictions programmatically.
 */
function plgc_enforce_role_manager() {
    $role_manager_options = get_option('elementor_role_manager', []);

    $role_manager_options['editor'] = [
        'design' => '',
    ];

    $role_manager_options['plgc_client'] = [
        'design' => 'restrict',
    ];

    update_option('elementor_role_manager', $role_manager_options);
}
add_action('after_switch_theme', 'plgc_enforce_role_manager');

/**
 * ============================================================
 * ELEMENTOR SETTINGS ENFORCEMENT
 * ============================================================
 */

/**
 * Set Elementor default settings on theme activation.
 * Enables V4-ready features alongside backward compat.
 */
function plgc_set_elementor_defaults() {
    // Disable default colors (use kit/theme colors instead)
    update_option('elementor_disable_color_schemes', 'yes');

    // Disable default fonts (use kit/theme fonts instead)
    update_option('elementor_disable_typography_schemes', 'yes');

    // Set container as default layout
    update_option('elementor_experiment-container', 'active');

    // Enable improved CSS loading
    update_option('elementor_experiment-e_optimized_css_loading', 'active');

    // Disable Google Fonts (we self-host)
    update_option('elementor_google_font', 'no');

    // Enable optimized markup (V4 prerequisite — removes unnecessary wrappers)
    update_option('elementor_experiment-e_optimized_markup', 'active');

    // Disable Elementor usage tracking (prevents Mixpanel SDK load attempts)
    update_option('elementor_allow_tracking', '');
}
add_action('after_switch_theme', 'plgc_set_elementor_defaults');

/**
 * Force Elementor containers to use semantic HTML where possible.
 */
function plgc_elementor_container_defaults($args) {
    if (isset($args['html_tag'])) {
        $args['html_tag'] = 'div';
    }
    return $args;
}
