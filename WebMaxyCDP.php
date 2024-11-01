<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://www.webmaxy.co/performance
 * @since             1.0.0
 * @package           WebMaxyCDP
 *
 * @wordpress-plugin
 * Plugin Name:       WebMaxyCDP
 * Plugin URI:        https://www.webmaxy.co/performance
 * Description:       WebMaxyCDP Wordpress plugins allows you to integrate WebMaxyCDP with digit marketing in your website seemlessly.
 * Version:           1.0.0
 * Author:            WebMaxy
 * Author URI:        https://www.webmaxy.co
 * License:           GPL-2.0+
 * Text Domain:       WebMaxyCDP
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WebMaxyCDP_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-webmaxycdp-activator.php
 */
function activate_WebMaxyCDP() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-webmaxycdp-activator.php';
	WebMaxyCDP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-webmaxycdp-deactivator.php
 */
function deactivate_WebMaxyCDP() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-webmaxycdp-deactivator.php';
	WebMaxyCDP_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_WebMaxyCDP' );
register_deactivation_hook( __FILE__, 'deactivate_WebMaxyCDP' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-webmaxycdp.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_WebMaxyCDP() {

	$plugin = new WebMaxyCDP();
	$plugin->run();

}
run_WebMaxyCDP();
