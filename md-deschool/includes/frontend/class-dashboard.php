<?php
/**
 * Learner dashboard shortcode: [deschool_dashboard].
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the units a user has access to, with progress and quiz status.
 */
final class Dashboard {

	private const SHORTCODE = 'deschool_dashboard';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
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
			wp_enqueue_style(
				Assets::HANDLE,
				MDDS_URL . 'assets/css/frontend.css',
				array(),
				MDDS_VERSION
			);
		}
	}

	/**
	 * Render the dashboard.
	 *
	 * @return string
	 */
	public function render(): string {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '<div class="mdds-dashboard mdds-dashboard-guest"><p>' . esc_html__( 'יש להתחבר כדי לצפות ביחידות שלך.', 'md-deschool' ) . '</p></div>';
		}

		$units      = Data::get_all_units();
		$accessible = array_filter(
			$units,
			static fn ( \WP_Post $unit ): bool => Access_Control::can_access( (int) $unit->ID, $user_id )
		);

		ob_start();
		echo '<section class="mdds-dashboard mdds-unit">';
		echo '<h2>' . esc_html__( 'יחידות התוכן שלי', 'md-deschool' ) . '</h2>';

		if ( empty( $accessible ) ) {
			echo '<p>' . esc_html__( 'עדיין אין לך גישה ליחידות תוכן.', 'md-deschool' ) . '</p>';
		} else {
			echo '<ul class="mdds-dashboard-list">';
			foreach ( $accessible as $unit ) {
				$this->render_card( (int) $unit->ID, $unit, $user_id );
			}
			echo '</ul>';
		}

		echo '</section>';

		return (string) ob_get_clean();
	}

	/**
	 * Render a single unit card.
	 *
	 * @param int      $unit_id Unit ID.
	 * @param \WP_Post $unit    Unit post.
	 * @param int      $user_id User ID.
	 */
	private function render_card( int $unit_id, \WP_Post $unit, int $user_id ): void {
		$progress = Data::get_progress( $user_id, $unit_id );
		$total    = (int) $progress['total'];
		$done     = (int) $progress['completed'];
		$percent  = $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;
		$quiz     = Data::get_quiz_result( $user_id, $unit_id );
		?>
		<li class="mdds-dashboard-card">
			<a class="mdds-dashboard-title" href="<?php echo esc_url( (string) get_permalink( $unit_id ) ); ?>">
				<?php echo esc_html( $unit->post_title ); ?>
			</a>
			<?php if ( $total > 0 ) : ?>
				<div class="mdds-progress">
					<div class="mdds-progress-bar" role="progressbar"
						aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>"
						aria-label="<?php esc_attr_e( 'התקדמות ביחידה', 'md-deschool' ); ?>">
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
			<?php if ( ! empty( $quiz ) ) : ?>
				<p class="mdds-dashboard-quiz <?php echo ! empty( $quiz['passed'] ) ? 'is-pass' : 'is-fail'; ?>">
					<?php
					printf(
						/* translators: %d: quiz score */
						esc_html__( 'ציון במבחן: %d%%', 'md-deschool' ),
						(int) ( $quiz['score'] ?? 0 )
					);
					?>
				</p>
			<?php endif; ?>
		</li>
		<?php
	}
}
