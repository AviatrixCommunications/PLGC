<?php
/**
 * Archive Template — News & Updates
 *
 * Displays the blog listing page (Posts page) and taxonomy archives
 * (categories, tags, dates). Branded grid layout with post cards.
 *
 * WCAG 2.1 AA compliant:
 *   - Proper heading hierarchy (H1 → H2 for card titles)
 *   - Descriptive link text (full titles, not "Read More")
 *   - Skip-to-content target already in header
 *   - Logical reading order and keyboard navigation
 *   - Alt text on featured images
 *   - Pagination with aria-labels
 *
 * @package PLGC
 */
get_header();

// Determine the page title based on archive type.
if (is_home()) {
    $page_title    = 'News & Updates';
    $page_subtitle = 'The latest from Prairie Landing Golf Club — events, course updates, dining specials, and more.';
} elseif (is_category()) {
    $page_title    = single_cat_title('', false);
    $page_subtitle = category_description() ?: '';
} elseif (is_tag()) {
    $page_title    = 'Tagged: ' . single_tag_title('', false);
    $page_subtitle = tag_description() ?: '';
} elseif (is_date()) {
    if (is_year()) {
        $page_title = 'Archives: ' . get_the_date('Y');
    } elseif (is_month()) {
        $page_title = 'Archives: ' . get_the_date('F Y');
    } else {
        $page_title = 'Archives: ' . get_the_date('F j, Y');
    }
    $page_subtitle = '';
} elseif (is_author()) {
    $page_title    = 'Articles by ' . get_the_author();
    $page_subtitle = '';
} else {
    $page_title    = 'News & Updates';
    $page_subtitle = '';
}
?>

<article class="plgc-news-archive">

    <!-- Archive Header -->
    <div class="plgc-news-archive__header">
        <div class="plgc-news-archive__inner">
            <h1 class="plgc-news-archive__title"><?php echo esc_html($page_title); ?></h1>
            <?php if ($page_subtitle) : ?>
                <p class="plgc-news-archive__subtitle"><?php echo esc_html($page_subtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="plgc-news-archive__inner">

        <?php if (have_posts()) : ?>

            <!-- Post Grid -->
            <div class="plgc-news-grid" role="feed" aria-label="News articles">

                <?php while (have_posts()) : the_post(); ?>

                    <article class="plgc-news-card" aria-labelledby="post-title-<?php the_ID(); ?>">

                        <?php if (has_post_thumbnail()) : ?>
                            <a href="<?php the_permalink(); ?>" class="plgc-news-card__image-link" tabindex="-1" aria-hidden="true">
                                <div class="plgc-news-card__image">
                                    <?php the_post_thumbnail('medium_large', [
                                        'loading' => 'lazy',
                                        'alt'     => '',
                                        'sizes'   => '(max-width: 767px) 100vw, (max-width: 1024px) 50vw, 540px',
                                    ]); ?>
                                </div>
                            </a>
                        <?php endif; ?>

                        <div class="plgc-news-card__body">
                            <div class="plgc-news-card__meta">
                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>" class="plgc-news-card__date">
                                    <?php echo esc_html(get_the_date('F j, Y')); ?>
                                </time>
                                <?php
                                $categories = get_the_category();
                                if ($categories) :
                                    $cat = $categories[0];
                                ?>
                                    <span class="plgc-news-card__sep" aria-hidden="true">&middot;</span>
                                    <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" class="plgc-news-card__category">
                                        <?php echo esc_html($cat->name); ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <h2 class="plgc-news-card__title" id="post-title-<?php the_ID(); ?>">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h2>

                            <?php if (has_excerpt() || get_the_content()) : ?>
                                <p class="plgc-news-card__excerpt">
                                    <?php
                                    if (has_excerpt()) {
                                        echo esc_html(wp_trim_words(get_the_excerpt(), 25, '&hellip;'));
                                    } else {
                                        echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()), 25, '&hellip;'));
                                    }
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>

                    </article>

                <?php endwhile; ?>

            </div><!-- /.plgc-news-grid -->

            <?php
            // Pagination
            $pagination = paginate_links([
                'prev_text' => '<span aria-hidden="true">&larr;</span> <span>Previous</span>',
                'next_text' => '<span>Next</span> <span aria-hidden="true">&rarr;</span>',
                'type'      => 'array',
            ]);

            if ($pagination) :
            ?>
                <nav class="plgc-news-pagination" aria-label="News articles pagination">
                    <ul>
                        <?php foreach ($pagination as $page_link) : ?>
                            <li><?php echo $page_link; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else : ?>

            <div class="plgc-news-archive__empty">
                <h2>No articles yet</h2>
                <p>Check back soon for news and updates from Prairie Landing Golf Club.</p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="plgc-btn plgc-btn--primary">
                    Back to Home
                </a>
            </div>

        <?php endif; ?>

    </div><!-- /.plgc-news-archive__inner -->

</article><!-- /.plgc-news-archive -->

<?php get_footer(); ?>
