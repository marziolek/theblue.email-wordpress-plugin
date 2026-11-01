<?php
/**
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use TheBlueEmail\Forms\Activity_Log;
use TheBlueEmail\Forms\Api_Response;
use TheBlueEmail\Forms\Client;
use TheBlueEmail\Forms\Relay;

final class RelayTest extends TestCase {

	private const KEY = 'aabbcc112233';

	private MockInterface $client;
	private Activity_Log $log;

	protected function setUp(): void {
		parent::setUp();
		$this->client = Mockery::mock( Client::class );
		$this->log    = new Activity_Log();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'do_action' )->justReturn( null );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
	}

	protected function tearDown(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}

	private function relay(): Relay {
		return new Relay( $this->client, $this->log );
	}

	/** @return array<string, mixed> */
	private function input( array $over = array() ): array {
		return $over + array(
			'email'      => 'ada@example.com',
			'first_name' => 'Ada',
			'pow_salt'   => 'salt',
			'pow_nonce'  => '42',
			'page_url'   => 'https://shop.example.com/contact/',
		);
	}

	#[Test]
	public function it_sends_the_signup_with_the_visitors_details(): void {
		$this->client->shouldReceive( 'subscribe' )->once()->with(
			self::KEY,
			array(
				'email'      => 'ada@example.com',
				'first_name' => 'Ada',
				'hp'         => '',
				'pow_salt'   => 'salt',
				'pow_nonce'  => '42',
				'visitor_ip' => '192.0.2.10',
				'page_url'   => 'https://shop.example.com/contact/',
				'country'    => '',
			)
		)->andReturn( new Api_Response( 200, array( 'success' => true ) ) );

		$result = $this->relay()->submit( 'contactform7', 'Contact', self::KEY, $this->input( array( 'email' => '  ada@example.com ' ) ) );

		$this->assertTrue( $result->is_subscribed() );
		$this->assertSame( 'a***@example.com', $this->log->all()[0]['email'] );
	}

	#[Test]
	public function nothing_is_sent_without_consent_and_nothing_is_logged(): void {
		$this->client->shouldNotReceive( 'subscribe' );

		$result = $this->relay()->submit( 'contactform7', 'Contact', self::KEY, $this->input( array( 'consent' => false ) ) );

		$this->assertSame( 'skipped', $result->outcome );
		$this->assertSame( array(), $this->log->all() );
	}

	#[Test]
	public function a_filled_honeypot_is_refused_here(): void {
		$this->client->shouldNotReceive( 'subscribe' );

		$result = $this->relay()->submit( 'contactform7', 'Contact', self::KEY, $this->input( array( 'hp' => 'http://spam' ) ) );

		$this->assertSame( 'tbef_honeypot', $result->code );
	}

	#[Test]
	public function an_invalid_or_overlong_address_is_refused_here(): void {
		$this->client->shouldNotReceive( 'subscribe' );

		$this->assertSame( 'tbef_invalid_email', $this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input( array( 'email' => 'nope' ) ) )->code );
		$this->assertSame( 'tbef_invalid_email', $this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input( array( 'email' => str_repeat( 'a', 250 ) . '@example.com' ) ) )->code );
	}

	#[Test]
	public function a_missing_target_is_refused_here(): void {
		$this->client->shouldNotReceive( 'subscribe' );

		$this->assertSame( 'tbef_no_target', $this->relay()->submit( 'gravityforms', 'F', '', $this->input() )->code );
	}

	#[Test]
	public function one_visitor_gets_ten_signups_an_hour_per_target(): void {
		$this->client->shouldReceive( 'subscribe' )->times( Relay::RATE_LIMIT )->andReturn( new Api_Response( 200 ) );

		for ( $i = 0; $i < Relay::RATE_LIMIT; $i++ ) {
			$this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input() );
		}

		$this->assertSame( 'tbef_rate_limited', $this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input() )->code );
	}

	#[Test]
	public function details_seen_at_submission_win_over_this_request(): void {
		$this->client->shouldReceive( 'subscribe' )->once()->with(
			self::KEY,
			Mockery::on( static fn( array $f ): bool => '198.51.100.7' === $f['visitor_ip'] && 'PL' === $f['country'] )
		)->andReturn( new Api_Response( 200 ) );

		$this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input( array( 'visitor_ip' => '198.51.100.7', 'country' => 'PL' ) ) );
	}

	#[Test]
	public function a_bad_ip_or_country_is_dropped(): void {
		$this->client->shouldReceive( 'subscribe' )->once()->with(
			self::KEY,
			Mockery::on( static fn( array $f ): bool => '' === $f['visitor_ip'] && '' === $f['country'] )
		)->andReturn( new Api_Response( 200 ) );

		$this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input( array( 'visitor_ip' => 'x', 'country' => 'Poland' ) ) );
	}

	#[Test]
	public function the_platforms_refusal_and_challenge_come_back(): void {
		$challenge = array( 'url' => 'https://theblue.email/api/tbe/v1/forms/aabbcc112233/verify', 'token' => 't' );
		$this->client->shouldReceive( 'subscribe' )->andReturn(
			new Api_Response( 403, array( 'code' => 'tbe_challenge_required', 'data' => array( 'challenge' => $challenge ) ), 'tbe_challenge_required', 'One more step' )
		);

		$result = $this->relay()->submit( 'gravityforms', 'F', self::KEY, $this->input() );

		$this->assertSame( 'refused', $result->outcome );
		$this->assertSame( $challenge, $result->challenge );
		$this->assertSame( 'tbe_challenge_required', $this->log->all()[0]['detail'] );
	}

	#[Test]
	public function the_log_keeps_fifty_entries(): void {
		for ( $i = 0; $i < 60; $i++ ) {
			$this->log->add( 'gravityforms', 'F' . $i, 'a@b.co', 'subscribed' );
		}

		$this->assertCount( Activity_Log::SIZE, $this->log->all() );
		$this->assertSame( 'F59', $this->log->all()[49]['label'] );
	}
}
