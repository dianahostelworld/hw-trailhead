<?php
/**
 * The blocks and live-data library: every shortcode the blog's plugins add,
 * with real examples found in published articles.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes Claude may use in drafts.
 */
class TH_Library {

	/**
	 * WordPress's own shortcodes, which aren't useful in articles.
	 */
	const SKIP = array( 'caption', 'wp_caption', 'gallery', 'audio', 'video', 'playlist', 'embed' );

	/**
	 * Up to three real uses of a shortcode in published articles. Cached
	 * for a day.
	 *
	 * @param string $tag Shortcode.
	 * @return string[]
	 */
	public static function examples( $tag ) {
		global $wpdb;
		$key    = 'th_sc_ex_' . md5( $tag );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_content LIKE %s ORDER BY post_modified DESC LIMIT 40", '%[' . $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB
		$out  = array();
		$q    = preg_quote( $tag, '#' );
		foreach ( (array) $rows as $content ) {
			if ( preg_match_all( '#\[' . $q . '(?=[\s\]/])[^\]]*\](?:(?:(?!\[/?' . $q . '[\s\]]).){0,400}?\[/' . $q . '\])?#s', $content, $m ) ) {
				foreach ( $m[0] as $ex ) {
					$ex = trim( $ex );
					if ( strlen( $ex ) <= 600 && ! in_array( $ex, $out, true ) ) {
						$out[] = $ex;
					}
					if ( count( $out ) >= 3 ) {
						break 2;
					}
				}
			}
		}
		set_transient( $key, $out, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Every shortcode the plugins add, with examples and the editor's
	 * settings.
	 *
	 * @return array tag key => tag, note, example (own), found, off, registered.
	 */
	public static function all() {
		global $shortcode_tags;
		$saved = (array) TH_Settings::get( 'blocks' );
		$out   = array();
		foreach ( array_keys( (array) $shortcode_tags ) as $tag ) {
			if ( in_array( $tag, self::SKIP, true ) ) {
				continue;
			}
			$key         = sanitize_key( $tag );
			$out[ $key ] = array(
				'tag'        => $tag,
				'note'       => $saved[ $key ]['note'] ?? '',
				'example'    => $saved[ $key ]['example'] ?? '',
				'off'        => ! empty( $saved[ $key ]['off'] ),
				'found'      => self::examples( $tag ),
				'registered' => true,
			);
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Whether Claude may use a block: switched on, and at least one example
	 * (found or the editor's own) so it never guesses the settings.
	 *
	 * @param array $b Block from all().
	 * @return bool
	 */
	public static function usable( $b ) {
		return $b['registered'] && ! $b['off'] && ( $b['found'] || '' !== trim( $b['example'] ) );
	}

	/**
	 * What Claude gets.
	 *
	 * @return array[]
	 */
	public static function for_claude() {
		$out = array();
		foreach ( self::all() as $b ) {
			if ( self::usable( $b ) ) {
				$examples = $b['found'];
				if ( '' !== trim( $b['example'] ) ) {
					array_unshift( $examples, trim( $b['example'] ) );
				}
				$out[] = array(
					'shortcode'          => $b['tag'],
					'when_to_use'        => $b['note'],
					'real_examples'      => array_slice( $examples, 0, 3 ),
					'how_to_use'         => 'Copy the syntax of an example exactly; change only values (such as a city or ID) to fit this article.',
				);
			}
		}
		return $out;
	}
}
