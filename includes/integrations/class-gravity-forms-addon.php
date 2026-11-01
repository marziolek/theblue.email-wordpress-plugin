<?php
/**
 * The Gravity Forms feed add-on.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Integrations;

use TheBlueEmail\Forms\Plugin;
use TheBlueEmail\Forms\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * A theblue.email feed on a Gravity Forms form: when an entry is submitted
 * (and the feed's condition, typically a consent checkbox, is met), the
 * mapped address and first name are sent to a theblue.email form, which runs
 * its own protections and sends the double opt-in email.
 *
 * Built on GFFeedAddOn, which Gravity Forms requires of add-ons listed in its
 * directory. Loaded only after the framework, by Gravity_Forms::register_addon().
 */
class Gravity_Forms_Addon extends \GFFeedAddOn {

	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore -- property names are the Add-On Framework's.

	/**
	 * Add-on version.
	 *
	 * @var string
	 */
	protected $_version = TBEF_VERSION;

	/**
	 * Lowest Gravity Forms version with the settings API used here.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * Slug.
	 *
	 * @var string
	 */
	protected $_slug = Gravity_Forms::SLUG;

	/**
	 * Relative path to the main plugin file.
	 *
	 * @var string
	 */
	protected $_path = 'theblue-email-forms/theblue-email-forms.php';

	/**
	 * Full path to the main plugin file.
	 *
	 * @var string
	 */
	protected $_full_path = TBEF_FILE;

	/**
	 * Title.
	 *
	 * @var string
	 */
	protected $_title = 'theblue.email Forms';

	/**
	 * Short title.
	 *
	 * @var string
	 */
	protected $_short_title = 'theblue.email';

	/**
	 * Who may edit feeds.
	 *
	 * @var string
	 */
	protected $_capabilities_form_settings = 'gravityforms_edit_forms';

	/**
	 * The one instance.
	 *
	 * @var self|null
	 */
	private static $_instance = null;

	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Entries waiting for the Turnstile hand-off in their confirmation.
	 *
	 * @var array<int, array<string, string>>
	 */
	private static array $challenges = array();

	/**
	 * The plugin's real folder, whatever it was installed as.
	 */
	public function __construct() {
		$this->_path = plugin_basename( TBEF_FILE );
		parent::__construct();
	}

	/**
	 * The instance the framework asks for.
	 */
	public static function get_instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Hooks for the front end.
	 */
	public function init(): void {
		parent::init();
		add_filter( 'gform_confirmation', array( $this, 'add_challenge_to_confirmation' ), 20, 3 );
	}

	/**
	 * The menu icon in the form settings.
	 */
	public function get_menu_icon(): string {
		return 'dashicons-email-alt';
	}

	/**
	 * Feeds can be made only once the site is connected.
	 */
	public function can_create_feed(): bool {
		return Plugin::instance()->connection()->is_connected();
	}

