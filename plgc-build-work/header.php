<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); // fires the skip link + any hooked content ?>

<?php
/**
 * ACF Alert Banner Hook
 *
 * Your ACF alert banner plugin should hook into 'plgc_before_header'
 * to output the dismissible announcement bar above the navigation.
 * This keeps it fully independent from the nav code.
 *
 * Example (in your ACF plugin or functions.php):
 *   add_action('plgc_before_header', 'my_acf_alert_banner');
 */
do_action('plgc_before_header');

// Get CTA button details from the menu (or defaults if not set in menu)
$cta = plgc_get_nav_cta();
?>

<header class="plgc-header" role="banner">
    <div class="plgc-header__inner">

        <!-- Logo -->
        <a href="<?php echo esc_url(home_url('/')); ?>"
           class="plgc-header__logo"
           aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> — Home">
            <?php
            $logo_id = get_theme_mod('custom_logo');
            if ($logo_id) {
                echo wp_get_attachment_image($logo_id, 'full', false, [
                    'class'   => 'plgc-logo-img',
                    'alt'     => esc_attr(get_bloginfo('name')),
                    'sizes'   => '(max-width: 480px) 88px, (max-width: 768px) 100px, 132px',
                    'loading' => 'eager',
                    'decoding' => 'async',
                ]);
            } else {
                echo '<span class="plgc-logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
            }
            ?>
        </a>

        <!-- Primary Navigation -->
        <?php plgc_render_primary_nav(); ?>

        <!-- Header Actions: CTA + Search + Hamburger -->
        <div class="plgc-header__actions">

            <!-- "Book a Tee Time" desktop CTA — pulled from menu or defaults -->
            <a href="<?php echo esc_url($cta['url']); ?>"
               class="plgc-btn plgc-btn--tee-time plgc-btn--desktop-cta"
               <?php if ($cta['target'] === '_blank') echo 'target="_blank" rel="noopener noreferrer"'; ?>>
                <?php echo esc_html($cta['label']); ?>
            </a>

            <!-- Search Toggle -->
            <button class="plgc-header__search-toggle"
                    aria-label="Open search"
                    aria-expanded="false"
                    aria-controls="plgc-search-panel">
                <!-- Magnifier icon (visible by default) -->
                <svg class="plgc-icon--search" aria-hidden="true" focusable="false"
                     width="27" height="27" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"
                     style="pointer-events:none;">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <!-- X icon (visible when search is open) -->
                <svg class="plgc-icon--close" aria-hidden="true" focusable="false"
                     width="27" height="27" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round"
                     style="pointer-events:none; display:none;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <!-- Mobile Hamburger -->
            <button class="plgc-hamburger"
                    aria-label="Open navigation menu"
                    aria-expanded="false"
                    aria-controls="plgc-mobile-nav">
                <span class="plgc-hamburger__bar"></span>
                <span class="plgc-hamburger__bar"></span>
                <span class="plgc-hamburger__bar"></span>
            </button>

        </div><!-- /.plgc-header__actions -->
    </div><!-- /.plgc-header__inner -->

    <!-- Search Panel -->
    <div id="plgc-search-panel"
         class="plgc-search-panel"
         role="search"
         aria-label="<?php esc_attr_e('Site search', 'plgc-child'); ?>"
         hidden>

        <h2 class="plgc-search-panel__heading" id="plgc-search-label">
            <?php esc_html_e('What are you looking for?', 'plgc-child'); ?>
        </h2>

        <div class="plgc-search-panel__form-wrap">

            <form class="plgc-search-form" role="search" method="get"
                  action="<?php echo esc_url(home_url('/')); ?>">

                <label class="plgc-search-form__label screen-reader-text" for="plgc-search-input">
                    <?php esc_html_e('Search Prairie Landing Golf Club', 'plgc-child'); ?>
                </label>

                <div class="plgc-search-form__pill">
                    <input id="plgc-search-input"
                           class="plgc-search-form__input"
                           type="search"
                           name="s"
                           autocomplete="off"
                           placeholder="<?php esc_attr_e('Search Prairie Landing Golf Club...', 'plgc-child'); ?>"
                           aria-label="<?php esc_attr_e('Search Prairie Landing Golf Club', 'plgc-child'); ?>"
                           value="<?php echo get_search_query(); ?>">

                    <button class="plgc-search-form__submit" type="submit"
                            aria-label="<?php esc_attr_e('Submit search', 'plgc-child'); ?>">
                        <svg aria-hidden="true" focusable="false" width="22" height="22"
                             viewBox="0 0 24 24" fill="none" stroke="#ffffff"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                             style="pointer-events:none;">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </button>
                </div>

            </form>

            <!-- Live AJAX results (populated by JS) -->
            <div id="plgc-search-results"
                 class="plgc-search-results"
                 role="region"
                 aria-label="<?php esc_attr_e('Search results', 'plgc-child'); ?>"
                 aria-live="polite"
                 hidden></div>

        </div>

    </div>

</header><!-- /.plgc-header -->

<!-- Mobile Navigation Drawer -->
<div id="plgc-mobile-nav"
     class="plgc-mobile-nav"
     aria-label="Mobile navigation"
     aria-hidden="true"
     inert>

    <!-- Scrollable menu area -->
    <div class="plgc-mobile-nav__inner">

        <button class="plgc-mobile-nav__close" aria-label="Close navigation menu">
            <svg aria-hidden="true" focusable="false" width="24" height="24"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'menu_class'     => 'plgc-mobile-nav__menu',
            'container'      => false,
            'walker'         => new PLGC_Mobile_Walker(),
            'fallback_cb'    => false,
        ]);
        ?>

    </div><!-- /.plgc-mobile-nav__inner -->

    <!-- CTA pinned to bottom of drawer — always visible, never scrolls away -->
    <div class="plgc-mobile-nav__cta">
        <a href="<?php echo esc_url($cta['url']); ?>"
           class="plgc-btn plgc-btn--tee-time"
           <?php if ($cta['target'] === '_blank') echo 'target="_blank" rel="noopener noreferrer"'; ?>>
            <?php echo esc_html($cta['label']); ?>
        </a>
    </div>

</div>
<div class="plgc-mobile-nav__overlay" aria-hidden="true"></div>

<main id="main-content" tabindex="-1">
