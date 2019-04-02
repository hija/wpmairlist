<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              hilko.eu
 * @since             1.0.0
 * @package           WPMairlist
 *
 * @wordpress-plugin
 * Plugin Name:       WPMairlist
 * Plugin URI:        https://github.com/hija/wpmairlist
 * Description:       Plugin for logging and displaying playout information from mairlist in wordpress
 * Version:           1.0.0
 * Author:            Hilko Janßen
 * Author URI:        hilko.eu
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpmairlist
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpmairlist_db_version;
$wpmairlist_db_version = '1.0';

function wpmairlist_activation() {
	wpmairlist_install_table();
}

function wpmairlist_install_table() {
	global $wpmairlist_db_version;
	$installed_ver = get_option( "wpmairlist_db_version" );
	if ( $installed_ver != $wpmairlist_db_version ) {
		write_log("Creating / Updating database.");
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmairlist';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			artist text NOT NULL,
			title text NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	update_option( 'wpmairlist_db_version', $wpmairlist_db_version );
}

function wpmairlist_deactivation() {

}

function wpmairlist_uninstall() {
	write_log('Uninstalling plugin... Removing DB.');
	global $wpdb;
	$table_name = $wpdb->prefix . 'wpmairlist';
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	delete_option("wpmairlist_db_version");
}


function show_mairlist_current($attr){

}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WPMairlist_VERSION', '1.0.0' );

register_activation_hook(__FILE__, 'wpmairlist_activation');
register_deactivation_hook(__FILE__, 'wpmairlist_deactivation');
register_uninstall_hook(__FILE__, 'wpmairlist_uninstall');

add_shortcode('mairlistcurrent', 'show_mairlist_current');


if (!function_exists('write_log')) {
    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}
