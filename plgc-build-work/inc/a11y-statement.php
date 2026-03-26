<?php
/**
 * Accessibility Statement
 *
 * Shortcode that generates a WCAG/ADA Title II compliant
 * accessibility statement. The DOJ expects public entities
 * to have one, and it demonstrates good faith.
 *
 * Usage: [plgc_accessibility_statement]
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

function plgc_a11y_statement_shortcode($atts) {
    $atts = shortcode_atts([
        'entity_name'    => get_bloginfo('name'),
        'contact_email'  => get_option('admin_email'),
        'contact_phone'  => '',
        'coordinator'    => '',
        'standard'       => 'WCAG 2.1 Level AA',
        'archive_url'    => '',
    ], $atts, 'plgc_accessibility_statement');

    $settings = get_option('plgc_docmgr_settings', []);
    $deadline = $settings['compliance_deadline'] ?? 'April 24, 2026';
    $archive_page = $settings['archive_page'] ?? 0;
    $archive_url = $atts['archive_url'] ?: ($archive_page ? get_permalink($archive_page) : '');
    $year = wp_date('Y');

    ob_start();
    ?>
    <div class="plgc-a11y-statement">

        <h2>Our Commitment</h2>
        <p>
            <?php echo esc_html($atts['entity_name']); ?> is committed to ensuring that its website
            and digital content are accessible to all users, including individuals with disabilities.
            We strive to meet or exceed the requirements of the Americans with Disabilities Act (ADA)
            Title II and the Web Content Accessibility Guidelines (<?php echo esc_html($atts['standard']); ?>).
        </p>

        <h2>Accessibility Standards</h2>
        <p>
            This website is designed to conform with <?php echo esc_html($atts['standard']); ?>,
            the technical standard adopted by the U.S. Department of Justice under ADA Title II
            (28 CFR Part 35, Subpart H). We actively work to identify and address accessibility
            barriers and continuously improve the user experience for all visitors.
        </p>

        <h2>What We Are Doing</h2>
        <p>
            <?php echo esc_html($atts['entity_name']); ?> has taken the following steps to ensure
            accessibility of this website:
        </p>
        <ul>
            <li>Conducting regular accessibility audits of our web content and documents</li>
            <li>Using automated and manual testing to identify accessibility barriers</li>
            <li>Training staff who create and maintain website content on accessibility best practices</li>
            <li>Validating uploaded documents against accessibility standards before publishing</li>
            <li>Including accessibility requirements in our procurement of digital tools and services</li>
            <li>Maintaining a dedicated document archive for legacy content with an accessible request process</li>
        </ul>

        <h2>Known Limitations</h2>
        <p>
            While we strive for full accessibility, some older content may not yet meet all
            <?php echo esc_html($atts['standard']); ?> success criteria. We are actively working to
            remediate known issues. Pre-existing documents and archived content created before
            <?php echo esc_html($deadline); ?> may not meet current accessibility standards but are
            available in accessible formats upon request.
        </p>

        <?php if ($archive_url) : ?>
        <h2>Archived Content</h2>
        <p>
            Documents that are no longer in active use are maintained in our
            <a href="<?php echo esc_url($archive_url); ?>">document archive</a>.
            Archived content may not meet current accessibility standards. If you need any
            archived document in an accessible format, you can submit a request through the
            archive page or contact us directly.
        </p>
        <?php endif; ?>

        <h2>Feedback and Assistance</h2>
        <p>
            We welcome your feedback on the accessibility of this website. If you encounter
            an accessibility barrier, need information in an alternative format, or have
            suggestions for improvement, please contact us:
        </p>
        <ul>
            <?php if ($atts['contact_email']) : ?>
                <li>Email: <a href="mailto:<?php echo esc_attr($atts['contact_email']); ?>"><?php echo esc_html($atts['contact_email']); ?></a></li>
            <?php endif; ?>
            <?php if ($atts['contact_phone']) : ?>
                <li>Phone: <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $atts['contact_phone'])); ?>" aria-label="Call <?php echo esc_attr($atts['contact_phone']); ?>"><?php echo esc_html($atts['contact_phone']); ?></a></li>
            <?php endif; ?>
            <?php if ($atts['coordinator']) : ?>
                <li>ADA/Accessibility Coordinator: <?php echo esc_html($atts['coordinator']); ?></li>
            <?php endif; ?>
        </ul>
        <p>
            We aim to respond to accessibility feedback and accommodation requests as promptly
            as possible. When you contact us, please include the web address (URL) of the content
            and a description of the issue or the specific assistance you need.
        </p>

        <h2>Complaint Process</h2>
        <p>
            If you believe you have been discriminated against on the basis of disability in
            accessing this website or our digital services, you may file a complaint with:
        </p>
        <ul>
            <li><?php echo esc_html($atts['entity_name']); ?> using the contact information above</li>
            <li>The U.S. Department of Justice, Civil Rights Division, at
                <a href="https://www.ada.gov/file-a-complaint/" rel="noopener noreferrer">ADA.gov</a>
            </li>
        </ul>

        <p style="margin-top: 2rem; font-size: 0.9em; color: #666;">
            This statement was last updated on <?php echo wp_date('F j, Y'); ?>.
        </p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('plgc_accessibility_statement', 'plgc_a11y_statement_shortcode');
