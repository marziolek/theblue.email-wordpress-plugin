<?php
/**
 * Which theblue.email form a form on this site sends signups to.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * A target is an opaque name such as gf-12 (Gravity Forms feed 12) or cf7-34
 * (Contact Form 7 form 34). Pages carry the target, never the theblue.email
 * form key; the key is looked up here, on the server, from the integration's
 * own settings, so a visitor cannot ask for a challenge for any other form.
 */
final class Targets {

	/**
	 * Resolvers by prefix.
	 *
	 * @var array<string, callable(int): string>
	 */
	private static array $resolvers = array();

	/**
	 * Register a resolver.
	 *
	 * @param string                $prefix   gf or cf7.
	 * @param callable(int): string $resolver Takes the id, returns the form key or ''.
	 */
	public static function register( string $prefix, callable $resolver ): void {
		self::$resolvers[ $prefix ] = $resolver;
	}

	/**
	 * The form key for a target, or ''.
	 *
	 * @param string $target e.g. gf-12.
	 */
	public static function resolve( string $target ): string {
		if ( 1 !== preg_match( '/^([a-z0-9]+)-([1-9][0-9]{0,9})$/', $target, $m ) || ! isset( self::$resolvers[ $m[1] ] ) ) {
			return '';
		}

		$key = (string) call_user_func( self::$resolvers[ $m[1] ], (int) $m[2] );

		return 1 === preg_match( '/^[a-f0-9]{12}$/', $key ) ? $key : '';
	}

	/**
	 * Forget every resolver (tests).
	 */
	public static function reset(): void {
		self::$resolvers = array();
	}
}
