<?php
/**
 * Accessibility Enhancements
 *
 * Server-side accessibility improvements that apply to all
 * Elementor output. These act as a safety net to catch common
 * WCAG 2.1 AA failures regardless of how content is built.
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * Add 'main' landmark ID to Elementor content area.
 * Ensures skip-link target exists.
 */
function plgc_add_main_content_id($content) {
    if (is_singular() && ! is_admin()) {
        // Add id="main-content" to the first Elementor section if not present
        if (strpos($content, 'id="main-content"') === false) {
            $content = preg_replace(
                '/(<div[^>]*class="[^"]*elementor[^"]*"[^>]*)>/',
                '$1 id="main-content" role="main">',
                $content,
                1 // Only first match
            );
        }
    }
    return $content;
}
add_filter('the_content', 'plgc_add_main_content_id', 20);

/**
 * Ensure all images have alt attributes.
 * Adds empty alt="" to decorative images that are missing it entirely.
 * (WCAG 1.1.1 - Non-text Content)
 */
function plgc_enforce_image_alt($content) {
    if (is_admin()) {
        return $content;
    }

    // Find <img> tags without alt attribute and add empty alt
    $content = preg_replace(
        '/<img(?![^>]*\balt\b)([^>]*)>/i',
        '<img alt=""$1>',
        $content
    );

    return $content;
}
add_filter('the_content', 'plgc_enforce_image_alt', 30);

/**
 * Add aria-label to phone number links.
 * (WCAG 2.4.4 - Link Purpose)
 */
function plgc_enhance_tel_links($content) {
    if (is_admin()) {
        return $content;
    }

    // Add aria-label to tel: links that don't have one
    $content = preg_replace_callback(
        '/<a([^>]*href="tel:([^"]*)"[^>]*)>([^<]*)<\/a>/i',
        function ($matches) {
            if (strpos($matches[1], 'aria-label') !== false) {
                return $matches[0]; // Already has aria-label
            }
            $phone = $matches[3];
            return '<a' . $matches[1] . ' aria-label="Call ' . esc_attr(strip_tags($phone)) . '">' . $matches[3] . '</a>';
        },
        $content
    );

    return $content;
}
add_filter('the_content', 'plgc_enhance_tel_links', 30);

/**
 * Add aria-label to email links.
 * (WCAG 2.4.4 - Link Purpose)
 */
function plgc_enhance_mailto_links($content) {
    if (is_admin()) {
        return $content;
    }

    $content = preg_replace_callback(
        '/<a([^>]*href="mailto:([^"]*)"[^>]*)>([^<]*)<\/a>/i',
        function ($matches) {
            if (strpos($matches[1], 'aria-label') !== false) {
                return $matches[0];
            }
            $email = $matches[3];
            return '<a' . $matches[1] . ' aria-label="Email ' . esc_attr(strip_tags($email)) . '">' . $matches[3] . '</a>';
        },
        $content
    );

    return $content;
}
add_filter('the_content', 'plgc_enhance_mailto_links', 30);

/**
 * Mark external links with screen-reader text.
 * Adds "(opens in new tab)" for target="_blank" links.
 * (WCAG 3.2.5 - Change on Request)
 */
function plgc_announce_external_links($content) {
    if (is_admin()) {
        return $content;
    }

    $content = preg_replace_callback(
        '/<a([^>]*target="_blank"[^>]*)>(.*?)<\/a>/is',
        function ($matches) {
            $inner = $matches[2];
            // Don't add if already has the text
            if (strpos($inner, 'opens in new tab') !== false) {
                return $matches[0];
            }
            return '<a' . $matches[1] . '>' . $inner .
                   '<span class="screen-reader-text"> (opens in new tab)</span></a>';
        },
        $content
    );

    return $content;
}
add_filter('the_content', 'plgc_announce_external_links', 30);

/**
 * Add language attribute to the html tag if missing.
 * (WCAG 3.1.1 - Language of Page)
 */
function plgc_ensure_lang_attribute($output) {
    if (strpos($output, 'lang=') === false) {
        $output = str_replace('<html', '<html lang="en-US"', $output);
    }
    return $output;
}

/**
 * Ensure Elementor icons have accessible labels.
 * Adds aria-hidden="true" to decorative icons without text.
 * (WCAG 1.1.1 - Non-text Content)
 */
function plgc_a11y_elementor_icons($content) {
    if (is_admin()) {
        return $content;
    }

    // Add aria-hidden to icon elements that are decorative
    $content = preg_replace(
        '/<i([^>]*class="[^"]*fa[^"]*"[^>]*)(?!aria-hidden)>/i',
        '<i$1 aria-hidden="true">',
        $content
    );

    return $content;
}
add_filter('the_content', 'plgc_a11y_elementor_icons', 30);

/**
 * Warn editors about heading hierarchy issues.
 * Displays an admin notice if page has heading level skips.
 */
function plgc_heading_hierarchy_check() {
    if (! is_admin() || ! function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (! $screen || $screen->base !== 'post') {
        return;
    }

    global $post;
    if (! $post) {
        return;
    }

    $content = $post->post_content;
    preg_match_all('/<h([1-6])/i', $content, $matches);

    if (empty($matches[1])) {
        return;
    }

    $levels = array_map('intval', $matches[1]);
    $issues = [];

    for ($i = 1; $i < count($levels); $i++) {
        // Check for skipped levels (e.g., H2 to H4)
        if ($levels[$i] > $levels[$i - 1] + 1) {
            $issues[] = sprintf(
                'Heading level skipped: H%d followed by H%d',
                $levels[$i - 1],
                $levels[$i]
            );
        }
    }

    if (! empty($issues)) {
        add_action('admin_notices', function () use ($issues) {
            echo '<div class="notice notice-warning"><p><strong>Accessibility Warning:</strong> ';
            echo 'Heading hierarchy issues detected. Screen readers rely on sequential heading levels (H1 → H2 → H3). ';
            echo 'Issues found: ' . esc_html(implode('; ', $issues));
            echo '</p></div>';
        });
    }
}
add_action('admin_init', 'plgc_heading_hierarchy_check');

/**
 * Add accessible labels to Elementor form widgets.
 * Ensures all form fields have proper label associations.
 * (WCAG 1.3.1 - Info and Relationships, 4.1.2 - Name Role Value)
 */
function plgc_elementor_form_a11y($field, $index, $form) {
    // Ensure required fields have proper aria-required
    if (! empty($field['required'])) {
        $field['custom_attributes'] = isset($field['custom_attributes']) ? $field['custom_attributes'] : '';
        if (strpos($field['custom_attributes'], 'aria-required') === false) {
            $field['custom_attributes'] .= ' aria-required="true"';
        }
    }
    return $field;
}

/**
 * Register accessibility-related custom Elementor controls notice
 * that displays in the editor panel.
 */
function plgc_a11y_editor_notice() {
    if (! did_action('elementor/loaded')) {
        return;
    }

    add_action('elementor/editor/after_enqueue_scripts', function () {
        wp_add_inline_script('elementor-editor', "
            jQuery(window).on('elementor:init', function() {
                // Add a11y reminder in the editor
                console.log('PLGC: Accessibility lockdowns active. Brand colors and fonts enforced.');
            });
        ");
    });
}
add_action('init', 'plgc_a11y_editor_notice');
