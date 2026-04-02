<?php
/**
 * Monsido / Acquia Web Governance Integration
 *
 * Connects to the Monsido API to pull site health data into
 * the WordPress admin — accessibility scores, broken links,
 * spelling errors, SEO issues, and readability.
 *
 * Security: API credentials and domain selection are restricted
 * to administrator role only. Clients see the dashboard data
 * but never the API token, domain list, or other site info.
 *
 * API Docs: https://docs.acquia.com/web-governance/introduction-acquia-web-governance-api
 * Swagger:  https://petstore.swagger.io/?url=https://app2.us.monsido.com/api/docs/v1
 *
 * @package PLGC_DocManager
 */

defined('ABSPATH') || exit;

/**
 * ============================================================
 * SETTINGS (Administrator only)
 * ============================================================
 */


/**
 * Render the Monsido settings tab content.
 * Called from the Compliance Suite settings page (monsido tab).
 * Only visible to administrators.
 */
function plgc_monsido_render_settings_tab() {
    if (! current_user_can('administrator')) {
        return;
    }

    $settings  = get_option('plgc_monsido_settings', []);
    $data      = get_option('plgc_monsido_data', []);
    $last_sync = get_option('plgc_monsido_last_sync', '');

    $api_url     = $settings['api_url'] ?? 'https://app2.us.monsido.com/api';
    $api_token   = $settings['api_token'] ?? '';
    $domain_id   = $settings['domain_id'] ?? '';
    $domain_name = $settings['domain_name'] ?? '';
    $domain_url  = $settings['domain_url'] ?? '';
    $match_url   = $settings['match_url'] ?? site_url();
    $auto_sync   = $settings['auto_sync'] ?? 'daily';
    ?>
    <div class="plgc-docmgr-section">
        <h2>🔍 Monsido / Web Governance Integration</h2>
        <p>Connect to Monsido (Acquia Web Governance) to pull accessibility scores, broken links, spelling errors, and QA data directly into the WordPress dashboard for editors.</p>
        <p style="font-size: 12px; color: #666;">🔒 These settings are only visible to administrators. Editors and clients see the dashboard data but not API credentials.</p>

        <?php if (! empty($data['domain'])) : ?>
            <div style="padding: 8px 12px; background: #f8fff0; border-left: 4px solid #567915; margin: 8px 0 16px;">
                <strong style="color: #567915;">✅ Connected</strong> — <?php echo esc_html($data['domain']['title'] ?? 'Unknown'); ?>
                (<?php echo esc_html($data['domain']['url'] ?? ''); ?>)
                <?php if ($last_sync) : ?>
                    · Last synced: <?php echo esc_html(human_time_diff(strtotime($last_sync))); ?> ago
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">API URL</th>
                <td>
                    <input type="url" name="plgc_monsido_settings[api_url]" value="<?php echo esc_attr($api_url); ?>" class="regular-text">
                    <p class="description">Your regional API endpoint. US: app1.us or app2.us · EU: app1.eu or app2.eu</p>
                </td>
            </tr>
            <tr>
                <th scope="row">API Token</th>
                <td>
                    <input type="password" name="plgc_monsido_settings[api_token]" value="<?php echo esc_attr($api_token); ?>" class="regular-text" autocomplete="off">
                    <?php if ($api_token) : ?>
                        <p class="description">Current token ends in: …<?php echo esc_html(substr($api_token, -4)); ?></p>
                    <?php endif; ?>
                    <p class="description">Find under Admin Settings → Users → API Users in Monsido.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Domain</th>
                <td>
                    <?php if ($domain_id && $domain_name) : ?>
                        <div style="padding: 8px 12px; background: #f9f9f9; border: 1px solid #e7e4e4; border-radius: 4px; margin-bottom: 8px;">
                            <strong><?php echo esc_html($domain_name); ?></strong>
                            <br><span style="color: #666; font-size: 12px;"><?php echo esc_html($domain_url); ?> · ID: <?php echo esc_html($domain_id); ?></span>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="plgc_monsido_settings[domain_id]" id="plgc-monsido-domain-id" value="<?php echo esc_attr($domain_id); ?>">
                    <input type="hidden" name="plgc_monsido_settings[domain_name]" id="plgc-monsido-domain-name" value="<?php echo esc_attr($domain_name); ?>">
                    <input type="hidden" name="plgc_monsido_settings[domain_url]" id="plgc-monsido-domain-url" value="<?php echo esc_attr($domain_url); ?>">

                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Match URL</label>
                    <input type="url" name="plgc_monsido_settings[match_url]" id="plgc-monsido-match-url" value="<?php echo esc_attr($match_url); ?>" class="regular-text">
                    <p class="description">
                        Defaults to <code><?php echo esc_html(site_url()); ?></code>.
                        <strong>Temp domains:</strong> Enter the staging URL during build; update to production URL at launch.
                    </p>

                    <?php if ($api_token) : ?>
                        <div style="margin-top: 8px;">
                            <button type="button" class="button button-primary" onclick="plgcMonsidoAutoMatch()">🔍 Auto-Match Domain</button>
                            <span id="plgc-monsido-match-status" style="margin-left: 8px;"></span>
                        </div>
                        <div id="plgc-monsido-match-result" style="margin-top: 8px;"></div>
                    <?php else : ?>
                        <p style="color: #856404; font-size: 12px;">Save your API URL and token first, then come back to match.</p>
                    <?php endif; ?>

                    <details style="margin-top: 12px;">
                        <summary style="cursor: pointer; font-size: 12px; color: #666;">Manual domain ID override</summary>
                        <div style="margin-top: 8px;">
                            <input type="text" id="plgc-monsido-manual-id" value="<?php echo esc_attr($domain_id); ?>" class="small-text" placeholder="12345">
                            <button type="button" class="button button-small" onclick="document.getElementById('plgc-monsido-domain-id').value=document.getElementById('plgc-monsido-manual-id').value;document.getElementById('plgc-monsido-domain-name').value='Manual';document.getElementById('plgc-monsido-domain-url').value='';">Set</button>
                            <p class="description">Find the ID in your Monsido URL: /domains/<strong>{id}</strong>/dashboard.</p>
                        </div>
                    </details>
                </td>
            </tr>
            <tr>
                <th scope="row">Auto Sync</th>
                <td>
                    <select name="plgc_monsido_settings[auto_sync]">
                        <option value="daily" <?php selected($auto_sync, 'daily'); ?>>Daily</option>
                        <option value="twicedaily" <?php selected($auto_sync, 'twicedaily'); ?>>Twice Daily</option>
                        <option value="manual" <?php selected($auto_sync, 'manual'); ?>>Manual Only</option>
                    </select>
                    &nbsp; <button type="button" class="button" onclick="plgcMonsidoSync()">⟳ Sync Now</button>
                    <span id="plgc-monsido-sync-status" style="margin-left: 8px;"></span>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

/**
 * ============================================================
 * API CLIENT
 * ============================================================
 */

/**
 * Make a request to the Monsido API.
 *
 * @param string $endpoint  API path after /api/
 * @param array  $params    Query parameters
 * @return array|WP_Error
 */
function plgc_monsido_api_request($endpoint, $params = []) {
    $settings = get_option('plgc_monsido_settings', []);

    if (empty($settings['api_url']) || empty($settings['api_token'])) {
        return new WP_Error('not_configured', 'Monsido API not configured.');
    }

    $url = trailingslashit($settings['api_url']) . ltrim($endpoint, '/');

    if (! empty($params)) {
        $url = add_query_arg($params, $url);
    }

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $settings['api_token'],
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 401) {
        return new WP_Error('unauthorized', 'Invalid API token. Check your Monsido API credentials.');
    }

    if ($code === 403) {
        return new WP_Error('forbidden', 'API access denied. Ensure your API user has the correct permissions.');
    }

    if ($code >= 400) {
        return new WP_Error('api_error', 'Monsido API error (HTTP ' . $code . '): ' . ($body['message'] ?? 'Unknown error'));
    }

    return $body;
}

