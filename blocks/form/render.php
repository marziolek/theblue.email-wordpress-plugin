<?php
/**
 * Server render of the theblue.email form block.
 *
 * @package TheBlueEmail\Forms
 *
 * @var array<string, mixed> $attributes The block attributes.
 */

defined( 'ABSPATH' ) || exit;

$tbef_markup = \TheBlueEmail\Forms\Plugin::instance()->embed()->markup( (string) ( $attributes['key'] ?? '' ) );

if ( '' !== $tbef_markup && 0 !== strpos( $tbef_markup, '<!--' ) ) {
	printf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
		$tbef_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped in Embed::markup().
	);
} else {
	echo $tbef_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an escaped HTML comment for authors, or nothing.
}
