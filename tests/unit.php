<?php
/**
 * Unit tests of the plugin, on the WordPress stand-in (php tests/unit.php). Exits 1 on the first failure.
 */
putenv( 'WP_STATE=/tmp/wp-stub-unit.json' );
@unlink( '/tmp/wp-stub-unit.json' );
require __DIR__ . '/harness/wp-stub.php';
require __DIR__ . '/../perimetry-cloud/perimetry-cloud.php';

$fails = 0;
function ok( $cond, $what ) { global $fails; echo ( $cond ? '  ✓ ' : '  ✗ ' ) . $what . "\n"; if ( ! $cond ) { $fails++; } }

// The report: what WordPress shows, nothing personal.
$inv = Perimetry_Cloud_Inventory::collect();
ok( '6.8.1' === $inv['core']['version'] && 3 === count( $inv['plugins'] ) && 5 === $inv['settings']['admins'], 'report: versions, plugins, number of administrators' );
ok( false === strpos( json_encode( $inv ), '@' ), 'report: no e-mail address' );
ok( 'hello-dolly' !== $inv['plugins'][2]['slug'] && 'hello' === $inv['plugins'][2]['slug'], 'report: a single-file plugin is named by its file' );

// The points to fix, the most serious first.
$inv['settings']['registration'] = true;
$inv['settings']['defaultRole']  = 'administrator';
$inv['core']['update']           = '7.1.4';
$inv['php']                      = '8.1.2';
$sev = array_map( function ( $i ) { return $i[0]; }, Perimetry_Cloud_Inventory::issues( $inv ) );
ok( 'critical' === $sev[0], 'issues: anyone becoming administrator comes first' );
$rank = array_map( function ( $s ) { return array_search( $s, array( 'critical', 'high', 'medium', 'low' ), true ); }, $sev );
$sorted = $rank;
sort( $sorted );
ok( $rank === $sorted && in_array( 'high', $sev, true ), 'issues: sorted by severity (' . implode( ',', $sev ) . ')' );
$inv['php'] = '8.2.0';
ok( ! in_array( 'PHP no longer receives security fixes', array_map( function ( $i ) { return $i[1]; }, Perimetry_Cloud_Inventory::issues( $inv ) ), true ), 'issues: PHP 8.2 is still supported' );

// Nothing is sent without consent.
ok( is_wp_error( Perimetry_Cloud_Inventory::send() ), 'send: refused when not connected' );
update_option( Perimetry_Cloud_API::KEY_OPTION, 'pc_test' );
ok( is_wp_error( Perimetry_Cloud_Inventory::send() ), 'send: refused without consent' );

exit( $fails ? 1 : 0 );