/**
 * Get domain summary data.
 */
function plgc_monsido_get_domain() {
    $settings = get_option('plgc_monsido_settings', []);
    if (empty($settings['domain_id'])) {
        return new WP_Error('no_domain', 'Domain ID not configured.');
    }

    return plgc_monsido_api_request('domains/' . $settings['domain_id']);
}

/**
 * Get pages with QA/accessibility data.
 *
 * @param int    $page      Page number
 * @param int    $per_page  Results per page
 * @param string $sort_by   Sort field
 * @param string $sort_dir  Sort direction
 * @return array|WP_Error
 */
function plgc_monsido_get_pages($page = 1, $per_page = 50, $sort_by = 'priority_score', $sort_dir = 'desc') {
    $settings = get_option('plgc_monsido_settings', []);
    if (empty($settings['domain_id'])) {
        return new WP_Error('no_domain', 'Domain ID not configured.');
    }

    return plgc_monsido_api_request('domains/' . $settings['domain_id'] . '/pages', [
        'page'      => $page,
        'page_size' => $per_page,
        'sort_by'   => $sort_by,
        'sort_dir'  => $sort_dir,
    ]);
}

/**
 * List all domains on the account (for setup).
 */
function plgc_monsido_list_domains() {
    return plgc_monsido_api_request('domains', ['page_size' => 50]);
}

/**
 * ============================================================
 * DATA SYNC
 * ============================================================
 */

/**
 * Sync Monsido data to local WordPress options.
 * Called by cron and manual sync button.
 */
