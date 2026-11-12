<?php
/**
 * A stand-in for the parts of WordPress the plugin uses, for the end-to-end tests (no WordPress in the test machine).
 * Options and transients live in a JSON file; HTTP goes through curl; escaping is real enough to catch raw output.
 * Never shipped with the plugin.
 */

define( 'ABSPATH', __DIR__ . '/root/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PERIMETRY_CLOUD_API', getenv( 'PERIMETRY_API' ) ?: 'http://127.0.0.1:3000' );
define( 'WP_STUB_HOME', getenv( 'WP_HOME' ) ?: 'https://acme-wp.com' );
define( 'WP_STUB_STATE', getenv( 'WP_STATE' ) ?: '/tmp/wp-stub-state.json' );
if ( getenv( 'WP_DEBUG_ON' ) ) {
	define( 'WP_DEBUG', true );
}

$GLOBALS['wp_stub'] = file_exists( WP_STUB_STATE ) ? json_decode( file_get_contents( WP_STUB_STATE ), true ) : array();
$GLOBALS['wp_stub'] += array( 'options' => array(), 'transients' => array(), 'cron' => array() );
function wp_stub_save() { file_put_contents( WP_STUB_STATE, json_encode( $GLOBALS['wp_stub'] ) ); }

class WP_Error {
	private $code; private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

$GLOBALS['wp_hooks'] = array();
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['wp_hooks'][ $h ][] = $cb; return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb ); }
function do_action( $h, ...$a ) { foreach ( $GLOBALS['wp_hooks'][ $h ] ?? array() as $cb ) { call_user_func_array( $cb, $a ); } }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['wp_hooks'][ $h ] ?? array() as $cb ) { $v = call_user_func_array( $cb, array_merge( array( $v ), $a ) ); } return $v; }
function register_deactivation_hook( $f, $cb ) {}

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['wp_stub']['options'] ) ? $GLOBALS['wp_stub']['options'][ $k ] : ( 'date_format' === $k ? 'Y-m-d' : ( 'time_format' === $k ? 'H:i' : ( 'default_role' === $k ? 'subscriber' : $d ) ) ); }
function update_option( $k, $v, $a = null ) { $GLOBALS['wp_stub']['options'][ $k ] = $v; wp_stub_save(); return true; }
function delete_option( $k ) { unset( $GLOBALS['wp_stub']['options'][ $k ] ); wp_stub_save(); return true; }
function get_site_option( $k, $d = false ) { return get_option( $k, $d ); }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['wp_stub']['transients'][ $k ] = array( $v, $e ? time() + $e : 0 ); wp_stub_save(); return true; }
function get_transient( $k ) { $t = $GLOBALS['wp_stub']['transients'][ $k ] ?? null; return $t && ( ! $t[1] || $t[1] > time() ) ? $t[0] : false; }
function delete_transient( $k ) { unset( $GLOBALS['wp_stub']['transients'][ $k ] ); wp_stub_save(); return true; }
function get_site_transient( $k ) { return $GLOBALS['wp_stub']['site_transients'][ $k ] ?? false; }
function wp_next_scheduled( $h ) { return $GLOBALS['wp_stub']['cron'][ $h ] ?? false; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['wp_stub']['cron'][ $h ] = $t; wp_stub_save(); return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['wp_stub']['cron'][ $h ] ); wp_stub_save(); return 1; }

function home_url( $p = '' ) { return WP_STUB_HOME . '/' . ltrim( $p, '/' ); }
function admin_url( $p = '' ) { return WP_STUB_HOME . '/wp-admin/' . ltrim( $p, '/' ); }
function plugins_url( $p, $f ) { return WP_STUB_HOME . '/wp-content/plugins/perimetry-cloud/' . $p; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_basename( $f ) { return 'perimetry-cloud/' . basename( $f ); }
function is_admin() { return true; }
function current_user_can( $c ) { return true; }
function get_current_user_id() { return 1; }
function get_user_locale() { return getenv( 'WP_LOCALE' ) ?: 'en_US'; }
function get_bloginfo( $k ) { return '6.8.1'; }
function get_stylesheet() { return 'twentytwentyfive'; }
function count_users() { return array( 'avail_roles' => array( 'administrator' => 5 ) ); }
function force_ssl_admin() { return true; }
function is_multisite() { return false; }
function get_plugins() { return array( 'perimetry-cloud/perimetry-cloud.php' => array( 'Name' => 'Perimetry Cloud', 'Version' => '1.0.0' ), 'akismet/akismet.php' => array( 'Name' => 'Akismet', 'Version' => '5.3' ), 'hello.php' => array( 'Name' => 'Hello Dolly', 'Version' => '1.7.2' ) ); }
function is_plugin_active( $f ) { return 'hello.php' !== $f; }
class WP_Stub_Theme { private $d; public function __construct( $d ) { $this->d = $d; } public function get( $k ) { return $this->d[ $k ]; } }
function wp_get_themes() { return array( 'twentytwentyfive' => new WP_Stub_Theme( array( 'Name' => 'Twenty Twenty-Five', 'Version' => '1.2' ) ) ); }

function __( $t, $d = null ) { return $t; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return esc_html( $t ); }
function esc_url( $t ) { return esc_html( $t ); }
function esc_html__( $t, $d = null ) { return esc_html( $t ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function checked( $a, $b = true, $echo = true ) { $r = $a === $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function wp_generate_password( $n = 12, $s = true ) { return substr( bin2hex( random_bytes( $n ) ), 0, $n ); }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', array_map( function ( $k, $v ) { return $k . '=' . $v; }, array_keys( $args ), $args ) ); }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="stub-' . esc_attr( $a ) . '">'; }
function check_admin_referer( $a ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== 'stub-' . $a ) { http_response_code( 403 ); exit( 'bad nonce' ); } }
function wp_die( $m, $c = 500 ) { http_response_code( $c ); exit( $m ); }
function wp_safe_redirect( $to ) { if ( 0 !== strpos( $to, WP_STUB_HOME ) ) { http_response_code( 500 ); exit( 'unsafe redirect' ); } header( 'Location: ' . $to, true, 302 ); }
function wp_redirect( $to ) { header( 'Location: ' . $to, true, 302 ); }
function status_header( $c ) { http_response_code( $c ); }
function nocache_headers() { header( 'Cache-Control: no-cache, must-revalidate, max-age=0' ); }
function load_plugin_textdomain() { return true; }
function add_options_page( $a, $b, $c, $slug, $cb ) { $GLOBALS['wp_stub_page'] = $cb; return 'settings_page_' . $slug; }
function wp_add_dashboard_widget( $id, $title, $cb ) { $GLOBALS['wp_stub_widget'] = $cb; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}

function wp_remote_request( $url, $args ) {
	$ch = curl_init( $url );
	$h  = array();
	foreach ( $args['headers'] ?? array() as $k => $v ) { $h[] = "$k: $v"; }
	curl_setopt_array( $ch, array( CURLOPT_CUSTOMREQUEST => $args['method'] ?? 'GET', CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $args['timeout'] ?? 20 ) );
	if ( isset( $args['body'] ) ) { curl_setopt( $ch, CURLOPT_POSTFIELDS, $args['body'] ); }
	$body = curl_exec( $ch );
	if ( false === $body ) { return new WP_Error( 'http', curl_error( $ch ) ); }
	return array( 'code' => curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 'body' => $body );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
