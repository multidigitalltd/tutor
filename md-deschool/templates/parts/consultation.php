<?php
/**
 * Subsidised consultation CTA part.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

$unit_id = (int) ( $args['unit_id'] ?? 0 );

$title = (string) get_post_meta( $unit_id, Data::META_CONSULT_TITLE, true );
$text  = (string) get_post_meta( $unit_id, Data::META_CONSULT_TEXT, true );
$label = (string) get_post_meta( $unit_id, Data::META_CONSULT_LABEL, true );
$url   = (string) get_post_meta( $unit_id, Data::META_CONSULT_URL, true );

if ( '' === $title && '' === $text ) {
	return;
}
?>
<section class="mdds-consultation" aria-labelledby="mdds-consult-title">
	<?php if ( '' !== $title ) : ?>
		<h2 id="mdds-consult-title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<?php if ( '' !== $text ) : ?>
		<p class="mdds-consultation-text"><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $url && '' !== $label ) : ?>
		<a class="mdds-button mdds-button-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endif; ?>
</section>
