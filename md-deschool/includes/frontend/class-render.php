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
			// A full iframe embed code: sanitise + harden YouTube privacy.
			if ( false !== stripos( $embed, '<iframe' ) ) {
				return '<div class="mdds-video-embed">' . self::harden_embed( $embed ) . '</div>';
			}
			// A bare URL pasted into the embed box: treat it like the URL field.
			$privacy = self::privacy_embed_url( trim( $embed ) );
			if ( '' !== $privacy ) {
				return self::facade( $privacy, $title );
			}

			return '<div class="mdds-video-embed">' . self::harden_embed( $embed ) . '</div>';
		}

		$file_id = (int) get_post_meta( $chapter_id, Data::META_VIDEO_FILE, true );
		if ( $file_id > 0 ) {
			$url = wp_get_attachment_url( $file_id );
			if ( $url ) {
				return sprintf(
					'<video class="mdds-video-file" controls preload="metadata" playsinline width="100%%"><source src="%1$s" type="%2$s" />%3$s</video>',
					esc_url( $url ),
					esc_attr( (string) get_post_mime_type( $file_id ) ),
					esc_html__( 'הדפדפן שלך אינו תומך בנגן הווידאו.', 'md-deschool' )
				);
			}
		}

		$url = (string) get_post_meta( $chapter_id, Data::META_VIDEO_URL, true );
		if ( '' !== $url ) {
			// Known providers get a clean facade that hides the source until play.
			$privacy = self::privacy_embed_url( $url );
			if ( '' !== $privacy ) {
				return self::facade( $privacy, $title );
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
	 * Build a click-to-play facade: a clean poster with a custom play button
	 * (no provider branding) that loads the player only on click.
	 *
	 * @param string $src   Provider embed URL (without autoplay).
	 * @param string $title Accessible title.
	 * @return string
	 */
	private static function facade( string $src, string $title ): string {
		$autoplay = add_query_arg( 'autoplay', 1, $src );

		return sprintf(
			'<div class="mdds-video-embed mdds-video-facade" data-mdds-video="%1$s">'
			. '<button type="button" class="mdds-video-play" aria-label="%2$s"><span class="mdds-video-play-icon" aria-hidden="true"></span></button>'
			. '<noscript><iframe src="%3$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></noscript>'
			. '</div>',
			esc_url( $autoplay ),
			esc_attr( $title ),
			esc_url( $src )
		);
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
			// Standard youtube.com/embed for maximum compatibility, with reduced
			// branding and related videos. youtube-nocookie is blocked in some
			// environments and can show a black screen.
			return add_query_arg(
				array(
					'rel'            => 0,
					'modestbranding' => 1,
					'playsinline'    => 1,
				),
				'https://www.youtube.com/embed/' . rawurlencode( $youtube )
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
