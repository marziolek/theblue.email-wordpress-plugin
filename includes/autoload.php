<?php
/**
 * Class loader: TheBlueEmail\Forms\Foo_Bar lives in includes/class-foo-bar.php,
 * TheBlueEmail\Forms\Integrations\Foo in includes/integrations/class-foo.php.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'TheBlueEmail\\Forms\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$name  = array_pop( $parts );
		$file  = 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		$dir   = '' === implode( '/', $parts ) ? '' : strtolower( implode( '/', $parts ) ) . '/';
		$path  = __DIR__ . '/' . $dir . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
