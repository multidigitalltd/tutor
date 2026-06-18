<?php
/**
 * Single chapter part.
 *
 * Each chapter is a self-contained learning step: description, video,
 * presentation and its own tasks. The unit page turns these into an
 * LMS-style stepper (one chapter at a time) via progressive enhancement.
 *
 * When a unit uses sequential (drip) learning and the chapter is still
 * locked, the inner content is NOT rendered at all — drip is enforced
 * server-side, not merely hidden with CSS.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;
use MultiDigital\DeSchool\Frontend\Render;
use MultiDigital\DeSchool\Frontend\Template_Loader;

defined( 'ABSPATH' ) || exit;

$unit_id    = (int) ( $args['unit_id'] ?? 0 );
$chapter    = $args['chapter'] ?? null;
$number     = (int) ( $args['number'] ?? 0 );
$index      = (int) ( $args['index'] ?? max( 0, $number - 1 ) );
$total      = (int) ( $args['total'] ?? 0 );
$user_id    = (int) ( $args['user_id'] ?? 0 );
$completed  = (bool) ( $args['completed'] ?? false );
$locked     = (bool) ( $args['locked'] ?? false );
$sequential = (bool) ( $args['sequential'] ?? false );

if ( ! $chapter instanceof WP_Post ) {
	return;
}

$chapter_id   = (int) $chapter->ID;
$is_last      = ( $total > 0 && $index >= $total - 1 );
$section_cls  = 'mdds-chapter';
$section_cls .= $completed ? ' is-completed' : '';
$section_cls .= $locked ? ' is-locked' : '';
?>
<section id="mdds-chapter-<?php echo esc_attr( (string) $chapter_id ); ?>"
	class="<?php echo esc_attr( $section_cls ); ?>"
	data-mdds-chapter
	data-mdds-panel
	data-index="<?php echo esc_attr( (string) $index ); ?>"
	data-locked="<?php echo $locked ? '1' : '0'; ?>"
	data-completed="<?php echo $completed ? '1' : '0'; ?>"
	aria-labelledby="mdds-chapter-title-<?php echo esc_attr( (string) $chapter_id ); ?>"
	tabindex="-1">

	<header class="mdds-chapter-header">
		<h2 id="mdds-chapter-title-<?php echo esc_attr( (string) $chapter_id ); ?>" class="mdds-chapter-title">
			<span class="mdds-chapter-number">
				<?php
				if ( $total > 0 ) {
					echo esc_html(
						sprintf(
							/* translators: 1: chapter number, 2: total chapters */
							__( 'פרק %1$d מתוך %2$d', 'md-deschool' ),
							$number,
							$total
						)
					);
				} else {
					echo esc_html( sprintf( /* translators: %d: chapter number */ __( 'פרק %d', 'md-deschool' ), $number ) );
				}
				?>
			</span>
			<?php echo esc_html( $chapter->post_title ); ?>
		</h2>
		<span class="mdds-chapter-status" data-mdds-chapter-status<?php echo $completed ? '' : ' hidden'; ?>>
			<?php esc_html_e( 'הושלם', 'md-deschool' ); ?>
		</span>
	</header>

	<?php if ( $locked ) : ?>

		<div class="mdds-chapter-locked" data-mdds-chapter-locked>
			<p class="mdds-chapter-locked-text">
				<?php esc_html_e( 'פרק זה ייפתח לאחר השלמת הפרק הקודם.', 'md-deschool' ); ?>
			</p>
		</div>

	<?php else : ?>

		<?php
		$description = (string) get_post_meta( $chapter_id, Data::META_CHAPTER_DESC, true );
		$video       = Render::video( $chapter_id, $chapter->post_title );
		$pres        = Render::presentation( $chapter_id );
		$tasks       = Data::get_tasks( $chapter_id );
		?>

		<?php if ( '' !== trim( $description ) ) : ?>
			<div class="mdds-chapter-description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
		<?php endif; ?>

		<?php if ( '' !== $video ) : ?>
			<div class="mdds-chapter-video">
				<?php
				// Output is pre-escaped/sanitised inside Render::video().
				echo $video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $pres ) : ?>
			<div class="mdds-chapter-presentation">
				<h3><?php esc_html_e( 'מצגת הפרק', 'md-deschool' ); ?></h3>
				<?php
				// Output is pre-escaped inside Render::presentation().
				echo $pres; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $tasks ) ) : ?>
			<div class="mdds-chapter-tasks">
				<h3><?php esc_html_e( 'שאלות / משימות הפרק', 'md-deschool' ); ?></h3>
				<form class="mdds-tasks-form" data-mdds-tasks-form data-chapter="<?php echo esc_attr( (string) $chapter_id ); ?>" enctype="multipart/form-data">
					<?php
					foreach ( $tasks as $task_index => $task ) {
						Template_Loader::get_part(
							'task',
							array(
								'unit_id'    => $unit_id,
								'chapter_id' => $chapter_id,
								'task'       => (array) $task,
								'index'      => (int) $task_index,
								'answer'     => $user_id > 0 ? Data::get_task_answer( $user_id, $chapter_id, (int) $task_index ) : array(
									'text'  => '',
									'files' => array(),
								),
							)
						);
					}
					?>
					<div class="mdds-tasks-actions">
						<button type="submit" class="mdds-button mdds-tasks-submit"><?php esc_html_e( 'אישור ושליחת התשובות', 'md-deschool' ); ?></button>
						<span class="mdds-task-feedback" data-mdds-tasks-feedback role="status" aria-live="polite"></span>
					</div>
				</form>
			</div>
		<?php endif; ?>

		<footer class="mdds-chapter-footer">
			<div class="mdds-chapter-nav-buttons">
				<button type="button" class="mdds-button mdds-button-outline mdds-chapter-prev" data-mdds-prev<?php echo 0 === $index ? ' hidden' : ''; ?>>
					<?php esc_html_e( 'הפרק הקודם', 'md-deschool' ); ?>
				</button>

				<button type="button" class="mdds-button mdds-mark-complete"
					data-mdds-mark-complete
					data-chapter="<?php echo esc_attr( (string) $chapter_id ); ?>"
					data-sequential="<?php echo $sequential ? '1' : '0'; ?>"
					data-last="<?php echo $is_last ? '1' : '0'; ?>"
					aria-pressed="<?php echo $completed ? 'true' : 'false'; ?>">
					<?php echo $completed ? esc_html__( 'הפרק הושלם', 'md-deschool' ) : esc_html__( 'סימון כהושלם והמשך', 'md-deschool' ); ?>
				</button>

				<button type="button" class="mdds-button mdds-button-outline mdds-chapter-next" data-mdds-next<?php echo $is_last ? ' hidden' : ''; ?>>
					<?php esc_html_e( 'הפרק הבא', 'md-deschool' ); ?>
				</button>
			</div>
		</footer>

	<?php endif; ?>
</section>
