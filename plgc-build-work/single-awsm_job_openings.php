<?php
/**
 * Single Job Opening Template
 *
 * Renders individual WP Job Openings (AWSM) job listings.
 * The AWSM plugin hooks into the_content() to output:
 *   - Job specifications bar (category, type, location)
 *   - Job description (from the editor)
 *   - Application form
 *   - "Back to Listings" link
 *
 * The H1 page title is prepended to the_content() by a filter in
 * functions.php (search: awsm-job-page-title). That filter only fires
 * on is_singular('awsm_job_openings'), so it's scoped to this template.
 *
 * This template intentionally does NOT include:
 *   - Post date (jobs don't need a publish date shown)
 *   - "Back to News & Updates" breadcrumb (that's for blog posts)
 *   - Previous / Next article navigation (that's for blog posts)
 *
 * WCAG 2.1 AA:
 *   - Single H1 (injected via the_content filter)
 *   - Article landmark
 *   - Content within <main> (opened by header.php, closed by footer.php)
 *
 * @package PLGC
 * @since   1.7.43
 */
get_header();
?>

<article class="plgc-job-single" itemscope itemtype="https://schema.org/JobPosting">

    <div class="plgc-job-single__content">
        <?php
        while ( have_posts() ) :
            the_post();
            the_content();
        endwhile;
        ?>
    </div>

</article><!-- /.plgc-job-single -->

<?php get_footer(); ?>
