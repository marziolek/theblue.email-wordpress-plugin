<?php
/**
 * HTTP client for the theblue.email routes this plugin uses.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to /api/tbe/v1/site/* with the Website key. Every answer comes back
 * as an Api_Response; nothing here throws.
 */
class Client {

	/** Long enough for a slow host, short enough not to hold a visitor's form submission. */
	private const TIMEOUT = 10;

	/** No answer of these routes comes near this. */
	private const MAX_BODY = 1048576;

	/**
	 * Constructor.
	 *
	 * @param Connection $connection The stored key.
	 */
	public function __construct( private Connection $connection ) {}

	/**
	 * GET /site/account with a key that is not stored yet.
	 *
	 * @param string $key The raw key to try.
	 */
	public function account_for_key( string $key ): Api_Response {
		return $this->request( 'GET', 'site/account', array(), $key );
	}

	/**
	 * GET /site/account.
	 */
	public function account(): Api_Response {
		return $this->request( 'GET', 'site/account' );
	}

	/**
	 * GET /site/forms.
	 */
	public function forms(): Api_Response {
		return $this->request( 'GET', 'site/forms' );
	}

	/**
	 * POST /site/forms/{key}/allowed-domains.
	 *
	 * @param string $form_key The form's public key.
	 * @param string $host     The hostname to allow.
	 */
	public function allow_host( string $form_key, string $host ): Api_Response {
		return $this->request( 'POST', 'site/forms/' . $form_key . '/allowed-domains', array( 'host' => $host ) );
	}

	/**
	 * GET /site/forms/{key}/challenge.
	 *
	 * @param string $form_key The form's public key.
	 */
	public function challenge( string $form_key ): Api_Response {
		return $this->request( 'GET', 'site/forms/' . $form_key . '/challenge' );
	}

	/**
	 * POST /site/forms/{key}/subscribe.
	 *
	 * @param string                $form_key The form's public key.
	 * @param array<string, string> $fields   email, first_name, hp, pow_salt, pow_nonce, visitor_ip, page_url, country.
	 */
	public function subscribe( string $form_key, array $fields ): Api_Response {
		return $this->request( 'POST', 'site/forms/' . $form_key . '/subscribe', $fields );
	}

	/**
	 * One request. A 401 on the stored key marks the connection revoked.
	 *
	 * @param string               $method GET or POST.
	 * @param string               $path   Relative to /api/tbe/v1/.
	 * @param array<string, mixed> $body   JSON body for POST.
	 * @param string|null          $key    A key to use instead of the stored one.
	 */
	protected function request( string $method, string $path, array $body = array(), ?string $key = null ): Api_Response {
		$use_key = $key ?? $this->connection->key();

		if ( '' === $use_key ) {
			return new Api_Response( 0, array(), 'tbef_not_connected', __( 'This site is not connected to theblue.email.', 'theblue-email-forms' ) );
		}

		if ( 1 !== preg_match( '#^site/[a-z0-9/_-]+$#', $path ) ) {
			return new Api_Response( 0, array(), 'tbef_bad_path', __( 'Invalid request.', 'theblue-email-forms' ) );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => self::TIMEOUT,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $use_key,
				'Accept'        => 'application/json',
			),
			'user-agent'  => 'theblue-email-forms/' . TBEF_VERSION . '; ' . home_url( '/' ),
		);

		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}

		$raw = wp_remote_request( TBEF_PLATFORM_URL . '/api/tbe/v1/' . $path, $args );

		if ( is_wp_error( $raw ) ) {
			return new Api_Response(
				0,
				array(),
				'tbef_unreachable',
				/* translators: %s: the error from the HTTP library. */
				sprintf( __( 'Could not reach theblue.email: %s', 'theblue-email-forms' ), $raw->get_error_message() )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $raw );
		$text   = (string) wp_remote_retrieve_body( $raw );

		if ( strlen( $text ) > self::MAX_BODY ) {
			return new Api_Response( $status, array(), 'tbef_too_large', __( 'theblue.email sent an unexpected answer.', 'theblue-email-forms' ) );
		}

		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( 401 === $status && null === $key ) {
			$this->connection->mark_revoked();
		}

		if ( $status >= 200 && $status < 300 ) {
			return new Api_Response( $status, $data );
		}

		$code    = isset( $data['code'] ) && is_string( $data['code'] ) ? sanitize_key( $data['code'] ) : 'tbef_http_' . $status;
		$message = isset( $data['message'] ) && is_string( $data['message'] )
			? sanitize_text_field( $data['message'] )
			: ( isset( $data['error'] ) && is_string( $data['error'] ) ? sanitize_text_field( $data['error'] ) : '' );

		if ( '' === $message ) {
			/* translators: %d: HTTP status code. */
			$message = sprintf( __( 'theblue.email answered with an error (%d).', 'theblue-email-forms' ), $status );
		}

		return new Api_Response( $status, $data, $code, $message );
	}
}
