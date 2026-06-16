<?php
/**
 * Admin tool: export learners' task answers for a unit to CSV.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an export page and a streaming CSV handler.
 */
final class Answers_Export {

	private const ACTION = 'mdds_export_answers';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Register the export sub-page.
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Data::POST_TYPE_UNIT,
			__( 'ייצוא תשובות', 'md-deschool' ),
			__( 'ייצוא תשובות', 'md-deschool' ),
			'edit_others_posts',
			'mdds-export',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the export form.
	 */
	public function render_page(): void {
		$units = Data::get_all_units();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ייצוא תשובות לומדים', 'md-deschool' ); ?></h1>
			<p><?php esc_html_e( 'בחרו יחידת תוכן והורידו קובץ CSV עם כל התשובות שהוגשו.', 'md-deschool' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<p>
					<label for="mdds-export-unit"><strong><?php esc_html_e( 'יחידת תוכן', 'md-deschool' ); ?></strong></label><br />
					<select id="mdds-export-unit" name="unit_id" required>
						<option value=""><?php esc_html_e( '— בחירת יחידה —', 'md-deschool' ); ?></option>
						<?php foreach ( $units as $unit ) : ?>
							<option value="<?php echo esc_attr( (string) $unit->ID ); ?>"><?php echo esc_html( $unit->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php submit_button( __( 'ייצוא CSV', 'md-deschool' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Stream the CSV.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'md-deschool' ) );
		}
		check_admin_referer( self::ACTION );

		$unit_id = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;
		if ( $unit_id <= 0 || Data::POST_TYPE_UNIT !== get_post_type( $unit_id ) ) {
			wp_die( esc_html__( 'יחידה לא תקינה.', 'md-deschool' ) );
		}

		$chapters = Data::get_chapters( $unit_id );
		$users    = Data::get_users_with_answers();

		$filename = 'deschool-answers-' . $unit_id . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- writing to output stream.

		// UTF-8 BOM so Excel renders Hebrew correctly.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- output stream.

		fputcsv(
			$output,
			array(
				__( 'מזהה משתמש', 'md-deschool' ),
				__( 'שם', 'md-deschool' ),
				__( 'אימייל', 'md-deschool' ),
				__( 'פרק', 'md-deschool' ),
				__( 'משימה', 'md-deschool' ),
				__( 'תשובה', 'md-deschool' ),
				__( 'קבצים', 'md-deschool' ),
			)
		);

		foreach ( $users as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			foreach ( $chapters as $chapter ) {
				$chapter_id = (int) $chapter->ID;
				$tasks      = Data::get_tasks( $chapter_id );

				foreach ( $tasks as $index => $task ) {
					$answer = Data::get_task_answer( $user_id, $chapter_id, (int) $index );
					if ( '' === $answer['text'] && empty( $answer['files'] ) ) {
						continue;
					}

					$files = array();
					foreach ( $answer['files'] as $file_id ) {
						$url = wp_get_attachment_url( $file_id );
						if ( $url ) {
							$files[] = $url;
						}
					}

					$task_label = '' !== ( $task['title'] ?? '' ) ? (string) $task['title'] : (string) ( $task['instruction'] ?? '' );

					fputcsv(
						$output,
						array(
							$user_id,
							$this->csv_safe( $user->display_name ),
							$this->csv_safe( $user->user_email ),
							$this->csv_safe( $chapter->post_title ),
							$this->csv_safe( $task_label ),
							$this->csv_safe( $answer['text'] ),
							$this->csv_safe( implode( ' | ', $files ) ),
						)
					);
				}
			}
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- writing to php://output stream.
		exit;
	}

	/**
	 * Neutralise CSV formula injection by prefixing risky cells.
	 *
	 * Learner-controlled text could start with =, +, -, @, tab or CR, which
	 * spreadsheet apps may execute as a formula. Prefix such values with a
	 * single quote so they are treated as plain text.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private function csv_safe( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
