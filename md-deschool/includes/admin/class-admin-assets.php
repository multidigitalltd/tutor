<?php
/**
 * Conditional admin asset loading.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin CSS/JS only on the unit and chapter edit screens.
 */
final class Admin_Assets {

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets when editing our post types.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}
		if ( ! in_array( $screen->post_type, array( Data::POST_TYPE_UNIT, Data::POST_TYPE_CHAPTER ), true ) ) {
			return;
		}

		// Needed for the media picker.
		wp_enqueue_media();

		wp_enqueue_style(
			'mdds-admin',
			MDDS_URL . 'assets/css/admin.css',
			array(),
			MDDS_VERSION
		);

		wp_enqueue_script(
			'mdds-admin',
			MDDS_URL . 'assets/js/admin.js',
			array(),
			MDDS_VERSION,
			true
		);

		wp_set_script_translations( 'mdds-admin', 'md-deschool' );
	}
}
