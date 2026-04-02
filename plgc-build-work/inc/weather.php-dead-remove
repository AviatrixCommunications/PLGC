<?php
/**
 * Prairie Landing Golf Club — Weather Module
 *
 * Fetches current conditions from Weatherbit.io, caches for 1 hour,
 * and returns animated inline SVG icons for every Midwest weather scenario.
 *
 * @package PLGC
 */

defined( 'ABSPATH' ) || exit;

// ── Public API ───────────────────────────────────────────────────────────────

/**
 * Get current weather data, served from a 1-hour transient.
 *
 * @return array|null  Keys: icon_code, description, temp, temp_unit,
 *                     wind_speed, wind_dir, is_night. Null if unconfigured.
 */
function plgc_get_weather(): ?array {
    $api_key = plgc_option( 'plgc_weatherbit_api_key' );
    if ( empty( $api_key ) ) return null;

    $cached = get_transient( 'plgc_weather_cache' );
    if ( $cached !== false ) return $cached;

    $lat   = plgc_option( 'plgc_weather_lat', '41.8880' );
    $lon   = plgc_option( 'plgc_weather_lon', '-88.2073' );
    $units = plgc_option( 'plgc_weather_unit', 'I' );

    $url = add_query_arg( [
        'lat'  => floatval( $lat ),
        'lon'  => floatval( $lon ),
        'key'  => $api_key,
        'units'=> $units,
    ], 'https://api.weatherbit.io/v2.0/current' );

    $response = wp_remote_get( $url, [ 'timeout' => 8 ] );

    if ( is_wp_error( $response ) ) return null;

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $data = $body['data'][0] ?? null;
    if ( ! $data ) return null;

    $unit_label = ( $units === 'I' ) ? '°F' : '°C';

    $weather = [
        'icon_code'   => $data['weather']['icon']        ?? 'c01d',
        'description' => $data['weather']['description'] ?? '',
        'temp'        => round( $data['temp'] ?? 0 ),
        'temp_unit'   => $unit_label,
        'wind_speed'  => round( $data['wind_spd'] ?? 0 ),
        'wind_dir'    => $data['wind_cdir_full']         ?? '',
        'is_night'    => str_ends_with( $data['weather']['icon'] ?? 'c01d', 'n' ),
    ];

    set_transient( 'plgc_weather_cache', $weather, HOUR_IN_SECONDS );
    return $weather;
}

/**
 * Render the weather block for the footer.
 * Returns empty string if no API key configured.
 *
 * @return string HTML
 */
function plgc_weather_block(): string {
    $w = plgc_get_weather();
    if ( ! $w ) return '';

    $svg         = plgc_weather_icon( $w['icon_code'], $w['description'] );
    $description = esc_html( ucfirst( strtolower( $w['description'] ) ) );
    $temp        = esc_html( $w['temp'] . $w['temp_unit'] );

    // Encode SVG as a data URI inside <img> — this bypasses every possible
    // theme CSS reset (svg { height:auto }, max-width:100%, etc.) and is
    // immune to WP Engine edge-cache stripping inline styles on SVG elements.
    $data_uri = 'data:image/svg+xml;base64,' . base64_encode( $svg );

    return sprintf(
        '<div class="plgc-footer__weather">
            <span class="plgc-footer__weather-label">Today\'s Golf Weather:</span>
            <img src="%s" alt="" aria-hidden="true" width="40" height="40"
                 class="plgc-footer__weather-icon-img"
                 style="display:inline-block;width:40px;height:40px;flex-shrink:0">
            <span class="plgc-footer__weather-reading">%s, %s</span>
        </div>',
        esc_attr( $data_uri ),
        $description,
        $temp
    );
}

// ── SVG Icon Library ─────────────────────────────────────────────────────────

/**
 * Return an animated inline SVG for a Weatherbit icon code.
 * Covers every Midwest weather scenario: sun, moon, clouds, rain,
 * thunderstorms, snow, sleet, freezing rain, fog, haze, and blizzard.
 *
 * @param  string $code        Weatherbit icon code e.g. "c01d", "t03n"
 * @param  string $description Human-readable description for screen readers.
 * @return string Inline SVG HTML
 */
