<?php
/**
 * Prairie Landing Golf Club — Restaurant Menu CPT
 *
 * Registers the plgc_menu_item CPT, menu_section taxonomy, ACF fields,
 * [plgc_menu] shortcode, admin columns, CSV importer/exporter, and
 * Schema.org Restaurant/Menu JSON-LD.
 *
 * WCAG 2.1 AA compliant output with semantic HTML, proper heading
 * hierarchy, ARIA labels, and screen-reader-friendly price formatting.
 *
 * @package PLGC
 * @since   1.7.26
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. CUSTOM POST TYPE
   ============================================================ */

add_action( 'init', 'plgc_register_menu_cpt' );

function plgc_register_menu_cpt() {
    $labels = [
        'name'                  => 'Menu Items',
        'singular_name'         => 'Menu Item',
        'add_new'               => 'Add Menu Item',
        'add_new_item'          => 'Add New Menu Item',
        'edit_item'             => 'Edit Menu Item',
        'new_item'              => 'New Menu Item',
        'view_item'             => 'View Menu Item',
        'search_items'          => 'Search Menu Items',
        'not_found'             => 'No menu items found.',
        'not_found_in_trash'    => 'No menu items found in Trash.',
        'all_items'             => 'All Menu Items',
        'menu_name'             => 'Restaurant Menu',
    ];

    register_post_type( 'plgc_menu_item', [
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'menu_position'       => 26,
        'menu_icon'           => 'dashicons-food',
        'supports'            => [ 'title' ],
        'capability_type'     => 'post',
        'has_archive'         => false,
        'rewrite'             => false,
        'exclude_from_search' => true,
    ] );
}

/* ============================================================
   2. TAXONOMY: MENU SECTION
   ============================================================ */

add_action( 'init', 'plgc_register_menu_section_taxonomy' );

function plgc_register_menu_section_taxonomy() {
    $labels = [
        'name'              => 'Menu Sections',
        'singular_name'     => 'Menu Section',
        'search_items'      => 'Search Sections',
        'all_items'         => 'All Sections',
        'edit_item'         => 'Edit Section',
        'update_item'       => 'Update Section',
        'add_new_item'      => 'Add New Section',
        'new_item_name'     => 'New Section Name',
        'menu_name'         => 'Menu Sections',
    ];

    register_taxonomy( 'menu_section', 'plgc_menu_item', [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => false,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => false,
    ] );
}

/* ============================================================
   3. ACF FIELD REGISTRATION
   ============================================================ */

add_action( 'acf/init', 'plgc_register_menu_acf_fields' );

function plgc_register_menu_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    // ── Menu Item Fields ─────────────────────────────────────
    acf_add_local_field_group( [
        'key'      => 'group_plgc_menu_item',
        'title'    => 'Menu Item Details',
        'location' => [ [ [
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'plgc_menu_item',
        ] ] ],
        'position'      => 'normal',
        'style'         => 'default',
        'label_placement' => 'top',
        'fields'   => [

            // Price
            [
                'key'           => 'field_menu_item_price',
                'label'         => 'Price',
                'name'          => 'menu_item_price',
                'type'          => 'number',
                'instructions'  => 'Base price in dollars (e.g. 16). Leave blank for items with only tiered pricing (like soup cup/bowl).',
                'prepend'       => '$',
                'min'           => 0,
                'step'          => 0.01,
                'wrapper'       => [ 'width' => '33' ],
            ],

            // Price qualifier
            [
                'key'           => 'field_menu_item_price_qualifier',
                'label'         => 'Price Qualifier',
                'name'          => 'menu_item_price_qualifier',
                'type'          => 'text',
                'instructions'  => 'Optional text shown with price (e.g. "starting at", "per person").',
                'placeholder'   => '',
                'wrapper'       => [ 'width' => '33' ],
            ],

            // Display order
            [
                'key'           => 'field_menu_item_order',
                'label'         => 'Display Order',
                'name'          => 'menu_item_order',
                'type'          => 'number',
                'instructions'  => 'Order within its section (lower = first).',
                'default_value' => 10,
                'min'           => 0,
                'step'          => 1,
                'wrapper'       => [ 'width' => '34' ],
            ],

            // Description
            [
                'key'           => 'field_menu_item_description',
                'label'         => 'Description',
                'name'          => 'menu_item_description',
                'type'          => 'textarea',
                'instructions'  => 'Describe the dish. Plain text only — no HTML.',
                'rows'          => 3,
                'new_lines'     => '',
            ],

            // Price modifiers (repeater)
            [
                'key'           => 'field_menu_item_modifiers',
                'label'         => 'Price Modifiers',
                'name'          => 'menu_item_modifiers',
                'type'          => 'repeater',
                'instructions'  => 'Add-ons (e.g. "Sub Steak +$3") or price tiers (e.g. "Cup $4", "Bowl $5").',
                'layout'        => 'table',
                'button_label'  => 'Add Modifier',
                'sub_fields'    => [
                    [
                        'key'   => 'field_modifier_label',
                        'label' => 'Label',
                        'name'  => 'modifier_label',
                        'type'  => 'text',
                        'wrapper' => [ 'width' => '40' ],
                    ],
                    [
                        'key'     => 'field_modifier_price',
                        'label'   => 'Price',
                        'name'    => 'modifier_price',
                        'type'    => 'number',
                        'prepend' => '$',
                        'min'     => 0,
                        'step'    => 0.01,
                        'wrapper' => [ 'width' => '30' ],
                    ],
                    [
                        'key'           => 'field_modifier_price_type',
                        'label'         => 'Type',
                        'name'          => 'modifier_price_type',
                        'type'          => 'select',
                        'choices'       => [
                            'add' => '+ Add-on',
                            'set' => '= Set price',
                        ],
                        'default_value' => 'add',
                        'wrapper'       => [ 'width' => '30' ],
                    ],
                ],
            ],

            // Dietary flags
            [
                'key'           => 'field_menu_item_dietary',
                'label'         => 'Dietary Information',
                'name'          => 'menu_item_dietary',
                'type'          => 'checkbox',
                'instructions'  => 'Select all that apply.',
                'choices'       => [
                    'vegetarian'  => 'Vegetarian',
                    'vegan'       => 'Vegan',
                    'gluten-free' => 'Gluten-Free',
                    'dairy-free'  => 'Dairy-Free',
                    'nuts'        => 'Contains Nuts',
                    'spicy'       => 'Spicy',
                ],
                'layout'        => 'horizontal',
                'return_format' => 'value',
            ],
        ],
    ] );

    // ── Menu Section (Taxonomy) Fields ───────────────────────
    acf_add_local_field_group( [
        'key'      => 'group_plgc_menu_section',
        'title'    => 'Section Details',
        'location' => [ [ [
            'param'    => 'taxonomy',
            'operator' => '==',
            'value'    => 'menu_section',
        ] ] ],
        'fields'   => [
            [
                'key'           => 'field_section_note',
                'label'         => 'Section Note',
                'name'          => 'section_note',
                'type'          => 'textarea',
                'instructions'  => 'Shown below the section heading (e.g. dressing options, side choices).',
                'rows'          => 2,
                'new_lines'     => '',
            ],
            [
                'key'           => 'field_section_subtitle',
                'label'         => 'Subtitle',
                'name'          => 'section_subtitle',
                'type'          => 'text',
                'instructions'  => 'Optional subtitle (e.g. "14-inch thin crust").',
            ],
            [
                'key'           => 'field_section_order',
                'label'         => 'Display Order',
                'name'          => 'section_order',
                'type'          => 'number',
                'instructions'  => 'Order on the menu page (lower = first).',
                'default_value' => 10,
                'min'           => 0,
                'step'          => 1,
            ],
        ],
    ] );

    // ── Menu Display Settings (PL Settings options page) ─────
    acf_add_local_field_group( [
        'key'      => 'group_plgc_menu_display',
        'title'    => 'Menu Display Settings',
        'location' => [ [ [
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'plgc-settings',
        ] ] ],
        'fields'   => [
            [
                'key'           => 'field_plgc_menu_hide_prices',
                'label'         => 'Hide Menu Prices',
                'name'          => 'plgc_menu_hide_prices',
                'type'          => 'true_false',
                'instructions'  => 'When enabled, all prices are hidden from the public menu display. The "Prices include tax" note is also suppressed. Price data is retained — toggle off to restore.',
                'default_value' => 0,
                'ui'            => 1,
                'ui_on_text'    => 'Hidden',
                'ui_off_text'   => 'Showing',
            ],
        ],
        'menu_order' => 50,
    ] );
}

