<?php
/**
 * Locked / preview part for users without access.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

$unit_id  = (int) ( $args['unit_id'] ?? 0 );
$chapters = (array) ( $args['chapters'] ?? array() );

$purchase_url = class_exists( Integration::class ) ? Integration::get_purchase_url( $unit_id ) : '';
$is_logged_in = is_user_logged_in();
?>
<section class="mdds-locked" aria-labelledby="mdds-locked-title">
	<h2 id="mdds-locked-title"><?php esc_html_e( 'תוכן היחידה נעול', 'md-deschool' ); ?></h2>

	<?php if ( ! empty( $chapters ) ) : ?>
		<p><?php esc_html_e( 'יחידה זו כוללת את הפרקים הבאים:', 'md-deschool' ); ?></p>
		<ol class="mdds-locked-list">
			<?php foreach ( $chapters as $chapter ) : ?>
				<?php if ( $chapter instanceof WP_Post ) : ?>
					<li><?php echo esc_html( $chapter->post_title ); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<div class="mdds-locked-actions">
		<?php if ( '' !== $purchase_url ) : ?>
			<a class="mdds-button mdds-button-primary" href="<?php echo esc_url( $purchase_url ); ?>">
				<?php esc_html_e( 'רכישת היחידה', 'md-deschool' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( ! $is_logged_in ) : ?>
			<a class="mdds-button mdds-button-outline" href="<?php echo esc_url( wp_login_url( get_permalink( $unit_id ) ) ); ?>">
				<?php esc_html_e( 'כניסה לחשבון', 'md-deschool' ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
