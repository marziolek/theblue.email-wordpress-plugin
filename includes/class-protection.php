<?php
/**
 * The honeypot and proof-of-work fields on Gravity Forms and Contact Form 7 forms.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Adds to a form that sends signups to theblue.email:
 *
 *  - a honeypot field, hidden from people, which naive bots fill in;
 *  - one pair of hidden proof-of-work fields per target, which
 *    tbef-protect.js fills by solving the platform's challenge for the
 *    address typed into the form, in the visitor's browser.
 *
 * Neither changes how the form looks or submits.
 */
final class Protection {

	public const SCRIPT_HANDLE = 'tbef-protect';

	/**
	 * The markup to place inside the form.
	 *
	 * @param array<int, array{target: string, email_field: string}> $targets Where signups go and which field holds the address.
	 */
	public static function fields( array $targets ): string {
		$config = array();
		$html   = '<span class="tbef-hp" style="display:none !important" aria-hidden="true">'
			. '<label>' . esc_html__( 'Leave this field empty', 'theblue-email-forms' )
			. ' <input type="text" name="tbef_hp" value="" tabindex="-1" autocomplete="off"></label></span>';

		foreach ( $targets as $t ) {
			if ( 1 !== preg_match( '/^[a-z0-9]+-[0-9]+$/', $t['target'] ) ) {
				continue;
			}

			$config[] = array(
				't' => $t['target'],
				'e' => $t['email_field'],
			);

			$html .= sprintf(
				'<input type="hidden" name="tbef_pow[%1$s][salt]" value=""><input type="hidden" name="tbef_pow[%1$s][nonce]" value="">',
				esc_attr( $t['target'] )
			);
		}

		if ( array() === $config ) {
			return '';
		}

		$html .= sprintf(
			'<input type="hidden" name="tbef_targets" value="%1$s" data-tbef="%2$s">',
			esc_attr( implode( ',', array_column( $config, 't' ) ) ),
			esc_attr( (string) wp_json_encode( $config ) )
		);

		self::enqueue();

		return $html;
	}

	/**
	 * Load tbef-protect.js.
	 */
	public static function enqueue(): void {
		if ( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			TBEF_URL . 'assets/js/tbef-protect.js',
			array(),
			TBEF_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.tbefProtect = ' . wp_json_encode( array( 'restUrl' => esc_url_raw( rest_url( 'tbef/v1/' ) ) ) ) . ';',
			'before'
		);
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * What the visitor's browser posted for one target.
	 *
	 * Read straight from the request: Gravity Forms and Contact Form 7 run
	 * their own checks on the submission, and these fields carry no
	 * authority of their own, theblue.email verifies the work.
	 *
	 * @param string $target e.g. gf-12.
	 * @return array{hp: string, pow_salt: string, pow_nonce: string}
	 */
	public static function posted( string $target ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form submission handled by Gravity Forms or Contact Form 7; the proof of work is verified by theblue.email.
		$hp  = isset( $_POST['tbef_hp'] ) && is_string( $_POST['tbef_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['tbef_hp'] ) ) : '';
		$pow = isset( $_POST['tbef_pow'] ) && is_array( $_POST['tbef_pow'] ) ? wp_unslash( $_POST['tbef_pow'] ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$mine = is_array( $pow[ $target ] ?? null ) ? $pow[ $target ] : array();

		return array(
			'hp'        => $hp,
			'pow_salt'  => is_string( $mine['salt'] ?? null ) ? self::clean_salt( $mine['salt'] ) : '',
			'pow_nonce' => is_string( $mine['nonce'] ?? null ) && 1 === preg_match( '/^[0-9]{1,15}$/', $mine['nonce'] ) ? $mine['nonce'] : '',
		);
	}

	/**
	 * A salt is {expires}.{bits}.{random}.{signature}: digits, letters, dots,
	 * dashes and underscores. Anything else is dropped.
	 *
	 * @param string $salt The posted salt.
	 */
	private static function clean_salt( string $salt ): string {
		return 1 === preg_match( '/^[A-Za-z0-9._\-]{1,256}$/', $salt ) ? $salt : '';
	}
}
