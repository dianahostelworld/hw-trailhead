<?php
/**
 * The link with Hostelworld Content Health: articles queued there come to
 * Trailhead, its refresh brief goes to Claude, its planner starts Trailhead
 * articles, and Trailhead refreshes show up in its Refresh impact.
 *
 * Everything here does nothing when Content Health is not active.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Content Health bridge.
 */
class TH_Bridge {

	/**
	 * Is Content Health active?
	 *
	 * @return bool
	 */
	public static function ch() {
		return class_exists( 'HWCH_Brief' ) && class_exists( 'HWCH_Store' );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'added_post_meta', array( __CLASS__, 'queued' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'queued' ), 10, 3 );
	}

	/**
	 * "Queue for refresh" in Content Health: due today on Trailhead's list,
	 * so the morning batch sends it to Claude.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return void
	 */
	public static function queued( $meta_id, $post_id, $key ) {
		if ( '_hwch_queued' !== $key || 'post' !== get_post_type( $post_id ) ) {
			return;
		}
		TH_Schedule::now( (int) $post_id, __( 'Queued from Content Health', 'hw-trailhead' ) );
	}

	/**
	 * Content Health's refresh brief for an article, as plain text.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function brief( $post_id ) {
		if ( ! self::ch() || ! method_exists( 'HWCH_Brief', 'text' ) ) {
			return '';
		}
		try {
			return (string) HWCH_Brief::text( HWCH_Brief::data( (int) $post_id ) );
		} catch ( Throwable $e ) {
			return '';
		}
	}

	/**
	 * A refresh is done in Trailhead: Content Health measures its impact from
	 * today (the latest refresh always restarts the comparison) and the
	 * "Queued" flag clears.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function refreshed( $post_id ) {
		if ( ! self::ch() ) {
			return;
		}
		update_post_meta( $post_id, '_hwch_refreshed', time() );
		delete_post_meta( $post_id, '_hwch_queued' );
		delete_transient( 'hwch_impact' );
		if ( class_exists( 'HWCH_Actions' ) && method_exists( 'HWCH_Actions', 'log' ) ) {
			HWCH_Actions::log( $post_id, 'refreshed', array( 'auto' => true, 'via' => 'Trailhead' ) );
		}
	}

	/**
	 * A Content planner row, to fill in New article.
	 *
	 * @param int $id Plan row ID.
	 * @return array|null title, keyword, notes, searches, impressions, destination, topic.
	 */
	public static function plan( $id ) {
		global $wpdb;
		if ( ! self::ch() || ! $id ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HWCH_Store::table( 'plan' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $row ) {
			return null;
		}
		$ev       = $row['evidence'] ? json_decode( $row['evidence'], true ) : null;
		$searches = array();
		foreach ( (array) ( $ev['examples'] ?? array() ) as $e ) {
			$searches[] = array( 'search' => (string) $e[0], 'impressions' => (int) $e[1], 'position' => round( (float) ( $e[2] ?? 0 ), 1 ) );
		}
		return array(
			'id'          => (int) $row['id'],
			'title'       => (string) $row['title'],
			'keyword'     => $searches ? $searches[0]['search'] : (string) $row['title'],
			'notes'       => (string) $row['notes'],
			'destination' => (string) $row['destination'],
			'topic'       => (string) $row['topic'],
			'impressions' => (int) ( $ev['impressions'] ?? 0 ),
			'searches'    => $searches,
		);
	}

	/**
	 * The draft exists: the planner row links to it and shows "Writing".
	 *
	 * @param int $plan_id Plan row ID.
	 * @param int $post_id Draft.
	 * @return void
	 */
	public static function plan_drafted( $plan_id, $post_id ) {
		global $wpdb;
		if ( ! self::ch() || ! $plan_id ) {
			return;
		}
		$wpdb->update( HWCH_Store::table( 'plan' ), array( 'post_id' => (int) $post_id, 'status' => 'writing' ), array( 'id' => (int) $plan_id ) ); // phpcs:ignore WordPress.DB
	}
}
