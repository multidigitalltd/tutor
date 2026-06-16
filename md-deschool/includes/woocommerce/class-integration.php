<?php
/**
 * WooCommerce integration: link a unit to a product.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\WooCommerce;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the product selector to units and exposes purchase helpers.
 */
final class Integration {

	private const NONCE_ACTION = 'mdds_save_product';
	private const NONCE_NAME   = 'mdds_product_nonce';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . Data::POST_TYPE_UNIT, array( $this, 'add_metabox' ) );
		add_action( 'save_post_' . Data::POST_TYPE_UNIT, array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Register the product metabox.
	 */
	public function add_metabox(): void {
		add_meta_box(
			'mdds-unit-product',
			__( 'מוצר WooCommerce לרכישה', 'md-deschool' ),
			array( $this, 'render' ),
			Data::POST_TYPE_UNIT,
			'side',
			'default'
		);
	}

	/**
	 * Render the product selector.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected = (int) get_post_meta( $post->ID, Data::META_PRODUCT_ID, true );

		$products = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		?>
		<p class="mdds-field">
			<label for="mdds-product-id"><strong><?php esc_html_e( 'מוצר שרכישתו פותחת גישה ליחידה', 'md-deschool' ); ?></strong></label>
			<select id="mdds-product-id" name="<?php echo esc_attr( Data::META_PRODUCT_ID ); ?>" class="widefat">
				<option value="0"><?php esc_html_e( '— ללא מוצר (גישה לעורכים בלבד) —', 'md-deschool' ); ?></option>
				<?php foreach ( $products as $product ) : ?>
					<option value="<?php echo esc_attr( (string) $product->ID ); ?>" <?php selected( $selected, $product->ID ); ?>>
						<?php echo esc_html( $product->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Save the linked product id.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_id = isset( $_POST[ Data::META_PRODUCT_ID ] ) ? absint( wp_unslash( $_POST[ Data::META_PRODUCT_ID ] ) ) : 0;
		update_post_meta( $post_id, Data::META_PRODUCT_ID, $product_id );
	}

	/**
	 * Whether a user has purchased the product linked to a unit.
	 *
	 * @param int $user_id User ID.
	 * @param int $unit_id Unit ID.
	 */
	public static function has_purchased( int $user_id, int $unit_id ): bool {
		if ( $user_id <= 0 || ! function_exists( 'wc_customer_bought_product' ) ) {
			return false;
		}

		$product_id = (int) get_post_meta( $unit_id, Data::META_PRODUCT_ID, true );
		if ( $product_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return wc_customer_bought_product( $user->user_email, $user_id, $product_id );
	}

	/**
	 * Get the purchase URL (add-to-cart) for a unit's product.
	 *
	 * @param int $unit_id Unit ID.
	 */
	public static function get_purchase_url( int $unit_id ): string {
		$product_id = (int) get_post_meta( $unit_id, Data::META_PRODUCT_ID, true );
		if ( $product_id <= 0 ) {
			return '';
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return '';
		}

		return (string) $product->get_permalink();
	}
}
