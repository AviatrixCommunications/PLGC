<?php
/**
 * Plugin Name:  PLGC Golf Weather
 * Description:  Golf-focused weather widget for Prairie Landing. Shows current conditions, playability rating, and a 3-day forecast strip using Weatherbit.io.
 * Version:      2.5.2
 * Author:       Aviatrix Communications
 * License:      GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'PLGC_WX_VERSION', '2.5.2' );
define( 'PLGC_WX_DIR',     plugin_dir_path( __FILE__ ) );
define( 'PLGC_WX_URI',     plugin_dir_url( __FILE__ ) );

// ── Settings ──────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_options_page( 'PLGC Golf Weather', 'Golf Weather', 'manage_options', 'plgc-weather', 'plgc_wx_settings_page' );
} );

add_action( 'admin_init', function () {
    foreach ( [ 'plgc_wx_api_key', 'plgc_wx_lat', 'plgc_wx_lon', 'plgc_wx_unit', 'plgc_wx_popup_url' ] as $opt ) {
        register_setting( 'plgc_weather_settings', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }
    register_setting( 'plgc_weather_settings', 'plgc_wx_cache_ttl', [ 'sanitize_callback' => 'absint' ] );

    // Course status settings
    foreach ( [ 'plgc_course_status', 'plgc_course_status_msg', 'plgc_course_season_start', 'plgc_course_season_end' ] as $opt ) {
        register_setting( 'plgc_weather_settings', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }
} );

foreach ( [ 'plgc_wx_api_key', 'plgc_wx_lat', 'plgc_wx_lon' ] as $_opt ) {
    add_action( "update_option_{$_opt}", function () { delete_transient( 'plgc_wx_current' ); delete_transient( 'plgc_wx_forecast' ); } );
}

function plgc_wx_settings_page() {
    $flushed = false;
    if ( isset( $_POST['plgc_wx_flush'] ) && check_admin_referer( 'plgc_wx_flush' ) ) {
        delete_transient( 'plgc_wx_current' );
        delete_transient( 'plgc_wx_forecast' );
        $flushed = true;
    }
    $status     = get_option( 'plgc_course_status', 'auto' );
    $status_msg = get_option( 'plgc_course_status_msg', '' );
    $s_start    = get_option( 'plgc_course_season_start', '' );
    $s_end      = get_option( 'plgc_course_season_end', '' );
    ?>
    <div class="wrap">
        <h1>PLGC Golf Weather &amp; Course Status</h1>
        <?php if ( $flushed ) : ?><div class="notice notice-success is-dismissible"><p>Cache cleared.</p></div><?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'plgc_weather_settings' ); ?>

            <h2>🌤 Weather Widget</h2>
            <table class="form-table">
                <tr><th><label for="plgc_wx_api_key">Weatherbit.io API Key</label></th>
                    <td><input type="text" id="plgc_wx_api_key" name="plgc_wx_api_key"
                               value="<?php echo esc_attr( get_option('plgc_wx_api_key') ); ?>" class="regular-text">
                        <p class="description">Free at <a href="https://app.weatherbit.io" target="_blank">app.weatherbit.io</a>.</p></td></tr>
                <tr><th><label for="plgc_wx_lat">Latitude</label></th>
                    <td><input type="text" id="plgc_wx_lat" name="plgc_wx_lat"
                               value="<?php echo esc_attr( get_option('plgc_wx_lat','41.8880') ); ?>" class="small-text"></td></tr>
                <tr><th><label for="plgc_wx_lon">Longitude</label></th>
                    <td><input type="text" id="plgc_wx_lon" name="plgc_wx_lon"
                               value="<?php echo esc_attr( get_option('plgc_wx_lon','-88.2073') ); ?>" class="small-text"></td></tr>
                <tr><th>Temperature Unit</th>
                    <td>
                        <label><input type="radio" name="plgc_wx_unit" value="I" <?php checked( get_option('plgc_wx_unit','I'), 'I' ); ?>> °F</label>&nbsp;&nbsp;
                        <label><input type="radio" name="plgc_wx_unit" value="M" <?php checked( get_option('plgc_wx_unit','I'), 'M' ); ?>> °C</label>
                    </td></tr>
                <tr><th><label for="plgc_wx_cache_ttl">Cache Duration (minutes)</label></th>
                    <td><input type="number" id="plgc_wx_cache_ttl" name="plgc_wx_cache_ttl"
                               value="<?php echo esc_attr( get_option('plgc_wx_cache_ttl',60) ); ?>" class="small-text" min="5" max="720"></td></tr>
                <tr><th><label for="plgc_wx_popup_url">Extended Forecast URL (optional)</label></th>
                    <td><input type="url" id="plgc_wx_popup_url" name="plgc_wx_popup_url"
                               value="<?php echo esc_attr( get_option('plgc_wx_popup_url') ); ?>" class="regular-text"></td></tr>
            </table>

            <hr>
            <h2>⛳ Course Status</h2>
            <p>Control what status banner displays on the site. Use this to manually mark the course closed for weather, aeration, maintenance, or off-season. The shortcode <code>[plgc_course_status]</code> outputs the banner anywhere.</p>
            <table class="form-table">
                <tr><th>Course Status</th>
                    <td>
                        <label style="display:block;margin-bottom:6px">
                            <input type="radio" name="plgc_course_status" value="auto" <?php checked($status,'auto'); ?>>
                            <strong>Auto</strong> — show no banner (course is open, status managed by weather widget)
                        </label>
                        <label style="display:block;margin-bottom:6px">
                            <input type="radio" name="plgc_course_status" value="limited" <?php checked($status,'limited'); ?>>
                            <strong>Limited Play</strong> — show a yellow notice (e.g. cart paths only, 9 holes only)
                        </label>
                        <label style="display:block;margin-bottom:6px">
                            <input type="radio" name="plgc_course_status" value="closed" <?php checked($status,'closed'); ?>>
                            <strong>Closed</strong> — show a red closed banner
                        </label>
                        <label style="display:block">
                            <input type="radio" name="plgc_course_status" value="offseason" <?php checked($status,'offseason'); ?>>
                            <strong>Off Season</strong> — show a neutral "course closed for the season" message
                        </label>
                    </td></tr>
                <tr><th><label for="plgc_course_status_msg">Custom Message</label></th>
                    <td>
                        <input type="text" id="plgc_course_status_msg" name="plgc_course_status_msg"
                               value="<?php echo esc_attr($status_msg); ?>" class="large-text"
                               placeholder="e.g. Cart paths only today due to course conditions.">
                        <p class="description">Leave blank to use the default message for the selected status above.</p>
                    </td></tr>
            </table>

            <h3 style="margin-top:1.5rem">Seasonal Schedule (optional)</h3>
            <p>If set, the banner will automatically switch to <strong>Off Season</strong> outside of these dates every year — no need to remember to toggle it. Leave blank to manage manually.</p>
            <table class="form-table">
                <tr><th><label for="plgc_course_season_start">Season Opens (month-day)</label></th>
                    <td>
                        <input type="text" id="plgc_course_season_start" name="plgc_course_season_start"
                               value="<?php echo esc_attr($s_start); ?>" class="small-text" placeholder="04-01">
                        <p class="description">Format: MM-DD (e.g. 04-01 for April 1st)</p>
                    </td></tr>
                <tr><th><label for="plgc_course_season_end">Season Closes (month-day)</label></th>
                    <td>
                        <input type="text" id="plgc_course_season_end" name="plgc_course_season_end"
                               value="<?php echo esc_attr($s_end); ?>" class="small-text" placeholder="11-30">
                        <p class="description">Format: MM-DD (e.g. 11-30 for November 30th)</p>
                    </td></tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>
        <h2>Shortcodes</h2>
        <table class="widefat" style="max-width:720px">
            <thead><tr><th>Shortcode</th><th>Use</th></tr></thead>
            <tbody>
                <tr><td><code>[plgc_weather]</code></td><td>Full golf forecast card (current + 3-day)</td></tr>
                <tr><td><code>[plgc_weather compact="true"]</code></td><td>Compact inline widget (footer)</td></tr>
                <tr><td><code>[plgc_weather theme="light"]</code></td><td>Dark text for light backgrounds</td></tr>
                <tr><td><code>[plgc_course_status]</code></td><td>Course status banner — shows nothing when status is Auto</td></tr>
                <tr><td><code>[plgc_course_status always="true"]</code></td><td>Always show the banner (even when Auto/open)</td></tr>
            </tbody>
        </table>

        <hr>
        <form method="post">
            <?php wp_nonce_field('plgc_wx_flush'); ?>
            <button type="submit" name="plgc_wx_flush" value="1" class="button">Clear Weather Cache</button>
        </form>
    </div>
    <?php
}

// ── API helpers ───────────────────────────────────────────────────────────────

function plgc_wx_api_key(): string {
    $key = get_option( 'plgc_wx_api_key' );
    if ( empty( $key ) && function_exists( 'get_field' ) ) {
        $key = get_field( 'plgc_weatherbit_api_key', 'option' ) ?: '';
    }
    return (string) $key;
}

function plgc_wx_coords(): array {
    $lat = get_option( 'plgc_wx_lat', '41.8880' );
    $lon = get_option( 'plgc_wx_lon', '-88.2073' );
    if ( empty( get_option('plgc_wx_lat') ) && function_exists( 'get_field' ) ) {
        $lat = get_field( 'plgc_weather_lat', 'option' ) ?: $lat;
        $lon = get_field( 'plgc_weather_lon', 'option' ) ?: $lon;
    }
    return [ floatval($lat), floatval($lon) ];
}

// ── Current conditions ────────────────────────────────────────────────────────

function plgc_wx_get_current(): ?array {
    $cached = get_transient( 'plgc_wx_current' );
    if ( $cached !== false ) return $cached;

    $key   = plgc_wx_api_key();
    if ( empty($key) ) return null;

    [$lat, $lon] = plgc_wx_coords();
    $units = get_option( 'plgc_wx_unit', 'I' );

    $url = 'https://api.weatherbit.io/v2.0/current?' . http_build_query([
        'lat' => $lat, 'lon' => $lon, 'key' => $key, 'units' => $units,
    ]);

    $res = wp_remote_get( $url, ['timeout' => 8] );
    if ( is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200 ) return null;

    $d = json_decode( wp_remote_retrieve_body($res), true )['data'][0] ?? null;
    if ( !$d ) return null;

    $icon_code = $d['weather']['icon'] ?? 'c01d';
    $wind_spd  = round( $d['wind_spd'] ?? 0 );
    $precip    = round( $d['precip'] ?? 0 );
    $temp      = round( $d['temp'] ?? 0 );

    $data = [
        'icon_code'   => $icon_code,
        'icon_type'   => plgc_wx_icon_type( $icon_code ),
        'description' => ucfirst( strtolower( $d['weather']['description'] ?? '' ) ),
        'temp'        => $temp,
        'feels_like'  => round( $d['app_temp'] ?? 0 ),
        'humidity'    => round( $d['rh'] ?? 0 ),
        'wind_speed'  => $wind_spd,
        'wind_dir'    => $d['wind_cdir_full'] ?? '',
        'wind_cdir'   => $d['wind_cdir'] ?? '',   // abbreviation e.g. "SW"
        'wind_deg'    => round( $d['wind_dir'] ?? 0 ),
        'uv_index'    => round( $d['uv'] ?? 0 ),
        'visibility'  => round( $d['vis'] ?? 0 ),
        'precip'      => $precip,
        'clouds'      => round( $d['clouds'] ?? 0 ),   // cloud cover %
        'unit_label'  => ( $units === 'I' ) ? '°F' : '°C',
        'speed_label' => ( $units === 'I' ) ? 'mph' : 'm/s',
        'is_night'    => str_ends_with( $icon_code, 'n' ),
        'playability' => plgc_wx_playability( $temp, $wind_spd, $precip, $icon_code, $units ),
    ];

    $ttl = absint( get_option('plgc_wx_cache_ttl', 60) ) * MINUTE_IN_SECONDS;
    set_transient( 'plgc_wx_current', $data, $ttl );
    return $data;
}

// ── 3-day forecast ────────────────────────────────────────────────────────────

function plgc_wx_get_forecast(): array {
    $cached = get_transient( 'plgc_wx_forecast' );
    if ( $cached !== false ) return $cached;

    $key   = plgc_wx_api_key();
    if ( empty($key) ) return [];

    [$lat, $lon] = plgc_wx_coords();
    $units = get_option( 'plgc_wx_unit', 'I' );

    $url = 'https://api.weatherbit.io/v2.0/forecast/daily?' . http_build_query([
        'lat' => $lat, 'lon' => $lon, 'key' => $key, 'units' => $units, 'days' => 4,
    ]);

    $res = wp_remote_get( $url, ['timeout' => 8] );
    if ( is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200 ) return [];

    $raw  = json_decode( wp_remote_retrieve_body($res), true )['data'] ?? [];
    $days = [];

    // Skip index 0 (today — we already have current), take next 3
    foreach ( array_slice( $raw, 1, 3 ) as $d ) {
        $icon_code = $d['weather']['icon'] ?? 'c01d';
        $high      = round( $d['high_temp'] ?? 0 );
        $low       = round( $d['low_temp'] ?? 0 );
        $pop       = round( $d['pop'] ?? 0 );      // precip probability %
        $wind_spd  = round( $d['wind_spd'] ?? 0 );
        $precip    = round( $d['precip'] ?? 0 );

        $days[] = [
            'date'        => $d['datetime'] ?? '',
            'icon_code'   => $icon_code,
            'icon_type'   => plgc_wx_icon_type( $icon_code ),
            'description' => ucfirst( strtolower( $d['weather']['description'] ?? '' ) ),
            'high'        => $high,
            'low'         => $low,
            'pop'         => $pop,
            'wind_speed'  => $wind_spd,
            'wind_dir'    => $d['wind_cdir_full'] ?? '',
            'unit_label'  => ( $units === 'I' ) ? '°F' : '°C',
            'speed_label' => ( $units === 'I' ) ? 'mph' : 'm/s',
            'playability' => plgc_wx_playability( $high, $wind_spd, $precip, $icon_code, $units ),
        ];
    }

    $ttl = absint( get_option('plgc_wx_cache_ttl', 60) ) * MINUTE_IN_SECONDS;
    set_transient( 'plgc_wx_forecast', $days, $ttl );
    return $days;
}

// ── Golf playability rating ───────────────────────────────────────────────────

function plgc_wx_playability( int $temp, int $wind, int $precip, string $icon_code, string $units ): array {
    // Normalise to °F for comparison
    $temp_f = ( $units === 'M' ) ? ( $temp * 9/5 + 32 ) : $temp;
    $base   = rtrim( $icon_code, 'dn' );

    // Lightning / thunderstorm — hard stop
    if ( str_starts_with( $base, 't' ) ) {
        return [ 'label' => 'Lightning Risk', 'emoji' => '⚡', 'class' => 'plgc-wx-play--danger', 'tip' => 'Thunderstorms expected — hold off on the round.' ];
    }

    // Heavy rain / significant precip
    if ( $precip >= 5 || in_array( $base, ['r03','r05','r06'] ) ) {
        return [ 'label' => 'Pack the Rain Gear', 'emoji' => '🌧', 'class' => 'plgc-wx-play--poor', 'tip' => 'Heavy rain likely — cart paths only, bring gear.' ];
    }

    // Snow / sleet / freezing
    if ( str_starts_with( $base, 's' ) || $base === 'f01' ) {
        return [ 'label' => 'Dress for the Chill', 'emoji' => '❄️', 'class' => 'plgc-wx-play--fair', 'tip' => 'Wintry conditions — layer up and check course conditions before heading out.' ];
    }

    // Too cold
    if ( $temp_f < 40 ) {
        return [ 'label' => 'Tough Conditions', 'emoji' => '🥶', 'class' => 'plgc-wx-play--poor', 'tip' => 'Bundle up — below 40°F makes for a tough round.' ];
    }

    // Too hot
    if ( $temp_f > 98 ) {
        return [ 'label' => 'Hydrate Often', 'emoji' => '🌡️', 'class' => 'plgc-wx-play--fair', 'tip' => 'Hot one today — drink plenty of water and pack sunscreen.' ];
    }

    // High wind
    if ( $wind >= 25 ) {
        return [ 'label' => 'Windy — Club Up', 'emoji' => '💨', 'class' => 'plgc-wx-play--fair', 'tip' => 'Strong winds will affect your game — add 1-2 clubs.' ];
    }

    // Light rain
    if ( $precip > 0 || in_array( $base, ['r01','r02','d01','d02','d03'] ) ) {
        return [ 'label' => 'Bring an Umbrella', 'emoji' => '☂️', 'class' => 'plgc-wx-play--fair', 'tip' => 'Light rain possible — pack a sleeve and an umbrella.' ];
    }

    // Good conditions
    if ( $temp_f >= 60 && $temp_f <= 85 && $wind < 12 ) {
        return [ 'label' => 'Perfect Golf Day', 'emoji' => '⛳', 'class' => 'plgc-wx-play--perfect', 'tip' => 'Ideal conditions — get out there and make some birdies.' ];
    }

    if ( $temp_f >= 50 && $temp_f <= 92 && $wind < 18 ) {
        return [ 'label' => 'Great Day to Play', 'emoji' => '🏌️', 'class' => 'plgc-wx-play--great', 'tip' => 'Solid conditions — should be a good round.' ];
    }

    return [ 'label' => 'Playable', 'emoji' => '👍', 'class' => 'plgc-wx-play--ok', 'tip' => 'Decent conditions — dress for comfort.' ];
}

// ── Icon type mapping ─────────────────────────────────────────────────────────

function plgc_wx_icon_type( string $code ): string {
    $night = str_ends_with( $code, 'n' );
    $base  = rtrim( $code, 'dn' );
    return match(true) {
        $base === 'c01'                        => $night ? 'moon'         : 'sun',
        $base === 'c02'                        => $night ? 'moon_cloud'   : 'sun_cloud',
        $base === 'c03'                        => $night ? 'moon_cloud'   : 'partly_cloudy',
        $base === 'c04'                        => 'overcast',
        in_array($base,['d01','d02','d03'])    => 'drizzle',
        in_array($base,['r01','r02'])          => 'rain_light',
        $base === 'r03'                        => 'rain_heavy',
        in_array($base,['r04','r05','r06'])    => 'shower',
        $base === 'f01'                        => 'freezing_rain',
        $base === 's01'                        => 'snow_light',
        in_array($base,['s02','s03'])          => 'snow_heavy',
        in_array($base,['s04','s05'])          => 'sleet',
        $base === 's06'                        => 'freezing_rain',
        in_array($base,['t01','t02'])          => 'thunder_light',
        in_array($base,['t03','t04','t05'])    => 'thunder_heavy',
        in_array($base,['a01','a02'])          => 'fog',
        default                                => 'overcast',
    };
}

// ── Course status → playability override ─────────────────────────────────────

/**
 * Returns a playability array override when course status demands it,
 * or null to let normal weather-based playability stand.
 */
