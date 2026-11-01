<?php
/**
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use TheBlueEmail\Forms\Api_Response;
use TheBlueEmail\Forms\Client;
use TheBlueEmail\Forms\Connection;
use TheBlueEmail\Forms\Embed;
use TheBlueEmail\Forms\Forms;
use TheBlueEmail\Forms\Shortcode;

final class FormsAndEmbedTest extends TestCase {

	/** @var array<string, string> */
	private array $inline = array();

	private bool $enqueued = false;

	protected function setUp(): void {
		parent::setUp();
		$this->inline   = array();
		$this->enqueued = false;

		Functions\when( 'wp_script_is' )->alias( fn(): bool => $this->enqueued );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_add_inline_script' )->alias(
			function ( string $handle, string $js ): bool {
				$this->inline[ $handle ] = $js;
				return true;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			function (): void {
				$this->enqueued = true;
			}
		);
		Functions\when( 'shortcode_atts' )->alias( static fn( array $d, array $a ): array => array_merge( $d, array_intersect_key( $a, $d ) ) );
		Functions\when( 'sanitize_html_class' )->alias( static fn( string $c ): string => (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $c ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function forms_with( array $rows ): Forms {
		$client = Mockery::mock( Client::class );
		$client->shouldReceive( 'forms' )->andReturn( new Api_Response( 200, array( 'data' => $rows ) ) );

		$forms = new Forms( $client );
		$forms->refresh();

		return $forms;
	}

	/** @return array<string, mixed> */
	private function row( array $over = array() ): array {
		return $over + array(
			'public_key'      => 'aabbcc112233',
			'name'            => 'Newsletter',
			'status'          => 'active',
			'fields'          => 2,
			'list'            => array( 'id' => 2, 'name' => 'Customers' ),
			'allowed_domains' => array( 'shop.example.com' ),
		);
	}

	#[Test]
	public function the_forms_list_keeps_only_well_formed_rows(): void {
		$forms = $this->forms_with( array( $this->row(), array( 'public_key' => 'XYZ' ), 'junk', $this->row( array( 'public_key' => 'ffeeddccbbaa', 'status' => 'weird', 'fields' => 9 ) ) ) );

		$this->assertCount( 2, $forms->all() );
		$this->assertSame( 'Customers', $forms->find( 'aabbcc112233' )['list'] );
		$this->assertSame( 'draft', $forms->find( 'ffeeddccbbaa' )['status'] );
		$this->assertSame( 2, $forms->find( 'ffeeddccbbaa' )['fields'] );
		$this->assertFalse( $forms->is_stale() );
	}

	#[Test]
	public function a_failed_refresh_keeps_the_old_list(): void {
		$forms  = $this->forms_with( array( $this->row() ) );
		$client = Mockery::mock( Client::class );
		$client->shouldReceive( 'forms' )->andReturn( new Api_Response( 0, array(), 'tbef_unreachable', 'down' ) );

		( new Forms( $client ) )->refresh();

		$this->assertCount( 1, $forms->all() );
	}

	#[Test]
	public function it_knows_whether_this_site_is_allowed(): void {
		$this->assertTrue( Forms::allows_this_site( $this->row() ) );
		$this->assertFalse( Forms::allows_this_site( $this->row( array( 'allowed_domains' => array( 'www.shop.example.com' ) ) ) ) );
	}

	#[Test]
	public function the_markup_is_the_platforms_snippet(): void {
		$embed = new Embed( $this->forms_with( array( $this->row() ) ), new Connection() );

		$this->assertSame(
			'<div class="tbef-form"><div class="tbe-form" data-form-id="aabbcc112233" data-fields="2"></div></div>',
			$embed->markup( 'AABBCC112233' )
		);
		$this->assertTrue( $this->enqueued );
		$this->assertStringContainsString( '"https:\/\/theblue.email\/api\/tbe\/v1\/"', $this->inline[ Embed::SCRIPT_HANDLE ] );
	}

	#[Test]
	public function a_draft_prints_nothing_for_visitors(): void {
		$embed = new Embed( $this->forms_with( array( $this->row( array( 'status' => 'draft' ) ) ) ), new Connection() );

		$this->assertSame( '', $embed->markup( 'aabbcc112233' ) );
		$this->assertFalse( $this->enqueued );
	}

	#[Test]
	public function an_unknown_key_is_left_to_the_platform(): void {
		$embed = new Embed( $this->forms_with( array() ), new Connection() );

		$this->assertStringContainsString( 'data-form-id="ffeeddccbbaa"', $embed->markup( 'ffeeddccbbaa' ) );
	}

	#[Test]
	public function a_malformed_key_prints_nothing(): void {
		$embed = new Embed( $this->forms_with( array() ), new Connection() );

		$this->assertSame( '', $embed->markup( '"><script>' ) );
	}

	#[Test]
	public function a_form_placed_on_a_site_it_does_not_allow_is_remembered(): void {
		$embed = new Embed( $this->forms_with( array( $this->row( array( 'allowed_domains' => array() ) ) ) ), new Connection() );

		$embed->markup( 'aabbcc112233' );
		$this->assertSame( array( 'aabbcc112233' ), $embed->unallowed() );

		$embed->forget_unallowed( 'aabbcc112233' );
		$this->assertSame( array(), $embed->unallowed() );
	}

	#[Test]
	public function the_shortcode_cleans_its_classes(): void {
		$shortcode = new Shortcode( new Embed( $this->forms_with( array( $this->row() ) ), new Connection() ) );

		$html = $shortcode->render( array( 'key' => 'aabbcc112233', 'class' => 'wide "x' ) );

		$this->assertStringContainsString( 'class="tbef-form wide x"', $html );
		$this->assertSame( '[theblue_email_form key="aabbcc112233"]', Shortcode::for_key( 'aabbcc112233' ) );
	}
}
