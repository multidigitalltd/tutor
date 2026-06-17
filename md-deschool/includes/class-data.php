<?php
/**
 * Central data access layer: meta keys, queries and user progress.
 *
 * Keeping every meta key and query in one place enforces DRY and lets us
 * guarantee consistent sanitisation when reading/writing.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool;

defined( 'ABSPATH' ) || exit;

/**
 * Data helpers for units, chapters, quiz and user progress.
 */
final class Data {

	public const POST_TYPE_UNIT    = 'mdds_unit';
	public const POST_TYPE_CHAPTER = 'mdds_chapter';

	/*
	---------------------------------------------------------------------
	 * Unit meta keys.
	 * ------------------------------------------------------------------- */
	public const META_SHORT_DESC      = '_mdds_short_description';
	public const META_INCLUDES        = '_mdds_includes';
	public const META_LECTURER_NAME   = '_mdds_lecturer_name';
	public const META_LECTURER_TITLE  = '_mdds_lecturer_title';
	public const META_LECTURER_IMAGE  = '_mdds_lecturer_image_id';
	public const META_LECTURER_BIO    = '_mdds_lecturer_bio';
	public const META_LECTURER_LINK   = '_mdds_lecturer_link';
	public const META_CONSULT_TITLE   = '_mdds_consult_title';
	public const META_CONSULT_TEXT    = '_mdds_consult_text';
	public const META_CONSULT_LABEL   = '_mdds_consult_button_label';
	public const META_CONSULT_URL     = '_mdds_consult_button_url';
	public const META_CONSULT_PRODUCT = '_mdds_consult_product_id';
	public const META_QUIZ_TITLE      = '_mdds_quiz_title';
	public const META_QUIZ_QUESTIONS  = '_mdds_quiz_questions';
	public const META_QUIZ_PASS       = '_mdds_quiz_pass_score';
	public const META_QUIZ_SHOW       = '_mdds_quiz_show_correct';
	public const META_QUIZ_RETRY      = '_mdds_quiz_allow_retry';
	public const META_PRODUCT_ID      = '_mdds_product_id';
	public const META_SEQUENTIAL      = '_mdds_sequential';

	/*
	---------------------------------------------------------------------
	 * Chapter meta keys.
	 * ------------------------------------------------------------------- */
	public const META_CHAPTER_DESC = '_mdds_chapter_description';
	public const META_VIDEO_EMBED  = '_mdds_video_embed';
	public const META_VIDEO_URL    = '_mdds_video_url';
	public const META_VIDEO_FILE   = '_mdds_video_file_id';
	public const META_PRES_FILE    = '_mdds_presentation_file_id';
	public const META_PRES_URL     = '_mdds_presentation_url';
	public const META_PRES_EMBED   = '_mdds_presentation_embed';
	public const META_TASKS        = '_mdds_tasks';

	/*
	---------------------------------------------------------------------
	 * User meta keys (progress).
	 * ------------------------------------------------------------------- */
	public const UMETA_COMPLETED = '_mdds_completed_chapters';
	public const UMETA_ANSWERS   = '_mdds_task_answers';
	public const UMETA_QUIZ      = '_mdds_quiz_results';

