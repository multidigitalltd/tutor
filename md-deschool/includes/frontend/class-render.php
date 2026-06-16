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
			// Already sanitised to safe iframes on save.
			return '<div class="mdds-video-embed">' . $embed . '</div>';
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
			$oembed = wp_oembed_get( $url );
			if ( $oembed ) {
				return '<div class="mdds-video-embed">' . $oembed . '</div>';
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
