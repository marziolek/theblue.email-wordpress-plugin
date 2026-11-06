<?php
/**
 * Fails when the plugin header, TBEF_VERSION, the readme's Stable tag and the
 * block's version disagree. WordPress.org ships what the Stable tag names, so
 * a mismatch publishes the wrong code.
 *
 * Usage: php bin/check-versions.php [expected-version]
 *
 * @package TheBlueEmail\Forms
 */

// phpcs:disable

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/theblue-email-forms.php' );

preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $header );
preg_match( "/define\(\s*'TBEF_VERSION',\s*'([^']+)'/", $main, $constant );
preg_match( '/^Stable tag:\s*(\S+)/mi', (string) file_get_contents( $root . '/readme.txt' ), $stable );
$block = json_decode( (string) file_get_contents( $root . '/blocks/form/block.json' ), true );

$found = array(
	'plugin header' => $header[1] ?? '',
	'TBEF_VERSION'  => $constant[1] ?? '',
	'readme Stable tag' => $stable[1] ?? '',
	'block.json version' => (string) ( $block['version'] ?? '' ),
);

$expected = $argv[1] ?? $found['plugin header'];
$expected = ltrim( (string) $expected, 'v' );
$failed   = false;

foreach ( $found as $where => $version ) {
	$ok     = $version === $expected;
	$failed = $failed || ! $ok;
	printf( "%s %-20s %s\n", $ok ? 'ok  ' : 'FAIL', $where, '' === $version ? '(missing)' : $version );
}

exit( $failed ? 1 : 0 );
