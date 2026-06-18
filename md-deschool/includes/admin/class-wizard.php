<?php
/**
 * "Create course" wizard: a guided, multi-step alternative to building a unit
 * from scratch in the standard post editor. The wizard creates the unit and
 * its chapters, then hands off to the normal editor for fine-tuning — so the
 * existing post-style editing remains fully available.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the course-creation wizard.
 */
final class Wizard {

	private const NONCE_ACTION = 'mdds_wizard';
	private const NONCE_NAME   = 'mdds_wizard_nonce';
	private const PAGE_SLUG    = 'mdds-wizard';

	/**
	 * Admin page hook suffix (for conditional asset loading).
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_mdds_create_unit', array( $this, 'create' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Show success/error notices after a wizard redirect.
	 */
	public function notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by our own redirect.
		if ( isset( $_GET['mdds_wizard_done'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'הקורס נוצר בהצלחה! השלימו כאן את התכנים (וידאו, מצגות, מבחן ועוד).', 'md-deschool' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by our own redirect.
		if ( isset( $_GET['mdds_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'יצירת הקורס נכשלה. ודאו שמילאתם שם קורס ונסו שוב.', 'md-deschool' ) . '</p></div>';
		}
	}

	/**
	 * Add the wizard under the DeSchool menu.
	 */
	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Data::POST_TYPE_UNIT,
			__( 'אשף יצירת קורס', 'md-deschool' ),
			__( 'אשף יצירת קורס', 'md-deschool' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue admin assets on the wizard screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'mdds-admin', MDDS_URL . 'assets/css/admin.css', array(), MDDS_VERSION );
		wp_enqueue_script( 'mdds-admin', MDDS_URL . 'assets/js/admin.js', array(), MDDS_VERSION, true );
		wp_enqueue_media();
	}

	/**
	 * Render the wizard page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$products = class_exists( 'WooCommerce' ) ? $this->get_products() : array();
		?>
		<div class="wrap mdds-wizard-wrap">
			<h1><?php esc_html_e( 'אשף יצירת קורס', 'md-deschool' ); ?></h1>
			<p class="description"><?php esc_html_e( 'בנו קורס חדש בכמה שלבים. בסיום תועברו לעורך הרגיל להשלמת פרטים (וידאו, מצגות, מבחן ועוד).', 'md-deschool' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mdds-wizard" data-mdds-wizard>
				<input type="hidden" name="action" value="mdds_create_unit" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<ol class="mdds-wizard-steps" data-mdds-wizard-dots>
					<li data-step="0" class="is-active"><?php esc_html_e( 'פרטי הקורס', 'md-deschool' ); ?></li>
					<li data-step="1"><?php esc_html_e( 'פרקים', 'md-deschool' ); ?></li>
					<li data-step="2"><?php esc_html_e( 'מבחן', 'md-deschool' ); ?></li>
					<li data-step="3"><?php esc_html_e( 'סיום', 'md-deschool' ); ?></li>
				</ol>

				<section class="mdds-wizard-panel is-active" data-mdds-wizard-step="0">
					<h2><?php esc_html_e( 'פרטי הקורס', 'md-deschool' ); ?></h2>
					<p class="mdds-field">
						<label for="mdds-wiz-title"><strong><?php esc_html_e( 'שם הקורס', 'md-deschool' ); ?> *</strong></label>
						<input type="text" id="mdds-wiz-title" name="mdds_title" class="widefat" required />
					</p>
					<p class="mdds-field">
						<label for="mdds-wiz-short"><strong><?php esc_html_e( 'תיאור קצר', 'md-deschool' ); ?></strong></label>
						<textarea id="mdds-wiz-short" name="mdds_short" rows="2" class="widefat"></textarea>
					</p>
					<p class="mdds-field">
						<label for="mdds-wiz-includes"><strong><?php esc_html_e( 'מה כולל הקורס (שורה לכל נקודה)', 'md-deschool' ); ?></strong></label>
						<textarea id="mdds-wiz-includes" name="mdds_includes" rows="4" class="widefat"></textarea>
					</p>
					<?php Field_Renderer::media( 'mdds_thumb', __( 'תמונת הקורס', 'md-deschool' ), 0, __( 'בחירת תמונה', 'md-deschool' ) ); ?>
					<p class="mdds-field">
						<label>
							<input type="checkbox" name="mdds_sequential" value="1" />
							<?php esc_html_e( 'למידה רציפה (פתיחת פרק רק לאחר השלמת הקודם)', 'md-deschool' ); ?>
						</label>
					</p>
					<?php if ( ! empty( $products ) ) : ?>
						<p class="mdds-field">
							<label for="mdds-wiz-product"><strong><?php esc_html_e( 'מוצר WooCommerce שרכישתו פותחת גישה', 'md-deschool' ); ?></strong></label>
							<select id="mdds-wiz-product" name="mdds_product" class="widefat">
								<option value="0"><?php esc_html_e( '— ללא מוצר —', 'md-deschool' ); ?></option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo esc_attr( (string) $product->ID ); ?>"><?php echo esc_html( $product->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endif; ?>
				</section>

				<section class="mdds-wizard-panel" data-mdds-wizard-step="1">
					<h2><?php esc_html_e( 'פרקי הקורס', 'md-deschool' ); ?></h2>
					<p class="description"><?php esc_html_e( 'הוסיפו את פרקי הקורס. ניתן להוסיף וידאו, מצגות ומשימות מפורטות בעורך לאחר היצירה.', 'md-deschool' ); ?></p>

					<div class="mdds-repeater" data-mdds-repeater="wizchapters">
						<div class="mdds-repeater-items" data-mdds-repeater-items>
							<?php $this->render_chapter_row( 0 ); ?>
						</div>
						<button type="button" class="button button-secondary" data-mdds-repeater-add><?php esc_html_e( 'הוספת פרק', 'md-deschool' ); ?></button>
					</div>

					<script type="text/template" data-mdds-repeater-template="wizchapters">
						<?php $this->render_chapter_row( 0, true ); ?>
					</script>
				</section>

				<section class="mdds-wizard-panel" data-mdds-wizard-step="2">
					<h2><?php esc_html_e( 'מבחן סיכום (רשות)', 'md-deschool' ); ?></h2>
					<p class="description"><?php esc_html_e( 'ניתן להוסיף שאלות לבוחן הסיכום. אפשר גם לדלג ולהוסיף מאוחר יותר בעורך.', 'md-deschool' ); ?></p>

					<p class="mdds-field">
						<label for="mdds-wiz-pass"><strong><?php esc_html_e( 'ציון מעבר (%)', 'md-deschool' ); ?></strong></label>
						<input type="number" id="mdds-wiz-pass" name="mdds_quiz_pass" value="70" min="0" max="100" class="small-text" />
					</p>

					<div class="mdds-repeater" data-mdds-repeater="wizquiz">
						<div class="mdds-repeater-items" data-mdds-repeater-items></div>
						<button type="button" class="button button-secondary" data-mdds-repeater-add><?php esc_html_e( 'הוספת שאלה', 'md-deschool' ); ?></button>
					</div>

					<script type="text/template" data-mdds-repeater-template="wizquiz">
						<?php $this->render_quiz_row( 0, true ); ?>
					</script>
				</section>

				<section class="mdds-wizard-panel" data-mdds-wizard-step="3">
					<h2><?php esc_html_e( 'סיום ויצירה', 'md-deschool' ); ?></h2>
					<p><?php esc_html_e( 'הקורס והפרקים ייווצרו, ותועברו לעורך הקורס להשלמת התכנים (וידאו, מצגות, מבחן סיכום, אזור ייעוץ וכו\').', 'md-deschool' ); ?></p>
					<p>
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'יצירת הקורס', 'md-deschool' ); ?></button>
					</p>
				</section>

				<div class="mdds-wizard-nav">
					<button type="button" class="button" data-mdds-wizard-back hidden><?php esc_html_e( 'הקודם', 'md-deschool' ); ?></button>
					<button type="button" class="button button-primary" data-mdds-wizard-next><?php esc_html_e( 'הבא', 'md-deschool' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single chapter input row.
	 *
	 * @param int  $index    Row index.
	 * @param bool $template Whether this is the JS template (placeholder index).
	 */
	private function render_chapter_row( int $index, bool $template = false ): void {
		$key  = $template ? '__index__' : (string) $index;
		$base = 'mdds_chapters[' . $key . ']';
		?>
		<div class="mdds-repeater-item" data-mdds-repeater-item>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'כותרת הפרק', 'md-deschool' ); ?></strong></label>
				<input type="text" name="<?php echo esc_attr( $base . '[title]' ); ?>" class="widefat" />
			</p>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'תיאור הפרק', 'md-deschool' ); ?></strong></label>
				<textarea name="<?php echo esc_attr( $base . '[description]' ); ?>" rows="2" class="widefat"></textarea>
			</p>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'קישור וידאו (YouTube / Vimeo)', 'md-deschool' ); ?></strong></label>
				<input type="url" name="<?php echo esc_attr( $base . '[video_url]' ); ?>" class="widefat" placeholder="https://" />
			</p>
			<button type="button" class="button-link mdds-repeater-remove" data-mdds-repeater-remove><?php esc_html_e( 'הסרת פרק', 'md-deschool' ); ?></button>
			<hr />
		</div>
		<?php
	}

