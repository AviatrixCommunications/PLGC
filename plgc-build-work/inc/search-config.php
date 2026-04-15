<?php
/**
 * PLGC Search Configuration
 *
 * Enhances WordPress search to handle punctuation-insensitive queries.
 * "mothers day" will match "Mother's Day" by stripping apostrophes
 * (and curly quote variants) from both the search term and the database
 * column values during the SQL comparison.
 *
 * Works with:
 *   - WP native search (WP_Query LIKE)
 *   - WP REST /wp/v2/search endpoint (used by our live search in nav.js)
 *   - WP Engine Smart Search / Elasticsearch (passes through WP_Query)
 *
 * @package PLGC
 * @since   1.7.55
 */

defined( 'ABSPATH' ) || exit;


/**
 * Augment the search SQL WHERE clause to match content even when the
 * user omits punctuation (apostrophes, curly quotes, backticks).
 *
 * How it works:
 *   WordPress's built-in search generates:
 *     AND ((post_title LIKE '%mothers%') OR (post_content LIKE '%mothers%'))
 *
 *   This filter appends an OR block that wraps the column in REPLACE()
 *   to strip apostrophes before comparing:
 *     OR (REPLACE(REPLACE(REPLACE(post_title, CHAR(39),''), CHAR(8217),''), CHAR(8216),'')
 *         LIKE '%mothers%')
 *
 *   So "Mother's" → "Mothers" in the comparison, and LIKE '%mothers%' matches.
 *
 * Performance note:
 *   REPLACE() on the column prevents MySQL from using an index on that column.
 *   This is acceptable for site search on a site this size — we're talking
 *   hundreds of posts, not millions. The original indexed search conditions
 *   remain as the primary path; this OR is a fallback for punctuation mismatches.
 *
 * @param string   $search   The search SQL fragment (starts with " AND (...)").
 * @param WP_Query $wp_query The current query object.
 * @return string  Modified search SQL.
 */
add_filter( 'posts_search', 'plgc_search_normalize_punctuation', 100, 2 );

function plgc_search_normalize_punctuation( string $search, WP_Query $wp_query ): string {

	// Only act on actual search queries with a search term
	if ( ! $wp_query->is_search() || empty( $wp_query->query_vars['s'] ) ) {
		return $search;
	}

	// Don't modify admin searches — only front-end and REST API
	if ( is_admin() && ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
		return $search;
	}

	// If the original search clause is empty, nothing to augment
	if ( empty( trim( $search ) ) ) {
		return $search;
	}

	global $wpdb;

	$raw_search = $wp_query->query_vars['s'];

	// Normalize the search term: strip apostrophe variants
	// CHAR(39)   = ' (ASCII apostrophe / single quote)
	// CHAR(8217) = ' (right single quotation mark, U+2019)
	// CHAR(8216) = ' (left single quotation mark, U+2018)
	// CHAR(96)   = ` (backtick / grave accent)
	$punct_chars = [ "'", "\u{2019}", "\u{2018}", '`' ];
	$normalized  = str_replace( $punct_chars, '', $raw_search );

	// Split into individual search terms (same way WordPress does)
	$terms = array_filter( array_map( 'trim', explode( ' ', $normalized ) ) );

	if ( empty( $terms ) ) {
		return $search;
	}

	// Build SQL expressions that strip apostrophes from the DB columns.
	// Using CHAR() avoids any SQL string escaping issues with literal quotes.
	$strip_sql = "REPLACE(REPLACE(REPLACE(REPLACE(%s, CHAR(39), ''), CHAR(8217), ''), CHAR(8216), ''), CHAR(96), '')";
	$title_clean   = sprintf( $strip_sql, "{$wpdb->posts}.post_title" );
	$content_clean = sprintf( $strip_sql, "{$wpdb->posts}.post_content" );
	$excerpt_clean = sprintf( $strip_sql, "{$wpdb->posts}.post_excerpt" );

	// Build a condition for each term: all terms must appear (AND)
	$term_conditions = [];
	foreach ( $terms as $term ) {
		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$term_conditions[] = $wpdb->prepare(
			"({$title_clean} LIKE %s OR {$content_clean} LIKE %s OR {$excerpt_clean} LIKE %s)",
			$like,
			$like,
			$like
		);
	}

	$extra = '(' . implode( ' AND ', $term_conditions ) . ')';

	// Append as OR to the existing search clause.
	// WordPress search SQL format: " AND ((conditions))"
	// We wrap: " AND ((original conditions) OR (normalized conditions))"
	$trimmed = preg_replace( '/^\s*AND\s+/i', '', $search, 1 );
	if ( ! empty( $trimmed ) ) {
		$search = " AND ({$trimmed} OR {$extra})";
	}

	return $search;
}


