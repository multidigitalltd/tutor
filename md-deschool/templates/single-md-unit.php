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

				<?php
				$sequential   = Data::is_sequential( $unit_id );
				$total        = count( $chapters );
				$all_complete = $user_id > 0 && Data::all_chapters_completed( $user_id, $unit_id );

				// Pre-compute per-chapter state once (avoids repeated meta reads).
				$states  = array();
				$current = -1; // Sentinel: no default step chosen yet.
				foreach ( $chapters as $i => $chapter ) {
					$cid       = (int) $chapter->ID;
					$completed = $user_id > 0 && Data::is_chapter_completed( $user_id, $cid );
					$unlocked  = Data::is_chapter_unlocked( $user_id, $unit_id, $cid, $chapters );

					$states[ $i ] = array(
						'completed' => $completed,
						'unlocked'  => $unlocked,
					);

					// Default step = first unlocked chapter that is not yet completed.
					if ( -1 === $current && $unlocked && ! $completed ) {
						$current = $i;
					}
				}

				// Everything completed (or no match) → start at the first chapter.
				if ( -1 === $current ) {
					$current = 0;
				}
				?>

				<nav class="mdds-chapter-nav" aria-label="<?php esc_attr_e( 'ניווט בין הפרקים', 'md-deschool' ); ?>" data-mdds-step-nav>
					<h2><?php esc_html_e( 'פרקי היחידה', 'md-deschool' ); ?></h2>
					<ol class="mdds-chapter-list">
						<?php
						foreach ( $chapters as $i => $chapter ) :
							$st        = $states[ $i ];
							$item_cls  = 'mdds-step';
							$item_cls .= $st['completed'] ? ' is-completed' : '';
							$item_cls .= $st['unlocked'] ? '' : ' is-locked';
							?>
							<li class="<?php echo esc_attr( $item_cls ); ?>">
								<button type="button" class="mdds-step-link"
									data-mdds-step="<?php echo esc_attr( (string) $i ); ?>"
									data-target="mdds-chapter-<?php echo esc_attr( (string) $chapter->ID ); ?>"
									<?php echo $st['unlocked'] ? '' : 'aria-disabled="true" disabled'; ?>>
									<span class="mdds-step-index"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
									<span class="mdds-step-name"><?php echo esc_html( $chapter->post_title ); ?></span>
									<span class="mdds-step-state" aria-hidden="true"></span>
								</button>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>

				<div class="mdds-chapters" data-mdds-stepper data-current="<?php echo esc_attr( (string) $current ); ?>">
					<?php
					foreach ( $chapters as $i => $chapter ) {
						Template_Loader::get_part(
							'chapter',
							array(
								'unit_id'    => $unit_id,
								'chapter'    => $chapter,
								'number'     => $i + 1,
								'index'      => $i,
								'total'      => $total,
								'user_id'    => $user_id,
								'sequential' => $sequential,
								'completed'  => $states[ $i ]['completed'],
								'locked'     => ! $states[ $i ]['unlocked'],
							)
						);
					}
					?>
				</div>

				<?php
				if ( $sequential && ! $all_complete && ! empty( Data::get_quiz_questions( $unit_id ) ) ) :
					?>
					<section class="mdds-quiz-locked" aria-labelledby="mdds-quiz-locked-title">
						<h2 id="mdds-quiz-locked-title"><?php esc_html_e( 'מבחן הסיכום נעול', 'md-deschool' ); ?></h2>
						<p><?php esc_html_e( 'השלימו את כל פרקי היחידה כדי לפתוח את מבחן הסיכום.', 'md-deschool' ); ?></p>
					</section>
					<?php
				else :
					Template_Loader::get_part(
						'quiz',
						array(
							'unit_id' => $unit_id,
							'user_id' => $user_id,
						)
					);
				endif;
				?>

			<?php endif; ?>

			<?php Template_Loader::get_part( 'consultation', array( 'unit_id' => $unit_id ) ); ?>

		</article>
	</main>
	<?php

endwhile;

get_footer();
