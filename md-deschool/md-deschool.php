<?php
/**
 * Plugin Name:       DeSchool
 * Plugin URI:        https://multidigital.co.il/
 * Description:        יחידות תוכן לימודיות (וידאו, מצגת, משימות, מבחן סיכום וייעוץ אישי) עם בקרת גישה מבוססת WooCommerce. פותח לפי תקן Multi Digital.
 * Version:           1.11.0
 * Requires at least: 6.4
 * Requires PHP:      8.3
 * Author:            Multi Digital
 * Author URI:        https://multidigital.co.il/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       md-deschool
 * Domain Path:       /languages
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool;

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin constants.
 */
define( 'MDDS_VERSION', '1.11.0' );
define( 'MDDS_FILE', __FILE__ );
define( 'MDDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MDDS_URL', plugin_dir_url( __FILE__ ) );
define( 'MDDS_BASENAME', plugin_basename( __FILE__ ) );

require_once MDDS_PATH . 'includes/class-autoloader.php';

Autoloader::register();

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return Plugin
 */
function mdds(): Plugin {
	return Plugin::instance();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\mdds', 5 );

/**
 * Activation: register post types so rewrite rules can be flushed.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		require_once MDDS_PATH . 'includes/class-autoloader.php';
		Autoloader::register();
		Plugin::instance()->register_post_types();
		flush_rewrite_rules();
	}
);

/**
 * Deactivation: clean up rewrite rules.
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
