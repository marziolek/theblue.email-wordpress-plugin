<?php
/**
 * Contact Form 7 integration.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Integrations;

use TheBlueEmail\Forms\Plugin;
use TheBlueEmail\Forms\Protection;
use TheBlueEmail\Forms\Settings_Page;
use TheBlueEmail\Forms\Targets;

defined( 'ABSPATH' ) || exit;

/**
 * A "theblue.email" tab in the Contact Form 7 editor. When it is switched on,
 * every submission Contact Form 7 accepts (not spam, validation passed) whose
 * consent field is ticked is sent to the chosen theblue.email form.
 *
 * The settings are a contact form property, so Contact Form 7 saves, copies,
 * exports and imports them with the form.
 */
final class Contact_Form_7 {

	public const PROPERTY = 'tbef';

	/**
	 * Wire everything. Called on plugins_loaded when Contact Form 7 is active.
	 */
	public static function boot(): void {
		add_filter( 'wpcf7_pre_construct_contact_form_properties', array( self::class, 'add_property' ), 10, 1 );
		add_filter( 'wpcf7_editor_panels', array( self::class, 'add_panel' ) );
		add_action( 'wpcf7_save_contact_form', array( self::class, 'save' ), 10, 1 );
		add_filter( 'wpcf7_form_elements', array( self::class, 'form_elements' ) );
		add_action( 'wpcf7_mail_sent', array( self::class, 'relay' ) );
		add_action( 'wpcf7_mail_failed', array( self::class, 'relay' ) );

		Targets::register( 'cf7', array( self::class, 'form_key_for_contact_form' ) );
	}

	/**
	 * The defaults.
	 *
	 * @return array{enabled: bool, form: string, email_field: string, name_field: string, consent_field: string}
	 */
	public static function defaults(): array {
		return array(
			'enabled'       => false,
			'form'          => '',
			'email_field'   => 'your-email',
			'name_field'    => 'your-name',
			'consent_field' => '',
		);
	}

	/**
	 * Register the property.
	 *
	 * @param array<string, mixed> $properties The contact form's properties.
	 * @return array<string, mixed>
	 */
	public static function add_property( $properties ): array {
		$properties                   = is_array( $properties ) ? $properties : array();
		$properties[ self::PROPERTY ] = self::defaults();

		return $properties;
	}

	/**
	 * A contact form's settings, with defaults filled in.
	 *
	 * @param object $contact_form A WPCF7_ContactForm.
	 * @return array{enabled: bool, form: string, email_field: string, name_field: string, consent_field: string}
	 */
	public static function settings_of( $contact_form ): array {
		$stored = method_exists( $contact_form, 'prop' ) ? $contact_form->prop( self::PROPERTY ) : array();

		return self::clean( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Validate settings as submitted or stored.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 * @return array{enabled: bool, form: string, email_field: string, name_field: string, consent_field: string}
	 */
	public static function clean( array $raw ): array {
		$d    = self::defaults();
		$name = static fn( $v, string $fallback ): string => is_string( $v ) && 1 === preg_match( '/^[A-Za-z][A-Za-z0-9_:.-]{0,63}$/', trim( $v ) ) ? trim( $v ) : $fallback;
		$form = is_string( $raw['form'] ?? null ) && 1 === preg_match( '/^[a-f0-9]{12}$/', $raw['form'] ) ? $raw['form'] : '';

		return array(
			'enabled'       => ! empty( $raw['enabled'] ) && '' !== $form,
			'form'          => $form,
			'email_field'   => $name( $raw['email_field'] ?? null, $d['email_field'] ),
			'name_field'    => $name( $raw['name_field'] ?? null, '' ),
			'consent_field' => $name( $raw['consent_field'] ?? null, '' ),
		);
	}

	/**
	 * The theblue.email form a contact form sends to, '' when off.
	 *
	 * @param int $id The contact form id.
	 */
	public static function form_key_for_contact_form( int $id ): string {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return '';
		}

		$contact_form = \WPCF7_ContactForm::get_instance( $id );
		if ( ! $contact_form ) {
			return '';
		}

		$settings = self::settings_of( $contact_form );

		return $settings['enabled'] ? $settings['form'] : '';
	}

	/**
	 * Add the tab.
	 *
	 * @param array<string, mixed> $panels The editor panels.
	 * @return array<string, mixed>
	 */
	public static function add_panel( $panels ): array {
		$panels                = is_array( $panels ) ? $panels : array();
		$panels['tbef-panel'] = array(
			'title'    => 'theblue.email',
			'callback' => array( self::class, 'render_panel' ),
		);

		return $panels;
	}

	/**
	 * The tab.
	 *
	 * @param object $contact_form A WPCF7_ContactForm.
	 */
	public static function render_panel( $contact_form ): void {
		$settings  = self::settings_of( $contact_form );
		$plugin    = Plugin::instance();
		$connected = $plugin->connection()->is_connected();

		if ( $connected ) {
			$plugin->forms()->refresh_if_stale();
		}

		$names = array();
		if ( method_exists( $contact_form, 'scan_form_tags' ) ) {
			foreach ( $contact_form->scan_form_tags() as $tag ) {
				if ( isset( $tag->name ) && '' !== $tag->name ) {
					$names[] = (string) $tag->name;
				}
			}
		}

		echo '<h2>' . esc_html__( 'theblue.email newsletter signups', 'theblue-email-forms' ) . '</h2>';

		if ( ! $connected ) {
			echo '<p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to the plugin's settings page. */
					esc_html__( 'Connect this site to theblue.email first: %s', 'theblue-email-forms' ),
					'<a href="' . esc_url( Settings_Page::url() ) . '">' . esc_html__( 'theblue.email settings', 'theblue-email-forms' ) . '</a>'
				)
			) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Send the people who submit this form to a theblue.email form. They get its confirmation email and join its list only once they confirm. The message itself is sent as usual.', 'theblue-email-forms' ) . '</p>';
		echo '<fieldset><table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Send signups', 'theblue-email-forms' ) . '</th><td><label><input type="checkbox" name="tbef[enabled]" value="1" ' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Send to theblue.email', 'theblue-email-forms' ) . '</label></td></tr>';