	/**
	 * What the feed list says while the site is not connected.
	 */
	public function configure_addon_message(): string {
		return sprintf(
			/* translators: %s: link to the plugin's settings page. */
			esc_html__( 'Connect this site to theblue.email first: %s', 'theblue-email-forms' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . Settings_Page::SLUG ) ) . '">' . esc_html__( 'Settings, theblue.email', 'theblue-email-forms' ) . '</a>'
		);
	}

	/**
	 * The feed settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function feed_settings_fields(): array {
		return array(
			array(
				'title'  => esc_html__( 'theblue.email signups', 'theblue-email-forms' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'theblue-email-forms' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
					),
					array(
						'name'     => 'tbeForm',
						'label'    => esc_html__( 'theblue.email form', 'theblue-email-forms' ),
						'type'     => 'select',
						'required' => true,
						'choices'  => $this->form_choices(),
						'tooltip'  => esc_html__( 'Signups join this form\'s list and get its confirmation email, as if they had used the form itself. The form must be live.', 'theblue-email-forms' ),
					),
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Fields', 'theblue-email-forms' ),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'email',
								'label'      => esc_html__( 'Email address', 'theblue-email-forms' ),
								'required'   => true,
								'field_type' => array( 'email', 'hidden', 'text' ),
							),
							array(
								'name'     => 'first_name',
								'label'    => esc_html__( 'First name', 'theblue-email-forms' ),
								'required' => false,
							),
						),
					),
					array(
						'name'           => 'optinCondition',
						'label'          => esc_html__( 'Consent', 'theblue-email-forms' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Send to theblue.email only when a condition is met', 'theblue-email-forms' ),
						'instructions'   => esc_html__( 'Send to theblue.email if', 'theblue-email-forms' ),
						'tooltip'        => esc_html__( 'On a contact or order form, add a checkbox such as "Send me the newsletter" and use it here. Everyone gets a confirmation email and joins only once they confirm.', 'theblue-email-forms' ),
					),
				),
			),
		);
	}

	/**
	 * The theblue.email forms to choose from.
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	private function form_choices(): array {
		$forms = Plugin::instance()->forms();
		if ( is_admin() ) {
			$forms->refresh_if_stale();
		}

		$choices = array(
			array(
				'label' => esc_html__( 'Choose a form', 'theblue-email-forms' ),
				'value' => '',
			),
		);

		foreach ( $forms->all() as $form ) {
			$label = (string) $form['name'];
			if ( 'active' !== $form['status'] ) {
				/* translators: %s: form name. */
				$label = sprintf( __( '%s (draft)', 'theblue-email-forms' ), $label );
			}
			$choices[] = array(
				'label' => esc_html( $label ),
				'value' => (string) $form['public_key'],
			);
		}

		return $choices;
	}

	/**
	 * The columns of the feed list.
	 *
	 * @return array<string, string>
	 */
	public function feed_list_columns(): array {
		return array(
			'feedName' => esc_html__( 'Name', 'theblue-email-forms' ),
			'tbeForm'  => esc_html__( 'theblue.email form', 'theblue-email-forms' ),
		);
	}

	/**
	 * The form's name in the feed list.
	 *
	 * @param array<string, mixed> $feed The feed.
	 */
	public function get_column_value_tbeForm( $feed ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- name required by the Add-On Framework.
		$key  = (string) ( $feed['meta']['tbeForm'] ?? '' );
		$form = Plugin::instance()->forms()->find( $key );

		return esc_html( null !== $form ? (string) $form['name'] : $key );
	}

	/**
	 * Send the entry's signup to theblue.email.
	 *
	 * @param array<string, mixed> $feed  The feed.
	 * @param array<string, mixed> $entry The entry.
	 * @param array<string, mixed> $form  The form.
	 * @return array<string, mixed>
	 */
	public function process_feed( $feed, $entry, $form ) {
		if ( 'spam' === ( $entry['status'] ?? '' ) ) {
			return $entry;
		}

		$entry_id = (int) $entry['id'];
		$target   = 'gf-' . (int) $feed['id'];
		$stored   = json_decode( (string) gform_get_meta( $entry_id, Gravity_Forms::META ), true );
		$stored   = is_array( $stored ) ? $stored : array();
		$pow      = is_array( $stored['pow'][ $target ] ?? null ) ? $stored['pow'][ $target ] : array();

		$result = Plugin::instance()->relay()->submit(
			'gravityforms',
			(string) ( $form['title'] ?? '' ),
			(string) ( $feed['meta']['tbeForm'] ?? '' ),
			array(
				'email'      => (string) $this->get_field_value( $form, $entry, (string) ( $feed['meta']['mappedFields_email'] ?? '' ) ),
				'first_name' => (string) $this->get_field_value( $form, $entry, (string) ( $feed['meta']['mappedFields_first_name'] ?? '' ) ),
				'hp'         => (string) ( $stored['hp'] ?? '' ),
				'pow_salt'   => (string) ( $pow['salt'] ?? '' ),
				'pow_nonce'  => (string) ( $pow['nonce'] ?? '' ),
				'page_url'   => (string) ( $entry['source_url'] ?? '' ),
				'visitor_ip' => (string) ( $stored['visitor_ip'] ?? '' ),
				'country'    => (string) ( $stored['country'] ?? '' ),
			)
		);

		$this->forget_submission( $entry_id, $target, $stored );

		if ( $result->is_subscribed() ) {
			$this->add_note( $entry_id, esc_html__( 'Sent to theblue.email. The confirmation email is on its way.', 'theblue-email-forms' ), 'success' );
			return $entry;
		}

		if ( null !== $result->challenge && 0 === strpos( (string) ( $result->challenge['url'] ?? '' ), TBEF_PLATFORM_URL . '/' ) ) {
			self::$challenges[ $entry_id ] = array_map( 'strval', $result->challenge ) + array( 'message' => $result->message );
		}

		$this->add_note(
			$entry_id,
			/* translators: 1: reason code, 2: message from theblue.email. */
			esc_html( sprintf( __( 'Not sent to theblue.email (%1$s). %2$s', 'theblue-email-forms' ), $result->code, $result->message ) ),
			'error'
		);

		return $entry;
	}

	/**
	 * When the form is under attack, theblue.email asks the visitor to finish
	 * on its own page. The confirmation carries the button that opens it.
	 *
	 * @param string|array<string, mixed> $confirmation The confirmation.
	 * @param array<string, mixed>        $form         The form.
	 * @param array<string, mixed>        $entry        The entry.
	 * @return string|array<string, mixed>
	 */
	public function add_challenge_to_confirmation( $confirmation, $form, $entry ) {
		$entry_id = is_array( $entry ) ? (int) ( $entry['id'] ?? 0 ) : 0;

		if ( ! is_string( $confirmation ) || ! isset( self::$challenges[ $entry_id ] ) ) {
			return $confirmation;
		}

		$c = self::$challenges[ $entry_id ];
		unset( self::$challenges[ $entry_id ] );

		return $confirmation . sprintf(
			'<div class="tbef-challenge"><p>%1$s</p><form method="post" action="%2$s" target="_blank" rel="noopener"><input type="hidden" name="t" value="%3$s"><button type="submit" class="tbef-challenge__button">%4$s</button></form><p>%5$s</p></div>',
			esc_html( $c['message'] ?? '' ),
			esc_url( $c['url'] ?? '' ),
			esc_attr( $c['token'] ?? '' ),
			esc_html( $c['button'] ?? __( 'Continue', 'theblue-email-forms' ) ),
			esc_html( $c['note'] ?? '' )
		);
	}

	/**
	 * Drop this feed's part of the stored submission, and the whole of it once
	 * no feed is left to use it: it holds the visitor's IP. Kept per target so
	 * it works when feeds run one by one in the background.
	 *
	 * @param int                  $entry_id The entry.
	 * @param string               $target   This feed's target.
	 * @param array<string, mixed> $stored   The stored details.
	 */
	private function forget_submission( int $entry_id, string $target, array $stored ): void {
		$pow = is_array( $stored['pow'] ?? null ) ? $stored['pow'] : array();
		unset( $pow[ $target ] );

		if ( array() === $pow ) {
			gform_delete_meta( $entry_id, Gravity_Forms::META );
			return;
		}

		$stored['pow'] = $pow;
		gform_update_meta( $entry_id, Gravity_Forms::META, (string) wp_json_encode( $stored ) );
	}
}
