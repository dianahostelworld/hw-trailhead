<?php
/**
 * Search Console data, read from what Content Health's weekly sync stores.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * A read-only bridge to Content Health's tables:
 * - metrics: per article, clicks/impressions/position over the data window
 *   (12 months by default), clicks in the last 90 days and the 90 before.
 * - queries: per article and search, the last 90 days.
 */
class TH_Data {

	/**
	 * Whether Content Health is installed and has synced at least once.
	 *
	 * @return bool
	 */
	public static function ready() {
		return class_exists( 'HWCH_Store' ) && HWCH_Store::has_data();
	}

	/**
	 * When Content Health last synced.
	 *
	 * @return string|null MySQL GMT datetime.
	 */
	public static function last_sync() {
		return self::ready() ? get_option( 'hwch_last_sync' ) : null;
	}

	/**
	 * Months in the main data window.
	 *
	 * @return int
	 */
	public static function window_months() {
		return class_exists( 'HWCH_Settings' ) ? (int) HWCH_Settings::get( 'window_months' ) : 12;
	}

	/**
	 * Every published English article with its metrics, keyed by post ID.
	 * Articles Content Health has no row for get zeros.
	 *
	 * @return array[]
	 */
	public static function articles() {
		global $wpdb;
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$ids = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		if ( class_exists( 'HWCH_Reports' ) ) {
			$ids = HWCH_Reports::english_ids( $ids );
		}

		$metrics = array();
		if ( self::ready() ) {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . HWCH_Store::table( 'metrics' ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $rows as $r ) {
				$metrics[ (int) $r['post_id'] ] = $r;
			}
		}

		$top = self::top_queries();
		$out = array();
		foreach ( $ids as $id ) {
			$m          = isset( $metrics[ $id ] ) ? $metrics[ $id ] : array();
			$impr       = (int) ( $m['impressions'] ?? 0 );
			$clicks     = (int) ( $m['clicks'] ?? 0 );
			$out[ $id ] = array(
				'id'          => (int) $id,
				'clicks'      => $clicks,
				'impressions' => $impr,
				'ctr'         => $impr ? round( $clicks / $impr * 100, 2 ) : null,
				'position'    => isset( $m['position'] ) && (float) $m['position'] > 0 ? (float) $m['position'] : null,
				'recent'      => (int) ( $m['clicks_recent'] ?? 0 ),
				'prior'       => (int) ( $m['clicks_prior'] ?? 0 ),
				'top'         => isset( $top[ $id ] ) ? $top[ $id ] : null,
			);
		}

		$cache = $out;
		return $out;
	}

	/**
	 * Each article's search with the most impressions in the last 90 days.
	 *
	 * @return array post ID => array( query, impressions, position ).
	 */
	public static function top_queries() {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		$cached = get_transient( 'th_top_queries' );
		if ( is_array( $cached ) && ( $cached['sync'] ?? '' ) === self::last_sync() ) {
			return $cached['rows'];
		}
		$rows = $wpdb->get_results( 'SELECT post_id, query, impressions, position FROM ' . HWCH_Store::table( 'queries' ) . ' ORDER BY impressions DESC', ARRAY_A ); // phpcs:ignore WordPress.DB
		$top  = array();
		foreach ( (array) $rows as $r ) {
			$id = (int) $r['post_id'];
			if ( ! isset( $top[ $id ] ) ) {
				$top[ $id ] = array( 'query' => $r['query'], 'impressions' => (int) $r['impressions'], 'position' => (float) $r['position'] );
			}
		}
		set_transient( 'th_top_queries', array( 'sync' => self::last_sync(), 'rows' => $top ), DAY_IN_SECONDS );
		return $top;
	}

