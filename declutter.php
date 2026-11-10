<?php
/**
 * Plugin Name:       Declutter
 * Description:       Clean up the WordPress admin: hide dashboard widgets, toolbar items and menu entries.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            webmaster.sh
 * Author URI:        https://webmaster.sh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       declutter
 * Domain Path:       /languages
 *
 * Declutter your WordPress admin.
 *
 * @package Declutter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DECLUTTER_VERSION', '1.0.2' );
define( 'DECLUTTER_FILE', __FILE__ );
define( 'DECLUTTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'DECLUTTER_URL', plugin_dir_url( __FILE__ ) );

require_once DECLUTTER_DIR . 'includes/class-declutter.php';
require_once DECLUTTER_DIR . 'includes/class-declutter-admin.php';

register_activation_hook( __FILE__, array( 'Declutter', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'declutter', false, dirname( plugin_basename( DECLUTTER_FILE ) ) . '/languages' );
		Declutter::instance();
		if ( is_admin() ) {
			Declutter_Admin::instance();
		}
	}
);
