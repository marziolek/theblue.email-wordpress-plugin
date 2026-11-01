<?php
/**
 * What became of a relayed signup.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribed (the confirmation email is on its way), refused, or skipped.
 */
final class Relay_Result {

	/**
	 * Constructor.
	 *
	 * @param string                    $outcome   subscribed, refused or skipped.
	 * @param string                    $code      Why, when not subscribed.
	 * @param string                    $message   The platform's message, if any.
	 * @param array<string, mixed>|null $challenge The Turnstile hand-off for a form under attack.
	 */
	private function __construct(
		public readonly string $outcome,
		public readonly string $code = '',
		public readonly string $message = '',
		public readonly ?array $challenge = null
	) {}

	/**
	 * The platform accepted it.
	 */
	public static function subscribed(): self {
		return new self( 'subscribed' );
	}

	/**
	 * A check here or on the platform said no.
	 *
	 * @param string                    $code      Why.
	 * @param string                    $message   The platform's message.
	 * @param array<string, mixed>|null $challenge The Turnstile hand-off.
	 */
	public static function refused( string $code, string $message, ?array $challenge = null ): self {
		return new self( 'refused', $code, $message, $challenge );
	}

	/**
	 * Nothing to send, e.g. the visitor did not tick the opt-in.
	 *
	 * @param string $code Why.
	 */
	public static function skipped( string $code ): self {
		return new self( 'skipped', $code );
	}

	/**
	 * Whether the signup reached theblue.email.
	 */
	public function is_subscribed(): bool {
		return 'subscribed' === $this->outcome;
	}
}