	/**
	 * One article's searches, last 90 days.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Rows.
	 * @return array[]
	 */
	public static function queries( $post_id, $limit = 80 ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT query, clicks, impressions, position FROM ' . HWCH_Store::table( 'queries' ) . ' WHERE post_id = %d ORDER BY impressions DESC LIMIT %d', $post_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Searches containing a phrase, with the English article that ranks for
	 * them. For research tasks, so Claude knows what we already cover.
	 *
	 * @param string $phrase Phrase.
	 * @param int    $limit  Rows.
	 * @return array[]
	 */
	public static function rankings_for( $phrase, $limit = 60 ) {
		global $wpdb;
		if ( ! self::ready() || '' === trim( $phrase ) ) {
			return array();
		}
		$english = self::articles();
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, query, clicks, impressions, position FROM ' . HWCH_Store::table( 'queries' ) . ' WHERE query LIKE %s ORDER BY impressions DESC LIMIT 400', '%' . $wpdb->esc_like( strtolower( $phrase ) ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out     = array();
		foreach ( (array) $rows as $r ) {
			if ( ! isset( $english[ (int) $r['post_id'] ] ) ) {
				continue;
			}
			$out[] = array(
				'search'      => $r['query'],
				'url'         => get_permalink( (int) $r['post_id'] ),
				'clicks'      => (int) $r['clicks'],
				'impressions' => (int) $r['impressions'],
				'position'    => round( (float) $r['position'], 1 ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Typical click-through for a position, in percent.
	 *
	 * @param float $p Position.
	 * @return float
	 */
	public static function expected_ctr( $p ) {
		if ( $p <= 1.5 ) {
			return 25;
		}
		if ( $p <= 2.5 ) {
			return 15;
		}
		if ( $p <= 3.5 ) {
			return 10;
		}
		if ( $p <= 5 ) {
			return 6;
		}
		if ( $p <= 7 ) {
			return 4;
		}
		return 2.5;
	}

	/**
	 * The opportunities dashboard.
	 *
	 * @return array quick, ctr, losing lists.
	 */
	public static function opportunities() {
		global $wpdb;
		$out = array( 'quick' => array(), 'ctr' => array(), 'losing' => array() );
		if ( ! self::ready() ) {
			return $out;
		}
		$articles = self::articles();

		// Almost on page one: searches where an English article sits 4th-15th.
		$rows = $wpdb->get_results( 'SELECT post_id, query, clicks, impressions, position FROM ' . HWCH_Store::table( 'queries' ) . ' WHERE position >= 4 AND position <= 15 AND impressions >= 100 ORDER BY impressions DESC LIMIT 600', ARRAY_A ); // phpcs:ignore WordPress.DB
		$seen = array();
		foreach ( (array) $rows as $r ) {
			$id = (int) $r['post_id'];
			if ( ! isset( $articles[ $id ] ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ]    = true;
			$out['quick'][] = array( 'post_id' => $id, 'search' => $r['query'], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => (float) $r['position'] );
			if ( count( $out['quick'] ) >= 30 ) {
				break;
			}
		}

		foreach ( $articles as $a ) {
			if ( $a['impressions'] >= 1000 && $a['position'] && $a['position'] <= 10 && null !== $a['ctr'] && $a['ctr'] < self::expected_ctr( $a['position'] ) / 2 ) {
				$out['ctr'][] = $a + array( 'expected' => self::expected_ctr( $a['position'] ) );
			}
			if ( $a['prior'] >= 30 && $a['recent'] <= $a['prior'] * 0.75 ) {
				$out['losing'][] = $a + array( 'change' => (int) round( ( $a['recent'] - $a['prior'] ) / $a['prior'] * 100 ) );
			}
		}
		usort( $out['ctr'], function ( $x, $y ) { return $y['impressions'] - $x['impressions']; } );
		usort( $out['losing'], function ( $x, $y ) { return ( $y['prior'] - $y['recent'] ) - ( $x['prior'] - $x['recent'] ); } );
		$out['ctr']    = array_slice( $out['ctr'], 0, 30 );
		$out['losing'] = array_slice( $out['losing'], 0, 30 );
		return $out;
	}
}
