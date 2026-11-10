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
 * A "theblue.email" link with the site's icon in the toolbar at the top of
 * every screen, for people who can manage the site. On by default; the
 * settings page switches it off. Also paints the same icon on the plugin's
 * item in the side menu.
 */
class Admin_Bar {

	public const OPTION = 'tbef_admin_bar';

	/**
	 * The blue of theblue.email's favicon.
	 */
	public const BLUE = '#3662e3';

	/**
	 * The theblue.email favicon: a blue square with corners rounded to a fifth
	 * of its side, inset by a pixel so it sits at the weight of a dashicon.
	 */
	private const ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect x="1" y="1" width="18" height="18" rx="3.6" fill="' . self::BLUE . '"/></svg>';

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
	 * The icon as a data URI, for the side menu and the toolbar. URL-encoded,
	 * not base64: WordPress repaints base64 menu icons in the admin colour
	 * scheme, and this one keeps its blue.
	 */
	public static function icon(): string {
		return 'data:image/svg+xml,' . rawurlencode( self::ICON_SVG );
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
	 * The icon on the side menu item (registered with 'none', so WordPress
	 * leaves the box empty for it) and in the toolbar.
	 */
	public function styles(): void {
		$url = 'url("' . self::icon() . '")';
		$css = '';

		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			$css .= '#adminmenu #toplevel_page_' . Settings_Page::SLUG . ' div.wp-menu-image{background:' . $url . ' center/20px 20px no-repeat}';
		}

		if ( function_exists( 'is_admin_bar_showing' ) && is_admin_bar_showing() && self::shows() ) {
			$css .= '#wpadminbar #wp-admin-bar-tbef .ab-icon:before{content:"";display:block;top:0;width:20px;height:20px;background:' . $url . ' center/20px 20px no-repeat}';
		}

		if ( '' === $css ) {
			return;
		}

		wp_register_style( 'tbef-admin-bar', false, array(), TBEF_VERSION );
		wp_enqueue_style( 'tbef-admin-bar' );
		wp_add_inline_style( 'tbef-admin-bar', $css );
	}
}
