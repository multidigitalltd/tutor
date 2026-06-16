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
use MultiDigital\DeSchool\Admin\Demo_Seeder;
use MultiDigital\DeSchool\Frontend\Template_Loader;
use MultiDigital\DeSchool\Frontend\Assets;
use MultiDigital\DeSchool\Frontend\Ajax;
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

		// Admin.
		if ( is_admin() ) {
			( new Unit_Metaboxes() )->register();
			( new Chapter_Metaboxes() )->register();
			( new Admin_Assets() )->register();
			( new Demo_Seeder() )->register();
		}

		// Front-end.
		( new Template_Loader() )->register();
		( new Assets() )->register();
		( new Ajax() )->register();

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
}