		echo '<tr><th scope="row"><label for="tbef-form">' . esc_html__( 'theblue.email form', 'theblue-email-forms' ) . '</label></th><td><select id="tbef-form" name="tbef[form]"><option value="">' . esc_html__( 'Choose a form', 'theblue-email-forms' ) . '</option>';
		foreach ( $plugin->forms()->all() as $form ) {
			$label = (string) $form['name'];
			if ( 'active' !== $form['status'] ) {
				/* translators: %s: form name. */
				$label = sprintf( __( '%s (draft)', 'theblue-email-forms' ), $label );
			}
			echo '<option value="' . esc_attr( (string) $form['public_key'] ) . '" ' . selected( $settings['form'], $form['public_key'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		$rows = array(
			'email_field'   => array( __( 'Email field', 'theblue-email-forms' ), __( 'The name of the field with the address, e.g. your-email.', 'theblue-email-forms' ) ),
			'name_field'    => array( __( 'First name field', 'theblue-email-forms' ), __( 'Optional, e.g. your-name.', 'theblue-email-forms' ) ),
			'consent_field' => array( __( 'Consent field', 'theblue-email-forms' ), __( 'The name of an acceptance or checkbox field such as [acceptance newsletter optional]. When set, only people who tick it are sent. Recommended on a contact form.', 'theblue-email-forms' ) ),
		);

		foreach ( $rows as $key => $row ) {
			echo '<tr><th scope="row"><label for="tbef-' . esc_attr( $key ) . '">' . esc_html( $row[0] ) . '</label></th><td><input type="text" class="regular-text code" id="tbef-' . esc_attr( $key ) . '" name="tbef[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '" list="tbef-field-names"><p class="description">' . esc_html( $row[1] ) . '</p></td></tr>';
		}

		echo '</tbody></table></fieldset><datalist id="tbef-field-names">';
		foreach ( array_unique( $names ) as $field_name ) {
			echo '<option value="' . esc_attr( $field_name ) . '"></option>';
		}
		echo '</datalist>';

		if ( $settings['enabled'] && array() !== $names && ! in_array( $settings['email_field'], $names, true ) ) {
			echo '<p class="notice notice-warning inline">' . esc_html__( 'This form has no field with that email field name.', 'theblue-email-forms' ) . '</p>';
		}
	}

	/**
	 * Save the tab. Contact Form 7 has already checked its nonce and the
	 * user's capability before it fires this action.
	 *
	 * @param object $contact_form A WPCF7_ContactForm.
	 */
	public static function save( $contact_form ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 verifies its save nonce (wpcf7-save-contact-form_{id}) before wpcf7_save_contact_form.
		if ( ! isset( $_POST['tbef'] ) || ! is_array( $_POST['tbef'] ) || ! method_exists( $contact_form, 'set_properties' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above; every value is validated in clean().
		$raw = wp_unslash( $_POST['tbef'] );

		$contact_form->set_properties( array( self::PROPERTY => self::clean( is_array( $raw ) ? $raw : array() ) ) );
	}

	/**
	 * Add the protection fields to a contact form that sends signups.
	 *
	 * @param string $html The form's inner markup.
	 */
	public static function form_elements( $html ): string {
		$html = (string) $html;

		if ( ! function_exists( 'wpcf7_get_current_contact_form' ) ) {
			return $html;
		}

		$contact_form = wpcf7_get_current_contact_form();
		if ( ! $contact_form ) {
			return $html;
		}

		$settings = self::settings_of( $contact_form );
		if ( ! $settings['enabled'] ) {
			return $html;
		}

		return $html . Protection::fields(
			array(
				array(
					'target'      => 'cf7-' . (int) $contact_form->id(),
					'email_field' => $settings['email_field'],
				),
			)
		);
	}

	/**
	 * Send an accepted submission to theblue.email.
	 *
	 * @param object $contact_form A WPCF7_ContactForm.
	 */
	public static function relay( $contact_form ): void {
		$settings = self::settings_of( $contact_form );
		if ( ! $settings['enabled'] || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = \WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$value = static function ( string $name ) use ( $submission ): string {
			if ( '' === $name ) {
				return '';
			}
			$v = $submission->get_posted_data( $name );
			if ( is_array( $v ) ) {
				$v = implode( ' ', array_filter( array_map( 'strval', $v ) ) );
			}
			return is_scalar( $v ) ? (string) $v : '';
		};

		$target = 'cf7-' . (int) $contact_form->id();
		$posted = Protection::posted( $target );
		$input  = array(
			'email'      => $value( $settings['email_field'] ),
			'first_name' => $value( $settings['name_field'] ),
			'hp'         => $posted['hp'],
			'pow_salt'   => $posted['pow_salt'],
			'pow_nonce'  => $posted['pow_nonce'],
			'page_url'   => (string) $submission->get_meta( 'url' ),
		);

		if ( '' !== $settings['consent_field'] ) {
			$input['consent'] = '' !== trim( $value( $settings['consent_field'] ) );
		}

		Plugin::instance()->relay()->submit( 'contactform7', (string) $contact_form->title(), $settings['form'], $input );
	}
}
