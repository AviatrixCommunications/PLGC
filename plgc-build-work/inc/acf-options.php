<?php
/**
 * Prairie Landing Golf Club — ACF Options Page
 *
 * Registers a "Prairie Landing Settings" options page with tabs for:
 *   Contact, Social Media, Weather, Cookie & Legal, Branding
 *
 * Fields are registered programmatically (no DB field group needed).
 * Requires ACF Pro.
 *
 * @package PLGC
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper: get an options field value.
 * Always defined — even if ACF Pro is not active — so footer.php
 * and other templates can call it safely without a fatal error.
 *
 * @param  string $field_name   ACF field name.
 * @param  mixed  $default      Fallback value if field is empty or ACF absent.
 * @return mixed
 */
function plgc_option( string $field_name, $default = '' ) {
    if ( ! function_exists( 'get_field' ) ) return $default;
    $value = get_field( $field_name, 'option' );
    return ( $value !== '' && $value !== null && $value !== false ) ? $value : $default;
}

// ── Register Options Page + Fields ──────────────────────────────────────────
// Hook to 'init' — fires after plugins are loaded but is still available
// when the theme registers its hooks via after_setup_theme.

add_action( 'init', function () {
    if ( ! function_exists( 'acf_add_options_page' ) ) return;

    acf_add_options_page( [
        'page_title'  => 'Prairie Landing Settings',
        'menu_title'  => 'PL Settings',
        'menu_slug'   => 'plgc-settings',
        'capability'  => 'manage_options',
        'icon_url'    => 'dashicons-admin-site-alt3',
        'position'    => 25,
        'autoload'    => true,
    ] );
}, 1 ); // priority 1 — before field groups register at default priority

add_action( 'acf/init', 'plgc_register_acf_options_fields' );

