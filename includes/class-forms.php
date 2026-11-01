<?php
/**
 * The account's forms, cached on the site.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * The last list GET /site/forms returned. Kept in an option, not only a
 * transient, so a page with a form never waits on theblue.email to render:
 * the front end reads this copy, and only admin screens refresh it.
 */
class Forms {

	public const OPTION = 'tbef_forms';

	/** How long a copy is fresh enough for the editor. */
	public const MAX_AGE = 600;

	/**
	 * Constructor.
	 *
	 * @param Client $client The platform client.
	 */
	public function __construct( private Client $client ) {}

	/**
	 * Every form, as last fetched.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) && isset( $stored['forms'] ) && is_array( $stored['forms'] ) ? $stored['forms'] : array();
	}

	/**
	 * When the copy was fetched, as a Unix time, 0 when never.
	 */
	public function fetched_at(): int {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? (int) ( $stored['fetched_at'] ?? 0 ) : 0;
	}

	/**
	 * Whether the copy is older than MAX_AGE.
	 */
	public function is_stale(): bool {
		return time() - $this->fetched_at() > self::MAX_AGE;
	}

	/**
	 * One form by its public key.
	 *
	 * @param string $key The form's public key.
	 * @return array<string, mixed>|null
	 */
	public function find( string $key ): ?array {
		foreach ( $this->all() as $form ) {
			if ( ( $form['public_key'] ?? '' ) === $key ) {
				return $form;
			}
		}

		return null;
	}

	/**
	 * Fetch the list again. On failure the old copy stays.
	 */
	public function refresh(): Api_Response {
		$response = $this->client->forms();

		if ( $response->ok() ) {
			$this->store( is_array( $response->data['data'] ?? null ) ? $response->data['data'] : array() );
		}

		return $response;
	}

	/**
	 * Refresh only when the copy is stale.
	 */
	public function refresh_if_stale(): ?Api_Response {
		return $this->is_stale() ? $this->refresh() : null;
	}

	/**
	 * Keep one changed form's allowed domains without fetching the whole list.
	 *
	 * @param string   $key     The form's public key.
	 * @param string[] $domains Its allowed domains.
	 */
	public function update_domains( string $key, array $domains ): void {
		$forms = $this->all();
		foreach ( $forms as $i => $form ) {
			if ( ( $form['public_key'] ?? '' ) === $key ) {
				$forms[ $i ]['allowed_domains'] = self::clean_domains( $domains );
			}
		}

		update_option(
			self::OPTION,
			array(
				'forms'      => $forms,
				'fetched_at' => $this->fetched_at(),
			),
			false
		);
	}

	/**
	 * Forget the copy.
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Whether this site's hostname is on the form's allowed domains.
	 *
	 * @param array<string, mixed> $form One form.
	 */
	public static function allows_this_site( array $form ): bool {
		$host = self::site_host();

		return '' !== $host && in_array( $host, (array) ( $form['allowed_domains'] ?? array() ), true );
	}

	/**
	 * This site's hostname, as a browser sends it in the Origin header.
	 */
	public static function site_host(): string {
		return strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}

	/**
	 * Keep only well-formed rows, with the fields this plugin reads.
	 *
	 * @param array<int, mixed> $rows What the platform sent.
	 */
	private function store( array $rows ): void {
		$forms = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['public_key'] ?? null ) || 1 !== preg_match( '/^[a-f0-9]{12}$/', $row['public_key'] ) ) {
				continue;
			}

			$forms[] = array(
				'public_key'      => $row['public_key'],
				'name'            => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'status'          => 'active' === ( $row['status'] ?? '' ) ? 'active' : 'draft',
				'fields'          => max( 1, min( 2, (int) ( $row['fields'] ?? 1 ) ) ),
				'list'            => sanitize_text_field( (string) ( $row['list']['name'] ?? '' ) ),
				'allowed_domains' => self::clean_domains( (array) ( $row['allowed_domains'] ?? array() ) ),
			);
		}

		update_option(
			self::OPTION,
			array(
				'forms'      => $forms,
				'fetched_at' => time(),
			),
			false
		);
	}

	/**
	 * Lower-case hostnames, nothing else.
	 *
	 * @param array<int|string, mixed> $domains Raw values.
	 * @return string[]
	 */
	private static function clean_domains( array $domains ): array {
		$clean = array();
		foreach ( $domains as $domain ) {
			if ( is_string( $domain ) && 1 === preg_match( '/^[a-z0-9.:-]{1,253}$/', strtolower( trim( $domain ) ) ) ) {
				$clean[] = strtolower( trim( $domain ) );
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
