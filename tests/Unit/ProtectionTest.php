<?php
/**
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\Test;
use TheBlueEmail\Forms\Integrations\Contact_Form_7;
use TheBlueEmail\Forms\Protection;
use TheBlueEmail\Forms\Targets;
use TheBlueEmail\Forms\Visitor;

final class ProtectionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Targets::reset();
		Functions\when( 'wp_script_is' )->justReturn( false );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		$_POST   = array();
		$_SERVER = array_diff_key( $_SERVER, array_flip( array( 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_CF_IPCOUNTRY' ) ) );
		parent::tearDown();
	}

	#[Test]
	public function a_form_gets_a_honeypot_and_one_pair_of_fields_per_target(): void {
		$html = Protection::fields(
			array(
				array( 'target' => 'gf-12', 'email_field' => 'input_3' ),
				array( 'target' => 'gf-13', 'email_field' => 'input_4' ),
			)
		);

		$this->assertStringContainsString( 'name="tbef_hp"', $html );
		$this->assertStringContainsString( 'name="tbef_pow[gf-12][salt]"', $html );
		$this->assertStringContainsString( 'name="tbef_pow[gf-13][nonce]"', $html );
		$this->assertStringContainsString( 'value="gf-12,gf-13"', $html );
		$this->assertStringContainsString( 'data-tbef="[{&quot;t&quot;:&quot;gf-12&quot;,&quot;e&quot;:&quot;input_3&quot;}', $html );
	}

	#[Test]
	public function a_form_without_a_valid_target_gets_nothing(): void {
		$this->assertSame( '', Protection::fields( array( array( 'target' => '"><x', 'email_field' => 'e' ) ) ) );
	}

	#[Test]
	public function what_the_browser_posted_is_read_for_its_own_target_only(): void {
		$_POST = array(
			'tbef_hp'  => '',
			'tbef_pow' => array(
				'gf-12' => array( 'salt' => '1793558821.15.904c21ede9ccfd64.cb0277e3450628378d2fee334aed22af', 'nonce' => '18324' ),
				'gf-13' => array( 'salt' => 'other', 'nonce' => '1' ),
			),
		);

		$this->assertSame(
			array( 'hp' => '', 'pow_salt' => '1793558821.15.904c21ede9ccfd64.cb0277e3450628378d2fee334aed22af', 'pow_nonce' => '18324' ),
			Protection::posted( 'gf-12' )
		);
	}

	#[Test]
	public function malformed_work_is_dropped(): void {
		$_POST = array( 'tbef_pow' => array( 'gf-12' => array( 'salt' => '<script>', 'nonce' => '12a' ) ) );

		$this->assertSame( array( 'hp' => '', 'pow_salt' => '', 'pow_nonce' => '' ), Protection::posted( 'gf-12' ) );
	}

	#[Test]
	public function a_target_resolves_only_through_its_integration(): void {
		Targets::register( 'cf7', static fn( int $id ): string => 3 === $id ? 'aabbcc112233' : 'not-a-key' );

		$this->assertSame( 'aabbcc112233', Targets::resolve( 'cf7-3' ) );
		$this->assertSame( '', Targets::resolve( 'cf7-4' ) );
		$this->assertSame( '', Targets::resolve( 'gf-3' ) );
		$this->assertSame( '', Targets::resolve( 'cf7-03' ) );
		$this->assertSame( '', Targets::resolve( 'cf7-3; drop' ) );
	}

	#[Test]
	public function the_visitor_ip_ignores_a_forwarding_header_unless_told_otherwise(): void {
		$_SERVER['REMOTE_ADDR']           = '192.0.2.10';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.5';
		$_SERVER['HTTP_CF_IPCOUNTRY']     = 'PL';

		$this->assertSame( '192.0.2.10', Visitor::ip() );
		$this->assertSame( '', Visitor::country() );
	}

	#[Test]
	public function contact_form_7_settings_are_validated(): void {
		$this->assertSame(
			array( 'enabled' => true, 'form' => 'aabbcc112233', 'email_field' => 'your-email', 'name_field' => 'your-name', 'consent_field' => 'newsletter' ),
			Contact_Form_7::clean( array( 'enabled' => '1', 'form' => 'aabbcc112233', 'email_field' => 'your-email', 'name_field' => 'your-name', 'consent_field' => 'newsletter' ) )
		);

		$bad = Contact_Form_7::clean( array( 'enabled' => '1', 'form' => 'x', 'email_field' => '"><script>', 'consent_field' => 'a b' ) );
		$this->assertFalse( $bad['enabled'] );
		$this->assertSame( 'your-email', $bad['email_field'] );
		$this->assertSame( '', $bad['consent_field'] );
	}
}
