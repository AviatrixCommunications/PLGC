<?php
/**
 * Media Embed Accessibility
 *
 * Catches common video and audio accessibility issues:
 * - YouTube/Vimeo embeds without captions indicator
 * - iframes without title attributes
 * - Audio elements without transcript references
 * - Auto-playing media
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * Add media embed checks to the content guardrails scanner.
 */
function plgc_media_embed_checks($content) {
    $issues = [];

    // --- iframes without title attribute (WCAG 4.1.2) ---
    preg_match_all('/<iframe[^>]*>/i', $content, $iframes);
    if (! empty($iframes[0])) {
        $untitled = 0;
        foreach ($iframes[0] as $iframe) {
            if (! preg_match('/\btitle\s*=\s*["\'][^"\']+["\']/i', $iframe)) {
                $untitled++;
            }
        }
        if ($untitled > 0) {
            $issues[] = [
                'type'     => 'iframe_title',
                'severity' => 'error',
                'wcag'     => '4.1.2',
                'message'  => $untitled . ' iframe(s) missing a title attribute. Every iframe (video embed, map, etc.) needs a descriptive title so screen reader users know what it contains.',
            ];
        }
    }

    // --- YouTube/Vimeo embeds — reminder about captions ---
    $video_count = 0;
    $video_count += preg_match_all('/youtube\.com|youtu\.be/i', $content, $yt);
    $video_count += preg_match_all('/vimeo\.com/i', $content, $vm);
    $video_count += preg_match_all('/<video[\s>]/i', $content, $vid);

    if ($video_count > 0) {
        $issues[] = [
            'type'     => 'video_captions',
            'severity' => 'warning',
            'wcag'     => '1.2.2',
            'message'  => $video_count . ' video embed(s) detected. All videos must have synchronized captions (WCAG 1.2.2). Auto-generated YouTube captions are not sufficient — they must be reviewed and corrected for accuracy.',
        ];
    }

    // --- Audio elements without transcript ---
    preg_match_all('/<audio[\s>]/i', $content, $audio);
    if (! empty($audio[0])) {
        $issues[] = [
            'type'     => 'audio_transcript',
            'severity' => 'warning',
            'wcag'     => '1.2.1',
            'message'  => count($audio[0]) . ' audio element(s) detected. Pre-recorded audio must have a text transcript (WCAG 1.2.1). Add a transcript link near the audio player.',
        ];
    }

    // --- Autoplay detection ---
    if (preg_match('/autoplay/i', $content)) {
        $issues[] = [
            'type'     => 'autoplay',
            'severity' => 'error',
            'wcag'     => '1.4.2',
            'message'  => 'Autoplay detected. Media that plays automatically for more than 3 seconds must have a pause/stop mechanism or volume control (WCAG 1.4.2). Autoplay can be disorienting for screen reader users.',
        ];
    }

    return $issues;
}

/**
 * Hook into the content guardrails scanner.
 */
function plgc_add_media_embed_checks($issues, $content = '') {
    // This is called from the save hook — we need to integrate with content guardrails
    return $issues;
}

/**
 * Extend the main scan function to include media embed checks.
 * We hook into save_post at a later priority to add our issues.
 */
function plgc_media_embed_on_save($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (! $post || ! in_array($post->post_type, ['page', 'post', 'product', 'tribe_events'], true)) {
        return;
    }

    $media_issues = plgc_media_embed_checks($post->post_content);
    if (empty($media_issues)) {
        return;
    }

    // Merge with existing issues from content guardrails
    $existing = get_post_meta($post_id, '_plgc_a11y_issues', true) ?: [];
    $merged = array_merge($existing, $media_issues);
    update_post_meta($post_id, '_plgc_a11y_issues', $merged);
}
add_action('save_post', 'plgc_media_embed_on_save', 25); // After content guardrails at priority 20

/**
 * Auto-add title to Elementor video widget iframes on front end.
 */
function plgc_fix_iframe_titles($content) {
    // Find iframes without titles and add a generic one
    return preg_replace_callback('/<iframe(?![^>]*\btitle\b)([^>]*)>/i', function ($matches) {
        $attrs = $matches[1];

        // Try to determine a good title from src
        $title = 'Embedded content';
        if (preg_match('/youtube|youtu\.be/i', $attrs)) {
            $title = 'YouTube video player';
        } elseif (preg_match('/vimeo/i', $attrs)) {
            $title = 'Vimeo video player';
        } elseif (preg_match('/google.*map/i', $attrs)) {
            $title = 'Google Maps';
        }

        return '<iframe title="' . esc_attr($title) . '"' . $attrs . '>';
    }, $content);
}
add_filter('the_content', 'plgc_fix_iframe_titles', 99);
add_filter('widget_text', 'plgc_fix_iframe_titles', 99);