function plgc_monsido_sync() {
    $settings = get_option('plgc_monsido_settings', []);
    if (empty($settings['api_token']) || empty($settings['domain_id'])) {
        return ['success' => false, 'message' => 'Not configured'];
    }

    $data = [];

    // 1. Domain summary
    $domain = plgc_monsido_get_domain();
    if (is_wp_error($domain)) {
        return ['success' => false, 'message' => $domain->get_error_message()];
    }
    $data['domain'] = $domain;

    // 2. Pull pages with issues (up to 200 highest-priority pages)
    $all_pages = [];
    for ($p = 1; $p <= 4; $p++) {
        $pages = plgc_monsido_get_pages($p, 50, 'priority_score', 'desc');
        if (is_wp_error($pages) || empty($pages)) {
            break;
        }
        $all_pages = array_merge($all_pages, $pages);
        if (count($pages) < 50) break; // Last page
    }

    // 3. Process page data into summaries
    $pages_with_a11y_errors   = [];
    $pages_with_broken_links  = [];
    $pages_with_spelling      = [];
    $totals = [
        'pages_scanned'     => count($all_pages),
        'a11y_errors_total' => 0,
        'broken_links'      => 0,
        'broken_images'     => 0,
        'spelling_confirmed'=> 0,
        'spelling_potential' => 0,
        'seo_issues'        => 0,
    ];

    foreach ($all_pages as $pg) {
        $a11y_count    = (int) ($pg['accessibility_checks_with_errors_count'] ?? 0);
        $dead_links    = (int) ($pg['dead_links_count'] ?? 0);
        $dead_images   = (int) ($pg['dead_images_count'] ?? 0);
        $spelling_c    = (int) ($pg['spelling_errors_confirmed_count'] ?? 0);
        $spelling_p    = (int) ($pg['spelling_errors_potential_count'] ?? 0);
        $seo           = (int) ($pg['seo_issues_count'] ?? 0);

        $totals['a11y_errors_total'] += $a11y_count;
        $totals['broken_links']      += $dead_links;
        $totals['broken_images']     += $dead_images;
        $totals['spelling_confirmed']+= $spelling_c;
        $totals['spelling_potential'] += $spelling_p;
        $totals['seo_issues']        += $seo;

        $page_summary = [
            'id'         => $pg['id'] ?? 0,
            'title'      => $pg['title'] ?? '',
            'url'        => $pg['url'] ?? '',
            'priority'   => $pg['priority_score'] ?? 0,
        ];

        if ($a11y_count > 0) {
            $pages_with_a11y_errors[] = array_merge($page_summary, ['count' => $a11y_count]);
        }
        if ($dead_links > 0 || $dead_images > 0) {
            $pages_with_broken_links[] = array_merge($page_summary, [
                'dead_links'  => $dead_links,
                'dead_images' => $dead_images,
            ]);
        }
        if ($spelling_c > 0 || $spelling_p > 0) {
            $pages_with_spelling[] = array_merge($page_summary, [
                'confirmed' => $spelling_c,
                'potential' => $spelling_p,
            ]);
        }
    }

    // Sort by count descending
    usort($pages_with_a11y_errors, fn($a, $b) => $b['count'] - $a['count']);
    usort($pages_with_broken_links, fn($a, $b) => ($b['dead_links'] + $b['dead_images']) - ($a['dead_links'] + $a['dead_images']));
    usort($pages_with_spelling, fn($a, $b) => ($b['confirmed'] + $b['potential']) - ($a['confirmed'] + $a['potential']));

    // Store top 25 of each
    $data['totals']               = $totals;
    $data['pages_a11y_errors']    = array_slice($pages_with_a11y_errors, 0, 25);
    $data['pages_broken_links']   = array_slice($pages_with_broken_links, 0, 25);
    $data['pages_spelling']       = array_slice($pages_with_spelling, 0, 25);

    // Extract crawl history if available
    if (! empty($domain['crawl_history'])) {
        $data['last_crawl'] = $domain['crawl_history'];
    }

    // Extract compliance score from domain features
    if (! empty($domain['features'])) {
        $data['wcag_level'] = $domain['features']['accessibility'] ?? 'Unknown';
    }

    update_option('plgc_monsido_data', $data, false);
    update_option('plgc_monsido_last_sync', current_time('mysql'));

    return [
        'success' => true,
        'message' => 'Synced ' . $totals['pages_scanned'] . ' pages.',
        'totals'  => $totals,
    ];
}
add_action('plgc_monsido_sync_cron', 'plgc_monsido_sync');

/**
 * ============================================================
 * AJAX HANDLERS
 * ============================================================
 */

/**
 * AJAX: Manual sync trigger. Admin only.
 */
