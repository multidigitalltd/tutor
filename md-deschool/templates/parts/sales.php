<?php
/**
 * Sales / landing layout shown to visitors without access.
 *
 * Two columns: course image on one side; title, "what's included", price and a
 * purchase button on the other — plus a sticky purchase bar to drive checkout.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;
use MultiDigital\DeSchool\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

$unit_id    = (int) ( $args['unit_id'] ?? 0 );
$chapters   = (array) ( $args['chapters'] ?? array() );
$can_access = (bool) ( $args['can_access'] ?? false );

$short        = (string) get_post_meta( $unit_id, Data::META_SHORT_DESC, true );
$includes     = (string) get_post_meta( $unit_id, Data::META_INCLUDES, true );
$has_wc       = class_exists( Integration::class );
$price_html   = $has_wc ? Integration::get_price_html( $unit_id ) : '';
$purchase_url = $has_wc ? Integration::get_purchase_url( $unit_id ) : '';
$is_logged_in = is_user_logged_in();
$login_url    = wp_login_url( (string) get_permalink( $unit_id ) );
$learn_url    = Data::get_learn_url( $unit_id );
$buy_label    = __( 'רכישת הקורס', 'md-deschool' );
?>
<section class="mdds-sales" aria-labelledby="mdds-sales-title">
	<div class="mdds-sales-hero">

		<div class="mdds-sales-media">
			<?php if ( has_post_thumbnail( $unit_id ) ) : ?>
				<?php echo get_the_post_thumbnail( $unit_id, 'large', array( 'class' => 'mdds-sales-image' ) ); ?>
			<?php else : ?>
				<span class="mdds-sales-image mdds-course-card-placeholder" aria-hidden="true"></span>
			<?php endif; ?>
		</div>

		<div class="mdds-sales-info">
			<h1 id="mdds-sales-title" class="mdds-sales-name"><?php echo esc_html( get_the_title( $unit_id ) ); ?></h1>

			<?php if ( '' !== $short ) : ?>
				<p class="mdds-sales-subtitle"><?php echo esc_html( $short ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== trim( $includes ) ) : ?>
				<div class="mdds-sales-includes">
					<h2><?php esc_html_e( 'מה כולל הקורס', 'md-deschool' ); ?></h2>
					<ul>
						<?php
						foreach ( (array) preg_split( '/\r\n|\r|\n/', $includes ) as $line ) :
							$line = trim( $line );
							if ( '' === $line ) {
								continue;
							}
							?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $chapters ) ) : ?>
				<p class="mdds-sales-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of chapters */
							_n( '%d פרק בקורס', '%d פרקים בקורס', count( $chapters ), 'md-deschool' ),
							count( $chapters )
						)
					);
					?>
				</p>
			<?php endif; ?>

			<div class="mdds-sales-cta">
				<?php if ( $can_access ) : ?>
					<a class="mdds-button mdds-button-primary mdds-sales-buy" href="<?php echo esc_url( $learn_url ); ?>"><?php esc_html_e( 'מעבר לקורס', 'md-deschool' ); ?></a>
				<?php else : ?>
					<?php if ( '' !== $price_html ) : ?>
						<span class="mdds-sales-price"><?php echo wp_kses_post( $price_html ); ?></span>
					<?php endif; ?>

					<?php if ( '' !== $purchase_url ) : ?>
						<a class="mdds-button mdds-button-primary mdds-sales-buy" href="<?php echo esc_url( $purchase_url ); ?>"><?php echo esc_html( $buy_label ); ?></a>
					<?php elseif ( ! $is_logged_in ) : ?>
						<a class="mdds-button mdds-button-primary" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'כניסה לחשבון', 'md-deschool' ); ?></a>
					<?php endif; ?>

					<?php if ( '' !== $purchase_url && ! $is_logged_in ) : ?>
						<a class="mdds-button mdds-button-outline" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'כבר רכשתי — כניסה', 'md-deschool' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php
	$content = apply_filters( 'the_content', get_post_field( 'post_content', $unit_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying WordPress core filter.
	if ( '' !== trim( wp_strip_all_tags( $content ) ) ) :
		?>
		<div class="mdds-sales-description"><?php echo wp_kses_post( $content ); ?></div>
	<?php endif; ?>
</section>

<?php if ( ! $can_access && '' !== $purchase_url ) : ?>
	<div class="mdds-buybar" data-mdds-buybar>
		<div class="mdds-buybar-inner">
			<span class="mdds-buybar-title"><?php echo esc_html( get_the_title( $unit_id ) ); ?></span>
			<?php if ( '' !== $price_html ) : ?>
				<span class="mdds-buybar-price"><?php echo wp_kses_post( $price_html ); ?></span>
			<?php endif; ?>
			<a class="mdds-button mdds-button-primary" href="<?php echo esc_url( $purchase_url ); ?>"><?php echo esc_html( $buy_label ); ?></a>
		</div>
	</div>
<?php endif; ?>