// Flush menu transient cache when PL Settings (ACF options page) is saved
// so the pricing toggle takes effect immediately.
add_action( 'acf/save_post', function ( $post_id ) {
    if ( $post_id !== 'options' ) {
        return;
    }
    plgc_menu_flush_cache();
}, 20 );

/* ============================================================
   4. ADMIN COLUMNS
   ============================================================ */

add_filter( 'manage_plgc_menu_item_posts_columns', 'plgc_menu_admin_columns' );

function plgc_menu_admin_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['menu_price']   = 'Price';
            $new['menu_dietary'] = 'Dietary';
            $new['menu_order']   = 'Order';
        }
    }
    return $new;
}

add_action( 'manage_plgc_menu_item_posts_custom_column', 'plgc_menu_admin_column_content', 10, 2 );

function plgc_menu_admin_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'menu_price':
            $price = get_field( 'menu_item_price', $post_id );
            if ( $price !== '' && $price !== null && $price !== false ) {
                echo '$' . esc_html( plgc_format_price( $price ) );
            }
            // Also show set-price modifiers
            $mods = get_field( 'menu_item_modifiers', $post_id );
            if ( $mods ) {
                $tiers = [];
                foreach ( $mods as $m ) {
                    if ( ! empty( $m['modifier_price_type'] ) && $m['modifier_price_type'] === 'set' ) {
                        $tiers[] = esc_html( $m['modifier_label'] ) . ' $' . esc_html( plgc_format_price( $m['modifier_price'] ) );
                    }
                }
                if ( $tiers ) {
                    echo ( $price ? '<br>' : '' ) . '<small>' . implode( ' | ', $tiers ) . '</small>';
                }
            }
            break;

        case 'menu_dietary':
            $dietary = get_field( 'menu_item_dietary', $post_id );
            if ( $dietary && is_array( $dietary ) ) {
                $labels = plgc_dietary_labels();
                $badges = [];
                foreach ( $dietary as $flag ) {
                    $badges[] = '<span class="plgc-admin-badge">' . esc_html( $labels[ $flag ] ?? $flag ) . '</span>';
                }
                echo implode( ' ', $badges );
            } else {
                echo '—';
            }
            break;

        case 'menu_order':
            $order = get_field( 'menu_item_order', $post_id );
            echo esc_html( $order !== '' && $order !== null ? $order : '—' );
            break;
    }
}

// Sortable columns
add_filter( 'manage_edit-plgc_menu_item_sortable_columns', function ( $columns ) {
    $columns['menu_price'] = 'menu_price';
    $columns['menu_order'] = 'menu_order';
    return $columns;
} );

add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'plgc_menu_item' ) {
        return;
    }
    $orderby = $query->get( 'orderby' );
    if ( $orderby === 'menu_price' ) {
        $query->set( 'meta_key', 'menu_item_price' );
        $query->set( 'orderby', 'meta_value_num' );
    } elseif ( $orderby === 'menu_order' ) {
        $query->set( 'meta_key', 'menu_item_order' );
        $query->set( 'orderby', 'meta_value_num' );
    }
} );

/* ============================================================
   5. QUICK EDIT — PRICE FIELD
   ============================================================ */

