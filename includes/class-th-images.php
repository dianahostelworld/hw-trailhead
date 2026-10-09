<?php
/**
 * Photo search and placement from Unsplash and Pexels.
 *
 * Unsplash's API rules: photos are shown from Unsplash's own URLs
 * (hotlinked), a download is registered when a photo is used, and the
 * photographer and Unsplash are credited with links. Pexels photos are
 * copied into the Media Library and credited.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * Image search.
 */
class TH_Images {

	const UTM = '?utm_source=hostelworld_blog&utm_medium=referral';

	/**
	 * Which providers have keys.
	 *
	 * @return string[]
	 */
	public static function providers() {
		$out = array();
		if ( TH_Settings::get( 'unsplash_key' ) ) {
			$out['unsplash'] = 'Unsplash';
		}
		if ( TH_Settings::get( 'pexels_key' ) ) {
			$out['pexels'] = 'Pexels';
		}
		return $out;
	}

	/**
	 * Search one provider. Results are cached for an hour.
	 *
	 * @param string $provider unsplash or pexels.
	 * @param string $q        Search.
	 * @return array|WP_Error List of photos.
	 */
	public static function search( $provider, $q ) {
		$q = trim( $q );
		if ( '' === $q ) {
			return array();
		}
		$key    = 'th_img_' . md5( $provider . '|' . strtolower( $q ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( 'unsplash' === $provider ) {
			$res = wp_remote_get( 'https://api.unsplash.com/search/photos?' . http_build_query( array( 'query' => $q, 'per_page' => 12, 'orientation' => 'landscape', 'content_filter' => 'high' ) ), array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Client-ID ' . TH_Settings::get( 'unsplash_key' ), 'Accept-Version' => 'v1' ) ) );
		} elseif ( 'pexels' === $provider ) {
			$res = wp_remote_get( 'https://api.pexels.com/v1/search?' . http_build_query( array( 'query' => $q, 'per_page' => 12, 'orientation' => 'landscape' ) ), array( 'timeout' => 20, 'headers' => array( 'Authorization' => TH_Settings::get( 'pexels_key' ) ) ) );
		} else {
			return new WP_Error( 'th_img', __( 'Unknown photo library.', 'hw-trailhead' ) );
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			/* translators: 1: provider, 2: HTTP code */
			return new WP_Error( 'th_img', sprintf( __( '%1$s refused the search (error %2$d). Check the API key in Settings.', 'hw-trailhead' ), ucfirst( $provider ), $code ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();
		if ( 'unsplash' === $provider ) {
			foreach ( (array) ( $body['results'] ?? array() ) as $p ) {
				$out[] = array(
					'id'       => (string) $p['id'],
					'thumb'    => $p['urls']['small'] ?? '',
					'src'      => $p['urls']['regular'] ?? '',
					'alt'      => (string) ( $p['alt_description'] ?? $q ),
					'width'    => 1080,
					'height'   => ! empty( $p['width'] ) ? (int) round( 1080 * $p['height'] / $p['width'] ) : 720,
					'name'     => (string) ( $p['user']['name'] ?? '' ),
					'profile'  => (string) ( $p['user']['links']['html'] ?? '' ),
					'page'     => (string) ( $p['links']['html'] ?? '' ),
					'download' => (string) ( $p['links']['download_location'] ?? '' ),
				);
			}
		} else {
			foreach ( (array) ( $body['photos'] ?? array() ) as $p ) {
				$out[] = array(
					'id'      => (string) $p['id'],
					'thumb'   => $p['src']['medium'] ?? '',
					'src'     => $p['src']['large2x'] ?? ( $p['src']['large'] ?? '' ),
					'alt'     => (string) ( $p['alt'] ? $p['alt'] : $q ),
					'width'   => (int) ( $p['width'] ?? 0 ),
					'height'  => (int) ( $p['height'] ?? 0 ),
					'name'    => (string) ( $p['photographer'] ?? '' ),
					'profile' => (string) ( $p['photographer_url'] ?? '' ),
					'page'    => (string) ( $p['url'] ?? '' ),
				);
			}
		}
		set_transient( $key, $out, HOUR_IN_SECONDS );
		set_transient( 'th_img_last_' . get_current_user_id(), array( 'provider' => $provider, 'q' => $q ), HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Pattern for an image slot: an empty placeholder Claude left, or a photo
	 * Trailhead placed (a caption carrying th_slot with the description).
	 */
	const SLOT = '#<figure data-image="([^"]*)">\s*</figure>|\[caption[^\]]*?th_slot="([^"]*)"[^\]]*\].*?\[/caption\]#is';

	/**
	 * Image slots in a draft, in order.
	 *
	 * @param string $html Content.
	 * @return array[] desc, filled, credit, src.
	 */
	public static function slots( $html ) {
		preg_match_all( self::SLOT, (string) $html, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $x ) {
			$filled = '' !== ( $x[2] ?? '' );
			$src    = '';
			$credit = '';
			if ( $filled ) {
				preg_match( '#src="([^"]+)"#', $x[0], $sm );
				$src    = $sm[1] ?? '';
				$credit = trim( wp_strip_all_tags( preg_replace( '#^\[caption[^\]]*\]|<img[^>]*>|\[/caption\]$#', '', $x[0] ) ) );
			}
			$out[] = array( 'desc' => html_entity_decode( $filled ? $x[2] : $x[1], ENT_QUOTES ), 'filled' => $filled, 'src' => $src, 'credit' => $credit );
		}
		return $out;
	}

	/**
	 * When a draft arrives: put the best match into every empty slot,
	 * Unsplash first, then Pexels.
	 *
	 * @param int $post_id Draft.
	 * @return int Photos placed.
	 */
	public static function auto_fill( $post_id ) {
		$providers = self::providers();
		if ( ! $providers ) {
			return 0;
		}
		$n = 0;
		foreach ( self::slots( get_post_field( 'post_content', $post_id ) ) as $k => $slot ) {
			if ( $slot['filled'] ) {
				continue;
			}
			foreach ( array( 'unsplash', 'pexels' ) as $prov ) {
				if ( ! isset( $providers[ $prov ] ) ) {
					continue;
				}
				$res = self::search( $prov, $slot['desc'] );
				if ( ! is_wp_error( $res ) && $res ) {
					// Best match not already used in this article.
					$used  = get_post_field( 'post_content', $post_id );
					$pick  = $res[0];
					foreach ( $res as $r ) {
						if ( false === strpos( $used, $r['src'] ) ) {
							$pick = $r;
							break;
						}
					}
					if ( true === self::put( $post_id, $k, $prov, $pick, $slot['desc'] ) ) {
						$n++;
					}
					break;
				}
			}
		}
		return $n;
	}

	/**
	 * Place a photo the editor picked from a search.
	 *
	 * @param array  $item     Item.
	 * @param int    $slot     Slot number.
	 * @param string $provider Provider.
	 * @param string $photo_id Photo ID from the last search.
	 * @return string Message.
	 */
	public static function place( $item, $slot, $provider, $photo_id ) {
		$post = $item['draft_post_id'] ? get_post( $item['draft_post_id'] ) : null;
		$last = get_transient( 'th_img_last_' . get_current_user_id() );
		if ( ! $post || ! is_array( $last ) || $last['provider'] !== $provider ) {
			return __( 'That search has expired. Search again and pick the photo.', 'hw-trailhead' );
		}
		$photo = null;
		foreach ( (array) self::search( $provider, $last['q'] ) as $p ) {
			if ( $p['id'] === $photo_id ) {
				$photo = $p;
			}
		}
		if ( ! $photo ) {
			return __( 'Couldn\'t find that photo. Search again.', 'hw-trailhead' );
		}
		$slots = self::slots( $post->post_content );
		$desc  = $slots[ $slot ]['desc'] ?? $last['q'];
		$done  = self::put( $post->ID, $slot, $provider, $photo, $desc );
		/* translators: %s: photographer */
		return true === $done ? sprintf( __( 'Photo by %s placed, with credit.', 'hw-trailhead' ), $photo['name'] ) : $done;
	}

	/**
	 * Put a photo into slot number $slot of a draft.
	 *
	 * @param int    $post_id  Draft.
	 * @param int    $slot     Slot.
	 * @param string $provider Provider.
	 * @param array  $photo    Photo from search().
	 * @param string $desc     What the slot should show (kept for swapping).
	 * @return true|string True, or an error message.
	 */
	private static function put( $post_id, $slot, $provider, $photo, $desc ) {
		$tag = ' th_slot="' . esc_attr( str_replace( array( '"', ']', '[' ), '', $desc ) ) . '"';
		if ( 'unsplash' === $provider ) {
			// Unsplash asks apps to register every use.
			if ( $photo['download'] ) {
				wp_remote_get( $photo['download'], array( 'timeout' => 10, 'headers' => array( 'Authorization' => 'Client-ID ' . TH_Settings::get( 'unsplash_key' ) ) ) );
			}
			$img    = '<img class="aligncenter size-large" src="' . esc_url( $photo['src'] ) . '" alt="' . esc_attr( $photo['alt'] ) . '" width="' . (int) $photo['width'] . '" height="' . (int) $photo['height'] . '" />';
			$credit = 'Photo by <a href="' . esc_url( $photo['profile'] . self::UTM ) . '">' . esc_html( $photo['name'] ) . '</a> on <a href="' . esc_url( 'https://unsplash.com/' . self::UTM ) . '">Unsplash</a>';
			$block  = '[caption align="aligncenter" width="' . (int) $photo['width'] . '"' . $tag . ']' . $img . ' ' . $credit . '[/caption]';
		} else {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$att = media_sideload_image( $photo['src'], $post_id, $photo['alt'], 'id' );
			if ( is_wp_error( $att ) ) {
				/* translators: %s: error */
				return sprintf( __( 'Couldn\'t copy the photo into the Media Library: %s', 'hw-trailhead' ), $att->get_error_message() );
			}
			update_post_meta( $att, '_wp_attachment_image_alt', $photo['alt'] );
			$credit = 'Photo by <a href="' . esc_url( $photo['profile'] ) . '">' . esc_html( $photo['name'] ) . '</a> on <a href="' . esc_url( $photo['page'] ) . '">Pexels</a>';
			wp_update_post( array( 'ID' => $att, 'post_excerpt' => $credit ) );
			$src   = wp_get_attachment_image_src( $att, 'large' );
			$img   = '<img class="aligncenter size-large wp-image-' . (int) $att . '" src="' . esc_url( $src[0] ) . '" alt="' . esc_attr( $photo['alt'] ) . '" width="' . (int) $src[1] . '" height="' . (int) $src[2] . '" />';
			$block = '[caption id="attachment_' . (int) $att . '" align="aligncenter" width="' . (int) $src[1] . '"' . $tag . ']' . $img . ' ' . $credit . '[/caption]';
		}

		$k       = -1;
		$content = preg_replace_callback(
			self::SLOT,
			function ( $m ) use ( &$k, $slot, $block ) {
				$k++;
				return $k === $slot ? $block : $m[0];
			},
			get_post_field( 'post_content', $post_id )
		);
		kses_remove_filters();
		wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $content ) ) );
		kses_init_filters();
		return true;
	}
}
