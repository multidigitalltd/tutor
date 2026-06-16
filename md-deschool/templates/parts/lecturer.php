<?php
/**
 * Lecturer part.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

$unit_id = (int) ( $args['unit_id'] ?? 0 );

$name  = (string) get_post_meta( $unit_id, Data::META_LECTURER_NAME, true );
$title = (string) get_post_meta( $unit_id, Data::META_LECTURER_TITLE, true );
$bio   = (string) get_post_meta( $unit_id, Data::META_LECTURER_BIO, true );
$link  = (string) get_post_meta( $unit_id, Data::META_LECTURER_LINK, true );
$image = (int) get_post_meta( $unit_id, Data::META_LECTURER_IMAGE, true );

if ( '' === $name && '' === $bio && 0 === $image ) {
	return;
}
?>
<section class="mdds-lecturer" aria-labelledby="mdds-lecturer-title">
	<h2 id="mdds-lecturer-title" class="screen-reader-text"><?php esc_html_e( 'פרטי המרצה', 'md-deschool' ); ?></h2>
	<div class="mdds-lecturer-card">
		<?php if ( $image > 0 ) : ?>
			<div class="mdds-lecturer-avatar">
				<?php
				echo wp_get_attachment_image(
					$image,
					'thumbnail',
					false,
					array(
						'loading' => 'lazy',
						'alt'     => $name,
					)
				);
				?>
			</div>
		<?php endif; ?>
		<div class="mdds-lecturer-body">
			<?php if ( '' !== $name ) : ?>
				<p class="mdds-lecturer-name"><?php echo esc_html( $name ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<p class="mdds-lecturer-role"><?php echo esc_html( $title ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $bio ) : ?>
				<p class="mdds-lecturer-bio"><?php echo esc_html( $bio ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $link ) : ?>
				<a class="mdds-button mdds-button-outline" href="<?php echo esc_url( $link ); ?>">
					<?php esc_html_e( 'קביעת פגישה עם המרצה', 'md-deschool' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