add_action( 'quick_edit_custom_box', function ( $column, $post_type ) {
    if ( $post_type !== 'plgc_menu_item' || $column !== 'menu_price' ) {
        return;
    }
    wp_nonce_field( 'plgc_menu_quick_edit', 'plgc_menu_qe_nonce' );
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label>
                <span class="title">Price</span>
                <span class="input-text-wrap">
                    <input type="number" name="menu_item_price" step="0.01" min="0" style="width:6em">
                </span>
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 2 );

add_action( 'save_post_plgc_menu_item', function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['plgc_menu_qe_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['plgc_menu_qe_nonce'], 'plgc_menu_quick_edit' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['menu_item_price'] ) ) {
        $price = $_POST['menu_item_price'];
        update_field( 'menu_item_price', $price !== '' ? floatval( $price ) : '', $post_id );
    }
} );

// Admin JS for Quick Edit
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'edit.php' ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'plgc_menu_item' ) return;

    wp_enqueue_script(
        'plgc-menu-admin',
        PLGC_URI . '/assets/js/menu-admin.js',
        [ 'jquery', 'inline-edit-post' ],
        PLGC_VERSION,
        true
    );
} );

// Admin badge styles
add_action( 'admin_head', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'plgc_menu_item' ) return;
    ?>
    <style>
        .plgc-admin-badge {
            display: inline-block;
            padding: 2px 6px;
            margin: 1px;
            font-size: 11px;
            line-height: 1.4;
            border-radius: 3px;
            background: #E5F0D0;
            color: #567915;
        }
    </style>
    <?php
} );

/* ============================================================
   6. HELPERS
   ============================================================ */

/**
 * Format a price number: $16 for whole, $16.50 for fractional.
 */
function plgc_format_price( $price ) {
    $price = floatval( $price );
    return ( $price == intval( $price ) ) ? number_format( $price, 0 ) : number_format( $price, 2 );
}

/**
 * Dietary flag labels.
 */
function plgc_dietary_labels() {
    return [
        'vegetarian'  => 'Vegetarian',
        'vegan'       => 'Vegan',
        'gluten-free' => 'Gluten-Free',
        'dairy-free'  => 'Dairy-Free',
        'nuts'        => 'Contains Nuts',
        'spicy'       => 'Spicy',
    ];
}

/**
 * Dietary flag abbreviations for compact frontend display.
 */
function plgc_dietary_abbr() {
    return [
        'vegetarian'  => [ 'abbr' => 'V',  'label' => 'Vegetarian' ],
        'vegan'       => [ 'abbr' => 'VG', 'label' => 'Vegan' ],
        'gluten-free' => [ 'abbr' => 'GF', 'label' => 'Gluten-Free' ],
        'dairy-free'  => [ 'abbr' => 'DF', 'label' => 'Dairy-Free' ],
        'nuts'        => [ 'abbr' => 'N',  'label' => 'Contains Nuts' ],
        'spicy'       => [ 'abbr' => '🌶', 'label' => 'Spicy' ],
    ];
}

/**
 * Build an aria-label for a price value.
 */
function plgc_price_aria( $price ) {
    $price  = floatval( $price );
    $whole  = intval( $price );
    $cents  = round( ( $price - $whole ) * 100 );
    $label  = $whole . ' dollar' . ( $whole !== 1 ? 's' : '' );
    if ( $cents > 0 ) {
        $label .= ' and ' . $cents . ' cent' . ( $cents !== 1 ? 's' : '' );
    }
    return $label;
}

/* ============================================================
   7. SHORTCODE: [plgc_menu]
   ============================================================ */

add_shortcode( 'plgc_menu', 'plgc_menu_shortcode' );

/**
 * Render the restaurant menu.
 *
 * Attributes:
 *   title       — Menu heading text. Default: "McChesney's Pub & Grill"
 *   note        — Note below title. Default: "Prices include tax"
 *   disclaimer  — Legal disclaimer at bottom. Default: food safety notice.
 *   columns     — "2" (default) or "1"
 *   sections    — Comma-separated section slugs to display (empty = all)
 */
