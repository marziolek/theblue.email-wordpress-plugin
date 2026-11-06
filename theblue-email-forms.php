<?php
/**
 * Plugin Name:       theblue.email Forms
 * Plugin URI:        https://theblue.email/wordpress
 * Description:       Place your theblue.email signup forms on your site with a block or a shortcode, and collect newsletter signups from Gravity Forms and Contact Form 7.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            theblue.email
 * Author URI:        https://theblue.email
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       theblue-email-forms
 * Domain Path:       /languages
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'TBEF_VERSION', '1.1.0' );
define( 'TBEF_FILE', __FILE__ );
define( 'TBEF_DIR', plugin_dir_path( __FILE__ ) );
define( 'TBEF_URL', plugin_dir_url( __FILE__ ) );

// The service this plugin connects to. A constant, never an option, so no
// setting on the site can point the stored key at another host. Developers of
// theblue.email itself may define it in wp-config.php to test against staging.
if ( ! defined( 'TBEF_PLATFORM_URL' ) ) {
	define( 'TBEF_PLATFORM_URL', 'https://theblue.email' );
}

require_once TBEF_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( \TheBlueEmail\Forms\Plugin::class, 'activate' ) );

add_action( 'plugins_loaded', array( \TheBlueEmail\Forms\Plugin::class, 'boot' ), 5 );
