<?php
/**
 * Prints a theblue.email form the way the app's embed snippet does.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * The markup is the snippet the Forms screen in theblue.email shows, a div
 * with the form's key, plus tbe-embed.js loaded once. The platform renders,
 * styles, protects and submits the form; this plugin never sees what a
 * visitor types into it.
 */
class Embed {

	public const SCRIPT_HANDLE = 'tbef-embed';

	/** Forms placed on a page whose site is not on their allowed domains. */
	public const OPTION_UNALLOWED = 'tbef_unallowed_forms';

	/**
	 * Constructor.
	 *
	 * @param Forms      $forms      The cached forms.
	 * @param Connection $connection The connection.
	 */
	public function __construct( private Forms $forms, private Connection $connection ) {}

	/**
	 * The markup for one form, or '' when there is nothing to show.
	 *
	 * @param string $key       The form's public key.
	 * @param string $css_class Extra classes for the wrapper.
	 */
	public function markup( string $key, string $css_class = '' ): string {
		$key = strtolower( trim( $key ) );

		if ( 1 !== preg_match( '/^[a-f0-9]{12}$/', $key ) ) {
			return $this->author_note( __( 'theblue.email: choose a form.', 'theblue-email-forms' ) );
		}

		$form = $this->forms->find( $key );

		// A key that is not in the cached list may still be a real form (the
		// list is from before it was made): the platform decides. A known draft
		// shows nothing, as the platform's own snippet does.
		if ( null !== $form && 'active' !== $form['status'] ) {
			return $this->author_note( __( 'theblue.email: this form is a draft and is not shown.', 'theblue-email-forms' ) );
		}

		if ( null !== $form && ! Forms::allows_this_site( $form ) ) {
			$this->remember_unallowed( $key );
		}

		$this->enqueue();

		$fields  = null !== $form ? (int) $form['fields'] : 2;
		$classes = trim( 'tbef-form ' . $css_class );

		return sprintf(
			'<div class="%1$s"><div class="tbe-form" data-form-id="%2$s" data-fields="%3$d"></div></div>',
			esc_attr( $classes ),
			esc_attr( $key ),
			$fields
		);
	}

	/**
	 * Load tbe-embed.js from theblue.email once, with its API base set inline
	 * so script optimisers that drop document.currentScript cannot break it.
	 */
	public function enqueue(): void {
		if ( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		// The platform puts its own version in the URL; null keeps WordPress from adding another.
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- versioned by the platform, see above.
		wp_register_script( self::SCRIPT_HANDLE, $this->connection->embed_script_url(), array(), null, array( 'strategy' => 'async' ) );
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.tbeEmbed = window.tbeEmbed || {}; window.tbeEmbed.restUrl = ' . wp_json_encode( TBEF_PLATFORM_URL . '/api/tbe/v1/' ) . ';',
			'before'
		);
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Keys of forms placed on this site that do not allow it.
	 *
	 * @return string[]
	 */
	public function unallowed(): array {
		$stored = get_option( self::OPTION_UNALLOWED, array() );

		return is_array( $stored ) ? array_values( array_filter( array_map( 'strval', array_keys( $stored ) ) ) ) : array();
	}

	/**
	 * Forget a key once the site is allowed on it.
	 *
	 * @param string $key The form's public key.
	 */
	public function forget_unallowed( string $key ): void {
		$stored = get_option( self::OPTION_UNALLOWED, array() );
		if ( is_array( $stored ) && isset( $stored[ $key ] ) ) {
			unset( $stored[ $key ] );
			update_option( self::OPTION_UNALLOWED, $stored, false );
		}
	}

	/**
	 * Record a form placed on a page while this site is not allowed on it.
	 *
	 * @param string $key The form's public key.
	 */
	private function remember_unallowed( string $key ): void {
		$stored = get_option( self::OPTION_UNALLOWED, array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( ! isset( $stored[ $key ] ) ) {
			$stored[ $key ] = time();
			update_option( self::OPTION_UNALLOWED, array_slice( $stored, -50, null, true ), false );
		}
	}

	/**
	 * A note for whoever edits the page, invisible to visitors.
	 *
	 * @param string $text The note.
	 */
	private function author_note( string $text ): string {
		return current_user_can( 'edit_posts' ) ? '<!-- ' . esc_html( $text ) . ' -->' : '';
	}
}