function plgc_monsido_ajax_sync() {
    check_ajax_referer('plgc_monsido_nonce', 'nonce');

    if (! current_user_can('administrator')) {
        wp_send_json_error('Permission denied.');
    }

    $result = plgc_monsido_sync();
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_plgc_monsido_sync', 'plgc_monsido_ajax_sync');

/**
 * AJAX: Auto-match domain by URL.
 *
 * Queries all domains on the account, finds the one whose URL
 * matches the provided match_url, and returns ONLY that domain's
 * id, title, and url. Never exposes the full domain list.
 */
function plgc_monsido_ajax_auto_match() {
    check_ajax_referer('plgc_monsido_nonce', 'nonce');

    if (! current_user_can('administrator')) {
        wp_send_json_error('Permission denied.');
    }

    $match_url = esc_url_raw($_POST['match_url'] ?? '');
    if (empty($match_url)) {
        wp_send_json_error('No URL provided to match against.');
    }

    // Normalize the match URL for comparison
    $match_host = strtolower(wp_parse_url($match_url, PHP_URL_HOST) ?: '');
    $match_host = preg_replace('/^www\./', '', $match_host);

    if (empty($match_host)) {
        wp_send_json_error('Could not parse URL: ' . $match_url);
    }

    // Fetch all domains from Monsido (paginate if needed)
    $all_domains = [];
    for ($page = 1; $page <= 5; $page++) {
        $result = plgc_monsido_api_request('domains', [
            'page'      => $page,
            'page_size' => 50,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error('API error: ' . $result->get_error_message());
        }

        if (empty($result) || ! is_array($result)) {
            break;
        }

        $all_domains = array_merge($all_domains, $result);
        if (count($result) < 50) break;
    }

    // Find matching domain(s) by hostname
    $matches = [];
    foreach ($all_domains as $domain) {
        $domain_url  = $domain['url'] ?? '';
        $domain_host = strtolower(wp_parse_url($domain_url, PHP_URL_HOST) ?: '');
        $domain_host = preg_replace('/^www\./', '', $domain_host);

        if ($domain_host === $match_host) {
            $matches[] = [
                'id'    => $domain['id'],
                'title' => $domain['title'] ?? '',
                'url'   => $domain_url,
            ];
        }
    }

    if (empty($matches)) {
        // Provide helpful message without exposing other domains
        wp_send_json_error(
            'No Monsido domain found matching "' . esc_html($match_host) . '". ' .
            'Make sure the domain is set up in Monsido and the URL matches exactly. ' .
            'Searched ' . count($all_domains) . ' domain(s) on your account. ' .
            'If building on a temp domain, enter the temp URL in the Match URL field.'
        );
    }

    if (count($matches) === 1) {
        wp_send_json_success([
            'match'   => 'exact',
            'domain'  => $matches[0],
            'message' => 'Found matching domain: ' . $matches[0]['title'],
        ]);
    }

    // Multiple matches (unlikely but possible with subdomains)
    wp_send_json_success([
        'match'   => 'multiple',
        'domains' => $matches,
        'message' => 'Found ' . count($matches) . ' domains matching "' . esc_html($match_host) . '". Select the correct one.',
    ]);
}
add_action('wp_ajax_plgc_monsido_auto_match', 'plgc_monsido_ajax_auto_match');

/**
 * Enqueue admin scripts for AJAX. Admin only.
 */
function plgc_monsido_admin_scripts($hook) {
    if (! current_user_can('administrator')) {
        return;
    }

    if (strpos($hook, 'plgc') === false && $hook !== 'options-general.php') {
        return;
    }

    wp_add_inline_script('jquery-core', '
        function plgcMonsidoSync() {
            var $status = jQuery("#plgc-monsido-sync-status");
            $status.text("Syncing...").css("color", "#666");
            jQuery.post(ajaxurl, {
                action: "plgc_monsido_sync",
                nonce: "' . wp_create_nonce('plgc_monsido_nonce') . '"
            }, function(response) {
                if (response.success) {
                    var t = response.data.totals;
                    $status.html("✅ Synced " + t.pages_scanned + " pages. " +
                        t.a11y_errors_total + " a11y errors, " +
                        t.broken_links + " broken links, " +
                        t.spelling_confirmed + " spelling errors.").css("color", "#567915");
                } else {
                    $status.text("❌ " + response.data).css("color", "#d63638");
                }
            }).fail(function() {
                $status.text("❌ Request failed").css("color", "#d63638");
            });
        }

        function plgcMonsidoAutoMatch() {
            var $status = jQuery("#plgc-monsido-match-status");
            var $result = jQuery("#plgc-monsido-match-result");
            var matchUrl = jQuery("#plgc-monsido-match-url").val();

            if (!matchUrl) {
                $status.text("❌ Enter a URL first").css("color", "#d63638");
                return;
            }

            $status.text("Searching...").css("color", "#666");
            $result.empty();

            jQuery.post(ajaxurl, {
                action: "plgc_monsido_auto_match",
                nonce: "' . wp_create_nonce('plgc_monsido_nonce') . '",
                match_url: matchUrl
            }, function(response) {
                if (response.success) {
                    if (response.data.match === "exact") {
                        var d = response.data.domain;
                        jQuery("#plgc-monsido-domain-id").val(d.id);
                        jQuery("#plgc-monsido-domain-name").val(d.title);
                        jQuery("#plgc-monsido-domain-url").val(d.url);
                        $status.html("✅ Matched!").css("color", "#567915");
                        $result.html(
                            "<div style=\"padding: 8px 12px; background: #f8fff0; border: 1px solid #567915; border-radius: 4px;\">" +
                            "<strong>" + d.title + "</strong><br>" +
                            "<span style=\"font-size: 12px; color: #666;\">" + d.url + " · ID: " + d.id + "</span><br>" +
                            "<span style=\"font-size: 12px; color: #567915;\">Save settings to confirm, then click Sync Now.</span>" +
                            "</div>"
                        );
                    } else if (response.data.match === "multiple") {
                        $status.text("⚠️ Multiple matches found").css("color", "#856404");
                        var html = "<div style=\"font-size: 12px;\">";
                        html += "<p>Multiple domains match this URL. Click the one for this site:</p>";
                        response.data.domains.forEach(function(d) {
                            html += "<button type=\"button\" class=\"button plgc-monsido-pick\" style=\"margin: 2px;\" " +
                                "data-id=\"" + d.id + "\" " +
                                "data-title=\"" + (d.title || "").replace(/"/g, "&quot;") + "\" " +
                                "data-url=\"" + d.url + "\">" +
                                d.title + " (" + d.url + ")</button><br>";
                        });
                        html += "</div>";
                        $result.html(html);
                    }
                } else {
                    $status.text("").css("color", "");
                    $result.html(
                        "<div style=\"padding: 8px 12px; background: #fff5f5; border: 1px solid #d63638; border-radius: 4px; font-size: 12px;\">" +
                        response.data +
                        "</div>"
                    );
                }
            }).fail(function() {
                $status.text("❌ Request failed").css("color", "#d63638");
            });
        }

        jQuery(document).on("click", ".plgc-monsido-pick", function() {
            var $btn = jQuery(this);
            jQuery("#plgc-monsido-domain-id").val($btn.data("id"));
            jQuery("#plgc-monsido-domain-name").val($btn.data("title"));
            jQuery("#plgc-monsido-domain-url").val($btn.data("url"));
            jQuery("#plgc-monsido-match-status").text("✅ Selected: " + $btn.data("title")).css("color", "#567915");
            jQuery("#plgc-monsido-match-result").empty();
        });
    ');
}
add_action('admin_enqueue_scripts', 'plgc_monsido_admin_scripts');

/**
 * ============================================================
 * DASHBOARD WIDGET — SITE HEALTH OVERVIEW
 * ============================================================
 * Shows Monsido scan data on the WP dashboard and the
 * Accessibility Dashboard page.
 */

/**
 * Dashboard widget.
 */
function plgc_monsido_dashboard_widget() {
    wp_add_dashboard_widget(
        'plgc_monsido_health',
        '🔍 Site Health — Monsido',
        'plgc_monsido_dashboard_widget_content',
        null,
        null,
        'normal',
        'high'
    );
}
add_action('wp_dashboard_setup', 'plgc_monsido_dashboard_widget');

function plgc_monsido_dashboard_widget_content() {
    $data      = get_option('plgc_monsido_data', []);
    $last_sync = get_option('plgc_monsido_last_sync', '');
    $settings  = get_option('plgc_monsido_settings', []);

    if (empty($data['totals'])) {
        if (empty($settings['api_token'])) {
            echo '<p>Monsido not configured. <a href="' . admin_url('options-general.php?page=plgc-docmgr') . '">Set up connection →</a></p>';
        } else {
            echo '<p>No data yet. <a href="#" onclick="plgcMonsidoSync(); return false;">Run first sync →</a></p>';
        }
        return;
    }

    $t = $data['totals'];
    ?>
    <div style="font-size: 13px;">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px;">
            <div style="text-align: center; padding: 10px; background: <?php echo $t['a11y_errors_total'] > 0 ? '#fff5f5' : '#f8fff0'; ?>; border-radius: 6px;">
                <div style="font-size: 22px; font-weight: 700; color: <?php echo $t['a11y_errors_total'] > 0 ? '#d63638' : '#567915'; ?>;">
                    <?php echo number_format($t['a11y_errors_total']); ?>
                </div>
                <div style="font-size: 11px; color: #666;">A11y Errors</div>
            </div>
            <div style="text-align: center; padding: 10px; background: <?php echo $t['broken_links'] > 0 ? '#fff5f5' : '#f8fff0'; ?>; border-radius: 6px;">
                <div style="font-size: 22px; font-weight: 700; color: <?php echo $t['broken_links'] > 0 ? '#d63638' : '#567915'; ?>;">
                    <?php echo number_format($t['broken_links']); ?>
                </div>
                <div style="font-size: 11px; color: #666;">Broken Links</div>
            </div>
            <div style="text-align: center; padding: 10px; background: <?php echo $t['spelling_confirmed'] > 0 ? '#fffbeb' : '#f8fff0'; ?>; border-radius: 6px;">
                <div style="font-size: 22px; font-weight: 700; color: <?php echo $t['spelling_confirmed'] > 0 ? '#856404' : '#567915'; ?>;">
                    <?php echo number_format($t['spelling_confirmed']); ?>
                </div>
                <div style="font-size: 11px; color: #666;">Spelling Errors</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px;">
            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                <div style="font-size: 22px; font-weight: 700;"><?php echo number_format($t['broken_images']); ?></div>
                <div style="font-size: 11px; color: #666;">Broken Images</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                <div style="font-size: 22px; font-weight: 700;"><?php echo number_format($t['seo_issues']); ?></div>
                <div style="font-size: 11px; color: #666;">SEO Issues</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                <div style="font-size: 22px; font-weight: 700;"><?php echo number_format($t['pages_scanned']); ?></div>
                <div style="font-size: 11px; color: #666;">Pages Scanned</div>
            </div>
        </div>

        <?php if (! empty($data['pages_broken_links'])) : ?>
            <details style="margin-bottom: 8px;">
                <summary style="cursor: pointer; font-weight: 600; font-size: 12px; color: #d63638;">
                    🔗 Top Pages with Broken Links
                </summary>
                <ul style="margin: 4px 0; padding-left: 16px; font-size: 12px;">
                    <?php foreach (array_slice($data['pages_broken_links'], 0, 8) as $pg) : ?>
                        <li>
                            <a href="<?php echo esc_url($pg['url']); ?>" target="_blank"><?php echo esc_html($pg['title'] ?: $pg['url']); ?></a>
                            — <?php echo (int) $pg['dead_links']; ?> link(s), <?php echo (int) $pg['dead_images']; ?> image(s)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

        <?php if (! empty($data['pages_spelling'])) : ?>
            <details style="margin-bottom: 8px;">
                <summary style="cursor: pointer; font-weight: 600; font-size: 12px; color: #856404;">
                    📝 Top Pages with Spelling Errors
                </summary>
                <ul style="margin: 4px 0; padding-left: 16px; font-size: 12px;">
                    <?php foreach (array_slice($data['pages_spelling'], 0, 8) as $pg) : ?>
                        <li>
                            <a href="<?php echo esc_url($pg['url']); ?>" target="_blank"><?php echo esc_html($pg['title'] ?: $pg['url']); ?></a>
                            — <?php echo (int) $pg['confirmed']; ?> confirmed, <?php echo (int) $pg['potential']; ?> potential
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

        <?php if (! empty($data['pages_a11y_errors'])) : ?>
            <details style="margin-bottom: 8px;">
                <summary style="cursor: pointer; font-weight: 600; font-size: 12px; color: #d63638;">
                    ♿ Top Pages with Accessibility Errors
                </summary>
                <ul style="margin: 4px 0; padding-left: 16px; font-size: 12px;">
                    <?php foreach (array_slice($data['pages_a11y_errors'], 0, 8) as $pg) : ?>
                        <li>
                            <a href="<?php echo esc_url($pg['url']); ?>" target="_blank"><?php echo esc_html($pg['title'] ?: $pg['url']); ?></a>
                            — <?php echo (int) $pg['count']; ?> check(s) failing
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f0; font-size: 11px; color: #999;">
            <span>
                <?php if ($last_sync) : ?>
                    Last synced: <?php echo esc_html(human_time_diff(strtotime($last_sync))); ?> ago
                <?php endif; ?>
                <?php if (! empty($data['wcag_level'])) : ?>
                    · Scanning: <?php echo esc_html($data['wcag_level']); ?>
                <?php endif; ?>
            </span>
            <a href="<?php echo admin_url('admin.php?page=plgc-accessibility'); ?>">Full Dashboard →</a>
        </div>
    </div>
    <?php
}

/**
 * ============================================================
 * ACCESSIBILITY DASHBOARD INTEGRATION
 * ============================================================
 * Adds Monsido data panels to the ♿ Accessibility Dashboard.
 */
function plgc_monsido_dashboard_panels() {
    $data      = get_option('plgc_monsido_data', []);
    $last_sync = get_option('plgc_monsido_last_sync', '');

    if (empty($data['totals'])) {
        return;
    }

    $t = $data['totals'];
    ?>
    <!-- MONSIDO SITE HEALTH -->
    <div style="background: #fff; border: 1px solid #e7e4e4; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0; font-size: 16px;">🔍 Monsido Site Health</h2>
            <span style="font-size: 12px; color: #999;">
                <?php if ($last_sync) : ?>
                    Synced <?php echo esc_html(human_time_diff(strtotime($last_sync))); ?> ago
                    <?php if (! empty($data['wcag_level'])) : ?>
                        · <?php echo esc_html($data['wcag_level']); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 20px;">
            <?php
            $cards = [
                ['label' => 'A11y Errors',      'val' => $t['a11y_errors_total'], 'bad_threshold' => 1, 'color_bad' => '#d63638'],
                ['label' => 'Broken Links',      'val' => $t['broken_links'],      'bad_threshold' => 1, 'color_bad' => '#d63638'],
                ['label' => 'Broken Images',     'val' => $t['broken_images'],     'bad_threshold' => 1, 'color_bad' => '#d63638'],
                ['label' => 'Spelling Errors',   'val' => $t['spelling_confirmed'],'bad_threshold' => 1, 'color_bad' => '#856404'],
                ['label' => 'SEO Issues',        'val' => $t['seo_issues'],        'bad_threshold' => 5, 'color_bad' => '#856404'],
                ['label' => 'Pages Scanned',     'val' => $t['pages_scanned'],     'bad_threshold' => 9999, 'color_bad' => '#333'],
            ];
            foreach ($cards as $c) :
                $is_bad = $c['val'] >= $c['bad_threshold'];
                $bg = $is_bad ? '#fff5f5' : '#f8fff0';
                $color = $is_bad ? $c['color_bad'] : '#567915';
                if ($c['label'] === 'Pages Scanned') { $bg = '#f9f9f9'; $color = '#333'; }
                ?>
                <div style="text-align: center; padding: 12px 8px; background: <?php echo $bg; ?>; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: <?php echo $color; ?>;"><?php echo number_format($c['val']); ?></div>
                    <div style="font-size: 11px; color: #666; margin-top: 2px;"><?php echo esc_html($c['label']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Three-column detail tables -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">

            <!-- Broken Links -->
            <div>
                <h3 style="font-size: 13px; margin: 0 0 8px; color: #d63638;">🔗 Broken Links</h3>
                <?php if (! empty($data['pages_broken_links'])) : ?>
                    <ul style="margin: 0; padding: 0; list-style: none; font-size: 12px;">
                        <?php foreach (array_slice($data['pages_broken_links'], 0, 10) as $pg) : ?>
                            <li style="padding: 4px 0; border-bottom: 1px solid #f5f5f5;">
                                <a href="<?php echo esc_url($pg['url']); ?>" target="_blank" title="<?php echo esc_attr($pg['url']); ?>">
                                    <?php echo esc_html(mb_strimwidth($pg['title'] ?: $pg['url'], 0, 40, '…')); ?>
                                </a>
                                <span style="float: right; color: #d63638; font-weight: 600;">
                                    <?php echo (int) $pg['dead_links'] + (int) $pg['dead_images']; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p style="color: #567915; font-size: 12px; margin: 0;">✅ No broken links found.</p>
                <?php endif; ?>
            </div>

            <!-- Spelling -->
            <div>
                <h3 style="font-size: 13px; margin: 0 0 8px; color: #856404;">📝 Spelling Errors</h3>
                <?php if (! empty($data['pages_spelling'])) : ?>
                    <ul style="margin: 0; padding: 0; list-style: none; font-size: 12px;">
                        <?php foreach (array_slice($data['pages_spelling'], 0, 10) as $pg) : ?>
                            <li style="padding: 4px 0; border-bottom: 1px solid #f5f5f5;">
                                <a href="<?php echo esc_url($pg['url']); ?>" target="_blank" title="<?php echo esc_attr($pg['url']); ?>">
                                    <?php echo esc_html(mb_strimwidth($pg['title'] ?: $pg['url'], 0, 40, '…')); ?>
                                </a>
                                <span style="float: right; color: #856404; font-weight: 600;">
                                    <?php echo (int) $pg['confirmed']; ?>
                                    <?php if ($pg['potential'] > 0) : ?>
                                        <span style="font-weight: 400; color: #999;">(+<?php echo (int) $pg['potential']; ?>)</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p style="color: #567915; font-size: 12px; margin: 0;">✅ No spelling errors found.</p>
                <?php endif; ?>
            </div>

            <!-- A11y Errors -->
            <div>
                <h3 style="font-size: 13px; margin: 0 0 8px; color: #d63638;">♿ Accessibility Errors</h3>
                <?php if (! empty($data['pages_a11y_errors'])) : ?>
                    <ul style="margin: 0; padding: 0; list-style: none; font-size: 12px;">
                        <?php foreach (array_slice($data['pages_a11y_errors'], 0, 10) as $pg) : ?>
                            <li style="padding: 4px 0; border-bottom: 1px solid #f5f5f5;">
                                <a href="<?php echo esc_url($pg['url']); ?>" target="_blank" title="<?php echo esc_attr($pg['url']); ?>">
                                    <?php echo esc_html(mb_strimwidth($pg['title'] ?: $pg['url'], 0, 40, '…')); ?>
                                </a>
                                <span style="float: right; color: #d63638; font-weight: 600;">
                                    <?php echo (int) $pg['count']; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p style="color: #567915; font-size: 12px; margin: 0;">✅ No accessibility errors found.</p>
                <?php endif; ?>
            </div>

        </div>

        <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #f0f0f0; font-size: 12px; color: #666;">
            For detailed remediation, use the
            <a href="https://app2.us.monsido.com" target="_blank">Monsido platform</a>
            or install the <strong>Monsido Browser Extension</strong> for in-page error highlighting.
            <?php if (current_user_can('manage_options')) : ?>
                · <a href="<?php echo admin_url('options-general.php?page=plgc-docmgr'); ?>">Integration Settings</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * ============================================================
 * PAGE LIST — MONSIDO COLUMN
 * ============================================================
 * Adds a column to the Pages/Posts list showing Monsido
 * issue counts matched by URL.
 */

function plgc_monsido_page_columns($columns) {
    $columns['monsido'] = '🔍 Monsido';
    return $columns;
}
add_filter('manage_pages_columns', 'plgc_monsido_page_columns');
add_filter('manage_posts_columns', 'plgc_monsido_page_columns');

function plgc_monsido_page_column_content($column_name, $post_id) {
    if ($column_name !== 'monsido') return;

    $data = get_option('plgc_monsido_data', []);
    if (empty($data['totals'])) {
        echo '<span style="color: #999;">—</span>';
        return;
    }

    $post_url = get_permalink($post_id);
    $post_path = wp_parse_url($post_url, PHP_URL_PATH);

    // Search Monsido data for matching URL
    $a11y = 0;
    $links = 0;
    $spelling = 0;

    foreach ($data['pages_a11y_errors'] ?? [] as $pg) {
        if (plgc_monsido_url_match($pg['url'], $post_url, $post_path)) {
            $a11y = (int) $pg['count'];
            break;
        }
    }

    foreach ($data['pages_broken_links'] ?? [] as $pg) {
        if (plgc_monsido_url_match($pg['url'], $post_url, $post_path)) {
            $links = (int) $pg['dead_links'] + (int) $pg['dead_images'];
            break;
        }
    }

    foreach ($data['pages_spelling'] ?? [] as $pg) {
        if (plgc_monsido_url_match($pg['url'], $post_url, $post_path)) {
            $spelling = (int) $pg['confirmed'];
            break;
        }
    }

    if ($a11y === 0 && $links === 0 && $spelling === 0) {
        echo '<span style="color: #567915;">✓</span>';
        return;
    }

    $parts = [];
    if ($a11y > 0) $parts[] = '<span style="color: #d63638;" title="Accessibility errors">♿' . $a11y . '</span>';
    if ($links > 0) $parts[] = '<span style="color: #d63638;" title="Broken links/images">🔗' . $links . '</span>';
    if ($spelling > 0) $parts[] = '<span style="color: #856404;" title="Spelling errors">📝' . $spelling . '</span>';

    echo '<span style="font-size: 12px;">' . implode(' ', $parts) . '</span>';
}
add_action('manage_pages_custom_column', 'plgc_monsido_page_column_content', 10, 2);
add_action('manage_posts_custom_column', 'plgc_monsido_page_column_content', 10, 2);

/**
 * Match a Monsido URL to a WordPress post URL.
 *
 * Handles domain mismatches (temp domain in Monsido, production
 * domain in WordPress) by comparing paths only.
 */
function plgc_monsido_url_match($monsido_url, $wp_url, $wp_path) {
    // Primary: path-only comparison (handles domain mismatches)
    $m_path = wp_parse_url($monsido_url, PHP_URL_PATH);
    $m_path = rtrim($m_path ?: '', '/');
    $w_path = rtrim($wp_path ?: '', '/');

    if ($m_path === $w_path && $m_path !== '') {
        return true;
    }

    // Fallback: full URL match (exact same domain)
    $m_clean = rtrim(preg_replace('#^https?://(www\.)?#', '', $monsido_url), '/');
    $w_clean = rtrim(preg_replace('#^https?://(www\.)?#', '', $wp_url), '/');

    return $m_clean === $w_clean;
}

/**
 * ============================================================
 * CRON SCHEDULE
 * ============================================================
 */
function plgc_monsido_schedule_sync() {
    $settings = get_option('plgc_monsido_settings', []);
    $interval = $settings['auto_sync'] ?? 'daily';

    if ($interval !== 'manual' && ! wp_next_scheduled('plgc_monsido_sync_cron')) {
        wp_schedule_event(time() + 3600, $interval, 'plgc_monsido_sync_cron');
    }
}
add_action('init', 'plgc_monsido_schedule_sync');

/**
 * Clean up on deactivation.
 */
function plgc_monsido_deactivate() {
    wp_clear_scheduled_hook('plgc_monsido_sync_cron');
}
register_deactivation_hook(PLGC_DOCMGR_FILE, 'plgc_monsido_deactivate');
