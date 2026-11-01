<?php
/**
 * One answer from theblue.email.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * What a call to the platform returned, decoded and checked once.
 */
final class Api_Response {

	/**
	 * Constructor.
	 *
	 * @param int                  $status  HTTP status, 0 when the request never got an answer.
	 * @param array<string, mixed> $data    The decoded JSON body.
	 * @param string               $code    The platform's error code, or a local one.
	 * @param string               $message A message fit to show an administrator.
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $data = array(),
		public readonly string $code = '',
		public readonly string $message = ''
	) {}

	/**
	 * A 2xx answer.
	 */
	public function ok(): bool {
		return $this->status >= 200 && $this->status < 300;
	}

	/**
	 * The key is wrong or was revoked.
	 */
	public function unauthorised(): bool {
		return 401 === $this->status;
	}
}
