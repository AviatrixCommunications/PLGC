<?php
/**
 * Settings Page
 *
 * Admin UI for managing document categories, retention periods,
 * archive behavior, and Clarity API credentials.
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

/**
 * Register settings page.
 */
function plgc_docmgr_settings_menu() {
    add_options_page(
        'Compliance Suite',
        'Compliance Suite',
        'manage_options',
        'plgc-docmgr',
        'plgc_docmgr_settings_page'
    );
}
add_action('admin_menu', 'plgc_docmgr_settings_menu');

/**
 * Handle settings form submissions.
 */
function plgc_docmgr_handle_settings() {
    if (! isset($_POST['plgc_docmgr_save']) || ! current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('plgc_docmgr_settings');

    $tab = sanitize_text_field($_POST['plgc_active_tab'] ?? 'categories');

    // --- Save Categories ---
    if ($tab === 'categories' && isset($_POST['categories'])) {
        $categories = [];
        foreach ($_POST['categories'] as $cat) {
            $slug  = sanitize_title($cat['slug'] ?? $cat['label'] ?? '');
            $label = sanitize_text_field($cat['label'] ?? '');
            $retention = sanitize_text_field($cat['retention'] ?? '');

            if (! empty($label) && ! empty($slug)) {
                $categories[] = [
                    'slug'      => $slug,
                    'label'     => $label,
                    'retention' => $retention,
                ];
            }
        }
        update_option('plgc_docmgr_categories', $categories);
    }

    // --- Save General Settings ---
    if ($tab === 'general') {
        $settings = [
            'compliance_deadline'    => sanitize_text_field($_POST['compliance_deadline'] ?? 'April 24, 2026'),
            'archive_behavior'       => sanitize_text_field($_POST['archive_behavior'] ?? 'redirect'),
            'archive_page'           => absint($_POST['archive_page'] ?? 0),
            'archive_contact_email'  => sanitize_email($_POST['archive_contact_email'] ?? ''),
            'ada_coordinator_name'   => sanitize_text_field($_POST['ada_coordinator_name'] ?? ''),
            'ada_coordinator_email'  => sanitize_email($_POST['ada_coordinator_email'] ?? ''),
            'ada_coordinator_phone'  => sanitize_text_field($_POST['ada_coordinator_phone'] ?? ''),
            'notify_email'           => sanitize_email($_POST['notify_email'] ?? ''),
            'notify_days_before'     => absint($_POST['notify_days_before'] ?? 30),
            'auto_archive'           => isset($_POST['auto_archive']),
        ];
        update_option('plgc_docmgr_settings', $settings);
    }

    // --- Save Clarity API Settings ---
    if ($tab === 'clarity') {
        update_option('plgc_clarity_enabled', isset($_POST['clarity_enabled']) ? 1 : 0);
        update_option('plgc_clarity_api_url', esc_url_raw(rtrim($_POST['clarity_api_url'] ?? 'https://awsclarity.commonlook.com/ClarityAPI', '/')));
        update_option('plgc_clarity_username', sanitize_email($_POST['clarity_username'] ?? ''));

        // Only update password if a new one was entered
        if (! empty($_POST['clarity_password'])) {
            update_option('plgc_clarity_password', sanitize_text_field($_POST['clarity_password']));
        }

        update_option('plgc_clarity_auto_check', isset($_POST['clarity_auto_check']) ? 1 : 0);
        update_option('plgc_clarity_block_upload', sanitize_text_field($_POST['clarity_block_upload'] ?? 'warn'));
        update_option('plgc_clarity_allyant_email', sanitize_email($_POST['clarity_allyant_email'] ?? ''));

        // Only update webhook secret if a new one was entered
        if (! empty($_POST['clarity_webhook_secret'])) {
            update_option('plgc_clarity_webhook_secret', sanitize_text_field($_POST['clarity_webhook_secret']));
        }

        $standards = [];
        if (isset($_POST['clarity_standards']) && is_array($_POST['clarity_standards'])) {
            $standards = array_map('sanitize_text_field', $_POST['clarity_standards']);
        }
        update_option('plgc_clarity_standards', $standards);
    }

    // --- Save Monsido Settings (admin only) ---
    if ($tab === 'monsido' && current_user_can('administrator') && isset($_POST['plgc_monsido_settings'])) {
        $m = $_POST['plgc_monsido_settings'];
        $monsido = [
            'api_url'     => esc_url_raw(rtrim($m['api_url'] ?? '', '/')),
            'api_token'   => sanitize_text_field($m['api_token'] ?? ''),
            'domain_id'   => sanitize_text_field($m['domain_id'] ?? ''),
            'domain_name' => sanitize_text_field($m['domain_name'] ?? ''),
            'domain_url'  => esc_url_raw($m['domain_url'] ?? ''),
            'match_url'   => esc_url_raw($m['match_url'] ?? ''),
            'auto_sync'   => in_array($m['auto_sync'] ?? '', ['daily', 'twicedaily', 'manual']) ? $m['auto_sync'] : 'daily',
        ];
        update_option('plgc_monsido_settings', $monsido);

        // Reschedule cron
        wp_clear_scheduled_hook('plgc_monsido_sync_cron');
        if ($monsido['auto_sync'] !== 'manual') {
            wp_schedule_event(time(), $monsido['auto_sync'], 'plgc_monsido_sync_cron');
        }
    }

    add_settings_error('plgc_docmgr', 'saved', 'Settings saved.', 'success');
}
add_action('admin_init', 'plgc_docmgr_handle_settings');

/**
 * Settings page HTML.
 */
function plgc_docmgr_settings_page() {
    $active_tab  = sanitize_text_field($_GET['tab'] ?? 'categories');
    $categories  = get_option('plgc_docmgr_categories', []);
    $settings    = get_option('plgc_docmgr_settings', []);
    $pages       = get_pages(['post_status' => 'publish']);

    settings_errors('plgc_docmgr');
    ?>
    <div class="wrap">
        <h1>📋 Aviatrix Compliance Suite</h1>

        <nav class="nav-tab-wrapper">
            <a href="?page=plgc-docmgr&tab=categories" class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">Categories & Retention</a>
            <a href="?page=plgc-docmgr&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Archive Behavior</a>
            <a href="?page=plgc-docmgr&tab=clarity" class="nav-tab <?php echo $active_tab === 'clarity' ? 'nav-tab-active' : ''; ?>">Clarity API</a>
            <?php if (current_user_can('administrator')) : ?>
                <a href="?page=plgc-docmgr&tab=monsido" class="nav-tab <?php echo $active_tab === 'monsido' ? 'nav-tab-active' : ''; ?>">Monsido</a>
            <?php endif; ?>
        </nav>

        <form method="post">
            <?php wp_nonce_field('plgc_docmgr_settings'); ?>
            <input type="hidden" name="plgc_active_tab" value="<?php echo esc_attr($active_tab); ?>" />

            <?php if ($active_tab === 'categories') : ?>
                <!-- ============================================================ -->
                <!-- CATEGORIES & RETENTION TAB                                   -->
                <!-- ============================================================ -->
                <div class="plgc-docmgr-section">
                    <h2>Document Categories & Default Retention Periods</h2>
                    <p>Define the document categories available in the media library. Each category has a default retention period that auto-calculates the review date from the upload date. Users can always override the date on individual documents.</p>

                    <p><strong>Retention format examples:</strong> <code>6 months</code>, <code>1 year</code>, <code>2 years</code>, <code>3 years</code>, <code>5 years</code>, <code>7 years</code>, <code>18 months</code></p>

                    <table class="widefat plgc-categories-table" id="plgc-categories-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Slug (ID)</th>
                                <th style="width: 35%;">Display Label</th>
                                <th style="width: 25%;">Default Retention</th>
                                <th style="width: 15%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $i => $cat) : ?>
                                <tr class="plgc-category-row">
                                    <td>
                                        <input type="text" name="categories[<?php echo $i; ?>][slug]"
                                               value="<?php echo esc_attr($cat['slug']); ?>"
                                               class="regular-text" style="width:100%;"
                                               pattern="[a-z0-9_-]+" title="Lowercase letters, numbers, hyphens, underscores only" />
                                    </td>
                                    <td>
                                        <input type="text" name="categories[<?php echo $i; ?>][label]"
                                               value="<?php echo esc_attr($cat['label']); ?>"
                                               class="regular-text" style="width:100%;" required />
                                    </td>
                                    <td>
                                        <input type="text" name="categories[<?php echo $i; ?>][retention]"
                                               value="<?php echo esc_attr($cat['retention']); ?>"
                                               class="regular-text" style="width:100%;"
                                               placeholder="e.g., 3 years" />
                                    </td>
                                    <td>
                                        <button type="button" class="button plgc-remove-category" title="Remove category">✕ Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">
                                    <button type="button" class="button" id="plgc-add-category">+ Add Category</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="description" style="margin-top: 12px;">
                        <strong>Note:</strong> Changing a category slug will not update documents already assigned to the old slug.
                        Removing a category does not delete documents — they'll just show "—" for category until reassigned.
                    </p>
                </div>

            <?php elseif ($active_tab === 'general') : ?>
                <!-- ============================================================ -->
                <!-- ARCHIVE BEHAVIOR TAB                                         -->
                <!-- ============================================================ -->
                <div class="plgc-docmgr-section">
                    <h2>What Happens When Documents Are Archived</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Compliance Deadline</th>
                            <td>
                                <select name="compliance_deadline">
                                    <option value="April 24, 2026" <?php selected($settings['compliance_deadline'] ?? '', 'April 24, 2026'); ?>>
                                        April 24, 2026 (population 50,000+)
                                    </option>
                                    <option value="April 26, 2027" <?php selected($settings['compliance_deadline'] ?? '', 'April 26, 2027'); ?>>
                                        April 26, 2027 (population under 50,000 / special districts)
                                    </option>
                                </select>
                                <p class="description">Your entity's Title II compliance deadline. This is shown on the archive page and used to determine which documents qualify for the archive exception.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Archive Behavior</th>
                            <td>
                                <select name="archive_behavior">
                                    <option value="redirect" <?php selected($settings['archive_behavior'] ?? '', 'redirect'); ?>>
                                        Redirect to archive notice page (recommended)
                                    </option>
                                    <option value="private" <?php selected($settings['archive_behavior'] ?? '', 'private'); ?>>
                                        Return 404 (make fully private)
                                    </option>
                                    <option value="noindex" <?php selected($settings['archive_behavior'] ?? '', 'noindex'); ?>>
                                        Keep URL alive but add noindex (hide from search only)
                                    </option>
                                </select>
                                <p class="description">
                                    <strong>Redirect</strong> shows visitors a page explaining the document has been archived with a contact option. No broken links.<br>
                                    <strong>404</strong> removes all access — existing links will break.<br>
                                    <strong>Noindex</strong> keeps the URL working but tells search engines not to index it.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Archive Notice Page</th>
                            <td>
                                <select name="archive_page">
                                    <option value="0">— Select or create a page —</option>
                                    <?php foreach ($pages as $page) : ?>
                                        <option value="<?php echo $page->ID; ?>" <?php selected($settings['archive_page'] ?? 0, $page->ID); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    Create a page with a message like: "This document has been archived. If you need access, please contact us at [email]."
                                    When a document is archived, visitors will be redirected to this page.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Archive Contact Email</th>
                            <td>
                                <input type="email" name="archive_contact_email"
                                       value="<?php echo esc_attr($settings['archive_contact_email'] ?? ''); ?>"
                                       class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                                <p class="description">Email shown on the archive page and used for document request emails. Defaults to admin email if blank.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">ADA Coordinator Name</th>
                            <td>
                                <input type="text" name="ada_coordinator_name"
                                       value="<?php echo esc_attr($settings['ada_coordinator_name'] ?? ''); ?>"
                                       class="regular-text" />
                                <p class="description">Displayed on the archive page. Leave blank to hide the ADA Coordinator section.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">ADA Coordinator Email</th>
                            <td>
                                <input type="email" name="ada_coordinator_email"
                                       value="<?php echo esc_attr($settings['ada_coordinator_email'] ?? ''); ?>"
                                       class="regular-text" />
                                <p class="description">If set, this overrides the Archive Contact Email for both display and form submissions.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">ADA Coordinator Phone</th>
                            <td>
                                <input type="tel" name="ada_coordinator_phone"
                                       value="<?php echo esc_attr($settings['ada_coordinator_phone'] ?? ''); ?>"
                                       class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Auto-Archive</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_archive" <?php checked($settings['auto_archive'] ?? false); ?> />
                                    Automatically archive documents when their review date passes
                                </label>
                                <p class="description">
                                    If unchecked (recommended), documents are flagged for review and a notification email is sent.
                                    A human must decide to archive or extend. If checked, documents are auto-archived on their review date.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notification Email</th>
                            <td>
                                <input type="email" name="notify_email"
                                       value="<?php echo esc_attr($settings['notify_email'] ?? ''); ?>"
                                       class="regular-text" />
                                <p class="description">Where to send retention review notifications.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Advance Warning</th>
                            <td>
                                <input type="number" name="notify_days_before"
                                       value="<?php echo esc_attr($settings['notify_days_before'] ?? 30); ?>"
                                       min="0" max="365" style="width: 80px;" /> days before review date
                                <p class="description">Send a heads-up email this many days before a document's review date arrives.</p>
                            </td>
                        </tr>
                    </table>
                </div>

            <?php elseif ($active_tab === 'clarity') : ?>
                <!-- ============================================================ -->
                <!-- CLARITY API TAB                                              -->
                <!-- ============================================================ -->
                <div class="plgc-docmgr-section">
                    <h2>CommonLook Clarity API</h2>
                    <p>Automatically validate PDF accessibility on upload. <a href="https://awsclarity.commonlook.com/ClarityAPI/Home/Documentation" target="_blank">View API documentation ↗</a></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Clarity API</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="clarity_enabled" <?php checked(get_option('plgc_clarity_enabled', 0)); ?> />
                                    Enable automatic PDF accessibility scanning
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API URL</th>
                            <td>
                                <input type="url" name="clarity_api_url" value="<?php echo esc_attr(get_option('plgc_clarity_api_url', 'https://awsclarity.commonlook.com/ClarityAPI')); ?>" class="regular-text" />
                                <p class="description">Your CommonLook Clarity endpoint. Default: <code>https://awsclarity.commonlook.com/ClarityAPI</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Username</th>
                            <td><input type="email" name="clarity_username" value="<?php echo esc_attr(get_option('plgc_clarity_username', '')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">API Password</th>
                            <td>
                                <input type="password" name="clarity_password" value="" class="regular-text" placeholder="<?php echo get_option('plgc_clarity_password') ? '••••••••' : ''; ?>" />
                                <p class="description">Leave blank to keep the existing password.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Validation Standards</th>
                            <td>
                                <?php $standards = get_option('plgc_clarity_standards', ['WCAG2_0', 'S508', 'PDF_UA_1']); ?>
                                <label><input type="checkbox" name="clarity_standards[]" value="WCAG2_0" <?php checked(in_array('WCAG2_0', $standards)); ?> /> WCAG 2.0 AA</label><br>
                                <label><input type="checkbox" name="clarity_standards[]" value="S508" <?php checked(in_array('S508', $standards)); ?> /> Section 508</label><br>
                                <label><input type="checkbox" name="clarity_standards[]" value="PDF_UA_1" <?php checked(in_array('PDF_UA_1', $standards)); ?> /> PDF/UA-1</label><br>
                                <label><input type="checkbox" name="clarity_standards[]" value="HHS_2018" <?php checked(in_array('HHS_2018', $standards)); ?> /> HHS 2018</label><br>
                                <label><input type="checkbox" name="clarity_standards[]" value="STRUCTURAL" <?php checked(in_array('STRUCTURAL', $standards)); ?> /> Structural</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Auto-Scan on Upload</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="clarity_auto_check" <?php checked(get_option('plgc_clarity_auto_check', 1)); ?> />
                                    Automatically scan PDFs when uploaded
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Non-Compliant Behavior</th>
                            <td>
                                <select name="clarity_block_upload">
                                    <?php $block = get_option('plgc_clarity_block_upload', 'warn'); ?>
                                    <option value="warn" <?php selected($block, 'warn'); ?>>Warn but allow (recommended)</option>
                                    <option value="block" <?php selected($block, 'block'); ?>>Block for non-admins</option>
                                    <option value="silent" <?php selected($block, 'silent'); ?>>Log silently</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Allyant Remediation Email</th>
                            <td>
                                <input type="email" name="clarity_allyant_email" value="<?php echo esc_attr(get_option('plgc_clarity_allyant_email', '')); ?>" class="regular-text" />
                                <p class="description">Email to send non-compliant documents to for remediation. Leave blank to disable.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook URL</th>
                            <td>
                                <code style="padding: 6px 10px; background: #f0f0f0; display: inline-block;"><?php echo esc_url(rest_url('plgc/v1/clarity-webhook')); ?></code>
                                <p class="description">Register this URL with CommonLook Clarity for real-time scan results.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook Secret</th>
                            <td>
                                <input type="password" name="clarity_webhook_secret" value="" class="regular-text" placeholder="<?php echo get_option('plgc_clarity_webhook_secret') ? '••••••••' : ''; ?>" autocomplete="new-password" />
                                <p class="description">Shared secret for webhook signature verification. The webhook endpoint is <strong>disabled</strong> until a secret is set. Enter the same value you configure in CommonLook Clarity's webhook settings.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Test Connection</th>
                            <td>
                                <button type="button" class="button" id="plgc-clarity-test">Test API Connection</button>
                                <span id="plgc-clarity-test-result" style="margin-left: 12px;"></span>
                            </td>
                        </tr>
                    </table>
                </div>

            <?php elseif ($active_tab === 'monsido' && current_user_can('administrator')) : ?>
                <!-- ============================================================ -->
                <!-- MONSIDO / WEB GOVERNANCE TAB (Admin only)                    -->
                <!-- ============================================================ -->
                <?php if (function_exists('plgc_monsido_render_settings_tab')) : ?>
                    <?php plgc_monsido_render_settings_tab(); ?>
                <?php else : ?>
                    <p>Monsido integration module not loaded.</p>
                <?php endif; ?>
            <?php endif; ?>

            <p class="submit">
                <input type="submit" name="plgc_docmgr_save" class="button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
    <?php
}
