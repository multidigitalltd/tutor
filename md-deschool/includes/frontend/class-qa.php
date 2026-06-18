<?php
/**
 * Per-course Q&A: students message the course instructor and read replies.
 *
 * Stored as WordPress comments of type "mdds_qa" attached to the unit, so the
 * site already has moderation/admin tooling for them. Students see only their
 * own threads; instructors (anyone who can edit the unit) see and answer all.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Course question & answer threads.
 */
final class QA {

	public const COMMENT_TYPE = 'mdds_qa';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_ajax_mdds_qa_post', array( $this, 'post_message' ) );

		// Central admin screen for instructors to read/answer every question.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_mdds_qa_admin_reply', array( $this, 'admin_reply' ) );

		// Keep Q&A out of normal comment counts and feeds.
		add_filter( 'comment_feed_where', array( $this, 'exclude_from_feed' ) );
	}

	/**
	 * Register the admin Q&A inbox under the DeSchool menu.
	 */
	public function admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Data::POST_TYPE_UNIT,
			__( 'שאלות ותשובות', 'md-deschool' ),
			__( 'שאלות ותשובות', 'md-deschool' ),
			'edit_posts',
			'mdds-qa',
			array( $this, 'render_admin' )
		);
	}

	/**
	 * Render the admin Q&A inbox: every question across all courses, with a
	 * reply box for each.
	 */
	public function render_admin(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$questions = get_comments(
			array(
				'type'    => self::COMMENT_TYPE,
				'parent'  => 0,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'שאלות ותשובות מהלומדים', 'md-deschool' ); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag after redirect.
			if ( isset( $_GET['mdds_qa_replied'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'התשובה נשלחה.', 'md-deschool' ) . '</p></div>';
			}

			if ( empty( $questions ) ) {
				echo '<p>' . esc_html__( 'אין עדיין שאלות.', 'md-deschool' ) . '</p>';
			}

			foreach ( $questions as $question ) {
				$unit_id = (int) $question->comment_post_ID;
				$replies = get_comments(
					array(
						'parent'  => (int) $question->comment_ID,
						'type'    => self::COMMENT_TYPE,
						'status'  => 'approve',
						'orderby' => 'comment_date_gmt',
						'order'   => 'ASC',
					)
				);
				?>
				<div class="mdds-qa-admin-card" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;margin:0 0 16px;max-width:820px;">
					<p style="margin:0 0 4px;">
						<strong><?php echo esc_html( $question->comment_author ); ?></strong>
						&mdash; <a href="<?php echo esc_url( (string) get_edit_post_link( $unit_id ) ); ?>"><?php echo esc_html( get_the_title( $unit_id ) ); ?></a>
						<span style="color:#787c82;"> · <?php echo esc_html( self::format_date( $question ) ); ?></span>
					</p>
					<div style="margin:0 0 10px;"><?php echo wp_kses_post( wpautop( $question->comment_content ) ); ?></div>

					<?php foreach ( $replies as $reply ) : ?>
						<div style="margin:0 0 8px;padding:8px 12px;background:#f6f7f7;border-inline-start:3px solid #2271b1;border-radius:4px;">
							<strong><?php echo esc_html( $reply->comment_author ); ?></strong>
							<span style="color:#787c82;"> · <?php echo esc_html( self::format_date( $reply ) ); ?></span>
							<div><?php echo wp_kses_post( wpautop( $reply->comment_content ) ); ?></div>
						</div>
					<?php endforeach; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
						<input type="hidden" name="action" value="mdds_qa_admin_reply" />
						<input type="hidden" name="parent" value="<?php echo esc_attr( (string) $question->comment_ID ); ?>" />
						<?php wp_nonce_field( 'mdds_qa_admin', 'mdds_qa_admin_nonce' ); ?>
						<textarea name="message" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'כתבו תשובה ללומד…', 'md-deschool' ); ?>" required></textarea>
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'שליחת תשובה', 'md-deschool' ); ?></button></p>
					</form>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Handle an instructor reply submitted from the admin inbox.
	 */
	public function admin_reply(): void {
		if ( ! isset( $_POST['mdds_qa_admin_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mdds_qa_admin_nonce'] ) ), 'mdds_qa_admin' ) ) {
			wp_die( esc_html__( 'בדיקת האבטחה נכשלה.', 'md-deschool' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'md-deschool' ) );
		}

		$parent  = isset( $_POST['parent'] ) ? absint( wp_unslash( $_POST['parent'] ) ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$question = $parent > 0 ? get_comment( $parent ) : null;

		$redirect = add_query_arg(
			array(
				'post_type' => Data::POST_TYPE_UNIT,
				'page'      => 'mdds-qa',
			),
			admin_url( 'edit.php' )
		);

		if ( '' !== $message && $question instanceof \WP_Comment && self::COMMENT_TYPE === $question->comment_type ) {
			$user = wp_get_current_user();
			$reply_id = wp_insert_comment(
				array(
					'comment_post_ID'      => (int) $question->comment_post_ID,
					'comment_parent'       => $parent,
					'comment_content'      => $message,
					'comment_type'         => self::COMMENT_TYPE,
					'user_id'              => (int) $user->ID,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_approved'     => 1,
				)
			);

			if ( $reply_id ) {
				self::notify_student( $question, $message );
				$redirect = add_query_arg( 'mdds_qa_replied', '1', $redirect );
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Whether a user may moderate/answer Q&A for a unit.
	 *
	 * @param int $unit_id Unit ID.
	 * @param int $user_id User ID.
	 */
	public static function can_moderate( int $unit_id, int $user_id ): bool {
		return user_can( $user_id, 'edit_post', $unit_id ) || user_can( $user_id, 'moderate_comments' );
	}

	/**
	 * Render the Q&A area for a unit.
	 *
	 * @param int $unit_id Unit ID.
	 * @param int $user_id Current user ID.
	 */
	public static function render( int $unit_id, int $user_id ): void {
		$moderator = self::can_moderate( $unit_id, $user_id );
		$threads   = self::get_threads( $unit_id, $user_id, $moderator );
		?>
		<section class="mdds-qa" data-mdds-qa aria-labelledby="mdds-qa-title">
			<h2 id="mdds-qa-title"><?php esc_html_e( 'שאלות ותשובות עם המנחה', 'md-deschool' ); ?></h2>
			<p class="mdds-qa-intro">
				<?php
				echo $moderator
					? esc_html__( 'שאלות הלומדים בקורס זה. ניתן להשיב לכל שאלה.', 'md-deschool' )
					: esc_html__( 'יש לכם שאלה? כתבו אותה כאן והמנחה יחזור אליכם.', 'md-deschool' );
				?>
			</p>

			<form class="mdds-qa-form" data-mdds-qa-form data-parent="0">
				<label for="mdds-qa-new" class="screen-reader-text"><?php esc_html_e( 'השאלה שלך', 'md-deschool' ); ?></label>
				<textarea id="mdds-qa-new" name="message" rows="3" class="mdds-task-answer" placeholder="<?php esc_attr_e( 'כתבו כאן את שאלתכם…', 'md-deschool' ); ?>" required></textarea>
				<button type="submit" class="mdds-button mdds-button-primary"><?php esc_html_e( 'שליחת שאלה', 'md-deschool' ); ?></button>
				<span class="mdds-task-feedback" data-mdds-qa-feedback role="status" aria-live="polite"></span>
			</form>

			<?php if ( empty( $threads ) ) : ?>
				<p class="mdds-qa-empty"><?php esc_html_e( 'אין עדיין שאלות.', 'md-deschool' ); ?></p>
			<?php else : ?>
				<ul class="mdds-qa-threads">
					<?php foreach ( $threads as $thread ) : ?>
						<?php self::render_thread( $thread, $moderator ); ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render a single question thread.
	 *
	 * @param array{question:\WP_Comment,replies:\WP_Comment[]} $thread    Thread.
	 * @param bool                                              $moderator Whether the viewer can answer.
	 */
	private static function render_thread( array $thread, bool $moderator ): void {
		$question = $thread['question'];
		?>
		<li class="mdds-qa-thread">
			<div class="mdds-qa-message mdds-qa-question">
				<p class="mdds-qa-author"><?php echo esc_html( $question->comment_author ); ?> <span class="mdds-qa-date"><?php echo esc_html( self::format_date( $question ) ); ?></span></p>
				<div class="mdds-qa-body"><?php echo wp_kses_post( wpautop( $question->comment_content ) ); ?></div>
			</div>

			<?php if ( ! empty( $thread['replies'] ) ) : ?>
				<ul class="mdds-qa-replies">
					<?php foreach ( $thread['replies'] as $reply ) : ?>
						<li class="mdds-qa-message mdds-qa-reply">
							<p class="mdds-qa-author"><?php echo esc_html( $reply->comment_author ); ?> <span class="mdds-qa-badge"><?php esc_html_e( 'מנחה', 'md-deschool' ); ?></span> <span class="mdds-qa-date"><?php echo esc_html( self::format_date( $reply ) ); ?></span></p>
							<div class="mdds-qa-body"><?php echo wp_kses_post( wpautop( $reply->comment_content ) ); ?></div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $moderator ) : ?>
				<form class="mdds-qa-form mdds-qa-reply-form" data-mdds-qa-form data-parent="<?php echo esc_attr( (string) $question->comment_ID ); ?>">
					<label for="mdds-qa-reply-<?php echo esc_attr( (string) $question->comment_ID ); ?>" class="screen-reader-text"><?php esc_html_e( 'התשובה שלך', 'md-deschool' ); ?></label>
					<textarea id="mdds-qa-reply-<?php echo esc_attr( (string) $question->comment_ID ); ?>" name="message" rows="2" class="mdds-task-answer" placeholder="<?php esc_attr_e( 'כתבו תשובה…', 'md-deschool' ); ?>" required></textarea>
					<button type="submit" class="mdds-button"><?php esc_html_e( 'שליחת תשובה', 'md-deschool' ); ?></button>
					<span class="mdds-task-feedback" data-mdds-qa-feedback role="status" aria-live="polite"></span>
				</form>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Get question threads for a unit.
	 *
	 * @param int  $unit_id   Unit ID.
	 * @param int  $user_id   Current user ID.
	 * @param bool $moderator Whether the viewer sees all threads.
	 * @return array<int,array{question:\WP_Comment,replies:\WP_Comment[]}>
	 */
	private static function get_threads( int $unit_id, int $user_id, bool $moderator ): array {
		$args = array(
			'post_id' => $unit_id,
			'type'    => self::COMMENT_TYPE,
			'parent'  => 0,
			'status'  => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		if ( ! $moderator ) {
			$args['user_id'] = $user_id;
		}

		$questions = get_comments( $args );
		$threads   = array();

		foreach ( $questions as $question ) {
			$replies = get_comments(
				array(
					'parent'  => (int) $question->comment_ID,
					'type'    => self::COMMENT_TYPE,
					'status'  => 'approve',
					'orderby' => 'comment_date_gmt',
					'order'   => 'ASC',
				)
			);

			$threads[] = array(
				'question' => $question,
				'replies'  => $replies,
			);
		}

		return $threads;
	}

	/**
	 * Handle posting a question or a reply.
	 */
	public function post_message(): void {
		check_ajax_referer( Ajax::NONCE_ACTION, 'nonce' );

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'נדרשת התחברות.', 'md-deschool' ) ), 401 );
		}

		$unit_id = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;
		if ( $unit_id <= 0 || get_post_type( $unit_id ) !== Data::POST_TYPE_UNIT ) {
			wp_send_json_error( array( 'message' => __( 'קורס לא תקין.', 'md-deschool' ) ), 400 );
		}

		if ( ! Access_Control::can_access( $unit_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'אין לך גישה לקורס זה.', 'md-deschool' ) ), 403 );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'יש לכתוב הודעה.', 'md-deschool' ) ), 400 );
		}

		$parent    = isset( $_POST['parent'] ) ? absint( wp_unslash( $_POST['parent'] ) ) : 0;
		$moderator = self::can_moderate( $unit_id, $user_id );

		if ( $parent > 0 ) {
			$parent_comment = get_comment( $parent );
			if ( ! $parent_comment instanceof \WP_Comment
				|| self::COMMENT_TYPE !== $parent_comment->comment_type
				|| (int) $parent_comment->comment_post_ID !== $unit_id ) {
				wp_send_json_error( array( 'message' => __( 'שאלה לא תקינה.', 'md-deschool' ) ), 400 );
			}

			// Only the asker or a moderator may add to a thread.
			if ( ! $moderator && (int) $parent_comment->user_id !== $user_id ) {
				wp_send_json_error( array( 'message' => __( 'אין הרשאה.', 'md-deschool' ) ), 403 );
			}
		}

		$user = get_userdata( $user_id );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $unit_id,
				'comment_parent'       => $parent,
				'comment_content'      => $message,
				'comment_type'         => self::COMMENT_TYPE,
				'user_id'              => $user_id,
				'comment_author'       => $user ? $user->display_name : '',
				'comment_author_email' => $user ? $user->user_email : '',
				'comment_approved'     => 1,
			)
		);

		if ( ! $comment_id ) {
			wp_send_json_error( array( 'message' => __( 'שמירת ההודעה נכשלה.', 'md-deschool' ) ), 500 );
		}

		if ( 0 === $parent && ! $moderator ) {
			// Learner asked a new question → notify the instructor.
			self::notify_instructor( $unit_id, $message );
		} elseif ( $parent > 0 && $moderator && isset( $parent_comment ) ) {
			// Instructor answered → notify the learner who asked.
			self::notify_student( $parent_comment, $message );
		}

		wp_send_json_success( array( 'message' => __( 'ההודעה נשלחה', 'md-deschool' ) ) );
	}

	/**
	 * Email the learner when the instructor answers their question.
	 *
	 * @param \WP_Comment $question The original question comment.
	 * @param string      $message  The reply text.
	 */
	private static function notify_student( \WP_Comment $question, string $message ): void {
		$student = (int) $question->user_id > 0 ? get_userdata( (int) $question->user_id ) : false;
		if ( ! $student || empty( $student->user_email ) ) {
			return;
		}

		$unit_id = (int) $question->comment_post_ID;
		$subject = sprintf(
			/* translators: %s: course title */
			__( 'התקבלה תשובה לשאלתך בקורס: %s', 'md-deschool' ),
			get_the_title( $unit_id )
		);
		$body = $message . "\n\n" . Data::get_learn_url( $unit_id );

		wp_mail( $student->user_email, $subject, $body );
	}

	/**
	 * Email the unit author/instructor about a new question (best-effort).
	 *
	 * @param int    $unit_id Unit ID.
	 * @param string $message Question text.
	 */
	private static function notify_instructor( int $unit_id, string $message ): void {
		$author_id = (int) get_post_field( 'post_author', $unit_id );
		$author    = $author_id > 0 ? get_userdata( $author_id ) : false;
		if ( ! $author || empty( $author->user_email ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: course title */
			__( 'שאלה חדשה בקורס: %s', 'md-deschool' ),
			get_the_title( $unit_id )
		);

		$body = $message . "\n\n" . get_permalink( $unit_id );

		wp_mail( $author->user_email, $subject, $body );
	}

	/**
	 * Localised date for a comment.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return string
	 */
	private static function format_date( \WP_Comment $comment ): string {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_comment_date( 'U', $comment ) );
	}

	/**
	 * Exclude Q&A comments from comment feeds.
	 *
	 * @param string $where Feed WHERE clause.
	 * @return string
	 */
	public function exclude_from_feed( string $where ): string {
		return $where . " AND comment_type != '" . esc_sql( self::COMMENT_TYPE ) . "'";
	}
}
