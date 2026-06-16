<?php
/**
 * Access control for content units.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the current user may view the full unit content.
 */
final class Access_Control {

	/**
	 * Whether a user can access the full unit content.
	 *
	 * Editors (users who can edit the unit) always have access. Otherwise the
	 * user must have purchased the linked WooCommerce product.
	 *
	 * @param int      $unit_id Unit ID.
	 * @param int|null $user_id User ID (defaults to current user).
	 */
	public static function can_access( int $unit_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		$access = false;

		if ( $user_id > 0 && user_can( $user_id, 'edit_post', $unit_id ) ) {
			$access = true;
		} elseif ( $user_id > 0 && Integration::has_purchased( $user_id, $unit_id ) ) {
			$access = true;
		}

		/**
		 * Filter the final access decision for a unit.
		 *
		 * @param bool $access  Whether access is granted.
		 * @param int  $unit_id Unit ID.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'mdds_user_has_access', $access, $unit_id, $user_id );
	}
}