function plgc_weather_icon( string $code, string $description = 'Weather' ): string {
    $title = esc_html( ucfirst( strtolower( $description ) ) );
    $night = str_ends_with( $code, 'n' );
    $base  = rtrim( $code, 'dn' ); // strip day/night suffix: c01d → c01

    // Map code prefix → icon type
    $type = match( true ) {
        in_array( $base, [ 'c01' ] )               => $night ? 'moon'         : 'sun',
        in_array( $base, [ 'c02' ] )               => $night ? 'moon_cloud'   : 'sun_cloud',
        in_array( $base, [ 'c03' ] )               => $night ? 'moon_cloud'   : 'partly_cloudy',
        in_array( $base, [ 'c04' ] )               => 'overcast',
        in_array( $base, [ 'd01', 'd02', 'd03' ] ) => 'drizzle',
        in_array( $base, [ 'r01', 'r02' ] )        => 'rain_light',
        in_array( $base, [ 'r03' ] )               => 'rain_heavy',
        in_array( $base, [ 'r04', 'r05', 'r06' ] ) => 'shower',
        in_array( $base, [ 'f01' ] )               => 'freezing_rain',
        in_array( $base, [ 's01' ] )               => 'snow_light',
        in_array( $base, [ 's02', 's03' ] )        => 'snow_heavy',
        in_array( $base, [ 's04', 's05' ] )        => 'sleet',
        in_array( $base, [ 's06' ] )               => 'freezing_rain',
        in_array( $base, [ 't01', 't02' ] )        => 'thunder_light',
        in_array( $base, [ 't03', 't04', 't05' ] ) => 'thunder_heavy',
        in_array( $base, [ 'a01', 'a02' ] )        => 'fog',
        in_array( $base, [ 'a03', 'a04', 'a05' ] ) => 'haze',
        in_array( $base, [ 'a06' ] )               => 'smoke',
        default                                    => 'overcast',
    };

    return plgc_build_icon( $type, $title );
}

/**
 * Build the actual SVG markup for a given icon type.
 * All icons: 40×40 viewBox, white/yellow palette for dark footer background.
 */