function plgc_wx_course_status_override(): ?array {
    $status = get_option( 'plgc_course_status', 'auto' );
    $msg    = trim( get_option( 'plgc_course_status_msg', '' ) );

    // Respect seasonal schedule same as the banner logic
    $s_start = get_option( 'plgc_course_season_start', '' );
    $s_end   = get_option( 'plgc_course_season_end', '' );
    if ( $status === 'auto' && $s_start && $s_end ) {
        $now      = current_time( 'timestamp' );
        $year     = wp_date( 'Y' );
        $start_ts = strtotime( $year . '-' . $s_start . ' 00:00:00' );
        $end_ts   = strtotime( $year . '-' . $s_end   . ' 23:59:59' );
        if ( $now < $start_ts || $now > $end_ts ) {
            $status = 'offseason';
        }
    }

    return match( $status ) {
        'closed'    => [
            'label' => 'Closed Today',
            'emoji' => '🚫',
            'class' => 'plgc-wx-play--closed',
            'tip'   => $msg ?: 'The course is closed today. We look forward to seeing you next time!',
        ],
        'limited'   => [
            'label' => 'Limited Play',
            'emoji' => '⚠️',
            'class' => 'plgc-wx-play--limited',
            'tip'   => $msg ?: 'Limited play today — check with the pro shop for current conditions.',
        ],
        'offseason' => [
            'label' => 'Off Season',
            'emoji' => '❄️',
            'class' => 'plgc-wx-play--offseason',
            'tip'   => $msg ?: 'Prairie Landing is closed for the season. See you in the spring!',
        ],
        default     => null,
    };
}

