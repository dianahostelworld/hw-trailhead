<?php
/**
 * The refresh schedule, kept as post meta on each article.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Each article has an interval, a next due date, an optional note, the date
 * it was last refreshed and, once Claude has written one, a refresh brief.
 */
class TH_Schedule {

	const INTERVALS = array(
		'monthly'   => 30,
		'quarterly' => 91,
		'half'      => 182,
		'yearly'    => 365,
		'none'      => 0,
	);

	/**
	 * Interval labels.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'monthly'   => __( 'Every month', 'hw-trailhead' ),
			'quarterly' => __( 'Every quarter', 'hw-trailhead' ),
			'half'      => __( 'Every 6 months', 'hw-trailhead' ),
			'yearly'    => __( 'Every year', 'hw-trailhead' ),
			'none'      => __( 'Not scheduled', 'hw-trailhead' ),
		);
	}

	/**
	 * Refresh types, from the article's numbers.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'push'    => array( __( 'Push to page one', 'hw-trailhead' ), 'new', __( 'Ranks 4th to 15th for searches with real impressions. Strengthen the sections those searches are about.', 'hw-trailhead' ) ),
			'ctr'     => array( __( 'Fix title and meta', 'hw-trailhead' ), 'warn', __( 'Shows up high but gets far fewer clicks than that position usually earns.', 'hw-trailhead' ) ),
			'recover' => array( __( 'Recover lost clicks', 'hw-trailhead' ), 'bad', __( 'Clicks in the last 90 days are down a quarter or more on the 90 days before.', 'hw-trailhead' ) ),
			'prune'   => array( __( 'Prune or merge?', 'hw-trailhead' ), '', __( 'Next to no clicks or impressions. Check whether it is worth keeping before refreshing it.', 'hw-trailhead' ) ),
			'fresh'   => array( __( 'Keep it fresh', 'hw-trailhead' ), 'ok', __( 'Performing steadily. Check facts, prices and dates on schedule.', 'hw-trailhead' ) ),
		);
	}

	/**
	 * Today's date in the site's time zone.
	 *
	 * @return string Y-m-d.
	 */
	public static function today() {
		return current_time( 'Y-m-d' );
	}

	/**
	 * A date some days after another.
	 *
	 * @param string $date Y-m-d.
	 * @param int    $days Days.
	 * @return string
	 */
	public static function add_days( $date, $days ) {
		return gmdate( 'Y-m-d', strtotime( $date . ' 12:00:00 UTC' ) + $days * DAY_IN_SECONDS );
	}

