<?php
/**
 * Front-end template loading with theme override support.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the single-unit template and renders reusable template parts.
 */
final class Template_Loader {

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'template_include' ) );
	}

	/**
	 * Swap in our single-unit template when viewing a unit.
	 *
	 * Themes may override by providing md-deschool/single-md-unit.php.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! is_singular( Data::POST_TYPE_UNIT ) ) {
			return $template;
		}

		$theme = locate_template( array( 'md-deschool/single-md-unit.php' ) );
		if ( '' !== $theme ) {
			return $theme;
		}

		return MDDS_PATH . 'templates/single-md-unit.php';
	}

	/**
	 * Render a template part, allowing theme overrides.
	 *
	 * @param string              $name Part name (without extension).
	 * @param array<string,mixed> $args Variables exposed to the part.
	 */
	public static function get_part( string $name, array $args = array() ): void {
		$name     = sanitize_file_name( $name );
		$relative = 'md-deschool/parts/' . $name . '.php';
		$theme    = locate_template( array( $relative ) );

		$file = '' !== $theme ? $theme : MDDS_PATH . 'templates/parts/' . $name . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		// Expose $args to the template part.
		load_template( $file, false, $args );
	}
}