// ── Condition advisory text ───────────────────────────────────────────────────

/**
 * Returns a plain-language advisory sentence for notable conditions,
 * or empty string for benign weather. Shown in the popup.
 */
function plgc_wx_condition_advisory( string $icon_code ): string {
    $base = rtrim( $icon_code, 'dn' );
    return match(true) {
        str_starts_with($base,'t')                          => 'Lightning possible — suspend play immediately if thunder is heard.',
        in_array($base, ['r05','r06'])                      => 'Heavy rain expected — greens and paths will be very wet.',
        in_array($base, ['r01','r02','r03','r04'])          => 'Rain in the forecast — bring a rain sleeve and umbrella.',
        in_array($base, ['d01','d02','d03'])                => 'Light drizzle expected — greens may be soft.',
        in_array($base, ['s02','s03'])                      => 'Heavy snow expected — course conditions will be significantly affected.',
        in_array($base, ['s01'])                            => 'Light snow possible — layer up and check conditions before heading out.',
        in_array($base, ['s04','s05','f01','s06'])          => 'Freezing rain or sleet possible — paths may be slippery.',
        in_array($base, ['a01','a02'])                      => 'Dense fog advisory — reduced visibility on the course.',
        default                                             => '',
    };
}

// ── SVG helper ────────────────────────────────────────────────────────────────

