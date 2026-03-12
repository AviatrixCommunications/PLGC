<?php
/**
 * Alt Text Enforcement
 *
 * The single biggest source of WCAG 1.1.1 violations at airports
 * is images without alt text. This creates friction at the moment
 * of upload and before publishing to force the editor to think about it.
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * FEATURED IMAGE CHECK ON PUBLISH
 * ============================================================
 * Prevent publishing if the featured image has no alt text.
 */
function plgc_check_featured_image_alt($new_status, $old_status, $post) {
    if ($new_status !== 'publish' || ! in_array($post->post_type, ['page', 'post'], true)) {
        return;
    }

    $thumbnail_id = get_post_thumbnail_id($post->ID);
    if (! $thumbnail_id) {
        return;
    }

    $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
    if (empty(trim($alt))) {
        // Don't block admins, but show a persistent warning
        if (! current_user_can('administrator')) {
            // Revert to draft
            remove_action('transition_post_status', 'plgc_check_featured_image_alt', 10);
            wp_update_post(['ID' => $post->ID, 'post_status' => 'draft']);
            add_action('transition_post_status', 'plgc_check_featured_image_alt', 10, 3);

            set_transient('plgc_alt_block_' . $post->ID, true, 60);
        } else {
            set_transient('plgc_alt_warning_' . $post->ID, true, 60);
        }
    }
}
add_action('transition_post_status', 'plgc_check_featured_image_alt', 10, 3);

/**
 * Show the block/warning notice.
 */
function plgc_alt_block_notice() {
    global $post;
    if (! $post) return;

    if (get_transient('plgc_alt_block_' . $post->ID)) {
        delete_transient('plgc_alt_block_' . $post->ID);
        echo '<div class="notice notice-error"><p>';
        echo '<strong>🚫 Publishing blocked:</strong> The featured image does not have alt text. ';
        echo 'This is required for WCAG 2.1 AA compliance (Success Criterion 1.1.1). ';
        echo 'Please add descriptive alt text to the featured image in the Media Library before publishing.';
        echo '</p></div>';
    }

    if (get_transient('plgc_alt_warning_' . $post->ID)) {
        delete_transient('plgc_alt_warning_' . $post->ID);
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>⚠️ Accessibility warning:</strong> The featured image does not have alt text. ';
        echo 'Please add descriptive alt text to maintain WCAG 2.1 AA compliance.';
        echo '</p></div>';
    }
}
add_action('admin_notices', 'plgc_alt_block_notice');

/**
 * ============================================================
 * MEDIA UPLOAD — ALT TEXT PROMPT
 * ============================================================
 * Adds a prominent reminder in the media uploader and
 * highlights the alt text field when it's empty.
 */
