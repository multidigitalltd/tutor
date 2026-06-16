<?php
/**
 * Single chapter part.
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

$unit_id   = (int) ( $args['unit_id'] ?? 0 );
$chapter   = $args['chapter'] ?? null;
$number    = (int) ( $args['number'] ?? 0 );
$user_id   = (int) ( $args['user_id'] ?? 0 );
$completed = (bool) ( $args['completed'] ?? false );

if ( ! $chapter instanceof WP_Post ) {
	return;
}

$chapter_id  = (int) $chapter->ID;
$description = (string) get_post_meta( $chapter_id, Data::META_CHAPTER_DESC, true );
$video       = Render::video( $chapter_id, $chapter->post_title );
$pres        = Render::presentation( $chapter_id );
$tasks       = Data::get_tasks( $chapter_id );
?>
<section id="mdds-chapter-<?php echo esc_attr( (string) $chapter_id ); ?>"
	class="mdds-chapter<?php echo $completed ? ' is-completed' : ''; ?>"
	aria-labelledby="mdds-chapter-title-<?php echo esc_attr( (string) $chapter_id ); ?>">

	<header class="mdds-chapter-header">
		<h2 id="mdds-chapter-title-<?php echo esc_attr( (string) $chapter_id ); ?>" class="mdds-chapter-title">
			<span class="mdds-chapter-number"><?php echo esc_html( sprintf( /* translators: %d: chapter number */ __( 'פרק %d', 'md-deschool' ), $number ) ); ?></span>
			<?php echo esc_html( $chapter->post_title ); ?>
		</h2>
		<span class="mdds-chapter-status" data-mdds-chapter-status<?php echo $completed ? '' : ' hidden'; ?>>
			<?php esc_html_e( 'הושלם', 'md-deschool' ); ?>
		</span>
	</header>

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
			<h3><?php esc_html_e( 'שאלות / משימות לאחר הפרק', 'md-deschool' ); ?></h3>
			<?php
			foreach ( $tasks as $index => $task ) {
				Template_Loader::get_part(
					'task',
					array(
						'unit_id'    => $unit_id,
						'chapter_id' => $chapter_id,
						'task'       => (array) $task,
						'index'      => (int) $index,
						'answer'     => $user_id > 0 ? Data::get_task_answer( $user_id, $chapter_id, (int) $index ) : array(
							'text'  => '',
							'files' => array(),
						),
					)
				);
			}
			?>
		</div>
	<?php endif; ?>

	<footer class="mdds-chapter-footer">
		<button type="button" class="mdds-button mdds-mark-complete"
			data-mdds-mark-complete
			data-chapter="<?php echo esc_attr( (string) $chapter_id ); ?>"
			aria-pressed="<?php echo $completed ? 'true' : 'false'; ?>">
			<?php echo $completed ? esc_html__( 'הפרק הושלם', 'md-deschool' ) : esc_html__( 'סימון כהושלם', 'md-deschool' ); ?>
		</button>
	</footer>
</section>