function plgc_wx_svg( string $icon_type, int $size = 40 ): string {
    $file = PLGC_WX_DIR . 'assets/icons/' . $icon_type . '.svg';
    if ( ! file_exists($file) ) {
        $file = PLGC_WX_DIR . 'assets/icons/overcast.svg';
    }
    $svg = file_get_contents( $file );
    return preg_replace(
        '/<svg/',
        "<svg width=\"{$size}\" height=\"{$size}\" style=\"width:{$size}px;height:{$size}px;display:block;flex-shrink:0\" aria-hidden=\"true\" focusable=\"false\"",
        $svg, 1
    );
}

// ── Wind compass helper ───────────────────────────────────────────────────────

function plgc_wx_compass_arrow( int $deg ): string {
    // The arrow points in the direction wind is blowing TO (from + 180)
    $arrow_deg = ( $deg + 180 ) % 360;
    return "<svg width='16' height='16' viewBox='0 0 16 16' style='display:inline-block;vertical-align:middle;transform:rotate({$arrow_deg}deg)' aria-hidden='true'>
        <polygon points='8,1 11,13 8,11 5,13' fill='#FFAE40'/>
    </svg>";
}

// ── Shortcode ─────────────────────────────────────────────────────────────────

add_shortcode( 'plgc_weather', 'plgc_wx_shortcode' );

