<?php
/**
 * Unit summary quiz part.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

$unit_id = (int) ( $args['unit_id'] ?? 0 );
$user_id = (int) ( $args['user_id'] ?? 0 );

$questions = Data::get_quiz_questions( $unit_id );
if ( empty( $questions ) ) {
	return;
}

$title       = (string) get_post_meta( $unit_id, Data::META_QUIZ_TITLE, true );
$title       = '' !== $title ? $title : __( 'מבחן סיכום', 'md-deschool' );
$allow_retry = (bool) get_post_meta( $unit_id, Data::META_QUIZ_RETRY, true );
$pass_score  = (int) get_post_meta( $unit_id, Data::META_QUIZ_PASS, true );
$pass_score  = $pass_score > 0 ? $pass_score : 70;
$previous    = $user_id > 0 ? Data::get_quiz_result( $user_id, $unit_id ) : array();
$has_result  = ! empty( $previous );
$letters     = array( 'א', 'ב', 'ג', 'ד', 'ה', 'ו' );
?>
<section class="mdds-quiz" aria-labelledby="mdds-quiz-title" data-mdds-quiz>
	<h2 id="mdds-quiz-title"><?php echo esc_html( $title ); ?></h2>
	<p class="mdds-quiz-meta">
		<?php
		printf(
			/* translators: %d: pass score percentage */
			esc_html__( 'ציון מעבר: %d%%', 'md-deschool' ),
			(int) $pass_score
		);
		?>
	</p>

	<div class="mdds-quiz-result" data-mdds-quiz-result role="status" aria-live="polite" tabindex="-1" <?php echo $has_result ? '' : 'hidden'; ?>>
		<?php if ( $has_result ) : ?>
			<p class="mdds-quiz-score <?php echo ! empty( $previous['passed'] ) ? 'is-pass' : 'is-fail'; ?>">
				<?php
				printf(
					/* translators: 1: score, 2: correct count, 3: total */
					esc_html__( 'הציון שלך: %1$d%% (%2$d מתוך %3$d)', 'md-deschool' ),
					(int) ( $previous['score'] ?? 0 ),
					(int) ( $previous['correct'] ?? 0 ),
					(int) ( $previous['total'] ?? 0 )
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<form class="mdds-quiz-form" data-mdds-quiz-form <?php echo ( $has_result && ! $allow_retry ) ? 'hidden' : ''; ?>>
		<?php foreach ( $questions as $q => $question ) : ?>
			<fieldset class="mdds-quiz-question" data-question="<?php echo esc_attr( (string) $q ); ?>">
				<legend>
					<span class="mdds-quiz-qnum"><?php echo esc_html( sprintf( /* translators: %d: question number */ __( 'שאלה %d', 'md-deschool' ), $q + 1 ) ); ?></span>
					<?php echo esc_html( (string) ( $question['question'] ?? '' ) ); ?>
				</legend>
				<?php
				$answers = isset( $question['answers'] ) && is_array( $question['answers'] ) ? $question['answers'] : array();
				foreach ( $answers as $a => $answer ) :
					$letter = $letters[ $a ] ?? (string) ( $a + 1 );
					$opt_id = 'mdds-q-' . $q . '-a-' . $a;
					?>
					<label class="mdds-quiz-answer" for="<?php echo esc_attr( $opt_id ); ?>" data-answer="<?php echo esc_attr( (string) $a ); ?>">
						<input type="radio" id="<?php echo esc_attr( $opt_id ); ?>" name="answers[<?php echo esc_attr( (string) $q ); ?>]" value="<?php echo esc_attr( (string) $a ); ?>" />
						<span class="mdds-quiz-letter"><?php echo esc_html( $letter ); ?>.</span>
						<span class="mdds-quiz-text"><?php echo esc_html( (string) $answer ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>

		<button type="submit" class="mdds-button mdds-quiz-submit"><?php esc_html_e( 'שליחת המבחן', 'md-deschool' ); ?></button>
		<span class="mdds-quiz-feedback" data-mdds-quiz-feedback role="status" aria-live="polite"></span>
	</form>

	<?php if ( $allow_retry ) : ?>
		<button type="button" class="mdds-button mdds-button-outline mdds-quiz-retry" data-mdds-quiz-retry <?php echo $has_result ? '' : 'hidden'; ?>>
			<?php esc_html_e( 'ניסיון חוזר', 'md-deschool' ); ?>
		</button>
	<?php endif; ?>
</section>
