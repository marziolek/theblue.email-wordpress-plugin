<?php
/**
 * Who submitted the form: the details the platform's limits need.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * The visitor's IP and country for a relayed signup.
 *
 * REMOTE_ADDR is the source of truth. A forwarding header is believed only
 * when the site owner says the site sits behind Cloudflare, by defining
 * TBEF_BEHIND_CLOUDFLARE, because any client can send that header. Other
 * proxies can supply the address through the tbef_visitor_ip filter.
 */
final class Visitor {

	/**
	 * The visitor's IP, or '' when none is known.
	 */
	public static function ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip     = $remote;

		if ( self::behind_cloudflare() && isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		/**
		 * Filters the visitor IP sent with a relayed signup.
		 *
		 * @param string $ip     The IP this plugin found.
		 * @param string $remote REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'tbef_visitor_ip', $ip, $remote );

		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
	}

	/**
	 * The visitor's country as two letters, only from Cloudflare.
	 */
	public static function country(): string {
		if ( ! self::behind_cloudflare() || ! isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return '';
		}

		$country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );

		return 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
	}

	/**
	 * Whether the site owner declared the site is behind Cloudflare.
	 */
	private static function behind_cloudflare(): bool {
		return defined( 'TBEF_BEHIND_CLOUDFLARE' ) && TBEF_BEHIND_CLOUDFLARE;
	}
}
