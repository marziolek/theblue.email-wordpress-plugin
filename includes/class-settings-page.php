<?php
/**
 * Settings, theblue.email.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Connect and disconnect, the account's forms with their shortcodes and an
 * "Allow this site" button, which integrations are active, and the recent
 * signups they relayed. Every action is an admin-post form with a nonce and
 * the manage_options capability.
 */
class Settings_Page {

	public const SLUG = 'theblue-email-forms';

	private const ACTIONS = array( 'connect', 'disconnect', 'refresh', 'allow', 'clear_log' );

	/**
	 * Constructor.
	 *
	 * @param Connection   $connection The connection.
	 * @param Client       $client     The platform client.
	 * @param Forms        $forms      The cached forms.
	 * @param Embed        $embed      The embed printer.
	 * @param Activity_Log $log        The activity log.
	 */
	public function __construct(
		private Connection $connection,
		private Client $client,
		private Forms $forms,
		private Embed $embed,
		private Activity_Log $log
	) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TBEF_FILE ), array( $this, 'action_links' ) );

		foreach ( self::ACTIONS as $action ) {
			add_action( 'admin_post_tbef_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/**
	 * The page under Settings.
	 */
	public function menu(): void {
		$hook = add_options_page(
			__( 'theblue.email', 'theblue-email-forms' ),
			__( 'theblue.email', 'theblue-email-forms' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			add_action( 'admin_print_styles-' . $hook, array( $this, 'assets' ) );
		}
	}

	/**
	 * A Settings link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links The links.
	 * @return array<int|string, string>
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'theblue-email-forms' ) . '</a>' );

		return $links;
	}

	/**
	 * The page's URL.
	 */
	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	/**
	 * Styles and the copy button.
	 */
	public function assets(): void {
		wp_enqueue_style( 'tbef-settings', TBEF_URL . 'assets/admin/settings.css', array(), TBEF_VERSION );
		wp_enqueue_script( 'tbef-settings', TBEF_URL . 'assets/admin/settings.js', array(), TBEF_VERSION, array( 'in_footer' => true ) );
	}

	// ------------------------------------------------------------------
	// Actions
	// ------------------------------------------------------------------

	/**
	 * Connect with a pasted key.
	 */
	public function handle_connect(): void {
		$this->guard( 'connect' );

		$key = isset( $_POST['tbef_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tbef_key'] ) ) ) : '';

		if ( ! Connection::is_valid_key_format( $key ) ) {
			$this->back( 'error', __( 'That does not look like a theblue.email key. It starts with tbe_ followed by 32 letters and digits.', 'theblue-email-forms' ) );
		}

		$response = $this->client->account_for_key( $key );

		if ( ! $response->ok() ) {
			$this->back( 'error', $response->message );
		}

		if ( ! $this->connection->save( $key, $response->data ) ) {
			$this->back( 'error', __( 'This server cannot encrypt the key (it needs the sodium or OpenSSL PHP extension).', 'theblue-email-forms' ) );
		}

		$forms = $this->forms->refresh();
		$this->back(
			'success',
			$forms->ok()
				? __( 'Connected. Your forms are listed below.', 'theblue-email-forms' )
				: __( 'Connected, but the forms could not be loaded yet. Use Refresh forms.', 'theblue-email-forms' )
		);
	}

	/**
	 * Forget the key.
	 */
	public function handle_disconnect(): void {
		$this->guard( 'disconnect' );
		$this->connection->forget();
		$this->forms->clear();
		$this->back( 'success', __( 'Disconnected. Forms already placed on pages keep working; revoke the key in theblue.email if you no longer need it.', 'theblue-email-forms' ) );
	}

	/**
	 * Load the forms again.
	 */
	public function handle_refresh(): void {
		$this->guard( 'refresh' );
		$response = $this->forms->refresh();
		$this->back( $response->ok() ? 'success' : 'error', $response->ok() ? __( 'Forms refreshed.', 'theblue-email-forms' ) : $response->message );
	}

	/**
	 * Allow this site on one form.
	 */
	public function handle_allow(): void {
		$this->guard( 'allow' );
		$key    = isset( $_POST['tbef_form'] ) ? sanitize_key( wp_unslash( $_POST['tbef_form'] ) ) : '';
		$result = 1 === preg_match( '/^[a-f0-9]{12}$/', $key )
			? Rest::allow( $key, $this->client, $this->forms, $this->embed )
			: array(
				'ok'      => false,
				'message' => __( 'Form not found.', 'theblue-email-forms' ),
			);
		$this->back( $result['ok'] ? 'success' : 'error', $result['message'] );
	}

	/**
	 * Empty the activity log.
	 */
	public function handle_clear_log(): void {
		$this->guard( 'clear_log' );
		$this->log->clear();
		$this->back( 'success', __( 'Activity cleared.', 'theblue-email-forms' ) );
	}

	/**
	 * Capability and nonce for an action.
	 *
	 * @param string $action The action.
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'theblue-email-forms' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'tbef_' . $action );
	}

	/**
	 * Back to the page with a message.
	 *
	 * @param string $type    success or error.
	 * @param string $message The message.
	 * @return never
	 */
	private function back( string $type, string $message ): never {
		set_transient(
			'tbef_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( self::url() );
		exit;
	}

	// ------------------------------------------------------------------
	// Page
	// ------------------------------------------------------------------

	/**
	 * The page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->connection->is_connected() && ! $this->connection->is_revoked() ) {
			$this->forms->refresh_if_stale();
		}

		echo '<div class="wrap tbef-settings"><h1>' . esc_html__( 'theblue.email', 'theblue-email-forms' ) . '</h1>';

		$this->flash();

		if ( $this->connection->is_connected() ) {
			$this->connected_card();
			$this->forms_card();
			$this->integrations_card();
			$this->activity_card();
		} else {
			$this->connect_card();
		}

		echo '</div>';
	}

	/**
	 * The message an action left.
	 */
	private function flash(): void {
		$key    = 'tbef_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );

		if ( is_array( $notice ) && isset( $notice['message'] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error',
				esc_html( (string) $notice['message'] )
			);
		}
	}

	/**
	 * The opening tag and hidden fields of an action form.
	 *
	 * @param string $action The action.
	 */
	private function form_open( string $action ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tbef-inline">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'tbef_' . $action ) . '">';
		wp_nonce_field( 'tbef_' . $action );
	}

	/**
	 * Not connected yet.
	 */
	private function connect_card(): void {
		echo '<div class="tbef-card"><h2>' . esc_html__( 'Connect your theblue.email account', 'theblue-email-forms' ) . '</h2>';

		if ( $this->connection->is_unreadable() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The stored key can no longer be read, usually because the site\'s security keys changed. Paste the key again.', 'theblue-email-forms' ) . '</p></div>';
		}

		echo '<ol class="tbef-steps">';
		echo '<li>' . wp_kses_post(
			sprintf(
				/* translators: %s: link to theblue.email. */
				__( 'In %s, open Integrations and generate a <strong>Website key</strong>. It works on every plan.', 'theblue-email-forms' ),
				'<a href="' . esc_url( TBEF_PLATFORM_URL . '/app/integrations' ) . '" target="_blank" rel="noopener">theblue.email</a>'
			)
		) . '</li>';
		echo '<li>' . esc_html__( 'Paste it here and connect.', 'theblue-email-forms' ) . '</li>';
		echo '</ol>';

		$this->form_open( 'connect' );
		echo '<label for="tbef_key" class="screen-reader-text">' . esc_html__( 'Website key', 'theblue-email-forms' ) . '</label>';
		echo '<input type="password" id="tbef_key" name="tbef_key" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="tbe_..." required pattern="tbe_[a-f0-9]{32}"> ';
		submit_button( __( 'Connect', 'theblue-email-forms' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<p class="description">' . esc_html__( 'The key is stored encrypted and is never shown again in full. A Website key can only read your forms, allow this site on them and pass on signups; it cannot read your subscribers or send email.', 'theblue-email-forms' ) . '</p>';
		echo '</div>';
	}

	/**
	 * The account this site is connected to.
	 */
	private function connected_card(): void {
		$account = $this->connection->account();

		echo '<div class="tbef-card"><h2>' . esc_html__( 'Connection', 'theblue-email-forms' ) . '</h2>';

		if ( $this->connection->is_revoked() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'theblue.email no longer accepts this key. It may have been revoked. Disconnect and connect again with a new Website key.', 'theblue-email-forms' ) . '</p></div>';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Account', 'theblue-email-forms' ) . '</th><td>' . esc_html( trim( ( $account['name'] ?? '' ) . ' ' . ( '' !== ( $account['email'] ?? '' ) ? '(' . $account['email'] . ')' : '' ) ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Plan', 'theblue-email-forms' ) . '</th><td>' . esc_html( ucfirst( $account['plan'] ?? '' ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Key', 'theblue-email-forms' ) . '</th><td><code>' . esc_html( $this->connection->key_prefix() ) . '…</code></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'This site', 'theblue-email-forms' ) . '</th><td><code>' . esc_html( Forms::site_host() ) . '</code></td></tr>';
		echo '</tbody></table>';

		$this->form_open( 'refresh' );
		submit_button( __( 'Refresh forms', 'theblue-email-forms' ), 'secondary', 'submit', false );
		echo '</form> ';
		$this->form_open( 'disconnect' );
		submit_button( __( 'Disconnect', 'theblue-email-forms' ), 'delete', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * The account's forms.
	 */
	private function forms_card(): void {
		$forms = $this->forms->all();

		echo '<div class="tbef-card"><h2>' . esc_html__( 'Your forms', 'theblue-email-forms' ) . '</h2>';
		echo '<p>' . esc_html__( 'Add the "theblue.email form" block to a page, or paste a shortcode. A form shows only on sites on its allowed domains.', 'theblue-email-forms' ) . '</p>';

		if ( array() === $forms ) {
			echo '<p><em>' . esc_html__( 'No forms yet. Create one under Forms in theblue.email, then refresh.', 'theblue-email-forms' ) . '</em></p></div>';
			return;
		}

		echo '<table class="widefat striped tbef-forms"><thead><tr>';
		echo '<th>' . esc_html__( 'Form', 'theblue-email-forms' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'theblue-email-forms' ) . '</th>';
		echo '<th>' . esc_html__( 'List', 'theblue-email-forms' ) . '</th>';
		echo '<th>' . esc_html__( 'This site', 'theblue-email-forms' ) . '</th>';
		echo '<th>' . esc_html__( 'Shortcode', 'theblue-email-forms' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $forms as $form ) {
			$key       = (string) $form['public_key'];
			$shortcode = Shortcode::for_key( $key );

			echo '<tr><td><strong>' . esc_html( (string) $form['name'] ) . '</strong></td>';
			echo '<td>' . ( 'active' === $form['status'] ? esc_html__( 'Live', 'theblue-email-forms' ) : esc_html__( 'Draft', 'theblue-email-forms' ) ) . '</td>';
			echo '<td>' . esc_html( '' !== $form['list'] ? (string) $form['list'] : '-' ) . '</td><td>';

			if ( Forms::allows_this_site( $form ) ) {
				echo '<span class="tbef-ok">' . esc_html__( 'Allowed', 'theblue-email-forms' ) . '</span>';
			} else {
				$this->form_open( 'allow' );
				echo '<input type="hidden" name="tbef_form" value="' . esc_attr( $key ) . '">';
				submit_button( __( 'Allow this site', 'theblue-email-forms' ), 'secondary small', 'submit', false );
				echo '</form>';
			}

			echo '</td><td><code class="tbef-code">' . esc_html( $shortcode ) . '</code> <button type="button" class="button button-small tbef-copy" data-copy="' . esc_attr( $shortcode ) . '">' . esc_html__( 'Copy', 'theblue-email-forms' ) . '</button></td></tr>';
		}

		echo '</tbody></table>';

		$fetched = $this->forms->fetched_at();
		if ( $fetched > 0 ) {
			/* translators: %s: how long ago, e.g. 5 mins. */
			echo '<p class="description">' . esc_html( sprintf( __( 'Loaded %s ago.', 'theblue-email-forms' ), human_time_diff( $fetched ) ) ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Gravity Forms and Contact Form 7.
	 */
	private function integrations_card(): void {
		echo '<div class="tbef-card"><h2>' . esc_html__( 'Signups from other form plugins', 'theblue-email-forms' ) . '</h2>';
		echo '<p>' . esc_html__( 'Send the people who fill in a contact, order or registration form to a theblue.email form. Each signup gets the same protection as a theblue.email form: a hidden honeypot field, an invisible proof-of-work check in the visitor\'s browser, rate limits, and double opt-in, so nobody joins a list without confirming.', 'theblue-email-forms' ) . '</p><ul class="tbef-integrations">';

		$rows = array(
			array(
				'Gravity Forms',
				class_exists( 'GFForms' ),
				__( 'Open a form, then Settings, theblue.email, and add a feed. Use a consent checkbox as the feed condition.', 'theblue-email-forms' ),
			),
			array(
				'Contact Form 7',
				defined( 'WPCF7_VERSION' ),
				__( 'Edit a contact form and open the theblue.email tab. Add an [acceptance newsletter optional] field and name it as the consent field.', 'theblue-email-forms' ),
			),
		);

		foreach ( $rows as $row ) {
			echo '<li><strong>' . esc_html( $row[0] ) . '</strong>: ';
			echo $row[1]
				? '<span class="tbef-ok">' . esc_html__( 'active', 'theblue-email-forms' ) . '</span> ' . esc_html( $row[2] )
				: esc_html__( 'not installed', 'theblue-email-forms' );
			echo '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * The last relayed signups.
	 */
	private function activity_card(): void {
		$entries = array_reverse( $this->log->all() );

		echo '<div class="tbef-card"><h2>' . esc_html__( 'Recent signups from other form plugins', 'theblue-email-forms' ) . '</h2>';

		if ( array() === $entries ) {
			echo '<p><em>' . esc_html__( 'Nothing yet.', 'theblue-email-forms' ) . '</em></p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'When', 'theblue-email-forms' ), __( 'Form', 'theblue-email-forms' ), __( 'Address', 'theblue-email-forms' ), __( 'Result', 'theblue-email-forms' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $entries as $e ) {
			$source = 'gravityforms' === ( $e['source'] ?? '' ) ? 'Gravity Forms' : 'Contact Form 7';
			echo '<tr><td>' . esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) $e['time'] ) ) . '</td>';
			echo '<td>' . esc_html( $source . ': ' . (string) ( $e['label'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $e['email'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( self::describe( (string) ( $e['outcome'] ?? '' ), (string) ( $e['detail'] ?? '' ) ) ) . '</td></tr>';
		}

		echo '</tbody></table><p>';
		$this->form_open( 'clear_log' );
		submit_button( __( 'Clear', 'theblue-email-forms' ), 'secondary small', 'submit', false );
		echo '</form></p></div>';
	}

	/**
	 * A result in words.
	 *
	 * @param string $outcome subscribed, refused or skipped.
	 * @param string $code    The reason code.
	 */
	public static function describe( string $outcome, string $code ): string {
		if ( 'subscribed' === $outcome ) {
			return __( 'Sent, confirmation email on its way', 'theblue-email-forms' );
		}

		$reasons = array(
			'tbef_honeypot'          => __( 'Refused: the hidden field was filled in (a bot)', 'theblue-email-forms' ),
			'tbe_honeypot'           => __( 'Refused: the hidden field was filled in (a bot)', 'theblue-email-forms' ),
			'tbef_invalid_email'     => __( 'Refused: not a valid address', 'theblue-email-forms' ),
			'tbe_invalid_email'      => __( 'Refused: not a valid address', 'theblue-email-forms' ),
			'tbef_rate_limited'      => __( 'Refused: too many signups from one visitor', 'theblue-email-forms' ),
			'tbe_rate_limited'       => __( 'Refused: too many attempts', 'theblue-email-forms' ),
			'tbe_pow_required'       => __( 'Refused: the browser check was missing or out of date (JavaScript off, or the page was open for over an hour)', 'theblue-email-forms' ),
			'tbe_challenge_required' => __( 'Waiting: the form is under attack, so the visitor was asked to finish a check on theblue.email', 'theblue-email-forms' ),
			'tbe_form_not_found'     => __( 'Refused: the theblue.email form is a draft or was deleted', 'theblue-email-forms' ),
			'tbe_auth_invalid'       => __( 'Refused: the key was revoked', 'theblue-email-forms' ),
			'tbe_auth_suspended'     => __( 'Refused: the theblue.email subscription has ended', 'theblue-email-forms' ),
			'tbef_unreachable'       => __( 'Failed: theblue.email could not be reached', 'theblue-email-forms' ),
			'tbef_not_connected'     => __( 'Failed: this site is not connected', 'theblue-email-forms' ),
			'tbef_no_target'         => __( 'Failed: no theblue.email form is chosen', 'theblue-email-forms' ),
		);

		/* translators: %s: reason code. */
		return $reasons[ $code ] ?? sprintf( __( 'Refused (%s)', 'theblue-email-forms' ), $code );
	}
}
