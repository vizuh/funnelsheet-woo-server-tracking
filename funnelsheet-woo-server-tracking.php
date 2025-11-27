<?php
/**
 * Plugin Name: Funnelsheet – WooCommerce Server-Side Tracking (GA4 & GTM-SS)
 * Plugin URI: https://vizuh.com
 * Description: Capture 100% of WooCommerce purchases using bulletproof server-side tracking. Bypasses ad blockers by sending events to GA4 or sGTM from your server.
 * Version: 1.0.0
 * Author: Vizuh, HugoC, Atroci
 * Author URI: https://vizuh.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: funnelsheet-woo-server-tracking
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 3.5
 * WC tested up to: 9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'FWST_VERSION', '1.0.0' );
define( 'FWST_PLUGIN_FILE', __FILE__ );
define( 'FWST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FWST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FWST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class FWST_Main {

	/**
	 * Single instance of the class.
	 *
	 * @var FWST_Main
	 */
	protected static $instance = null;

	/**
	 * Get single instance of the class.
	 *
	 * @return FWST_Main
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Check if WooCommerce is active.
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load plugin classes.
		$this->includes();

		// Initialize plugin.
		$this->init();

		// Activation/deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Display admin notice if WooCommerce is not active.
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: WooCommerce plugin link */
						__( '<strong>Funnelsheet – WooCommerce Server-Side Tracking</strong> requires WooCommerce to be installed and active. You can download %s here.', 'funnelsheet-woo-server-tracking' ),
						'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core classes.
		require_once FWST_PLUGIN_DIR . 'includes/class-fwst-database.php';
		require_once FWST_PLUGIN_DIR . 'includes/class-fwst-helpers.php';
		require_once FWST_PLUGIN_DIR . 'includes/class-fwst-order-hooks.php';
		require_once FWST_PLUGIN_DIR . 'includes/class-fwst-dispatcher.php';
		require_once FWST_PLUGIN_DIR . 'includes/class-fwst-worker.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once FWST_PLUGIN_DIR . 'includes/class-fwst-settings.php';
			require_once FWST_PLUGIN_DIR . 'includes/class-fwst-event-log.php';
		}
	}

	/**
	 * Initialize plugin.
	 */
	private function init() {
		// Initialize order hooks.
		FWST_Order_Hooks::instance();

		// Initialize worker.
		FWST_Worker::instance();

		// Initialize admin.
		if ( is_admin() ) {
			FWST_Settings::instance();
			FWST_Event_Log::instance();
		}

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'funnelsheet-woo-server-tracking', false, dirname( FWST_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Create database table.
		FWST_Database::create_table();

		// Schedule worker if not already scheduled.
		if ( ! as_next_scheduled_action( 'fwst_process_queue' ) ) {
			as_schedule_recurring_action( time(), 5 * MINUTE_IN_SECONDS, 'fwst_process_queue', array(), 'funnelsheet-wst' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Unschedule worker.
		as_unschedule_all_actions( 'fwst_process_queue', array(), 'funnelsheet-wst' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin.
 */
function fwst_init() {
	return FWST_Main::instance();
}

// Start the plugin.
add_action( 'plugins_loaded', 'fwst_init' );
