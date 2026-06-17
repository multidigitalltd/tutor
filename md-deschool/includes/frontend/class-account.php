<?php
/**
 * Personal area: [deschool_account] — a tabbed dashboard with the learner's
 * courses (and where they're up to), grades/exams and account settings.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the learner's personal area.
 */
final class Account {

	private const SHORTCODE = 'deschool_account';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_mdds_save_account', array( $this, 'save_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Enqueue the shared stylesheet when the shortcode is present.
	 */
	public function maybe_enqueue(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style( Assets::HANDLE, MDDS_URL . 'assets/css/frontend.css', array(), MDDS_VERSION );
		}
	}

	/**
	 * Render the personal area.
	 *
	 * @return string
	 */
	public function render(): string {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '<div class="mdds-account mdds-unit"><p>' . esc_html__( 'יש להתחבר כדי לצפות באזור האישי שלך.', 'md-deschool' ) . ' '
				. '<a href="' . esc_url( wp_login_url( (string) get_permalink() ) ) . '">' . esc_html__( 'כניסה לחשבון', 'md-deschool' ) . '</a></p></div>';
		}

		$units      = Data::get_all_units();
		$accessible = array_values(
			array_filter(
				$units,
				static fn ( \WP_Post $unit ): bool => Access_Control::can_access( (int) $unit->ID, $user_id )
			)
		);

		ob_start();
		echo '<div class="mdds-account mdds-unit">';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag after a redirect.
		if ( isset( $_GET['mdds_saved'] ) ) {
			echo '<p class="mdds-account-notice">' . esc_html__( 'ההגדרות נשמרו בהצלחה.', 'md-deschool' ) . '</p>';
		}

		echo '<div class="mdds-tabs">';
		echo '<input type="radio" name="mdds-account-tab" id="mdds-tab-courses" class="mdds-tab-radio" checked />';
		echo '<input type="radio" name="mdds-account-tab" id="mdds-tab-grades" class="mdds-tab-radio" />';
		echo '<input type="radio" name="mdds-account-tab" id="mdds-tab-settings" class="mdds-tab-radio" />';

		echo '<nav class="mdds-tabs-nav" aria-label="' . esc_attr__( 'אזור אישי', 'md-deschool' ) . '">';
		echo '<label for="mdds-tab-courses" class="mdds-tab-label mdds-tab-label--courses">' . esc_html__( 'הקורסים שלי', 'md-deschool' ) . '</label>';
		echo '<label for="mdds-tab-grades" class="mdds-tab-label mdds-tab-label--grades">' . esc_html__( 'ציונים ומבחנים', 'md-deschool' ) . '</label>';
		echo '<label for="mdds-tab-settings" class="mdds-tab-label mdds-tab-label--settings">' . esc_html__( 'הגדרות', 'md-deschool' ) . '</label>';
		echo '</nav>';

		echo '<div class="mdds-tabs-body">';

		echo '<section class="mdds-tab-panel mdds-tab-panel--courses" aria-label="' . esc_attr__( 'הקורסים שלי', 'md-deschool' ) . '">';
		$this->render_courses( $accessible, $user_id );
		echo '</section>';

		echo '<section class="mdds-tab-panel mdds-tab-panel--grades" aria-label="' . esc_attr__( 'ציונים ומבחנים', 'md-deschool' ) . '">';
		$this->render_grades( $accessible, $user_id );
		echo '</section>';

		echo '<section class="mdds-tab-panel mdds-tab-panel--settings" aria-label="' . esc_attr__( 'הגדרות', 'md-deschool' ) . '">';
		$this->render_settings( $user_id );
		echo '</section>';

		echo '</div></div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render the "my courses" tab.
	 *
	 * @param \WP_Post[] $units   Accessible units.
	 * @param int        $user_id User ID.
	 */
	private function render_courses( array $units, int $user_id ): void {
		if ( empty( $units ) ) {
			echo '<p>' . esc_html__( 'עדיין אין לך גישה לקורסים.', 'md-deschool' ) . '</p>';
			return;
		}

		echo '<ul class="mdds-account-courses">';
		foreach ( $units as $unit ) {
			$unit_id  = (int) $unit->ID;
			$progress = Data::get_progress( $user_id, $unit_id );
			$total    = (int) $progress['total'];
			$done     = (int) $progress['completed'];
			$percent  = $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;
			$current  = $this->current_chapter( $user_id, $unit_id );
			?>
			<li class="mdds-account-course">
				<div class="mdds-account-course-head">
					<a class="mdds-account-course-title" href="<?php echo esc_url( (string) get_permalink( $unit_id ) ); ?>">
						<?php echo esc_html( $unit->post_title ); ?>
					</a>
					<?php if ( $total > 0 && $done >= $total ) : ?>
						<span class="mdds-course-card-badge"><?php esc_html_e( 'הושלם', 'md-deschool' ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( $total > 0 ) : ?>
					<div class="mdds-progress">
						<div class="mdds-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>" aria-label="<?php esc_attr_e( 'התקדמות בקורס', 'md-deschool' ); ?>">
							<span class="mdds-progress-fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span>
						</div>
						<p class="mdds-progress-text">
							<?php
							printf(
								/* translators: 1: completed, 2: total */
								esc_html__( '%1$d מתוך %2$d פרקים הושלמו', 'md-deschool' ),
								(int) $done,
								(int) $total
							);
							?>
						</p>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $current ) : ?>
					<p class="mdds-account-current">
						<?php
						printf(
							/* translators: %s: chapter title */
							esc_html__( 'הפרק הבא: %s', 'md-deschool' ),
							'<strong>' . esc_html( $current ) . '</strong>'
						);
						?>
					</p>
				<?php endif; ?>

				<a class="mdds-button mdds-button-primary" href="<?php echo esc_url( (string) get_permalink( $unit_id ) ); ?>">
					<?php echo ( $done > 0 ) ? esc_html__( 'המשך לקורס', 'md-deschool' ) : esc_html__( 'התחלת הקורס', 'md-deschool' ); ?>
				</a>
			</li>
			<?php
		}
		echo '</ul>';
	}

	/**
	 * Render the grades / exams tab.
	 *
	 * @param \WP_Post[] $units   Accessible units.
	 * @param int        $user_id User ID.
	 */
	private function render_grades( array $units, int $user_id ): void {
		$rows = array();
		foreach ( $units as $unit ) {
			$result = Data::get_quiz_result( $user_id, (int) $unit->ID );
			if ( ! empty( $result ) ) {
				$rows[] = array( $unit, $result );
			}
		}

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'עדיין לא ביצעת מבחנים.', 'md-deschool' ) . '</p>';
			return;
		}

		echo '<table class="mdds-grades-table"><thead><tr>';
		echo '<th>' . esc_html__( 'קורס', 'md-deschool' ) . '</th>';
		echo '<th>' . esc_html__( 'ציון', 'md-deschool' ) . '</th>';
		echo '<th>' . esc_html__( 'תוצאה', 'md-deschool' ) . '</th>';
		echo '<th>' . esc_html__( 'תאריך', 'md-deschool' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			[ $unit, $result ] = $row;
			$passed            = ! empty( $result['passed'] );
			$date              = ! empty( $result['date'] ) ? wp_date( get_option( 'date_format' ), (int) $result['date'] ) : '';
			echo '<tr>';
			echo '<td><a href="' . esc_url( (string) get_permalink( (int) $unit->ID ) ) . '">' . esc_html( $unit->post_title ) . '</a></td>';
			echo '<td>' . esc_html( (string) (int) ( $result['score'] ?? 0 ) ) . '%</td>';
			echo '<td><span class="mdds-grade ' . ( $passed ? 'is-pass' : 'is-fail' ) . '">'
				. ( $passed ? esc_html__( 'עבר', 'md-deschool' ) : esc_html__( 'לא עבר', 'md-deschool' ) )
				. '</span></td>';
			echo '<td>' . esc_html( (string) $date ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the settings tab.
	 *
	 * @param int $user_id User ID.
	 */
	private function render_settings( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		?>
		<form class="mdds-account-settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mdds_save_account" />
			<?php wp_nonce_field( 'mdds_account', 'mdds_account_nonce' ); ?>

			<p class="mdds-field">
				<label for="mdds-account-name"><strong><?php esc_html_e( 'שם לתצוגה', 'md-deschool' ); ?></strong></label>
				<input type="text" id="mdds-account-name" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" />
			</p>

			<p class="mdds-field">
				<label for="mdds-account-email"><strong><?php esc_html_e( 'כתובת אימייל', 'md-deschool' ); ?></strong></label>
				<input type="email" id="mdds-account-email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>" />
			</p>

			<button type="submit" class="mdds-button mdds-button-primary"><?php esc_html_e( 'שמירת הגדרות', 'md-deschool' ); ?></button>
		</form>

		<p class="mdds-account-logout">
			<a href="<?php echo esc_url( wp_logout_url( (string) get_permalink() ) ); ?>"><?php esc_html_e( 'התנתקות', 'md-deschool' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Persist account settings.
	 */
	public function save_settings(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		check_admin_referer( 'mdds_account', 'mdds_account_nonce' );

		$user_id = get_current_user_id();
		$data    = array( 'ID' => $user_id );

		$display = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		if ( '' !== $display ) {
			$data['display_name'] = $display;
		}

		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		if ( '' !== $email && is_email( $email ) ) {
			$data['user_email'] = $email;
		}

		wp_update_user( $data );

		$referer  = wp_get_referer();
		$redirect = false !== $referer ? $referer : home_url();
		wp_safe_redirect( add_query_arg( 'mdds_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Title of the first not-yet-completed, unlocked chapter (the "resume" point).
	 *
	 * @param int $user_id User ID.
	 * @param int $unit_id Unit ID.
	 * @return string Chapter title, or '' if everything is done.
	 */
	private function current_chapter( int $user_id, int $unit_id ): string {
		$chapters = Data::get_chapters( $unit_id );
		foreach ( $chapters as $chapter ) {
			$cid = (int) $chapter->ID;
			if ( ! Data::is_chapter_completed( $user_id, $cid ) && Data::is_chapter_unlocked( $user_id, $unit_id, $cid, $chapters ) ) {
				return (string) $chapter->post_title;
			}
		}

		return '';
	}
}
