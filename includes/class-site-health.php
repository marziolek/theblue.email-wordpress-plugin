<?php
/**
 * A Site Health test.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Tools, Site Health: is the site connected, and does theblue.email still
 * accept its key.
 */
class Site_Health {

	/**
	 * Constructor.
	 *
	 * @param Connection $connection The connection.
	 * @param Client     $client     The platform client.
	 */
	public function __construct( private Connection $connection, private Client $client ) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add' ) );
	}

	/**
	 * Add the test.
	 *
	 * @param array<string, mixed> $tests The tests.
	 * @return array<string, mixed>
	 */
	public function add( $tests ): array {
		$tests                                  = is_array( $tests ) ? $tests : array();
		$tests['direct']['tbef_connection'] = array(
			'label' => __( 'theblue.email connection', 'theblue-email-forms' ),
			'test'  => array( $this, 'run' ),
		);

		return $tests;
	}

	/**
	 * Run it.
	 *
	 * @return array<string, mixed>
	 */
	public function run(): array {
		$result = array(
			'label'       => __( 'This site is connected to theblue.email', 'theblue-email-forms' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'theblue.email', 'theblue-email-forms' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Forms and signups from Gravity Forms and Contact Form 7 reach your theblue.email account.', 'theblue-email-forms' ) . '</p>',
			'actions'     => '<p><a href="' . esc_url( Settings_Page::url() ) . '">' . esc_html__( 'Settings, theblue.email', 'theblue-email-forms' ) . '</a></p>',
			'test'        => 'tbef_connection',
		);

		if ( ! $this->connection->is_connected() ) {
			$result['label']       = __( 'This site is not connected to theblue.email', 'theblue-email-forms' );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( 'Connect a Website key to place forms and pass on signups.', 'theblue-email-forms' ) . '</p>';
			return $result;
		}

		$response = $this->client->account();

		if ( ! $response->ok() ) {
			$result['label']       = __( 'theblue.email does not answer this site', 'theblue-email-forms' );
			$result['status']      = 'critical';
			$result['description'] = '<p>' . esc_html( $response->message ) . '</p>';
			return $result;
		}

		$this->connection->save_account( $response->data );

		return $result;
	}
}
