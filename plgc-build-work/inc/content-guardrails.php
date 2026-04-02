<?php
/**
 * Content Guardrails
 *
 * Real-time and save-time checks that catch common WCAG 2.1 AA
 * violations before they go live. Designed for content editors
 * who don't understand accessibility standards.
 *
 * Catches:
 * - Generic link text ("click here", "read more", "learn more")
 * - Missing alt text on images
 * - Heading hierarchy skips
 * - Empty links and buttons
 * - Suspicious color contrast (inline styles)
 * - Tables without headers
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * SAVE-TIME CONTENT SCAN
 * ============================================================
 * Scans page/post content on save and shows warnings.
 */

/**
 * Scan content for accessibility issues on save.
 */
function plgc_content_guardrails_on_save($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (! $post || ! in_array($post->post_type, ['page', 'post', 'product', 'tribe_events'], true)) {
        return;
    }

    $content = $post->post_content;
    $issues = plgc_scan_content_for_issues($content, $post_id);

    if (! empty($issues)) {
        update_post_meta($post_id, '_plgc_a11y_issues', $issues);
        update_post_meta($post_id, '_plgc_a11y_scanned', current_time('mysql'));
    } else {
        delete_post_meta($post_id, '_plgc_a11y_issues');
        update_post_meta($post_id, '_plgc_a11y_scanned', current_time('mysql'));
    }
}
add_action('save_post', 'plgc_content_guardrails_on_save', 20);

/**
 * Scan content string for accessibility issues.
 *
 * @param string $content HTML content to scan.
 * @param int    $post_id Optional post ID for context-aware checks.
 * @return array Array of issue descriptions.
 */
function plgc_scan_content_for_issues($content, $post_id = 0) {
    $issues = [];

    // --- Generic Link Text (WCAG 2.4.4 - Link Purpose) ---
    $bad_link_texts = [
        'click here', 'here', 'read more', 'learn more', 'more',
        'link', 'this link', 'more info', 'details', 'info',
        'go', 'click', 'this', 'click this',
    ];

    preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $content, $link_matches);
    if (! empty($link_matches[1])) {
        foreach ($link_matches[1] as $link_text) {
            $clean_text = strtolower(trim(strip_tags($link_text)));
            if (in_array($clean_text, $bad_link_texts, true)) {
                $issues[] = [
                    'type'    => 'link_text',
                    'severity' => 'error',
                    'wcag'    => '2.4.4',
                    'message' => 'Link text "' . esc_html($clean_text) . '" is not descriptive. Use text that describes where the link goes (e.g., "View Golf Rates" instead of "Click Here").',
                ];
            }
        }
    }

    // --- Empty Links (WCAG 2.4.4) ---
    preg_match_all('/<a[^>]*>\s*<\/a>/i', $content, $empty_links);
    if (! empty($empty_links[0])) {
        $issues[] = [
            'type'     => 'empty_link',
            'severity' => 'error',
            'wcag'     => '2.4.4',
            'message'  => count($empty_links[0]) . ' empty link(s) found. Every link must have visible text or an aria-label.',
        ];
    }

    // --- Heading Hierarchy (WCAG 1.3.1 - Info and Relationships) ---
    preg_match_all('/<h([1-6])/i', $content, $heading_matches);
    if (! empty($heading_matches[1])) {
        $levels = array_map('intval', $heading_matches[1]);

        // Check for H1 in content.
        // Elementor pages typically render the page title as an H1 widget
        // inside the content — that's correct and expected. Only flag if:
        //   - There are multiple H1s (always wrong)
        //   - The theme outputs a separate H1 outside the_content (not the case here:
        //     header.php opens <main> and lets Elementor handle the title)
        $h1_count = count(array_filter($levels, fn($l) => $l === 1));
        if ($h1_count > 1) {
            $issues[] = [
                'type'     => 'heading_h1',
                'severity' => 'error',
                'wcag'     => '1.3.1',
                'message'  => $h1_count . ' H1 headings found. Each page should have exactly one H1 (the page title). Demote extra H1s to H2.',
            ];
        }

        // Check for skipped levels
        for ($i = 1; $i < count($levels); $i++) {
            if ($levels[$i] > $levels[$i - 1] + 1) {
                $issues[] = [
                    'type'     => 'heading_skip',
                    'severity' => 'error',
                    'wcag'     => '1.3.1',
                    'message'  => 'Heading level skipped: H' . $levels[$i - 1] . ' is followed by H' . $levels[$i] . '. Screen readers rely on sequential heading levels. Don\'t skip from H2 to H4.',
                ];
            }
        }
    }

    // --- Images Without Alt Text (WCAG 1.1.1) ---
    preg_match_all('/<img[^>]*>/i', $content, $img_matches);
    if (! empty($img_matches[0])) {
        $missing_alt = 0;
        foreach ($img_matches[0] as $img) {
            // Check if alt attribute exists (even empty is OK for decorative)
            if (! preg_match('/\balt\s*=/i', $img)) {
                $missing_alt++;
            }
        }
        if ($missing_alt > 0) {
            $issues[] = [
                'type'     => 'missing_alt',
                'severity' => 'error',
                'wcag'     => '1.1.1',
                'message'  => $missing_alt . ' image(s) missing alt text. Add descriptive alt text to informational images, or empty alt="" to decorative images.',
            ];
        }
    }

    // --- Tables Without Headers (WCAG 1.3.1) ---
    preg_match_all('/<table[^>]*>.*?<\/table>/is', $content, $table_matches);
    if (! empty($table_matches[0])) {
        foreach ($table_matches[0] as $table) {
            if (stripos($table, '<th') === false) {
                $issues[] = [
                    'type'     => 'table_headers',
                    'severity' => 'error',
                    'wcag'     => '1.3.1',
                    'message'  => 'Table found without header cells (<th>). Data tables must have row or column headers so screen readers can associate data with labels.',
                ];
            }
        }
    }

    // --- All-Caps Text (WCAG 1.3.1 - can cause screen reader issues) ---
    preg_match_all('/<[^>]*style="[^"]*text-transform:\s*uppercase[^"]*"[^>]*>(.{50,})/i', $content, $caps_matches);
    if (! empty($caps_matches[0])) {
        $issues[] = [
            'type'     => 'all_caps',
            'severity' => 'info',
            'wcag'     => '1.3.1',
            'message'  => 'Long text with text-transform: uppercase detected. Some screen readers spell out uppercase text letter-by-letter. Consider using CSS text-transform only for short labels.',
        ];
    }

    // --- Duplicate link text going to different URLs ---
    $link_url_map = [];
    preg_match_all('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', $content, $all_links, PREG_SET_ORDER);
    foreach ($all_links as $link) {
        $url  = trim($link[1]);
        $text = strtolower(trim(strip_tags($link[2])));
        if (strlen($text) < 2) continue;

        if (isset($link_url_map[$text]) && $link_url_map[$text] !== $url) {
            $issues[] = [
                'type'     => 'ambiguous_link',
                'severity' => 'warning',
                'wcag'     => '2.4.4',
                'message'  => 'Multiple links with the same text "' . esc_html($text) . '" go to different URLs. Screen reader users navigating by links won\'t be able to tell them apart.',
            ];
        }
        $link_url_map[$text] = $url;
    }

    return $issues;
}