function plgc_wx_shortcode( $atts ): string {
    $atts = shortcode_atts([
        'compact'    => 'false',
        'theme'      => 'dark',
        'show_label' => 'true',
        'label'      => "Today's Golf Forecast:",
    ], $atts, 'plgc_weather' );

    plgc_wx_enqueue_assets();

    $w = plgc_wx_get_current();

    if ( ! $w ) {
        if ( current_user_can('manage_options') ) {
            return '<span class="plgc-wx-notice">Golf Weather: add your Weatherbit API key in <a href="' . admin_url('options-general.php?page=plgc-weather') . '">Settings → Golf Weather</a>.</span>';
        }
        return '';
    }

    $theme = 'plgc-wx--' . ( $atts['theme'] === 'light' ? 'light' : 'dark' );

    // ── Compact mode (footer inline) ──────────────────────────────────────────
    if ( $atts['compact'] === 'true' ) {
        return plgc_wx_compact_widget( $w, $theme, $atts );
    }

    // ── Full card ─────────────────────────────────────────────────────────────
    return plgc_wx_full_card( $w, $theme, $atts );
}

// ── Compact inline widget (for footer) ───────────────────────────────────────

function plgc_wx_compact_widget( array $w, string $theme, array $atts ): string {
    $label_html = $atts['show_label'] !== 'false'
        ? '<span class="plgc-wx__label">' . esc_html( $atts['label'] ) . '</span>'
        : '';

    // Course status overrides weather-based playability if set
    $play       = plgc_wx_course_status_override() ?? $w['playability'];
    $icon_sm    = plgc_wx_svg( $w['icon_type'], 52 );
    $icon_lg    = plgc_wx_svg( $w['icon_type'], 80 );
    $desc       = esc_html( $w['description'] );
    $temp       = esc_html( $w['temp'] . $w['unit_label'] );
    $feels      = esc_html( $w['feels_like'] . $w['unit_label'] );
    $humidity   = esc_html( $w['humidity'] . '%' );
    $compass    = plgc_wx_compass_arrow( $w['wind_deg'] );
    $wind_dir   = esc_html( strtoupper( $w['wind_cdir'] ) );
    $wind       = esc_html( $w['wind_speed'] . ' ' . $w['speed_label'] );
    $uv         = esc_html( $w['uv_index'] );
    $clouds     = esc_html( ( $w['clouds'] ?? 0 ) . '%' );

    $feels_inline = $w['temp'] !== $w['feels_like']
        ? '<span class="plgc-wx__feels-inline">Feels ' . $feels . '</span>'
        : '';

    // Advisory text for notable conditions (rain, storms, snow, fog)
    $advisory_text = plgc_wx_condition_advisory( $w['icon_code'] );
    $advisory_html = $advisory_text
        ? '<p class="plgc-wx__condition-advisory">' . esc_html( $advisory_text ) . '</p>'
        : '';

    // Badge: text only — icon above already communicates the condition
    $play_badge = '<span class="plgc-wx__play-badge ' . esc_attr($play['class']) . '">'
        . esc_html($play['label']) . '</span>';

    // Suppress day playability pills when course status overrides (they'd be misleading)
    $status_overridden = plgc_wx_course_status_override() !== null;

    // 3-day forecast strip for popup
    $forecast   = plgc_wx_get_forecast();
    $days_html  = '';
    foreach ( $forecast as $i => $day ) {
        $date     = strtotime( $day['date'] );
        $day_name = $date ? date('D', $date) : 'Day ' . ($i+1);
        $d_icon   = plgc_wx_svg( $day['icon_type'], 44 );
        $d_high   = esc_html( $day['high'] . $day['unit_label'] );
        $d_low    = esc_html( $day['low'] . $day['unit_label'] );
        $d_pop    = esc_html( $day['pop'] . '%' );
        $d_play   = $day['playability'];
        $d_badge  = $status_overridden ? '' : '<span class="plgc-wx__day-play ' . esc_attr($d_play['class']) . '">' . esc_html($d_play['label']) . '</span>';
        $days_html .= <<<DAY
<div class="plgc-wx__day">
    <span class="plgc-wx__day-name">{$day_name}</span>
    <span class="plgc-wx__day-icon">{$d_icon}</span>
    <span class="plgc-wx__day-temp">{$d_high} <em>{$d_low}</em></span>
    <span class="plgc-wx__day-pop">🌧 {$d_pop}</span>
    {$d_badge}
</div>
DAY;
    }
    $forecast_strip = $days_html ? '<div class="plgc-wx__popup-forecast">' . $days_html . '</div>' : '';

    return <<<HTML
<div class="plgc-wx plgc-wx--compact {$theme}" role="group" aria-label="Golf weather">
    {$label_html}
    <button class="plgc-wx__trigger" aria-expanded="false" aria-controls="plgc-wx-popup" type="button">
        <span class="plgc-wx__icon">{$icon_sm}</span>
        <span class="plgc-wx__reading">{$desc}, {$temp}</span>
        <span class="plgc-wx__chevron" aria-hidden="true">&#8964;</span>
    </button>
    <div class="plgc-wx__popup" id="plgc-wx-popup" role="dialog" aria-label="Golf weather details" aria-modal="true" hidden>
        <div class="plgc-wx__popup-inner">
            <button class="plgc-wx__close" aria-label="Close" type="button">&times;</button>
            <div class="plgc-wx__popup-hero">
                <div class="plgc-wx__popup-icon">{$icon_lg}</div>
                <div class="plgc-wx__popup-hero-info">
                    <p class="plgc-wx__popup-temp">{$temp}{$feels_inline}</p>
                    <p class="plgc-wx__popup-desc">{$desc}</p>
                    <div class="plgc-wx__popup-badge-row">
                        {$play_badge}
                        <span class="plgc-wx__play-tip">{$play['tip']}</span>
                    </div>
                    {$advisory_html}
                </div>
            </div>
            <dl class="plgc-wx__popup-details">
                <div><dt>Wind {$compass} {$wind_dir}</dt><dd>{$wind}</dd></div>
                <div><dt>Humidity</dt><dd>{$humidity}</dd></div>
                <div><dt>UV Index</dt><dd>{$uv}</dd></div>
                <div><dt>Cloud Cover</dt><dd>{$clouds}</dd></div>
            </dl>
            {$forecast_strip}
        </div>
    </div>
</div>
HTML;
}

