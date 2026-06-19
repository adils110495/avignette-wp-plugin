<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://heservices.in
 * @since             1.0.0
 * @package           Woo_Vignette
 *
 * @wordpress-plugin
 * Plugin Name:       Woo Vignette
 * Plugin URI:        https://heservices.in/woo-vignette/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Hindustan Engineering Services
 * Author URI:        https://heservices.in/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-vignette
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
define('WOO_VIGNETTE_VERSION','1.0.0');
define('WOO_VIGNETTE_PATH',plugin_dir_path( __FILE__ ));
define('WOO_VIGNETTE_URI',plugin_dir_url( __FILE__ ));
/**
 * Check if WooCommerce is installed and active.
 * If not, show an admin notice and prevent plugin activation.
 *
 * @return bool
 */
function check_dependencies() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo __( 'The Woo Vignette plugin requires WooCommerce to be installed and active.', 'woo-vignette' );
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * The code that runs during plugin activation.
 */
function activate_woo_vignette() {
    // Check if WooCommerce is active before continuing with the activation
    if ( ! check_dependencies() ) {
        // WooCommerce is not active, prevent plugin activation
        wp_die( __( 'WooCommerce is required to run this plugin. Please install and activate WooCommerce first.', 'woo-vignette' ) );
    }

    // If WooCommerce is active, continue with the activation process
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-vignette-activator.php';
    Woo_Vignette_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_woo_vignette() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-vignette-deactivator.php';
    Woo_Vignette_Deactivator::deactivate();
}

// Register the activation hook to check dependencies
register_activation_hook( __FILE__, 'activate_woo_vignette' );

// Register the deactivation hook
register_deactivation_hook( __FILE__, 'deactivate_woo_vignette' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woo-vignette.php';

/**
 * Begins execution of the plugin.
 */
function run_woo_vignette() {
    // Only run the plugin if WooCommerce is active
    if ( ! check_dependencies() ) {
        return; // Stop execution if WooCommerce is not active
    }

    $plugin = new Woo_Vignette();
    $plugin->run();
}

run_woo_vignette();