	/**
	 * Render a single quiz-question input row.
	 *
	 * @param int  $index    Row index.
	 * @param bool $template Whether this is the JS template (placeholder index).
	 */
	private function render_quiz_row( int $index, bool $template = false ): void {
		$key  = $template ? '__index__' : (string) $index;
		$base = 'mdds_quiz[' . $key . ']';
		?>
		<div class="mdds-repeater-item" data-mdds-repeater-item>
			<p class="mdds-field">
				<label><strong><?php esc_html_e( 'שאלה', 'md-deschool' ); ?></strong></label>
				<textarea name="<?php echo esc_attr( $base . '[question]' ); ?>" rows="2" class="widefat"></textarea>
			</p>
			<fieldset class="mdds-answers">
				<legend><?php esc_html_e( 'תשובות (סמנו את הנכונה)', 'md-deschool' ); ?></legend>
				<?php for ( $a = 0; $a < 4; $a++ ) : ?>
					<label class="mdds-answer-row">
						<input type="radio" name="<?php echo esc_attr( $base . '[correct]' ); ?>" value="<?php echo esc_attr( (string) $a ); ?>" <?php checked( 0, $a ); ?> />
						<input type="text" name="<?php echo esc_attr( $base . '[answers][]' ); ?>" class="widefat" placeholder="<?php echo esc_attr( sprintf( /* translators: %d: answer number */ __( 'תשובה %d', 'md-deschool' ), $a + 1 ) ); ?>" />
					</label>
				<?php endfor; ?>
			</fieldset>
			<button type="button" class="button-link mdds-repeater-remove" data-mdds-repeater-remove><?php esc_html_e( 'הסרת שאלה', 'md-deschool' ); ?></button>
			<hr />
		</div>
		<?php
	}

