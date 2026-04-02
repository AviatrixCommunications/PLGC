<?php
/**
 * Archive Page — Title II Compliant
 *
 * DOJ Requirements for the archive area:
 * 1. Dedicated area clearly identified as archived
 * 2. Explanation of what the content is
 * 3. Statement of how it qualifies under the rule
 * 4. Method to request accessible alternative versions
 * 5. The archive page ITSELF must be WCAG 2.1 AA compliant
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

/**
 * Shortcode: [plgc_archive_page]
 *
 * Contact info and ADA coordinator details are pulled from
 * Compliance Suite → General settings. Shortcode attributes
 * override settings if provided.
 */
function plgc_docmgr_archive_page_shortcode($atts) {
    $settings = get_option('plgc_docmgr_settings', []);

    $atts = shortcode_atts([
        'contact_email'    => $settings['archive_contact_email'] ?? get_option('admin_email'),
        'entity_name'      => get_bloginfo('name'),
        'ada_name'         => $settings['ada_coordinator_name']  ?? '',
        'ada_email'        => $settings['ada_coordinator_email'] ?? '',
        'ada_phone'        => $settings['ada_coordinator_phone'] ?? '',
        'show_form'        => 'yes',
    ], $atts, 'plgc_archive_page');

    $contact_email = ! empty($atts['ada_email']) ? $atts['ada_email'] : $atts['contact_email'];
    $categories    = get_option('plgc_docmgr_categories', []);
    $requested_doc = isset($_GET['document']) ? sanitize_text_field(urldecode($_GET['document'])) : '';
    $deadline      = $settings['compliance_deadline'] ?? 'April 24, 2026';

    wp_enqueue_style('plgc-archive-page', PLGC_DOCMGR_URI . 'assets/css/archive-page.css', [], PLGC_DOCMGR_VERSION);

    ob_start();
    ?>
    <div class="plgc-archive" role="region" aria-label="Document Archive">

        <?php if ($requested_doc) : ?>
            <div class="plgc-archive__redirect-notice" role="alert">
                <p class="plgc-archive__redirect-title">
                    The document &ldquo;<?php echo esc_html($requested_doc); ?>&rdquo; has been archived.
                </p>
                <p>This document is no longer in active use. If you need it in an accessible format, please <a href="#plgc-request-form">use the request form below</a> or contact <a href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a>.</p>
            </div>
        <?php endif; ?>

        <div class="plgc-archive__notice">
            <h2 class="plgc-archive__notice-title">About This Archive</h2>
            <p>This section contains archived documents and web content from <?php echo esc_html($atts['entity_name']); ?>. These materials are retained exclusively for reference, research, and recordkeeping purposes and are no longer in active use.</p>
            <p>In accordance with the Americans with Disabilities Act (ADA) Title II final rule (28 CFR &sect; 35.200), certain archived web content and pre-existing documents may not meet current WCAG 2.1 Level AA accessibility standards. Content in this archive was created before the compliance deadline of <?php echo esc_html($deadline); ?>, has not been modified since being archived, and is stored in this dedicated archive section.</p>
            <p><strong>Need an accessible version?</strong> <?php echo esc_html($atts['entity_name']); ?> is committed to providing equal access to information. If you require any document in an accessible format, please <a href="#plgc-request-form">submit a request below</a> or email <a href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a>. We will provide it as promptly as possible.</p>
        </div>

        <?php if (! empty($atts['ada_name']) || ! empty($atts['ada_email'])) : ?>
            <div class="plgc-archive__ada-coordinator">
                <h2 class="plgc-archive__section-title">ADA Coordinator</h2>
                <?php if (! empty($atts['ada_name'])) : ?><p class="plgc-archive__ada-name"><?php echo esc_html($atts['ada_name']); ?></p><?php endif; ?>
                <?php if (! empty($atts['ada_email'])) : ?><p><a href="mailto:<?php echo esc_attr($atts['ada_email']); ?>"><?php echo esc_html($atts['ada_email']); ?></a></p><?php endif; ?>
                <?php if (! empty($atts['ada_phone'])) : ?><p><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $atts['ada_phone'])); ?>"><?php echo esc_html($atts['ada_phone']); ?></a></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        $archived_docs = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_query' => [['key' => '_plgc_lifecycle', 'value' => 'archived']], 'orderby' => 'title', 'order' => 'ASC']);

        if (empty($archived_docs)) : ?>
            <p>No archived documents are currently available.</p>
        <?php else :
            $grouped = ['uncategorized' => []];
            foreach ($categories as $cat) { $grouped[$cat['slug']] = []; }
            foreach ($archived_docs as $doc) { $cat = get_post_meta($doc->ID, '_plgc_doc_category', true) ?: 'uncategorized'; $grouped[$cat][] = $doc; }
            $cat_labels = ['uncategorized' => 'Other Documents'];
            foreach ($categories as $cat) { $cat_labels[$cat['slug']] = $cat['label']; }
            $exception_labels = ['archived_content' => 'Archived Content', 'preexisting_doc' => 'Pre-Existing Document', 'third_party' => 'Third-Party Content', 'password_protected' => 'Password-Protected'];

            foreach ($grouped as $cat_slug => $docs) :
                if (empty($docs)) continue;
                $cat_label = $cat_labels[$cat_slug] ?? 'Other';
                ?>
                <h2 id="archive-<?php echo esc_attr($cat_slug); ?>" class="plgc-archive__section-title"><?php echo esc_html($cat_label); ?></h2>
                <div class="plgc-archive__table-wrap">
                    <table class="plgc-archive__table">
                        <caption class="screen-reader-text">Archived <?php echo esc_html($cat_label); ?> documents</caption>
                        <thead><tr>
                            <th scope="col">Document</th>
                            <th scope="col" class="plgc-archive__col-date">Original Date</th>
                            <th scope="col" class="plgc-archive__col-date">Archived</th>
                            <th scope="col" class="plgc-archive__col-exception">Exception</th>
                            <th scope="col" class="plgc-archive__col-format">Format</th>
                            <th scope="col" class="plgc-archive__col-actions">Actions</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($docs as $doc) :
                                $archived_date = get_post_meta($doc->ID, '_plgc_archived_date', true);
                                $original_date = get_post_meta($doc->ID, '_plgc_original_doc_date', true);
                                $exception     = get_post_meta($doc->ID, '_plgc_title2_exception', true);
                                $replaced_by   = get_post_meta($doc->ID, '_plgc_replaced_by', true);
                                $file_url      = wp_get_attachment_url($doc->ID);
                                $file_path     = get_attached_file($doc->ID);
                                $ext           = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));
                                $filesize      = ($file_path && file_exists($file_path)) ? size_format(filesize($file_path)) : '';
                                $mailto_subject = 'Accessible Document Request: ' . $doc->post_title;
                                $mailto_body = "I am requesting an accessible version of:\n\nDocument: " . $doc->post_title . "\nCategory: " . $cat_label . "\n\nPlease provide this in an accessible format.\n\nThank you.";
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($doc->post_title); ?></strong>
                                    <?php if ($replaced_by) : $newer = get_post($replaced_by); if ($newer) : ?>
                                        <br><small class="plgc-archive__newer-version">Current version: <a href="<?php echo esc_url(wp_get_attachment_url($replaced_by)); ?>"><?php echo esc_html($newer->post_title); ?></a></small>
                                    <?php endif; endif; ?>
                                </td>
                                <td class="plgc-archive__cell-meta"><?php echo $original_date ? esc_html(wp_date('M j, Y', strtotime($original_date))) : esc_html(get_the_date('M j, Y', $doc->ID)); ?></td>
                                <td class="plgc-archive__cell-meta"><?php echo $archived_date ? esc_html(wp_date('M j, Y', strtotime($archived_date))) : '—'; ?></td>
                                <td class="plgc-archive__cell-meta"><?php if ($exception && isset($exception_labels[$exception])) : ?><span class="plgc-archive__badge"><?php echo esc_html($exception_labels[$exception]); ?></span><?php else : ?><span class="plgc-archive__muted">—</span><?php endif; ?></td>
                                <td><span class="plgc-archive__badge plgc-archive__badge--mono"><?php echo esc_html($ext); ?></span><?php if ($filesize) : ?><br><small class="plgc-archive__muted"><?php echo esc_html($filesize); ?></small><?php endif; ?></td>
                                <td class="plgc-archive__cell-actions">
                                    <?php if ($file_url) : ?><a href="<?php echo esc_url($file_url); ?>" class="plgc-archive__action-link" aria-label="Download <?php echo esc_attr($doc->post_title); ?> (<?php echo esc_attr($ext); ?><?php if ($filesize) echo ', ' . esc_attr($filesize); ?>)" target="_blank" rel="noopener noreferrer">📥 Download <span class="screen-reader-text">(opens in new tab)</span></a><?php endif; ?>
                                    <a href="mailto:<?php echo esc_attr($contact_email); ?>?subject=<?php echo rawurlencode($mailto_subject); ?>&body=<?php echo rawurlencode($mailto_body); ?>" class="plgc-archive__action-link" aria-label="Request accessible version of <?php echo esc_attr($doc->post_title); ?>">📧 Request Accessible Version</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach;
        endif; ?>

        <?php if ($atts['show_form'] === 'yes') : ?>
        <div class="plgc-archive__form-section" id="plgc-request-form">
            <h2 class="plgc-archive__form-title">Request an Accessible Document</h2>
            <p class="plgc-archive__form-intro">If you need any document from this archive in an accessible format, please provide your information below. We will respond as promptly as possible.</p>
            <?php if (isset($_GET['request_sent'])) : ?>
                <div class="plgc-archive__success" role="status"><strong>✓ Your request has been received.</strong> We will provide the accessible document as soon as possible.</div>
            <?php endif; ?>
            <form method="post" action="" class="plgc-archive__form" aria-labelledby="plgc-request-form">
                <?php wp_nonce_field('plgc_archive_request'); ?>
                <input type="hidden" name="plgc_archive_request" value="1" />
                <div class="plgc-archive__field"><label for="plgc_req_name" class="plgc-archive__label">Your Name <span class="plgc-archive__required" aria-hidden="true">*</span><span class="screen-reader-text">(required)</span></label><input type="text" id="plgc_req_name" name="plgc_req_name" required aria-required="true" autocomplete="name" class="plgc-archive__input" /></div>
                <div class="plgc-archive__field"><label for="plgc_req_email" class="plgc-archive__label">Email Address <span class="plgc-archive__required" aria-hidden="true">*</span><span class="screen-reader-text">(required)</span></label><input type="email" id="plgc_req_email" name="plgc_req_email" required aria-required="true" autocomplete="email" class="plgc-archive__input" /></div>
                <div class="plgc-archive__field"><label for="plgc_req_phone" class="plgc-archive__label">Phone Number (optional)</label><input type="tel" id="plgc_req_phone" name="plgc_req_phone" autocomplete="tel" class="plgc-archive__input" /></div>
                <div class="plgc-archive__field"><label for="plgc_req_document" class="plgc-archive__label">Document Name or Description <span class="plgc-archive__required" aria-hidden="true">*</span><span class="screen-reader-text">(required)</span></label><input type="text" id="plgc_req_document" name="plgc_req_document" required aria-required="true" class="plgc-archive__input" value="<?php echo esc_attr($requested_doc); ?>" /></div>
                <div class="plgc-archive__field"><label for="plgc_req_format" class="plgc-archive__label">Preferred Accessible Format</label><select id="plgc_req_format" name="plgc_req_format" class="plgc-archive__select"><option value="">No preference</option><option value="accessible_pdf">Accessible PDF (tagged, screen reader compatible)</option><option value="html">HTML web page</option><option value="word">Microsoft Word document</option><option value="large_print">Large print</option><option value="braille">Braille</option><option value="audio">Audio recording / description</option><option value="plain_text">Plain text</option><option value="other">Other (please describe below)</option></select></div>
                <div class="plgc-archive__field"><label for="plgc_req_notes" class="plgc-archive__label">Additional Details (optional)</label><textarea id="plgc_req_notes" name="plgc_req_notes" rows="3" class="plgc-archive__textarea"></textarea></div>
                <div class="plgc-archive__field"><button type="submit" class="plgc-archive__submit">Submit Request</button></div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('plgc_archive_page', 'plgc_docmgr_archive_page_shortcode');

