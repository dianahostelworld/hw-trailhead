<?php
/**
 * The queue Claude works through.
 *
 * Each piece of work is a private "Trailhead task" post. Its content holds
 * plain-English instructions and the data as JSON. Claude (a scheduled task
 * on the editor's own Claude account, using the WordPress and Ahrefs
 * connectors) finds pending tasks, does the work, writes its answer as JSON
 * under the RESULT line and sets the task to draft. Trailhead then applies
 * the answer: ideas, briefs, drafts or refresh briefs. Claude never edits
 * articles and never publishes; Trailhead creates drafts itself.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue of work for Claude.
 */
class TH_Tasks {

	const TYPE    = 'trailhead_task';
	const PENDING = 'RESULT_PENDING';
	const MARK_T  = '=== TASK ===';
	const MARK_R  = '=== RESULT ===';

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'label'               => __( 'Trailhead tasks', 'hw-trailhead' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => true,
				'rest_base'           => 'trailhead-tasks',
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * What each task type asks Claude to do, and the answer it expects.
	 *
	 * @param string $type Type.
	 * @return array instructions, output.
	 */
	public static function spec( $type ) {
		$rules = 'Follow everything in "house": the style guide, the playbooks for this step, the author\'s voice if one is given, and hostelworld_facts as the source of truth about Hostelworld and its products. Never publish anything and never edit articles. Only change this task post: put your answer as one JSON object in place of the placeholder line at the very end of this post, then set this post\'s status to draft. If you cannot do the task, answer {"error": "what went wrong, in one sentence"}. Never invent numbers, prices, quotes or reviews.';
		$idea  = '{"ideas": [{"title": "working title with character", "keyword": "main keyword exactly as Ahrefs gave it", "cluster": ["other keywords one article would target"], "verdict": "new" | "refresh" | "skip", "score": 0-100, "reason": "one or two sentences citing the numbers", "our_url": "our ranking article URL or null", "volume": number or null, "difficulty": number or null, "competitors": [{"domain": "", "position": 1, "url": ""}]}]}';
		$brief = '{"titles": ["three title options with character"], "intent": "", "length": "e.g. About 1,600 words", "secondary": ["keywords to cover"], "outline": [{"h2": "", "notes": ""}], "questions": ["questions readers ask"], "links": ["internal link URLs, only from related_pages"], "seoTitle": "under 60 characters", "meta": "under 155 characters", "sources": [{"what": "the fact, angle or quote this adds", "url": "where it comes from", "kind": "news | official | reddit | social | study | ahrefs | editor"}], "blocks": ["which blocks_library shortcode goes in which section, and why"], "check": ["facts or assumptions the editor should verify"]}';
		$html  = '{"html": "the article as HTML using only h2, h3, p, ul, ol, li, strong, em, a, blockquote. Wrap every claim that can go out of date or that you are not sure of (prices, opening hours, dates, transport, numbers, business names) in <mark>. Where a photo should go add <figure data-image=\"what the photo should show\"></figure>. Internal links only from the brief\'s links. Use shortcodes only from house.blocks_library, written exactly as its exact_syntax with the right values for this article, and only where they fit; never invent a shortcode. Link to the sources in the brief where a fact comes from them. No h1, no styles, no markdown."}';

		$specs = array(
			'research' => array(
				'Research article ideas for the Hostelworld blog (budget travel, hostels, backpackers). If input.ahrefs_data is present, it already holds the Ahrefs keywords for each seed and what each competitor ranks top 10 for: use it and do not call the Ahrefs connector. Otherwise use the Ahrefs connector. For each seed: keywords-explorer-matching-terms with the given country, select "keyword,volume,difficulty,parent_topic", limit kw_per_seed, order_by "volume:desc". For each competitor and seed: site-explorer-organic-keywords with mode "subdomains", date two days ago, the given country, keywords containing the seed and best_position at most 10, limit 15, select "keyword,volume,keyword_difficulty,best_position,best_position_url". Compare with our_rankings (our real Search Console data). Group keywords one article would target into one idea; return up to 15 ideas. verdict "refresh" when one of our articles already ranks 4-30 for the main keyword (give its URL in our_url); "new" when we have nothing and it fits hostel travellers; "skip" when it does not fit, is branded or we already rank top 3. score = value to us: volume, realistic difficulty, competitor proof, fit.',
				$idea,
			),
			'gaps'     => array(
				'Find competitor content gaps for the Hostelworld blog. If input.ahrefs_data is present, it already holds each competitor\'s top 10 keywords with the ones we appear for removed: use it and do not call the Ahrefs connector. Otherwise use the Ahrefs connector: for each competitor, site-explorer-organic-keywords with mode "subdomains", date two days ago, the given country, select "keyword,volume,keyword_difficulty,best_position,best_position_url", where best_position at most 10, volume at least 200, is_informational true and is_branded false, order_by "sum_traffic:desc", limit 60. Drop any keyword in our_searches (we already appear for it). Group what is left into up to 15 article ideas for hostel travellers, verdict "new" or "skip", scored as for research.',
				$idea,
			),
			'brief'    => array(
				'Write a content brief for this Hostelworld blog idea. Use the idea data, the editor\'s direction and notes, and related_pages. See what the top results cover: if input.ahrefs_serp is present it holds the current top 10 from Ahrefs, so use it and do not call the Ahrefs connector for it; otherwise use the Ahrefs connector (serp-overview for the main keyword). content-helper-topics with the Ahrefs connector is optional. Then add information gain: use web search for recent news, official sources and what travellers say on Reddit and social media, plus anything in the editor\'s notes or interviews, and list each addition with its source URL under sources. Plan where shortcodes from house.blocks_library fit.',
				$brief,
			),
			'brief_revise' => array(
				'Revise this content brief as the editor asks. Keep everything the editor did not ask to change, including sources unless asked.',
				$brief,
			),
			'draft'    => array(
				'Write the full Hostelworld blog article from the approved brief, in the author\'s voice, following the brief\'s length, outline, sources and block plan.',
				$html,
			),
			'draft_revise' => array(
				'Revise this Hostelworld blog article as the editor asks. Keep everything the editor did not ask to change, including <mark>, <figure> markers, shortcodes and photos already placed.',
				$html,
			),
			'refresh'  => array(
				'Refresh this existing Hostelworld blog article. You have its current HTML (article_html), its numbers and the searches it appears for (last 90 days, from Search Console). First work out what to change: what readers search for that the article does not answer well (high impressions, low clicks, positions 4-20), what has gone or will go out of date, what the standing brief asks for every refresh, what the editor\'s note asks, and everything content_health_brief flags (searches, unanswered questions, outdated years, on-page issues, broken links, link suggestions, overlapping articles, seasonality), when it is given. Use web search to check facts that may have changed (prices, opening times, transport, events, new places) and give sources. Be specific: name sections and searches. Then write the full updated article in "html": start from article_html and keep everything that does not need to change exactly as it is, including links, [caption] photos, shortcodes, embeds and headings. Classic Editor HTML, no Markdown, no <mark>, no new image placeholders. The editor reviews it before anything goes live.',
				'{"summary": "two sentences", "priority": "high" | "medium" | "low", "changes": [{"what": "specific change", "why": "citing the numbers or the source"}], "queries": ["up to 10 searches to cover better, copied from the list"], "seoTitle": "new SEO title or empty", "meta": "new meta description or empty", "facts": ["things to re-check"], "sources": [{"what": "", "url": "", "kind": ""}], "html": "the full updated article"}',
			),
			'refresh_revise' => array(
				'Revise this updated version of a live Hostelworld blog article as the editor asks. Keep everything the editor did not ask to change, including links, [caption] photos, shortcodes and embeds. Classic Editor HTML, no Markdown, no <mark>.',
				'{"html": "the full revised article"}',
			),
		);

		$s = isset( $specs[ $type ] ) ? $specs[ $type ] : array( '', '{}' );
		return array( 'instructions' => $s[0] . ' ' . $rules, 'output' => $s[1] );
	}

	/**
	 * Add a task. Any waiting task of the same type for the same target is
	 * replaced, so the queue never holds two of the same thing.
	 *
	 * @param string $type   Type.
	 * @param string $target 'item:ID', 'post:ID' or 'global'.
	 * @param string $label  Human label.
	 * @param array  $input  Data for Claude.
	 * @return int Task ID.
	 */
	public static function add( $type, $target, $label, $input ) {
		foreach ( self::waiting( $target ) as $old ) {
			if ( get_post_meta( $old->ID, '_th_type', true ) === $type ) {
				wp_delete_post( $old->ID, true );
			}
		}
		$spec    = self::spec( $type );
		$payload = array(
			'type'         => $type,
			'instructions' => $spec['instructions'],
			'input'        => $input,
			'output'       => $spec['output'],
		);
		$content = "Trailhead task for Claude. Read the JSON below, do what \"instructions\" says, then replace the placeholder line at the very end with your answer as JSON in the \"output\" shape, and set this post's status to draft.\n\n"
			. self::MARK_T . "\n" . wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n\n" . self::MARK_R . "\n" . self::PENDING . "\n";

		kses_remove_filters();
		$id = wp_insert_post(
			array(
				'post_type'    => self::TYPE,
				'post_status'  => 'pending',
				'post_title'   => '[Trailhead] ' . $label,
				'post_content' => wp_slash( $content ),
			),
			true
		);
		kses_init_filters();

		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_th_type', $type );
		update_post_meta( $id, '_th_target', $target );
		update_post_meta( $id, '_th_label', $label );
		return (int) $id;
	}

	/**
	 * Tasks waiting for Claude.
	 *
	 * @param string|null $target Only this target.
	 * @return WP_Post[]
	 */
	public static function waiting( $target = null ) {
		$args = array(
			'post_type'        => self::TYPE,
			'post_status'      => 'pending',
			'posts_per_page'   => 200,
			'orderby'          => 'date',
			'order'            => 'ASC',
			'suppress_filters' => true,
		);
		if ( $target ) {
			$args['meta_key']   = '_th_target'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $target; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return get_posts( $args );
	}

	/**
	 * Whether a target has work waiting, and since when.
	 *
	 * @param string $target Target.
	 * @return array|null type, since.
	 */
	public static function waiting_for( $target ) {
		$w = self::waiting( $target );
		if ( ! $w ) {
			return null;
		}
		return array( 'type' => get_post_meta( $w[0]->ID, '_th_type', true ), 'since' => $w[0]->post_date_gmt, 'id' => $w[0]->ID );
	}

	/**
	 * Cancel a waiting task.
	 *
	 * @param int $id Task ID.
	 * @return void
	 */
	public static function cancel( $id ) {
		$post = get_post( $id );
		if ( $post && self::TYPE === $post->post_type && 'pending' === $post->post_status ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * The JSON answer in a task, if Claude has written one.
	 *
	 * @param WP_Post $post Task.
	 * @return array|null
	 */
	public static function result( $post ) {
		$content = (string) $post->post_content;
		$pos     = strrpos( $content, self::MARK_R );
		if ( false === $pos ) {
			return null;
		}
		$text = trim( substr( $content, $pos + strlen( self::MARK_R ) ) );
		if ( '' === $text || 0 === strpos( $text, self::PENDING ) ) {
			return null;
		}
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $text );
		// A connector may save the answer wrapped in a paragraph or a block
		// comment, or with entities encoded. Try the plain text first.
		$unwrapped  = preg_replace( '#^(\s*<!--.*?-->\s*)*(<p>)?|(</p>)?(\s*<!--.*?-->\s*)*$#s', '', $text );
		$candidates = array( $text, html_entity_decode( $text, ENT_QUOTES ), $unwrapped, html_entity_decode( $unwrapped, ENT_QUOTES ) );
		foreach ( $candidates as $c ) {
			$json = json_decode( $c, true );
			if ( ! is_array( $json ) ) {
				$start = strpos( $c, '{' );
				$end   = strrpos( $c, '}' );
				$json  = ( false !== $start && false !== $end ) ? json_decode( substr( $c, $start, $end - $start + 1 ), true ) : null;
			}
			if ( is_array( $json ) ) {
				return $json;
			}
		}
		return array( 'error' => __( 'Claude answered, but not in the expected format. Ask again.', 'hw-trailhead' ) );
	}

	/**
	 * Apply every finished task. Runs when Trailhead's screens load.
	 *
	 * @return int Tasks applied.
	 */
	public static function collect() {
		$done = get_posts(
			array(
				'post_type'        => self::TYPE,
				'post_status'      => array( 'draft', 'pending' ),
				'posts_per_page'   => 100,
				'suppress_filters' => true,
			)
		);
		$n = 0;
		foreach ( $done as $post ) {
			$result = self::result( $post );
			if ( null === $result ) {
				continue;
			}
			self::apply( get_post_meta( $post->ID, '_th_type', true ), (string) get_post_meta( $post->ID, '_th_target', true ), $result, $post );
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'private' ) );
			$n++;
		}
		return $n;
	}

	/**
	 * Apply one answer.
	 *
	 * @param string  $type   Type.
	 * @param string  $target Target.
	 * @param array   $r      Answer.
	 * @param WP_Post $task   Task post.
	 * @return void
	 */
	private static function apply( $type, $target, $r, $task ) {
		list( $kind, $tid ) = array_pad( explode( ':', $target ), 2, 0 );
		$tid = (int) $tid;
		$err = isset( $r['error'] ) ? sanitize_text_field( $r['error'] ) : '';

		if ( in_array( $type, array( 'research', 'gaps' ), true ) ) {
			if ( $err ) {
				update_option( 'th_last_research_error', $err, false );
				return;
			}
			delete_option( 'th_last_research_error' );
			$ours  = 'gaps' === $type ? self::our_searches_set() : array();
			$added = 0;
			foreach ( array_slice( (array) ( $r['ideas'] ?? array() ), 0, 20 ) as $x ) {
				if ( ! is_array( $x ) || empty( $x['keyword'] ) ) {
					continue;
				}
				if ( $ours && isset( $ours[ strtolower( (string) $x['keyword'] ) ] ) ) {
					continue;
				}
				TH_Items::insert(
					array(
						'title'   => sanitize_text_field( $x['title'] ?? $x['keyword'] ),
						'keyword' => sanitize_text_field( $x['keyword'] ),
						'verdict' => $x['verdict'] ?? 'new',
						'score'   => $x['score'] ?? 0,
						'reason'  => sanitize_textarea_field( $x['reason'] ?? '' ),
						'data'    => array(
							'cluster'     => array_map( 'sanitize_text_field', array_slice( (array) ( $x['cluster'] ?? array() ), 0, 20 ) ),
							'our_url'     => isset( $x['our_url'] ) && $x['our_url'] ? esc_url_raw( $x['our_url'] ) : '',
							'volume'      => isset( $x['volume'] ) ? (int) $x['volume'] : null,
							'kd'          => isset( $x['difficulty'] ) ? (int) $x['difficulty'] : null,
							'competitors' => array_slice( array_map( function ( $c ) { return array( 'domain' => sanitize_text_field( $c['domain'] ?? '' ), 'position' => (int) ( $c['position'] ?? 0 ), 'url' => esc_url_raw( $c['url'] ?? '' ) ); }, array_filter( (array) ( $x['competitors'] ?? array() ), 'is_array' ) ), 0, 6 ),
							'source'      => 'gaps' === $type ? 'Competitor gap' : 'Research: ' . get_post_meta( $task->ID, '_th_label', true ),
						),
					)
				);
				$added++;
			}
			update_option( 'th_last_research', array( 'at' => current_time( 'mysql', true ), 'added' => $added, 'label' => get_post_meta( $task->ID, '_th_label', true ) ), false );
			return;
		}

		if ( 'item' === $kind ) {
			$item = TH_Items::get( $tid );
			if ( ! $item ) {
				return;
			}
			if ( $err ) {
				TH_Items::merge_data( $tid, array( 'error' => $err ) );
				return;
			}
			$data = $item['data'];
			unset( $data['error'] );

			if ( in_array( $type, array( 'brief', 'brief_revise' ), true ) && ! empty( $r['outline'] ) ) {
				$r['chosen'] = isset( $item['brief']['chosen'] ) ? (int) $item['brief']['chosen'] : 0;
				TH_Items::update( $tid, array( 'brief' => self::clean_deep( $r ), 'data' => $data ) );
			}
			if ( in_array( $type, array( 'draft', 'draft_revise' ), true ) && ! empty( $r['html'] ) ) {
				$html  = self::clean_html( (string) $r['html'] );
				$brief = (array) $item['brief'];
				$title = $brief['titles'][ (int) ( $brief['chosen'] ?? 0 ) ] ?? $item['title'];
				$post  = $item['draft_post_id'] ? get_post( $item['draft_post_id'] ) : null;
				kses_remove_filters();
				if ( $post && in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
					wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $html ) ) );
					$pid = $post->ID;
				} else {
					$pid = (int) wp_insert_post( wp_slash( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => sanitize_text_field( $title ), 'post_content' => $html ) ) );
				}
				kses_init_filters();
				if ( $pid ) {
					update_post_meta( $pid, '_th_item', $tid );
					if ( ! empty( $brief['seoTitle'] ) && ! get_post_meta( $pid, '_th_seo_title', true ) ) {
						update_post_meta( $pid, '_th_seo_title', sanitize_text_field( $brief['seoTitle'] ) );
						update_post_meta( $pid, '_th_meta', sanitize_text_field( $brief['meta'] ?? '' ) );
					}
					TH_Items::update( $tid, array( 'draft_post_id' => $pid, 'stage' => 'draft', 'checked' => array(), 'data' => $data ) );
					TH_Images::auto_fill( $pid );
					if ( ! empty( $item['data']['plan_id'] ) ) {
						TH_Bridge::plan_drafted( (int) $item['data']['plan_id'], $pid );
					}
				}
			}
			return;
		}

		if ( 'post' === $kind && $tid ) {
			delete_post_meta( $tid, '_th_task' );
			if ( $err ) {
				update_post_meta( $tid, '_th_error', $err );
				return;
			}
			delete_post_meta( $tid, '_th_error' );
			$html = isset( $r['html'] ) ? (string) $r['html'] : '';
			unset( $r['html'] );
			if ( 'refresh' === $type ) {
				update_post_meta( $tid, '_th_brief', wp_slash( wp_json_encode( self::clean_deep( $r ) ) ) );
			}
			if ( '' !== trim( $html ) ) {
				TH_Update::set( $tid, $html );
			}
		}
	}

	/**
	 * Searches the blog already appears for, as a lookup set.
	 *
	 * @return array
	 */
	public static function our_searches_set() {
		global $wpdb;
		if ( ! TH_Data::ready() ) {
			return array();
		}
		$set = array();
		foreach ( (array) $wpdb->get_col( 'SELECT DISTINCT query FROM ' . HWCH_Store::table( 'queries' ) ) as $q ) { // phpcs:ignore WordPress.DB
			$set[ strtolower( $q ) ] = true;
		}
		return $set;
	}

	/**
	 * Allowed HTML for drafts: the usual post tags plus <mark> and the image
	 * placeholders.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function clean_html( $html ) {
		$html    = preg_replace( '/^```(?:html)?\s*|\s*```$/', '', trim( $html ) );
		$html    = preg_replace( '#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html );
		$allowed = array(
			'h2'         => array(),
			'h3'         => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'strong'     => array(),
			'em'         => array(),
			'b'          => array(),
			'i'          => array(),
			'blockquote' => array(),
			'br'         => array(),
			'mark'       => array(),
			'figure'     => array( 'data-image' => true ),
			'a'          => array( 'href' => true ),
		);
		return wp_kses( $html, $allowed );
	}

	/**
	 * Sanitise every string in an answer.
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private static function clean_deep( $v ) {
		if ( is_array( $v ) ) {
			return array_map( array( __CLASS__, 'clean_deep' ), $v );
		}
		return is_string( $v ) ? sanitize_textarea_field( $v ) : $v;
	}

	/**
	 * The prompt for the scheduled task on the editor's Claude account.
	 *
	 * @return string
	 */
	public static function claude_prompt() {
		return "Work through the Trailhead queue on the Hostelworld blog.\n\n"
			. "1. Using the WordPress connector, list posts of type \"" . self::TYPE . "\" (REST base \"trailhead-tasks\") with status \"pending\", oldest first.\n"
			. "2. For each one: read its content. Between \"" . self::MARK_T . "\" and \"" . self::MARK_R . "\" is a JSON object with \"instructions\", \"input\" and \"output\". Do what the instructions say, using the Ahrefs connector where they ask for it.\n"
			. "3. Update that same task post: replace the line " . self::PENDING . " with your answer as one JSON object in the \"output\" shape, and set the post's status to \"draft\".\n\n"
			. "Rules: only ever change " . self::TYPE . " posts. Never create, edit, publish or delete articles, pages or anything else. Never invent statistics, prices, quotes or reviews. If a task can't be done, answer {\"error\": \"one sentence on why\"}. Do at most 10 tasks per run, then stop and list what you did.";
	}
}
