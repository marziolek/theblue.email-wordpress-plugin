<?php
/**
 * The site's connection to a theblue.email account.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the Website key (encrypted) and what the platform said about the
 * account it belongs to. Everything lives in two options; nothing autoloads
 * except the small account summary every page with a form needs.
 */
final class Connection {

	public const OPTION_KEY     = 'tbef_api_key';
	public const OPTION_ACCOUNT = 'tbef_account';
	public const OPTION_STATE   = 'tbef_state';

	/** A Website key or a full key: tbe_ and 32 hex characters. */
	public const KEY_PATTERN = '/^tbe_[a-f0-9]{32}\z/';

	/**
	 * Whether a value looks like a key the platform issues.
	 *
	 * @param string $key The value.
	 */
	public static function is_valid_key_format( string $key ): bool {
		return 1 === preg_match( self::KEY_PATTERN, $key );
	}

	/**
	 * The raw key, or '' when not connected or the stored value cannot be read.
	 */
	public function key(): string {
		$stored = (string) get_option( self::OPTION_KEY, '' );
		if ( '' === $stored ) {
			return '';
		}

		$key = Crypto::decrypt( $stored );

		return self::is_valid_key_format( $key ) ? $key : '';
	}

	/**
	 * The first characters of the key, the way the platform's Integrations
	 * screen shows them, so an administrator can tell which key this is.
	 */
	public function key_prefix(): string {
		$key = $this->key();

		return '' === $key ? '' : substr( $key, 0, 8 );
	}

	/**
	 * Whether a key is stored and readable.
	 */
	public function is_connected(): bool {
		return '' !== $this->key();
	}

	/**
	 * Whether the platform refused the stored key on the last call.
	 */
	public function is_revoked(): bool {
		return 'revoked' === get_option( self::OPTION_STATE, '' );
	}

	/**
	 * Whether a key was stored but cannot be decrypted any more (the salts changed).
	 */
	public function is_unreadable(): bool {
		return '' !== (string) get_option( self::OPTION_KEY, '' ) && '' === $this->key();
	}

	/**
	 * The account summary from GET /site/account.
	 *
	 * @return array<string, string>
	 */
	public function account(): array {
		$account = get_option( self::OPTION_ACCOUNT, array() );

		return is_array( $account ) ? array_map( 'strval', $account ) : array();
	}

	/**
	 * Store a key and the account it belongs to.
	 *
	 * @param string               $key     The raw key.
	 * @param array<string, mixed> $account What GET /site/account returned.
	 */
	public function save( string $key, array $account ): bool {
		$encrypted = Crypto::encrypt( $key );
		if ( '' === $encrypted ) {
			return false;
		}

		update_option( self::OPTION_KEY, $encrypted, false );
		$this->save_account( $account );
		delete_option( self::OPTION_STATE );

		return true;
	}

	/**
	 * Keep only the fields this plugin reads, as strings.
	 *
	 * @param array<string, mixed> $account What GET /site/account returned.
	 */
	public function save_account( array $account ): void {
		$keep = array();
		foreach ( array( 'name', 'email', 'plan', 'embed_script_url' ) as $field ) {
			$keep[ $field ] = isset( $account[ $field ] ) && is_scalar( $account[ $field ] ) ? (string) $account[ $field ] : '';
		}

		update_option( self::OPTION_ACCOUNT, $keep, true );
	}

	/**
	 * Remember that the platform refused the key.
	 */
	public function mark_revoked(): void {
		update_option( self::OPTION_STATE, 'revoked', false );
	}

	/**
	 * Forget the key and everything learned with it.
	 */
	public function forget(): void {
		delete_option( self::OPTION_KEY );
		delete_option( self::OPTION_ACCOUNT );
		delete_option( self::OPTION_STATE );
	}

	/**
	 * The URL of tbe-embed.js. The platform names it with its current version;
	 * anything that does not live on the platform is ignored.
	 */
	public function embed_script_url(): string {
		$url = $this->account()['embed_script_url'] ?? '';

		if ( '' !== $url && 0 === strpos( $url, TBEF_PLATFORM_URL . '/' ) ) {
			return $url;
		}

		return TBEF_PLATFORM_URL . '/embed/tbe-embed.js';
	}
}
