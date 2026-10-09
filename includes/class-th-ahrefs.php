<?php
/**
 * Ahrefs API v3, called from WordPress with the editor's own key, so Claude
 * gets the data ready-made and only does the thinking.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small Ahrefs client. Every run records how many API units it used.
 */
class TH_Ahrefs {

	const API = 'https://api.ahrefs.com/v3/';

	/**
	 * Is there a key?
	 *
	 * @return bool
	 */
	public static function ready() {
		return '' !== (string) TH_Settings::get( 'ahrefs_key' );
	}

	/**
	 * One API call. Answers are cached for 12 hours.
	 *
	 * @param string $endpoint Endpoint, e.g. keywords-explorer/matching-terms.
	 * @param array  $params   Query parameters.
	 * @param bool   $cache    Use the cache.
	 * @return array|WP_Error
	 */
	public static function call( $endpoint, $params = array(), $cache = true ) {
		if ( ! self::ready() ) {
			return new WP_Error( 'th_no_key', __( 'No Ahrefs API key in Settings.', 'hw-trailhead' ) );
		}
		$url = add_query_arg( array_map( 'rawurlencode', $params + array( 'output' => 'json' ) ), self::API . $endpoint );
		$ck  = 'th_ah_' . md5( $url );
		if ( $cache ) {
			$hit = get_transient( $ck );
			if ( is_array( $hit ) ) {
				return $hit;
			}
		}
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
				'headers' => array( 'Authorization' => 'Bearer ' . TH_Settings::get( 'ahrefs_key' ), 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			$msg = is_array( $body ) && isset( $body['error'] ) ? ( is_string( $body['error'] ) ? $body['error'] : wp_json_encode( $body['error'] ) ) : 'HTTP ' . $code;
			if ( 401 === $code || 403 === $code ) {
				$msg = __( 'Ahrefs didn\'t accept the API key. Check it in Settings.', 'hw-trailhead' ) . ' (' . $msg . ')';
			}
			return new WP_Error( 'th_ahrefs', $msg );
		}
		if ( $cache ) {
			set_transient( $ck, $body, 12 * HOUR_IN_SECONDS );
		}
		return $body;
	}

	/**
	 * Units used by this key this month, or null.
	 *
	 * @return int|null
	 */
	public static function units_used() {
		$r = self::call( 'subscription-info/limits-and-usage', array(), false );
		return is_wp_error( $r ) ? null : (int) ( $r['limits_and_usage']['units_usage_api_key'] ?? 0 );
	}

	/**
	 * Plan limits, for Settings.
	 *
	 * @return array|WP_Error
	 */
	public static function limits() {
		$r = self::call( 'subscription-info/limits-and-usage', array(), false );
		return is_wp_error( $r ) ? $r : (array) ( $r['limits_and_usage'] ?? array() );
	}

	/**
	 * Run some calls and log the units they used.
	 *
	 * @param string   $label What this run was.
	 * @param callable $fn    Does the calls; returns data or WP_Error.
	 * @return mixed
	 */
	public static function run( $label, $fn ) {
		$before = self::units_used();
		$out    = $fn();
		$after  = null === $before ? null : self::units_used();
		$log    = (array) get_option( 'th_ahrefs_log', array() );
		array_unshift(
			$log,
			array(
				'at'    => current_time( 'mysql', true ),
				'label' => $label,
				'units' => null === $after ? null : max( 0, $after - $before ),
				'error' => is_wp_error( $out ) ? $out->get_error_message() : '',
			)
		);
		update_option( 'th_ahrefs_log', array_slice( $log, 0, 15 ), false );
		return $out;
	}

	/**
	 * Keyword ideas containing a seed.
	 *
	 * @param string $seed    Seed.
	 * @param string $country Country.
	 * @param int    $limit   Rows.
	 * @return array|WP_Error
	 */
	public static function matching_terms( $seed, $country, $limit ) {
		$r = self::call(
			'keywords-explorer/matching-terms',
			array(
				'keywords' => $seed,
				'country'  => strtolower( $country ),
				'select'   => 'keyword,volume,difficulty,traffic_potential,parent_topic',
				'order_by' => 'volume:desc',
				'limit'    => (int) $limit,
			)
		);
		return is_wp_error( $r ) ? $r : (array) ( $r['keywords'] ?? array() );
	}

