<?php
/**
 * [theblue_email_form key="..."]
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * The shortcode, for the classic editor, widgets and page builders.
 */
class Shortcode {

	public const TAG = 'theblue_email_form';

	/**
	 * Constructor.
	 *
	 * @param Embed $embed The embed printer.
	 */
	public function __construct( private Embed $embed ) {}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render it.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'key'   => '',
				'class' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$parts   = preg_split( '/\s+/', (string) $atts['class'] );
		$classes = implode( ' ', array_map( 'sanitize_html_class', false === $parts ? array() : $parts ) );

		return $this->embed->markup( (string) $atts['key'], $classes );
	}

	/**
	 * The shortcode for one form, as the settings page offers it.
	 *
	 * @param string $key The form's public key.
	 */
	public static function for_key( string $key ): string {
		return '[' . self::TAG . ' key="' . $key . '"]';
	}
}
