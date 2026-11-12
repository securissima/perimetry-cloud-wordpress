<?php
/**
 * Plugin Name:       Perimetry Cloud – Security Score
 * Plugin URI:        https://perimetry.cloud/help/wordpress-plugin/
 * Description:       The security score of your site's domain, seen from the outside, in your dashboard: e-mail spoofing, certificates, exposed services, look-alike domains, and what WordPress itself shows. Free check, no account needed.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Perimetry Cloud
 * Author URI:        https://perimetry.cloud/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       perimetry-cloud
 * Domain Path:       /languages
 *
 * @package PerimetryCloud
 */

defined( 'ABSPATH' ) || exit;

define( 'PERIMETRY_CLOUD_VERSION', '1.0.0' );
define( 'PERIMETRY_CLOUD_FILE', __FILE__ );
define( 'PERIMETRY_CLOUD_DIR', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'PERIMETRY_CLOUD_API' ) ) {
	// The service the plugin talks to. Can be set in wp-config.php (tests, a staging copy of the service).
	define( 'PERIMETRY_CLOUD_API', 'https://app.perimetry.cloud' );
}

require_once PERIMETRY_CLOUD_DIR . 'includes/class-perimetry-cloud-api.php';
require_once PERIMETRY_CLOUD_DIR . 'includes/class-perimetry-cloud-inventory.php';
require_once PERIMETRY_CLOUD_DIR . 'includes/class-perimetry-cloud-proof.php';
require_once PERIMETRY_CLOUD_DIR . 'includes/class-perimetry-cloud-admin.php';

Perimetry_Cloud_Proof::init();
if ( is_admin() ) {
	Perimetry_Cloud_Admin::init();
}
add_action( Perimetry_Cloud_Inventory::CRON, array( 'Perimetry_Cloud_Inventory', 'send_scheduled' ) );
register_deactivation_hook( __FILE__, array( 'Perimetry_Cloud_Inventory', 'unschedule' ) );
