<?php
/**
 * Metaboxes for the Unit post type.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and saves all unit-level metaboxes.
 */
final class Unit_Metaboxes {

	private const NONCE_ACTION = 'mdds_save_unit';
	private const NONCE_NAME   = 'mdds_unit_nonce';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . Data::POST_TYPE_UNIT, array( $this, 'add' ) );
		add_action( 'save_post_' . Data::POST_TYPE_UNIT, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the metaboxes.
	 */
	public function add(): void {
		add_meta_box( 'mdds-unit-details', __( 'פרטי יחידת התוכן', 'md-deschool' ), array( $this, 'render_details' ), Data::POST_TYPE_UNIT, 'normal', 'high' );
		add_meta_box( 'mdds-unit-lecturer', __( 'פרטי המרצה', 'md-deschool' ), array( $this, 'render_lecturer' ), Data::POST_TYPE_UNIT, 'normal', 'default' );
		add_meta_box( 'mdds-unit-quiz', __( 'מבחן סיכום (בוחן אמריקאי)', 'md-deschool' ), array( $this, 'render_quiz' ), Data::POST_TYPE_UNIT, 'normal', 'default' );
		add_meta_box( 'mdds-unit-consult', __( 'אזור ייעוץ אישי', 'md-deschool' ), array( $this, 'render_consult' ), Data::POST_TYPE_UNIT, 'normal', 'default' );
	}

	/**
	 * Render the details metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_details( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		Field_Renderer::textarea(
			Data::META_SHORT_DESC,
			__( 'תיאור קצר (מופיע מתחת לכותרת)', 'md-deschool' ),
			(string) get_post_meta( $post->ID, Data::META_SHORT_DESC, true ),
			3
		);
		Field_Renderer::textarea(
			Data::META_INCLUDES,
			__( 'מה כוללת היחידה (שורה לכל נקודה)', 'md-deschool' ),
			(string) get_post_meta( $post->ID, Data::META_INCLUDES, true ),
			6
		);
		echo '<p class="description">' . esc_html__( 'התיאור המלא של היחידה נכתב בעורך התוכן הראשי למעלה.', 'md-deschool' ) . '</p>';

		echo '<hr />';
		echo '<h4>' . esc_html__( 'מבנה הלמידה', 'md-deschool' ) . '</h4>';
		Field_Renderer::checkbox(
			Data::META_SEQUENTIAL,
			__( 'למידה רציפה (טפטוף תוכן): כל פרק נפתח רק לאחר השלמת הפרק הקודם, והמבחן נפתח רק לאחר השלמת כל הפרקים.', 'md-deschool' ),
			(bool) get_post_meta( $post->ID, Data::META_SEQUENTIAL, true )
		);
	}

	/**
	 * Render the lecturer metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_lecturer( \WP_Post $post ): void {
		Field_Renderer::text( Data::META_LECTURER_NAME, __( 'שם המרצה', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_LECTURER_NAME, true ) );
		Field_Renderer::text( Data::META_LECTURER_TITLE, __( 'תפקיד / טייטל', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_LECTURER_TITLE, true ) );
		Field_Renderer::media( Data::META_LECTURER_IMAGE, __( 'תמונת מרצה', 'md-deschool' ), (int) get_post_meta( $post->ID, Data::META_LECTURER_IMAGE, true ), __( 'בחירת תמונה', 'md-deschool' ) );
		Field_Renderer::textarea( Data::META_LECTURER_BIO, __( 'תיאור קצר', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_LECTURER_BIO, true ), 3 );
		Field_Renderer::text( Data::META_LECTURER_LINK, __( 'קישור לקביעת פגישה / טופס פנייה', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_LECTURER_LINK, true ), 'url' );
	}

	/**
	 * Render the consultation metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_consult( \WP_Post $post ): void {
		Field_Renderer::checkbox(
			Data::META_CONSULT_ENABLED,
			__( 'הפעלת אזור הייעוץ ביחידה זו', 'md-deschool' ),
			Data::is_consult_enabled( (int) $post->ID )
		);
		Field_Renderer::checkbox(
			Data::META_CONSULT_ON_DONE,
			__( 'להציג רק למי שסיים את כל הקורס', 'md-deschool' ),
			Data::consult_after_complete( (int) $post->ID )
		);
		echo '<hr />';

		Field_Renderer::text( Data::META_CONSULT_TITLE, __( 'כותרת', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_CONSULT_TITLE, true ) );
		Field_Renderer::textarea( Data::META_CONSULT_TEXT, __( 'טקסט הסבר', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_CONSULT_TEXT, true ), 4 );
		Field_Renderer::text( Data::META_CONSULT_LABEL, __( 'טקסט הכפתור', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_CONSULT_LABEL, true ) );
		Field_Renderer::text( Data::META_CONSULT_URL, __( 'קישור הכפתור (יומן / טופס / מוצר / סליקה)', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_CONSULT_URL, true ), 'url' );
	}

	/**
	 * Render the quiz metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_quiz( \WP_Post $post ): void {
		$questions  = Data::get_quiz_questions( $post->ID );
		$pass       = (int) get_post_meta( $post->ID, Data::META_QUIZ_PASS, true );
		$show       = (bool) get_post_meta( $post->ID, Data::META_QUIZ_SHOW, true );
		$retry      = (bool) get_post_meta( $post->ID, Data::META_QUIZ_RETRY, true );
		$quiz_title = (string) get_post_meta( $post->ID, Data::META_QUIZ_TITLE, true );
		?>
		<?php Field_Renderer::text( Data::META_QUIZ_TITLE, __( 'כותרת המבחן', 'md-deschool' ), $quiz_title ); ?>
		<?php Field_Renderer::text( Data::META_QUIZ_PASS, __( 'ציון מעבר (%)', 'md-deschool' ), (string) ( $pass > 0 ? $pass : 70 ), 'number' ); ?>
		<?php Field_Renderer::checkbox( Data::META_QUIZ_SHOW, __( 'הצגת התשובות הנכונות בסיום', 'md-deschool' ), $show ); ?>
		<?php Field_Renderer::checkbox( Data::META_QUIZ_RETRY, __( 'אפשרות לניסיון חוזר', 'md-deschool' ), $retry ); ?>

		<hr />
		<h4><?php esc_html_e( 'שאלות המבחן', 'md-deschool' ); ?></h4>
		<div class="mdds-repeater" data-mdds-repeater="quiz">
			<div class="mdds-repeater-items" data-mdds-repeater-items>
				<?php
				if ( empty( $questions ) ) {
					$this->render_quiz_row( 0, array() );
				} else {
					foreach ( $questions as $i => $question ) {
						$this->render_quiz_row( (int) $i, (array) $question );
					}
				}
				?>
			</div>
			<button type="button" class="button button-secondary" data-mdds-repeater-add><?php esc_html_e( 'הוספת שאלה', 'md-deschool' ); ?></button>
		</div>

		<script type="text/template" data-mdds-repeater-template="quiz">
			<?php $this->render_quiz_row( 0, array(), true ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single quiz question row.
	 *
	 * @param int                 $index    Row index.
	 * @param array<string,mixed> $question Question data.
	 * @param bool                $template Whether this is the JS template (index placeholder).
	 */
	private function render_quiz_row( int $index, array $question, bool $template = false ): void {
		$key     = $template ? '__index__' : (string) $index;
		$text    = isset( $question['question'] ) ? (string) $question['question'] : '';
		$answers = isset( $question['answers'] ) && is_array( $question['answers'] ) ? $question['answers'] : array( '', '', '', '' );
		$answers = array_pad( array_slice( $answers, 0, 4 ), 4, '' );
		$correct = isset( $question['correct'] ) ? (int) $question['correct'] : 0;
		$base    = 'mdds_quiz[' . $key . ']';
		?>
		<div class="mdds-repeater-item" data-mdds-repeater-item>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'שאלה', 'md-deschool' ); ?></strong></label>
				<textarea name="<?php echo esc_attr( $base . '[question]' ); ?>" rows="2" class="widefat"><?php echo esc_textarea( $text ); ?></textarea>
			</p>
			<fieldset class="mdds-answers">
				<legend><?php esc_html_e( 'תשובות (סמנו את הנכונה)', 'md-deschool' ); ?></legend>
				<?php foreach ( $answers as $a => $answer ) : ?>
					<label class="mdds-answer-row">
						<input type="radio" name="<?php echo esc_attr( $base . '[correct]' ); ?>" value="<?php echo esc_attr( (string) $a ); ?>" <?php checked( $correct, $a ); ?> />
						<input type="text" name="<?php echo esc_attr( $base . '[answers][]' ); ?>" value="<?php echo esc_attr( (string) $answer ); ?>" class="widefat" placeholder="<?php echo esc_attr( sprintf( /* translators: %d: answer number */ __( 'תשובה %d', 'md-deschool' ), $a + 1 ) ); ?>" />
					</label>
				<?php endforeach; ?>
			</fieldset>
			<button type="button" class="button-link mdds-repeater-remove" data-mdds-repeater-remove><?php esc_html_e( 'הסרת שאלה', 'md-deschool' ); ?></button>
			<hr />
		</div>
		<?php
	}

	/**
	 * Save all unit meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Simple text / textarea fields.
		$this->update_textarea( $post_id, Data::META_SHORT_DESC );
		$this->update_textarea( $post_id, Data::META_INCLUDES );
		$this->update_text( $post_id, Data::META_LECTURER_NAME );
		$this->update_text( $post_id, Data::META_LECTURER_TITLE );
		$this->update_textarea( $post_id, Data::META_LECTURER_BIO );
		$this->update_url( $post_id, Data::META_LECTURER_LINK );
		$this->update_text( $post_id, Data::META_CONSULT_TITLE );
		$this->update_textarea( $post_id, Data::META_CONSULT_TEXT );
		$this->update_text( $post_id, Data::META_CONSULT_LABEL );
		$this->update_url( $post_id, Data::META_CONSULT_URL );
		$this->update_text( $post_id, Data::META_QUIZ_TITLE );

		// Consultation banner visibility.
		update_post_meta( $post_id, Data::META_CONSULT_ENABLED, isset( $_POST[ Data::META_CONSULT_ENABLED ] ) ? 1 : 0 );
		update_post_meta( $post_id, Data::META_CONSULT_ON_DONE, isset( $_POST[ Data::META_CONSULT_ON_DONE ] ) ? 1 : 0 );

		// Learning structure.
		update_post_meta( $post_id, Data::META_SEQUENTIAL, isset( $_POST[ Data::META_SEQUENTIAL ] ) ? 1 : 0 );

		// Lecturer image (attachment id).
		$image = isset( $_POST[ Data::META_LECTURER_IMAGE ] ) ? absint( wp_unslash( $_POST[ Data::META_LECTURER_IMAGE ] ) ) : 0;
		update_post_meta( $post_id, Data::META_LECTURER_IMAGE, $image );

		// Quiz settings.
		$pass = isset( $_POST[ Data::META_QUIZ_PASS ] ) ? absint( wp_unslash( $_POST[ Data::META_QUIZ_PASS ] ) ) : 0;
		update_post_meta( $post_id, Data::META_QUIZ_PASS, min( 100, $pass ) );
		update_post_meta( $post_id, Data::META_QUIZ_SHOW, isset( $_POST[ Data::META_QUIZ_SHOW ] ) ? 1 : 0 );
		update_post_meta( $post_id, Data::META_QUIZ_RETRY, isset( $_POST[ Data::META_QUIZ_RETRY ] ) ? 1 : 0 );

		// Quiz questions.
		$this->save_quiz( $post_id );
	}

	/**
	 * Sanitise and persist the quiz questions repeater.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_quiz( int $post_id ): void {
		if ( ! isset( $_POST['mdds_quiz'] ) || ! is_array( $_POST['mdds_quiz'] ) ) {
			update_post_meta( $post_id, Data::META_QUIZ_QUESTIONS, array() );
			return;
		}

		$raw       = wp_unslash( $_POST['mdds_quiz'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		$questions = array();

		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? sanitize_textarea_field( (string) $row['question'] ) : '';

			$answers = array();
			if ( isset( $row['answers'] ) && is_array( $row['answers'] ) ) {
				foreach ( $row['answers'] as $answer ) {
					$answer = sanitize_text_field( (string) $answer );
					if ( '' !== $answer ) {
						$answers[] = $answer;
					}
				}
			}

			if ( '' === $question || count( $answers ) < 2 ) {
				continue;
			}

			$correct = isset( $row['correct'] ) ? absint( $row['correct'] ) : 0;
			if ( $correct > count( $answers ) - 1 ) {
				$correct = 0;
			}

			$questions[] = array(
				'question' => $question,
				'answers'  => array_values( $answers ),
				'correct'  => $correct,
			);
		}

		update_post_meta( $post_id, Data::META_QUIZ_QUESTIONS, $questions );
	}

	/**
	 * Save a sanitised single-line text field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	private function update_text( int $post_id, string $key ): void {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Save a sanitised multi-line text field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	private function update_textarea( int $post_id, string $key ): void {
		$value = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Save a sanitised URL field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	private function update_url( int $post_id, string $key ): void {
		$value = isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
		update_post_meta( $post_id, $key, $value );
	}
}
