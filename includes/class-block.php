<?php
/**
 * The theblue-email/form block.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block from blocks/form/block.json. It is dynamic: render.php
 * prints the embed snippet, so changing a form in theblue.email never needs
 * the post to be saved again.
 */
final class Block {

	/**
	 * Register the block.
	 */
	public static function register(): void {
		$type = register_block_type( TBEF_DIR . 'blocks/form' );

		if ( $type instanceof \WP_Block_Type && function_exists( 'wp_set_script_translations' ) ) {
			foreach ( (array) $type->editor_script_handles as $handle ) {
				wp_set_script_translations( $handle, 'theblue-email-forms', TBEF_DIR . 'languages' );
			}
		}
	}
}
