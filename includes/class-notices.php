<?php
/**
 * Admin notices.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Three things an administrator needs to know, each shown only where it helps:
 * the site is not connected (Plugins screen), the key stopped working (every
 * admin screen, until fixed), and a form placed on a page does not allow this
 * site (every admin screen, with the button that fixes it).
 */
class Notices {

	/**
	 * Constructor.
	 *
	 * @param Connection $connection The connection.
	 * @param Forms      $forms      The cached forms.
	 * @param Embed      $embed      The embed printer.
	 */
	public function __construct( private Connection $connection, private Forms $forms, private Embed $embed ) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Print whatever applies.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id   = $screen ? (string) $screen->id : '';
		$on_settings = Settings_Page::SCREEN === $screen_id;

		if ( ! $this->connection->is_connected() ) {
			if ( 'plugins' === $screen_id ) {
				$this->notice( 'info', __( 'theblue.email Forms is active. Connect your theblue.email account to place your forms.', 'theblue-email-forms' ), __( 'Connect', 'theblue-email-forms' ) );
			}
			return;
		}

		if ( $on_settings ) {
			return;
		}

		if ( $this->connection->is_revoked() ) {
			$this->notice( 'error', __( 'theblue.email no longer accepts this site\'s key, so new signups from Gravity Forms and Contact Form 7 are not being sent.', 'theblue-email-forms' ), __( 'Fix the connection', 'theblue-email-forms' ) );
			return;
		}

		$names = array();
		foreach ( $this->embed->unallowed() as $key ) {
			$form = $this->forms->find( $key );
			if ( null !== $form && ! Forms::allows_this_site( $form ) ) {
				$names[] = (string) $form['name'];
			}
		}

		if ( array() !== $names ) {
			$this->notice(
				'warning',
				sprintf(
					/* translators: 1: comma-separated form names, 2: this site's hostname. */
					__( 'These theblue.email forms are placed on your pages but do not allow %2$s, so visitors see a short message instead: %1$s.', 'theblue-email-forms' ),
					implode( ', ', $names ),
					Forms::site_host()
				),
				__( 'Allow this site', 'theblue-email-forms' )
			);
		}
	}

	/**
	 * One notice with a link to the settings page.
	 *
	 * @param string $type    info, warning or error.
	 * @param string $message The text.
	 * @param string $link    The link text.
	 */
	private function notice( string $type, string $message, string $link ): void {
		printf(
			'<div class="notice notice-%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			esc_attr( $type ),
			esc_html( $message ),
			esc_url( Settings_Page::url() ),
			esc_html( $link )
		);
	}
}
