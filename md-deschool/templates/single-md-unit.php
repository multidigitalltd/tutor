<?php
/**
 * Single content-unit template.
 *
 * Override by copying to: your-theme/md-deschool/single-md-unit.php
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;
use MultiDigital\DeSchool\Frontend\Access_Control;
use MultiDigital\DeSchool\Frontend\Template_Loader;

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$unit_id    = get_the_ID();
	$can_access = Access_Control::can_access( $unit_id );
	$chapters   = Data::get_chapters( $unit_id );
	$user_id    = get_current_user_id();
	$progress   = $user_id > 0 ? Data::get_progress( $user_id, $unit_id ) : array(
		'completed' => 0,
		'total'     => count( $chapters ),
	);
	?>
	<main id="mdds-main" class="mdds-unit" dir="auto">
		<article class="mdds-unit-inner">

			<?php
			Template_Loader::get_part(
				'unit-header',
				array(
					'unit_id'    => $unit_id,
					'progress'   => $progress,
					'can_access' => $can_access,
				)
			);

			Template_Loader::get_part( 'lecturer', array( 'unit_id' => $unit_id ) );
			?>

			<?php if ( ! $can_access ) : ?>

				<?php
				Template_Loader::get_part(
					'locked',
					array(
						'unit_id'  => $unit_id,
						'chapters' => $chapters,
					)
				);
				?>

			<?php else : ?>

				<nav class="mdds-chapter-nav" aria-label="<?php esc_attr_e( 'ניווט בין הפרקים', 'md-deschool' ); ?>">
					<h2><?php esc_html_e( 'פרקי היחידה', 'md-deschool' ); ?></h2>
					<ol class="mdds-chapter-list">
						<?php foreach ( $chapters as $i => $chapter ) : ?>
							<li>
								<a href="#mdds-chapter-<?php echo esc_attr( (string) $chapter->ID ); ?>">
									<?php echo esc_html( $chapter->post_title ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>

				<div class="mdds-chapters">
					<?php
					foreach ( $chapters as $i => $chapter ) {
						Template_Loader::get_part(
							'chapter',
							array(
								'unit_id'   => $unit_id,
								'chapter'   => $chapter,
								'number'    => $i + 1,
								'user_id'   => $user_id,
								'completed' => $user_id > 0 && Data::is_chapter_completed( $user_id, (int) $chapter->ID ),
							)
						);
					}
					?>
				</div>

				<?php
				Template_Loader::get_part(
					'quiz',
					array(
						'unit_id' => $unit_id,
						'user_id' => $user_id,
					)
				);
				?>

			<?php endif; ?>

			<?php Template_Loader::get_part( 'consultation', array( 'unit_id' => $unit_id ) ); ?>

		</article>
	</main>
	<?php

endwhile;

get_footer();