	/**
	 * What a competitor ranks top 10 for.
	 *
	 * @param string $domain  Domain.
	 * @param string $country Country.
	 * @param array  $where   Extra filters.
	 * @param int    $limit   Rows.
	 * @return array|WP_Error
	 */
	public static function competitor_keywords( $domain, $country, $where, $limit ) {
		$and = array_merge( array( array( 'field' => 'best_position', 'is' => array( 'lte', 10 ) ) ), $where );
		$r   = self::call(
			'site-explorer/organic-keywords',
			array(
				'target'   => $domain,
				'mode'     => 'subdomains',
				'date'     => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ),
				'country'  => strtolower( $country ),
				'select'   => 'keyword,volume,keyword_difficulty,best_position,best_position_url,sum_traffic',
				'where'    => wp_json_encode( array( 'and' => $and ) ),
				'order_by' => 'sum_traffic:desc',
				'limit'    => (int) $limit,
			)
		);
		return is_wp_error( $r ) ? $r : (array) ( $r['keywords'] ?? array() );
	}

	/**
	 * The top 10 organic results for a keyword.
	 *
	 * @param string $keyword Keyword.
	 * @param string $country Country.
	 * @return array|WP_Error
	 */
	public static function serp( $keyword, $country ) {
		$r = self::call(
			'serp-overview/serp-overview',
			array(
				'keyword'       => $keyword,
				'country'       => strtolower( $country ),
				'top_positions' => 10,
				'select'        => 'position,url,title,domain_rating,traffic,top_keyword,type',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$out = array();
		foreach ( (array) ( $r['positions'] ?? array() ) as $p ) {
			if ( in_array( 'organic', (array) ( $p['type'] ?? array() ), true ) && ! empty( $p['url'] ) ) {
				unset( $p['type'] );
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * Research a set of seeds: keyword ideas plus what competitors rank for.
	 *
	 * @param string[] $seeds Seeds.
	 * @return array|WP_Error keywords, competitors.
	 */
	public static function research( $seeds ) {
		$country = TH_Settings::get( 'country' );
		$limit   = (int) TH_Settings::get( 'kw_per_seed' );
		$out     = array( 'keywords' => array(), 'competitors' => array() );
		foreach ( $seeds as $s ) {
			$kw = self::matching_terms( $s, $country, $limit );
			if ( is_wp_error( $kw ) ) {
				return $kw;
			}
			$out['keywords'][ $s ] = $kw;
			foreach ( TH_Settings::competitors() as $d ) {
				$ck = self::competitor_keywords( $d, $country, array( array( 'field' => 'keyword', 'is' => array( 'isubstring', $s ) ) ), 15 );
				if ( ! is_wp_error( $ck ) && $ck ) {
					$out['competitors'][ $s ][ $d ] = $ck;
				}
			}
		}
		return $out;
	}

	/**
	 * Our best Search Console position for each keyword (exact match).
	 *
	 * @param string[] $keywords Keywords.
	 * @return array keyword => position, url.
	 */
	public static function ours( $keywords ) {
		global $wpdb;
		if ( ! TH_Data::ready() || ! $keywords ) {
			return array();
		}
		$keywords = array_slice( array_values( array_unique( array_map( 'strtolower', $keywords ) ) ), 0, 800 );
		$in       = implode( ',', array_fill( 0, count( $keywords ), '%s' ) );
		$rows     = $wpdb->get_results( $wpdb->prepare( 'SELECT query, post_id, position, impressions FROM ' . HWCH_Store::table( 'queries' ) . " WHERE query IN ($in) ORDER BY impressions DESC", $keywords ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out      = array();
		foreach ( (array) $rows as $r ) {
			$q = strtolower( $r['query'] );
			if ( ! isset( $out[ $q ] ) ) {
				$out[ $q ] = array( 'position' => round( (float) $r['position'], 1 ), 'url' => get_permalink( (int) $r['post_id'] ) );
			}
		}
		return $out;
	}
}
