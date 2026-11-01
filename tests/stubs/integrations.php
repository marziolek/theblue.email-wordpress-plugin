<?php
/**
 * The parts of Gravity Forms and Contact Form 7 this plugin uses, for PHPStan.
 * Signatures follow Gravity Forms 2.5+ and Contact Form 7 5.x.
 *
 * @package TheBlueEmail\Forms
 */

// phpcs:disable

class GFForms {
	public static function include_feed_addon_framework(): void {}
}

class GFAddOn {
	protected $_version;
	protected $_min_gravityforms_version;
	protected $_slug;
	protected $_path;
	protected $_full_path;
	protected $_title;
	protected $_short_title;
	protected $_capabilities_form_settings;
	public function __construct() {}
	public static function register( string $class_name ): void {}
	public function init() {}
	public function get_menu_icon() {}
	/** @param array<string, mixed> $form @param array<string, mixed> $entry */
	public function get_field_value( $form, $entry, $field_id ) {}
}

class GFFeedAddOn extends GFAddOn {
	public function can_create_feed() {}
	public function configure_addon_message() {}
	public function feed_settings_fields() {}
	public function feed_list_columns() {}
	/** @param array<string, mixed> $feed @param array<string, mixed> $entry @param array<string, mixed> $form */
	public function process_feed( $feed, $entry, $form ) {}
	public function add_note( $entry_id, $note, $note_type = null ) {}
}

class GFAPI {
	/** @return array<int, array<string, mixed>>|WP_Error */
	public static function get_feeds( $feed_ids = null, $form_id = null, $addon_slug = null, $is_active = true ) { return array(); }
	/** @return array<string, mixed>|WP_Error */
	public static function get_feed( $feed_id ) { return array(); }
}

function gform_update_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {}
function gform_get_meta( $entry_id, $meta_key ) { return ''; }
function gform_delete_meta( $entry_id, $meta_key = '' ) {}

class WPCF7_FormTag {
	public string $name = '';
}

class WPCF7_ContactForm {
	public static function get_instance( $post ): ?self { return null; }
	public function id(): int { return 0; }
	public function title(): string { return ''; }
	public function prop( string $name ) { return null; }
	/** @param array<string, mixed> $properties */
	public function set_properties( array $properties ): void {}
	/** @return WPCF7_FormTag[] */
	public function scan_form_tags( $cond = null ): array { return array(); }
}

class WPCF7_Submission {
	public static function get_instance(): ?self { return null; }
	/** @return mixed */
	public function get_posted_data( $name = '' ) { return null; }
	/** @return mixed */
	public function get_meta( $name ) { return null; }
}

function wpcf7_get_current_contact_form(): ?WPCF7_ContactForm { return null; }
