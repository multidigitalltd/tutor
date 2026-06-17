<?php
/**
 * Single content-unit template.
 *
 * Override by copying to: your-theme/md-deschool/single-md-unit.php
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Frontend\Course_View;

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	echo '<main id="mdds-main">';
	// Output is escaped within Course_View::render().
	echo Course_View::render( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</main>';

endwhile;

get_footer();
