<?php
/**
 * Removing the plugin removes everything it kept. The key stays known to Perimetry Cloud until it is revoked there
 * (Disconnect, before removing, revokes it).
 *
 * @package PerimetryCloud
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'perimetry_cloud_key', 'perimetry_cloud_domain', 'perimetry_cloud_verify', 'perimetry_cloud_share', 'perimetry_cloud_sent', 'perimetry_cloud_check' ) as $perimetry_cloud_option ) {
	delete_option( $perimetry_cloud_option );
}
delete_transient( 'perimetry_cloud_summary' );
delete_transient( 'perimetry_cloud_proof' );
wp_clear_scheduled_hook( 'perimetry_cloud_daily' );
