<?php
/**
 * The last relayed signups, for the settings page.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Fifty entries at most, with the address masked: the log exists to tell an
 * administrator whether the integration works, not to keep a second copy of
 * who signed up. Subscribers live in theblue.email.
 */
class Activity_Log {

	public const OPTION = 'tbef_activity';
	public const SIZE   = 50;

	/**
	 * Add one entry.
	 *
	 * @param string $source  gravityforms or contactform7.
	 * @param string $label   Which form on this site, e.g. its title.
	 * @param string $email   The address, masked before it is stored.
	 * @param string $outcome subscribed, refused or skipped.
	 * @param string $detail  The platform's code or a local reason.
	 */
	public function add( string $source, string $label, string $email, string $outcome, string $detail = '' ): void {
		$entries   = $this->all();
		$entries[] = array(
			'time'    => time(),
			'source'  => sanitize_key( $source ),
			'label'   => mb_substr( sanitize_text_field( $label ), 0, 120 ),
			'email'   => self::mask( $email ),
			'outcome' => sanitize_key( $outcome ),
			'detail'  => mb_substr( sanitize_text_field( $detail ), 0, 200 ),
		);

		update_option( self::OPTION, array_slice( $entries, -self::SIZE ), false );
	}

	/**
	 * Every entry, oldest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? array_values( $entries ) : array();
	}

	/**
	 * Empty the log.
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Masks an address: ada@example.com becomes a***@example.com.
	 *
	 * @param string $email The address.
	 */
	public static function mask( string $email ): string {
		$at = strrpos( $email, '@' );
		if ( false === $at || 0 === $at ) {
			return '' === $email ? '' : '***';
		}

		return mb_substr( $email, 0, 1 ) . '***' . mb_substr( $email, $at );
	}
}
