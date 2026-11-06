<?php
/**
 * The theblue.email link in the toolbar, and the plugin's menu icon.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * A "theblue.email" link with the corgi in the toolbar at the top of every
 * screen, for people who can manage the site. On by default; the settings page
 * switches it off.
 */
class Admin_Bar {

	public const OPTION = 'tbef_admin_bar';

	/**
	 * The corgi's head, one shape with the eyes and nose cut out. fill="black"
	 * lets WordPress recolour it to the admin colour scheme in the side menu.
	 */
	private const ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M2.6 1.2c.3-.4.8-.4 1.2-.1L8 5.2c.7-.2 1.3-.2 2-.2s1.3 0 2 .2l4.2-4.1c.4-.3.9-.3 1.2.1 1 1.9 1.2 4.4.6 6.8 1 1.2 1.6 2.8 1.6 4.4C19.6 16.6 15.4 19 10 19S.4 16.6.4 12.4c0-1.6.6-3.2 1.6-4.4-.6-2.4-.4-4.9.6-6.8zM7 9.6a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zm6 0a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zm-3 3.6c-.9 0-1.8.3-1.8.8 0 .6 1 1.3 1.8 1.3s1.8-.7 1.8-1.3c0-.5-.9-.8-1.8-.8z"/></svg>';

	/**
	 * Hooks. Runs on the front end too, where the toolbar also shows.
	 */
	public function register(): void {
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Whether the toolbar link shows.
	 */
	public static function is_enabled(): bool {
		return '0' !== (string) get_option( self::OPTION, '1' );
	}

	/**
	 * Switch the toolbar link on or off.
	 *
	 * @param bool $enabled On or off.
	 */
	public static function set_enabled( bool $enabled ): void {
		update_option( self::OPTION, $enabled ? '1' : '0', true );
	}

	/**
	 * The icon as a data URI, for the side menu and the toolbar. WordPress
	 * recolours only base64 SVG menu icons, hence the encoding.
	 */
	public static function icon(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a static SVG icon; WordPress recolours only base64 menu icons.
		return 'data:image/svg+xml;base64,' . base64_encode( self::ICON_SVG );
	}

	/**
	 * Whether this person sees the link.
	 */
	private static function shows(): bool {
		return self::is_enabled() && current_user_can( 'manage_options' );
	}

	/**
	 * The toolbar node.
	 *
	 * @param \WP_Admin_Bar|object $bar The toolbar.
	 */
	public function add_node( $bar ): void {
		if ( ! self::shows() || ! is_object( $bar ) || ! method_exists( $bar, 'add_node' ) ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'tbef',
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'theblue.email', 'theblue-email-forms' ) . '</span>',
				'href'  => Settings_Page::url(),
				'meta'  => array(
					'title' => __( 'theblue.email settings', 'theblue-email-forms' ),
				),
			)
		);
	}

	/**
	 * The icon in the toolbar: a mask filled with the text colour, so it
	 * follows the colour scheme and the hover colour like a dashicon.
	 */
	public function styles(): void {
		if ( ! function_exists( 'is_admin_bar_showing' ) || ! is_admin_bar_showing() || ! self::shows() ) {
			return;
		}

		$url = 'url("' . self::icon() . '")';
		$css = '#wpadminbar #wp-admin-bar-tbef .ab-icon:before{content:"";display:block;top:0;width:20px;height:20px;background-color:currentColor;'
			. '-webkit-mask:' . $url . ' center/20px 20px no-repeat;mask:' . $url . ' center/20px 20px no-repeat}';

		wp_register_style( 'tbef-admin-bar', false, array( 'admin-bar' ), TBEF_VERSION );
		wp_enqueue_style( 'tbef-admin-bar' );
		wp_add_inline_style( 'tbef-admin-bar', $css );
	}
}
