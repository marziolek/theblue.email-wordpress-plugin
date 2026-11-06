<?php
/**
 * Base test case: Brain\Monkey, Mockery, and options and transients kept in arrays.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base {

	use MockeryPHPUnitIntegration;

	/** @var array<string, mixed> */
	protected array $options = array();

	/** @var array<string, mixed> */
	protected array $transients = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options    = array();
		$this->transients = array();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'get_option' )->alias( fn( string $k, mixed $d = false ): mixed => $this->options[ $k ] ?? $d );
		Functions\when( 'update_option' )->alias(
			function ( string $k, mixed $v ): bool {
				$this->options[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $k ): bool {
				unset( $this->options[ $k ] );
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $k ): mixed => $this->transients[ $k ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( string $k, mixed $v ): bool {
				$this->transients[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'wp_salt' )->justReturn( 'test-salt' );
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $v ): string => (string) json_encode( $v ) );
		Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://shop.example.com' . $p );
		Functions\when( 'rest_url' )->alias( static fn( string $p = '' ): string => 'https://shop.example.com/wp-json/' . $p );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $u, int $c = -1 ): mixed => parse_url( $u, $c ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ): string => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_key' )->alias( static fn( $s ): string => (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ) );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'is_email' )->alias( static fn( string $e ): bool => false !== filter_var( $e, FILTER_VALIDATE_EMAIL ) );
		Functions\when( 'is_wp_error' )->alias( static fn( $x ): bool => $x instanceof \WP_Error );
		Functions\when( 'current_user_can' )->justReturn( false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
