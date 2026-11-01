<?php
/**
 * The plugin's own REST routes, under /wp-json/tbef/v1/.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * GET  /forms             The cached forms, for the block editor (edit_posts).
 * POST /forms/{key}/allow Allow this site on a form (manage_options).
 * GET  /challenge         A proof-of-work challenge for a target, for tbef-protect.js (public).
 *
 * The key never leaves the server: the editor and the visitor's browser talk
 * to this site, and this site talks to theblue.email.
 */
class Rest {

	public const NS = 'tbef/v1';

	/** Challenges one visitor IP may ask for in ten minutes. */
	public const CHALLENGE_LIMIT = 30;

	/**
	 * Constructor.
	 *
	 * @param Forms      $forms      The cached forms.
	 * @param Client     $client     The platform client.
	 * @param Connection $connection The connection.
	 * @param Embed      $embed      The embed printer.
	 */
	public function __construct(
		private Forms $forms,
		private Client $client,
		private Connection $connection,
		private Embed $embed
	) {}

	/**
	 * Register the routes.
	 */
	public function register(): void {
		register_rest_route(
			self::NS,
			'/forms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_forms' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms/(?P<key>[a-f0-9]{12})/allow',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'allow_site' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::NS,
			'/challenge',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_challenge' ),
				// Public on purpose: visitors filling in a form are not logged in.
				// It reveals nothing but a puzzle, and is rate limited below.
				'permission_callback' => '__return_true',
				'args'                => array(
					'target' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * GET /forms.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_forms( \WP_REST_Request $request ): \WP_REST_Response {
		$error = '';

		if ( $this->connection->is_connected() ) {
			$response = $request->get_param( 'refresh' ) ? $this->forms->refresh() : $this->forms->refresh_if_stale();
			if ( null !== $response && ! $response->ok() ) {
				$error = $response->message;
			}
		}

		$forms = array_map(
			static fn( array $form ): array => $form + array( 'allows_site' => Forms::allows_this_site( $form ) ),
			$this->forms->all()
		);

		return new \WP_REST_Response(
			array(
				'connected'    => $this->connection->is_connected(),
				'can_manage'   => current_user_can( 'manage_options' ),
				'settings_url' => admin_url( 'options-general.php?page=' . Settings_Page::SLUG ),
				'site_host'    => Forms::site_host(),
				'forms'        => $forms,
				'error'        => $error,
			),
			200
		);
	}

	/**
	 * POST /forms/{key}/allow.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function allow_site( \WP_REST_Request $request ): \WP_REST_Response {
		$result = self::allow( (string) $request->get_param( 'key' ), $this->client, $this->forms, $this->embed );

		return new \WP_REST_Response( $result, $result['ok'] ? 200 : 400 );
	}

	/**
	 * Allow this site's hostname on a form. Shared with the settings page.
	 *
	 * @param string $key    The form's public key.
	 * @param Client $client The platform client.
	 * @param Forms  $forms  The cached forms.
	 * @param Embed  $embed  The embed printer.
	 * @return array{ok: bool, message: string, allowed_domains: string[]}
	 */
	public static function allow( string $key, Client $client, Forms $forms, Embed $embed ): array {
		$host = Forms::site_host();

		if ( '' === $host ) {
			return array(
				'ok'              => false,
				'message'         => __( 'This site has no hostname to allow.', 'theblue-email-forms' ),
				'allowed_domains' => array(),
			);
		}

		$response = $client->allow_host( $key, $host );

		if ( ! $response->ok() ) {
			return array(
				'ok'              => false,
				'message'         => $response->message,
				'allowed_domains' => array(),
			);
		}

		$domains = array_values( array_map( 'strval', (array) ( $response->data['allowed_domains'] ?? array() ) ) );
		$forms->update_domains( $key, $domains );
		$embed->forget_unallowed( $key );

		return array(
			'ok'              => true,
			/* translators: %s: this site's hostname. */
			'message'         => sprintf( __( '%s can now show this form.', 'theblue-email-forms' ), $host ),
			'allowed_domains' => $domains,
		);
	}

	/**
	 * GET /challenge?target=gf-12.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_challenge( \WP_REST_Request $request ): \WP_REST_Response {
		$headers = array( 'Cache-Control' => 'no-store, max-age=0' );

		if ( $this->over_limit( Visitor::ip() ) ) {
			return new \WP_REST_Response( array( 'pow' => null ), 429, $headers );
		}

		$key = Targets::resolve( (string) $request->get_param( 'target' ) );
		if ( '' === $key ) {
			return new \WP_REST_Response( array( 'pow' => null ), 404, $headers );
		}

		$response = $this->client->challenge( $key );
		$pow      = $response->ok() ? ( $response->data['pow'] ?? null ) : null;

		if ( is_array( $pow ) && is_string( $pow['salt'] ?? null ) && is_int( $pow['bits'] ?? null ) && $pow['bits'] >= 0 && $pow['bits'] <= 32 ) {
			$pow = array(
				'salt' => $pow['salt'],
				'bits' => $pow['bits'],
			);
		} else {
			$pow = null;
		}

		return new \WP_REST_Response( array( 'pow' => $pow ), $response->ok() ? 200 : 502, $headers );
	}

	/**
	 * Count a challenge against the visitor's IP.
	 *
	 * @param string $ip The visitor's IP.
	 */
	private function over_limit( string $ip ): bool {
		$key   = 'tbef_chal_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::CHALLENGE_LIMIT ) {
			return true;
		}

		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

		return false;
	}
}
