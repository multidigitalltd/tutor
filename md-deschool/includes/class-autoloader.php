<?php
/**
 * Lightweight PSR-4-style autoloader (no Composer dependency).
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the plugin namespace to files under /includes using the WordPress
 * file naming convention (class-foo-bar.php).
 */
final class Autoloader {

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Resolve a fully-qualified class name to a file and require it.
	 *
	 * Example: MultiDigital\DeSchool\Admin\Unit_Metaboxes
	 *          -> includes/admin/class-unit-metaboxes.php
	 *
	 * @param string $class_name Fully-qualified class name.
	 */
	public static function load( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );

		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		$sub_path = '';
		if ( ! empty( $parts ) ) {
			$sub_path = implode( '/', array_map( static fn ( string $part ): string => strtolower( str_replace( '_', '-', $part ) ), $parts ) ) . '/';
		}

		$path = MDDS_PATH . 'includes/' . $sub_path . $file_name;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
