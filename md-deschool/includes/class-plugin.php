<?php
/**
 * Main plugin orchestrator.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool;

use MultiDigital\DeSchool\Post_Types\Unit_Post_Type;
use MultiDigital\DeSchool\Post_Types\Chapter_Post_Type;
use MultiDigital\DeSchool\Admin\Unit_Metaboxes;
use MultiDigital\DeSchool\Admin\Chapter_Metaboxes;
use MultiDigital\DeSchool\Admin\Admin_Assets;
use MultiDigital\DeSchool\Admin\Unit_Chapters_Box;
use MultiDigital\DeSchool\Admin\Answers_Export;
use MultiDigital\DeSchool\Admin\Wizard;
use MultiDigital\DeSchool\Frontend\Template_Loader;
use MultiDigital\DeSchool\Frontend\Assets;
use MultiDigital\DeSchool\Frontend\Ajax;
use MultiDigital\DeSchool\Frontend\Dashboard;
use MultiDigital\DeSchool\Frontend\Catalog;
use MultiDigital\DeSchool\Frontend\Account;
use MultiDigital\DeSchool\Frontend\QA;
use MultiDigital\DeSchool\Frontend\Course_View;
use MultiDigital\DeSchool\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that wires together every plugin component.
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Unit post type handler.
	 *
	 * @var Unit_Post_Type
	 */
	public Unit_Post_Type $unit_post_type;

	/**
	 * Chapter post type handler.
	 *
	 * @var Chapter_Post_Type
	 */
	public Chapter_Post_Type $chapter_post_type;

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Private constructor (singleton).
	 */
	private function __construct() {
		$this->unit_post_type    = new Unit_Post_Type();
		$this->chapter_post_type = new Chapter_Post_Type();
	}

	/**
	 * Register hooks for every component.
	 */
	private function boot(): void {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );

		// Admin.
		if ( is_admin() ) {
			( new Unit_Metaboxes() )->register();
			( new Chapter_Metaboxes() )->register();
			( new Admin_Assets() )->register();
			( new Unit_Chapters_Box() )->register();
			( new Answers_Export() )->register();
			( new Wizard() )->register();
		}

		// Front-end.
		( new Template_Loader() )->register();
		( new Assets() )->register();
		( new Ajax() )->register();
		( new Dashboard() )->register();
		( new Catalog() )->register();
		( new Account() )->register();
		( new QA() )->register();
		( new Course_View() )->register();

		// WooCommerce (only if active).
		if ( class_exists( 'WooCommerce' ) ) {
			( new Integration() )->register();
		}
	}

	/**
	 * Register custom post types. Public so it can run on activation too.
	 */
	public function register_post_types(): void {
		$this->unit_post_type->register();
		$this->chapter_post_type->register();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'md-deschool', false, dirname( MDDS_BASENAME ) . '/languages' );
	}

	/**
	 * Flush rewrite rules once after an update so new endpoints (e.g. /learn/)
	 * start working without a manual permalinks re-save, and make sure the
	 * personal-area and catalog pages exist.
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_option( 'mdds_rewrite_version' ) !== MDDS_VERSION ) {
			$this->ensure_pages();
			flush_rewrite_rules( false );
			update_option( 'mdds_rewrite_version', MDDS_VERSION );
		}
	}

	/**
	 * Create the personal-area and catalog pages if they don't exist yet.
	 */
	private function ensure_pages(): void {
		$this->ensure_page( 'mdds_account_page_id', __( 'אזור אישי', 'md-deschool' ), 'mdds-account', '[deschool_account]' );
		$this->ensure_page( 'mdds_courses_page_id', __( 'הקורסים שלנו', 'md-deschool' ), 'mdds-courses', '[deschool_courses]' );
	}

	/**
	 * Ensure a single shortcode page exists, storing its ID in an option.
	 *
	 * @param string $option  Option name holding the page ID.
	 * @param string $title   Page title.
	 * @param string $slug    Page slug.
	 * @param string $content Page content (shortcode).
	 */
	private function ensure_page( string $option, string $title, string $slug, string $content ): void {
		$existing = (int) get_option( $option, 0 );
		if ( $existing > 0 ) {
			$post = get_post( $existing );
			if ( $post instanceof \WP_Post && 'trash' !== $post->post_status ) {
				return;
			}
		}

		// Reuse an existing page with the same slug if present (avoid duplicates).
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'name'           => $slug,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $found ) ) {
			update_option( $option, (int) $found[0] );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( $option, (int) $page_id );
		}
	}
}
