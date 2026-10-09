<?php
/**
 * Trailhead settings.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in one option.
 */
class TH_Settings {

	const OPTION = 'th_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'style_guide'     => implode(
				"\n",
				array(
					'Audience and voice: [describe who we write for and how we sound]',
					'Titles: give them character, not just the keyword.',
					'Every city article links to its city hub and uses the hostel cards shortcode for that city.',
					'Events go on Events pages; experiences link to the Experiences Hub.',
					'Images: real photos from the Media Library only. Never AI images of real places.',
					'Prices: give the month they were checked.',
					'[Add the rest of your house rules]',
				)
			),
			'competitors'     => "lonelyplanet.com\nnomadicmatt.com\ntimeout.com",
			'country'         => 'us',
			'kw_per_seed'     => 30,
			'quarterly_count' => 250,
			'ahrefs_project'  => 3401995,
			'facts'           => implode(
				"\n",
				array(
					'Hostelworld: [one paragraph on what Hostelworld is and does]',
					'Hostelworld app: [what it does, link]',
					'Social Pass: [what it is, link]',
					'Linkups: [what they are, link]',
					'City Chats: [what they are, link]',
					'Events pages: [what they are, link]',
					'Never link to the Experiences Hub; link Events pages instead.',
				)
			),
			'authors'         => "Hostelworld | [house voice: friendly, practical, spoken not stiff]\n[Author name] | [their voice and the topics they cover]",
			'playbooks'       => self::default_playbooks(),
			'blocks'          => array(),
			'ahrefs_key'      => '',
			'unsplash_key'    => '',
			'pexels_key'      => '',
			'auto_refresh'    => 1,
			'auto_per_day'    => 10,
		);
	}

	/**
	 * Each step's playbook: the editor's process, written down once and sent
	 * to Claude with every task for that step.
	 *
	 * @return array
	 */
	public static function steps() {
		return array(
			'research' => __( 'Keyword and competitor research', 'hw-trailhead' ),
			'info_gain' => __( 'Information gain (what we add that others don\'t)', 'hw-trailhead' ),
			'outline'  => __( 'Outlining', 'hw-trailhead' ),
			'draft'    => __( 'Drafting', 'hw-trailhead' ),
			'product'  => __( 'Hostelworld product placement', 'hw-trailhead' ),
			'images'   => __( 'Images', 'hw-trailhead' ),
			'links'    => __( 'Internal linking', 'hw-trailhead' ),
			'format'   => __( 'Formatting for WordPress', 'hw-trailhead' ),
		);
	}

	/**
	 * Starting playbooks, from the rules the editor has already set.
	 *
	 * @return array
	 */
	public static function default_playbooks() {
		return array(
			'research'  => 'Ahrefs for keywords and volumes. Prefer searches hostel travellers make. If we already rank 4-30, refresh that article rather than writing a new one.',
			'info_gain' => "Add something the top results don't have: recent news, official sources (tourism boards, transport operators), what travellers say on Reddit and social media, and the editor's own notes or interviews. Every new fact needs a source URL. Never invent statistics, quotes or reviews.",
			'outline'   => '[How you like articles structured, e.g. the nine-section template]',
			'draft'     => 'Spoken, not stiff. Short paragraphs. Answer the search in the first two paragraphs.',
			'product'   => 'Mention Hostelworld products only where they genuinely help the reader: hostel listings, the app, Social Pass, Linkups, City Chats, Events. Never forced.',
			'images'    => 'Lifestyle and editorial photos from Unsplash or Pexels with photographer credit. Product images from the brand CDN, never stock. Suggest one image per major section.',
			'links'     => "Weave internal links through every major section, never bunched in one. Regular targets: homepage, hostel listing pages, city hostel pages, the app, Social Pass, Events pages, Linkups, City Chats, and evergreen guides (hostel packing list, carry-on only guide, backpacking Europe guide, backpacking checklist, how to pack for a hostel, what is Hostelworld, free things to do in Europe's backpacker cities, 50 free things to do in Europe). Flag any URL you can't verify instead of assuming it's live. Never link to the Experiences Hub.",
			'format'    => 'Classic editor HTML. Use shortcodes and blocks from the blocks library only where they fit the section, never forced, with the exact syntax given.',
		);
	}

	/**
	 * Author voices as name => notes.
	 *
	 * @return array
	 */
	public static function authors() {
		$out = array();
		foreach ( preg_split( '/\r?\n/', (string) self::get( 'authors' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' !== $parts[0] ) {
				$out[ $parts[0] ] = $parts[1] ?? '';
			}
		}
		return $out;
	}

	/**
	 * Everything Claude needs about the house for one step.
	 *
	 * @param string[] $steps  Playbook steps.
	 * @param string   $author Author name.
	 * @return array
	 */
	public static function house( $steps, $author = '' ) {
		$pb      = (array) self::get( 'playbooks' );
		$authors = self::authors();
		$out     = array(
			'style_guide'       => self::get( 'style_guide' ),
			'hostelworld_facts' => self::get( 'facts' ),
			'playbooks'         => array(),
		);
		foreach ( $steps as $s ) {
			if ( ! empty( $pb[ $s ] ) ) {
				$out['playbooks'][ $s ] = $pb[ $s ];
			}
		}
		if ( $author && isset( $authors[ $author ] ) ) {
			$out['author'] = array( 'name' => $author, 'voice' => $authors[ $author ] );
		}
		if ( array_intersect( $steps, array( 'outline', 'draft', 'format' ) ) ) {
			$out['blocks_library'] = TH_Library::for_claude();
		}
		return $out;
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Competitor domains as a list.
	 *
	 * @return string[]
	 */
	public static function competitors() {
		$list = preg_split( '/[\r\n,]+/', (string) self::get( 'competitors' ) );
		$list = array_map(
			function ( $d ) {
				$d = strtolower( trim( $d ) );
				$d = preg_replace( '#^https?://#', '', $d );
				return preg_replace( '#/.*$#', '', $d );
			},
			$list
		);
		return array_slice( array_values( array_unique( array_filter( $list ) ) ), 0, 6 );
	}

	/**
	 * Save from a form.
	 *
	 * @param array $in Raw input.
	 * @return void
	 */
	public static function save( $in ) {
		$all = self::all();

		$all['style_guide']     = sanitize_textarea_field( wp_unslash( $in['style_guide'] ?? $all['style_guide'] ) );
		$all['competitors']     = sanitize_textarea_field( wp_unslash( $in['competitors'] ?? $all['competitors'] ) );
		$all['country']         = in_array( $in['country'] ?? '', array( 'us', 'gb', 'au', 'ca', 'ie' ), true ) ? $in['country'] : $all['country'];
		$all['kw_per_seed']     = max( 10, min( 200, (int) ( $in['kw_per_seed'] ?? $all['kw_per_seed'] ) ) );
		$all['quarterly_count'] = max( 0, min( 5000, (int) ( $in['quarterly_count'] ?? $all['quarterly_count'] ) ) );
		$all['ahrefs_project']  = max( 0, (int) ( $in['ahrefs_project'] ?? $all['ahrefs_project'] ) );
		if ( isset( $in['facts'] ) ) {
			$all['facts'] = sanitize_textarea_field( wp_unslash( $in['facts'] ) );
		}
		if ( isset( $in['authors'] ) ) {
			$all['authors'] = sanitize_textarea_field( wp_unslash( $in['authors'] ) );
		}
		if ( isset( $in['pb'] ) && is_array( $in['pb'] ) ) {
			foreach ( array_keys( self::steps() ) as $k ) {
				if ( isset( $in['pb'][ $k ] ) ) {
					$all['playbooks'][ $k ] = sanitize_textarea_field( wp_unslash( $in['pb'][ $k ] ) );
				}
			}
		}
		if ( isset( $in['block_note'] ) && is_array( $in['block_note'] ) ) {
			$blocks = array();
			foreach ( $in['block_note'] as $tag => $note ) {
				$tag = sanitize_key( $tag );
				$ex  = isset( $in['block_example'][ $tag ] ) ? sanitize_textarea_field( wp_unslash( $in['block_example'][ $tag ] ) ) : '';
				$off = empty( $in['block_use'][ $tag ] );
				$blocks[ $tag ] = array( 'note' => sanitize_textarea_field( wp_unslash( $note ) ), 'example' => $ex, 'off' => $off );
			}
			$all['blocks'] = $blocks;
		}
		foreach ( array( 'ahrefs_key', 'unsplash_key', 'pexels_key' ) as $k ) {
			if ( ! empty( $in[ $k ] ) ) {
				$all[ $k ] = sanitize_text_field( wp_unslash( $in[ $k ] ) );
			}
			if ( ! empty( $in[ 'clear_' . $k ] ) ) {
				$all[ $k ] = '';
			}
		}
		if ( isset( $in['auto_per_day'] ) ) {
			$all['auto_refresh'] = empty( $in['auto_refresh'] ) ? 0 : 1;
			$all['auto_per_day'] = max( 1, min( 50, (int) $in['auto_per_day'] ) );
		}

		update_option( self::OPTION, $all, false );
	}
}
