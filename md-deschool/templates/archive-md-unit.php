<?php
/**
 * Courses archive template.
 *
 * Override by copying to: your-theme/md-deschool/archive-md-unit.php
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Frontend\Catalog;

defined( 'ABSPATH' ) || exit;

get_header();

// Collect the posts from the main archive query (respects pagination/filters).
$mdds_units = array();
while ( have_posts() ) {
	the_post();
	$mdds_units[] = get_post();
}
?>
<main id="mdds-main" class="mdds-unit mdds-courses-archive" dir="auto">
	<header class="mdds-courses-header">
		<h1 class="mdds-courses-title">
			<?php
			echo esc_html( post_type_archive_title( '', false ) );
			?>
		</h1>
		<?php
		$mdds_archive_desc = get_the_archive_description();
		if ( '' !== trim( wp_strip_all_tags( (string) $mdds_archive_desc ) ) ) :
			?>
			<div class="mdds-courses-intro"><?php echo wp_kses_post( $mdds_archive_desc ); ?></div>
		<?php endif; ?>
	</header>

	<?php
	// Output is escaped inside Catalog::render_grid().
	echo Catalog::render_grid( $mdds_units ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => esc_html__( 'הקודם', 'md-deschool' ),
			'next_text' => esc_html__( 'הבא', 'md-deschool' ),
		)
	);
	?>
</main>
<?php

get_footer();
