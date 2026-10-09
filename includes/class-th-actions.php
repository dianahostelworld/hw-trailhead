<?php
/**
 * Form handlers.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every button posts to admin-post.php?action=th_do with a "do" value.
 */
class TH_Actions {

	const CAP = 'edit_others_posts';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_th_do', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Hidden fields for a form.
	 *
	 * @param string $do     Action.
	 * @param array  $fields Extra hidden fields.
	 * @return string
	 */
	public static function fields( $do, $fields = array() ) {
		$out = wp_nonce_field( 'th_do', '_thnonce', true, false ) . '<input type="hidden" name="action" value="th_do"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		foreach ( $fields as $k => $v ) {
			$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $out;
	}

	/**
	 * Back to a screen with a message.
	 *
	 * @param array  $args Query args for the Trailhead page.
	 * @param string $msg  Message.
	 * @return void
	 */
	private static function back( $args, $msg = '' ) {
		if ( $msg ) {
			set_transient( 'th_msg_' . get_current_user_id(), $msg, 60 );
		}
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'trailhead' ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Data every writing task gets.
	 *
	 * @return array
	 */
	private static function house( $steps, $item = null ) {
		return array( 'house' => TH_Settings::house( $steps, $item ? (string) ( $item['data']['author'] ?? '' ) : '' ) );
	}

	/**
	 * An idea's data for Claude.
	 *
	 * @param array $item Item.
	 * @return array
	 */
	private static function idea_for_claude( $item ) {
		return array(
			'title'       => $item['title'],
			'keyword'     => $item['keyword'],
			'verdict'     => $item['verdict'],
			'reason'      => $item['reason'],
			'cluster'     => $item['data']['cluster'] ?? array(),
			'volume'      => $item['data']['volume'] ?? null,
			'difficulty'  => $item['data']['kd'] ?? null,
			'our_url'     => $item['data']['our_url'] ?? '',
			'competitors' => $item['data']['competitors'] ?? array(),
			'content_planner' => $item['data']['planner'] ?? null,
		);
	}

	/**
	 * Handle a form.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'hw-trailhead' ) );
		}
		check_admin_referer( 'th_do', '_thnonce' );

		// phpcs:disable WordPress.Security.NonceVerification
		$do   = sanitize_key( $_POST['go'] ?? ( $_POST['do'] ?? '' ) );
		$id   = (int) ( $_POST['id'] ?? 0 );
		$post = (int) ( $_POST['post'] ?? 0 );
		$item = $id ? TH_Items::get( $id ) : null;
		$text = function ( $k ) { return sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) ); };

		switch ( $do ) {
			case 'research':
				$seeds = array_slice( array_values( array_filter( array_map( 'trim', explode( ',', $text( 'seeds' ) ) ) ) ), 0, 8 );
				if ( ! $seeds ) {
					self::back( array( 'view' => 'research', 'tab' => 'topic' ), __( 'Add at least one destination or topic.', 'hw-trailhead' ) );
				}
				$ah = TH_Ahrefs::ready() ? TH_Ahrefs::run( 'Research: ' . implode( ', ', $seeds ), function () use ( $seeds ) { return TH_Ahrefs::research( $seeds ); } ) : null;
				self::queue_research( $seeds, is_wp_error( $ah ) ? null : $ah );
				self::back( array( 'view' => 'research', 'tab' => 'topic' ), is_wp_error( $ah ) ? sprintf( /* translators: %s: error */ __( 'Ahrefs gave an error (%s), so Claude will fetch the data with the connector instead. Research queued.', 'hw-trailhead' ), $ah->get_error_message() ) : __( 'Research queued for Claude. The ideas appear in Ideas after the next run.', 'hw-trailhead' ) );
				break;

			case 'keywords':
				$seeds = array_slice( array_values( array_filter( array_map( 'trim', explode( ',', $text( 'seeds' ) ) ) ) ), 0, 8 );
				if ( ! $seeds || ! TH_Ahrefs::ready() ) {
					self::back( array( 'view' => 'research', 'tab' => 'topic' ), __( 'Add at least one destination or topic.', 'hw-trailhead' ) );
				}
				$ah = TH_Ahrefs::run( 'Keywords: ' . implode( ', ', $seeds ), function () use ( $seeds ) { return TH_Ahrefs::research( $seeds ); } );
				if ( is_wp_error( $ah ) ) {
					self::back( array( 'view' => 'research', 'tab' => 'topic' ), sprintf( /* translators: %s: error */ __( 'Ahrefs gave an error: %s', 'hw-trailhead' ), $ah->get_error_message() ) );
				}
				set_transient( 'th_kw_' . get_current_user_id(), array( 'seeds' => $seeds, 'data' => $ah, 'at' => time() ), DAY_IN_SECONDS );
				self::back( array( 'view' => 'research', 'tab' => 'topic', 'kw' => 1 ) );
				break;

			case 'keywords_to_claude':
				$kw = get_transient( 'th_kw_' . get_current_user_id() );
				if ( ! is_array( $kw ) ) {
					self::back( array( 'view' => 'research', 'tab' => 'topic' ), __( 'Those keywords have expired. Get them again.', 'hw-trailhead' ) );
				}
				self::queue_research( $kw['seeds'], $kw['data'] );
				self::back( array( 'view' => 'research', 'tab' => 'topic', 'kw' => 1 ), __( 'Sent to Claude. The ideas appear in Ideas after the next run.', 'hw-trailhead' ) );
				break;

			case 'gaps':
				global $wpdb;
				$top = TH_Data::ready() ? $wpdb->get_col( 'SELECT query FROM ' . HWCH_Store::table( 'queries' ) . ' GROUP BY query ORDER BY SUM(impressions) DESC LIMIT 1500' ) : array(); // phpcs:ignore WordPress.DB
				$ah = null;
				if ( TH_Ahrefs::ready() ) {
					$ah = TH_Ahrefs::run(
						'Competitor gaps',
						function () {
							$where = array( array( 'field' => 'volume', 'is' => array( 'gte', 200 ) ), array( 'field' => 'is_informational', 'is' => array( 'eq', true ) ), array( 'field' => 'is_branded', 'is' => array( 'eq', false ) ) );
							$ours  = TH_Tasks::our_searches_set();
							$out   = array();
							foreach ( TH_Settings::competitors() as $d ) {
								$rows = TH_Ahrefs::competitor_keywords( $d, TH_Settings::get( 'country' ), $where, 60 );
								if ( is_wp_error( $rows ) ) {
									return $rows;
								}
								$out[ $d ] = array_values( array_filter( $rows, function ( $r ) use ( $ours ) { return ! isset( $ours[ strtolower( (string) ( $r['keyword'] ?? '' ) ) ] ); } ) );
							}
							return $out;
						}
					);
				}
				$input = array( 'competitors' => TH_Settings::competitors(), 'country' => TH_Settings::get( 'country' ) );
				$input += ( $ah && ! is_wp_error( $ah ) ) ? array( 'ahrefs_data' => $ah ) : array( 'our_searches' => $top );
				TH_Tasks::add( 'gaps', 'gaps', 'Competitor gaps', $input + self::house( array( 'research' ) ) );
				self::back( array( 'view' => 'research' ), is_wp_error( $ah ) ? sprintf( /* translators: %s: error */ __( 'Ahrefs gave an error (%s), so Claude will fetch the data with the connector instead. Competitor gaps queued.', 'hw-trailhead' ), $ah->get_error_message() ) : __( 'Competitor gap research queued for Claude.', 'hw-trailhead' ) );
				break;

			case 'schedule_now':
				TH_Schedule::now( $post, $text( 'note' ) );
				self::back( array( 'view' => 'research' ), sprintf( /* translators: %s: title */ __( 'Added to today\'s refreshes: %s.', 'hw-trailhead' ), get_the_title( $post ) ) );
				break;

			case 'start':
				$kw = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
				if ( '' === $kw ) {
					self::back( array( 'view' => 'start' ), __( 'Add a target keyword.', 'hw-trailhead' ) );
				}
				$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
				$rank  = TH_Data::rankings_for( $kw, 1 );
				$new   = TH_Items::insert(
					array(
						'title'   => $title ? $title : $kw,
						'keyword' => $kw,
						'verdict' => 'new',
						'score'   => 0,
						'reason'  => __( 'Started by you.', 'hw-trailhead' ),
						'data'    => array(
							'author'    => sanitize_text_field( wp_unslash( $_POST['author'] ?? '' ) ),
							'direction' => $text( 'direction' ),
							'notes'     => $text( 'notes' ),
							'our_url'   => $rank ? $rank[0]['url'] : '',
							'source'    => __( 'Started by you', 'hw-trailhead' ),
							'plan_id'   => (int) ( $_POST['plan'] ?? 0 ),
							'planner'   => ( $pl = TH_Bridge::plan( (int) ( $_POST['plan'] ?? 0 ) ) ) ? array( 'destination' => $pl['destination'], 'topic' => $pl['topic'], 'impressions' => $pl['impressions'], 'searches' => $pl['searches'] ) : null,
						),
					)
				);
				TH_Items::update( $new, array( 'status' => 'approved', 'stage' => 'brief' ) );
				if ( ! empty( $_POST['queue'] ) ) {
					$item = TH_Items::get( $new );
					TH_Tasks::add( 'brief', 'item:' . $new, 'Brief: ' . $item['title'], array( 'idea' => self::idea_for_claude( $item ), 'direction' => $item['data']['direction'], 'notes' => $item['data']['notes'], 'related_pages' => TH_Data::rankings_for( $kw, 30 ) ) + self::serp_for( $item['keyword'] ) + self::house( array( 'info_gain', 'outline', 'product', 'links', 'images' ), $item ) );
				}
				self::back( array( 'view' => 'briefs', 'item' => $new ), ! empty( $_POST['queue'] ) ? __( 'Article started. The brief is queued for Claude.', 'hw-trailhead' ) : __( 'Article started.', 'hw-trailhead' ) );
				break;

			case 'image_use':
				$msg = $item ? TH_Images::place( $item, (int) ( $_POST['slot'] ?? 0 ), sanitize_key( $_POST['provider'] ?? '' ), sanitize_text_field( wp_unslash( $_POST['photo'] ?? '' ) ) ) : '';
				self::back( array( 'view' => 'drafts', 'item' => $id ), $msg );
				break;

			case 'idea_approve':
				if ( $item ) {
					$url = $item['data']['our_url'] ?? '';
					$pid = $url ? (int) url_to_postid( $url ) : 0;
					if ( 'refresh' === $item['verdict'] && $pid ) {
						TH_Schedule::now( $pid, 'From research: ' . $item['keyword'] );
						TH_Items::update( $id, array( 'status' => 'approved', 'stage' => 'done' ) );
						self::back( array( 'view' => 'refresh_item', 'post' => $pid ), __( 'This is a refresh, so it\'s on today\'s refresh list.', 'hw-trailhead' ) );
					}
					TH_Items::update( $id, array( 'status' => 'approved', 'stage' => 'brief' ) );
				}
				self::back( array( 'view' => 'ideas' ), __( 'Approved. It\'s in Briefs.', 'hw-trailhead' ) );
				break;

			case 'idea_skip':
			case 'idea_unskip':
				if ( $item ) {
					TH_Items::update( $id, array( 'status' => 'idea_skip' === $do ? 'skipped' : 'new' ) );
				}
				self::back( array( 'view' => 'ideas', 'filter' => sanitize_key( $_POST['filter'] ?? 'waiting' ) ) );
				break;

			case 'brief_queue':
				if ( $item ) {
					TH_Items::merge_data( $id, array( 'notes' => $text( 'notes' ) ) );
					$item = TH_Items::get( $id );
					TH_Tasks::add( 'brief', 'item:' . $id, 'Brief: ' . $item['title'], array( 'idea' => self::idea_for_claude( $item ), 'direction' => $item['data']['direction'] ?? '', 'notes' => $item['data']['notes'] ?? '', 'related_pages' => TH_Data::rankings_for( $item['keyword'], 30 ) ) + self::serp_for( $item['keyword'] ) + self::house( array( 'info_gain', 'outline', 'product', 'links', 'images' ), $item ) );
				}
				self::back( array( 'view' => 'briefs', 'item' => $id ), __( 'Brief queued for Claude.', 'hw-trailhead' ) );
				break;

			case 'brief_save':
				if ( $item && $item['brief'] ) {
					$b       = $item['brief'];
					$lines   = function ( $k ) use ( $text ) { return array_values( array_filter( array_map( 'trim', explode( "\n", $text( $k ) ) ) ) ); };
					$b['chosen']    = (int) ( $_POST['chosen'] ?? 0 );
					$b['intent']    = $text( 'intent' );
					$b['length']    = $text( 'length' );
					$b['secondary'] = $lines( 'secondary' );
					$b['questions'] = $lines( 'questions' );
					$b['links']     = $lines( 'links' );
					$b['seoTitle']  = $text( 'seoTitle' );
					$b['meta']      = $text( 'meta' );
					$b['sources']   = array();
					foreach ( $lines( 'sources' ) as $line ) {
						$parts          = array_map( 'trim', explode( '|', $line ) );
						$b['sources'][] = array( 'what' => $parts[0], 'url' => esc_url_raw( $parts[1] ?? '' ), 'kind' => sanitize_key( $parts[2] ?? '' ) );
					}
					$h2s            = (array) wp_unslash( $_POST['h2'] ?? array() );
					$notes          = (array) wp_unslash( $_POST['h2notes'] ?? array() );
					$outline        = array();
					foreach ( $h2s as $k => $h ) {
						$h = sanitize_text_field( $h );
						if ( '' !== $h ) {
							$outline[] = array( 'h2' => $h, 'notes' => sanitize_textarea_field( $notes[ $k ] ?? '' ) );
						}
					}
					if ( '' !== $text( 'new_h2' ) ) {
						$outline[] = array( 'h2' => $text( 'new_h2' ), 'notes' => '' );
					}
					$b['outline'] = $outline;
					TH_Items::update( $id, array( 'brief' => $b ) );
				}
				self::back( array( 'view' => 'briefs', 'item' => $id ), __( 'Brief saved.', 'hw-trailhead' ) );
				break;

			case 'brief_revise':
				if ( $item && $item['brief'] && '' !== $text( 'ask' ) ) {
					TH_Tasks::add( 'brief_revise', 'item:' . $id, 'Revise brief: ' . $item['title'], array( 'idea' => self::idea_for_claude( $item ), 'brief' => $item['brief'], 'ask' => $text( 'ask' ), 'direction' => $item['data']['direction'] ?? '', 'related_pages' => TH_Data::rankings_for( $item['keyword'], 30 ) ) + self::serp_for( $item['keyword'] ) + self::house( array( 'info_gain', 'outline', 'product', 'links' ), $item ) );
					$data            = $item['data'];
					$data['history'] = array_slice( array_merge( (array) ( $data['history'] ?? array() ), array( 'Brief: ' . mb_substr( $text( 'ask' ), 0, 140 ) ) ), -10 );
					TH_Items::update( $id, array( 'data' => $data ) );
				}
				self::back( array( 'view' => 'briefs', 'item' => $id ), __( 'Your changes are queued for Claude.', 'hw-trailhead' ) );
				break;

			case 'brief_redo':
				if ( $item ) {
					TH_Items::update( $id, array( 'brief' => null ) );
				}
				self::back( array( 'view' => 'briefs', 'item' => $id ) );
				break;

			case 'brief_approve':
				if ( $item && $item['brief'] ) {
					TH_Items::update( $id, array( 'stage' => 'draft' ) );
					$b     = $item['brief'];
					$title = $b['titles'][ (int) ( $b['chosen'] ?? 0 ) ] ?? $item['title'];
					TH_Tasks::add( 'draft', 'item:' . $id, 'Draft: ' . $title, array( 'title' => $title, 'brief' => $b, 'direction' => $item['data']['direction'] ?? '' ) + self::house( array( 'draft', 'product', 'images', 'links', 'format' ), $item ) );
				}
				self::back( array( 'view' => 'drafts', 'item' => $id ), __( 'Brief approved. The draft is queued for Claude.', 'hw-trailhead' ) );
				break;

			case 'back_to_brief':
				if ( $item ) {
					TH_Items::update( $id, array( 'stage' => 'brief' ) );
				}
				self::back( array( 'view' => 'briefs', 'item' => $id ) );
				break;

			case 'draft_revise':
				if ( $item && $item['draft_post_id'] && '' !== $text( 'ask' ) ) {
					$p = get_post( $item['draft_post_id'] );
					TH_Tasks::add( 'draft_revise', 'item:' . $id, 'Revise draft: ' . $item['title'], array( 'html' => $p ? $p->post_content : '', 'brief' => $item['brief'], 'ask' => $text( 'ask' ) ) + self::house( array( 'draft', 'product', 'images', 'links', 'format' ), $item ) );
				}
				self::back( array( 'view' => 'drafts', 'item' => $id ), __( 'Your changes are queued for Claude.', 'hw-trailhead' ) );
				break;

			case 'draft_save':
				if ( $item && $item['draft_post_id'] ) {
					$checked = array_map( 'intval', array_keys( (array) ( $_POST['fact'] ?? array() ) ) );
					TH_Items::update( $id, array( 'checked' => $checked ) );
					$pid   = $item['draft_post_id'];
					$title = $text( 'seoTitle' );
					$meta  = $text( 'meta' );
					update_post_meta( $pid, '_th_seo_title', $title );
					update_post_meta( $pid, '_th_meta', $meta );
					if ( defined( 'WPSEO_VERSION' ) ) {
						update_post_meta( $pid, '_yoast_wpseo_title', $title );
						update_post_meta( $pid, '_yoast_wpseo_metadesc', $meta );
					}
					if ( class_exists( 'RankMath' ) ) {
						update_post_meta( $pid, 'rank_math_title', $title );
						update_post_meta( $pid, 'rank_math_description', $meta );
					}
				}
				self::back( array( 'view' => 'drafts', 'item' => $id ), __( 'Saved.', 'hw-trailhead' ) );
				break;

			case 'reviewed':
				if ( $item && $item['draft_post_id'] ) {
					$p     = get_post( $item['draft_post_id'] );
					$facts = $p ? self::facts( $p->post_content ) : array();
					if ( count( array_intersect( array_keys( $facts ), (array) $item['checked'] ) ) < count( $facts ) ) {
						self::back( array( 'view' => 'drafts', 'item' => $id ), __( 'Tick every highlighted fact first.', 'hw-trailhead' ) );
					}
					// The highlights were for the review; the article keeps the text.
					$clean = preg_replace( '#</?mark>#i', '', $p->post_content );
					$clean = preg_replace( '#<figure data-image="[^"]*">\s*</figure>#i', '', $clean );
					kses_remove_filters();
					wp_update_post( wp_slash( array( 'ID' => $p->ID, 'post_content' => $clean ) ) );
					kses_init_filters();
					TH_Items::update( $id, array( 'stage' => 'ready' ) );
				}
				self::back( array( 'view' => 'drafts', 'item' => $id ), __( 'Reviewed. Open it in the editor to add images and publish when you\'re ready.', 'hw-trailhead' ) );
				break;

			case 'refresh_queue':
				self::queue_refresh( $post );
				self::back( array( 'view' => 'refresh_item', 'post' => $post ), __( 'Queued for Claude. The brief and the updated version appear here after the next run.', 'hw-trailhead' ) );
				break;

			case 'refresh_revise':
				$ask = $text( 'ask' );
				if ( $post && '' !== $ask && '' !== TH_Update::get( $post ) ) {
					$task = TH_Tasks::add( 'refresh_revise', 'post:' . $post, 'Revise refresh: ' . get_the_title( $post ), array( 'url' => get_permalink( $post ), 'html' => TH_Update::get( $post ), 'brief' => json_decode( (string) get_post_meta( $post, '_th_brief', true ), true ), 'ask' => $ask ) + self::house( array( 'draft', 'product', 'links', 'format' ) ) );
					update_post_meta( $post, '_th_task', $task );
				}
				self::back( array( 'view' => 'refresh_item', 'post' => $post ), __( 'Queued for Claude. The revised version appears here after the next run.', 'hw-trailhead' ) );
				break;

			case 'refresh_bulk':
				$n = 0;
				foreach ( (array) ( $_POST['interval'] ?? array() ) as $pid => $iv ) {
					$pid = (int) $pid;
					$iv  = sanitize_key( $iv );
					$was = get_post_meta( $pid, '_th_interval', true );
					if ( $pid && isset( TH_Schedule::INTERVALS[ $iv ] ) && $iv !== ( $was ? $was : 'none' ) ) {
						TH_Schedule::set_interval( $pid, $iv );
						$n++;
					}
				}
				$keep = array( 'view' => 'refresh' );
				foreach ( array( 'f', 'type', 'sort', 'p' ) as $k ) {
					if ( ! empty( $_POST[ $k ] ) ) {
						$keep[ $k ] = sanitize_key( $_POST[ $k ] );
					}
				}
				self::back( $keep, $n ? sprintf( /* translators: %d: count */ _n( 'Saved %d article.', 'Saved %d articles.', $n, 'hw-trailhead' ), $n ) : __( 'Nothing changed.', 'hw-trailhead' ) );
				break;

			case 'refresh_save':
				if ( $post ) {
					TH_Schedule::set_interval( $post, sanitize_key( $_POST['interval'] ?? '' ) );
					if ( isset( $_POST['note'] ) ) {
						update_post_meta( $post, '_th_note', mb_substr( $text( 'note' ), 0, 500 ) );
					}
					if ( isset( $_POST['standing'] ) ) {
						update_post_meta( $post, '_th_standing', mb_substr( $text( 'standing' ), 0, 1000 ) );
					}
				}
				self::back( array( 'view' => sanitize_key( $_POST['return'] ?? 'refresh_item' ), 'post' => $post ), __( 'Saved.', 'hw-trailhead' ) );
				break;

			case 'refresh_done':
				TH_Schedule::done( $post );
				self::back( array( 'view' => 'refresh' ), sprintf( /* translators: %s: title */ __( 'Marked as refreshed: %s.', 'hw-trailhead' ), get_the_title( $post ) ) );
				break;

			case 'schedule_setup':
				$c = TH_Schedule::setup();
				self::back( array( 'view' => 'refresh' ), sprintf( /* translators: 1: quarterly, 2: yearly, 3: none */ __( 'Scheduled %1$d articles quarterly and %2$d yearly. %3$d with no clicks are left unscheduled for a prune decision.', 'hw-trailhead' ), $c['quarterly'], $c['yearly'], $c['none'] ) );
				break;

			case 'task_cancel':
				TH_Tasks::cancel( (int) ( $_POST['task'] ?? 0 ) );
				self::back( array( 'view' => 'queue' ), __( 'Removed from the queue.', 'hw-trailhead' ) );
				break;

			case 'settings':
				TH_Settings::save( $_POST );
				self::back( array( 'view' => 'settings', 'tab' => sanitize_key( $_POST['tab'] ?? 'general' ) ), __( 'Settings saved.', 'hw-trailhead' ) );
				break;
		}
		// phpcs:enable
		self::back( array() );
	}

	/**
	 * Queue research, with the Ahrefs data when WordPress already fetched it.
	 *
	 * @param string[]   $seeds Seeds.
	 * @param array|null $ah    Ahrefs data.
	 * @return void
	 */
	private static function queue_research( $seeds, $ah ) {
		$ours = array();
		foreach ( $seeds as $s ) {
			$ours[ $s ] = TH_Data::rankings_for( $s );
		}
		$input = array(
			'seeds'        => $seeds,
			'country'      => TH_Settings::get( 'country' ),
			'kw_per_seed'  => (int) TH_Settings::get( 'kw_per_seed' ),
			'competitors'  => TH_Settings::competitors(),
			'our_rankings' => $ours,
		);
		if ( $ah ) {
			$input['ahrefs_data'] = $ah;
		}
		TH_Tasks::add( 'research', 'research:' . sanitize_title( implode( '-', $seeds ) ), 'Research: ' . implode( ', ', $seeds ), $input + self::house( array( 'research' ) ) );
	}

	/**
	 * The top 10 for a brief's keyword, when there is an Ahrefs key.
	 *
	 * @param string $keyword Keyword.
	 * @return array
	 */
	private static function serp_for( $keyword ) {
		if ( ! TH_Ahrefs::ready() || '' === trim( (string) $keyword ) ) {
			return array();
		}
		$r = TH_Ahrefs::run( 'Brief: ' . $keyword, function () use ( $keyword ) { return TH_Ahrefs::serp( $keyword, TH_Settings::get( 'country' ) ); } );
		return is_wp_error( $r ) || ! $r ? array() : array( 'ahrefs_serp' => $r );
	}

	/**
	 * Queue a refresh for an article: Claude writes the brief and the
	 * updated version.
	 *
	 * @param int $post Post ID.
	 * @return int Task ID, or 0.
	 */
	public static function queue_refresh( $post ) {
		$rows = TH_Schedule::rows();
		$r    = $rows[ $post ] ?? null;
		$p    = get_post( $post );
		if ( ! $r || ! $p ) {
			return 0;
		}
		$types = TH_Schedule::types();
		$task  = TH_Tasks::add(
			'refresh',
			'post:' . $post,
			'Refresh: ' . get_the_title( $post ),
			array(
				'url'            => get_permalink( $post ),
				'title'          => get_the_title( $post ),
				'last_updated'   => get_the_modified_date( 'Y-m-d', $post ),
				'numbers'        => array(
					'clicks_' . TH_Data::window_months() . '_months'      => $r['clicks'],
					'impressions_' . TH_Data::window_months() . '_months' => $r['impressions'],
					'ctr_percent'             => $r['ctr'],
					'avg_position'            => $r['position'],
					'clicks_last_90_days'     => $r['recent'],
					'clicks_previous_90_days' => $r['prior'],
				),
				'suggested_type' => $types[ $r['type'] ][0] . ': ' . $types[ $r['type'] ][2],
				'searches'       => TH_Data::queries( $post, 60 ),
				'standing_brief' => (string) get_post_meta( $post, '_th_standing', true ),
				'editor_note'    => $r['note'],
				'article_html'   => $p->post_content,
				'content_health_brief' => TH_Bridge::brief( $post ),
			) + self::house( array( 'info_gain', 'links', 'product', 'format' ) )
		);
		update_post_meta( $post, '_th_task', $task );
		delete_post_meta( $post, '_th_error' );
		return $task;
	}

	/**
	 * Once a day: queue refreshes for articles that are due, so the brief and
	 * the updated version are ready when the editor looks. Nothing goes live
	 * until the editor clicks Update.
	 *
	 * @return int Queued.
	 */
	public static function auto_queue() {
		if ( ! TH_Settings::get( 'auto_refresh' ) ) {
			return 0;
		}
		$waiting = count( array_filter( TH_Tasks::waiting(), function ( $t ) { return 'refresh' === get_post_meta( $t->ID, '_th_type', true ); } ) );
		$room    = (int) TH_Settings::get( 'auto_per_day' ) - $waiting;
		if ( $room <= 0 ) {
			return 0;
		}
		$today = TH_Schedule::today();
		$due   = array_filter( TH_Schedule::rows(), function ( $r ) use ( $today ) { return $r['interval'] && 'none' !== $r['interval'] && $r['due'] && $r['due'] <= $today && ! $r['brief'] && ! $r['task']; } );
		uasort( $due, function ( $a, $b ) { return strcmp( $a['due'], $b['due'] ) ?: $b['recent'] - $a['recent']; } );
		$n = 0;
		foreach ( array_slice( array_keys( $due ), 0, $room ) as $id ) {
			if ( self::queue_refresh( $id ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * The highlighted facts in a draft.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function facts( $html ) {
		preg_match_all( '#<mark>(.*?)</mark>#is', (string) $html, $m );
		return array_map( function ( $t ) { return trim( wp_strip_all_tags( $t ) ); }, $m[1] );
	}
}
