<?php
/**
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TheBlueEmail\Forms\Connection;
use TheBlueEmail\Forms\Crypto;

final class ConnectionTest extends TestCase {

	private const KEY = 'tbe_0123456789abcdef0123456789abcdef';

	#[Test]
	public function it_stores_the_key_encrypted_and_reads_it_back(): void {
		$connection = new Connection();

		$this->assertTrue( $connection->save( self::KEY, array( 'name' => 'Ada' ) ) );
		$this->assertStringNotContainsString( self::KEY, (string) $this->options[ Connection::OPTION_KEY ] );
		$this->assertSame( self::KEY, $connection->key() );
		$this->assertTrue( $connection->is_connected() );
		$this->assertSame( 'tbe_0123', $connection->key_prefix() );
	}

	#[Test]
	public function a_key_encrypted_with_other_salts_cannot_be_read(): void {
		$this->options[ Connection::OPTION_KEY ] = 's1:' . base64_encode( str_repeat( 'x', 64 ) );

		$connection = new Connection();

		$this->assertSame( '', $connection->key() );
		$this->assertFalse( $connection->is_connected() );
		$this->assertTrue( $connection->is_unreadable() );
	}

	#[Test]
	public function it_accepts_only_the_platforms_key_format(): void {
		$this->assertTrue( Connection::is_valid_key_format( self::KEY ) );
		$this->assertFalse( Connection::is_valid_key_format( 'tbe_short' ) );
		$this->assertFalse( Connection::is_valid_key_format( 'sk_0123456789abcdef0123456789abcdef' ) );
		$this->assertFalse( Connection::is_valid_key_format( self::KEY . "\n" ) );
	}

	#[Test]
	public function it_keeps_only_the_account_fields_it_reads(): void {
		$connection = new Connection();
		$connection->save_account( array( 'name' => 'Ada', 'plan' => 'pro', 'secret' => 'x', 'email' => array( 'no' ) ) );

		$this->assertSame(
			array( 'name' => 'Ada', 'email' => '', 'plan' => 'pro', 'embed_script_url' => '' ),
			$this->options[ Connection::OPTION_ACCOUNT ]
		);
	}

	#[Test]
	public function the_embed_script_must_come_from_the_platform(): void {
		$connection = new Connection();

		$connection->save_account( array( 'embed_script_url' => 'https://theblue.email/embed/tbe-embed.js?v=1.0.42' ) );
		$this->assertSame( 'https://theblue.email/embed/tbe-embed.js?v=1.0.42', $connection->embed_script_url() );

		$connection->save_account( array( 'embed_script_url' => 'https://theblue.email.evil.test/x.js' ) );
		$this->assertSame( 'https://theblue.email/embed/tbe-embed.js', $connection->embed_script_url() );
	}

	#[Test]
	public function forgetting_removes_everything(): void {
		$connection = new Connection();
		$connection->save( self::KEY, array() );
		$connection->mark_revoked();

		$connection->forget();

		$this->assertSame( array(), $this->options );
	}

	#[Test]
	public function crypto_round_trips_and_rejects_tampering(): void {
		$stored = Crypto::encrypt( 'hello' );

		$this->assertSame( 'hello', Crypto::decrypt( $stored ) );
		$this->assertSame( '', Crypto::decrypt( substr( $stored, 0, -4 ) . 'AAAA' ) );
		$this->assertSame( '', Crypto::decrypt( 'plain' ) );
	}
}
