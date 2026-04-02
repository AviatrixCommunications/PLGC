<?php
/**
 * Accessibility Statement
 *
 * Generates a comprehensive, WCAG-compliant accessibility statement
 * for Prairie Landing Golf Club (operated by the DuPage Airport Authority).
 *
 * Covers:
 * - ADA Title II obligations (special district — April 26, 2027 deadline)
 * - Section 504 of the Rehabilitation Act (federal financial assistance recipient)
 * - WCAG 2.1 Level AA conformance
 * - Third-party service disclosures (Club Caddie, Golfback)
 * - Feedback & complaint process
 *
 * Usage: [plgc_accessibility_statement]
 *
 * @package PLGC
 */

defined('ABSPATH') || exit;

function plgc_a11y_statement_shortcode($atts) {

    $year = wp_date('Y');

    ob_start();
    ?>
    <div class="plgc-a11y-statement" aria-label="Accessibility Statement">

        <h2>Our Commitment to Accessibility</h2>
        <p>
            Prairie Landing Golf Club is a business unit of the
            <strong>DuPage Airport Authority</strong>, an independent governmental body
            established by the State of Illinois. As a public entity, the DuPage Airport
            Authority is committed to ensuring that Prairie Landing Golf Club's website,
            digital content, and online services are accessible to all individuals, including
            people with disabilities.
        </p>
        <p>
            We believe that every visitor — whether planning a round of golf, reserving a
            table, purchasing event tickets, or simply browsing — deserves an equal and
            effective digital experience.
        </p>

        <h2>Applicable Laws and Standards</h2>
        <p>
            As a special district of state government, the DuPage Airport Authority and its
            business units, including Prairie Landing Golf Club, are subject to the following
            federal accessibility requirements:
        </p>
        <ul>
            <li>
                <strong>Title II of the Americans with Disabilities Act (ADA)</strong> —
                Prohibits discrimination on the basis of disability in all services, programs,
                and activities of state and local governments. Under the U.S. Department of
                Justice final rule published April 24, 2024 (28 CFR Part 35, Subpart H),
                all web content and mobile applications must conform to
                <strong>WCAG 2.1 Level AA</strong>. As a special district government, the
                DuPage Airport Authority's compliance deadline is <strong>April 26, 2027</strong>.
            </li>
            <li>
                <strong>Section 504 of the Rehabilitation Act of 1973</strong> —
                Prohibits discrimination on the basis of disability in any program or activity
                receiving federal financial assistance. The DuPage Airport Authority receives
                federal funding through the Federal Aviation Administration (FAA) and is
                therefore subject to Section 504's nondiscrimination requirements, including
                ensuring accessible digital services and effective communication with
                individuals with disabilities.
            </li>
        </ul>
        <p>
            The technical standard we conform to is the
            <strong>Web Content Accessibility Guidelines (WCAG) 2.1 Level AA</strong>,
            published by the World Wide Web Consortium (W3C). These guidelines ensure that
            web content is perceivable, operable, understandable, and robust for users of
            all abilities.
        </p>

        <h2>What We Have Done</h2>
        <p>
            Prairie Landing Golf Club has taken meaningful steps to design, develop, and
            maintain an accessible website. These include:
        </p>
        <ul>
            <li>
                <strong>Standards-based design and development</strong> — The website was
                designed and built from the ground up with WCAG 2.1 Level AA conformance as
                a core requirement, not an afterthought. Accessibility is integrated into our
                design system, theme code, and content workflows.
            </li>
            <li>
                <strong>Semantic HTML and heading structure</strong> — Pages use proper heading
                hierarchy (H1 through H6 in logical order), landmark roles, and semantic
                elements so assistive technologies can reliably navigate content.
            </li>
            <li>
                <strong>Keyboard accessibility</strong> — All interactive elements — navigation
                menus, accordions, forms, event calendars, and ticket purchasing — are operable
                using a keyboard alone. A visible focus indicator is provided for all
                interactive components.
            </li>
            <li>
                <strong>Skip navigation</strong> — A "Skip to main content" link is provided
                at the top of every page so keyboard and screen reader users can bypass
                repetitive navigation.
            </li>
            <li>
                <strong>Color contrast and visual design</strong> — All text and interactive
                elements meet or exceed WCAG's minimum contrast ratios: 4.5:1 for normal text
                and 3:1 for large text and UI components. Color is never used as the sole means
                of conveying information.
            </li>
            <li>
                <strong>Responsive and scalable design</strong> — The site supports browser
                zoom up to 200% without loss of content or functionality. The layout adapts to
                all screen sizes, and text is set in relative units (rem) to respect user font
                size preferences.
            </li>
            <li>
                <strong>Reduced motion support</strong> — Animations and transitions are
                minimized or disabled for users who have enabled the "prefers-reduced-motion"
                setting in their operating system or browser.
            </li>
            <li>
                <strong>Image accessibility</strong> — Informative images include descriptive
                alternative text. Decorative images are marked appropriately so screen readers
                skip them.
            </li>
            <li>
                <strong>Accessible forms</strong> — All form fields have visible, associated
                labels. Required fields are clearly indicated. Error messages appear near the
                relevant field and describe how to correct the issue.
            </li>
            <li>
                <strong>Content guardrails</strong> — Automated checks are built into the
                content management system to flag common accessibility issues — such as missing
                alternative text, vague link text, and heading hierarchy problems — before
                content is published.
            </li>
            <li>
                <strong>Accessible media</strong> — Video and audio embeds are checked for
                captions and descriptive titles. Iframes include title attributes for screen
                reader context.
            </li>
            <li>
                <strong>Accessible purchasing</strong> — The event ticket and online merchandise
                purchasing flow is built with accessible cart, checkout, and payment
                confirmation experiences, including properly labeled form controls and clear
                order summaries.
            </li>
        </ul>

        <h2>Ongoing Efforts</h2>
        <p>
            Accessibility is not a one-time project — it requires continuous attention. We are
            committed to the following ongoing practices:
        </p>
        <ul>
            <li>
                Conducting periodic accessibility reviews using both automated tools and manual
                testing with assistive technologies.
            </li>
            <li>
                Training staff who create and maintain website content on accessibility best
                practices and WCAG requirements.
            </li>
            <li>
                Including accessibility requirements in our procurement process for digital
                tools and third-party services.
            </li>
            <li>
                Monitoring and responding to user feedback about accessibility barriers.
            </li>
            <li>
                Reviewing and remediating newly published content on an ongoing basis.
            </li>
        </ul>

        <h2>Third-Party Content and Services</h2>
        <p>
            Some features and services on this website are provided or managed by third-party
            vendors. While we include accessibility expectations in our vendor relationships
            and procurement process, the DuPage Airport Authority cannot directly control the
            accessibility of content or functionality hosted on external platforms. These
            third-party services include:
        </p>
        <ul>
            <li>
                <strong>Online tee time booking</strong> — Tee time reservations are managed
                through a platform provided by <strong>Golfback</strong>. The booking
                interface may link to or embed content from Golfback's systems.
            </li>
            <li>
                <strong>Mobile application</strong> — The Prairie Landing Golf Club mobile app
                is developed and managed by <strong>Club Caddie Holdings, Inc.</strong>
                Accessibility of the mobile application is the responsibility of Club Caddie
                Holdings. If you encounter accessibility barriers within the mobile app, we
                encourage you to contact us so we can relay your feedback to the vendor and
                work toward a resolution.
            </li>
            <li>
                <strong>Payment processing</strong> — Online payments are processed through a
                secure hosted payment form. The payment provider is responsible for the
                accessibility of their hosted form.
            </li>
        </ul>
        <p>
            Under ADA Title II, responsibility for ensuring accessible digital services
            remains with the public entity regardless of whether a third party provides the
            technology. We actively work with our vendors to address accessibility issues and
            are committed to providing alternative means of access when third-party tools
            present barriers.
        </p>

        <h2>Known Limitations</h2>
        <p>
            While we strive for comprehensive accessibility, we are aware of the following
            areas where improvements may be needed:
        </p>
        <ul>
            <li>
                Some older documents (PDFs, flyers, menus) published before the site redesign
                may not fully conform to WCAG 2.1 Level AA. We are remediating these on a
                priority basis and will provide accessible alternatives upon request.
            </li>
            <li>
                Third-party embedded content (tee time booking, payment forms) may have
                accessibility limitations outside our direct control. We are working with
                these vendors to improve conformance and can assist you with completing
                transactions through alternative methods if needed.
            </li>
        </ul>

        <h2>Feedback and Requests for Assistance</h2>
        <p>
            We welcome your feedback on the accessibility of the Prairie Landing Golf Club
            website. If you encounter an accessibility barrier, need information in an
            alternative format, or have suggestions for how we can improve, please contact us:
        </p>
        <ul>
            <li>
                <strong>Email:</strong>
                <a href="mailto:websitesupport@dupageairport.gov">websitesupport@dupageairport.gov</a>
            </li>
            <li>
                <strong>Phone:</strong>
                <a href="tel:+16302087600" aria-label="Call 630-208-7600">630.208.7600</a>
            </li>
            <li>
                <strong>Mail:</strong>
                Prairie Landing Golf Club, 2325 Longest Drive, West Chicago, IL 60185
            </li>
        </ul>
        <p>
            When contacting us, please include the web address (URL) of the page where you
            experienced the issue and a description of the problem or the specific assistance
            you need. We aim to respond to accessibility feedback within five (5) business
            days.
        </p>

        <h2>Alternative Access</h2>
        <p>
            If you are unable to access any content or complete a transaction on this website
            due to a disability, please contact us using the information above. We will work
            with you to provide the information or service you need through an alternative
            method. This may include:
        </p>
        <ul>
            <li>Providing documents in an accessible format (large print, plain text, or other format)</li>
            <li>Assisting with tee time reservations, event ticket purchases, or dining reservations by phone</li>
            <li>Reading or describing website content over the phone or via email</li>
        </ul>

        <h2>Formal Complaint Process</h2>
        <p>
            If you believe you have been discriminated against on the basis of disability in
            accessing this website or any digital services of Prairie Landing Golf Club or
            the DuPage Airport Authority, you may file a complaint through any of the
            following channels:
        </p>
        <ul>
            <li>
                <strong>DuPage Airport Authority</strong> — Contact us directly at
                <a href="mailto:websitesupport@dupageairport.gov">websitesupport@dupageairport.gov</a>
                or 630.208.7600. We will investigate and respond to your complaint.
            </li>
            <li>
                <strong>U.S. Department of Justice</strong> — You may file a complaint with the
                Civil Rights Division at
                <a href="https://www.ada.gov/file-a-complaint/" rel="noopener noreferrer">ADA.gov</a>.
            </li>
            <li>
                <strong>U.S. Department of Transportation</strong> — For complaints related to
                the DuPage Airport Authority's federally assisted programs, you may also
                contact the DOT Office of Civil Rights at
                <a href="https://www.transportation.gov/civil-rights/complaint" rel="noopener noreferrer">transportation.gov</a>.
            </li>
        </ul>

        <p class="plgc-a11y-statement__updated">
            This accessibility statement was last reviewed and updated on <?php echo wp_date('F j, Y'); ?>.
        </p>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('plgc_accessibility_statement', 'plgc_a11y_statement_shortcode');