function plgc_menu_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'title'      => "McChesney's Pub &amp; Grill",
        'note'       => 'Prices include tax.',
        'disclaimer' => 'Consuming raw or undercooked meats, poultry, seafood, shellfish, or eggs may increase your risk of foodborne illness, especially if you have certain medical conditions.',
        'columns'    => '2',
        'sections'   => '',
    ], $atts, 'plgc_menu' );

    // Check global pricing toggle (PL Settings → Menu Display Settings)
    $hide_prices = (bool) get_field( 'plgc_menu_hide_prices', 'option' );

    // Check transient cache — include pricing toggle state in key
    $cache_key = 'plgc_menu_' . md5( wp_json_encode( $atts ) . ( $hide_prices ? '_np' : '' ) );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        // Store data for Schema.org output
        plgc_menu_set_schema_data( $cached['schema'] ?? [] );
        // Enqueue CSS
        wp_enqueue_style( 'plgc-menu' );
        return $cached['html'];
    }

    // Query sections
    $tax_args = [
        'taxonomy'   => 'menu_section',
        'hide_empty' => true,
        'meta_key'   => 'section_order',
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
    ];
    if ( ! empty( $atts['sections'] ) ) {
        $tax_args['slug'] = array_map( 'trim', explode( ',', $atts['sections'] ) );
    }
    $sections = get_terms( $tax_args );

    if ( is_wp_error( $sections ) || empty( $sections ) ) {
        return '<p class="plgc-menu__empty">The menu is currently being updated. Please check back soon.</p>';
    }

    // Build schema data for JSON-LD
    $schema_sections = [];

    // Start output
    $col_class = $atts['columns'] === '1' ? 'plgc-menu--single-col' : '';
    $no_price_class = $hide_prices ? ' plgc-menu--no-prices' : '';
    $html  = '<div class="plgc-menu ' . $col_class . $no_price_class . '">';

    // Header
    if ( ! empty( $atts['title'] ) ) {
        $html .= '<div class="plgc-menu__header">';
        $html .= '<p class="plgc-menu__title">' . wp_kses_post( $atts['title'] ) . '</p>';
        if ( ! empty( $atts['note'] ) && ! $hide_prices ) {
            $html .= '<p class="plgc-menu__note">' . esc_html( $atts['note'] ) . '</p>';
        }
        $html .= '</div>';
    }

    // Grid
    $html .= '<div class="plgc-menu__grid">';

    foreach ( $sections as $section ) {
        $section_id   = 'menu-section-' . $section->slug;
        $term_ref     = 'menu_section_' . $section->term_id;
        $section_note = get_field( 'section_note', $term_ref );
        $section_sub  = get_field( 'section_subtitle', $term_ref );

        // Query items in this section
        $items = get_posts( [
            'post_type'      => 'plgc_menu_item',
            'posts_per_page' => -1,
            'tax_query'      => [ [
                'taxonomy' => 'menu_section',
                'field'    => 'term_id',
                'terms'    => $section->term_id,
            ] ],
            'meta_key'       => 'menu_item_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );

        if ( empty( $items ) ) continue;

        $schema_items = [];

        $html .= '<section class="plgc-menu-section" aria-labelledby="' . esc_attr( $section_id ) . '">';
        $html .= '<h2 id="' . esc_attr( $section_id ) . '" class="plgc-menu-section__heading">' . esc_html( $section->name ) . '</h2>';

        if ( $section_sub ) {
            $html .= '<p class="plgc-menu-section__subtitle">' . esc_html( $section_sub ) . '</p>';
        }
        if ( $section_note ) {
            $html .= '<p class="plgc-menu-section__note">' . esc_html( $section_note ) . '</p>';
        }

        $html .= '<div class="plgc-menu-section__items">';

        foreach ( $items as $item ) {
            $price      = get_field( 'menu_item_price', $item->ID );
            $qualifier  = get_field( 'menu_item_price_qualifier', $item->ID );
            $desc       = get_field( 'menu_item_description', $item->ID );
            $modifiers  = get_field( 'menu_item_modifiers', $item->ID );
            $dietary    = get_field( 'menu_item_dietary', $item->ID );
            $item_name  = get_the_title( $item->ID );

            $html .= '<article class="plgc-menu-item">';
            $html .= '<div class="plgc-menu-item__header">';
            $html .= '<h3 class="plgc-menu-item__name">' . esc_html( $item_name ) . '</h3>';

            // Price display (hidden when "Hide Menu Prices" toggle is on)
            $has_base_price = ( $price !== '' && $price !== null && $price !== false );
            $set_mods       = [];
            $add_mods       = [];

            if ( $modifiers && is_array( $modifiers ) ) {
                foreach ( $modifiers as $m ) {
                    if ( ( $m['modifier_price_type'] ?? 'add' ) === 'set' ) {
                        $set_mods[] = $m;
                    } else {
                        $add_mods[] = $m;
                    }
                }
            }

            if ( ! $hide_prices ) {
                $html .= '<span class="plgc-menu-item__dots" aria-hidden="true"></span>';

                if ( $has_base_price ) {
                    // Standard price
                    $formatted = '$' . plgc_format_price( $price );
                    $aria      = plgc_price_aria( $price );
                    if ( $qualifier ) {
                        $html .= '<span class="plgc-menu-item__price" aria-label="' . esc_attr( $qualifier ) . ', ' . esc_attr( $aria ) . '">';
                        $html .= '<span class="plgc-menu-item__qualifier">' . esc_html( $qualifier ) . '</span> ';
                        $html .= esc_html( $formatted );
                    } else {
                        $html .= '<span class="plgc-menu-item__price" aria-label="' . esc_attr( $aria ) . '">';
                        $html .= esc_html( $formatted );
                    }
                    $html .= '</span>';
                } elseif ( ! empty( $set_mods ) ) {
                    // Tiered pricing (e.g. Cup $4 | Bowl $5)
                    $tier_parts = [];
                    $aria_parts = [];
                    foreach ( $set_mods as $m ) {
                        $tier_parts[] = '<span class="plgc-menu-item__price-tier">'
                                      . esc_html( $m['modifier_label'] ) . ' $'
                                      . esc_html( plgc_format_price( $m['modifier_price'] ) )
                                      . '</span>';
                        $aria_parts[] = $m['modifier_label'] . ' ' . plgc_price_aria( $m['modifier_price'] );
                    }
                    $html .= '<span class="plgc-menu-item__prices" aria-label="' . esc_attr( implode( ', ', $aria_parts ) ) . '">';
                    $html .= implode( '<span class="plgc-menu-item__price-sep" aria-hidden="true"> | </span>', $tier_parts );
                    $html .= '</span>';
                }
            } // end if ! $hide_prices

            $html .= '</div>'; // end header

            // Description
            if ( $desc ) {
                $html .= '<p class="plgc-menu-item__description">' . esc_html( $desc ) . '</p>';
            }

            // Add-on modifiers (also hidden when prices are hidden — they contain prices)
            if ( ! $hide_prices && ! empty( $add_mods ) ) {
                $html .= '<ul class="plgc-menu-item__modifiers" aria-label="Add-ons for ' . esc_attr( $item_name ) . '">';
                foreach ( $add_mods as $m ) {
                    $mod_price = '+$' . plgc_format_price( $m['modifier_price'] );
                    $html .= '<li>' . esc_html( $m['modifier_label'] ) . ' <span aria-label="add ' . esc_attr( plgc_price_aria( $m['modifier_price'] ) ) . '">' . esc_html( $mod_price ) . '</span></li>';
                }
                $html .= '</ul>';
            }

            // Dietary flags
            if ( $dietary && is_array( $dietary ) ) {
                $abbrs = plgc_dietary_abbr();
                $html .= '<ul class="plgc-menu-item__dietary" aria-label="Dietary information">';
                foreach ( $dietary as $flag ) {
                    $info = $abbrs[ $flag ] ?? null;
                    if ( ! $info ) continue;
                    $html .= '<li><abbr title="' . esc_attr( $info['label'] ) . '">' . esc_html( $info['abbr'] ) . '</abbr></li>';
                }
                $html .= '</ul>';
            }

            $html .= '</article>'; // end menu-item

            // Schema data
            $schema_item = [
                '@type'       => 'MenuItem',
                'name'        => $item_name,
            ];
            if ( $desc ) {
                $schema_item['description'] = $desc;
            }
            if ( $has_base_price ) {
                $schema_item['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => number_format( floatval( $price ), 2, '.', '' ),
                    'priceCurrency' => 'USD',
                ];
            } elseif ( ! empty( $set_mods ) ) {
                $offers = [];
                foreach ( $set_mods as $m ) {
                    $offers[] = [
                        '@type'         => 'Offer',
                        'name'          => $m['modifier_label'],
                        'price'         => number_format( floatval( $m['modifier_price'] ), 2, '.', '' ),
                        'priceCurrency' => 'USD',
                    ];
                }
                $schema_item['offers'] = $offers;
            }
            if ( $dietary && is_array( $dietary ) ) {
                $diet_labels = plgc_dietary_labels();
                $restrictions = [];
                foreach ( $dietary as $flag ) {
                    $restrictions[] = $diet_labels[ $flag ] ?? $flag;
                }
                $schema_item['suitableForDiet'] = $restrictions;
            }
            $schema_items[] = $schema_item;
        }

        $html .= '</div>'; // end section items
        $html .= '</section>';

        $schema_sections[] = [
            '@type'       => 'MenuSection',
            'name'        => $section->name,
            'hasMenuItem' => $schema_items,
        ];
    }

    $html .= '</div>'; // end grid

    // Disclaimer
    if ( ! empty( $atts['disclaimer'] ) ) {
        $html .= '<footer class="plgc-menu__footer">';
        $html .= '<p class="plgc-menu__disclaimer">' . esc_html( $atts['disclaimer'] ) . '</p>';
        $html .= '</footer>';
    }

    $html .= '</div>'; // end plgc-menu

    // Cache the result
    $schema_data = [
        'sections' => $schema_sections,
        'title'    => wp_strip_all_tags( $atts['title'] ),
    ];
    set_transient( $cache_key, [
        'html'   => $html,
        'schema' => $schema_data,
    ], 0 ); // no expiry — invalidated on save

    plgc_menu_set_schema_data( $schema_data );

    // Enqueue CSS
    wp_enqueue_style( 'plgc-menu' );

    return $html;
}

/* ============================================================
   8. SCHEMA.ORG JSON-LD
   ============================================================ */

/**
 * Store/retrieve schema data for the current page.
 */
function plgc_menu_set_schema_data( $data = null ) {
    static $stored = null;
    if ( $data !== null ) {
        $stored = $data;
    }
    return $stored;
}

add_action( 'wp_footer', function () {
    $data = plgc_menu_set_schema_data();
    if ( ! $data || empty( $data['sections'] ) ) return;

    $address = function_exists( 'plgc_option' )
        ? plgc_option( 'plgc_address', '2325 Longest Drive, West Chicago, IL 60185' )
        : '2325 Longest Drive, West Chicago, IL 60185';

    $phone = function_exists( 'plgc_option' )
        ? plgc_option( 'plgc_pub_phone', '(630) 208-7633' )
        : '(630) 208-7633';

    $schema = [
        '@context'  => 'https://schema.org',
        '@type'     => 'Restaurant',
        'name'      => $data['title'] ?: "McChesney's Pub & Grill",
        'telephone' => $phone,
        'address'   => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => '2325 Longest Drive',
            'addressLocality' => 'West Chicago',
            'addressRegion'   => 'IL',
            'postalCode'      => '60185',
            'addressCountry'  => 'US',
        ],
        'hasMenu' => [
            '@type'          => 'Menu',
            'name'           => ( $data['title'] ?: "McChesney's Pub & Grill" ) . ' Menu',
            'hasMenuSection' => $data['sections'],
        ],
    ];

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}, 99 );

