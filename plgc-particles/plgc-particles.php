<?php
/**
 * Plugin Name:  PLGC Falling Particles
 * Description:  Festive falling particle animations for Prairie Landing — snowflakes, flowers, golf balls, leaves, Easter eggs, and more. Schedule by date, target specific pages, and upload custom branded images.
 * Version:      1.3.0
 * Author:       Aviatrix Communications
 * License:      GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'PLGC_PARTS_VERSION', '1.3.0' );
define( 'PLGC_PARTS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'PLGC_PARTS_URI',     plugin_dir_url( __FILE__ ) );

// ── Admin menu ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_menu_page(
        'PLGC Particles',
        'Particles',
        'manage_options',
        'plgc-particles',
        'plgc_parts_settings_page',
        'dashicons-star-filled',
        26
    );
} );

// ── Settings registration ─────────────────────────────────────────────────────

add_action( 'admin_init', function () {
    $opts = [
        'plgc_parts_enabled',
        'plgc_parts_type',
        'plgc_parts_custom_image',
        'plgc_parts_custom_emojis',
        'plgc_parts_count',
        'plgc_parts_speed',
        'plgc_parts_size',
        'plgc_parts_page_target',
        'plgc_parts_page_ids',
        'plgc_parts_schedule_start',
        'plgc_parts_schedule_end',
        'plgc_parts_opacity',
        'plgc_parts_wind',
    ];
    foreach ( $opts as $o ) {
        register_setting( 'plgc_particles_settings', $o, [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }
} );

// ── Particle presets ──────────────────────────────────────────────────────────

function plgc_parts_presets(): array {
    return [
        'snowflakes'    => [ 'label' => '❄️ Snowflakes',       'particles' => ['❄', '❅', '❆'],            'behavior' => 'drift',   'color' => '#a8d4f5' ],
        'flowers'       => [ 'label' => '🌸 Flower Petals',    'particles' => ['🌸', '🌼', '🌺', '🌻'],    'behavior' => 'flutter', 'color' => '#f9a8d4' ],
        'leaves'        => [ 'label' => '🍂 Autumn Leaves',    'particles' => ['🍂', '🍁', '🍃'],          'behavior' => 'tumble',  'color' => '#f97316' ],
        'golf_balls'    => [ 'label' => '⛳ Golf Balls',        'particles' => ['⛳'],                       'behavior' => 'bounce',  'color' => '#ffffff' ],
        'easter'        => [ 'label' => '🐣 Easter',           'particles' => ['🐣', '🐰', '🥚', '🌷'],   'behavior' => 'flutter', 'color' => '#fde68a' ],
        'santa'         => [ 'label' => '🎅 Santa Hats',       'particles' => ['🎅', '🤶', '🎁', '⭐'],   'behavior' => 'drift',   'color' => '#ef4444' ],
        'confetti'      => [ 'label' => '🎉 Confetti',         'particles' => ['confetti'],                 'behavior' => 'spin',    'color' => 'multi'   ],
        'food'          => [ 'label' => '🍔 Food',             'particles' => ['🍔', '🌮', '🍕', '🍟'],   'behavior' => 'tumble',  'color' => '#f59e0b' ],
        'beverages'     => [ 'label' => '🍺 Beverages',        'particles' => ['🍺', '🍷', '🥃', '🥂'],   'behavior' => 'flutter', 'color' => '#f59e0b' ],
        'custom_emoji'  => [ 'label' => '✏️ Custom Emojis',    'particles' => ['custom_emoji'],             'behavior' => 'flutter', 'color' => '#ffffff' ],
        'custom'        => [ 'label' => '🖼 Custom Image',     'particles' => ['custom'],                   'behavior' => 'spin',    'color' => '#ffffff' ],
    ];
}

// ── Settings page ─────────────────────────────────────────────────────────────

function plgc_parts_settings_page() {
    $presets  = plgc_parts_presets();
    $enabled  = get_option( 'plgc_parts_enabled', '0' );
    $type     = get_option( 'plgc_parts_type', 'snowflakes' );
    $custom       = get_option( 'plgc_parts_custom_image', '' );
    $custom_emoji = get_option( 'plgc_parts_custom_emojis', '' );
    $count    = get_option( 'plgc_parts_count', '40' );
    $speed    = get_option( 'plgc_parts_speed', '1' );
    $size     = get_option( 'plgc_parts_size', '1' );
    $opacity  = get_option( 'plgc_parts_opacity', '0.85' );
    $wind     = get_option( 'plgc_parts_wind', '0' );
    $target   = get_option( 'plgc_parts_page_target', 'home' );
    $page_ids = get_option( 'plgc_parts_page_ids', '' );
    $s_start  = get_option( 'plgc_parts_schedule_start', '' );
    $s_end    = get_option( 'plgc_parts_schedule_end', '' );

    // Live status
    $active = plgc_parts_should_run_globally();
    ?>
    <div class="wrap">
        <h1>🎉 PLGC Falling Particles</h1>

        <?php if ( $active ) : ?>
        <div class="notice notice-success"><p>✅ Particles are currently <strong>active</strong> on your site based on your settings.</p></div>
        <?php elseif ( $enabled === '1' ) : ?>
        <div class="notice notice-warning"><p>⏸ Particles are enabled but not currently showing — check your schedule dates or page targets.</p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'plgc_particles_settings' ); ?>

            <!-- ── On/Off ───────────────────────────────────────── -->
            <h2>Enable</h2>
            <table class="form-table">
                <tr><th>Particle Animation</th>
                    <td>
                        <label>
                            <input type="checkbox" name="plgc_parts_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
                            Enable falling particles on the site
                        </label>
                        <p class="description">This is the master switch. Use Schedule below to automate seasonal on/off.</p>
                    </td></tr>
            </table>

            <!-- ── Particle type ────────────────────────────────── -->
            <hr>
            <h2>Particle Type</h2>
            <table class="form-table">
                <tr><th>Choose a Preset</th>
                    <td>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;max-width:720px">
                        <?php foreach ( $presets as $key => $preset ) : ?>
                            <label style="display:flex;align-items:center;gap:8px;background:#f9f9f9;border:2px solid <?php echo $type === $key ? '#567915' : '#ddd'; ?>;border-radius:8px;padding:10px;cursor:pointer">
                                <input type="radio" name="plgc_parts_type" value="<?php echo esc_attr($key); ?>" <?php checked($type,$key); ?> style="margin:0">
                                <span style="font-size:0.9rem"><?php echo esc_html($preset['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </td></tr>
                <tr><th><label for="plgc_parts_custom_emojis">Custom Emojis / Characters</label></th>
                    <td>
                        <input type="text" id="plgc_parts_custom_emojis" name="plgc_parts_custom_emojis"
                               value="<?php echo esc_attr($custom_emoji); ?>" class="large-text"
                               placeholder="🦌, 🎄, ⭐, 🕯️">
                        <p class="description">
                            Used when <strong>Custom Emojis</strong> is selected above. Enter any emoji or characters, separated by commas.<br>
                            You can mix and match — e.g. <code>🦌, 🎄, ⭐</code> for a custom holiday theme, or <code>🏌️, ⛳, 🏆</code> for a tournament.<br>
                            Pro tip: Open your emoji picker with <strong>Windows + .</strong> (Windows) or <strong>Cmd + Ctrl + Space</strong> (Mac) to browse emojis.
                        </p>
                    </td></tr>
                <tr><th><label for="plgc_parts_custom_image">Custom Image URL</label></th>
                    <td>
                        <input type="url" id="plgc_parts_custom_image" name="plgc_parts_custom_image"
                               value="<?php echo esc_attr($custom); ?>" class="large-text"
                               placeholder="https://yourdomain.com/wp-content/uploads/golf-ball-logo.png">
                        <p class="description">Used when "Custom Image" is selected above. Upload your branded image to the Media Library, then paste the URL here. Works great for a logo-branded golf ball. PNG with transparent background recommended.</p>
                        <?php if ( $custom ) : ?>
                            <p><img src="<?php echo esc_url($custom); ?>" style="max-height:60px;max-width:120px;margin-top:6px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff" alt="Current custom image"></p>
                        <?php endif; ?>
                    </td></tr>
            </table>

            <!-- ── Appearance ───────────────────────────────────── -->
            <hr>
            <h2>Appearance &amp; Physics</h2>
            <table class="form-table">
                <tr><th><label for="plgc_parts_count">Particle Count</label></th>
                    <td>
                        <input type="range" id="plgc_parts_count" name="plgc_parts_count"
                               min="5" max="120" step="5" value="<?php echo esc_attr($count); ?>"
                               oninput="document.getElementById('plgc_count_val').textContent=this.value">
                        <span id="plgc_count_val" style="font-weight:bold;margin-left:8px"><?php echo esc_html($count); ?></span>
                        <p class="description">More particles = more festive but heavier on performance. 30–50 is the sweet spot.</p>
                    </td></tr>
                <tr><th><label for="plgc_parts_speed">Fall Speed</label></th>
                    <td>
                        <input type="range" id="plgc_parts_speed" name="plgc_parts_speed"
                               min="0.3" max="3" step="0.1" value="<?php echo esc_attr($speed); ?>"
                               oninput="document.getElementById('plgc_speed_val').textContent=parseFloat(this.value).toFixed(1)+'x'">
                        <span id="plgc_speed_val" style="font-weight:bold;margin-left:8px"><?php echo esc_html($speed); ?>x</span>
                    </td></tr>
                <tr><th><label for="plgc_parts_size">Size</label></th>
                    <td>
                        <input type="range" id="plgc_parts_size" name="plgc_parts_size"
                               min="0.5" max="3" step="0.1" value="<?php echo esc_attr($size); ?>"
                               oninput="document.getElementById('plgc_size_val').textContent=parseFloat(this.value).toFixed(1)+'x'">
                        <span id="plgc_size_val" style="font-weight:bold;margin-left:8px"><?php echo esc_html($size); ?>x</span>
                    </td></tr>
                <tr><th><label for="plgc_parts_opacity">Opacity</label></th>
                    <td>
                        <input type="range" id="plgc_parts_opacity" name="plgc_parts_opacity"
                               min="0.1" max="1" step="0.05" value="<?php echo esc_attr($opacity); ?>"
                               oninput="document.getElementById('plgc_opacity_val').textContent=Math.round(this.value*100)+'%'">
                        <span id="plgc_opacity_val" style="font-weight:bold;margin-left:8px"><?php echo round($opacity*100); ?>%</span>
                    </td></tr>
                <tr><th><label for="plgc_parts_wind">Horizontal Drift</label></th>
                    <td>
                        <input type="range" id="plgc_parts_wind" name="plgc_parts_wind"
                               min="-2" max="2" step="0.1" value="<?php echo esc_attr($wind); ?>"
                               oninput="document.getElementById('plgc_wind_val').textContent=parseFloat(this.value).toFixed(1)">
                        <span id="plgc_wind_val" style="font-weight:bold;margin-left:8px"><?php echo esc_html($wind); ?></span>
                        <p class="description">Negative = drift left, 0 = straight down, positive = drift right.</p>
                    </td></tr>
            </table>

            <!-- ── Page targeting ───────────────────────────────── -->
            <hr>
            <h2>Where to Show</h2>
            <table class="form-table">
                <tr><th>Show Particles On</th>
                    <td>
                        <label style="display:block;margin-bottom:6px">
                            <input type="radio" name="plgc_parts_page_target" value="home" <?php checked($target,'home'); ?>>
                            <strong>Homepage only</strong>
                        </label>
                        <label style="display:block;margin-bottom:6px">
                            <input type="radio" name="plgc_parts_page_target" value="all" <?php checked($target,'all'); ?>>
                            <strong>All pages</strong> — entire site
                        </label>
                        <label style="display:block;margin-bottom:6px">
                            <input type="radio" name="plgc_parts_page_target" value="specific" <?php checked($target,'specific'); ?>>
                            <strong>Specific pages</strong> — enter page IDs below
                        </label>
                        <label style="display:block">
                            <input type="radio" name="plgc_parts_page_target" value="home_and_specific" <?php checked($target,'home_and_specific'); ?>>
                            <strong>Homepage + specific pages</strong>
                        </label>
                    </td></tr>
                <tr><th><label for="plgc_parts_page_ids">Specific Page IDs</label></th>
                    <td>
                        <input type="text" id="plgc_parts_page_ids" name="plgc_parts_page_ids"
                               value="<?php echo esc_attr($page_ids); ?>" class="regular-text"
                               placeholder="42, 87, 156">
                        <p class="description">Comma-separated WordPress page IDs. To find a page ID, go to Pages, hover over the page title, and look for "post=123" in the URL at the bottom of your browser.</p>
                        <?php
                        // Show a helpful page picker
                        $pages = get_pages( ['sort_column' => 'post_title', 'number' => 60] );
                        if ( $pages ) :
                        ?>
                        <details style="margin-top:8px">
                            <summary style="cursor:pointer;color:#567915;font-weight:600">Browse pages to find IDs ▾</summary>
                            <div style="margin-top:8px;max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;background:#f9f9f9">
                            <?php foreach ( $pages as $p ) : ?>
                                <div style="font-size:0.85rem;padding:2px 0">
                                    <code style="font-size:0.8rem;background:#e5e7eb;padding:1px 5px;border-radius:3px"><?php echo $p->ID; ?></code>
                                    <?php echo esc_html( $p->post_title ); ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    </td></tr>
            </table>

            <!-- ── Schedule ─────────────────────────────────────── -->
            <hr>
            <h2>Schedule (Optional)</h2>
            <p>Set a date range and the particles will turn on and off automatically. Leave blank to control manually with the Enable toggle above.</p>
            <table class="form-table">
                <tr><th><label for="plgc_parts_schedule_start">Show From</label></th>
                    <td>
                        <input type="date" id="plgc_parts_schedule_start" name="plgc_parts_schedule_start"
                               value="<?php echo esc_attr($s_start); ?>" class="regular-text">
                        <p class="description">Leave blank to use the Enable toggle above instead.</p>
                    </td></tr>
                <tr><th><label for="plgc_parts_schedule_end">Show Until</label></th>
                    <td>
                        <input type="date" id="plgc_parts_schedule_end" name="plgc_parts_schedule_end"
                               value="<?php echo esc_attr($s_end); ?>" class="regular-text">
                    </td></tr>
            </table>

            <div style="background:#f0f9ef;border:1px solid #bde5b3;border-radius:8px;padding:12px 16px;max-width:620px;margin:1rem 0">
                <strong>💡 Scheduling examples:</strong>
                <ul style="margin:6px 0 0 20px;font-size:0.875rem">
                    <li>December golf balls → Dec 1 – Dec 31</li>
                    <li>Spring flowers → Apr 1 – May 15</li>
                    <li>Easter eggs → the week before Easter</li>
                    <li>Opening Day party → just set the single day</li>
                </ul>
            </div>

            <?php submit_button( 'Save Particle Settings' ); ?>
        </form>
    </div>
    <?php
}

// ── Should particles run? ─────────────────────────────────────────────────────

function plgc_parts_should_run_globally(): bool {
    if ( get_option( 'plgc_parts_enabled', '0' ) !== '1' ) return false;

    // Check schedule
    $start = get_option( 'plgc_parts_schedule_start', '' );
    $end   = get_option( 'plgc_parts_schedule_end', '' );
    if ( $start || $end ) {
        $now = current_time( 'timestamp' );
        if ( $start && $now < strtotime( $start . ' 00:00:00' ) ) return false;
        if ( $end   && $now > strtotime( $end   . ' 23:59:59' ) ) return false;
    }

    return true;
}

function plgc_parts_should_run_on_page(): bool {
    if ( ! plgc_parts_should_run_globally() ) return false;

    $target   = get_option( 'plgc_parts_page_target', 'home' );
    $page_ids = array_filter( array_map( 'trim', explode( ',', get_option( 'plgc_parts_page_ids', '' ) ) ) );
    $page_ids = array_map( 'intval', $page_ids );

    if ( $target === 'all' )                                           return true;
    if ( $target === 'home' && is_front_page() )                       return true;
    if ( $target === 'specific' && is_page( $page_ids ) )              return true;
    if ( $target === 'home_and_specific' && ( is_front_page() || is_page( $page_ids ) ) ) return true;

    return false;
}

// ── Output everything inline in the footer — no enqueue, no timing issues ────

add_action( 'wp_footer', function () {
    if ( ! plgc_parts_should_run_on_page() ) return;

    $type         = get_option( 'plgc_parts_type', 'snowflakes' );
    $presets      = plgc_parts_presets();
    $preset       = $presets[ $type ] ?? $presets['snowflakes'];
    $custom       = get_option( 'plgc_parts_custom_image', '' );
    $custom_emoji = get_option( 'plgc_parts_custom_emojis', '' );

    if ( $type === 'custom_emoji' && $custom_emoji ) {
        $emoji_list = array_values( array_filter( array_map( 'trim', explode( ',', $custom_emoji ) ) ) );
        $particles  = $emoji_list ?: ['🎉'];
        $behavior   = 'flutter';
    } else {
        $particles = $preset['particles'];
        $behavior  = $preset['behavior'];
    }

    $cfg = json_encode([
        'type'        => $type,
        'particles'   => $particles,
        'behavior'    => $behavior,
        'count'       => intval( get_option( 'plgc_parts_count', 40 ) ),
        'speed'       => floatval( get_option( 'plgc_parts_speed', 1 ) ),
        'size'        => floatval( get_option( 'plgc_parts_size', 1 ) ),
        'opacity'     => floatval( get_option( 'plgc_parts_opacity', 0.85 ) ),
        'wind'        => floatval( get_option( 'plgc_parts_wind', 0 ) ),
        'customImage' => $type === 'custom' ? esc_url( $custom ) : '',
    ]);

    // Read the engine JS from disk
    $js_file = PLGC_PARTS_DIR . 'assets/js/particles.js';
    $engine  = file_exists( $js_file ) ? file_get_contents( $js_file ) : '';

    ?>
    <canvas id="plgc-particles-canvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99998" aria-hidden="true"></canvas>
    <script>
    (function(){
        var PLGC_PARTICLES = <?php echo $cfg; ?>;
        <?php echo $engine; ?>
    })();
    </script>
    <?php
}, 99 );