	/**
	 * Every English article with its numbers and schedule, keyed by ID.
	 *
	 * @return array[]
	 */
	public static function rows() {
		global $wpdb;
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$articles = TH_Data::articles();
		$meta     = array();
		$res      = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('_th_interval','_th_due','_th_note','_th_last','_th_brief','_th_task','_th_standing')", ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $res as $r ) {
			$meta[ (int) $r['post_id'] ][ $r['meta_key'] ] = $r['meta_value'];
		}
		$out = array();
		foreach ( $articles as $id => $a ) {
			$m        = isset( $meta[ $id ] ) ? $meta[ $id ] : array();
			$row      = $a + array(
				'interval' => $m['_th_interval'] ?? '',
				'due'      => $m['_th_due'] ?? '',
				'note'     => $m['_th_note'] ?? '',
				'last'     => $m['_th_last'] ?? '',
				'brief'    => isset( $m['_th_brief'] ) ? json_decode( $m['_th_brief'], true ) : null,
				'task'     => (int) ( $m['_th_task'] ?? 0 ),
				'standing' => $m['_th_standing'] ?? '',
			);
			$row['type'] = self::classify( $row );
			$out[ $id ]  = $row;
		}
		$cache = $out;
		return $out;
	}

	/**
	 * Clear the per-request cache after a change.
	 *
	 * @return void
	 */
	public static function flush() {
		// Static caches live per request; a redirect follows every change.
	}

	/**
	 * Which refresh an article needs.
	 *
	 * @param array $a Row.
	 * @return string Type key.
	 */
	public static function classify( $a ) {
		if ( $a['prior'] >= 30 && $a['recent'] <= $a['prior'] * 0.75 ) {
			return 'recover';
		}
		if ( $a['impressions'] >= 1000 && $a['position'] && $a['position'] <= 10 && null !== $a['ctr'] && $a['ctr'] < TH_Data::expected_ctr( $a['position'] ) / 2 ) {
			return 'ctr';
		}
		$top = $a['top'];
		if ( ( $top && $top['position'] >= 4 && $top['position'] <= 15 && $top['impressions'] >= 100 ) || ( $a['position'] && $a['position'] >= 4 && $a['position'] <= 15 && $a['impressions'] >= 500 ) ) {
			return 'push';
		}
		if ( 0 === $a['clicks'] && $a['impressions'] < 100 ) {
			return 'prune';
		}
		return 'fresh';
	}

	/**
	 * Schedule every article that has no schedule yet: the most valuable
	 * quarterly, the rest with clicks yearly, articles with no clicks left
	 * unscheduled for a prune decision. Due dates are spread out so a few
	 * come up each day, the most valuable first.
	 *
	 * @return array Counts.
	 */
	public static function setup() {
		$rows = self::rows();
		$new  = array_filter( $rows, function ( $r ) { return '' === $r['interval']; } );
		uasort( $new, function ( $x, $y ) { return ( $y['recent'] * 3 + $y['clicks'] ) - ( $x['recent'] * 3 + $x['clicks'] ); } );

		$q       = (int) TH_Settings::get( 'quarterly_count' );
		$taken_q = count( array_filter( $rows, function ( $r ) { return 'quarterly' === $r['interval']; } ) );
		$slots_q = max( 0, $q - $taken_q );
		$with    = array_values( array_filter( $new, function ( $r ) { return $r['clicks'] > 0; } ) );
		$without = array_values( array_filter( $new, function ( $r ) { return $r['clicks'] <= 0; } ) );
		$today   = self::today();
		$count   = array( 'quarterly' => 0, 'yearly' => 0, 'none' => 0 );
		$yearly  = max( 1, count( $with ) - $slots_q );

		foreach ( $with as $k => $r ) {
			if ( $k < $slots_q ) {
				$interval = 'quarterly';
				$offset   = (int) floor( $k * 91 / max( 1, $slots_q ) );
			} else {
				$interval = 'yearly';
				$offset   = (int) floor( ( $k - $slots_q ) * 365 / $yearly );
			}
			update_post_meta( $r['id'], '_th_interval', $interval );
			update_post_meta( $r['id'], '_th_due', self::add_days( $today, $offset ) );
			$count[ $interval ]++;
		}
		foreach ( $without as $r ) {
			update_post_meta( $r['id'], '_th_interval', 'none' );
			$count['none']++;
		}
		update_option( 'th_schedule_setup', current_time( 'mysql', true ), false );
		return $count;
	}

	/**
	 * Change an article's interval; the due date follows the last refresh.
	 *
	 * @param int    $id       Post ID.
	 * @param string $interval Interval key.
	 * @return void
	 */
	public static function set_interval( $id, $interval ) {
		if ( ! isset( self::INTERVALS[ $interval ] ) ) {
			return;
		}
		update_post_meta( $id, '_th_interval', $interval );
		if ( 'none' === $interval ) {
			return;
		}
		$last = get_post_meta( $id, '_th_last', true );
		$due  = $last ? self::add_days( $last, self::INTERVALS[ $interval ] ) : ( get_post_meta( $id, '_th_due', true ) ? get_post_meta( $id, '_th_due', true ) : self::today() );
		update_post_meta( $id, '_th_due', $due );
	}

	/**
	 * Bring an article forward to today, adding a note.
	 *
	 * @param int    $id   Post ID.
	 * @param string $note Note to add.
	 * @return void
	 */
	public static function now( $id, $note ) {
		$old = (string) get_post_meta( $id, '_th_note', true );
		update_post_meta( $id, '_th_note', mb_substr( trim( $old ? $old . ' · ' . $note : $note ), 0, 500 ) );
		update_post_meta( $id, '_th_due', self::today() );
		$interval = get_post_meta( $id, '_th_interval', true );
		if ( ! $interval || 'none' === $interval ) {
			update_post_meta( $id, '_th_interval', 'yearly' );
		}
	}

	/**
	 * Mark refreshed: next due date from today, note and brief cleared.
	 *
	 * @param int $id Post ID.
	 * @return void
	 */
	public static function done( $id ) {
		$interval = get_post_meta( $id, '_th_interval', true );
		$days     = isset( self::INTERVALS[ $interval ] ) && self::INTERVALS[ $interval ] ? self::INTERVALS[ $interval ] : 365;
		update_post_meta( $id, '_th_last', self::today() );
		update_post_meta( $id, '_th_due', self::add_days( self::today(), $days ) );
		delete_post_meta( $id, '_th_note' );
		delete_post_meta( $id, '_th_brief' );
		delete_post_meta( $id, '_th_task' );
		delete_post_meta( $id, '_th_update' );
		TH_Bridge::refreshed( $id );
	}
}