/**
 * Handle form submission.
 */
function plgc_docmgr_handle_archive_request() {
    if (! isset($_POST['plgc_archive_request']) || ! wp_verify_nonce($_POST['_wpnonce'], 'plgc_archive_request')) { return; }
    $name = sanitize_text_field($_POST['plgc_req_name'] ?? ''); $email = sanitize_email($_POST['plgc_req_email'] ?? ''); $phone = sanitize_text_field($_POST['plgc_req_phone'] ?? '');
    $document = sanitize_text_field($_POST['plgc_req_document'] ?? ''); $format = sanitize_text_field($_POST['plgc_req_format'] ?? ''); $notes = sanitize_textarea_field($_POST['plgc_req_notes'] ?? '');
    if (empty($name) || empty($email) || empty($document)) { return; }
    $settings = get_option('plgc_docmgr_settings', []); $admin_email = $settings['ada_coordinator_email'] ?? ($settings['notify_email'] ?? get_option('admin_email')); $site_name = get_bloginfo('name');
    $format_labels = ['accessible_pdf' => 'Accessible PDF', 'html' => 'HTML web page', 'word' => 'Microsoft Word', 'large_print' => 'Large print', 'braille' => 'Braille', 'audio' => 'Audio', 'plain_text' => 'Plain text', 'other' => 'Other'];
    $subject = sprintf('[%s] Accessible Document Request: %s', $site_name, $document);
    $message = "ACCESSIBLE DOCUMENT REQUEST\n" . str_repeat('=', 50) . "\n\nRequester:  " . $name . "\nEmail:      " . $email . "\n";
    if ($phone) { $message .= "Phone:      " . $phone . "\n"; }
    $message .= "Document:   " . $document . "\nFormat:     " . ($format_labels[$format] ?? 'No preference') . "\n";
    if ($notes) { $message .= "\nDetails:\n" . $notes . "\n"; }
    $message .= "\nSubmitted:  " . current_time('F j, Y g:i A') . "\n\n" . str_repeat('-', 50) . "\nREMINDER: Under ADA Title II (28 CFR § 35.160), you must provide\nthis information in an accessible format or equally effective\nalternative as promptly as possible. Document your response.\n";
    wp_mail($admin_email, $subject, $message, ['Reply-To: ' . $name . ' <' . $email . '>']);
    $requests = get_option('plgc_docmgr_access_requests', []); $requests[] = compact('name', 'email', 'phone', 'document', 'format', 'notes') + ['date' => current_time('mysql')];
    if (count($requests) > 500) { $requests = array_slice($requests, -500); } update_option('plgc_docmgr_access_requests', $requests);
    wp_redirect(add_query_arg('request_sent', '1', wp_get_referer() ?: home_url('/'))); exit;
}
add_action('init', 'plgc_docmgr_handle_archive_request');
