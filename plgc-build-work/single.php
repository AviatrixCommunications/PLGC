<?php
/**
 * Single Post Template — News & Updates Article
 *
 * Displays an individual blog post / news article with:
 *   - Featured image hero (if set)
 *   - Article metadata (date, categories)
 *   - The post content (from block editor or classic editor)
 *   - Previous / Next article navigation
 *   - Back-to-archive link
 *
 * WCAG 2.1 AA compliant:
 *   - Proper heading hierarchy (H1 for title, content starts at H2)
 *   - Time element with machine-readable datetime
 *   - Article landmark with proper structure
 *   - Keyboard-navigable post navigation
 *   - Featured image as decorative (empty alt — title provides context)
 *
 * @package PLGC
 */
get_header();
?>

<article class="plgc-news-single" itemscope itemtype="https://schema.org/Article">

    <?php if (has_post_thumbnail()) : ?>
        <!-- Featured Image Hero -->
        <div class="plgc-news-single__hero">
            <?php the_post_thumbnail('large', [
                'class'   => 'plgc-news-single__hero-img',
                'alt'     => '',
                'loading' => 'eager',
                'sizes'   => '100vw',
            ]); ?>
            <div class="plgc-news-single__hero-overlay" aria-hidden="true"></div>
        </div>
    <?php endif; ?>

    <!-- Article Header -->
    <header class="plgc-news-single__header">
        <div class="plgc-news-single__inner">

            <!-- Back link -->
            <nav class="plgc-news-single__breadcrumb" aria-label="Breadcrumb">
                <?php
                $news_page_id  = get_option('page_for_posts');
                $news_page_url = $news_page_id ? get_permalink($news_page_id) : home_url('/');
                ?>
                <a href="<?php echo esc_url($news_page_url); ?>">
                    <span aria-hidden="true">&larr;</span> Back to News & Updates
                </a>
            </nav>

            <!-- Meta -->
            <div class="plgc-news-single__meta">
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>" itemprop="datePublished">
                    <?php echo esc_html(get_the_date('F j, Y')); ?>
                </time>
                <?php
                $categories = get_the_category();
                if ($categories) :
                ?>
                    <span class="plgc-news-single__sep" aria-hidden="true">&middot;</span>
                    <span class="plgc-news-single__categories">
                        <?php
                        $cat_links = [];
                        foreach ($categories as $cat) {
                            $cat_links[] = sprintf(
                                '<a href="%s">%s</a>',
                                esc_url(get_category_link($cat->term_id)),
                                esc_html($cat->name)
                            );
                        }
                        echo implode(', ', $cat_links);
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="plgc-news-single__title" itemprop="headline">
                <?php the_title(); ?>
            </h1>

        </div>
    </header>

    <!-- Article Content -->
    <div class="plgc-news-single__content" itemprop="articleBody">
        <div class="plgc-news-single__inner plgc-news-single__inner--narrow">
            <?php
            while (have_posts()) :
                the_post();
                the_content();
            endwhile;
            ?>
        </div>
    </div>

    <!-- Tags -->
    <?php
    $tags = get_the_tags();
    if ($tags) :
    ?>
        <footer class="plgc-news-single__tags">
            <div class="plgc-news-single__inner plgc-news-single__inner--narrow">
                <p class="plgc-news-single__tag-label">
                    <strong>Tags:</strong>
                    <?php
                    $tag_links = [];
                    foreach ($tags as $tag) {
                        $tag_links[] = sprintf(
                            '<a href="%s">%s</a>',
                            esc_url(get_tag_link($tag->term_id)),
                            esc_html($tag->name)
                        );
                    }
                    echo implode(', ', $tag_links);
                    ?>
                </p>
            </div>
        </footer>
    <?php endif; ?>

</article><!-- /.plgc-news-single -->

<!-- Post Navigation -->
<?php
$prev_post = get_previous_post();
$next_post = get_next_post();

if ($prev_post || $next_post) :
?>
    <nav class="plgc-news-nav" aria-label="More articles">
        <div class="plgc-news-nav__inner">
            <?php if ($prev_post) : ?>
                <a href="<?php echo esc_url(get_permalink($prev_post)); ?>" class="plgc-news-nav__link plgc-news-nav__link--prev">
                    <span class="plgc-news-nav__direction"><span aria-hidden="true">&larr;</span> Previous Article</span>
                    <span class="plgc-news-nav__post-title"><?php echo esc_html(get_the_title($prev_post)); ?></span>
                </a>
            <?php endif; ?>

            <?php if ($next_post) : ?>
                <a href="<?php echo esc_url(get_permalink($next_post)); ?>" class="plgc-news-nav__link plgc-news-nav__link--next">
                    <span class="plgc-news-nav__direction">Next Article <span aria-hidden="true">&rarr;</span></span>
                    <span class="plgc-news-nav__post-title"><?php echo esc_html(get_the_title($next_post)); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>

<?php get_footer(); ?>
