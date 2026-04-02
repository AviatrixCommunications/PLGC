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
 * Parameters:
 *   contact_email  — email for requests (default: admin email)
 *   entity_name    — organization name (default: site name)
 *   show_form      — show request form (default: yes)
 */
function plgc_docmgr_archive_page_shortcode($atts) {
    $atts = shortcode_atts([
        'contact_email' => get_option('admin_email'),
        'entity_name'   => get_bloginfo('name'),
        'show_form'     => 'yes',
    ], $atts, 'plgc_archive_page');

    $categories = get_option('plgc_docmgr_categories', []);
    $requested_doc = isset($_GET['document']) ? sanitize_text_field(urldecode($_GET['document'])) : '';
    $settings = get_option('plgc_docmgr_settings', []);
    $deadline = $settings['compliance_deadline'] ?? 'April 24, 2026';

    ob_start();
    ?>
    <div class="plgc-archive-page" role="region" aria-label="Document Archive">

        <?php if ($requested_doc) : ?>
            <div role="alert" style="padding: 1.25rem; background: #fff3cd; border-left: 4px solid #FFAE40; margin-bottom: 2rem;">
                <p style="margin: 0 0 0.5rem; font-weight: 600; font-size: 1.125rem;">
                    The document &ldquo;<?php echo esc_html($requested_doc); ?>&rdquo; has been archived.
                </p>
                <p style="margin: 0;">
                    This document is no longer in active use and is retained for reference purposes only.
                    If you need this document in an accessible format, please
                    <a href="#request-form">use the request form below</a> or contact us at
                    <a href="mailto:<?php echo esc_attr($atts['contact_email']); ?>"><?php echo esc_html($atts['contact_email']); ?></a>.
                </p>
            </div>
        <?php endif; ?>

        <!-- COMPLIANCE NOTICE -->
        <div style="padding: 1.5rem; background: #f2f2f2; border-left: 4px solid #567915; margin-bottom: 2rem;">
            <h2 style="margin-top: 0;">About This Archive</h2>
            <p>
                This section contains archived documents and web content from
                <?php echo esc_html($atts['entity_name']); ?>. These materials are retained exclusively
                for reference, research, and recordkeeping purposes and are no longer in active use.
            </p>
            <p>
                In accordance with the Americans with Disabilities Act (ADA) Title II final rule
                (28 CFR &sect; 35.200), certain archived web content and pre-existing documents may
                not meet current WCAG 2.1 Level AA accessibility standards. Content in this archive
                was created before the compliance deadline of <?php echo esc_html($deadline); ?>,
                has not been modified since being archived, and is stored in this dedicated archive section.
            </p>
            <p>
                <strong>Need an accessible version?</strong> <?php echo esc_html($atts['entity_name']); ?>
                is committed to providing equal access to information for all members of the public.
                If you require any document in an accessible format, please
                <a href="#request-form">submit a request below</a> or email
                <a href="mailto:<?php echo esc_attr($atts['contact_email']); ?>"><?php echo esc_html($atts['contact_email']); ?></a>.
                We will provide the information in an accessible format or an equally effective
                alternative as promptly as possible.
            </p>
        </div>

        <!-- DOCUMENT LISTING -->
        <?php
        $archived_docs = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [['key' => '_plgc_lifecycle', 'value' => 'archived']],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (empty($archived_docs)) : ?>
            <p>No archived documents are currently available.</p>
        <?php else :
            // Group by category
            $grouped = ['uncategorized' => []];
            foreach ($categories as $cat) { $grouped[$cat['slug']] = []; }
            foreach ($archived_docs as $doc) {
                $cat = get_post_meta($doc->ID, '_plgc_doc_category', true) ?: 'uncategorized';
                $grouped[$cat][] = $doc;
            }

            $cat_labels = ['uncategorized' => 'Other Documents'];
            foreach ($categories as $cat) { $cat_labels[$cat['slug']] = $cat['label']; }

            $exception_labels = [
                'archived_content'   => 'Archived Content',
                'preexisting_doc'    => 'Pre-Existing Document',
                'third_party'        => 'Third-Party Content',
                'password_protected' => 'Password-Protected',
            ];

            foreach ($grouped as $cat_slug => $docs) :
                if (empty($docs)) continue;
                $cat_label = $cat_labels[$cat_slug] ?? 'Other';
                ?>
                <h2 id="archive-<?php echo esc_attr($cat_slug); ?>"><?php echo esc_html($cat_label); ?></h2>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 2rem;">
                    <caption class="screen-reader-text">
                        Archived <?php echo esc_html($cat_label); ?> documents retained for reference purposes
                    </caption>
                    <thead>
                        <tr style="border-bottom: 2px solid #233C26; text-align: left;">
                            <th scope="col" style="padding: 0.625rem;">Document</th>
                            <th scope="col" style="padding: 0.625rem; width: 110px;">Original Date</th>
                            <th scope="col" style="padding: 0.625rem; width: 110px;">Archived</th>
                            <th scope="col" style="padding: 0.625rem; width: 140px;">Exception</th>
                            <th scope="col" style="padding: 0.625rem; width: 70px;">Format</th>
                            <th scope="col" style="padding: 0.625rem; width: 100px;">Request</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docs as $doc) :
                            $archived_date = get_post_meta($doc->ID, '_plgc_archived_date', true);
                            $original_date = get_post_meta($doc->ID, '_plgc_original_doc_date', true);
                            $exception     = get_post_meta($doc->ID, '_plgc_title2_exception', true);
                            $replaced_by   = get_post_meta($doc->ID, '_plgc_replaced_by', true);
                            $file_path     = get_attached_file($doc->ID);
                            $ext           = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));
                            $filesize      = ($file_path && file_exists($file_path)) ? size_format(filesize($file_path)) : '';

                            $mailto_subject = 'Accessible Document Request: ' . $doc->post_title;
                            $mailto_body = "I am requesting an accessible version of the following archived document:\n\nDocument: " . $doc->post_title . "\nCategory: " . $cat_label . "\n\nPlease provide this document in an accessible format.\n\nThank you.";
                            ?>
                            <tr style="border-bottom: 1px solid #e7e4e4;">
                                <td style="padding: 0.625rem;">
                                    <strong><?php echo esc_html($doc->post_title); ?></strong>
                                    <?php if ($replaced_by) :
                                        $newer = get_post($replaced_by);
                                        if ($newer) : ?>
                                            <br><small style="color: #567915;">
                                                Current version: <a href="<?php echo esc_url(wp_get_attachment_url($replaced_by)); ?>"><?php echo esc_html($newer->post_title); ?></a>
                                            </small>
                                        <?php endif;
                                    endif; ?>
                                </td>
                                <td style="padding: 0.625rem; color: #666; font-size: 0.9em;">
                                    <?php echo $original_date ? esc_html(wp_date('M j, Y', strtotime($original_date))) : esc_html(get_the_date('M j, Y', $doc->ID)); ?>
                                </td>
                                <td style="padding: 0.625rem; color: #666; font-size: 0.9em;">
                                    <?php echo $archived_date ? esc_html(wp_date('M j, Y', strtotime($archived_date))) : '—'; ?>
                                </td>
                                <td style="padding: 0.625rem; font-size: 0.85em;">
                                    <?php if ($exception && isset($exception_labels[$exception])) : ?>
                                        <span style="background: #f2f2f2; padding: 2px 6px; border-radius: 3px;">
                                            <?php echo esc_html($exception_labels[$exception]); ?>
                                        </span>
                                    <?php else : ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.625rem;">
                                    <span style="background: #f2f2f2; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 0.85em;"><?php echo esc_html($ext); ?></span>
                                    <?php if ($filesize) : ?>
                                        <br><small style="color: #999;"><?php echo esc_html($filesize); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.625rem;">
                                    <a href="mailto:<?php echo esc_attr($atts['contact_email']); ?>?subject=<?php echo rawurlencode($mailto_subject); ?>&body=<?php echo rawurlencode($mailto_body); ?>"
                                       aria-label="Request accessible version of <?php echo esc_attr($doc->post_title); ?>"
                                       style="font-size: 0.85em;">
                                        📧 Request
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach;
        endif; ?>

        <!-- REQUEST FORM -->
        <?php if ($atts['show_form'] === 'yes') : ?>
        <div style="padding: 1.5rem; background: #e5f0d0; border-radius: 0.5rem; margin-top: 2rem;">
            <h2 id="request-form" style="margin-top: 0;">Request an Accessible Document</h2>
            <p>
                If you need any document from this archive in an accessible format, please provide
                your information below. We will respond as promptly as possible.
            </p>

            <?php if (isset($_GET['request_sent'])) : ?>
                <div role="status" style="padding: 1rem; background: #567915; color: #fff; border-radius: 0.25rem; margin-bottom: 1rem;">
                    <strong>✓ Your request has been received.</strong> We will provide the accessible document as soon as possible.
                </div>
            <?php endif; ?>

            <form method="post" action="" aria-labelledby="request-form">
                <?php wp_nonce_field('plgc_archive_request'); ?>
                <input type="hidden" name="plgc_archive_request" value="1" />

                <p>
                    <label for="plgc_req_name" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">Your Name <span aria-hidden="true">*</span><span class="screen-reader-text">(required)</span></label>
                    <input type="text" id="plgc_req_name" name="plgc_req_name" required aria-required="true" autocomplete="name" style="width: 100%; max-width: 400px; padding: 0.5rem;" />
                </p>
                <p>
                    <label for="plgc_req_email" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">Email Address <span aria-hidden="true">*</span><span class="screen-reader-text">(required)</span></label>
                    <input type="email" id="plgc_req_email" name="plgc_req_email" required aria-required="true" autocomplete="email" style="width: 100%; max-width: 400px; padding: 0.5rem;" />
                </p>
                <p>
                    <label for="plgc_req_phone" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">Phone Number (optional)</label>
                    <input type="tel" id="plgc_req_phone" name="plgc_req_phone" autocomplete="tel" style="width: 100%; max-width: 400px; padding: 0.5rem;" />
                </p>
                <p>
                    <label for="plgc_req_document" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">Document Name or Description <span aria-hidden="true">*</span><span class="screen-reader-text">(required)</span></label>
                    <input type="text" id="plgc_req_document" name="plgc_req_document" required aria-required="true" style="width: 100%; max-width: 400px; padding: 0.5rem;" value="<?php echo esc_attr($requested_doc); ?>" />
                </p>
                <p>
                    <label for="plgc_req_format" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">Preferred Accessible Format</label>
                    <select id="plgc_req_format" name="plgc_req_format" style="padding: 0.5rem;">
                        <option value="">No preference</option>
                        <option value="accessible_pdf">Accessible PDF (tagged, screen reader compatible)</option>
                        <option value="html">HTML web page</option>
                        <option value="word">Microsoft Word document</option>
                        <option value="large_print">Large print</option>
                        <option value="braille">Braille</option>
                        <option value="audio">Audio recording / description</option>
                        <option value="plain_text">Plain text</option>
                        <option value="other">Other (please describe below)</option>
                    </select>
                </p>
                <p>
                    <label for="plgc_req_notes" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">Additional Details (optional)</label>
                    <textarea id="plgc_req_notes" name="plgc_req_notes" rows="3" style="width: 100%; max-width: 400px; padding: 0.5rem;"></textarea>
                </p>
                <p>
                    <button type="submit" style="background: #567915; color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 6.25rem; font-weight: 600; cursor: pointer; font-size: 1rem; min-height: 44px;">
                        Submit Request
                    </button>
                </p>
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
    if (! isset($_POST['plgc_archive_request']) || ! wp_verify_nonce($_POST['_wpnonce'], 'plgc_archive_request')) {
        return;
    }

    $name     = sanitize_text_field($_POST['plgc_req_name'] ?? '');
    $email    = sanitize_email($_POST['plgc_req_email'] ?? '');
    $phone    = sanitize_text_field($_POST['plgc_req_phone'] ?? '');
    $document = sanitize_text_field($_POST['plgc_req_document'] ?? '');
    $format   = sanitize_text_field($_POST['plgc_req_format'] ?? '');
    $notes    = sanitize_textarea_field($_POST['plgc_req_notes'] ?? '');

    if (empty($name) || empty($email) || empty($document)) {
        return;
    }

    $settings    = get_option('plgc_docmgr_settings', []);
    $admin_email = $settings['notify_email'] ?? get_option('admin_email');
    $site_name   = get_bloginfo('name');

    $format_labels = [
        'accessible_pdf' => 'Accessible PDF',
        'html'           => 'HTML web page',
        'word'           => 'Microsoft Word',
        'large_print'    => 'Large print',
        'braille'        => 'Braille',
        'audio'          => 'Audio',
        'plain_text'     => 'Plain text',
        'other'          => 'Other',
    ];

    $subject = sprintf('[%s] Accessible Document Request: %s', $site_name, $document);

    $message  = "ACCESSIBLE DOCUMENT REQUEST\n";
    $message .= str_repeat('=', 50) . "\n\n";
    $message .= "Requester:  " . $name . "\n";
    $message .= "Email:      " . $email . "\n";
    if ($phone) { $message .= "Phone:      " . $phone . "\n"; }
    $message .= "Document:   " . $document . "\n";
    $message .= "Format:     " . ($format_labels[$format] ?? 'No preference') . "\n";
    if ($notes) { $message .= "\nDetails:\n" . $notes . "\n"; }
    $message .= "\nSubmitted:  " . current_time('F j, Y g:i A') . "\n";
    $message .= "\n" . str_repeat('-', 50) . "\n";
    $message .= "REMINDER: Under ADA Title II (28 CFR § 35.160), you must provide\n";
    $message .= "this information in an accessible format or equally effective\n";
    $message .= "alternative as promptly as possible. Document your response.\n";

    wp_mail($admin_email, $subject, $message, ['Reply-To: ' . $name . ' <' . $email . '>']);

    // Audit log
    $requests = get_option('plgc_docmgr_access_requests', []);
    $requests[] = compact('name', 'email', 'phone', 'document', 'format', 'notes') + ['date' => current_time('mysql')];
    if (count($requests) > 500) { $requests = array_slice($requests, -500); }
    update_option('plgc_docmgr_access_requests', $requests);

    wp_redirect(add_query_arg('request_sent', '1', wp_get_referer() ?: home_url('/')));
    exit;
}
add_action('init', 'plgc_docmgr_handle_archive_request');