/* ============================================================
   9. TRANSIENT CACHE INVALIDATION
   ============================================================ */

/**
 * Flush all plgc_menu_ transients when menu data changes.
 */
function plgc_menu_flush_cache() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_plgc_menu_%' OR option_name LIKE '_transient_timeout_plgc_menu_%'"
    );
}

add_action( 'save_post_plgc_menu_item',     'plgc_menu_flush_cache' );
add_action( 'delete_post',                   function ( $post_id ) {
    if ( get_post_type( $post_id ) === 'plgc_menu_item' ) {
        plgc_menu_flush_cache();
    }
} );
add_action( 'edited_menu_section',  'plgc_menu_flush_cache' );
add_action( 'created_menu_section', 'plgc_menu_flush_cache' );
add_action( 'deleted_menu_section', 'plgc_menu_flush_cache' );

/* ============================================================
   10. CSS REGISTRATION
   ============================================================ */

add_action( 'wp_enqueue_scripts', function () {
    wp_register_style(
        'plgc-menu',
        PLGC_URI . '/assets/css/menu.css',
        [ 'plgc-theme' ],
        PLGC_VERSION
    );
} );

/* ============================================================
   11. CSV IMPORTER / EXPORTER — ADMIN PAGE
   ============================================================ */

add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=plgc_menu_item',
        'Import / Export Menu',
        'Import / Export',
        'edit_others_posts',
        'plgc-menu-csv',
        'plgc_menu_csv_page'
    );
} );

/**
 * Intercept CSV export requests on admin_init — BEFORE any HTML output.
 *
 * WordPress renders the full admin shell (head, admin bar, sidebar) before
 * firing the page callback. If we send Content-Disposition headers inside
 * the callback, the browser has already received HTML and the download
 * fails. Hooking admin_init runs before any output starts.
 */
add_action( 'admin_init', function () {
    if (
        ! isset( $_GET['plgc_export_menu'] ) ||
        ! isset( $_GET['page'] ) ||
        $_GET['page'] !== 'plgc-menu-csv'
    ) {
        return;
    }

    // Verify nonce
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'plgc_export_menu' ) ) {
        wp_die( 'Invalid or expired security token. Please go back and try again.', 'Export Error', [ 'back_link' => true ] );
    }

    plgc_menu_csv_export();
    // plgc_menu_csv_export() calls exit — execution stops here.
} );

/**
 * Render the CSV Import/Export admin page.
 */
