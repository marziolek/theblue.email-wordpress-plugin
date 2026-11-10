<?php
/**
 * Dependencies of editor.js, which has no build step.
 *
 * @package TheBlueEmail\Forms
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
	'version'      => '1.1.1',
);
