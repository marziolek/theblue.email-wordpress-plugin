<?php
/**
 * Removes everything the plugin stored. Contact Form 7 forms keep their
 * theblue.email tab settings (a form property, stored with the form), and
 * Gravity Forms keeps its feeds until the add-on is uninstalled from Forms,
 * Settings, Uninstall, as with every add-on.
 *
 * @package TheBlueEmail\Forms
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'tbef_api_key', 'tbef_account', 'tbef_state', 'tbef_forms', 'tbef_unallowed_forms', 'tbef_activity' ) as $tbef_option ) {
	delete_option( $tbef_option );
}