function plgc_register_acf_options_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'      => 'group_plgc_site_settings',
        'title'    => 'Site Settings',
        'location' => [ [ [
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'plgc-settings',
        ] ] ],
        'fields'   => [

            // ================================================================
            // TAB: HOMEPAGE
            // ================================================================
            [
                'key'   => 'field_plgc_tab_homepage',
                'label' => 'Homepage',
                'type'  => 'tab',
            ],
            [
                'key'     => 'field_plgc_homepage_intro_msg',
                'label'   => '',
                'name'    => 'plgc_homepage_intro_msg',
                'type'    => 'message',
                'message' => '<h3 style="margin:0 0 0.5em">Homepage Content</h3>'
                           . '<p>Homepage sections are managed in the panels below this one on this same settings page. '
                           . 'Scroll down to find:</p>'
                           . '<ul style="margin:0.5em 0 0 1.25em;list-style:disc">'
                           . '<li><strong>Homepage Gallery Sections</strong> — Golf Outings, Weddings &amp; Events, and McChesney\'s sliders (images, headings, body text, CTAs)</li>'
                           . '<li><strong>"Grass Is Greener" Section</strong> — Section title, intro, background image, four image tiles (with links), and testimonials</li>'
                           . '<li><strong>Events Carousel</strong> — Fallback image and messaging shown when no events are currently featured.</li>'
                           . '</ul>',
                'new_lines' => 'wpautop',
                'esc_html'  => 0,
            ],

            // ================================================================
            // CHAMPIONSHIP GOLF — EVENT SLIDER FALLBACK
            // ================================================================
            [
                'key'     => 'field_plgc_es_fallback_label',
                'label'   => '',
                'name'    => 'plgc_es_fallback_label',
                'type'    => 'message',
                'message' => '<h3 style="margin:0 0 0.25em">Events Carousel — Fallback</h3>'
                           . '<p style="margin:0">Shown in the Events Carousel panel when no events are currently featured. '
                           . 'If no fallback image is set, a plain text message is displayed instead.</p>',
                'new_lines' => 'wpautop',
                'esc_html'  => 0,
                'wrapper'   => [ 'width' => '100' ],
            ],
            [
                'key'           => 'field_plgc_event_fallback_image',
                'label'         => 'Fallback Image',
                'name'          => 'plgc_event_fallback_image',
                'type'          => 'image',
                'instructions'  => 'Full-bleed cover photo shown when no events are active. Ideal upload size: 1416 × 1134 px (2× retina). Leave blank to show a text-only message instead.',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'wrapper'       => [ 'width' => '40' ],
            ],
            [
                'key'          => 'field_plgc_event_fallback_title',
                'label'        => 'Fallback Heading',
                'name'         => 'plgc_event_fallback_title',
                'type'         => 'text',
                'instructions' => 'Optional headline overlaid on the fallback image. Leave blank to hide.',
                'placeholder'  => 'Events at Prairie Landing',
                'wrapper'      => [ 'width' => '60' ],
            ],
            [
                'key'          => 'field_plgc_event_fallback_msg',
                'label'        => 'Fallback Message',
                'name'         => 'plgc_event_fallback_msg',
                'type'         => 'text',
                'instructions' => 'Short line of text shown beneath the heading. Leave blank to hide.',
                'placeholder'  => 'Stay tuned — upcoming events will be announced here.',
                'wrapper'      => [ 'width' => '60' ],
            ],
            [
                'key'          => 'field_plgc_event_fallback_cta_text',
                'label'        => 'Fallback CTA Label',
                'name'         => 'plgc_event_fallback_cta_text',
                'type'         => 'text',
                'instructions' => 'Button label on the fallback slide. Leave blank to hide the CTA entirely.',
                'placeholder'  => 'View All Events',
                'wrapper'      => [ 'width' => '20' ],
            ],
            [
                'key'          => 'field_plgc_event_fallback_cta_url',
                'label'        => 'Fallback CTA URL',
                'name'         => 'plgc_event_fallback_cta_url',
                'type'         => 'url',
                'instructions' => 'Where the CTA link points. Only used when a CTA Label is set.',
                'placeholder'  => 'https://prairielanding.com/events/',
                'wrapper'      => [ 'width' => '40' ],
            ],

            // ================================================================
            // TAB: CONTACT
            // ================================================================
            [
                'key'   => 'field_plgc_tab_contact',
                'label' => 'Contact',
                'type'  => 'tab',
            ],
            [
                'key'          => 'field_plgc_address',
                'label'        => 'Street Address',
                'name'         => 'plgc_address',
                'type'         => 'text',
                'instructions' => 'Displayed in footer and linked to Google Maps.',
                'default_value'=> '2325 Longest Drive, West Chicago, IL 60185',
                'wrapper'      => [ 'width' => '70' ],
            ],
            [
                'key'          => 'field_plgc_maps_place_id',
                'label'        => 'Google Maps Place ID',
                'name'         => 'plgc_maps_place_id',
                'type'         => 'text',
                'instructions' => 'Find at: maps.google.com → your location → Share → Embed a map → copy the place_id from the URL. Starts with "ChIJ…"',
                'placeholder'  => 'ChIJ...',
                'wrapper'      => [ 'width' => '30' ],
            ],
            [
                'key'          => 'field_plgc_maps_api_key',
                'label'        => 'Google Maps Static API Key',
                'name'         => 'plgc_maps_api_key',
                'type'         => 'text',
                'instructions' => 'Used to render the static map image in the footer. Enable the "Maps Static API" in Google Cloud Console. Store securely — this key should have HTTP referrer restrictions.',
                'placeholder'  => 'AIza...',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_plgc_phone_pro_shop',
                'label'        => 'Pro Shop Phone',
                'name'         => 'plgc_phone_pro_shop',
                'type'         => 'text',
                'instructions' => 'Displayed as "Pro Shop: (630) 208-7600"',
                'default_value'=> '(630) 208-7600',
                'wrapper'      => [ 'width' => '25' ],
            ],
            [
                'key'          => 'field_plgc_phone_events',
                'label'        => 'Events / Banquets Phone',
                'name'         => 'plgc_phone_events',
                'type'         => 'text',
                'instructions' => 'For Banquets, Weddings, Golf Outings, and Special Events.',
                'default_value'=> '(630) 208-7629',
                'wrapper'      => [ 'width' => '25' ],
            ],

            // ================================================================
            // TAB: SOCIAL MEDIA
            // ================================================================
            [
                'key'   => 'field_plgc_tab_social',
                'label' => 'Social Media',
                'type'  => 'tab',
            ],
            [
                'key'     => 'field_plgc_social_intro_msg',
                'label'   => '',
                'name'    => 'plgc_social_intro_msg',
                'type'    => 'message',
                'message' => '<p><strong>Social Media Links</strong></p>'
                           . '<p>Each icon appears in the footer only when a URL is filled in. '
                           . '<strong>Leave a field blank to hide that icon.</strong> '
                           . 'To remove a network, simply clear the URL out of the box and save.</p>',
                'new_lines' => 'wpautop',
                'esc_html'  => 0,
            ],
            [
                'key'         => 'field_plgc_social_facebook',
                'label'       => 'Facebook URL',
                'name'        => 'plgc_social_facebook',
                'type'        => 'url',
                'placeholder' => 'https://www.facebook.com/...',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_plgc_social_instagram',
                'label'       => 'Instagram URL',
                'name'        => 'plgc_social_instagram',
                'type'        => 'url',
                'placeholder' => 'https://www.instagram.com/...',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_plgc_social_tiktok',
                'label'       => 'TikTok URL',
                'name'        => 'plgc_social_tiktok',
                'type'        => 'url',
                'placeholder' => 'https://www.tiktok.com/@...',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_plgc_social_x',
                'label'       => 'X (Twitter) URL',
                'name'        => 'plgc_social_x',
                'type'        => 'url',
                'placeholder' => 'https://x.com/...',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_plgc_social_linkedin',
                'label'       => 'LinkedIn URL',
                'name'        => 'plgc_social_linkedin',
                'type'        => 'url',
                'placeholder' => 'https://www.linkedin.com/company/...',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_plgc_social_theknot',
                'label'       => 'The Knot URL',
                'name'        => 'plgc_social_theknot',
                'type'        => 'url',
                'placeholder' => 'https://www.theknot.com/marketplace/...',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_plgc_theknot_logo',
                'label'        => 'The Knot — Logo Image URL',
                'name'         => 'plgc_theknot_logo',
                'type'         => 'url',
                'instructions' => 'Paste the direct URL to your The Knot badge image from the Media Library. To get it: Media → find the image → click Edit → copy the "File URL" from the right column.',
                'placeholder'  => 'https://yoursite.com/wp-content/uploads/...',
                'wrapper'      => [ 'width' => '50' ],
            ],

            // ================================================================
            // TAB: WEATHER
            // ================================================================
            // TAB: COOKIE & LEGAL
            // ================================================================
            [
                'key'   => 'field_plgc_tab_legal',
                'label' => 'Cookie & Legal',
                'type'  => 'tab',
            ],
            [
                'key'          => 'field_plgc_acquia_script',
                'label'        => 'Acquia Web Governance — Script',
                'name'         => 'plgc_acquia_script',
                'type'         => 'textarea',
                'new_lines'    => '',
                'instructions' => 'Paste the <strong>full script block</strong> from your Acquia / Monsido dashboard (Script Setup Guide → copy script). The theme outputs it verbatim on every page at the location chosen below. Leave empty to disable.<br><em>Requires an admin account with the "unfiltered HTML" capability (standard on single-site WordPress).</em>',
                'rows'         => 14,
                'placeholder'  => '<script type="text/javascript">
window._monsido = window._monsido || { token: "YOUR-TOKEN", … };
window._monsidoConsentManagerConfig = { token: "YOUR-TOKEN", … };
</script>
<script type="text/javascript" async src="https://app-script.monsido.com/v2/monsido-script.js"></script>
<script type="text/javascript" src="https://monsido-consent.com/v1/mcm.js"></script>',
                'wrapper'      => [ 'width' => '100' ],
            ],
            [
                'key'           => 'field_plgc_acquia_script_placement',
                'label'         => 'Script Placement',
                'name'          => 'plgc_acquia_script_placement',
                'type'          => 'select',
                'instructions'  => 'Where to insert the script. Acquia recommends <strong>&lt;body&gt;</strong> for the tracking script. Choose <strong>&lt;head&gt;</strong> only if you enable Consent Manager (it must load before other scripts to block cookies).',
                'choices'       => [
                    'body' => '<body> — right after the opening tag (recommended by Acquia)',
                    'head' => '<head> — before any other scripts (required for Consent Manager)',
                ],
                'default_value' => 'body',
                'return_format' => 'value',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_plgc_cookie_js_method',
                'label'        => 'Cookie Settings Button — JS Call',
                'name'         => 'plgc_cookie_js_method',
                'type'         => 'text',
                'instructions' => 'JavaScript expression that reopens the consent dialog when the footer "Manage Cookie Settings" button is clicked. Once Monsido is running, right-click the floating cookie icon → Inspect → copy its selector, then update this field to: <code>document.querySelector(\'YOUR_SELECTOR\').click()</code>',
                'default_value'=> 'var el=document.querySelector(\'[id*=\"mcm\"][role=\"button\"], [class*=\"mcm-consent\"], [id*=\"monsido-consent\"], [aria-label*=\"cookie\"][aria-label*=\"consent\"]\'); if(el) el.click();',
                'wrapper'      => [ 'width' => '100' ],
            ],
            [
                'key'          => 'field_plgc_privacy_policy_url',
                'label'        => 'Privacy Policy URL',
                'name'         => 'plgc_privacy_policy_url',
                'type'         => 'url',
                'instructions' => 'Leave blank to auto-use the URL from Settings → Privacy. Once you create your Termageddon policy page, paste its URL here.',
                'placeholder'  => 'https://...',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_plgc_cookie_policy_url',
                'label'        => 'Cookie Policy URL',
                'name'         => 'plgc_cookie_policy_url',
                'type'         => 'url',
                'instructions' => 'Separate cookie policy page if you have one (Termageddon can generate this). Used as the fallback link if JS consent is unavailable.',
                'placeholder'  => 'https://...',
                'wrapper'      => [ 'width' => '50' ],
            ],

            // ================================================================
            // TAB: BRANDING
            // ================================================================
            [
                'key'   => 'field_plgc_tab_branding',
                'label' => 'Branding',
                'type'  => 'tab',
            ],
            [
                'key'          => 'field_plgc_footer_logo',
                'label'        => 'Footer Logo',
                'name'         => 'plgc_footer_logo',
                'type'         => 'image',
                'instructions' => 'The circular logo version for the footer. If left blank, falls back to the site logo from Appearance → Customize. The white circle background is applied via CSS — upload the transparent-background version.',
                'return_format'=> 'array',
                'preview_size' => 'thumbnail',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_plgc_copyright_text',
                'label'        => 'Copyright Text',
                'name'         => 'plgc_copyright_text',
                'type'         => 'text',
                'instructions' => 'The year is prepended automatically. Example: "Prairie Landing Golf Club. All rights reserved."',
                'default_value'=> 'Prairie Landing Golf Club. All rights reserved.',
                'wrapper'      => [ 'width' => '50' ],
            ],

        ], // end fields
    ] ); // end acf_add_local_field_group
}

// Clear weather cache whenever options are saved
add_action( 'acf/save_post', function ( $post_id ) {
    if ( $post_id === 'options' ) {
        delete_transient( 'plgc_weather_cache' );
    }
}, 20 );