function plgc_menu_csv_page() {
    // NOTE: Export is handled by the admin_init hook above — it must run
    // before WordPress outputs any HTML, so the browser receives proper
    // Content-Disposition headers for the file download.

    // Handle import
    $import_result = null;
    if ( isset( $_POST['plgc_import_menu'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'plgc_import_menu' ) ) {
        $import_result = plgc_menu_csv_import(
            $_FILES['csv_file'] ?? null,
            ! empty( $_POST['dry_run'] ),
            ! empty( $_POST['delete_unlisted'] )
        );
    }

    $export_url = wp_nonce_url(
        admin_url( 'edit.php?post_type=plgc_menu_item&page=plgc-menu-csv&plgc_export_menu=1' ),
        'plgc_export_menu'
    );
    ?>
    <div class="wrap">
        <h1>Restaurant Menu — Import / Export</h1>

        <?php if ( $import_result ) : ?>
            <div class="notice notice-<?php echo $import_result['success'] ? 'success' : 'error'; ?> is-dismissible">
                <p><strong><?php echo esc_html( $import_result['message'] ); ?></strong></p>
                <?php if ( ! empty( $import_result['details'] ) ) : ?>
                    <ul style="list-style:disc;margin-left:1.5em">
                        <?php foreach ( $import_result['details'] as $detail ) : ?>
                            <li><?php echo esc_html( $detail ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:1.5rem">

            <!-- IMPORT -->
            <div class="card" style="max-width:none;padding:1.5rem">
                <h2 style="margin-top:0">Import Menu from CSV</h2>
                <p>Upload a CSV file to create or update menu items. Existing items are matched by name within their section.</p>

                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'plgc_import_menu' ); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="csv_file">CSV File</label></th>
                            <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                        </tr>
                        <tr>
                            <th>Options</th>
                            <td>
                                <label style="display:block;margin-bottom:0.5em">
                                    <input type="checkbox" name="dry_run" value="1" checked>
                                    <strong>Preview only</strong> — show what would change without saving
                                </label>
                                <label style="display:block">
                                    <input type="checkbox" name="delete_unlisted" value="1">
                                    Trash items not in the CSV <em>(recoverable from Trash)</em>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="submit" name="plgc_import_menu" class="button button-primary">Upload &amp; Process</button>
                    </p>
                </form>

                <hr>
                <h3>CSV Format Guide</h3>
                <p>Columns (order matters):</p>
                <code style="display:block;background:#f6f6f6;padding:0.75rem;margin:0.5rem 0;font-size:12px;overflow-x:auto;white-space:nowrap">
                    section, section_note, section_subtitle, section_order, name, price, price_qualifier, description, modifiers, dietary, item_order
                </code>
                <p><strong>Modifiers format:</strong> Semicolon-separated. Use <code>+</code> for add-ons, <code>=</code> for set prices.</p>
                <ul style="list-style:disc;margin-left:1.5em">
                    <li><code>Sub Steak +3</code> — add-on modifier</li>
                    <li><code>Cup =4;Bowl =5</code> — tiered pricing</li>
                </ul>
                <p><strong>Dietary flags:</strong> Comma-separated values: <code>vegetarian, vegan, gluten-free, dairy-free, nuts, spicy</code></p>
                <p><strong>Section metadata</strong> (note, subtitle, order) is read from the first row of each section. Subsequent rows can leave those columns blank.</p>
            </div>

            <!-- EXPORT -->
            <div class="card" style="max-width:none;padding:1.5rem">
                <h2 style="margin-top:0">Export Current Menu</h2>
                <p>Download the current menu as a CSV file. Use this as a template for updates — edit in Excel or Google Sheets, then re-import.</p>
                <p>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">Download Menu CSV</a>
                </p>

                <hr>
                <h3>Workflow</h3>
                <ol style="margin-left:1.5em">
                    <li>Click "Download Menu CSV" to get the current menu</li>
                    <li>Open in Excel or Google Sheets</li>
                    <li>Make your changes (update prices, add items, remove rows)</li>
                    <li>Save as CSV (UTF-8)</li>
                    <li>Import with "Preview only" checked first</li>
                    <li>Review the preview, then uncheck and import for real</li>
                </ol>
            </div>

        </div>
    </div>
    <?php
}

/* ============================================================
   12. CSV EXPORT
   ============================================================ */

function plgc_menu_csv_export() {
    if ( ! current_user_can( 'edit_others_posts' ) ) {
        wp_die( 'Unauthorized.' );
    }

    $sections = get_terms( [
        'taxonomy'   => 'menu_section',
        'hide_empty' => false,
        'meta_key'   => 'section_order',
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
    ] );

    $filename = 'plgc-menu-export-' . date( 'Y-m-d' ) . '.csv';
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $out = fopen( 'php://output', 'w' );

    // BOM for Excel UTF-8 compatibility
    fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );

    // Header row
    fputcsv( $out, [
        'section', 'section_note', 'section_subtitle', 'section_order',
        'name', 'price', 'price_qualifier', 'description',
        'modifiers', 'dietary', 'item_order',
    ] );

    if ( ! is_wp_error( $sections ) ) {
        foreach ( $sections as $section ) {
            $term_ref      = 'menu_section_' . $section->term_id;
            $section_note  = get_field( 'section_note', $term_ref ) ?: '';
            $section_sub   = get_field( 'section_subtitle', $term_ref ) ?: '';
            $section_order = get_field( 'section_order', $term_ref ) ?: '';
            $first_in_section = true;

            $items = get_posts( [
                'post_type'      => 'plgc_menu_item',
                'posts_per_page' => -1,
                'tax_query'      => [ [
                    'taxonomy' => 'menu_section',
                    'field'    => 'term_id',
                    'terms'    => $section->term_id,
                ] ],
                'meta_key'       => 'menu_item_order',
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'post_status'    => 'publish',
            ] );

            foreach ( $items as $item ) {
                $price     = get_field( 'menu_item_price', $item->ID );
                $qual      = get_field( 'menu_item_price_qualifier', $item->ID ) ?: '';
                $desc      = get_field( 'menu_item_description', $item->ID ) ?: '';
                $mods      = get_field( 'menu_item_modifiers', $item->ID );
                $dietary   = get_field( 'menu_item_dietary', $item->ID );
                $order     = get_field( 'menu_item_order', $item->ID ) ?: '';

                // Encode modifiers
                $mod_str = '';
                if ( $mods && is_array( $mods ) ) {
                    $parts = [];
                    foreach ( $mods as $m ) {
                        $op = ( $m['modifier_price_type'] ?? 'add' ) === 'set' ? '=' : '+';
                        $parts[] = $m['modifier_label'] . ' ' . $op . $m['modifier_price'];
                    }
                    $mod_str = implode( ';', $parts );
                }

                // Encode dietary
                $diet_str = ( $dietary && is_array( $dietary ) ) ? implode( ',', $dietary ) : '';

                fputcsv( $out, [
                    $section->name,
                    $first_in_section ? $section_note : '',
                    $first_in_section ? $section_sub : '',
                    $first_in_section ? $section_order : '',
                    get_the_title( $item->ID ),
                    ( $price !== '' && $price !== null && $price !== false ) ? $price : '',
                    $qual,
                    $desc,
                    $mod_str,
                    $diet_str,
                    $order,
                ] );

                $first_in_section = false;
            }

            // If section has no items, still export it
            if ( empty( $items ) ) {
                fputcsv( $out, [
                    $section->name, $section_note, $section_sub, $section_order,
                    '', '', '', '', '', '', '',
                ] );
            }
        }
    }

    fclose( $out );
    exit;
}

