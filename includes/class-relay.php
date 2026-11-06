<?php
/**
 * Sends a signup from Gravity Forms or Contact Form 7 to theblue.email.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * The same checks theblue.email runs on its own forms, in the same order,
 * first here and then again on the platform, which has the final word:
 *
 *  1. Consent       - the integration's opt-in field, when one is set.
 *  2. Honeypot      - the hidden field this plugin adds to the form.
 *  3. Address       - a valid email address of at most 254 characters.
 *  4. Rate limit    - ten signups an hour from one visitor IP to one target.
 *  5. Proof of work - solved by the visitor's browser (tbef-protect.js), checked by the platform.
 *  6. Platform      - FormGuard address and surge limits, Turnstile for a form under attack,
 *                     and double opt-in: nobody is subscribed until they confirm.
 *
 * A relay never blocks the form it came from: the visitor's message or entry
 * goes through whatever happens here. Every outcome is logged.
 */
class Relay {

	public const RATE_LIMIT = 10;

	/**
	 * Constructor.
	 *
	 * @param Client       $client The platform client.
	 * @param Activity_Log $log    The activity log.
	 */
	public function __construct( private Client $client, private Activity_Log $log ) {}

	/**
	 * Relay one signup.
	 *
	 * @param string               $source   gravityforms or contactform7.
	 * @param string               $label    Which form on this site.
	 * @param string               $form_key The theblue.email form's public key.
	 * @param array<string, mixed> $input    email, first_name, consent (bool), hp, pow_salt, pow_nonce, page_url,
	 *                                       and optionally visitor_ip and country as seen at submission.
	 */
	public function submit( string $source, string $label, string $form_key, array $input ): Relay_Result {
		if ( array_key_exists( 'consent', $input ) && ! $input['consent'] ) {
			return Relay_Result::skipped( 'no_consent' );
		}

		$email = trim( (string) ( $input['email'] ?? '' ) );

		if ( '' !== (string) ( $input['hp'] ?? '' ) ) {
			return $this->finish( $source, $label, $email, Relay_Result::refused( 'tbef_honeypot', '' ) );
		}

		if ( strlen( $email ) > 254 || ! is_email( $email ) ) {
			return $this->finish( $source, $label, $email, Relay_Result::refused( 'tbef_invalid_email', '' ) );
		}

		if ( 1 !== preg_match( '/^[a-f0-9]{12}$/', $form_key ) ) {
			return $this->finish( $source, $label, $email, Relay_Result::refused( 'tbef_no_target', '' ) );
		}

		// A Gravity Forms feed may run after the request, in the background, so
		// the integration can pass what it saw when the visitor submitted.
		$ip = array_key_exists( 'visitor_ip', $input ) ? (string) $input['visitor_ip'] : Visitor::ip();
		$ip = false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;

		$country = array_key_exists( 'country', $input ) ? (string) $input['country'] : Visitor::country();
		$country = 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';

		if ( $this->over_rate_limit( $form_key, $ip ) ) {
			return $this->finish( $source, $label, $email, Relay_Result::refused( 'tbef_rate_limited', '' ) );
		}

		$response = $this->client->subscribe(
			$form_key,
			array(
				// As the browser hashed it: trimmed, not otherwise changed.
				'email'      => $email,
				'first_name' => mb_substr( sanitize_text_field( (string) ( $input['first_name'] ?? '' ) ), 0, 100 ),
				'hp'         => '',
				'pow_salt'   => mb_substr( (string) ( $input['pow_salt'] ?? '' ), 0, 256 ),
				'pow_nonce'  => mb_substr( (string) ( $input['pow_nonce'] ?? '' ), 0, 32 ),
				'visitor_ip' => $ip,
				'page_url'   => esc_url_raw( (string) ( $input['page_url'] ?? '' ) ),
				'country'    => $country,
			)
		);

		if ( $response->ok() ) {
			return $this->finish( $source, $label, $email, Relay_Result::subscribed() );
		}

		$challenge = null;
		if ( 'tbe_challenge_required' === $response->code && is_array( $response->data['data']['challenge'] ?? null ) ) {
			$challenge = $response->data['data']['challenge'];
		}

		return $this->finish( $source, $label, $email, Relay_Result::refused( $response->code, $response->message, $challenge ) );
	}

	/**
	 * Count a signup against the visitor's IP and the target, ten an hour.
	 *
	 * @param string $form_key The target form.
	 * @param string $ip       The visitor's IP.
	 */
	private function over_rate_limit( string $form_key, string $ip ): bool {
		$key   = 'tbef_rate_' . md5( $form_key . '|' . $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return false;
	}

	/**
	 * Log the outcome and hand it back.
	 *
	 * @param string       $source The integration.
	 * @param string       $label  The form on this site.
	 * @param string       $email  The address.
	 * @param Relay_Result $result The outcome.
	 */
	private function finish( string $source, string $label, string $email, Relay_Result $result ): Relay_Result {
		$this->log->add( $source, $label, $email, $result->outcome, $result->code );

		/**
		 * Fires after a signup was relayed, or refused, from an integration.
		 *
		 * @param Relay_Result $result The outcome.
		 * @param string       $source gravityforms or contactform7.
		 */
		do_action( 'tbef_relayed', $result, $source );

		return $result;
	}
}
