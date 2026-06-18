<?php
/**
 * Renders the unit course/sales interface as a reusable block.
 *
 * Used by the single-unit template AND by the [deschool_course] shortcode, so
 * the full learning interface can be placed inside a page builder template
 * (e.g. an Elementor Theme Builder "Single" template) — a plain "Post Content"
 * widget is NOT enough, because the interface is not stored in post_content.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Frontend;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the course/sales markup for a unit.
 */
final class Course_View {

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_shortcode( 'deschool_course', array( $this, 'shortcode' ) );
	}

	/**
	 * [deschool_course id="123"] — renders the course for the given/current unit.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts    = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'deschool_course' );
		$unit_id = (int) $atts['id'];
		if ( $unit_id <= 0 ) {
			$unit_id = (int) get_queried_object_id();
		}

		if ( get_post_type( $unit_id ) !== Data::POST_TYPE_UNIT ) {
			return '';
		}

		// Ensure assets load even when our template was bypassed (e.g. Elementor).
		if ( Access_Control::can_access( $unit_id ) ) {
			Assets::enqueue_script( $unit_id );
		} else {
			Assets::enqueue_style();
		}

		return self::render( $unit_id );
	}

	/**
	 * Render the full course/sales interface for a unit.
	 *
	 * @param int $unit_id Unit ID.
	 * @return string HTML.
	 */
	public static function render( int $unit_id ): string {
		$can_access = Access_Control::can_access( $unit_id );
		$chapters   = Data::get_chapters( $unit_id );
		$user_id    = get_current_user_id();
		$progress   = $user_id > 0 ? Data::get_progress( $user_id, $unit_id ) : array(
			'completed' => 0,
			'total'     => count( $chapters ),
		);

		// Base URL is the sales page; the learning interface lives at /learn/.
		$view_course = $can_access && ( null !== get_query_var( 'learn', null ) );

		// Learning mode uses a wider container for the two-column course layout.
		$wrap_class = 'mdds-unit' . ( $view_course ? ' mdds-unit--learning' : '' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrap_class ); ?>" dir="auto">
			<div class="mdds-unit-inner">

				<?php if ( ! $view_course ) : ?>

					<?php
					Template_Loader::get_part(
						'sales',
						array(
							'unit_id'    => $unit_id,
							'chapters'   => $chapters,
							'can_access' => $can_access,
						)
					);

					Template_Loader::get_part( 'lecturer', array( 'unit_id' => $unit_id ) );
					?>

				<?php else : ?>

					<?php
					// Learning mode: a compact header (title + progress) and a short
					// instructor line, so the course content stays the focus.
					Template_Loader::get_part(
						'unit-header',
						array(
							'unit_id'    => $unit_id,
							'progress'   => $progress,
							'can_access' => $can_access,
							'compact'    => true,
						)
					);

					Template_Loader::get_part(
						'lecturer',
						array(
							'unit_id' => $unit_id,
							'compact' => true,
						)
					);

					$sequential   = Data::is_sequential( $unit_id );
					$total        = count( $chapters );
					$all_complete = $user_id > 0 && Data::all_chapters_completed( $user_id, $unit_id );

					$states  = array();
					$current = -1;
					foreach ( $chapters as $i => $chapter ) {
						$cid       = (int) $chapter->ID;
						$completed = $user_id > 0 && Data::is_chapter_completed( $user_id, $cid );
						$unlocked  = Data::is_chapter_unlocked( $user_id, $unit_id, $cid, $chapters );

						$states[ $i ] = array(
							'completed' => $completed,
							'unlocked'  => $unlocked,
						);

						if ( -1 === $current && $unlocked && ! $completed ) {
							$current = $i;
						}
					}

					$questions   = Data::get_quiz_questions( $unit_id );
					$has_quiz    = ! empty( $questions );
					$quiz_locked = $sequential && ! $all_complete;
					$quiz_index  = $total;
					$qa_index    = $total + ( $has_quiz ? 1 : 0 );

					if ( -1 === $current ) {
						$current = ( $has_quiz && ! $quiz_locked ) ? $quiz_index : 0;
					}
					?>

					<div class="mdds-course-toolbar">
						<button type="button" class="mdds-button mdds-button-outline mdds-focus-toggle" data-mdds-focus-toggle aria-pressed="false">
							<?php esc_html_e( 'מצב מיקוד', 'md-deschool' ); ?>
						</button>
					</div>

					<div class="mdds-course-layout">

						<aside class="mdds-course-sidebar" aria-label="<?php esc_attr_e( 'מבנה הקורס', 'md-deschool' ); ?>">
							<nav class="mdds-chapter-nav" data-mdds-step-nav>
								<h2 class="mdds-course-outline-title"><?php esc_html_e( 'מבנה הקורס', 'md-deschool' ); ?></h2>
								<ol class="mdds-chapter-list">
									<?php
									foreach ( $chapters as $i => $chapter ) :
										$st        = $states[ $i ];
										$item_cls  = 'mdds-step';
										$item_cls .= $st['completed'] ? ' is-completed' : '';
										$item_cls .= $st['unlocked'] ? '' : ' is-locked';
										?>
										<li class="<?php echo esc_attr( $item_cls ); ?>">
											<button type="button" class="mdds-step-link"
												data-mdds-step="<?php echo esc_attr( (string) $i ); ?>"
												data-target="mdds-chapter-<?php echo esc_attr( (string) $chapter->ID ); ?>"
												<?php echo $st['unlocked'] ? '' : 'aria-disabled="true" disabled'; ?>>
												<span class="mdds-step-index"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
												<span class="mdds-step-name"><?php echo esc_html( $chapter->post_title ); ?></span>
												<span class="mdds-step-state" aria-hidden="true"></span>
											</button>
										</li>
									<?php endforeach; ?>

									<?php if ( $has_quiz ) : ?>
										<li class="mdds-step mdds-step-quiz<?php echo $quiz_locked ? ' is-locked' : ''; ?>">
											<button type="button" class="mdds-step-link"
												data-mdds-step="<?php echo esc_attr( (string) $quiz_index ); ?>"
												data-target="mdds-quiz-panel"
												<?php echo $quiz_locked ? 'aria-disabled="true" disabled' : ''; ?>>
												<span class="mdds-step-index" aria-hidden="true">★</span>
												<span class="mdds-step-name"><?php esc_html_e( 'מבחן סיכום', 'md-deschool' ); ?></span>
												<span class="mdds-step-state" aria-hidden="true"></span>
											</button>
										</li>
									<?php endif; ?>

									<li class="mdds-step mdds-step-qa">
										<button type="button" class="mdds-step-link"
											data-mdds-step="<?php echo esc_attr( (string) $qa_index ); ?>"
											data-target="mdds-qa-panel">
											<span class="mdds-step-index" aria-hidden="true">?</span>
											<span class="mdds-step-name"><?php esc_html_e( 'שאלות ותשובות', 'md-deschool' ); ?></span>
											<span class="mdds-step-state" aria-hidden="true"></span>
										</button>
									</li>
								</ol>
							</nav>

							<?php
							$account_url = Account::get_account_url();
							if ( '' !== $account_url ) :
								?>
								<p class="mdds-sidebar-account">
									<a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'האזור האישי שלי', 'md-deschool' ); ?></a>
								</p>
							<?php endif; ?>
						</aside>

						<div class="mdds-course-main">
							<div class="mdds-chapters" data-mdds-stepper data-current="<?php echo esc_attr( (string) $current ); ?>">
								<?php
								foreach ( $chapters as $i => $chapter ) {
									Template_Loader::get_part(
										'chapter',
										array(
											'unit_id'    => $unit_id,
											'chapter'    => $chapter,
											'number'     => $i + 1,
											'index'      => $i,
											'total'      => $total,
											'user_id'    => $user_id,
											'sequential' => $sequential,
											'completed'  => $states[ $i ]['completed'],
											'locked'     => ! $states[ $i ]['unlocked'],
										)
									);
								}

								if ( $has_quiz && $quiz_locked ) :
									?>
									<section class="mdds-quiz-locked" data-mdds-panel data-index="<?php echo esc_attr( (string) $quiz_index ); ?>" data-locked="1" tabindex="-1" aria-labelledby="mdds-quiz-locked-title">
										<h2 id="mdds-quiz-locked-title"><?php esc_html_e( 'מבחן הסיכום נעול', 'md-deschool' ); ?></h2>
										<p><?php esc_html_e( 'השלימו את כל פרקי היחידה כדי לפתוח את מבחן הסיכום.', 'md-deschool' ); ?></p>
									</section>
									<?php
								elseif ( $has_quiz ) :
									?>
									<div id="mdds-quiz-panel" class="mdds-quiz-wrap" data-mdds-panel data-index="<?php echo esc_attr( (string) $quiz_index ); ?>" data-locked="0" tabindex="-1">
										<?php
										Template_Loader::get_part(
											'quiz',
											array(
												'unit_id' => $unit_id,
												'user_id' => $user_id,
											)
										);
										?>
									</div>
									<?php
								endif;
								?>

								<div id="mdds-qa-panel" class="mdds-qa-wrap" data-mdds-panel data-index="<?php echo esc_attr( (string) $qa_index ); ?>" data-locked="0" tabindex="-1">
									<?php QA::render( $unit_id, $user_id ); ?>
								</div>
							</div>
						</div>

					</div>

				<?php endif; ?>

				<?php Template_Loader::get_part( 'consultation', array( 'unit_id' => $unit_id ) ); ?>

			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
