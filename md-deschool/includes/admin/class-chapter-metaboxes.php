<?php
/**
 * Metaboxes for the Chapter post type.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and saves all chapter-level metaboxes.
 */
final class Chapter_Metaboxes {

	private const NONCE_ACTION = 'mdds_save_chapter';
	private const NONCE_NAME   = 'mdds_chapter_nonce';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . Data::POST_TYPE_CHAPTER, array( $this, 'add' ) );
		add_action( 'save_post_' . Data::POST_TYPE_CHAPTER, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the metaboxes.
	 */
	public function add(): void {
		add_meta_box( 'mdds-chapter-unit', __( 'שיוך ליחידת תוכן', 'md-deschool' ), array( $this, 'render_unit' ), Data::POST_TYPE_CHAPTER, 'side', 'high' );
		add_meta_box( 'mdds-chapter-content', __( 'תוכן הפרק', 'md-deschool' ), array( $this, 'render_content' ), Data::POST_TYPE_CHAPTER, 'normal', 'high' );
		add_meta_box( 'mdds-chapter-tasks', __( 'שאלות / משימות לאחר הפרק', 'md-deschool' ), array( $this, 'render_tasks' ), Data::POST_TYPE_CHAPTER, 'normal', 'default' );
	}

	/**
	 * Render the parent-unit selector.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_unit( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$units = get_posts(
			array(
				'post_type'        => Data::POST_TYPE_UNIT,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		// Pre-select the unit when arriving from the unit "add chapter" link.
		$current_parent = (int) $post->post_parent;
		if ( 0 === $current_parent && isset( $_GET['mdds_unit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only default selection.
			$current_parent = absint( wp_unslash( $_GET['mdds_unit'] ) );
		}
		?>
		<p class="mdds-field">
			<label for="mdds-chapter-parent"><strong><?php esc_html_e( 'יחידת התוכן', 'md-deschool' ); ?></strong></label>
			<select id="mdds-chapter-parent" name="mdds_chapter_parent" class="widefat">
				<option value="0"><?php esc_html_e( '— בחירת יחידה —', 'md-deschool' ); ?></option>
				<?php foreach ( $units as $unit ) : ?>
					<option value="<?php echo esc_attr( (string) $unit->ID ); ?>" <?php selected( $current_parent, $unit->ID ); ?>>
						<?php echo esc_html( $unit->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'הסדר בין הפרקים נקבע בשדה "סדר" בתיבת מאפייני העמוד.', 'md-deschool' ); ?></p>
		<?php
	}

	/**
	 * Render description, video and presentation fields.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_content( \WP_Post $post ): void {
		Field_Renderer::textarea( Data::META_CHAPTER_DESC, __( 'תיאור הפרק', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_CHAPTER_DESC, true ), 4 );

		echo '<hr /><h4>' . esc_html__( 'וידאו הפרק', 'md-deschool' ) . '</h4>';
		Field_Renderer::text( Data::META_VIDEO_URL, __( 'קישור להטמעה (YouTube / Vimeo / Bunny)', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_VIDEO_URL, true ), 'url' );
		Field_Renderer::textarea( Data::META_VIDEO_EMBED, __( 'או קוד הטמעה (iframe)', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_VIDEO_EMBED, true ), 3 );
		Field_Renderer::media( Data::META_VIDEO_FILE, __( 'או קובץ וידאו מהמדיה', 'md-deschool' ), (int) get_post_meta( $post->ID, Data::META_VIDEO_FILE, true ), __( 'בחירת וידאו', 'md-deschool' ) );

		echo '<hr /><h4>' . esc_html__( 'מצגת הפרק', 'md-deschool' ) . '</h4>';
		Field_Renderer::media( Data::META_PRES_FILE, __( 'קובץ מצגת (PDF)', 'md-deschool' ), (int) get_post_meta( $post->ID, Data::META_PRES_FILE, true ), __( 'בחירת קובץ', 'md-deschool' ) );
		Field_Renderer::text( Data::META_PRES_URL, __( 'או קישור למצגת', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_PRES_URL, true ), 'url' );
		Field_Renderer::textarea( Data::META_PRES_EMBED, __( 'או קוד הטמעת מצגת', 'md-deschool' ), (string) get_post_meta( $post->ID, Data::META_PRES_EMBED, true ), 3 );
	}

	/**
	 * Render the tasks repeater.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_tasks( \WP_Post $post ): void {
		$tasks = Data::get_tasks( $post->ID );
		?>
		<div class="mdds-repeater" data-mdds-repeater="tasks">
			<div class="mdds-repeater-items" data-mdds-repeater-items>
				<?php
				if ( empty( $tasks ) ) {
					$this->render_task_row( 0, array() );
				} else {
					foreach ( $tasks as $i => $task ) {
						$this->render_task_row( (int) $i, (array) $task );
					}
				}
				?>
			</div>
			<button type="button" class="button button-secondary" data-mdds-repeater-add><?php esc_html_e( 'הוספת שאלה / משימה', 'md-deschool' ); ?></button>
		</div>

		<script type="text/template" data-mdds-repeater-template="tasks">
			<?php $this->render_task_row( 0, array(), true ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single task row.
	 *
	 * @param int                 $index    Row index.
	 * @param array<string,mixed> $task     Task data.
	 * @param bool                $template Whether this is the JS template.
	 */
	private function render_task_row( int $index, array $task, bool $template = false ): void {
		$key   = $template ? '__index__' : (string) $index;
		$title = isset( $task['title'] ) ? (string) $task['title'] : '';
		$text  = isset( $task['instruction'] ) ? (string) $task['instruction'] : '';
		$label = isset( $task['button_label'] ) ? (string) $task['button_label'] : '';
		$allow = isset( $task['allow_file'] ) ? (bool) $task['allow_file'] : true;
		$base  = 'mdds_tasks[' . $key . ']';
		?>
		<div class="mdds-repeater-item" data-mdds-repeater-item>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'כותרת פנימית (אופציונלי)', 'md-deschool' ); ?></strong></label>
				<input type="text" name="<?php echo esc_attr( $base . '[title]' ); ?>" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
			</p>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'הנחיה / טקסט המשימה', 'md-deschool' ); ?></strong></label>
				<textarea name="<?php echo esc_attr( $base . '[instruction]' ); ?>" rows="3" class="widefat"><?php echo esc_textarea( $text ); ?></textarea>
			</p>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'טקסט כפתור השמירה (ברירת מחדל: שמירת תשובה)', 'md-deschool' ); ?></strong></label>
				<input type="text" name="<?php echo esc_attr( $base . '[button_label]' ); ?>" value="<?php echo esc_attr( $label ); ?>" class="widefat" />
			</p>
			<p class="mdds-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $base . '[allow_file]' ); ?>" value="1" <?php checked( $allow ); ?> />
					<?php esc_html_e( 'לאפשר צירוף קובץ', 'md-deschool' ); ?>
				</label>
			</p>
			<button type="button" class="button-link mdds-repeater-remove" data-mdds-repeater-remove><?php esc_html_e( 'הסרה', 'md-deschool' ); ?></button>
			<hr />
		</div>
		<?php
	}

	/**
	 * Save chapter meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Parent unit (stored as post_parent). Unhook to avoid infinite loop.
		if ( isset( $_POST['mdds_chapter_parent'] ) ) {
			$parent = absint( wp_unslash( $_POST['mdds_chapter_parent'] ) );
			if ( (int) $post->post_parent !== $parent ) {
				remove_action( 'save_post_' . Data::POST_TYPE_CHAPTER, array( $this, 'save' ), 10 );
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_parent' => $parent,
					)
				);
				add_action( 'save_post_' . Data::POST_TYPE_CHAPTER, array( $this, 'save' ), 10, 2 );
			}
		}

		// Description.
		$desc = isset( $_POST[ Data::META_CHAPTER_DESC ] ) ? wp_kses_post( wp_unslash( $_POST[ Data::META_CHAPTER_DESC ] ) ) : '';
		update_post_meta( $post_id, Data::META_CHAPTER_DESC, $desc );

		// Video.
		$video_url = isset( $_POST[ Data::META_VIDEO_URL ] ) ? esc_url_raw( wp_unslash( $_POST[ Data::META_VIDEO_URL ] ) ) : '';
		update_post_meta( $post_id, Data::META_VIDEO_URL, $video_url );

		$video_embed = isset( $_POST[ Data::META_VIDEO_EMBED ] ) ? $this->sanitize_embed( wp_unslash( $_POST[ Data::META_VIDEO_EMBED ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in sanitize_embed().
		update_post_meta( $post_id, Data::META_VIDEO_EMBED, $video_embed );

		update_post_meta( $post_id, Data::META_VIDEO_FILE, isset( $_POST[ Data::META_VIDEO_FILE ] ) ? absint( wp_unslash( $_POST[ Data::META_VIDEO_FILE ] ) ) : 0 );

		// Presentation.
		update_post_meta( $post_id, Data::META_PRES_FILE, isset( $_POST[ Data::META_PRES_FILE ] ) ? absint( wp_unslash( $_POST[ Data::META_PRES_FILE ] ) ) : 0 );
		$pres_url = isset( $_POST[ Data::META_PRES_URL ] ) ? esc_url_raw( wp_unslash( $_POST[ Data::META_PRES_URL ] ) ) : '';
		update_post_meta( $post_id, Data::META_PRES_URL, $pres_url );
		$pres_embed = isset( $_POST[ Data::META_PRES_EMBED ] ) ? $this->sanitize_embed( wp_unslash( $_POST[ Data::META_PRES_EMBED ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in sanitize_embed().
		update_post_meta( $post_id, Data::META_PRES_EMBED, $pres_embed );

		// Tasks.
		$this->save_tasks( $post_id );
	}

	/**
	 * Sanitise and persist the tasks repeater.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_tasks( int $post_id ): void {
		if ( ! isset( $_POST['mdds_tasks'] ) || ! is_array( $_POST['mdds_tasks'] ) ) {
			update_post_meta( $post_id, Data::META_TASKS, array() );
			return;
		}

		$raw   = wp_unslash( $_POST['mdds_tasks'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		$tasks = array();

		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$instruction = isset( $row['instruction'] ) ? sanitize_textarea_field( (string) $row['instruction'] ) : '';
			if ( '' === $instruction ) {
				continue;
			}

			$tasks[] = array(
				'title'        => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
				'instruction'  => $instruction,
				'button_label' => isset( $row['button_label'] ) ? sanitize_text_field( (string) $row['button_label'] ) : '',
				'allow_file'   => ! empty( $row['allow_file'] ),
			);
		}

		update_post_meta( $post_id, Data::META_TASKS, $tasks );
	}

	/**
	 * Sanitise an embed code, allowing only iframes.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_embed( string $value ): string {
		return wp_kses(
			$value,
			array(
				'iframe' => array(
					'src'             => true,
					'width'           => true,
					'height'          => true,
					'frameborder'     => true,
					'allow'           => true,
					'allowfullscreen' => true,
					'loading'         => true,
					'title'           => true,
					'style'           => true,
				),
			)
		);
	}
}