// ── Full golf forecast card ───────────────────────────────────────────────────

function plgc_wx_full_card( array $w, string $theme, array $atts ): string {
    // Course status overrides weather-based playability if set
    $play      = plgc_wx_course_status_override() ?? $w['playability'];
    $icon_svg  = plgc_wx_svg( $w['icon_type'], 88 );
    $compass   = plgc_wx_compass_arrow( $w['wind_deg'] );
    $desc      = esc_html( $w['description'] );
    $temp      = esc_html( $w['temp'] . $w['unit_label'] );
    $feels     = esc_html( $w['feels_like'] . $w['unit_label'] );
    $feels_sub = $w['temp'] !== $w['feels_like'] ? '<span class="plgc-wx__feels-sub">Feels ' . $feels . '</span>' : '';
    $wind      = esc_html( $w['wind_speed'] . ' ' . $w['speed_label'] );
    $wind_dir  = esc_html( strtoupper( $w['wind_cdir'] ) );
    $humidity  = esc_html( $w['humidity'] . '%' );
    $uv        = esc_html( $w['uv_index'] );
    $clouds    = esc_html( ( $w['clouds'] ?? 0 ) . '%' );
    $popup_url = esc_url( get_option('plgc_wx_popup_url') );
    $more_link = $popup_url ? '<a href="' . $popup_url . '" class="plgc-wx__more-link" target="_blank" rel="noopener">Extended Forecast →</a>' : '';

    // Advisory text for notable conditions
    $advisory_text = plgc_wx_condition_advisory( $w['icon_code'] );
    $advisory_html = $advisory_text
        ? '<p class="plgc-wx__condition-advisory">' . esc_html( $advisory_text ) . '</p>'
        : '';

    // Suppress day playability pills when course status overrides
    $status_overridden = plgc_wx_course_status_override() !== null;

    // 3-day forecast strip — text-only badges on day cards
    $forecast  = plgc_wx_get_forecast();
    $days_html = '';
    foreach ( $forecast as $i => $day ) {
        $date      = strtotime( $day['date'] );
        $day_name  = $date ? date('D', $date) : 'Day ' . ($i+1);
        $d_icon    = plgc_wx_svg( $day['icon_type'], 44 );
        $d_high    = esc_html( $day['high'] . $day['unit_label'] );
        $d_low     = esc_html( $day['low'] . $day['unit_label'] );
        $d_pop     = esc_html( $day['pop'] . '%' );
        $d_play    = $day['playability'];
        $d_badge   = $status_overridden ? '' : '<span class="plgc-wx__day-play ' . esc_attr($d_play['class']) . '" title="' . esc_attr($d_play['tip']) . '">' . esc_html($d_play['label']) . '</span>';
        $days_html .= <<<DAY
<div class="plgc-wx__day">
    <span class="plgc-wx__day-name">{$day_name}</span>
    <span class="plgc-wx__day-icon">{$d_icon}</span>
    <span class="plgc-wx__day-temp">{$d_high} <em>{$d_low}</em></span>
    <span class="plgc-wx__day-pop" title="Rain chance">🌧 {$d_pop}</span>
    {$d_badge}
</div>
DAY;
    }

    $forecast_strip = $days_html ? '<div class="plgc-wx__forecast-strip">' . $days_html . '</div>' : '';

    return <<<HTML
<div class="plgc-wx plgc-wx--full {$theme}" role="region" aria-label="Golf weather forecast">
    <div class="plgc-wx__card">

        <div class="plgc-wx__header">
            <span class="plgc-wx__header-label">Golf Forecast</span>
            {$more_link}
        </div>

        <div class="plgc-wx__current">
            <div class="plgc-wx__current-icon">{$icon_svg}</div>
            <div class="plgc-wx__current-info">
                <p class="plgc-wx__current-temp">{$temp}{$feels_sub}</p>
                <p class="plgc-wx__current-desc">{$desc}</p>
                <div class="plgc-wx__play-badge {$play['class']}">{$play['label']}</div>
                <p class="plgc-wx__play-tip">{$play['tip']}</p>
                {$advisory_html}
            </div>
        </div>

        <div class="plgc-wx__stats">
            <div class="plgc-wx__stat">
                <span class="plgc-wx__stat-label">Wind {$compass} {$wind_dir}</span>
                <span class="plgc-wx__stat-value">{$wind}</span>
            </div>
            <div class="plgc-wx__stat">
                <span class="plgc-wx__stat-label">Humidity</span>
                <span class="plgc-wx__stat-value">{$humidity}</span>
            </div>
            <div class="plgc-wx__stat">
                <span class="plgc-wx__stat-label">UV Index</span>
                <span class="plgc-wx__stat-value">{$uv}</span>
            </div>
            <div class="plgc-wx__stat">
                <span class="plgc-wx__stat-label">Cloud Cover</span>
                <span class="plgc-wx__stat-value">{$clouds}</span>
            </div>
        </div>

        {$forecast_strip}

    </div>
</div>
HTML;
}

