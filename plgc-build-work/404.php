<?php
/**
 * 404 Page Template — Page Not Found
 *
 * Branded, WCAG 2.1 AA compliant 404 page with search form
 * and links to popular areas of the site.
 *
 * @package PLGC
 */
get_header();
?>

<div class="plgc-404">

    <div class="plgc-404__header">
        <div class="plgc-404__inner">
            <h1 class="plgc-404__title">Page Not Found</h1>
            <p class="plgc-404__subtitle">
                Sorry, the page you&rsquo;re looking for doesn&rsquo;t exist or may have been moved.
            </p>
        </div>
    </div>

    <div class="plgc-404__inner">

        <div class="plgc-404__search-section">
            <h2 class="plgc-404__heading">Try searching for what you need</h2>
            <form role="search" method="get" class="plgc-404__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <label for="plgc-404-search" class="screen-reader-text">Search Prairie Landing Golf Club</label>
                <div class="plgc-404__form-wrap">
                    <input
                        type="search"
                        id="plgc-404-search"
                        class="plgc-404__input"
                        name="s"
                        placeholder="Search Prairie Landing Golf Club&hellip;"
                        autocomplete="off"
                    >
                    <button type="submit" class="plgc-404__submit" aria-label="Submit search">
                        <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        <div class="plgc-404__links-section">
            <h2 class="plgc-404__heading">Or visit one of these popular pages</h2>
            <ul class="plgc-404__links" role="list">
                <li>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <span class="plgc-404__link-title">Homepage</span>
                        <span class="plgc-404__link-desc">Back to the main page</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>">
                        <span class="plgc-404__link-title">Events Calendar</span>
                        <span class="plgc-404__link-desc">Upcoming events and tournaments</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( home_url( '/facilities-amenities/' ) ); ?>">
                        <span class="plgc-404__link-title">Facilities &amp; Amenities</span>
                        <span class="plgc-404__link-desc">Course, restaurant, and venue info</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">
                        <span class="plgc-404__link-title">Contact Us</span>
                        <span class="plgc-404__link-desc">Get in touch with our team</span>
                    </a>
                </li>
            </ul>
        </div>

    </div>

</div><!-- /.plgc-404 -->

<?php get_footer(); ?>
