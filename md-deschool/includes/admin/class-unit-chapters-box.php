<?php
/**
 * Authoring helpers on the unit editor: a chapters overview table and a
 * "quick guide" checklist that makes the build flow clear for content authors.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Consolidates chapter management within the unit edit screen.
 */
final class Unit_Chapters_Box {

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . Data::POST_TYPE_UNIT, array( $this, 'add' ) );
	}

	/**
	 * Register the metaboxes.
	 */
	public function add(): void {
		add_meta_box(
			'mdds-unit-chapters',
			__( 'פרקי היחידה', 'md-deschool' ),
			array( $this, 'render' ),
			Data::POST_TYPE_UNIT,
			'normal',
			'high'
		);

		add_meta_box(
			'mdds-unit-guide',
			__( 'מדריך מהיר', 'md-deschool' ),
			array( $this, 'render_guide' ),
			Data::POST_TYPE_UNIT,
			'side',
			'high'
		);
	}

	/**
	 * Render the chapter list.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'יש לשמור את היחידה (טיוטה) לפני הוספת פרקים.', 'md-deschool' ) . '</p>';
			return;
		}

		$add_url  = $this->add_chapter_url( (int) $post->ID );
		$chapters = $this->get_admin_chapters( (int) $post->ID );

		if ( empty( $chapters ) ) {
			echo '<p>' . esc_html__( 'עדיין לא נוספו פרקים ליחידה זו. כל פרק כולל וידאו, מצגת ומשימות משלו.', 'md-deschool' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'סדר הפרקים נקבע לפי שדה "סדר" בכל פרק (תיבת "מאפייני עמוד"). גררו/עדכנו את הסדר שם.', 'md-deschool' ) . '</p>';
			echo '<table class="widefat striped mdds-chapters-table"><thead><tr>';
			echo '<th class="mdds-col-order">' . esc_html__( 'סדר', 'md-deschool' ) . '</th>';
			echo '<th>' . esc_html__( 'כותרת הפרק', 'md-deschool' ) . '</th>';
			echo '<th>' . esc_html__( 'סטטוס', 'md-deschool' ) . '</th>';
			echo '<th class="mdds-col-tasks">' . esc_html__( 'משימות', 'md-deschool' ) . '</th>';
			echo '<th class="mdds-col-actions">' . esc_html__( 'פעולות', 'md-deschool' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $chapters as $chapter ) {
				$edit_link = get_edit_post_link( $chapter->ID );
				$tasks     = count( Data::get_tasks( (int) $chapter->ID ) );
				echo '<tr>';
				echo '<td>' . esc_html( (string) $chapter->menu_order ) . '</td>';
				echo '<td><strong>' . esc_html( $chapter->post_title ) . '</strong></td>';
				echo '<td>' . wp_kses_post( $this->status_badge( (string) $chapter->post_status ) ) . '</td>';
				echo '<td>' . esc_html( (string) $tasks ) . '</td>';
				echo '<td><a href="' . esc_url( (string) $edit_link ) . '">' . esc_html__( 'עריכה', 'md-deschool' ) . '</a></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '<p class="mdds-chapters-actions"><a class="button button-primary button-hero" href="' . esc_url( $add_url ) . '">' . esc_html__( '+ הוספת פרק חדש', 'md-deschool' ) . '</a></p>';
	}

	/**
	 * Render the quick-guide checklist.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_guide( \WP_Post $post ): void {
		$unit_id = (int) $post->ID;
		$saved   = 'auto-draft' !== $post->post_status;

		$has_chapters = $saved && ! empty( $this->get_admin_chapters( $unit_id ) );
		$has_quiz     = ! empty( Data::get_quiz_questions( $unit_id ) );
		$has_product  = (int) get_post_meta( $unit_id, Data::META_PRODUCT_ID, true ) > 0;
		$sequential   = Data::is_sequential( $unit_id );

		echo '<ol class="mdds-guide-list">';
		$this->guide_item( $saved, __( 'מילוי כותרת, תיאור ו"מה כוללת היחידה"', 'md-deschool' ) );
		$this->guide_item( $has_chapters, __( 'הוספת פרקים (וידאו / מצגת / משימות לכל פרק)', 'md-deschool' ) );
		$this->guide_item( $has_quiz, __( 'הגדרת מבחן סיכום (אופציונלי)', 'md-deschool' ), true );

		if ( class_exists( 'WooCommerce' ) ) {
			$this->guide_item( $has_product, __( 'שיוך מוצר WooCommerce לפתיחת גישה', 'md-deschool' ) );
		}

		echo '</ol>';

		echo '<p class="mdds-guide-note">';
		echo $sequential
			? esc_html__( 'מצב פעיל: למידה רציפה (טפטוף) — פרקים נפתחים בזה אחר זה.', 'md-deschool' )
			: esc_html__( 'טיפ: ניתן להפעיל "למידה רציפה" בתיבת פרטי היחידה לפתיחת פרקים בהדרגה.', 'md-deschool' );
		echo '</p>';

		if ( $saved ) {
			echo '<p><a class="button" href="' . esc_url( (string) get_permalink( $unit_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'תצוגה מקדימה של הקורס', 'md-deschool' ) . '</a></p>';
			echo '<p><a class="button button-primary" href="' . esc_url( $this->add_chapter_url( $unit_id ) ) . '">' . esc_html__( '+ הוספת פרק', 'md-deschool' ) . '</a></p>';
		}
	}

	/**
	 * Render a single checklist item.
	 *
	 * @param bool   $done     Whether the step is complete.
	 * @param string $label    Step label.
	 * @param bool   $optional Whether the step is optional.
	 */
	private function guide_item( bool $done, string $label, bool $optional = false ): void {
		$class = 'mdds-guide-item ' . ( $done ? 'is-done' : 'is-todo' );
		echo '<li class="' . esc_attr( $class ) . '">';
		echo '<span class="mdds-guide-check" aria-hidden="true">' . ( $done ? '&#10003;' : '&#9675;' ) . '</span>';
		echo '<span>' . esc_html( $label );
		if ( $optional ) {
			echo ' <em class="mdds-guide-optional">' . esc_html__( '(רשות)', 'md-deschool' ) . '</em>';
		}
		echo '</span></li>';
	}

	/**
	 * Coloured status badge for a chapter post status.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	private function status_badge( string $status ): string {
		$map = array(
			'publish' => array( __( 'פורסם', 'md-deschool' ), 'is-publish' ),
			'draft'   => array( __( 'טיוטה', 'md-deschool' ), 'is-draft' ),
			'pending' => array( __( 'ממתין', 'md-deschool' ), 'is-pending' ),
			'private' => array( __( 'פרטי', 'md-deschool' ), 'is-private' ),
			'future'  => array( __( 'מתוזמן', 'md-deschool' ), 'is-future' ),
		);

		$item  = $map[ $status ] ?? array( $status, 'is-draft' );
		$label = (string) $item[0];
		$cls   = (string) $item[1];

		return '<span class="mdds-status-badge ' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Build the "add chapter" URL pre-linked to this unit.
	 *
	 * @param int $unit_id Unit ID.
	 * @return string
	 */
	private function add_chapter_url( int $unit_id ): string {
		return add_query_arg(
			array(
				'post_type' => Data::POST_TYPE_CHAPTER,
				'mdds_unit' => $unit_id,
			),
			admin_url( 'post-new.php' )
		);
	}

	/**
	 * Get a unit's chapters for admin management, including unpublished ones.
	 *
	 * The front-end helper (Data::get_chapters) is intentionally publish-only;
	 * the editor must also see draft/pending/private/scheduled chapters.
	 *
	 * @param int $unit_id Unit ID.
	 * @return \WP_Post[]
	 */
	private function get_admin_chapters( int $unit_id ): array {
		return get_posts(
			array(
				'post_type'              => Data::POST_TYPE_CHAPTER,
				'post_parent'            => $unit_id,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
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
}