function plgc_alt_text_upload_scripts() {
    $screen = get_current_screen();
    if (! $screen) return;

    // Load on any screen that might have the media uploader
    ?>
    <style>
        /* Highlight empty alt text fields in media modal */
        .attachment-details .setting[data-setting="alt"] input[value=""],
        .attachment-details .setting[data-setting="alt"] input:placeholder-shown {
            border: 2px solid #d63638 !important;
            background: #fff5f5 !important;
        }

        /* Alt text reminder banner in media modal */
        .plgc-alt-reminder {
            background: #fff3cd;
            border-left: 4px solid #FFAE40;
            padding: 8px 12px;
            margin: 8px 0 12px;
            font-size: 12px;
            line-height: 1.4;
        }
        .plgc-alt-reminder strong {
            color: #856404;
        }

        /* Green border when alt text is filled */
        .attachment-details .setting[data-setting="alt"] input:not([value=""]):not(:placeholder-shown) {
            border: 2px solid #567915 !important;
            background: #f8fff0 !important;
        }

        /* Media library grid view — badge on images missing alt */
        .attachment.plgc-no-alt::after {
            content: "No Alt Text";
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: #d63638;
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 2px;
            font-weight: 600;
            pointer-events: none;
        }
    </style>
    <script>
    jQuery(document).ready(function($) {
        // Inject alt text reminder into attachment details when they open
        if (typeof wp !== 'undefined' && wp.media) {
            wp.media.view.Attachment.Details.prototype.on('ready', function() {
                var $alt = this.$('.setting[data-setting="alt"]');
                if ($alt.length && !$alt.prev('.plgc-alt-reminder').length) {
                    $alt.before(
                        '<div class="plgc-alt-reminder">' +
                        '<strong>♿ Alt text is required.</strong> Describe what this image shows. ' +
                        'Keep it concise but meaningful. ' +
                        'If purely decorative, use the checkbox in the Accessibility section below.' +
                        '</div>'
                    );
                }
            });
        }

        // Mark images without alt text in grid view
        function markMissingAlt() {
            $('.attachments-browser .attachment').each(function() {
                var $this = $(this);
                if ($this.hasClass('plgc-checked')) return;
                $this.addClass('plgc-checked');
                var type = $this.find('.type').text() || '';
                if (type.toLowerCase() !== 'image') return;

                // Check alt via the model if available
                var model = $this.data('model') || $this.find('.thumbnail').data('model');
                // Fallback: we rely on the CSS class added server-side
            });
        }

        // Run on media modal open
        $(document).on('click', '.upload-php .attachments .attachment, .media-modal .attachments .attachment', function() {
            setTimeout(function() {
                var $input = $('.attachment-details .setting[data-setting="alt"] input');
                if ($input.length && !$input.val()) {
                    $input.focus();
                }
            }, 300);
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'plgc_alt_text_upload_scripts');

/**
 * ============================================================
 * PDF DOWNLOAD LINK ENHANCEMENT
 * ============================================================
 * Auto-append file type and size to links pointing to PDFs
 * so screen reader users know what they're downloading.
 * (WCAG 2.4.4 - Link Purpose)
 */
function plgc_enhance_pdf_links($content) {
    if (is_admin()) return $content;

    return preg_replace_callback(
        '/<a([^>]*href="([^"]*\.pdf)"[^>]*)>(.*?)<\/a>/is',
        function ($matches) {
            $attrs     = $matches[1];
            $url       = $matches[2];
            $link_text = $matches[3];

            // Don't double-process
            if (strpos($link_text, 'PDF') !== false || strpos($attrs, 'plgc-enhanced') !== false) {
                return $matches[0];
            }

            // Try to get file size
            $attachment_id = attachment_url_to_postid($url);
            $size_text = '';
            if ($attachment_id) {
                $file_path = get_attached_file($attachment_id);
                if ($file_path && file_exists($file_path)) {
                    $size_text = ', ' . size_format(filesize($file_path));
                }
            }

            return '<a' . $attrs . ' plgc-enhanced="true">' . $link_text .
                   '<span class="screen-reader-text"> (PDF' . esc_html($size_text) . ')</span>' .
                   '<span aria-hidden="true" style="font-size: 0.8em; color: #666; margin-left: 4px;">[PDF' . esc_html($size_text) . ']</span>' .
                   '</a>';
        },
        $content
    );
}
add_filter('the_content', 'plgc_enhance_pdf_links', 35);

/**
 * ============================================================
 * BULK PAGE SCANNER
 * ============================================================
 * Admin action to scan all published pages at once rather
 * than waiting for individual saves.
 */
function plgc_bulk_scanner_page() {
    add_submenu_page(
        'plgc-accessibility',
        'Bulk Scan Pages',
        'Bulk Scanner',
        'manage_options',
        'plgc-bulk-scan',
        'plgc_bulk_scanner_render'
    );
}
add_action('admin_menu', 'plgc_bulk_scanner_page');

function plgc_bulk_scanner_render() {
    $running = isset($_POST['plgc_run_bulk_scan']) && wp_verify_nonce($_POST['_wpnonce'], 'plgc_bulk_scan');
    $results = [];

    if ($running) {
        $posts = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $p) {
            $issues = plgc_scan_content_for_issues($p->post_content);

            // Also run media embed checks
            if (function_exists('plgc_media_embed_checks')) {
                $media_issues = plgc_media_embed_checks($p->post_content);
                $issues = array_merge($issues, $media_issues);
            }

            if (! empty($issues)) {
                update_post_meta($p->ID, '_plgc_a11y_issues', $issues);
            } else {
                delete_post_meta($p->ID, '_plgc_a11y_issues');
            }
            update_post_meta($p->ID, '_plgc_a11y_scanned', current_time('mysql'));

            $results[] = [
                'id'     => $p->ID,
                'title'  => $p->post_title,
                'type'   => $p->post_type,
                'issues' => $issues,
            ];
        }
    }

    ?>
    <div class="wrap">
        <h1>Bulk Accessibility Scanner</h1>
        <p>Scans all published pages and posts for WCAG 2.1 AA issues. This runs the same checks
           that normally happen on individual page saves, across the entire site at once.</p>

        <form method="post">
            <?php wp_nonce_field('plgc_bulk_scan'); ?>
            <p>
                <button type="submit" name="plgc_run_bulk_scan" value="1" class="button button-primary button-hero">
                    🔍 Scan All Published Content
                </button>
            </p>
        </form>

        <?php if ($running && ! empty($results)) :
            $with_issues = array_filter($results, fn($r) => ! empty($r['issues']));
            $clean = array_filter($results, fn($r) => empty($r['issues']));
            ?>
            <div style="margin-top: 24px;">
                <h2>Scan Complete — <?php echo count($results); ?> pages scanned</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 24px;">
                    <div style="padding: 16px; background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700;"><?php echo count($results); ?></div>
                        <div style="font-size: 13px; color: #666;">Total Scanned</div>
                    </div>
                    <div style="padding: 16px; background: #f8fff0; border: 1px solid #567915; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700; color: #567915;"><?php echo count($clean); ?></div>
                        <div style="font-size: 13px; color: #666;">Clean</div>
                    </div>
                    <div style="padding: 16px; background: <?php echo count($with_issues) ? '#fff5f5' : '#f8fff0'; ?>; border: 1px solid <?php echo count($with_issues) ? '#d63638' : '#567915'; ?>; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700; color: <?php echo count($with_issues) ? '#d63638' : '#567915'; ?>;"><?php echo count($with_issues); ?></div>
                        <div style="font-size: 13px; color: #666;">With Issues</div>
                    </div>
                </div>

                <?php if (! empty($with_issues)) : ?>
                    <h3 style="color: #d63638;">Pages with Issues</h3>
                    <table class="widefat striped" style="margin-bottom: 24px;">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Type</th>
                                <th>Errors</th>
                                <th>Warnings</th>
                                <th>Issues</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($with_issues as $r) :
                                $errors = count(array_filter($r['issues'], fn($i) => $i['severity'] === 'error'));
                                $warnings = count(array_filter($r['issues'], fn($i) => $i['severity'] !== 'error'));
                                ?>
                                <tr>
                                    <td><a href="<?php echo get_edit_post_link($r['id']); ?>"><?php echo esc_html($r['title']); ?></a></td>
                                    <td><?php echo esc_html(ucfirst($r['type'])); ?></td>
                                    <td style="color: #d63638; font-weight: <?php echo $errors ? '600' : '400'; ?>;"><?php echo $errors; ?></td>
                                    <td style="color: #856404;"><?php echo $warnings; ?></td>
                                    <td style="font-size: 12px;">
                                        <?php
                                        $summaries = array_map(fn($i) => $i['message'], array_slice($r['issues'], 0, 3));
                                        echo esc_html(implode(' · ', $summaries));
                                        if (count($r['issues']) > 3) echo ' ...+' . (count($r['issues']) - 3) . ' more';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (! empty($clean)) : ?>
                    <details>
                        <summary style="cursor: pointer; color: #567915; font-weight: 600;">
                            ✅ <?php echo count($clean); ?> clean pages (click to expand)
                        </summary>
                        <ul style="margin-top: 8px; columns: 2;">
                            <?php foreach ($clean as $r) : ?>
                                <li><?php echo esc_html($r['title']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