// ── Course Status shortcode ───────────────────────────────────────────────────

add_shortcode( 'plgc_course_status', 'plgc_course_status_shortcode' );

function plgc_course_status_shortcode( $atts ): string {
    $atts   = shortcode_atts( [ 'always' => 'false' ], $atts );
    $banner = plgc_get_course_status_banner();
    if ( ! $banner && $atts['always'] !== 'true' ) return '';
    return $banner ?: '<div class="plgc-course-status plgc-course-status--open"><span class="plgc-course-status__icon">⛳</span> <span>Course is open — see you on the links!</span></div>';
}

/**
 * Returns the HTML banner string, or empty string if no banner needed (auto/open).
 * Also respects the seasonal schedule — auto-off-seasons if outside the season window.
 */
function plgc_get_course_status_banner(): string {
    $status  = get_option( 'plgc_course_status', 'auto' );
    $msg     = trim( get_option( 'plgc_course_status_msg', '' ) );
    $s_start = get_option( 'plgc_course_season_start', '' );
    $s_end   = get_option( 'plgc_course_season_end', '' );

    // Check seasonal schedule — override status to offseason if outside window
    if ( $status === 'auto' && $s_start && $s_end ) {
        $now      = current_time( 'timestamp' );
        $year     = wp_date( 'Y' );
        $start_ts = strtotime( $year . '-' . $s_start );
        $end_ts   = strtotime( $year . '-' . $s_end );

        if ( $now < $start_ts || $now > $end_ts ) {
            $status = 'offseason';
        }
    }

    $defaults = [
        'auto'      => '',
        'limited'   => 'Cart paths only today — course conditions require limited access.',
        'closed'    => 'The course is closed today. We\'ll see you next time!',
        'offseason' => 'Prairie Landing is closed for the season. We look forward to seeing you in the spring!',
    ];

    if ( $status === 'auto' ) return '';

    $text = $msg ?: ( $defaults[$status] ?? '' );
    if ( ! $text ) return '';

    $icon_map  = [ 'limited' => '⚠️', 'closed' => '🚫', 'offseason' => '❄️' ];
    $icon      = $icon_map[$status] ?? 'ℹ️';
    $class     = esc_attr( 'plgc-course-status plgc-course-status--' . $status );

    return '<div class="' . $class . '" role="status"><span class="plgc-course-status__icon" aria-hidden="true">' . $icon . '</span> <span>' . esc_html( $text ) . '</span></div>';
}

// ── REST endpoint ─────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
    register_rest_route( 'plgc-weather/v1', '/current', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response( plgc_wx_get_current() ?? new WP_Error('no_data','Unavailable',['status'=>503]) ),
        'permission_callback' => '__return_true',
    ] );
    register_rest_route( 'plgc-weather/v1', '/forecast', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response( plgc_wx_get_forecast() ),
        'permission_callback' => '__return_true',
    ] );
} );

// ── Assets ────────────────────────────────────────────────────────────────────

function plgc_wx_enqueue_assets(): void {
    static $done = false;
    if ( $done ) return;
    $done = true;
    wp_enqueue_style( 'plgc-golf-weather', PLGC_WX_URI . 'assets/css/weather-widget.css', [], PLGC_WX_VERSION );
    wp_enqueue_script( 'plgc-golf-weather', PLGC_WX_URI . 'assets/js/weather-widget.js', [], PLGC_WX_VERSION, true );
}

add_action( 'wp_enqueue_scripts', function () {
    // The compact widget lives in footer.php on every page, so always load assets on the frontend.
    plgc_wx_enqueue_assets();
} );
