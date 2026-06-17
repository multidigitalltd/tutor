<?php
/**
 * Single task (question / assignment) field block.
 *
 * Tasks no longer submit individually: the chapter wraps all of its tasks in a
 * single form with one "submit answers" button (see chapter.php).
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$chapter_id = (int) ( $args['chapter_id'] ?? 0 );
$task       = (array) ( $args['task'] ?? array() );
$index      = (int) ( $args['index'] ?? 0 );
$answer     = (array) ( $args['answer'] ?? array() );

$title       = isset( $task['title'] ) ? (string) $task['title'] : '';
$instruction = isset( $task['instruction'] ) ? (string) $task['instruction'] : '';
$allow_file  = ! empty( $task['allow_file'] );

$answer_text  = isset( $answer['text'] ) ? (string) $answer['text'] : '';
$answer_files = isset( $answer['files'] ) && is_array( $answer['files'] ) ? array_map( 'absint', $answer['files'] ) : array();

$field_id = 'mdds-task-' . $chapter_id . '-' . $index;
?>
<div class="mdds-task" data-mdds-task data-index="<?php echo esc_attr( (string) $index ); ?>">
	<?php if ( '' !== $title ) : ?>
		<h4 class="mdds-task-title"><?php echo esc_html( $title ); ?></h4>
	<?php endif; ?>

	<?php if ( '' !== $instruction ) : ?>
		<p class="mdds-task-instruction" id="<?php echo esc_attr( $field_id . '-desc' ); ?>"><?php echo esc_html( $instruction ); ?></p>
	<?php endif; ?>

	<p class="mdds-field">
		<label for="<?php echo esc_attr( $field_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'התשובה שלך', 'md-deschool' ); ?></label>
		<textarea id="<?php echo esc_attr( $field_id ); ?>"
			name="answer_<?php echo esc_attr( (string) $index ); ?>"
			rows="4"
			class="mdds-task-answer"
			aria-describedby="<?php echo esc_attr( $field_id . '-desc' ); ?>"
			placeholder="<?php esc_attr_e( 'כתבו כאן את התשובה שלכם…', 'md-deschool' ); ?>"><?php echo esc_textarea( $answer_text ); ?></textarea>
	</p>

	<?php if ( $allow_file ) : ?>
		<p class="mdds-field mdds-task-file">
			<label for="<?php echo esc_attr( $field_id . '-file' ); ?>"><?php esc_html_e( 'צירוף קובץ (PDF, Word, PowerPoint, Excel, תמונה)', 'md-deschool' ); ?></label>
			<input type="file" id="<?php echo esc_attr( $field_id . '-file' ); ?>" name="file_<?php echo esc_attr( (string) $index ); ?>"
				accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" />
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $answer_files ) ) : ?>
		<ul class="mdds-task-files" data-mdds-task-files>
			<?php foreach ( $answer_files as $file_id ) : ?>
				<?php
				$url = wp_get_attachment_url( $file_id );
				if ( ! $url ) {
					continue;
				}
				?>
				<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_basename( $url ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<ul class="mdds-task-files" data-mdds-task-files hidden></ul>
	<?php endif; ?>
</div>