	/**
	 * Get the ordered chapters that belong to a unit.
	 *
	 * Uses get_posts with no_found_rows for a lean query (Multi Digital std).
	 *
	 * @param int $unit_id Unit post ID.
	 * @return \WP_Post[]
	 */
	public static function get_chapters( int $unit_id ): array {
		if ( $unit_id <= 0 ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'              => self::POST_TYPE_CHAPTER,
				'post_parent'            => $unit_id,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'date'       => 'ASC',
				),
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);
	}

	/**
	 * Get the tasks configured for a chapter.
	 *
	 * @param int $chapter_id Chapter post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_tasks( int $chapter_id ): array {
		$tasks = get_post_meta( $chapter_id, self::META_TASKS, true );

		return is_array( $tasks ) ? array_values( $tasks ) : array();
	}

	/**
	 * Get the quiz questions for a unit.
	 *
	 * @param int $unit_id Unit post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_quiz_questions( int $unit_id ): array {
		$questions = get_post_meta( $unit_id, self::META_QUIZ_QUESTIONS, true );

		return is_array( $questions ) ? array_values( $questions ) : array();
	}

	/**
	 * Get every published unit (lean query).
	 *
	 * @return \WP_Post[]
	 */
	public static function get_all_units(): array {
		return get_posts(
			array(
				'post_type'              => self::POST_TYPE_UNIT,
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);
	}

	/**
	 * Get the IDs of users who have saved any task answer.
	 *
	 * @return int[]
	 */
	public static function get_users_with_answers(): array {
		$users = get_users(
			array(
				'meta_key' => self::UMETA_ANSWERS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- intentional, admin-only export.
				'fields'   => 'ID',
			)
		);

		return array_map( 'absint', $users );
	}

	/*
	---------------------------------------------------------------------
	 * Sequential learning (drip) helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a unit uses sequential (drip) learning.
	 *
	 * In sequential mode every chapter unlocks only once the preceding chapter
	 * has been completed, and the summary quiz unlocks only after every chapter
	 * is completed.
	 *
	 * @param int $unit_id Unit ID.
	 */
	public static function is_sequential( int $unit_id ): bool {
		return (bool) get_post_meta( $unit_id, self::META_SEQUENTIAL, true );
	}

	/**
	 * Whether a chapter is unlocked for a user.
	 *
	 * Non-sequential units always return true. In sequential units a chapter is
	 * unlocked only when every chapter ordered before it is completed. The first
	 * chapter is always unlocked.
	 *
	 * @param int             $user_id    User ID.
	 * @param int             $unit_id    Unit ID.
	 * @param int             $chapter_id Chapter being checked.
	 * @param \WP_Post[]|null $chapters   Optional pre-fetched ordered chapters.
	 */
	public static function is_chapter_unlocked( int $user_id, int $unit_id, int $chapter_id, ?array $chapters = null ): bool {
		if ( ! self::is_sequential( $unit_id ) ) {
			return true;
		}

		$chapters = null !== $chapters ? $chapters : self::get_chapters( $unit_id );

		foreach ( $chapters as $chapter ) {
			if ( (int) $chapter->ID === $chapter_id ) {
				return true; // Every earlier chapter was completed.
			}
			if ( $user_id <= 0 || ! self::is_chapter_completed( $user_id, (int) $chapter->ID ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the user has completed every chapter of a unit.
	 *
	 * @param int $user_id User ID.
	 * @param int $unit_id Unit ID.
	 */
	public static function all_chapters_completed( int $user_id, int $unit_id ): bool {
		$progress = self::get_progress( $user_id, $unit_id );

		return $progress['total'] > 0 && $progress['completed'] >= $progress['total'];
	}

	/*
	---------------------------------------------------------------------
	 * User progress.
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a chapter is marked completed by a user.
	 *
	 * @param int $user_id    User ID.
	 * @param int $chapter_id Chapter ID.
	 */
	public static function is_chapter_completed( int $user_id, int $chapter_id ): bool {
		$completed = get_user_meta( $user_id, self::UMETA_COMPLETED, true );

		return is_array( $completed ) && in_array( $chapter_id, array_map( 'absint', $completed ), true );
	}

	/**
	 * Set / unset chapter completion for a user.
	 *
	 * @param int  $user_id    User ID.
	 * @param int  $chapter_id Chapter ID.
	 * @param bool $completed  Desired state.
	 */
	public static function set_chapter_completed( int $user_id, int $chapter_id, bool $completed ): void {
		$list = get_user_meta( $user_id, self::UMETA_COMPLETED, true );
		$list = is_array( $list ) ? array_map( 'absint', $list ) : array();

		if ( $completed ) {
			$list[] = $chapter_id;
			$list   = array_values( array_unique( $list ) );
		} else {
			$list = array_values( array_diff( $list, array( $chapter_id ) ) );
		}

		update_user_meta( $user_id, self::UMETA_COMPLETED, $list );
	}

	/**
	 * Count completed chapters of a unit for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $unit_id Unit ID.
	 * @return array{completed:int,total:int}
	 */
	public static function get_progress( int $user_id, int $unit_id ): array {
		$chapters = self::get_chapters( $unit_id );
		$total    = count( $chapters );

		// Read the completed list once instead of per-chapter lookups.
		$completed_list = get_user_meta( $user_id, self::UMETA_COMPLETED, true );
		$completed_list = is_array( $completed_list ) ? array_map( 'absint', $completed_list ) : array();

		$completed = 0;
		foreach ( $chapters as $chapter ) {
			if ( in_array( (int) $chapter->ID, $completed_list, true ) ) {
				++$completed;
			}
		}

		return array(
			'completed' => $completed,
			'total'     => $total,
		);
	}

	/**
	 * Get a user's saved answer for a single task.
	 *
	 * @param int $user_id    User ID.
	 * @param int $chapter_id Chapter ID.
	 * @param int $task_index Task index.
	 * @return array{text:string,files:int[]}
	 */
	public static function get_task_answer( int $user_id, int $chapter_id, int $task_index ): array {
		$all = get_user_meta( $user_id, self::UMETA_ANSWERS, true );
		$all = is_array( $all ) ? $all : array();

		$answer = $all[ $chapter_id ][ $task_index ] ?? array();

		return array(
			'text'  => isset( $answer['text'] ) ? (string) $answer['text'] : '',
			'files' => isset( $answer['files'] ) && is_array( $answer['files'] ) ? array_map( 'absint', $answer['files'] ) : array(),
		);
	}

	/**
	 * Persist a user's answer for a single task.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $chapter_id Chapter ID.
	 * @param int    $task_index Task index.
	 * @param string $text       Answer text (already sanitised).
	 * @param int[]  $files      Attachment IDs (already sanitised).
	 */
	public static function save_task_answer( int $user_id, int $chapter_id, int $task_index, string $text, array $files ): void {
		$all = get_user_meta( $user_id, self::UMETA_ANSWERS, true );
		$all = is_array( $all ) ? $all : array();

		$all[ $chapter_id ][ $task_index ] = array(
			'text'  => $text,
			'files' => array_map( 'absint', $files ),
			'date'  => time(),
		);

		update_user_meta( $user_id, self::UMETA_ANSWERS, $all );
	}

	/**
	 * Get the latest quiz result for a unit.
	 *
	 * @param int $user_id User ID.
	 * @param int $unit_id Unit ID.
	 * @return array<string,mixed>
	 */
	public static function get_quiz_result( int $user_id, int $unit_id ): array {
		$all = get_user_meta( $user_id, self::UMETA_QUIZ, true );
		$all = is_array( $all ) ? $all : array();

		return isset( $all[ $unit_id ] ) && is_array( $all[ $unit_id ] ) ? $all[ $unit_id ] : array();
	}

	/**
	 * Save a quiz result for a unit.
	 *
	 * @param int                 $user_id User ID.
	 * @param int                 $unit_id Unit ID.
	 * @param array<string,mixed> $result  Result payload.
	 */
	public static function save_quiz_result( int $user_id, int $unit_id, array $result ): void {
		$all             = get_user_meta( $user_id, self::UMETA_QUIZ, true );
		$all             = is_array( $all ) ? $all : array();
		$all[ $unit_id ] = $result;

		update_user_meta( $user_id, self::UMETA_QUIZ, $all );
	}
}