	/**
	 * Handle wizard submission: create the unit and its chapters.
	 */
	public function create(): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'בדיקת האבטחה נכשלה.', 'md-deschool' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'אין לך הרשאה.', 'md-deschool' ) );
		}

		$title = isset( $_POST['mdds_title'] ) ? sanitize_text_field( wp_unslash( $_POST['mdds_title'] ) ) : '';
		if ( '' === $title ) {
			wp_safe_redirect( add_query_arg( 'mdds_error', '1', $this->wizard_url() ) );
			exit;
		}

		$status  = current_user_can( 'publish_posts' ) ? 'publish' : 'draft';
		$unit_id = wp_insert_post(
			array(
				'post_type'   => Data::POST_TYPE_UNIT,
				'post_status' => $status,
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $unit_id ) || 0 === $unit_id ) {
			wp_safe_redirect( add_query_arg( 'mdds_error', '2', $this->wizard_url() ) );
			exit;
		}

		$unit_id = (int) $unit_id;

		update_post_meta( $unit_id, Data::META_SHORT_DESC, isset( $_POST['mdds_short'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mdds_short'] ) ) : '' );
		update_post_meta( $unit_id, Data::META_INCLUDES, isset( $_POST['mdds_includes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mdds_includes'] ) ) : '' );
		update_post_meta( $unit_id, Data::META_SEQUENTIAL, isset( $_POST['mdds_sequential'] ) ? 1 : 0 );

		$product_id = isset( $_POST['mdds_product'] ) ? absint( wp_unslash( $_POST['mdds_product'] ) ) : 0;
		if ( $product_id > 0 ) {
			update_post_meta( $unit_id, Data::META_PRODUCT_ID, $product_id );
		}

		$thumb_id = isset( $_POST['mdds_thumb'] ) ? absint( wp_unslash( $_POST['mdds_thumb'] ) ) : 0;
		if ( $thumb_id > 0 ) {
			set_post_thumbnail( $unit_id, $thumb_id );
		}

		$this->create_chapters( $unit_id, $status );
		$this->save_quiz( $unit_id );

		wp_safe_redirect( add_query_arg( 'mdds_wizard_done', '1', (string) get_edit_post_link( $unit_id, 'url' ) ) );
		exit;
	}

	/**
	 * Create chapter posts from the submitted repeater data.
	 *
	 * @param int    $unit_id Parent unit ID.
	 * @param string $status  Post status to use.
	 */
	private function create_chapters( int $unit_id, string $status ): void {
		if ( ! isset( $_POST['mdds_chapters'] ) || ! is_array( $_POST['mdds_chapters'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in create().
			return;
		}

		$raw   = wp_unslash( $_POST['mdds_chapters'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in create(); sanitised per field below.
		$order = 0;

		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$chapter_title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
			if ( '' === $chapter_title ) {
				continue;
			}

			$chapter_id = wp_insert_post(
				array(
					'post_type'   => Data::POST_TYPE_CHAPTER,
					'post_status' => $status,
					'post_title'  => $chapter_title,
					'post_parent' => $unit_id,
					'menu_order'  => $order,
				)
			);

			if ( is_wp_error( $chapter_id ) || 0 === $chapter_id ) {
				continue;
			}

			$chapter_id  = (int) $chapter_id;
			$description = isset( $row['description'] ) ? sanitize_textarea_field( (string) $row['description'] ) : '';
			if ( '' !== $description ) {
				update_post_meta( $chapter_id, Data::META_CHAPTER_DESC, $description );
			}

			$video_url = isset( $row['video_url'] ) ? esc_url_raw( (string) $row['video_url'] ) : '';
			if ( '' !== $video_url ) {
				update_post_meta( $chapter_id, Data::META_VIDEO_URL, $video_url );
			}

			++$order;
		}
	}

	/**
	 * Sanitise and persist the optional summary quiz from the wizard.
	 *
	 * @param int $unit_id Unit ID.
	 */
	private function save_quiz( int $unit_id ): void {
		$pass = isset( $_POST['mdds_quiz_pass'] ) ? absint( wp_unslash( $_POST['mdds_quiz_pass'] ) ) : 70; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in create().
		update_post_meta( $unit_id, Data::META_QUIZ_PASS, min( 100, $pass ) );

		if ( ! isset( $_POST['mdds_quiz'] ) || ! is_array( $_POST['mdds_quiz'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in create().
			return;
		}

		$raw       = wp_unslash( $_POST['mdds_quiz'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in create(); sanitised per field below.
		$questions = array();

		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? sanitize_textarea_field( (string) $row['question'] ) : '';

			$answers = array();
			if ( isset( $row['answers'] ) && is_array( $row['answers'] ) ) {
				foreach ( $row['answers'] as $answer ) {
					$answer = sanitize_text_field( (string) $answer );
					if ( '' !== $answer ) {
						$answers[] = $answer;
					}
				}
			}

			if ( '' === $question || count( $answers ) < 2 ) {
				continue;
			}

			$correct = isset( $row['correct'] ) ? absint( $row['correct'] ) : 0;
			if ( $correct > count( $answers ) - 1 ) {
				$correct = 0;
			}

			$questions[] = array(
				'question' => $question,
				'answers'  => array_values( $answers ),
				'correct'  => $correct,
			);
		}

		if ( ! empty( $questions ) ) {
			update_post_meta( $unit_id, Data::META_QUIZ_QUESTIONS, $questions );
			update_post_meta( $unit_id, Data::META_QUIZ_SHOW, 1 );
		}
	}

	/**
	 * Published products for the access-product selector.
	 *
	 * @return \WP_Post[]
	 */
	private function get_products(): array {
		return get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * URL of the wizard page.
	 *
	 * @return string
	 */
	private function wizard_url(): string {
		return add_query_arg(
			array(
				'post_type' => Data::POST_TYPE_UNIT,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}
}
