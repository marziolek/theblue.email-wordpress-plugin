<?php
/**
 * Minimal WordPress classes for unit tests.
 *
 * @package TheBlueEmail\Forms
 */

// phpcs:disable

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private string $code = '', private string $message = '', private mixed $data = null ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
		public function get_error_data(): mixed { return $this->data; }
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		public array $params = array();
		public function get_param( string $key ): mixed { return $this->params[ $key ] ?? null; }
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct( public mixed $data = null, public int $status = 200, public array $headers = array() ) {}
		public function get_data(): mixed { return $this->data; }
		public function get_status(): int { return $this->status; }
		public function get_headers(): array { return $this->headers; }
	}
}
