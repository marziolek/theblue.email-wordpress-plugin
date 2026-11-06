<?php
/**
 * The toolbar link and the menu icon.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Tests\Unit;

use Brain\Monkey\Functions;
use TheBlueEmail\Forms\Admin_Bar;

final class AdminBarTest extends TestCase {

	/** @var array<int, array<string, mixed>> */
	private array $nodes = array();

	protected function setUp(): void {
		parent::setUp();
		$this->nodes = array();
		Functions\when( 'admin_url' )->alias( static fn( string $p = '' ): string => 'https://shop.example.com/wp-admin/' . $p );
	}

	private function bar(): object {
		$test = $this;
		return new class( $test ) {
			public function __construct( private AdminBarTest $test ) {}
			public function add_node( array $node ): void {
				$this->test->record( $node );
			}
		};
	}

	public function record( array $node ): void {
		$this->nodes[] = $node;
	}

	public function test_it_is_on_until_switched_off(): void {
		self::assertTrue( Admin_Bar::is_enabled() );

		Admin_Bar::set_enabled( false );
		self::assertSame( '0', $this->options[ Admin_Bar::OPTION ] );
		self::assertFalse( Admin_Bar::is_enabled() );

		Admin_Bar::set_enabled( true );
		self::assertTrue( Admin_Bar::is_enabled() );
	}

	public function test_it_adds_a_link_to_the_settings_page_for_admins(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		( new Admin_Bar() )->add_node( $this->bar() );

		self::assertCount( 1, $this->nodes );
		self::assertSame( 'tbef', $this->nodes[0]['id'] );
		self::assertSame( 'https://shop.example.com/wp-admin/admin.php?page=theblue-email-forms', $this->nodes[0]['href'] );
		self::assertStringContainsString( 'ab-icon', (string) $this->nodes[0]['title'] );
		self::assertStringContainsString( 'theblue.email', (string) $this->nodes[0]['title'] );
	}

	public function test_it_adds_nothing_when_switched_off(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Admin_Bar::set_enabled( false );

		( new Admin_Bar() )->add_node( $this->bar() );

		self::assertSame( array(), $this->nodes );
	}

	public function test_it_adds_nothing_for_people_who_cannot_manage_the_site(): void {
		( new Admin_Bar() )->add_node( $this->bar() );

		self::assertSame( array(), $this->nodes );
	}

	public function test_the_icon_is_a_base64_svg_wordpress_can_recolour(): void {
		$icon = Admin_Bar::icon();

		self::assertStringStartsWith( 'data:image/svg+xml;base64,', $icon );

		$svg = (string) base64_decode( substr( $icon, strlen( 'data:image/svg+xml;base64,' ) ), true );
		self::assertStringStartsWith( '<svg', $svg );
		self::assertStringContainsString( 'fill="black"', $svg );
	}
}
