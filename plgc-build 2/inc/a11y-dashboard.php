<?php
/**
 * Accessibility Dashboard
 *
 * A dedicated admin page giving editors a single view of
 * their site's accessibility health — page scan results,
 * document compliance, outstanding issues, and a setup
 * checklist for new editors.
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

/**
 * Register the dashboard page.
 */
function plgc_a11y_dashboard_menu() {
    add_menu_page(
        'Accessibility',
        '♿ Accessibility',
        'edit_pages',
        'plgc-accessibility',
        'plgc_a11y_dashboard_page',
        'dashicons-universal-access-alt',
        3
    );
}
add_action('admin_menu', 'plgc_a11y_dashboard_menu');

/**
 * Dashboard page.
 */
function plgc_a11y_dashboard_page() {
    global $wpdb;

    // --- Gather stats ---

    // Pages with issues
    $pages_with_errors = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_plgc_a11y_issues'
         WHERE p.post_type IN ('page','post') AND p.post_status = 'publish'
         AND pm.meta_value != '' AND pm.meta_value != 'a:0:{}'"
    );

    $pages_clean = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_plgc_a11y_scanned'
         LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_plgc_a11y_issues'
         WHERE p.post_type IN ('page','post') AND p.post_status = 'publish'
         AND (pm2.meta_value IS NULL OR pm2.meta_value = '' OR pm2.meta_value = 'a:0:{}')"
    );

    $pages_unscanned = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_plgc_a11y_scanned'
         WHERE p.post_type IN ('page','post') AND p.post_status = 'publish'
         AND pm.meta_value IS NULL"
    );

    $total_pages = $pages_with_errors + $pages_clean + $pages_unscanned;

    // Document stats
    $docs_compliant = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_plgc_a11y_status' AND meta_value = 'compliant'");
    $docs_noncompliant = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_plgc_a11y_status' AND meta_value = 'non_compliant'");
    $docs_unknown = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_plgc_a11y_status' AND meta_value = 'unknown'");
    $docs_pending_review = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_plgc_lifecycle' AND meta_value = 'review'");

    // Images missing alt text (excluding decorative images)
    $images_no_alt = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
         LEFT JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = '_plgc_decorative'
         WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
         AND p.post_mime_type LIKE 'image/%'
         AND (pm.meta_value IS NULL OR pm.meta_value = '')
         AND (pd.meta_value IS NULL OR pd.meta_value != '1')"
    );

    // Access requests
    $access_requests = get_option('plgc_docmgr_access_requests', []);
    $recent_requests = array_filter($access_requests, function ($r) {
        return isset($r['date']) && strtotime($r['date']) > strtotime('-30 days');
    });

    // Calculate overall health score (rough)
    $score = 100;
    if ($total_pages > 0) {
        $score -= round(($pages_with_errors / $total_pages) * 30);
        $score -= round(($pages_unscanned / $total_pages) * 10);
    }
    if ($docs_noncompliant > 0) $score -= min(20, $docs_noncompliant * 3);
    if ($images_no_alt > 10) $score -= min(20, round($images_no_alt / 2));
    if ($docs_pending_review > 0) $score -= min(10, $docs_pending_review * 2);
    $score = max(0, min(100, $score));

    $score_color = $score >= 80 ? '#567915' : ($score >= 50 ? '#FFAE40' : '#d63638');

    // Settings
    $settings = get_option('plgc_docmgr_settings', []);
    $deadline = $settings['compliance_deadline'] ?? 'April 24, 2026';
    $days_until = max(0, floor((strtotime($deadline) - time()) / 86400));
    ?>
    <div class="wrap">
        <h1>♿ Accessibility Dashboard</h1>
        <p style="font-size: 14px; color: #666; margin-bottom: 24px;">
            Overview of your site's WCAG 2.1 AA compliance status. Title II deadline: <strong><?php echo esc_html($deadline); ?></strong>
            <?php if ($days_until > 0) : ?>
                <span style="background: <?php echo $days_until < 90 ? '#d63638' : ($days_until < 180 ? '#FFAE40' : '#567915'); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 8px;">
                    <?php echo $days_until; ?> days remaining
                </span>
            <?php else : ?>
                <span style="background: #d63638; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 8px;">
                    Deadline passed — compliance required
                </span>
            <?php endif; ?>
        </p>

        <!-- HEALTH SCORE -->
        <div style="display: grid; grid-template-columns: 200px 1fr; gap: 24px; margin-bottom: 32px;">
            <div style="text-align: center; padding: 24px; background: #fff; border: 1px solid #e7e4e4; border-radius: 8px;">
                <div style="font-size: 48px; font-weight: 700; color: <?php echo $score_color; ?>; line-height: 1;"><?php echo $score; ?></div>
                <div style="font-size: 13px; color: #666; margin-top: 4px;">Health Score</div>
                <div style="margin-top: 8px; height: 6px; background: #f0f0f0; border-radius: 3px; overflow: hidden;">
                    <div style="height: 100%; width: <?php echo $score; ?>%; background: <?php echo $score_color; ?>; border-radius: 3px;"></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                <div style="padding: 16px; background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #567915;"><?php echo $pages_clean; ?></div>
                    <div style="font-size: 12px; color: #666;">Pages Clean</div>
                </div>
                <div style="padding: 16px; background: <?php echo $pages_with_errors ? '#fff5f5' : '#fff'; ?>; border: 1px solid <?php echo $pages_with_errors ? '#d63638' : '#e7e4e4'; ?>; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: <?php echo $pages_with_errors ? '#d63638' : '#567915'; ?>;"><?php echo $pages_with_errors; ?></div>
                    <div style="font-size: 12px; color: #666;">Pages with Issues</div>
                </div>
                <div style="padding: 16px; background: <?php echo $docs_noncompliant ? '#fff5f5' : '#fff'; ?>; border: 1px solid <?php echo $docs_noncompliant ? '#d63638' : '#e7e4e4'; ?>; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: <?php echo $docs_noncompliant ? '#d63638' : '#567915'; ?>;"><?php echo $docs_noncompliant; ?></div>
                    <div style="font-size: 12px; color: #666;">Docs Non-Compliant</div>
                </div>
                <div style="padding: 16px; background: <?php echo $images_no_alt > 0 ? '#fffbeb' : '#fff'; ?>; border: 1px solid <?php echo $images_no_alt > 0 ? '#FFAE40' : '#e7e4e4'; ?>; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: <?php echo $images_no_alt > 0 ? '#856404' : '#567915'; ?>;"><?php echo $images_no_alt; ?></div>
                    <div style="font-size: 12px; color: #666;">Images Missing Alt</div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">

            <!-- PAGES WITH ISSUES -->
            <div style="background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; padding: 20px;">
                <h2 style="margin-top: 0; font-size: 16px;">📝 Pages Needing Attention</h2>
                <?php
                $problem_pages = get_posts([
                    'post_type'   => ['page', 'post'],
                    'post_status' => 'publish',
                    'meta_key'    => '_plgc_a11y_issues',
                    'numberposts' => 8,
                    'orderby'     => 'modified',
                    'order'       => 'DESC',
                ]);

                $shown = 0;
                if (! empty($problem_pages)) :
                    echo '<ul style="margin: 0; padding: 0; list-style: none;">';
                    foreach ($problem_pages as $pp) :
                        $issues = get_post_meta($pp->ID, '_plgc_a11y_issues', true);
                        if (empty($issues)) continue;
                        $errors = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
                        $warnings = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));
                        $shown++;
                        ?>
                        <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                            <a href="<?php echo get_edit_post_link($pp->ID); ?>"><?php echo esc_html($pp->post_title); ?></a>
                            <span style="font-size: 12px;">
                                <?php if ($errors) : ?><span style="color: #d63638; font-weight: 600;"><?php echo $errors; ?> error<?php echo $errors > 1 ? 's' : ''; ?></span><?php endif; ?>
                                <?php if ($errors && $warnings) echo ' · '; ?>
                                <?php if ($warnings) : ?><span style="color: #856404;"><?php echo $warnings; ?> warning<?php echo $warnings > 1 ? 's' : ''; ?></span><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach;
                    echo '</ul>';
                endif;

                if ($shown === 0) {
                    echo '<p style="color: #567915; margin: 0;">✅ No pages with accessibility issues found.</p>';
                }

                if ($pages_unscanned > 0) {
                    echo '<p style="margin-top: 12px; padding: 8px; background: #f9f9f9; border-radius: 4px; font-size: 12px; color: #666;">';
                    echo '⚪ ' . $pages_unscanned . ' page(s) haven\'t been scanned yet. Open and save each page to trigger a scan.</p>';
                }
                ?>
            </div>

            <!-- DOCUMENTS NEEDING ATTENTION -->
            <div style="background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; padding: 20px;">
                <h2 style="margin-top: 0; font-size: 16px;">📄 Documents Needing Attention</h2>

                <?php if ($docs_noncompliant > 0) : ?>
                    <p style="margin: 0 0 8px;">
                        <a href="<?php echo admin_url('upload.php?plgc_a11y_filter=non_compliant&mode=list'); ?>"
                           style="color: #d63638; font-weight: 600;">
                            🔴 <?php echo $docs_noncompliant; ?> non-compliant document(s)
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($docs_unknown > 0) : ?>
                    <p style="margin: 0 0 8px;">
                        <a href="<?php echo admin_url('upload.php?plgc_a11y_filter=unknown&mode=list'); ?>">
                            ⚪ <?php echo $docs_unknown; ?> document(s) not yet checked
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($docs_pending_review > 0) : ?>
                    <p style="margin: 0 0 8px;">
                        <a href="<?php echo admin_url('upload.php?plgc_lifecycle=review&mode=list'); ?>"
                           style="color: #856404; font-weight: 600;">
                            ⚠️ <?php echo $docs_pending_review; ?> document(s) pending retention review
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($docs_compliant > 0) : ?>
                    <p style="margin: 0 0 8px; color: #567915;">
                        ✅ <?php echo $docs_compliant; ?> compliant document(s)
                    </p>
                <?php endif; ?>

                <?php if ($docs_noncompliant === 0 && $docs_unknown === 0 && $docs_pending_review === 0) : ?>
                    <p style="color: #567915; margin: 0;">✅ All documents are in good shape.</p>
                <?php endif; ?>

                <?php if (! empty($recent_requests)) : ?>
                    <div style="margin-top: 12px; padding: 8px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                        📬 <?php echo count($recent_requests); ?> accessible document request(s) in the last 30 days.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Hook for Monsido / Web Governance integration panels
        if (function_exists('plgc_monsido_dashboard_panels')) {
            plgc_monsido_dashboard_panels();
        }
        ?>

        <!-- EDITOR QUICK REFERENCE -->
        <div style="background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
            <h2 style="margin-top: 0; font-size: 16px;">📚 Quick Reference for Editors</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; font-size: 13px;">
                <div>
                    <h3 style="font-size: 14px; color: #233C26; margin-top: 0;">Images</h3>
                    <p style="margin: 0;">Every informational image needs <strong>alt text</strong> describing what it shows. Decorative images (backgrounds, dividers) should have empty alt text. Never use "image of" or "photo of" — just describe the content.</p>
                </div>
                <div>
                    <h3 style="font-size: 14px; color: #233C26; margin-top: 0;">Links</h3>
                    <p style="margin: 0;">Link text must describe where the link goes. Never use "click here" or "read more" alone. Good: "View 2026 Golf Rates." Bad: "Click here." If a link opens a new tab, that should be indicated.</p>
                </div>
                <div>
                    <h3 style="font-size: 14px; color: #233C26; margin-top: 0;">Headings</h3>
                    <p style="margin: 0;">The page title is H1. Content sections start at <strong>H2</strong>. Subsections use H3. Never skip levels (H2 → H4). Never use headings just to make text bigger — use the design system heading styles instead.</p>
                </div>
                <div>
                    <h3 style="font-size: 14px; color: #233C26; margin-top: 0;">Documents</h3>
                    <p style="margin: 0;">All PDFs and Word docs must be accessible (tagged, with reading order). Use the Clarity API scan on upload. Non-compliant docs get flagged — send to Allyant for remediation or replace with an accessible version.</p>
                </div>
                <div>
                    <h3 style="font-size: 14px; color: #233C26; margin-top: 0;">Color & Contrast</h3>
                    <p style="margin: 0;">Stick to the brand color palette — it's been tested for contrast. Never put light text on light backgrounds or dark text on dark. Minimum contrast ratio: <strong>4.5:1</strong> for text, 3:1 for large text.</p>
                </div>
                <div>
                    <h3 style="font-size: 14px; color: #233C26; margin-top: 0;">Video & Audio</h3>
                    <p style="margin: 0;">All videos need <strong>captions</strong>. Pre-recorded audio needs a <strong>transcript</strong>. Auto-generated captions (YouTube) are not sufficient — they must be reviewed for accuracy.</p>
                </div>
            </div>
        </div>

        <!-- SETUP CHECKLIST -->
        <?php if (current_user_can('manage_options')) : ?>
        <div style="background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; padding: 20px;">
            <h2 style="margin-top: 0; font-size: 16px;">🔧 Compliance Setup Checklist</h2>
            <p style="font-size: 13px; color: #666;">Admin-only checklist to ensure the site is properly configured for Title II compliance.</p>

            <?php
            $checks = [
                ['label' => 'Compliance deadline set', 'done' => ! empty($settings['compliance_deadline'])],
                ['label' => 'Archive page created and selected', 'done' => ! empty($settings['archive_page']) && get_post($settings['archive_page'])],
                ['label' => 'Notification email configured', 'done' => ! empty($settings['notify_email'])],
                ['label' => 'Clarity API connected', 'done' => get_option('plgc_clarity_enabled', 0)],
                ['label' => 'Document categories configured', 'done' => ! empty(get_option('plgc_docmgr_categories', []))],
                ['label' => 'Accessibility statement page exists', 'done' => plgc_check_page_exists('accessibility')],
                ['label' => 'All published pages scanned', 'done' => $pages_unscanned === 0],
                ['label' => 'No non-compliant public documents', 'done' => $docs_noncompliant === 0],
                ['label' => 'All images have alt text', 'done' => $images_no_alt === 0],
                ['label' => 'Monsido Web Governance connected', 'done' => ! empty(get_option('plgc_monsido_data', []))],
            ];
            ?>
            <ul style="margin: 0; padding: 0; list-style: none;">
                <?php foreach ($checks as $check) : ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px;">
                        <?php echo $check['done']
                            ? '<span style="color: #567915; font-weight: 600;">✅</span>'
                            : '<span style="color: #d63638;">☐</span>'; ?>
                        &nbsp; <?php echo esc_html($check['label']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p style="margin-top: 16px;">
                <a href="<?php echo admin_url('options-general.php?page=plgc-docmgr'); ?>" class="button">Compliance Suite Settings</a>
            </p>
        </div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Helper: check if a page with a slug keyword exists.
 */
function plgc_check_page_exists($slug_keyword) {
    $pages = get_posts([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'numberposts' => 1,
        's'           => $slug_keyword,
    ]);
    return ! empty($pages);
}
