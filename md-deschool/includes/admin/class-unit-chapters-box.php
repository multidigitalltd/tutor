<?php
/**
 * "Chapters" overview metabox shown on the unit editor.
 *
 * Lists the chapters belonging to the current unit with quick edit links and
 * an "add chapter" button that pre-links the new chapter to this unit.
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
	 * Register the metabox.
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
	}

	/**
	 * Render the chapter list.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		$add_url = add_query_arg(
			array(
				'post_type' => Data::POST_TYPE_CHAPTER,
				'mdds_unit' => $post->ID,
			),
			admin_url( 'post-new.php' )
		);

		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'יש לשמור את היחידה לפני הוספת פרקים.', 'md-deschool' ) . '</p>';
			return;
		}

		$chapters = Data::get_chapters( $post->ID );

		if ( empty( $chapters ) ) {
			echo '<p>' . esc_html__( 'עדיין לא נוספו פרקים ליחידה זו.', 'md-deschool' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'סדר', 'md-deschool' ) . '</th>';
			echo '<th>' . esc_html__( 'כותרת הפרק', 'md-deschool' ) . '</th>';
			echo '<th>' . esc_html__( 'משימות', 'md-deschool' ) . '</th>';
			echo '<th>' . esc_html__( 'פעולות', 'md-deschool' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $chapters as $chapter ) {
				$edit_link = get_edit_post_link( $chapter->ID );
				$tasks     = count( Data::get_tasks( (int) $chapter->ID ) );
				echo '<tr>';
				echo '<td>' . esc_html( (string) $chapter->menu_order ) . '</td>';
				echo '<td><strong>' . esc_html( $chapter->post_title ) . '</strong></td>';
				echo '<td>' . esc_html( (string) $tasks ) . '</td>';
				echo '<td><a href="' . esc_url( (string) $edit_link ) . '">' . esc_html__( 'עריכה', 'md-deschool' ) . '</a></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '<p style="margin-top:12px;"><a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'הוספת פרק', 'md-deschool' ) . '</a></p>';
	}
}
