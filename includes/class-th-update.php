<?php
/**
 * Refresh updates: the new version of a live article that Claude wrote,
 * kept hidden with the article until the editor loads it into the editor
 * and clicks Update.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * The update lives in post meta (_th_update). The live article is never
 * changed by Trailhead or Claude.
 */
class TH_Update {

	const META = '_th_update';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'content_edit_pre', array( __CLASS__, 'load' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'marker' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'save_post_post', array( __CLASS__, 'saved' ), 10, 2 );
	}

	/**
	 * The update for an article, or ''.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	public static function get( $id ) {
		return (string) get_post_meta( $id, self::META, true );
	}

	/**
	 * Store an update Claude wrote.
	 *
	 * @param int    $id   Post ID.
	 * @param string $html HTML.
	 * @return void
	 */
	public static function set( $id, $html ) {
		$html = self::clean( $html );
		if ( '' !== trim( $html ) ) {
			update_post_meta( $id, self::META, wp_slash( $html ) );
		}
	}

	/**
	 * Clean Claude's HTML: the normal post tags, shortcodes and video embeds
	 * stay; scripts go.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function clean( $html ) {
		$html    = preg_replace( '/^```(?:html)?\s*|\s*```$/', '', trim( (string) $html ) );
		$html    = preg_replace( '#<(script|style|object|embed)\b[^>]*>.*?</\1>#is', '', $html );
		$allowed = wp_kses_allowed_html( 'post' );
		$allowed['iframe'] = array( 'src' => true, 'width' => true, 'height' => true, 'title' => true, 'allow' => true, 'allowfullscreen' => true, 'frameborder' => true, 'loading' => true );
		return wp_kses( $html, $allowed );
	}

	/**
	 * Does the live article have embed code the update can't carry over?
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public static function has_scripts( $id ) {
		return (bool) preg_match( '#<(script|object|embed)\b#i', (string) get_post_field( 'post_content', $id ) );
	}

	/**
	 * Readable lines of an article, for showing what changed.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function lines( $html ) {
		$html = preg_replace( '#\[caption[^\]]*\].*?\[/caption\]#is', "\n[photo]\n", $html );
		$html = preg_replace( '#</?(p|h[1-6]|li|ul|ol|blockquote|table|tr|div|figure)\b[^>]*>#i', "\n", $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES );
		$out  = array();
		foreach ( preg_split( '/\r?\n/', $text ) as $l ) {
			$l = trim( preg_replace( '/\s+/', ' ', $l ) );
			if ( '' !== $l ) {
				$out[] = $l;
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * What changed between the live article and the update.
	 *
	 * @param int $id Post ID.
	 * @return string Diff table HTML, or ''.
	 */
	public static function diff( $id ) {
		$new = self::get( $id );
		if ( '' === $new ) {
			return '';
		}
		return (string) wp_text_diff(
			self::lines( get_post_field( 'post_content', $id ) ),
			self::lines( $new ),
			array( 'title_left' => __( 'Live now', 'hw-trailhead' ), 'title_right' => __( 'Updated', 'hw-trailhead' ) )
		);
	}

	/**
	 * Link that opens the editor with the update loaded.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	public static function edit_url( $id ) {
		return add_query_arg( 'th_update', 1, get_edit_post_link( $id, 'raw' ) );
	}

	/**
	 * Is the editor being opened with the update?
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private static function loading( $id ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		return is_admin() && ! empty( $_GET['th_update'] ) && $id && current_user_can( 'edit_post', $id ) && '' !== self::get( $id );
	}

	/**
	 * Put the update into the Classic Editor instead of the live text.
	 *
	 * @param string $content Content.
	 * @param int    $id      Post ID.
	 * @return string
	 */
	public static function load( $content, $id ) {
		return self::loading( (int) $id ) ? self::get( (int) $id ) : $content;
	}

	/**
	 * Mark the form so clicking Update finishes the refresh.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function marker( $post ) {
		if ( self::loading( $post->ID ) ) {
			echo '<input type="hidden" name="th_refresh_apply" value="1">';
			wp_nonce_field( 'th_refresh_apply_' . $post->ID, 'th_refresh_nonce' );
		}
	}

	/**
	 * Tell the editor what they are looking at.
	 *
	 * @return void
	 */
	public static function notice() {
		global $post;
		if ( $post && 'post' === get_current_screen()->base && self::loading( $post->ID ) ) {
			echo '<div class="notice notice-info"><p><b>' . esc_html__( 'Trailhead loaded the updated version of this article.', 'hw-trailhead' ) . '</b> ' . esc_html__( 'Nothing changes on the site until you click Update. After that, the article is marked as refreshed and the next refresh date is set.', 'hw-trailhead' ) . '</p></div>';
		}
	}

	/**
	 * After the editor clicks Update on the loaded version: mark refreshed.
	 *
	 * @param int     $id   Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function saved( $id, $post ) {
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( empty( $_POST['th_refresh_apply'] ) || ! isset( $_POST['th_refresh_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['th_refresh_nonce'] ), 'th_refresh_apply_' . $id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		TH_Schedule::done( $id );
	}
}
