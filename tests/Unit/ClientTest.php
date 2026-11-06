<?php
/**
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\Test;
use TheBlueEmail\Forms\Client;
use TheBlueEmail\Forms\Connection;

final class ClientTest extends TestCase {

	private const KEY = 'tbe_0123456789abcdef0123456789abcdef';

	/** @var array<int, array{0: string, 1: array<string, mixed>}> */
	private array $requests = array();

	private mixed $answer = null;

	protected function setUp(): void {
		parent::setUp();
		$this->requests = array();

		Functions\when( 'wp_remote_request' )->alias(
			function ( string $url, array $args ): mixed {
				$this->requests[] = array( $url, $args );
				return $this->answer;
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( array $r ): int => $r['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( array $r ): string => $r['body'] );
	}

	private function connected(): Connection {
		$connection = new Connection();
		$connection->save( self::KEY, array() );

		return $connection;
	}

	private function answer( int $code, mixed $body ): void {
		$this->answer = array(
			'code' => $code,
			'body' => is_string( $body ) ? $body : (string) json_encode( $body ),
		);
	}

	#[Test]
	public function it_sends_the_key_as_a_bearer_token_to_the_platform_only(): void {
		$this->answer( 200, array( 'data' => array() ) );

		( new Client( $this->connected() ) )->forms();

		[ $url, $args ] = $this->requests[0];
		$this->assertSame( 'https://theblue.email/api/tbe/v1/site/forms', $url );
		$this->assertSame( 'Bearer ' . self::KEY, $args['headers']['Authorization'] );
		$this->assertSame( 0, $args['redirection'] );
		$this->assertTrue( $args['sslverify'] );
	}

	#[Test]
	public function it_posts_json(): void {
		$this->answer( 200, array( 'success' => true ) );

		( new Client( $this->connected() ) )->subscribe( 'aabbcc112233', array( 'email' => 'ada@example.com' ) );

		[ $url, $args ] = $this->requests[0];
		$this->assertSame( 'https://theblue.email/api/tbe/v1/site/forms/aabbcc112233/subscribe', $url );
		$this->assertSame( 'POST', $args['method'] );
		$this->assertSame( '{"email":"ada@example.com"}', $args['body'] );
	}

	#[Test]
	public function it_makes_no_request_without_a_key(): void {
		$response = ( new Client( new Connection() ) )->forms();

		$this->assertSame( 'tbef_not_connected', $response->code );
		$this->assertSame( array(), $this->requests );
	}

	#[Test]
	public function a_refused_stored_key_marks_the_connection_revoked(): void {
		$this->answer( 401, array( 'code' => 'tbe_auth_invalid', 'message' => 'Invalid API key.' ) );
		$connection = $this->connected();

		$response = ( new Client( $connection ) )->account();

		$this->assertTrue( $response->unauthorised() );
		$this->assertSame( 'tbe_auth_invalid', $response->code );
		$this->assertSame( 'Invalid API key.', $response->message );
		$this->assertTrue( $connection->is_revoked() );
	}

	#[Test]
	public function trying_a_new_key_does_not_mark_the_stored_one_revoked(): void {
		$this->answer( 401, array( 'code' => 'tbe_auth_invalid' ) );
		$connection = $this->connected();

		( new Client( $connection ) )->account_for_key( 'tbe_ffffffffffffffffffffffffffffffff' );

		$this->assertFalse( $connection->is_revoked() );
	}

	#[Test]
	public function an_unreachable_platform_is_an_answer_not_an_exception(): void {
		$this->answer = new \WP_Error( 'http_request_failed', 'timed out' );

		$response = ( new Client( $this->connected() ) )->forms();

		$this->assertSame( 0, $response->status );
		$this->assertSame( 'tbef_unreachable', $response->code );
	}

	#[Test]
	public function a_body_that_is_not_json_is_an_empty_answer(): void {
		$this->answer( 500, '<html>error</html>' );

		$response = ( new Client( $this->connected() ) )->forms();

		$this->assertSame( array(), $response->data );
		$this->assertSame( 'tbef_http_500', $response->code );
	}

	#[Test]
	public function an_oversized_body_is_refused(): void {
		$this->answer( 200, str_repeat( 'x', 1048577 ) );

		$this->assertSame( 'tbef_too_large', ( new Client( $this->connected() ) )->forms()->code );
	}

	#[Test]
	public function it_will_not_build_a_path_from_unexpected_characters(): void {
		$response = ( new Client( $this->connected() ) )->challenge( '../../wp-admin' );

		$this->assertSame( 'tbef_bad_path', $response->code );
		$this->assertSame( array(), $this->requests );
	}
}
