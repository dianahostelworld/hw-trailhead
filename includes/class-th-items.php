<?php
/**
 * Ideas and the articles made from them, in their own table.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * One row per idea. An approved idea moves through the stages
 * brief → draft → ready.
 */
class TH_Items {

	const DB_VERSION = 1;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'th_items';
	}

	/**
	 * Create the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			'CREATE TABLE ' . self::table() . ' (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL DEFAULT \'\',
				keyword varchar(255) NOT NULL DEFAULT \'\',
				verdict varchar(10) NOT NULL DEFAULT \'new\',
				score smallint(5) NOT NULL DEFAULT 0,
				reason text NULL,
				status varchar(10) NOT NULL DEFAULT \'new\',
				stage varchar(10) NOT NULL DEFAULT \'\',
				data longtext NULL,
				brief longtext NULL,
				draft_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				checked longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status)
			) ' . $wpdb->get_charset_collate() . ';'
		);

		update_option( 'th_db_version', self::DB_VERSION, false );
	}

	/**
	 * Install after an update that changed the table.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'th_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Add an idea.
	 *
	 * @param array $row Fields; data and brief may be arrays.
	 * @return int ID.
	 */
	public static function insert( $row ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'title'      => mb_substr( (string) ( $row['title'] ?? '' ), 0, 255 ),
				'keyword'    => mb_substr( (string) ( $row['keyword'] ?? '' ), 0, 255 ),
				'verdict'    => in_array( $row['verdict'] ?? '', array( 'new', 'refresh', 'skip' ), true ) ? $row['verdict'] : 'new',
				'score'      => max( 0, min( 100, (int) ( $row['score'] ?? 0 ) ) ),
				'reason'     => (string) ( $row['reason'] ?? '' ),
				'status'     => 'new',
				'stage'      => '',
				'data'       => wp_json_encode( $row['data'] ?? array() ),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * One item, with JSON fields decoded.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Items matching simple conditions.
	 *
	 * @param array $where Column => value.
	 * @return array[]
	 */
	public static function find( $where = array() ) {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . self::table() . ' WHERE 1=1';
		$args = array();
		foreach ( $where as $col => $val ) {
			if ( ! in_array( $col, array( 'status', 'stage', 'verdict' ), true ) ) {
				continue;
			}
			if ( is_array( $val ) ) {
				$sql .= ' AND ' . $col . ' IN (' . implode( ',', array_fill( 0, count( $val ), '%s' ) ) . ')';
				$args = array_merge( $args, $val );
			} else {
				$sql   .= ' AND ' . $col . ' = %s';
				$args[] = $val;
			}
		}
		$sql .= ' ORDER BY score DESC, id DESC LIMIT 500';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	/**
	 * Count by status/stage.
	 *
	 * @param array $where As find().
	 * @return int
	 */
	public static function count( $where ) {
		return count( self::find( $where ) );
	}

	/**
	 * Update fields.
	 *
	 * @param int   $id     ID.
	 * @param array $fields Fields; data, brief and checked may be arrays.
	 * @return void
	 */
	public static function update( $id, $fields ) {
		global $wpdb;
		foreach ( array( 'data', 'brief', 'checked' ) as $json ) {
			if ( array_key_exists( $json, $fields ) && ! is_string( $fields[ $json ] ) ) {
				$fields[ $json ] = null === $fields[ $json ] ? null : wp_json_encode( $fields[ $json ] );
			}
		}
		$fields['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Merge keys into an item's data.
	 *
	 * @param int   $id   ID.
	 * @param array $data Keys to set.
	 * @return void
	 */
	public static function merge_data( $id, $data ) {
		$item = self::get( $id );
		if ( $item ) {
			self::update( $id, array( 'data' => array_merge( $item['data'], $data ) ) );
		}
	}

	/**
	 * Decode JSON columns.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function decode( $row ) {
		foreach ( array( 'data', 'brief', 'checked' ) as $json ) {
			$val          = isset( $row[ $json ] ) ? json_decode( (string) $row[ $json ], true ) : null;
			$row[ $json ] = is_array( $val ) ? $val : ( 'brief' === $json ? null : array() );
		}
		$row['id'] = (int) $row['id'];
		return $row;
	}
}
