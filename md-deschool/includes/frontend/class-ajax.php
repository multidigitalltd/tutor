<?php
/**
 * AJAX endpoints for progress, answers and quiz grading.
 *
 * Every handler enforces: nonce verification, authentication, per-unit access
 * (no IDOR), object-ownership validation and full input sanitisation.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the plugin's authenticated AJAX actions.
 */
final class Ajax {

	public const NONCE_ACTION = 'mdds_frontend';

	/** Maximum allowed upload size in bytes (20 MB). */
	private const MAX_UPLOAD = 20971520;

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_ajax_mdds_save_answer', array( $this, 'save_answer' ) );
		add_action( 'wp_ajax_mdds_mark_complete', array( $this, 'mark_complete' ) );
		add_action( 'wp_ajax_mdds_submit_quiz', array( $this, 'submit_quiz' ) );
	}

	/**
	 * Shared guard: verify nonce, login and access to the unit.
	 *
	 * @return array{user_id:int,unit_id:int}
	 */
	private function guard(): array {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'נדרשת התחברות.', 'md-deschool' ) ), 401 );
		}

		$unit_id = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;
		if ( $unit_id <= 0 || get_post_type( $unit_id ) !== Data::POST_TYPE_UNIT ) {
			wp_send_json_error( array( 'message' => __( 'יחידה לא תקינה.', 'md-deschool' ) ), 400 );
		}

		if ( ! Access_Control::can_access( $unit_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'אין לך הרשאת גישה ליחידה זו.', 'md-deschool' ) ), 403 );
		}

		return array(
			'user_id' => $user_id,
			'unit_id' => $unit_id,
		);
	}

	/**
	 * Validate that a chapter belongs to the given unit (prevents IDOR).
	 *
	 * @param int $chapter_id Chapter ID.
	 * @param int $unit_id    Unit ID.
	 */
	private function assert_chapter_in_unit( int $chapter_id, int $unit_id ): void {
		$chapter = get_post( $chapter_id );
		if ( ! $chapter instanceof \WP_Post
			|| Data::POST_TYPE_CHAPTER !== $chapter->post_type
			|| (int) $chapter->post_parent !== $unit_id ) {
			wp_send_json_error( array( 'message' => __( 'פרק לא תקין.', 'md-deschool' ) ), 400 );
		}
	}

	/**
	 * Save a task answer (text + optional file uploads).
	 */
	public function save_answer(): void {
		$ctx     = $this->guard();
		$user_id = $ctx['user_id'];
		$unit_id = $ctx['unit_id'];

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( wp_unslash( $_POST['chapter_id'] ) ) : 0;
		$task_index = isset( $_POST['task_index'] ) ? absint( wp_unslash( $_POST['task_index'] ) ) : 0;

		$this->assert_chapter_in_unit( $chapter_id, $unit_id );

		$tasks = Data::get_tasks( $chapter_id );
		if ( ! isset( $tasks[ $task_index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'משימה לא קיימת.', 'md-deschool' ) ), 400 );
		}

		$text = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';

		// Keep previously stored files, append new uploads.
		$existing = Data::get_task_answer( $user_id, $chapter_id, $task_index );
		$files    = $existing['files'];

		if ( ! empty( $tasks[ $task_index ]['allow_file'] ) && ! empty( $_FILES['file'] ) ) {
			$uploaded = $this->handle_upload( $chapter_id, $user_id );
			if ( $uploaded > 0 ) {
				$files[] = $uploaded;
			}
		}

		Data::save_task_answer( $user_id, $chapter_id, $task_index, $text, $files );

		$file_links = array();
		foreach ( $files as $attachment_id ) {
			$file_links[] = array(
				'name' => wp_basename( (string) get_attached_file( $attachment_id ) ),
				'url'  => wp_get_attachment_url( $attachment_id ),
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'התשובה נשמרה', 'md-deschool' ),
				'files'   => $file_links,
			)
		);
	}

	/**
	 * Handle a single secure file upload and attach it.
	 *
	 * @param int $chapter_id Chapter ID (used as post parent).
	 * @param int $user_id    Uploading user.
	 * @return int Attachment ID (0 on failure).
	 */
	private function handle_upload( int $chapter_id, int $user_id ): int {
		if ( ! isset( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return 0;
		}

		// Validate size before doing any work.
		$size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0;
		if ( $size <= 0 || $size > self::MAX_UPLOAD ) {
			wp_send_json_error( array( 'message' => __( 'הקובץ גדול מדי או ריק.', 'md-deschool' ) ), 400 );
		}

		// Allowed types only (no PHP / executables).
		$allowed = $this->allowed_mimes();
		$name    = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
		$check   = wp_check_filetype_and_ext( isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ) : '', $name, $allowed );

		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( $check['type'], $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'סוג הקובץ אינו נתמך.', 'md-deschool' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		add_filter( 'upload_mimes', array( $this, 'restrict_mimes' ) );
		$attachment_id = media_handle_upload(
			'file',
			$chapter_id,
			array(
				'post_author' => $user_id,
			),
			array(
				'test_form' => false,
				'mimes'     => $allowed,
			)
		);
		remove_filter( 'upload_mimes', array( $this, 'restrict_mimes' ) );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'העלאת הקובץ נכשלה.', 'md-deschool' ) ), 400 );
		}

		return (int) $attachment_id;
	}

	/**
	 * Allowed MIME types for learner uploads.
	 *
	 * @return array<string,string>
	 */
	private function allowed_mimes(): array {
		return array(
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'ppt'          => 'application/vnd.ms-powerpoint',
			'pptx'         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'jpg|jpeg'     => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);
	}

	/**
	 * Restrict the allowed upload mimes during learner uploads.
	 *
	 * @param array<string,string> $mimes Default mimes.
	 * @return array<string,string>
	 */
	public function restrict_mimes( array $mimes ): array {
		return $this->allowed_mimes();
	}

	/**
	 * Toggle chapter completion for the current user.
	 */
	public function mark_complete(): void {
		$ctx     = $this->guard();
		$user_id = $ctx['user_id'];
		$unit_id = $ctx['unit_id'];

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( wp_unslash( $_POST['chapter_id'] ) ) : 0;
		$this->assert_chapter_in_unit( $chapter_id, $unit_id );

		$completed = isset( $_POST['completed'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['completed'] ) );
		Data::set_chapter_completed( $user_id, $chapter_id, $completed );

		$progress = Data::get_progress( $user_id, $unit_id );

		wp_send_json_success(
			array(
				'completed' => $completed,
				'progress'  => $progress,
			)
		);
	}

	/**
	 * Grade a quiz submission entirely server-side.
	 */
	public function submit_quiz(): void {
		$ctx     = $this->guard();
		$user_id = $ctx['user_id'];
		$unit_id = $ctx['unit_id'];

		$questions = Data::get_quiz_questions( $unit_id );
		$total     = count( $questions );
		if ( 0 === $total ) {
			wp_send_json_error( array( 'message' => __( 'אין שאלות במבחן.', 'md-deschool' ) ), 400 );
		}

		// Respect the "allow retry" setting using the stored result.
		$allow_retry = (bool) get_post_meta( $unit_id, Data::META_QUIZ_RETRY, true );
		$previous    = Data::get_quiz_result( $user_id, $unit_id );
		if ( ! $allow_retry && ! empty( $previous ) ) {
			wp_send_json_error( array( 'message' => __( 'כבר ביצעת את המבחן.', 'md-deschool' ) ), 403 );
		}

		$submitted = isset( $_POST['answers'] ) && is_array( $_POST['answers'] )
			? array_map( 'absint', wp_unslash( $_POST['answers'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitized -- absint maps each value.
			: array();

		$correct_count = 0;
		$review        = array();

		foreach ( $questions as $index => $question ) {
			$correct_index  = isset( $question['correct'] ) ? (int) $question['correct'] : 0;
			$selected_index = isset( $submitted[ $index ] ) ? (int) $submitted[ $index ] : -1;
			$is_correct     = ( $selected_index === $correct_index );

			if ( $is_correct ) {
				++$correct_count;
			}

			$review[ $index ] = array(
				'selected'   => $selected_index,
				'correct'    => $correct_index,
				'is_correct' => $is_correct,
			);
		}

		$pass_score = (int) get_post_meta( $unit_id, Data::META_QUIZ_PASS, true );
		$pass_score = $pass_score > 0 ? $pass_score : 70;
		$score      = (int) round( ( $correct_count / $total ) * 100 );
		$passed     = $score >= $pass_score;
		$show       = (bool) get_post_meta( $unit_id, Data::META_QUIZ_SHOW, true );

		$result = array(
			'score'   => $score,
			'correct' => $correct_count,
			'total'   => $total,
			'passed'  => $passed,
			'date'    => time(),
		);

		Data::save_quiz_result( $user_id, $unit_id, $result );

		$response = $result;
		if ( $show ) {
			$response['review'] = $review;
		}

		wp_send_json_success( $response );
	}
}
