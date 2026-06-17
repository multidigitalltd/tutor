<?php
/**
 * Course catalog grid: shared by the unit archive and the
 * [deschool_courses] shortcode.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;
use MultiDigital\DeSchool\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a responsive grid of course cards.
 */
final class Catalog {

	private const SHORTCODE = 'deschool_courses';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
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
	 * Render the [deschool_courses] shortcode (full published catalog).
	 *
	 * @return string
	 */
	public function shortcode(): string {
		return self::render_grid( Data::get_all_units() );
	}

	/**
	 * Render a grid of course cards.
	 *
	 * @param \WP_Post[] $units Units to display.
	 * @return string
	 */
	public static function render_grid( array $units ): string {
		if ( empty( $units ) ) {
			return '<div class="mdds-courses mdds-unit"><p>' . esc_html__( 'אין קורסים זמינים כרגע.', 'md-deschool' ) . '</p></div>';
		}

		$user_id = get_current_user_id();

		ob_start();
		echo '<div class="mdds-courses mdds-unit"><ul class="mdds-courses-grid">';
		foreach ( $units as $unit ) {
			if ( $unit instanceof \WP_Post ) {
				self::render_card( $unit, $user_id );
			}
		}
		echo '</ul></div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render a single course card.
	 *
	 * @param \WP_Post $unit    Unit post.
	 * @param int      $user_id Current user ID (0 if guest).
	 */
	private static function render_card( \WP_Post $unit, int $user_id ): void {
		$unit_id    = (int) $unit->ID;
		$url        = (string) get_permalink( $unit_id );
		$short      = (string) get_post_meta( $unit_id, Data::META_SHORT_DESC, true );
		$chapters   = count( Data::get_chapters( $unit_id ) );
		$can_access = Access_Control::can_access( $unit_id, $user_id );
		// Buyers go straight to the learning interface; everyone else to the sales page.
		$url = ( $can_access && $user_id > 0 ) ? Data::get_learn_url( $unit_id ) : $url;
		?>
		<li class="mdds-course-card">
			<a class="mdds-course-card-media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				if ( has_post_thumbnail( $unit_id ) ) {
					echo get_the_post_thumbnail( $unit_id, 'medium_large', array( 'loading' => 'lazy' ) );
				} else {
					echo '<span class="mdds-course-card-placeholder" aria-hidden="true"></span>';
				}
				?>
			</a>
			<div class="mdds-course-card-body">
				<h3 class="mdds-course-card-title">
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $unit->post_title ); ?></a>
				</h3>

				<?php if ( '' !== $short ) : ?>
					<p class="mdds-course-card-desc"><?php echo esc_html( $short ); ?></p>
				<?php endif; ?>

				<p class="mdds-course-card-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of chapters */
							_n( '%d פרק', '%d פרקים', $chapters, 'md-deschool' ),
							$chapters
						)
					);
					?>
					<?php if ( $can_access && $user_id > 0 ) : ?>
						<span class="mdds-course-card-badge"><?php esc_html_e( 'יש לך גישה', 'md-deschool' ); ?></span>
					<?php endif; ?>
				</p>

				<a class="mdds-button <?php echo $can_access ? 'mdds-button-primary' : 'mdds-button-outline'; ?>" href="<?php echo esc_url( $url ); ?>">
					<?php echo $can_access ? esc_html__( 'מעבר לקורס', 'md-deschool' ) : esc_html__( 'לפרטים ולרכישה', 'md-deschool' ); ?>
				</a>
			</div>
		</li>
		<?php
	}
}
