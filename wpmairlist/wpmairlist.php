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
		write_log("WPMairlist: Creating / Updating database.");
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmairlist';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
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
	write_log('WPMairlist: Uninstalling plugin... Removing DB.');
	global $wpdb;
	$table_name = $wpdb->prefix . 'wpmairlist';
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	delete_option("wpmairlist_db_version");
}


function show_mairlist_current($attr){
	//mairlistcurrent
	$attr = shortcode_atts( array(
		'format' => 'all',
	), $attr, 'mairlistcurrent');

	// Get last song from database
	global $wpdb;
	$table_name = $wpdb->prefix . 'wpmairlist';

	$last = $wpdb->get_row("
		SELECT artist, title, time
		FROM $table_name
		ORDER BY id DESC
		LIMIT 1");

	if(!$last){
		return;
	}else{
		switch($attr['format']){
			case 'artist':
				return esc_html($last->artist);
			case 'title':
				return esc_html($last->title);
			case 'time':
				return esc_html($last->time);
			default:
				return esc_html($last->artist) . ' - ' . esc_html($last->title);
		}
	}
}

/// THIS IS THE API PART
/// Works like this:
/// https://.../mairlist/api -> https:/.../index.php?__mairlistapi=1 (wpmairlist_add_endpoint)
/// For all other values it is set to 0 (wpmairlist_add_query_var)
/// If a request contains __marlistapi = 1 (== wpmairlist_add_endpoint)
/// then we log the $_POST parameters and save the data to database (== handle_api_request)
function wpmairlist_add_endpoint(){
	add_rewrite_rule('^mairlist/api','index.php?__mairlistapi=1','top');
	flush_rewrite_rules();
}

function wpmairlist_add_query_var($vars){
	$vars[] = '__mairlistapi';
	return $vars;
}

function wpmairlist_sniff_requests(){
	global $wp;
	if(isset($wp->query_vars['__mairlistapi'])){
		write_log('WPMairlist: Mailirst API request');
		handle_api_request();
		exit;
	}
}

function handle_api_request(){

	$settings = get_option('wpmairlist_settings');
	if(get_option('wpmairlist_settings') !== FALSE && !empty($settings['wpmairlist_auth_password'])){
		if(!isset($_POST['auth_password']) || $settings['wpmairlist_auth_password'] !== $_POST['auth_password']){
			write_log('WPMairlist: Incorrect auth_password --> Aborting!');
			return;
		}
	}

	if(isset($_POST['artist'], $_POST['title'])){
		insert_artist_title($_POST['artist'], $_POST['title']);
	}else{
		write_log('WPMairlist: Missing Parameters in API Request');
	}
}

function insert_artist_title($artist, $title){
	write_log('WPMairlist: Insert data into playlist - ' . $artist . ' - ' . $title);
	global $wpdb;
	$table_name = $wpdb->prefix . 'wpmairlist';
	$wpdb->insert(
		$table_name,
		array(
			'artist' => $artist,
			'title' => $title,
			'time' => current_time('mysql', 1)
		)
	);
}


///////////// SETTINGS PAGE

function wpmairlist_add_admin_menu(  ) {
	add_submenu_page( 'options-general.php', 'WPMairlist', 'WPMairlist', 'manage_options', 'wpmairlist', 'wpmairlist_options_page' );
}


function wpmairlist_settings_init(  ) {
	register_setting( 'pluginPage', 'wpmairlist_settings' );
	add_settings_section(
		'wpmairlist_pluginPage_section',
		__( 'General', 'wpmairlist' ),
		'wpmairlist_settings_section_callback',
		'pluginPage'
	);

	add_settings_field(
		'wpmairlist_auth_password',
		__( 'Authentication Password', 'wpmairlist' ),
		'wpmairlist_auth_password_render',
		'pluginPage',
		'wpmairlist_pluginPage_section'
	);


}


function wpmairlist_auth_password_render(  ) {

	$options = get_option( 'wpmairlist_settings' );
	?>
	<input type='password' name='wpmairlist_settings[wpmairlist_auth_password]' value='<?php echo $options['wpmairlist_auth_password']; ?>'>
	<?php

}


function wpmairlist_settings_section_callback(  ) {

	echo __( 'Please set your authentication password here. It should be randomly generated and not easily to guess. <br /> Please use at least 10 characters. Remember to set the password in mairlist as well, otherwise the playlist data cannot be transmitted. <br /><strong>If you do not set a password, everyone can submit data to your playlist!</strong>', 'wpmairlist' );

}


function wpmairlist_options_page(  ) {

	?>
	<form action='options.php' method='post'>

		<h2>WPMairlist</h2>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		submit_button();
		?>

	</form>
	<?php

}


/////// END SETTINGS


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

// For endpoint
add_action('init', 'wpmairlist_add_endpoint');
add_filter('query_vars', 'wpmairlist_add_query_var');
add_action('parse_request', 'wpmairlist_sniff_requests');


// For settings
add_action( 'admin_menu', 'wpmairlist_add_admin_menu' );
add_action( 'admin_init', 'wpmairlist_settings_init' );

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
