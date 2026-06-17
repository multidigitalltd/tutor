<?php
/**
 * Safe HTML rendering helpers for unit/chapter media.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Produces escaped, lazy-loaded, accessible media markup.
 */
final class Render {

	/**
	 * Render a chapter's video.
	 *
	 * Priority: explicit embed code, then uploaded file, then oEmbed/iframe URL.
	 *
	 * @param int    $chapter_id Chapter ID.
	 * @param string $title      Accessible title for the player.
	 * @return string
	 */
	public static function video( int $chapter_id, string $title ): string {
		$embed = (string) get_post_meta( $chapter_id, Data::META_VIDEO_EMBED, true );
		if ( '' !== $embed ) {
			// Already sanitised to safe iframes on save; harden YouTube privacy.
			return '<div class="mdds-video-embed">' . self::harden_embed( $embed ) . '</div>';
		}

		$file_id = (int) get_post_meta( $chapter_id, Data::META_VIDEO_FILE, true );
		if ( $file_id > 0 ) {
			$url = wp_get_attachment_url( $file_id );
			if ( $url ) {
				return sprintf(
					'<video class="mdds-video-file" controls preload="none" playsinline width="100%%"><source src="%1$s" type="%2$s" />%3$s</video>',
					esc_url( $url ),
					esc_attr( (string) get_post_mime_type( $file_id ) ),
					esc_html__( 'הדפדפן שלך אינו תומך בנגן הווידאו.', 'md-deschool' )
				);
			}
		}

		$url = (string) get_post_meta( $chapter_id, Data::META_VIDEO_URL, true );
		if ( '' !== $url ) {
			// Build a privacy-friendly, unbranded player for known providers.
			$privacy = self::privacy_embed_url( $url );
			if ( '' !== $privacy ) {
				return sprintf(
					'<div class="mdds-video-embed"><iframe src="%1$s" title="%2$s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
					esc_url( $privacy ),
					esc_attr( $title )
				);
			}

			$oembed = wp_oembed_get( $url );
			if ( $oembed ) {
				return '<div class="mdds-video-embed">' . self::harden_embed( $oembed ) . '</div>';
			}

			return sprintf(
				'<div class="mdds-video-embed"><iframe src="%1$s" title="%2$s" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
				esc_url( $url ),
				esc_attr( $title )
			);
		}

		return '';
	}

	/**
	 * Build an unbranded, privacy-friendly embed URL for known providers.
	 *
	 * YouTube → youtube-nocookie.com with rel/modestbranding so the player
	 * does not advertise YouTube; Vimeo → player without the byline/portrait.
	 *
	 * @param string $url Source URL.
	 * @return string Embed URL, or '' if the provider is unknown.
	 */
	private static function privacy_embed_url( string $url ): string {
		$youtube = self::youtube_id( $url );
		if ( '' !== $youtube ) {
			return add_query_arg(
				array(
					'rel'            => 0,
					'modestbranding' => 1,
					'playsinline'    => 1,
					'iv_load_policy' => 3,
				),
				'https://www.youtube-nocookie.com/embed/' . rawurlencode( $youtube )
			);
		}

		$vimeo = self::vimeo_id( $url );
		if ( '' !== $vimeo ) {
			return add_query_arg(
				array(
					'byline'   => 0,
					'portrait' => 0,
					'title'    => 0,
					'dnt'      => 1,
				),
				'https://player.vimeo.com/video/' . rawurlencode( $vimeo )
			);
		}

		return '';
	}

	/**
	 * Extract a YouTube video ID from a URL.
	 *
	 * @param string $url URL.
	 * @return string Video ID or ''.
	 */
	private static function youtube_id( string $url ): string {
		if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|v/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Extract a Vimeo video ID from a URL.
	 *
	 * @param string $url URL.
	 * @return string Video ID or ''.
	 */
	private static function vimeo_id( string $url ): string {
		if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~i', $url, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Harden a raw embed/oEmbed iframe: prefer the no-cookie YouTube host and
	 * strip the YouTube branding parameters.
	 *
	 * @param string $html Embed HTML.
	 * @return string
	 */
	private static function harden_embed( string $html ): string {
		$html = str_replace(
			array( 'https://www.youtube.com/embed/', 'https://youtube.com/embed/' ),
			'https://www.youtube-nocookie.com/embed/',
			$html
		);

		// Nudge the player to hide related videos / branding when no query is set.
		$html = preg_replace_callback(
			'~(youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11})(\?[^"\'\s]*)?~i',
			static function ( array $m ): string {
				$base  = $m[1];
				$query = isset( $m[2] ) ? ltrim( $m[2], '?' ) : '';
				parse_str( $query, $params );
				$params['rel']            = '0';
				$params['modestbranding'] = '1';
				return $base . '?' . http_build_query( $params );
			},
			$html
		);

		return (string) $html;
	}

	/**
	 * Render a chapter's presentation block (view / download).
	 *
	 * @param int $chapter_id Chapter ID.
	 * @return string
	 */
	public static function presentation( int $chapter_id ): string {
		$embed = (string) get_post_meta( $chapter_id, Data::META_PRES_EMBED, true );
		if ( '' !== $embed ) {
			return '<div class="mdds-presentation-embed">' . $embed . '</div>';
		}

		$file_id = (int) get_post_meta( $chapter_id, Data::META_PRES_FILE, true );
		$url     = (string) get_post_meta( $chapter_id, Data::META_PRES_URL, true );

		if ( $file_id > 0 ) {
			$file_url = wp_get_attachment_url( $file_id );
			if ( $file_url ) {
				$url = $file_url;
			}
		}

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<div class="mdds-presentation-actions">
				<a class="mdds-button mdds-button-outline" href="%1$s" target="_blank" rel="noopener">%2$s</a>
				<a class="mdds-button mdds-button-outline" href="%1$s" download>%3$s</a>
			</div>',
			esc_url( $url ),
			esc_html__( 'צפייה במצגת', 'md-deschool' ),
			esc_html__( 'הורדת מצגת', 'md-deschool' )
		);
	}
}
