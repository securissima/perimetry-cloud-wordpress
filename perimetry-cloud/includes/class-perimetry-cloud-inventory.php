<?php
/**
 * What WordPress shows about itself: versions, updates waiting, a few settings, how many administrators (never who).
 * Read locally for the plugin's page; sent to Perimetry Cloud only once a day, and only when an administrator agreed.
 *
 * @package PerimetryCloud
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the report, works out the points to fix, and sends it when allowed.
 */
class Perimetry_Cloud_Inventory {

	const CRON          = 'perimetry_cloud_daily';
	const SHARE_OPTION  = 'perimetry_cloud_share';
	const SENT_OPTION   = 'perimetry_cloud_sent';
	const PHP_SUPPORTED = '8.2'; // Oldest PHP branch still receiving security fixes (php.net, November 2026).

	/**
	 * The report, in the shape Perimetry Cloud expects.
	 *
	 * @return array
	 */
	public static function collect() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$core_update = null;
		$core        = get_site_transient( 'update_core' );
		if ( is_object( $core ) && ! empty( $core->updates ) ) {
			foreach ( $core->updates as $u ) {
				if ( isset( $u->response, $u->current ) && 'upgrade' === $u->response ) {
					$core_update = (string) $u->current;
					break;
				}
			}
		}
		$auto = 'minor';
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			$auto = true === WP_AUTO_UPDATE_CORE ? 'all' : ( false === WP_AUTO_UPDATE_CORE ? 'none' : 'minor' );
		}

		$plugin_updates = get_site_transient( 'update_plugins' );
		$auto_plugins   = (array) get_site_option( 'auto_update_plugins', array() );
		$plugins        = array();
		foreach ( get_plugins() as $file => $p ) {
			$slug      = false !== strpos( $file, '/' ) ? dirname( $file ) : basename( $file, '.php' );
			$plugins[] = array(
				'slug'    => $slug,
				'name'    => $p['Name'],
				'version' => $p['Version'],
				'update'  => isset( $plugin_updates->response[ $file ]->new_version ) ? (string) $plugin_updates->response[ $file ]->new_version : null,
				'active'  => is_plugin_active( $file ),
				'auto'    => in_array( $file, $auto_plugins, true ),
			);
		}

		$theme_updates = get_site_transient( 'update_themes' );
		$auto_themes   = (array) get_site_option( 'auto_update_themes', array() );
		$current       = get_stylesheet();
		$themes        = array();
		foreach ( wp_get_themes() as $slug => $t ) {
			$themes[] = array(
				'slug'    => $slug,
				'name'    => $t->get( 'Name' ),
				'version' => $t->get( 'Version' ),
				'update'  => isset( $theme_updates->response[ $slug ]['new_version'] ) ? (string) $theme_updates->response[ $slug ]['new_version'] : null,
				'active'  => $slug === $current,
				'auto'    => in_array( $slug, $auto_themes, true ),
			);
		}

		$users = count_users();
		return array(
			'core'     => array(
				'version' => get_bloginfo( 'version' ),
				'update'  => $core_update,
				'auto'    => $auto,
			),
			'php'      => PHP_VERSION,
			'plugins'  => $plugins,
			'themes'   => $themes,
			'settings' => array(
				'debugDisplay' => defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ),
				'fileEdit'     => ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) && ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ),
				// The filter is WordPress's own (xmlrpc_enabled), read here, never changed.
				'xmlrpc'       => (bool) apply_filters( 'xmlrpc_enabled', true ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'registration' => (bool) get_option( 'users_can_register' ),
				'defaultRole'  => (string) get_option( 'default_role' ),
				'adminSsl'     => force_ssl_admin(),
				'admins'       => isset( $users['avail_roles']['administrator'] ) ? (int) $users['avail_roles']['administrator'] : 0,
				'multisite'    => is_multisite(),
			),
			'plugin'   => PERIMETRY_CLOUD_VERSION,
		);
	}

	/**
	 * The points to fix in a report, the most serious first. Same rules as Perimetry Cloud's.
	 *
	 * @param array $inv A report from collect().
	 * @return array[] Each: id, severity, label, details (strings).
	 */
	public static function issues( $inv ) {
		$out = array();
		$s   = $inv['settings'];
		if ( $s['registration'] && 'administrator' === $s['defaultRole'] ) {
			$out[] = array( 'critical', __( 'Anyone can register, and new accounts become administrators', 'perimetry-cloud' ), array() );
		}
		if ( $inv['core']['update'] ) {
			$out[] = array( 'high', __( 'A WordPress update is available', 'perimetry-cloud' ), array( $inv['core']['version'] . ' → ' . $inv['core']['update'] ) );
		}
		$pu = array_filter( $inv['plugins'], static function ( $p ) {
			return null !== $p['update'];
		} );
		if ( $pu ) {
			$active = (bool) array_filter( $pu, static function ( $p ) {
				return $p['active'];
			} );
			$out[]  = array(
				$active ? 'high' : 'medium',
				__( 'Plugins have updates waiting', 'perimetry-cloud' ),
				array_map( static function ( $p ) {
					return $p['name'] . ' ' . $p['version'] . ' → ' . $p['update'];
				}, array_values( $pu ) ),
			);
		}
		$tu = array_filter( $inv['themes'], static function ( $t ) {
			return null !== $t['update'];
		} );
		if ( $tu ) {
			$out[] = array(
				'medium',
				__( 'Themes have updates waiting', 'perimetry-cloud' ),
				array_map( static function ( $t ) {
					return $t['name'] . ' ' . $t['version'] . ' → ' . $t['update'];
				}, array_values( $tu ) ),
			);
		}
		if ( version_compare( preg_replace( '/^(\d+\.\d+).*$/', '$1', $inv['php'] ), self::PHP_SUPPORTED, '<' ) ) {
			$out[] = array( 'high', __( 'PHP no longer receives security fixes', 'perimetry-cloud' ), array( $inv['php'] ) );
		}
		if ( $s['debugDisplay'] ) {
			$out[] = array( 'medium', __( 'Errors are shown to visitors (WP_DEBUG_DISPLAY)', 'perimetry-cloud' ), array() );
		}
		if ( $s['fileEdit'] ) {
			$out[] = array( 'low', __( 'Theme and plugin files can be edited from the admin (DISALLOW_FILE_EDIT)', 'perimetry-cloud' ), array() );
		}
		if ( $s['xmlrpc'] ) {
			$out[] = array( 'low', __( 'XML-RPC is on', 'perimetry-cloud' ), array() );
		}
		if ( $s['admins'] > 3 ) {
			/* translators: %d: number of administrator accounts. */
			$out[] = array( 'low', sprintf( _n( '%d administrator account', '%d administrator accounts', $s['admins'], 'perimetry-cloud' ), $s['admins'] ), array() );
		}
		$inactive = count( array_filter( $inv['plugins'], static function ( $p ) {
			return ! $p['active'];
		} ) );
		if ( $inactive ) {
			/* translators: %d: number of inactive plugins. */
			$out[] = array( 'low', sprintf( _n( '%d inactive plugin is still installed', '%d inactive plugins are still installed', $inactive, 'perimetry-cloud' ), $inactive ), array() );
		}
		$rank = array(
			'critical' => 0,
			'high'     => 1,
			'medium'   => 2,
			'low'      => 3,
		);
		usort( $out, static function ( $a, $b ) use ( $rank ) {
			return $rank[ $a[0] ] - $rank[ $b[0] ];
		} );
		return $out;
	}

	/**
	 * Sends the report now (connected sites, with consent only).
	 *
	 * @return true|WP_Error
	 */
	public static function send() {
		if ( ! Perimetry_Cloud_API::connected() || ! get_option( self::SHARE_OPTION ) ) {
			return new WP_Error( 'perimetry_not_allowed', __( 'Sharing is off.', 'perimetry-cloud' ) );
		}
		$r = Perimetry_Cloud_API::request( 'POST', 'inventory', self::collect(), true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		update_option( self::SENT_OPTION, time(), false );
		return true;
	}

	/**
	 * The daily send (WP-Cron).
	 */
	public static function send_scheduled() {
		self::send();
	}

	/**
	 * Daily sending on (consent given) or off.
	 *
	 * @param bool $on Whether to send.
	 */
	public static function schedule( $on ) {
		if ( $on && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		} elseif ( ! $on ) {
			self::unschedule();
		}
	}

	/**
	 * No more daily sending.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
	}
}
