<?php
/**
 * Gravity Forms integration: bootstrap and form-side hooks.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms\Integrations;

use TheBlueEmail\Forms\Protection;
use TheBlueEmail\Forms\Targets;
use TheBlueEmail\Forms\Visitor;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the feed add-on with Gravity Forms' Add-On Framework, and adds
 * the honeypot and proof-of-work fields to every form that has an active
 * theblue.email feed. Loaded only when Gravity Forms is active.
 */
final class Gravity_Forms {

	/** The add-on slug; feeds are stored under it. */
	public const SLUG = 'theblue-email-forms';

	/** Entry meta holding what the browser posted, until the feed has run. */
	public const META = 'tbef_relay';

	/**
	 * Wire everything. Called on plugins_loaded.
	 */
	public static function boot(): void {
		add_action( 'gform_loaded', array( self::class, 'register_addon' ), 5 );
		add_filter( 'gform_form_tag', array( self::class, 'form_tag' ), 10, 2 );
		add_action( 'gform_entry_created', array( self::class, 'remember_submission' ), 10, 2 );

		Targets::register( 'gf', array( self::class, 'form_key_for_feed' ) );

		if ( did_action( 'gform_loaded' ) ) {
			self::register_addon();
		}
	}

	/**
	 * Load the framework and register the add-on.
	 */
	public static function register_addon(): void {
		static $done = false;
		if ( $done || ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}
		$done = true;

		\GFForms::include_feed_addon_framework();
		require_once __DIR__ . '/class-gravity-forms-addon.php';
		\GFAddOn::register( Gravity_Forms_Addon::class );
	}

	/**
	 * The active theblue.email feeds of a form.
	 *
	 * @param int $form_id Gravity Forms form id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function active_feeds( int $form_id ): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$feeds = \GFAPI::get_feeds( null, $form_id, self::SLUG, true );

		return is_array( $feeds ) ? $feeds : array();
	}

	/**
	 * The theblue.email form a feed sends to, '' when the feed is gone or off.
	 *
	 * @param int $feed_id The feed id.
	 */
	public static function form_key_for_feed( int $feed_id ): string {
		if ( ! class_exists( 'GFAPI' ) ) {
			return '';
		}

		$feed = \GFAPI::get_feed( $feed_id );

		if ( ! is_array( $feed ) || self::SLUG !== ( $feed['addon_slug'] ?? '' ) || empty( $feed['is_active'] ) ) {
			return '';
		}

		return (string) ( $feed['meta']['tbeForm'] ?? '' );
	}

	/**
	 * The name of the input that holds a mapped field's value.
	 *
	 * @param string $field_id e.g. 3 or 1.3.
	 */
	public static function input_name( string $field_id ): string {
		return 'input_' . str_replace( '.', '_', $field_id );
	}

	/**
	 * Add the protection fields right after the opening form tag.
	 *
	 * @param string               $form_tag The opening tag.
	 * @param array<string, mixed> $form     The form.
	 */
	public static function form_tag( $form_tag, $form ): string {
		$form_tag = (string) $form_tag;
		if ( ! is_array( $form ) || is_admin() ) {
			return $form_tag;
		}

		$targets = array();
		foreach ( self::active_feeds( (int) ( $form['id'] ?? 0 ) ) as $feed ) {
			$email_field = (string) ( $feed['meta']['mappedFields_email'] ?? '' );
			if ( '' === $email_field ) {
				continue;
			}
			$targets[] = array(
				'target'      => 'gf-' . (int) $feed['id'],
				'email_field' => self::input_name( $email_field ),
			);
		}

		return $form_tag . Protection::fields( $targets );
	}

	/**
	 * Keep what the browser posted with the entry: a feed may run later, in
	 * the background, when the request is gone. The feed deletes it.
	 *
	 * @param array<string, mixed> $entry The new entry.
	 * @param array<string, mixed> $form  The form.
	 */
	public static function remember_submission( $entry, $form ): void {
		if ( ! is_array( $entry ) || ! is_array( $form ) || ! function_exists( 'gform_update_meta' ) ) {
			return;
		}

		$feeds = self::active_feeds( (int) ( $form['id'] ?? 0 ) );
		if ( array() === $feeds ) {
			return;
		}

		$pow = array();
		$hp  = '';
		foreach ( $feeds as $feed ) {
			$target         = 'gf-' . (int) $feed['id'];
			$posted         = Protection::posted( $target );
			$hp             = $posted['hp'];
			$pow[ $target ] = array(
				'salt'  => $posted['pow_salt'],
				'nonce' => $posted['pow_nonce'],
			);
		}

		gform_update_meta(
			(int) $entry['id'],
			self::META,
			(string) wp_json_encode(
				array(
					'hp'         => $hp,
					'pow'        => $pow,
					'visitor_ip' => Visitor::ip(),
					'country'    => Visitor::country(),
				)
			)
		);
	}
}
