<?php
/**
 * The proofs this site serves to Perimetry Cloud: that the free check was asked by this site, and that it controls its
 * domain. Each is one line of plain text at the home address, only while it is expected.
 *
 * @package PerimetryCloud
 */

defined( 'ABSPATH' ) || exit;

/**
 * Answers /?perimetry_proof=<token> and /?perimetry_verify=1.
 */
class Perimetry_Cloud_Proof {

	const PROOF_TRANSIENT = 'perimetry_cloud_proof';
	const VERIFY_OPTION   = 'perimetry_cloud_verify';

	/**
	 * Hooks the answer before anything else is sent.
	 */
	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'answer' ), 0 );
	}

	/**
	 * Serves a proof when one is asked and expected; does nothing otherwise.
	 */
	public static function answer() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read by Perimetry Cloud's servers, no form and no change.
		if ( isset( $_GET['perimetry_proof'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['perimetry_proof'] ) );
			$proof = get_transient( self::PROOF_TRANSIENT );
			if ( is_array( $proof ) && isset( $proof['token'], $proof['value'] ) && hash_equals( (string) $proof['token'], $token ) ) {
				self::send( 'perimetry-proof=' . $proof['value'] );
			}
			return;
		}
		if ( isset( $_GET['perimetry_verify'] ) ) {
			$token = (string) get_option( self::VERIFY_OPTION, '' );
			if ( '' !== $token ) {
				self::send( 'perimetry-verify=' . $token );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * One line of plain text, never cached nor indexed, then stop.
	 *
	 * @param string $line The proof.
	 */
	private static function send( $line ) {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $line );
		exit;
	}
}