function plgc_build_icon( string $type, string $title ): string {
    // Shared colour palette
    $sun     = '#FFAE40';  // brand yellow
    $white   = '#FFFFFF';
    $cloud   = '#E8E8E8';
    $cloud_d = '#B8C0CC';  // darker cloud for storms
    $rain    = '#A8C8E8';
    $snow    = '#DDEEFF';
    $moon    = '#F5E6C8';

    $svg_open = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 40 40\" width=\"40\" height=\"40\" style=\"display:block;width:40px;height:40px;min-width:40px;overflow:visible\" role=\"img\" aria-label=\"{$title}\" class=\"plgc-weather-svg plgc-weather-svg--{$type}\">";
    $svg_close = '</svg>';

    switch ( $type ) {

        // ── Clear Day: animated rotating sun ──────────────────────────────
        case 'sun':
            return $svg_open . "
            <circle cx=\"20\" cy=\"20\" r=\"8\" fill=\"{$sun}\"/>
            <g>
                <animateTransform attributeName=\"transform\" type=\"rotate\"
                    from=\"0 20 20\" to=\"360 20 20\" dur=\"12s\" repeatCount=\"indefinite\"/>
                <line x1=\"20\" y1=\"4\"  x2=\"20\" y2=\"8\"  stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"20\" y1=\"32\" x2=\"20\" y2=\"36\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"4\"  y1=\"20\" x2=\"8\"  y2=\"20\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"32\" y1=\"20\" x2=\"36\" y2=\"20\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"8.23\"  y1=\"8.23\"  x2=\"11.05\" y2=\"11.05\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"28.95\" y1=\"28.95\" x2=\"31.77\" y2=\"31.77\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"31.77\" y1=\"8.23\"  x2=\"28.95\" y2=\"11.05\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
                <line x1=\"11.05\" y1=\"28.95\" x2=\"8.23\"  y2=\"31.77\" stroke=\"{$sun}\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>
            </g>" . $svg_close;

        // ── Clear Night: crescent moon + stars ────────────────────────────
        case 'moon':
            return $svg_open . "
            <g class=\"plgc-wi-moon\">
                <path d=\"M24 8 A12 12 0 1 0 24 32 A8 8 0 1 1 24 8 Z\" fill=\"{$moon}\"/>
                <circle cx=\"28\" cy=\"10\" r=\"1\" fill=\"{$white}\" class=\"plgc-wi-star\"/>
                <circle cx=\"33\" cy=\"16\" r=\"1.5\" fill=\"{$white}\" class=\"plgc-wi-star plgc-wi-star--2\"/>
                <circle cx=\"31\" cy=\"6\" r=\"1\" fill=\"{$white}\" class=\"plgc-wi-star plgc-wi-star--3\"/>
            </g>" . $svg_close;

        // ── Sun + Cloud ───────────────────────────────────────────────────
        case 'sun_cloud':
            return $svg_open . "
            <g>
                <animateTransform attributeName=\"transform\" type=\"rotate\"
                    from=\"0 14 14\" to=\"360 14 14\" dur=\"10s\" repeatCount=\"indefinite\"/>
                <circle cx=\"14\" cy=\"14\" r=\"5.5\" fill=\"{$sun}\"/>
                <line x1=\"14\" y1=\"5\"  x2=\"14\" y2=\"8\"  stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                <line x1=\"14\" y1=\"20\" x2=\"14\" y2=\"23\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                <line x1=\"5\"  y1=\"14\" x2=\"8\"  y2=\"14\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                <line x1=\"20\" y1=\"14\" x2=\"23\" y2=\"14\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                <line x1=\"7.64\" y1=\"7.64\" x2=\"9.77\" y2=\"9.77\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                <line x1=\"18.23\" y1=\"18.23\" x2=\"20.36\" y2=\"20.36\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
            </g>
            <path d=\"M10 28 Q10 22 16 22 Q17 18 22 18 Q28 18 28 23 Q32 23 32 28 Z\" fill=\"{$cloud}\">
                <animateTransform attributeName=\"transform\" type=\"translate\"
                    values=\"0,0; 0,-2; 0,0\" dur=\"4s\" repeatCount=\"indefinite\"/>
            </path>" . $svg_close;

        // ── Moon + Cloud ──────────────────────────────────────────────────
        case 'moon_cloud':
            return $svg_open . "
            <g class=\"plgc-wi-moon-cloud\">
                <path d=\"M16 8 A8 8 0 1 0 16 22 A5 5 0 1 1 16 8 Z\" fill=\"{$moon}\" opacity=\"0.9\"/>
                <g class=\"plgc-wi-cloud-float\">
                    <path d=\"M10 28 Q10 22 16 22 Q17 18 22 18 Q28 18 28 23 Q32 23 32 28 Z\" fill=\"{$cloud}\"/>
                </g>
            </g>" . $svg_close;

        // ── Partly Cloudy (day) ───────────────────────────────────────────
        case 'partly_cloudy':
            return $svg_open . "
            <g class=\"plgc-wi-partly-cloudy\">
                <g class=\"plgc-wi-rays-sm\" transform-origin=\"13 13\">
                    <circle cx=\"13\" cy=\"13\" r=\"5\" fill=\"{$sun}\"/>
                    <line x1=\"13\" y1=\"5\" x2=\"13\" y2=\"7.5\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                    <line x1=\"13\" y1=\"18.5\" x2=\"13\" y2=\"21\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                    <line x1=\"5\" y1=\"13\" x2=\"7.5\" y2=\"13\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                    <line x1=\"18.5\" y1=\"13\" x2=\"21\" y2=\"13\" stroke=\"{$sun}\" stroke-width=\"2\" stroke-linecap=\"round\"/>
                </g>
                <g class=\"plgc-wi-cloud-float\">
                    <path d=\"M8 30 Q8 23 15 23 Q16 19 22 19 Q30 19 30 25 Q35 25 35 30 Z\" fill=\"{$cloud}\"/>
                </g>
            </g>" . $svg_close;

        // ── Overcast ──────────────────────────────────────────────────────
        case 'overcast':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M6 26 Q6 19 13 19 Q14 14 21 14 Q30 14 30 20 Q36 20 36 26 Z\" fill=\"{$cloud}\"/>
                <path d=\"M10 32 Q10 27 15 27 Q15.5 24 19 24 Q25 24 25 28 Q29 28 29 32 Z\" fill=\"{$cloud_d}\" opacity=\"0.6\"/>
            </g>" . $svg_close;

        // ── Drizzle ───────────────────────────────────────────────────────
        case 'drizzle':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M6 22 Q6 15 13 15 Q14 10 21 10 Q30 10 30 16 Q36 16 36 22 Z\" fill=\"{$cloud}\"/>
            </g>
            <g class=\"plgc-wi-rain\">
                <line x1=\"13\" y1=\"26\" x2=\"11\" y2=\"32\" stroke=\"{$rain}\" stroke-width=\"1.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"20\" y1=\"26\" x2=\"18\" y2=\"32\" stroke=\"{$rain}\" stroke-width=\"1.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--2\"/>
                <line x1=\"27\" y1=\"26\" x2=\"25\" y2=\"32\" stroke=\"{$rain}\" stroke-width=\"1.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
            </g>" . $svg_close;

        // ── Light Rain ────────────────────────────────────────────────────
        case 'rain_light':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M5 20 Q5 13 12 13 Q13 8 20 8 Q29 8 29 14 Q35 14 35 20 Z\" fill=\"{$cloud}\"/>
            </g>
            <g class=\"plgc-wi-rain\">
                <line x1=\"11\" y1=\"24\" x2=\"9\"  y2=\"31\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"18\" y1=\"24\" x2=\"16\" y2=\"31\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--2\"/>
                <line x1=\"25\" y1=\"24\" x2=\"23\" y2=\"31\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
                <line x1=\"14\" y1=\"29\" x2=\"12\" y2=\"36\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--4\"/>
                <line x1=\"22\" y1=\"29\" x2=\"20\" y2=\"36\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--5\"/>
            </g>" . $svg_close;

        // ── Heavy Rain ────────────────────────────────────────────────────
        case 'rain_heavy':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M4 19 Q4 12 11 12 Q12 7 19 7 Q28 7 28 13 Q34 13 34 19 Z\" fill=\"{$cloud_d}\"/>
            </g>
            <g class=\"plgc-wi-rain\">
                <line x1=\"10\" y1=\"23\" x2=\"7\"  y2=\"32\" stroke=\"{$rain}\" stroke-width=\"2.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"17\" y1=\"23\" x2=\"14\" y2=\"32\" stroke=\"{$rain}\" stroke-width=\"2.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--2\"/>
                <line x1=\"24\" y1=\"23\" x2=\"21\" y2=\"32\" stroke=\"{$rain}\" stroke-width=\"2.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
                <line x1=\"13\" y1=\"30\" x2=\"10\" y2=\"39\" stroke=\"{$rain}\" stroke-width=\"2.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--4\"/>
                <line x1=\"21\" y1=\"30\" x2=\"18\" y2=\"39\" stroke=\"{$rain}\" stroke-width=\"2.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--5\"/>
                <line x1=\"29\" y1=\"30\" x2=\"26\" y2=\"39\" stroke=\"{$rain}\" stroke-width=\"2.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--6\"/>
            </g>" . $svg_close;

        // ── Shower ────────────────────────────────────────────────────────
        case 'shower':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <circle cx=\"20\" cy=\"13\" r=\"7\" fill=\"{$cloud}\"/>
                <circle cx=\"12\" cy=\"16\" r=\"5\" fill=\"{$cloud}\"/>
                <circle cx=\"28\" cy=\"16\" r=\"5\" fill=\"{$cloud}\"/>
                <rect x=\"7\" y=\"16\" width=\"26\" height=\"6\" fill=\"{$cloud}\"/>
            </g>
            <g class=\"plgc-wi-rain\">
                <line x1=\"13\" y1=\"25\" x2=\"11\" y2=\"33\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"20\" y1=\"25\" x2=\"18\" y2=\"33\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--2\"/>
                <line x1=\"27\" y1=\"25\" x2=\"25\" y2=\"33\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
            </g>" . $svg_close;

        // ── Freezing Rain / Ice ───────────────────────────────────────────
        case 'freezing_rain':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M5 19 Q5 12 12 12 Q13 7 20 7 Q29 7 29 13 Q35 13 35 19 Z\" fill=\"{$cloud_d}\"/>
            </g>
            <g class=\"plgc-wi-sleet\">
                <circle cx=\"12\" cy=\"27\" r=\"2\" fill=\"{$snow}\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"18\" y1=\"24\" x2=\"16\" y2=\"32\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--2\"/>
                <circle cx=\"24\" cy=\"27\" r=\"2\" fill=\"{$snow}\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
                <line x1=\"13\" y1=\"31\" x2=\"11\" y2=\"38\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--4\"/>
                <circle cx=\"29\" cy=\"33\" r=\"2\" fill=\"{$snow}\" class=\"plgc-wi-drop plgc-wi-drop--5\"/>
            </g>" . $svg_close;

        // ── Light Snow ────────────────────────────────────────────────────
        case 'snow_light':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M5 19 Q5 12 12 12 Q13 7 20 7 Q29 7 29 13 Q35 13 35 19 Z\" fill=\"{$cloud}\"/>
            </g>
            <g class=\"plgc-wi-snow\">
                " . plgc_snowflake( 12, 27, 'plgc-wi-flake--1' ) . "
                " . plgc_snowflake( 20, 30, 'plgc-wi-flake--2' ) . "
                " . plgc_snowflake( 28, 27, 'plgc-wi-flake--3' ) . "
            </g>" . $svg_close;

        // ── Heavy Snow / Blizzard ─────────────────────────────────────────
        case 'snow_heavy':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M4 18 Q4 11 11 11 Q12 6 19 6 Q28 6 28 12 Q34 12 34 18 Z\" fill=\"{$cloud_d}\"/>
            </g>
            <g class=\"plgc-wi-snow\">
                " . plgc_snowflake( 10, 24, 'plgc-wi-flake--1' ) . "
                " . plgc_snowflake( 20, 27, 'plgc-wi-flake--2' ) . "
                " . plgc_snowflake( 30, 24, 'plgc-wi-flake--3' ) . "
                " . plgc_snowflake( 15, 33, 'plgc-wi-flake--4' ) . "
                " . plgc_snowflake( 25, 33, 'plgc-wi-flake--5' ) . "
            </g>" . $svg_close;

        // ── Sleet / Mix ───────────────────────────────────────────────────
        case 'sleet':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M5 19 Q5 12 12 12 Q13 7 20 7 Q29 7 29 13 Q35 13 35 19 Z\" fill=\"{$cloud_d}\"/>
            </g>
            <g class=\"plgc-wi-sleet\">
                <line x1=\"12\" y1=\"23\" x2=\"10\" y2=\"30\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                " . plgc_snowflake( 20, 27, 'plgc-wi-flake--2' ) . "
                <line x1=\"28\" y1=\"23\" x2=\"26\" y2=\"30\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
                " . plgc_snowflake( 14, 34, 'plgc-wi-flake--4' ) . "
                <line x1=\"24\" y1=\"31\" x2=\"22\" y2=\"38\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--5\"/>
            </g>" . $svg_close;

        // ── Thunderstorm (light) ──────────────────────────────────────────
        case 'thunder_light':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M5 19 Q5 12 12 12 Q13 7 20 7 Q29 7 29 13 Q35 13 35 19 Z\" fill=\"{$cloud}\"/>
            </g>
            <g class=\"plgc-wi-lightning\">
                <polygon points=\"22,20 17,28 21,28 18,38 26,26 22,26\" fill=\"{$sun}\"/>
            </g>
            <g class=\"plgc-wi-rain\">
                <line x1=\"10\" y1=\"22\" x2=\"8\"  y2=\"28\" stroke=\"{$rain}\" stroke-width=\"1.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"31\" y1=\"22\" x2=\"29\" y2=\"28\" stroke=\"{$rain}\" stroke-width=\"1.5\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
            </g>" . $svg_close;

        // ── Thunderstorm (heavy/severe) ───────────────────────────────────
        case 'thunder_heavy':
            return $svg_open . "
            <g class=\"plgc-wi-cloud-float\">
                <path d=\"M4 18 Q4 11 11 11 Q12 6 19 6 Q28 6 28 12 Q34 12 34 18 Z\" fill=\"{$cloud_d}\"/>
            </g>
            <g class=\"plgc-wi-lightning\">
                <polygon points=\"23,19 16,29 21,29 17,40 28,26 23,26\" fill=\"{$sun}\"/>
            </g>
            <g class=\"plgc-wi-rain\">
                <line x1=\"8\"  y1=\"21\" x2=\"5\"  y2=\"30\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--1\"/>
                <line x1=\"32\" y1=\"21\" x2=\"29\" y2=\"30\" stroke=\"{$rain}\" stroke-width=\"2\" stroke-linecap=\"round\" class=\"plgc-wi-drop plgc-wi-drop--3\"/>
            </g>" . $svg_close;

        // ── Fog ───────────────────────────────────────────────────────────
        case 'fog':
            return $svg_open . "
            <g class=\"plgc-wi-fog\">
                <line x1=\"4\"  y1=\"12\" x2=\"36\" y2=\"12\" stroke=\"{$white}\" stroke-width=\"2.5\" stroke-linecap=\"round\" opacity=\"0.9\"/>
                <line x1=\"8\"  y1=\"18\" x2=\"32\" y2=\"18\" stroke=\"{$white}\" stroke-width=\"2.5\" stroke-linecap=\"round\" opacity=\"0.7\"/>
                <line x1=\"4\"  y1=\"24\" x2=\"36\" y2=\"24\" stroke=\"{$white}\" stroke-width=\"2.5\" stroke-linecap=\"round\" opacity=\"0.5\"/>
                <line x1=\"10\" y1=\"30\" x2=\"30\" y2=\"30\" stroke=\"{$white}\" stroke-width=\"2.5\" stroke-linecap=\"round\" opacity=\"0.3\"/>
            </g>" . $svg_close;

        // ── Haze / Smoke ──────────────────────────────────────────────────
        case 'haze':
        case 'smoke':
            return $svg_open . "
            <g class=\"plgc-wi-fog\">
                <path d=\"M6 14 Q10 11 14 14 Q18 17 22 14 Q26 11 30 14 Q34 17 36 14\" fill=\"none\" stroke=\"{$white}\" stroke-width=\"2\" stroke-linecap=\"round\" opacity=\"0.6\"/>
                <path d=\"M4 21 Q8 18 12 21 Q16 24 20 21 Q24 18 28 21 Q32 24 36 21\" fill=\"none\" stroke=\"{$white}\" stroke-width=\"2\" stroke-linecap=\"round\" opacity=\"0.4\"/>
                <path d=\"M6 28 Q10 25 14 28 Q18 31 22 28 Q26 25 30 28 Q34 31 36 28\" fill=\"none\" stroke=\"{$white}\" stroke-width=\"2\" stroke-linecap=\"round\" opacity=\"0.3\"/>
            </g>" . $svg_close;

        // ── Fallback ──────────────────────────────────────────────────────
        default:
            return plgc_build_icon( 'overcast', $title );
    }
}

