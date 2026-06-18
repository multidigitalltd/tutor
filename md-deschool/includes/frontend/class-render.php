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
		if ( '' !== trim( $embed ) ) {
			// Route any YouTube/Vimeo embed — whether pasted as a URL or as full
			// <iframe> code — to the custom player (extracts the video ID).
			$built = self::provider_player( trim( $embed ), $title );
			if ( '' !== $built ) {
				return $built;
			}

			// Unknown provider iframe: sanitise and output as-is.
			return '<div class="mdds-video-embed">' . self::harden_embed( $embed ) . '</div>';
		}

		$file_id = (int) get_post_meta( $chapter_id, Data::META_VIDEO_FILE, true );
		if ( $file_id > 0 ) {
			$url = wp_get_attachment_url( $file_id );
			if ( $url ) {
				return sprintf(
					'<video class="mdds-video-file mdds-plyr-video" controls controlsList="nodownload" preload="metadata" playsinline width="100%%"><source src="%1$s" type="%2$s" />%3$s</video>',
					esc_url( $url ),
					esc_attr( (string) get_post_mime_type( $file_id ) ),
					esc_html__( 'הדפדפן שלך אינו תומך בנגן הווידאו.', 'md-deschool' )
				);
			}
		}

		$url = (string) get_post_meta( $chapter_id, Data::META_VIDEO_URL, true );
		if ( '' !== $url ) {
			$built = self::provider_player( $url, $title );
			if ( '' !== $built ) {
				return $built;
			}

			$oembed = wp_oembed_get( $url );
			if ( $oembed ) {
				return '<div class="mdds-video-embed">' . self::harden_embed( $oembed ) . '</div>';
			}

			return self::iframe( $url, $title );
		}

		return '';
	}

	/**
	 * Build a Plyr-enhanced player for a known provider URL.
	 *
	 * Plyr (the same player Tutor LMS uses) wraps YouTube/Vimeo with its own
	 * skin and controls so the source provider's chrome is not shown. If Plyr
	 * fails to load, the embedded iframe still plays natively.
	 *
	 * @param string $url   Source URL.
	 * @param string $title Accessible title.
	 * @return string Player HTML, or '' if the provider is unknown.
	 */
	private static function provider_player( string $url, string $title ): string {
		$youtube = self::youtube_id( $url );
		if ( '' !== $youtube ) {
			$src = 'https://www.youtube.com/embed/' . rawurlencode( $youtube )
				. '?rel=0&showinfo=0&iv_load_policy=3&modestbranding=1&playsinline=1&enablejsapi=1';

			return sprintf(
				'<div class="mdds-plyr"><div class="plyr__video-embed"><iframe src="%1$s" title="%2$s" allowfullscreen allowtransparency allow="autoplay; encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe></div></div>',
				esc_url( $src ),
				esc_attr( $title )
			);
		}

		$vimeo = self::vimeo_id( $url );
		if ( '' !== $vimeo ) {
			$src = 'https://player.vimeo.com/video/' . rawurlencode( $vimeo )
				. '?loop=false&byline=false&portrait=false&title=false&transparent=0';

			return sprintf(
				'<div class="mdds-plyr"><div class="plyr__video-embed"><iframe src="%1$s" title="%2$s" allowfullscreen allowtransparency allow="autoplay; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe></div></div>',
				esc_url( $src ),
				esc_attr( $title )
			);
		}

		return '';
	}

	/**
	 * Build a responsive, accessible embed iframe.
	 *
	 * @param string $src   Embed URL.
	 * @param string $title Accessible title.
	 * @return string
	 */
	private static function iframe( string $src, string $title ): string {
		return sprintf(
			'<div class="mdds-video-embed"><iframe src="%1$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
			esc_url( $src ),
			esc_attr( $title )
		);
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
		// Keep the provider/domain the author pasted (works reliably); only
		// reduce branding/related videos on YouTube embeds.
		return (string) preg_replace_callback(
			'~(youtube(?:-nocookie)?\.com/embed/[A-Za-z0-9_-]{11})(\?[^"\'\s]*)?~i',
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
