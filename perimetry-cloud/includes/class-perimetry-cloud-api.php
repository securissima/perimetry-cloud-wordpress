<?php
/**
 * Calls to the Perimetry Cloud service. Nothing is sent before an administrator asks for it.
 *
 * @package PerimetryCloud
 */

defined( 'ABSPATH' ) || exit;

/**
 * The service's endpoints for this plugin (https://app.perimetry.cloud/api/wp/...).
 */
class Perimetry_Cloud_API {

	const KEY_OPTION    = 'perimetry_cloud_key';
	const DOMAIN_OPTION = 'perimetry_cloud_domain';

	/**
	 * The address of the site, as WordPress knows it.
	 *
	 * @return string
	 */
	public static function site() {
		return home_url( '/' );
	}

	/**
	 * The language of the answers: English, French or German, from the language of the admin.
	 *
	 * @return string
	 */
	public static function lang() {
		$l = substr( get_user_locale(), 0, 2 );
		return in_array( $l, array( 'fr', 'de' ), true ) ? $l : 'en';
	}

	/**
	 * Whether this site holds a key.
	 *
	 * @return bool
	 */
	public static function connected() {
		return '' !== (string) get_option( self::KEY_OPTION, '' );
	}

	/**
	 * One call to the service.
	 *
	 * @param string     $method GET or POST.
	 * @param string     $path   Path under /api/wp/.
	 * @param array|null $body   JSON body.
	 * @param bool       $auth   Whether to send the key.
	 * @return array|WP_Error The decoded answer, or an error with the service's message.
	 */
	public static function request( $method, $path, $body = null, $auth = false ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'PerimetryCloud-WordPress/' . PERIMETRY_CLOUD_VERSION . '; ' . home_url( '/' ),
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		if ( $auth ) {
			$args['headers']['Authorization'] = 'Bearer ' . get_option( self::KEY_OPTION, '' );
		}
		$res = wp_remote_request( untrailingslashit( PERIMETRY_CLOUD_API ) . '/api/wp/' . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'perimetry_unreachable', __( 'Perimetry Cloud could not be reached. Try again in a moment.', 'perimetry-cloud' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 || ! is_array( $data ) ) {
			if ( 401 === $code && $auth ) {
				// The key was revoked from Perimetry Cloud: forget it, the site shows the free check again.
				self::forget();
			}
			$message = is_array( $data ) && isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : __( 'Perimetry Cloud answered with an error. Try again later.', 'perimetry-cloud' );
			return new WP_Error( is_array( $data ) && isset( $data['error'] ) ? sanitize_key( $data['error'] ) : 'perimetry_error', $message );
		}
		return $data;
	}

	/**
	 * Forgets the connection and everything cached from it.
	 */
	public static function forget() {
		delete_option( self::KEY_OPTION );
		delete_option( self::DOMAIN_OPTION );
		delete_option( Perimetry_Cloud_Proof::VERIFY_OPTION );
		delete_transient( 'perimetry_cloud_summary' );
		Perimetry_Cloud_Inventory::unschedule();
	}
}