/* ============================================================
   13. CSV IMPORT
   ============================================================ */

/**
 * Process a CSV import.
 *
 * @param  array  $file            $_FILES['csv_file']
 * @param  bool   $dry_run         If true, only preview changes.
 * @param  bool   $delete_unlisted If true, trash items not in the CSV.
 * @return array  Result with 'success', 'message', 'details' keys.
 */
function plgc_menu_csv_import( $file, $dry_run = true, $delete_unlisted = false ) {
    if ( ! current_user_can( 'edit_others_posts' ) ) {
        return [ 'success' => false, 'message' => 'Unauthorized.', 'details' => [] ];
    }

    if ( empty( $file ) || $file['error'] !== UPLOAD_ERR_OK ) {
        return [ 'success' => false, 'message' => 'File upload failed.', 'details' => [] ];
    }

    // Validate extension
    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( $ext !== 'csv' ) {
        return [ 'success' => false, 'message' => 'Please upload a .csv file.', 'details' => [] ];
    }

    $handle = fopen( $file['tmp_name'], 'r' );
    if ( ! $handle ) {
        return [ 'success' => false, 'message' => 'Could not read the uploaded file.', 'details' => [] ];
    }

    // Skip BOM if present
    $bom = fread( $handle, 3 );
    if ( $bom !== chr(0xEF) . chr(0xBB) . chr(0xBF) ) {
        rewind( $handle );
    }

    // Read header
    $header = fgetcsv( $handle );
    if ( ! $header || count( $header ) < 5 ) {
        fclose( $handle );
        return [ 'success' => false, 'message' => 'Invalid CSV format. Expected at least 5 columns.', 'details' => [] ];
    }

    // Normalize header
    $header = array_map( 'trim', array_map( 'strtolower', $header ) );

    $details    = [];
    $created    = 0;
    $updated    = 0;
    $skipped    = 0;
    $row_num    = 1;
    $seen_ids   = [];
    $section_cache = []; // slug => [ 'term_id' => int, 'note_set' => bool ]

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $row_num++;

        // Pad row to expected length
        while ( count( $row ) < 11 ) {
            $row[] = '';
        }

        $section_name  = trim( $row[0] ?? '' );
        $section_note  = trim( $row[1] ?? '' );
        $section_sub   = trim( $row[2] ?? '' );
        $section_order = trim( $row[3] ?? '' );
        $item_name     = trim( $row[4] ?? '' );
        $price         = trim( $row[5] ?? '' );
        $qualifier     = trim( $row[6] ?? '' );
        $description   = trim( $row[7] ?? '' );
        $modifiers_raw = trim( $row[8] ?? '' );
        $dietary_raw   = trim( $row[9] ?? '' );
        $item_order    = trim( $row[10] ?? '' );

        // Skip empty rows
        if ( $section_name === '' && $item_name === '' ) {
            continue;
        }

        // Skip rows with section but no item name (section-only rows)
        if ( $item_name === '' ) {
            // Still process section metadata
            if ( $section_name !== '' && ! $dry_run ) {
                plgc_menu_ensure_section( $section_name, $section_note, $section_sub, $section_order, $section_cache );
            }
            continue;
        }

        // Ensure section exists
        $term_id = null;
        if ( $section_name !== '' ) {
            if ( $dry_run ) {
                $term = get_term_by( 'name', $section_name, 'menu_section' );
                $term_id = $term ? $term->term_id : null;
                $details[] = $term_id
                    ? "Row {$row_num}: Section \"{$section_name}\" exists"
                    : "Row {$row_num}: Section \"{$section_name}\" would be created";
            } else {
                $term_id = plgc_menu_ensure_section( $section_name, $section_note, $section_sub, $section_order, $section_cache );
            }
        }

        // Find existing item by name + section
        $existing = null;
        $args = [
            'post_type'      => 'plgc_menu_item',
            'posts_per_page' => 1,
            'title'          => $item_name,
            'post_status'    => [ 'publish', 'draft', 'trash' ],
        ];
        if ( $term_id ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'menu_section',
                'field'    => 'term_id',
                'terms'    => $term_id,
            ] ];
        }
        $found = get_posts( $args );

        // Fallback: search by title across all sections
        if ( empty( $found ) ) {
            unset( $args['tax_query'] );
            $found = get_posts( $args );
        }

        if ( ! empty( $found ) ) {
            $existing = $found[0];
        }

        // Parse modifiers
        $parsed_mods = plgc_menu_parse_modifiers( $modifiers_raw );

        // Parse dietary
        $parsed_dietary = [];
        if ( $dietary_raw !== '' ) {
            $parsed_dietary = array_map( 'trim', explode( ',', strtolower( $dietary_raw ) ) );
            $valid_flags = array_keys( plgc_dietary_labels() );
            $parsed_dietary = array_intersect( $parsed_dietary, $valid_flags );
        }

        if ( $dry_run ) {
            if ( $existing ) {
                $details[] = "Row {$row_num}: \"{$item_name}\" — would UPDATE (ID {$existing->ID})";
                $updated++;
            } else {
                $details[] = "Row {$row_num}: \"{$item_name}\" — would CREATE in \"{$section_name}\"";
                $created++;
            }
            if ( $existing ) {
                $seen_ids[] = $existing->ID;
            }
            continue;
        }

        // Create or update
        if ( $existing ) {
            // Untrash if needed
            if ( $existing->post_status === 'trash' ) {
                wp_untrash_post( $existing->ID );
            }
            wp_update_post( [ 'ID' => $existing->ID, 'post_status' => 'publish' ] );
            $post_id = $existing->ID;
            $details[] = "Row {$row_num}: Updated \"{$item_name}\" (ID {$post_id})";
            $updated++;
        } else {
            $post_id = wp_insert_post( [
                'post_type'   => 'plgc_menu_item',
                'post_title'  => sanitize_text_field( $item_name ),
                'post_status' => 'publish',
            ] );
            if ( is_wp_error( $post_id ) ) {
                $details[] = "Row {$row_num}: FAILED to create \"{$item_name}\"";
                $skipped++;
                continue;
            }
            $details[] = "Row {$row_num}: Created \"{$item_name}\" (ID {$post_id})";
            $created++;
        }

        $seen_ids[] = $post_id;

        // Set taxonomy
        if ( $term_id ) {
            wp_set_object_terms( $post_id, [ $term_id ], 'menu_section' );
        }

        // Set ACF fields
        update_field( 'menu_item_price', $price !== '' ? floatval( $price ) : '', $post_id );
        update_field( 'menu_item_price_qualifier', sanitize_text_field( $qualifier ), $post_id );
        update_field( 'menu_item_description', sanitize_textarea_field( $description ), $post_id );
        update_field( 'menu_item_order', $item_order !== '' ? intval( $item_order ) : 10, $post_id );
        update_field( 'menu_item_dietary', array_values( $parsed_dietary ), $post_id );

        // Set modifiers (repeater)
        if ( ! empty( $parsed_mods ) ) {
            update_field( 'menu_item_modifiers', $parsed_mods, $post_id );
        } else {
            delete_field( 'menu_item_modifiers', $post_id );
        }
    }

    fclose( $handle );

    // Handle deletion of unlisted items
    if ( $delete_unlisted && ! empty( $seen_ids ) ) {
        $all_items = get_posts( [
            'post_type'      => 'plgc_menu_item',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );
        $to_trash = array_diff( $all_items, $seen_ids );
        $trash_count = 0;
        foreach ( $to_trash as $trash_id ) {
            if ( $dry_run ) {
                $details[] = "Would TRASH: \"" . get_the_title( $trash_id ) . "\" (ID {$trash_id})";
            } else {
                wp_trash_post( $trash_id );
                $details[] = "Trashed: \"" . get_the_title( $trash_id ) . "\" (ID {$trash_id})";
            }
            $trash_count++;
        }
        if ( $trash_count > 0 ) {
            $details[] = "---";
            $details[] = "{$trash_count} item(s) " . ( $dry_run ? 'would be' : 'were' ) . " moved to Trash.";
        }
    }

    // Flush cache after real import
    if ( ! $dry_run ) {
        plgc_menu_flush_cache();
    }

    $verb    = $dry_run ? 'Preview' : 'Import';
    $message = "{$verb} complete: {$created} created, {$updated} updated, {$skipped} skipped.";

    return [ 'success' => true, 'message' => $message, 'details' => $details ];
}

