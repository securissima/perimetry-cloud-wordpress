<?php
/**
 * PHP built-in server router for the end-to-end tests: the home address (proofs), the plugin's page, admin-post.php,
 * the dashboard, and the plugin's assets, inside a frame that looks like the WordPress admin (for the screenshots).
 *
 * php -S 127.0.0.1:8099 tests/harness/router.php
 */

require __DIR__ . '/wp-stub.php';
require __DIR__ . '/../../perimetry-cloud/perimetry-cloud.php';
do_action( 'init' );
do_action( 'admin_menu' );
do_action( 'wp_dashboard_setup' );

$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

if ( 0 === strpos( $path, '/wp-content/plugins/perimetry-cloud/' ) ) {
	$file = __DIR__ . '/../../perimetry-cloud/' . substr( $path, strlen( '/wp-content/plugins/perimetry-cloud/' ) );
	if ( is_file( $file ) && preg_match( '/\.(css|js)$/', $file, $m ) ) {
		header( 'Content-Type: ' . ( 'css' === $m[1] ? 'text/css' : 'text/javascript' ) );
		readfile( $file );
	} else {
		http_response_code( 404 );
	}
	return true;
}

if ( '/wp-admin/admin-post.php' === $path ) {
	do_action( 'admin_post_' . ( $_POST['action'] ?? '' ) );
	return true;
}

function wp_stub_chrome( $title, $active, $body ) {
	$menu = array( 'Dashboard' => 'index.php', 'Posts' => '#', 'Media' => '#', 'Pages' => '#', 'Comments' => '#', 'Appearance' => '#', 'Plugins' => '#', 'Users' => '#', 'Tools' => '#', 'Settings' => 'options-general.php?page=perimetry-cloud' );
	echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title>';
	echo '<link rel="stylesheet" href="/wp-content/plugins/perimetry-cloud/assets/admin.css">';
	echo '<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;font-size:13px;color:#1d2327;background:#f0f0f1}'
		. '#bar{height:32px;background:#1d2327;color:#c3c4c7;display:flex;align-items:center;padding:0 12px;gap:16px}#bar b{color:#fff}'
		. '#side{position:fixed;top:32px;bottom:0;left:0;width:160px;background:#1d2327}#side a{display:block;color:#f0f0f1;text-decoration:none;padding:8px 12px;font-size:14px}#side a.on{background:#2271b1}'
		. '#main{margin-left:160px;padding:10px 20px}h1{font-size:23px;font-weight:400}h2{font-size:1.3em}.wrap{margin:10px 20px 0 2px}'
		. '.button{display:inline-block;border:1px solid #2271b1;color:#2271b1;background:#f6f7f7;border-radius:3px;padding:0 10px;line-height:2.15;min-height:30px;font-size:13px;cursor:pointer;text-decoration:none}'
		. '.button-primary{background:#2271b1;color:#fff}.button-link-delete{border:0;background:none;color:#b32d2e;text-decoration:underline}.description{color:#646970}'
		. '.notice{background:#fff;border-left:4px solid #72aee6;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:1px 12px;margin:5px 0 15px}.notice-success{border-left-color:#00a32a}.notice-error{border-left-color:#d63638}'
		. '.postbox{background:#fff;border:1px solid #c3c4c7;width:420px;margin-top:12px}.postbox h2{font-size:14px;padding:8px 12px;margin:0;border-bottom:1px solid #c3c4c7}.inside{padding:0 12px 12px}</style>';
	echo '</head><body><div id="bar"><b>Acme WP</b><span>acme-wp.com</span></div><div id="side">';
	foreach ( $menu as $label => $href ) {
		echo '<a href="/wp-admin/' . esc_attr( $href ) . '"' . ( $label === $active ? ' class="on"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</div><div id="main">' . $body . '</div><script src="/wp-content/plugins/perimetry-cloud/assets/admin.js"></script></body></html>';
}

if ( '/wp-admin/options-general.php' === $path ) {
	do_action( 'load-settings_page_perimetry-cloud' );
	if ( headers_sent() || array_filter( headers_list(), function ( $h ) { return 0 === stripos( $h, 'Location:' ); } ) ) {
		return true;
	}
	ob_start();
	call_user_func( $GLOBALS['wp_stub_page'] );
	wp_stub_chrome( 'Perimetry Cloud', 'Settings', ob_get_clean() );
	return true;
}

if ( '/wp-admin/index.php' === $path || '/wp-admin/' === $path ) {
	ob_start();
	echo '<div class="wrap"><h1>Dashboard</h1><div class="postbox"><h2>Perimetry Cloud – Security score</h2><div class="inside">';
	call_user_func( $GLOBALS['wp_stub_widget'] );
	echo '</div></div></div>';
	wp_stub_chrome( 'Dashboard', 'Dashboard', ob_get_clean() );
	return true;
}

// The home address: the plugin answers its proofs here (parse_request), anything else is an empty front page.
do_action( 'parse_request' );
echo '<!doctype html><title>Acme WP</title><h1>Acme WP</h1>';
return true;