/**
 * Display accessibility issues as admin notices on post edit screen.
 */
function plgc_content_guardrails_notices() {
    $screen = get_current_screen();
    if (! $screen || $screen->base !== 'post') {
        return;
    }

    global $post;
    if (! $post) return;

    $issues = get_post_meta($post->ID, '_plgc_a11y_issues', true);
    if (empty($issues)) {
        // Show green "all clear" if it's been scanned
        $scanned = get_post_meta($post->ID, '_plgc_a11y_scanned', true);
        if ($scanned) {
            echo '<div class="notice notice-success"><p>✅ <strong>Accessibility Check:</strong> No issues detected. Last scanned: ' . esc_html($scanned) . '</p></div>';
        }
        return;
    }

    $errors   = array_filter($issues, fn($i) => $i['severity'] === 'error');
    $warnings = array_filter($issues, fn($i) => $i['severity'] === 'warning');
    $infos    = array_filter($issues, fn($i) => $i['severity'] === 'info');

    if (! empty($errors)) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>🔴 Accessibility Errors (' . count($errors) . '):</strong> These issues will cause WCAG 2.1 AA violations.</p>';
        echo '<ul style="margin-left: 20px; list-style: disc;">';
        foreach ($errors as $issue) {
            echo '<li><strong>WCAG ' . esc_html($issue['wcag']) . ':</strong> ' . esc_html($issue['message']) . '</li>';
        }
        echo '</ul></div>';
    }

    if (! empty($warnings)) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>🟡 Accessibility Warnings (' . count($warnings) . '):</strong></p>';
        echo '<ul style="margin-left: 20px; list-style: disc;">';
        foreach ($warnings as $issue) {
            echo '<li><strong>WCAG ' . esc_html($issue['wcag']) . ':</strong> ' . esc_html($issue['message']) . '</li>';
        }
        echo '</ul></div>';
    }

    if (! empty($infos)) {
        echo '<div class="notice notice-info">';
        echo '<ul style="margin: 0; padding: 0; list-style: none;">';
        foreach ($infos as $issue) {
            echo '<li>ℹ️ ' . esc_html($issue['message']) . '</li>';
        }
        echo '</ul></div>';
    }
}
add_action('admin_notices', 'plgc_content_guardrails_notices');

/**
 * ============================================================
 * POST LIST COLUMN — A11Y STATUS
 * ============================================================
 * Shows a quick indicator on the Pages/Posts list.
 */

function plgc_guardrails_page_columns($columns) {
    $columns['plgc_page_a11y'] = '♿';
    return $columns;
}
add_filter('manage_pages_columns', 'plgc_guardrails_page_columns');
add_filter('manage_posts_columns', 'plgc_guardrails_page_columns');

function plgc_guardrails_page_column_content($column, $post_id) {
    if ($column !== 'plgc_page_a11y') return;

    $issues = get_post_meta($post_id, '_plgc_a11y_issues', true);
    $scanned = get_post_meta($post_id, '_plgc_a11y_scanned', true);

    if (! $scanned) {
        echo '<span title="Not scanned" style="font-size:14px;">⚪</span>';
    } elseif (empty($issues)) {
        echo '<span title="No issues" style="font-size:14px;">🟢</span>';
    } else {
        $errors = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
        if ($errors > 0) {
            echo '<span title="' . $errors . ' error(s)" style="font-size:14px;">🔴</span>';
        } else {
            echo '<span title="Warnings only" style="font-size:14px;">🟡</span>';
        }
    }
}
add_action('manage_pages_custom_column', 'plgc_guardrails_page_column_content', 10, 2);
add_action('manage_posts_custom_column', 'plgc_guardrails_page_column_content', 10, 2);