/**
 * Ensure a menu_section term exists; create if not. Set metadata on first encounter.
 *
 * @return int Term ID.
 */
function plgc_menu_ensure_section( $name, $note, $subtitle, $order, &$cache ) {
    $slug = sanitize_title( $name );

    if ( isset( $cache[ $slug ] ) ) {
        return $cache[ $slug ]['term_id'];
    }

    $term = get_term_by( 'name', $name, 'menu_section' );

    if ( ! $term ) {
        $result = wp_insert_term( $name, 'menu_section', [ 'slug' => $slug ] );
        if ( is_wp_error( $result ) ) {
            // Try fetching by slug as fallback (name mismatch but slug exists)
            $term = get_term_by( 'slug', $slug, 'menu_section' );
            if ( ! $term ) return 0;
            $term_id = $term->term_id;
        } else {
            $term_id = $result['term_id'];
        }
    } else {
        $term_id = $term->term_id;
    }

    // Set section metadata
    if ( $note !== '' )     update_field( 'section_note', sanitize_textarea_field( $note ), 'menu_section_' . $term_id );
    if ( $subtitle !== '' ) update_field( 'section_subtitle', sanitize_text_field( $subtitle ), 'menu_section_' . $term_id );
    if ( $order !== '' )    update_field( 'section_order', intval( $order ), 'menu_section_' . $term_id );

    $cache[ $slug ] = [ 'term_id' => $term_id ];
    return $term_id;
}

/**
 * Parse modifier string from CSV.
 *
 * Format: "Label +3;Label =5" where + = add-on, = = set price.
 *
 * @return array ACF repeater rows.
 */
function plgc_menu_parse_modifiers( $raw ) {
    if ( $raw === '' ) return [];

    $parts  = explode( ';', $raw );
    $result = [];

    foreach ( $parts as $part ) {
        $part = trim( $part );
        if ( $part === '' ) continue;

        // Match: "Label +3.50" or "Label =4"
        if ( preg_match( '/^(.+?)\s*([+=])(\d+\.?\d*)$/', $part, $matches ) ) {
            $result[] = [
                'modifier_label'      => trim( $matches[1] ),
                'modifier_price'      => floatval( $matches[3] ),
                'modifier_price_type' => $matches[2] === '=' ? 'set' : 'add',
            ];
        }
    }

    return $result;
}
