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
		$is_single  = is_singular( Data::POST_TYPE_UNIT );
		$is_archive = is_post_type_archive( Data::POST_TYPE_UNIT );

		if ( ! $is_single && ! $is_archive ) {
			return;
		}

		self::enqueue_style();

		// The interactive stepper JS is only needed on a single unit the user can access.
		if ( ! $is_single ) {
			return;
		}

		$unit_id = get_queried_object_id();
		if ( Access_Control::can_access( $unit_id ) ) {
			self::enqueue_script( $unit_id );
		}
	}

	/**
	 * Enqueue the stylesheet (idempotent).
	 */
	public static function enqueue_style(): void {
		if ( ! wp_style_is( self::HANDLE, 'enqueued' ) ) {
			wp_enqueue_style( self::HANDLE, MDDS_URL . 'assets/css/frontend.css', array(), MDDS_VERSION );
		}
	}

	/**
	 * Enqueue and localise the interactive script for a unit (idempotent).
	 *
	 * Public so the [deschool_course] shortcode can load assets when our
	 * template was bypassed (e.g. inside an Elementor template).
	 *
	 * @param int $unit_id Unit ID.
	 */
	public static function enqueue_script( int $unit_id ): void {
		self::enqueue_style();

		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		// Plyr media player (same library Tutor LMS uses) — wraps YouTube/Vimeo
		// with a custom skin so the provider's branding/chrome is not shown.
		wp_enqueue_style( 'plyr', 'https://cdn.plyr.io/3.7.8/plyr.css', array(), '3.7.8' );
		wp_enqueue_script( 'plyr', 'https://cdn.plyr.io/3.7.8/plyr.polyfilled.js', array(), '3.7.8', true );

		wp_enqueue_script( self::HANDLE, MDDS_URL . 'assets/js/frontend.js', array( 'plyr' ), MDDS_VERSION, true );

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
					'play'       => __( 'הפעלה', 'md-deschool' ),
					'pause'      => __( 'השהיה', 'md-deschool' ),
					'seek'       => __( 'מעבר במהלך הסרטון', 'md-deschool' ),
					'mute'       => __( 'השתקה', 'md-deschool' ),
					'fullscreen' => __( 'מסך מלא', 'md-deschool' ),
				),
			)
		);
	}
}
