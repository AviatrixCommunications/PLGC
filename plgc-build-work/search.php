<?php
/**
 * Search Results Template
 *
 * Styled to match PLGC brand. Used when visitors click
 * "See all results" from the AJAX search or submit the form.
 *
 * @package PLGC
 */
get_header();

$query_term = get_search_query();
?>

<main id="main-content" class="plgc-search-page" role="main">
    <div class="plgc-search-page__header">
        <div class="plgc-search-page__inner">
            <h1 class="plgc-search-page__title">Search Results</h1>
            <?php if ($query_term) : ?>
                <p class="plgc-search-page__summary">
                    <?php
                    printf(
                        'Showing results for &ldquo;%s&rdquo;',
                        esc_html($query_term)
                    );
                    ?>
                </p>
            <?php endif; ?>

            <form role="search" method="get" class="plgc-search-page__form" action="<?php echo esc_url(home_url('/')); ?>">
                <label for="plgc-search-page-input" class="screen-reader-text">Search for:</label>
                <div class="plgc-search-page__form-wrap">
                    <input
                        type="search"
                        id="plgc-search-page-input"
                        class="plgc-search-page__input"
                        name="s"
                        value="<?php echo esc_attr($query_term); ?>"
                        placeholder="Search&hellip;"
                    >
                    <button type="submit" class="plgc-search-page__submit" aria-label="Search">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="plgc-search-page__inner">
        <?php if (have_posts()) : ?>

            <div class="plgc-search-page__count">
                <?php
                printf(
                    '%s %s found',
                    esc_html($wp_query->found_posts),
                    $wp_query->found_posts === 1 ? 'result' : 'results'
                );
                ?>
            </div>

            <ul class="plgc-search-page__results" role="list">
                <?php while (have_posts()) : the_post(); ?>
                    <?php
                    $post_type     = get_post_type();
                    $post_type_obj = get_post_type_object($post_type);

                    // Friendly type labels
                    $type_labels = [
                        'page'               => 'Page',
                        'post'               => 'News & Updates',
                        'tribe_events'       => 'Event',
                        'awsm_job_openings'  => 'Job Opening',
                        'product'            => 'Product',
                    ];
                    $type_label = isset($type_labels[$post_type])
                        ? $type_labels[$post_type]
                        : ($post_type_obj ? $post_type_obj->labels->singular_name : 'Page');
                    ?>

                    <li class="plgc-search-page__result">
                        <a href="<?php the_permalink(); ?>" class="plgc-search-page__result-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="plgc-search-page__result-thumb">
                                    <?php the_post_thumbnail('medium', ['loading' => 'lazy', 'alt' => '']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="plgc-search-page__result-body">
                                <span class="plgc-search-page__result-type"><?php echo esc_html($type_label); ?></span>
                                <h2 class="plgc-search-page__result-title"><?php the_title(); ?></h2>

                                <?php if (has_excerpt() || get_the_content()) : ?>
                                    <p class="plgc-search-page__result-excerpt">
                                        <?php
                                        if (has_excerpt()) {
                                            echo esc_html(wp_trim_words(get_the_excerpt(), 30));
                                        } else {
                                            echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()), 30));
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>

                <?php endwhile; ?>
            </ul>

            <?php
            // Pagination
            $pagination = paginate_links([
                'prev_text' => '&larr; Previous',
                'next_text' => 'Next &rarr;',
                'type'      => 'array',
            ]);

            if ($pagination) :
            ?>
                <nav class="plgc-search-page__pagination" aria-label="Search results pagination">
                    <ul>
                        <?php foreach ($pagination as $page_link) : ?>
                            <li><?php echo $page_link; // Already escaped by WP ?></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else : ?>
            <div class="plgc-search-page__no-results">
                <h2>No results found</h2>
                <p>
                    We couldn&rsquo;t find anything matching &ldquo;<?php echo esc_html($query_term); ?>&rdquo;.
                    Try a different search term or browse our pages using the navigation above.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