/**
 * Helper: generate a small snowflake SVG group centered at (cx, cy).
 */
function plgc_snowflake( float $cx, float $cy, string $cls ): string {
    $snow = '#DDEEFF';
    $r    = 3.5; // half-length of each arm
    return "<g class=\"plgc-wi-flake {$cls}\" transform-origin=\"{$cx} {$cy}\">
        <line x1=\"" . ($cx)    . "\" y1=\"" . ($cy-$r) . "\" x2=\"" . ($cx)    . "\" y2=\"" . ($cy+$r) . "\" stroke=\"{$snow}\" stroke-width=\"1.5\" stroke-linecap=\"round\"/>
        <line x1=\"" . ($cx-$r) . "\" y1=\"" . ($cy)    . "\" x2=\"" . ($cx+$r) . "\" y2=\"" . ($cy)    . "\" stroke=\"{$snow}\" stroke-width=\"1.5\" stroke-linecap=\"round\"/>
        <line x1=\"" . ($cx-2.5) . "\" y1=\"" . ($cy-2.5) . "\" x2=\"" . ($cx+2.5) . "\" y2=\"" . ($cy+2.5) . "\" stroke=\"{$snow}\" stroke-width=\"1.5\" stroke-linecap=\"round\"/>
        <line x1=\"" . ($cx+2.5) . "\" y1=\"" . ($cy-2.5) . "\" x2=\"" . ($cx-2.5) . "\" y2=\"" . ($cy+2.5) . "\" stroke=\"{$snow}\" stroke-width=\"1.5\" stroke-linecap=\"round\"/>
    </g>";
}


