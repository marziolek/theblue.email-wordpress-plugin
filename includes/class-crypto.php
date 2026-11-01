<?php
/**
 * Encrypts the Website key at rest.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Symmetric encryption with a key derived from the site's own salts.
 *
 * This keeps the Website key out of database dumps and backups in clear. It
 * does not protect it from code running on the site, which can derive the
 * same key: that is why the platform gives this plugin a key that can do
 * nothing but forms.
 */
final class Crypto {

	private const PREFIX_SODIUM  = 's1:';
	private const PREFIX_OPENSSL = 'o1:';

	/**
	 * Encrypts a value. Returns '' when no cipher is available.
	 *
	 * @param string $plain The value.
	 */
	public static function encrypt( string $plain ): string {
		$key = self::key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary ciphertext stored as text, not obfuscation.
			return self::PREFIX_SODIUM . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false === $cipher ) {
				return '';
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary ciphertext stored as text, not obfuscation.
			return self::PREFIX_OPENSSL . base64_encode( $iv . $tag . $cipher );
		}

		return '';
	}

	/**
	 * Decrypts a value from encrypt(). Returns '' on any failure, which is what
	 * a changed salt looks like: the site has to connect again.
	 *
	 * @param string $stored The stored value.
	 */
	public static function decrypt( string $stored ): string {
		$key = self::key();

		if ( 0 === strpos( $stored, self::PREFIX_SODIUM ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see encrypt().
			$raw = base64_decode( substr( $stored, strlen( self::PREFIX_SODIUM ) ), true );
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				$key
			);
			return false === $plain ? '' : $plain;
		}

		if ( 0 === strpos( $stored, self::PREFIX_OPENSSL ) && function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see encrypt().
			$raw = base64_decode( substr( $stored, strlen( self::PREFIX_OPENSSL ) ), true );
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return '';
			}
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $plain ? '' : $plain;
		}

		return '';
	}

	/**
	 * 32 bytes derived from the site's auth salt.
	 */
	private static function key(): string {
		return hash_hmac( 'sha256', 'theblue-email-forms', wp_salt( 'auth' ), true );
	}
}
