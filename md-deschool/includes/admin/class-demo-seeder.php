<?php
/**
 * One-click demo unit seeder.
 *
 * Creates the example "six questions before a campaign" unit described in the
 * project brief, so the template can be reviewed with real content.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Admin;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an admin tool that seeds a complete sample unit.
 */
final class Demo_Seeder {

	private const ACTION       = 'mdds_create_demo';
	private const OPTION_FLAG  = 'mdds_demo_unit_id';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Register the tool sub-page under the DeSchool menu.
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Data::POST_TYPE_UNIT,
			__( 'יחידת דוגמה', 'md-deschool' ),
			__( 'יחידת דוגמה', 'md-deschool' ),
			'manage_options',
			'mdds-demo',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the tool page.
	 */
	public function render_page(): void {
		$existing = (int) get_option( self::OPTION_FLAG, 0 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'יצירת יחידת תוכן לדוגמה', 'md-deschool' ); ?></h1>
			<?php if ( $existing > 0 && get_post_status( $existing ) ) : ?>
				<p>
					<?php esc_html_e( 'יחידת הדוגמה כבר נוצרה:', 'md-deschool' ); ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $existing ) ); ?>"><?php echo esc_html( get_the_title( $existing ) ); ?></a>
				</p>
			<?php endif; ?>
			<p><?php esc_html_e( 'פעולה זו תיצור יחידת תוכן מלאה ("שש השאלות לפני קמפיין") עם שני פרקים, משימות ומבחן סיכום, לבדיקת התבנית.', 'md-deschool' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php submit_button( __( 'יצירת יחידת דוגמה', 'md-deschool' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the seeding request.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'md-deschool' ) );
		}
		check_admin_referer( self::ACTION );

		$unit_id = $this->create_unit();
		$this->create_chapters( $unit_id );

		update_option( self::OPTION_FLAG, $unit_id, false );

		wp_safe_redirect( (string) get_edit_post_link( $unit_id, 'redirect' ) );
		exit;
	}

	/**
	 * Create the demo unit with all meta.
	 *
	 * @return int Unit ID.
	 */
	private function create_unit(): int {
		$content = "לפני שיוצאים לקמפיין, לא מתחילים מהעיצוב, לא מהמודעה ולא מהשאלה איפה לפרסם. מתחילים מהגדרות בסיסיות: מה אנחנו מקדמים, למה שזה יעניין את הלקוח, למי אנחנו פונים, איך נכון להעביר את המסר, ומה המטרה העסקית של הקמפיין.\n\nביחידת התוכן הזו נלמד איך לעצור רגע לפני שמוציאים כסף על פרסום, ולבנות בסיס נכון לקמפיין ממוקד, ברור ואפקטיבי.";

		$unit_id = wp_insert_post(
			array(
				'post_type'    => Data::POST_TYPE_UNIT,
				'post_status'  => 'publish',
				'post_title'   => 'שש השאלות שחייבים לענות עליהן לפני שיוצאים לקמפיין',
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $unit_id ) ) {
			wp_die( esc_html( $unit_id->get_error_message() ) );
		}

		update_post_meta( $unit_id, Data::META_SHORT_DESC, 'יחידת תוכן מעשית לבעלי עסקים שרוצים לצאת לקמפיין בצורה חכמה ולא לבזבז כסף על פרסום לא מדויק.' );
		update_post_meta(
			$unit_id,
			Data::META_INCLUDES,
			"הגדרת ה\"מה\" — מה בדיוק מקדמים בקמפיין\nחידוד המסר המרכזי\nהבנת הלקוח וקהל היעד\nהגדרת הסיבה שהלקוח אמור להתעניין\nחשיבה על ערוצי הפרסום והדרך הנכונה להגיע לקהל\nהתאמת התקציב למטרה העסקית\nעבודה עצמית לבניית בסיס לקמפיין"
		);
		update_post_meta( $unit_id, Data::META_LECTURER_NAME, 'שם המרצה' );
		update_post_meta( $unit_id, Data::META_LECTURER_TITLE, 'מומחה לשיווק, פרסום ובניית קמפיינים לעסקים קטנים ובינוניים' );

		// Consultation.
		update_post_meta( $unit_id, Data::META_CONSULT_TITLE, 'רוצה ליישם את זה בעסק שלך?' );
		update_post_meta( $unit_id, Data::META_CONSULT_TEXT, 'צפית ביחידת התוכן ובנית בסיס ראשוני לקמפיין שלך. עכשיו אפשר לקבוע פגישת ייעוץ אישית עם המרצה, במחיר מסובסד, כדי לעבור על התשובות שלך, לדייק את המסר ולבנות כיוון מעשי להמשך.' );
		update_post_meta( $unit_id, Data::META_CONSULT_LABEL, 'קביעת פגישת ייעוץ מסובסדת' );
		update_post_meta( $unit_id, Data::META_CONSULT_URL, home_url( '/contact/' ) );

		// Quiz.
		update_post_meta( $unit_id, Data::META_QUIZ_TITLE, 'מבחן סיכום — שש השאלות לפני קמפיין' );
		update_post_meta( $unit_id, Data::META_QUIZ_PASS, 70 );
		update_post_meta( $unit_id, Data::META_QUIZ_SHOW, 1 );
		update_post_meta( $unit_id, Data::META_QUIZ_RETRY, 1 );
		update_post_meta( $unit_id, Data::META_QUIZ_QUESTIONS, $this->demo_quiz() );

		return (int) $unit_id;
	}

	/**
	 * Build the demo quiz questions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function demo_quiz(): array {
		return array(
			array(
				'question' => 'מה הדבר הראשון שצריך לברר לפני שיוצאים לקמפיין?',
				'answers'  => array( 'איפה הכי זול לפרסם', 'מה בדיוק אנחנו רוצים לקדם ומה המסר המרכזי', 'איזה צבע יהיה במודעה', 'כמה מתחרים יש בשוק' ),
				'correct'  => 1,
			),
			array(
				'question' => 'למה חשוב להגדיר את ה"מה" לפני תחילת הקמפיין?',
				'answers'  => array( 'כדי שהקמפיין יהיה ממוקד ולא יתפזר למסרים שונים', 'כדי לחסוך עבודה לגרפיקאי', 'כדי שלא יהיה צורך בתקציב', 'כדי לבחור מהר יותר את חברת הפרסום' ),
				'correct'  => 0,
			),
			array(
				'question' => 'מה עלול לקרות כאשר הקמפיין יוצא בלי מסר ברור?',
				'answers'  => array( 'הקמפיין בהכרח יצליח יותר', 'הלקוחות יבינו לבד מה רצינו לומר', 'הכסף עלול להתבזבז על פרסום לא ממוקד', 'אין לזה משמעות אם העיצוב יפה' ),
				'correct'  => 2,
			),
			array(
				'question' => 'מהי שאלה טובה שבעל עסק צריך לשאול את עצמו לפני פרסום?',
				'answers'  => array( 'איזה פונט הכי יוקרתי?', 'מה הלקוח אמור להבין תוך כמה שניות?', 'כמה מודעות אפשר להכניס בעמוד אחד?', 'האם המתחרה שלי פרסם השבוע?' ),
				'correct'  => 1,
			),
			array(
				'question' => 'מה עדיף בפרק עבודה לאחר צפייה בתוכן?',
				'answers'  => array( 'להישאר ברמת השראה כללית בלבד', 'לכתוב תשובות מעשיות על העסק הספציפי של המשתמש', 'לסכם את כל מה שהמרצה אמר מילה במילה', 'לדלג ישר לפרק הבא' ),
				'correct'  => 1,
			),
		);
	}

	/**
	 * Create the two demo chapters with tasks.
	 *
	 * @param int $unit_id Unit ID.
	 */
	private function create_chapters( int $unit_id ): void {
		$chapters = array(
			array(
				'title' => 'לפני שמפרסמים — מה בדיוק אנחנו מוכרים?',
				'desc'  => 'בפרק הראשון נעסוק בשאלה הבסיסית ביותר לפני קמפיין: מה בדיוק אנחנו רוצים לקדם? נחדד את ההבדל בין מוצר, שירות, הצעה ומסר, ונבין למה קמפיין טוב חייב להתחיל בהגדרה ברורה של הדבר שאותו אנחנו רוצים שהלקוח יבין.',
				'tasks' => array(
					array( 'title' => 'משימה 1: מה אתה מקדם?', 'instruction' => 'כתוב במשפט אחד ברור מה בדיוק המוצר או השירות שאתה רוצה לקדם בקמפיין.', 'button_label' => '', 'allow_file' => true ),
					array( 'title' => 'משימה 2: מה הלקוח צריך להבין?', 'instruction' => 'כתוב מה הדבר המרכזי שהלקוח אמור להבין תוך 5 שניות כשהוא רואה את הפרסום שלך.', 'button_label' => '', 'allow_file' => true ),
					array( 'title' => 'משימה 3: איזו בעיה אתה פותר?', 'instruction' => 'כתוב איזו בעיה, צורך או כאב של הלקוח המוצר / השירות שלך פותר.', 'button_label' => '', 'allow_file' => true ),
					array( 'title' => 'משימה 4: ניסוח מסר ראשוני', 'instruction' => 'נסח טיוטה ראשונית למסר המרכזי של הקמפיין. אפשר לכתוב את הנוסח בתיבת הטקסט או לצרף קובץ עם טיוטת מודעה / מצגת / בריף.', 'button_label' => '', 'allow_file' => true ),
				),
			),
			array(
				'title' => 'מרכיב ה"מה" — חלק שני: להפוך מוצר למסר ברור',
				'desc'  => 'בפרק זה נמשיך לחדד את מרכיב ה"מה", ונראה איך לוקחים מוצר או שירות מורכב והופכים אותו למסר פשוט, ברור ומובן ללקוח.',
				'tasks' => array(
					array( 'title' => 'משימה 1: מה לא ברור במסר שלך?', 'instruction' => 'כתוב אילו חלקים במסר הנוכחי שלך עלולים להיות לא ברורים ללקוח שלא מכיר אותך.', 'button_label' => '', 'allow_file' => true ),
					array( 'title' => 'משימה 2: גרסה פשוטה יותר', 'instruction' => 'נסח את המסר שלך מחדש בשפה פשוטה יותר, כאילו אתה מסביר אותו ללקוח שלא מכיר את התחום.', 'button_label' => '', 'allow_file' => true ),
				),
			),
		);

		foreach ( $chapters as $order => $chapter ) {
			$chapter_id = wp_insert_post(
				array(
					'post_type'   => Data::POST_TYPE_CHAPTER,
					'post_status' => 'publish',
					'post_title'  => $chapter['title'],
					'post_parent' => $unit_id,
					'menu_order'  => $order,
				),
				true
			);

			if ( is_wp_error( $chapter_id ) ) {
				continue;
			}

			update_post_meta( $chapter_id, Data::META_CHAPTER_DESC, $chapter['desc'] );
			update_post_meta( $chapter_id, Data::META_TASKS, $chapter['tasks'] );
		}
	}
}
