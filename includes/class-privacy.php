<?php
/**
 * Suggested text for the site's privacy policy.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, Privacy shows this as a suggestion for the policy page.
 */
final class Privacy {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'add_policy_text' ) );
	}

	/**
	 * Register the text.
	 */
	public static function add_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$text = '<p>' . esc_html__( 'Newsletter signup forms on this site are provided by theblue.email. When you open a page with such a form, your browser loads the form and its script from theblue.email. When you sign up, the address and first name you enter are sent to theblue.email, together with your IP address and the page you signed up on, and you receive an email asking you to confirm. You join the newsletter only once you confirm.', 'theblue-email-forms' ) . '</p>'
			. '<p>' . esc_html__( 'If you tick a newsletter box on another form of this site (for example a contact or order form), the same details are sent to theblue.email from this site\'s server after you submit it.', 'theblue-email-forms' ) . '</p>'
			. '<p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to the theblue.email privacy policy. */
					__( 'How theblue.email processes these details: %s.', 'theblue-email-forms' ),
					'<a href="https://theblue.email/privacy-policy/">https://theblue.email/privacy-policy/</a>'
				)
			) . '</p>';

		wp_add_privacy_policy_content( 'theblue.email Forms', $text );
	}
}