/**
 * Boost title matches to the top of search results.
 *
 * WordPress's default search treats title and content matches equally
 * and sorts by date. With per_page: 10 in the REST API, a search for
 * "mothers day" can bury the "Mother's Day Brunch" event because dozens
 * of pages contain the word "day" in their content and are newer.
 *
 * This filter prepends ORDER BY clauses that score:
 *   1. Exact phrase match in title (highest)
 *   2. Normalized phrase match in title (apostrophe-stripped)
 *   3. All search terms present in title
 *   4. Default WordPress ordering (fallback)
 *
 * Uses REPLACE(col, CHAR(39), '') to normalize titles at query time,
 * matching the approach in plgc_search_normalize_punctuation() above.
 *
 * @param string   $orderby  The current ORDER BY clause for search.
 * @param WP_Query $wp_query The query object.
 * @return string  Modified ORDER BY clause.
 */
add_filter( 'posts_search_orderby', 'plgc_search_boost_title_matches', 10, 2 );

function plgc_search_boost_title_matches( string $orderby, WP_Query $wp_query ): string {

	if ( ! $wp_query->is_search() || empty( $wp_query->query_vars['s'] ) ) {
		return $orderby;
	}

	// Same admin guard as the search filter
	if ( is_admin() && ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
		return $orderby;
	}

	global $wpdb;

	$raw = $wp_query->query_vars['s'];

	// Normalize: strip apostrophe variants
	$punct      = [ "'", "\u{2019}", "\u{2018}", '`' ];
	$normalized = str_replace( $punct, '', $raw );

	// SQL expression for an apostrophe-stripped title
	$strip_sql   = "REPLACE(REPLACE(REPLACE(REPLACE(%s, CHAR(39), ''), CHAR(8217), ''), CHAR(8216), ''), CHAR(96), '')";
	$title_clean = sprintf( $strip_sql, "{$wpdb->posts}.post_title" );

	// Phrase match LIKE patterns
	$raw_like  = '%' . $wpdb->esc_like( $raw ) . '%';
	$norm_like = '%' . $wpdb->esc_like( $normalized ) . '%';

	// Score 1: exact phrase in title (e.g. "mother's day" in "Mother's Day Brunch")
	$score_exact = $wpdb->prepare(
		"({$wpdb->posts}.post_title LIKE %s)",
		$raw_like
	);

	// Score 2: normalized phrase in title (e.g. "mothers day" matches "Mother's Day")
	$score_norm = $wpdb->prepare(
		"({$title_clean} LIKE %s)",
		$norm_like
	);

	// Score 3: all individual terms present in title (broader match)
	$terms       = array_filter( array_map( 'trim', explode( ' ', $normalized ) ) );
	$term_checks = [];
	foreach ( $terms as $t ) {
		$term_checks[] = $wpdb->prepare(
			"({$title_clean} LIKE %s)",
			'%' . $wpdb->esc_like( $t ) . '%'
		);
	}
	// All terms in title = 1, otherwise 0
	$score_terms = count( $term_checks ) > 1
		? '(' . implode( ' AND ', $term_checks ) . ')'
		: ( $term_checks[0] ?? '0' );

	// Build the ORDER BY: highest score first, then fall back to default
	$boost = "{$score_exact} DESC, {$score_norm} DESC, {$score_terms} DESC";

	if ( ! empty( $orderby ) ) {
		return "{$boost}, {$orderby}";
	}

	return $boost;
}
