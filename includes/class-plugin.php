<?php
/**
 * Wires the plugin together.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

namespace TheBlueEmail\Forms;

use TheBlueEmail\Forms\Integrations\Contact_Form_7;
use TheBlueEmail\Forms\Integrations\Gravity_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Builds each service once and registers its hooks. Nothing else in the
 * plugin calls add_action() at file scope.
 */
final class Plugin {

	/**
	 * The instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	private Connection $connection;
	private Client $client;
	private Forms $forms;
	private Embed $embed;
	private Activity_Log $log;
	private Relay $relay;

	/**
	 * Build the services.
	 */
	private function __construct() {
		$this->connection = new Connection();
		$this->client     = new Client( $this->connection );
		$this->forms      = new Forms( $this->client );
		$this->embed      = new Embed( $this->forms, $this->connection );
		$this->log        = new Activity_Log();
		$this->relay      = new Relay( $this->client, $this->log );
	}

	/**
	 * The instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register every hook. Runs on plugins_loaded.
	 */
	public static function boot(): void {
		$plugin = self::instance();

		add_action( 'init', array( self::class, 'load_textdomain' ) );
		add_action( 'init', array( Block::class, 'register' ) );
		add_action( 'rest_api_init', array( $plugin, 'register_rest' ) );

		( new Shortcode( $plugin->embed ) )->register();
		Privacy::register();

		if ( is_admin() ) {
			( new Settings_Page( $plugin->connection, $plugin->client, $plugin->forms, $plugin->embed, $plugin->log ) )->register();
			( new Notices( $plugin->connection, $plugin->forms, $plugin->embed ) )->register();
			( new Site_Health( $plugin->connection, $plugin->client ) )->register();
		}

		if ( class_exists( 'GFForms' ) ) {
			Gravity_Forms::boot();
		} else {
			// Gravity Forms may load after this plugin: hook in when it does.
			add_action( 'gform_loaded', array( self::class, 'late_gravity_forms' ), 1 );
		}

		if ( defined( 'WPCF7_VERSION' ) ) {
			Contact_Form_7::boot();
		}
	}

	/**
	 * Gravity Forms loaded after this plugin's boot.
	 */
	public static function late_gravity_forms(): void {
		remove_action( 'gform_loaded', array( self::class, 'late_gravity_forms' ), 1 );
		Gravity_Forms::boot();
	}

	/**
	 * Translations shipped with the plugin, until translate.wordpress.org has them.
	 */
	public static function load_textdomain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- ships Polish until the directory's translation project exists.
		load_plugin_textdomain( 'theblue-email-forms', false, dirname( plugin_basename( TBEF_FILE ) ) . '/languages' );
	}

	/**
	 * The plugin's REST routes.
	 */
	public function register_rest(): void {
		( new Rest( $this->forms, $this->client, $this->connection, $this->embed ) )->register();
	}

	/**
	 * Activation: nothing to create, only a check of the PHP extensions the
	 * key encryption needs.
	 */
	public static function activate(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) && ! function_exists( 'openssl_encrypt' ) ) {
			deactivate_plugins( plugin_basename( TBEF_FILE ) );
			wp_die( esc_html__( 'theblue.email Forms needs the sodium or OpenSSL PHP extension.', 'theblue-email-forms' ) );
		}
	}

	/**
	 * The connection.
	 */
	public function connection(): Connection {
		return $this->connection;
	}

	/**
	 * The platform client.
	 */
	public function client(): Client {
		return $this->client;
	}

	/**
	 * The cached forms.
	 */
	public function forms(): Forms {
		return $this->forms;
	}

	/**
	 * The embed printer.
	 */
	public function embed(): Embed {
		return $this->embed;
	}

	/**
	 * The relay for integrations.
	 */
	public function relay(): Relay {
		return $this->relay;
	}

	/**
	 * The activity log.
	 */
	public function log(): Activity_Log {
		return $this->log;
	}
}
