<?php
/**
 * Conditional front-end asset loading.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Loads front-end CSS/JS only on single unit pages.
 */
final class Assets {

	public const HANDLE = 'mdds-frontend';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets only on a single unit page.
	 */
	public function enqueue(): void {
		if ( ! is_singular( Data::POST_TYPE_UNIT ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			MDDS_URL . 'assets/css/frontend.css',
			array(),
			MDDS_VERSION
		);

		// Only enqueue interactive JS for logged-in users with access.
		$unit_id = get_queried_object_id();
		if ( ! Access_Control::can_access( $unit_id ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			MDDS_URL . 'assets/js/frontend.js',
			array(),
			MDDS_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'mddsData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::NONCE_ACTION ),
				'unitId'  => $unit_id,
				'i18n'    => array(
					'saving'     => __( 'שומר…', 'md-deschool' ),
					'saved'      => __( 'נשמר בהצלחה', 'md-deschool' ),
					'error'      => __( 'אירעה שגיאה, נסו שוב', 'md-deschool' ),
					'uploading'  => __( 'מעלה קובץ…', 'md-deschool' ),
					'fileTooBig' => __( 'הקובץ גדול מדי', 'md-deschool' ),
					'completed'  => __( 'הפרק הושלם', 'md-deschool' ),
					'markDone'   => __( 'סימון כהושלם והמשך', 'md-deschool' ),
				),
			)
		);
	}
}
