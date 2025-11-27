<?php
/**
 * Settings page for Funnelsheet WooCommerce Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class FWST_Settings {

	/**
	 * Single instance.
	 *
	 * @var FWST_Settings
	 */
	protected static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return FWST_Settings
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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_FWST_test_event', array( $this, 'ajax_test_event' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Server-Side Tracking', 'funnelsheet-woo-server-tracking' ),
			__( 'Server Tracking', 'funnelsheet-woo-server-tracking' ),
			'manage_woocommerce',
			'wc-server-tracking',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// General settings.
		register_setting( 'FWST_settings', 'FWST_destination_type' );
		register_setting( 'FWST_settings', 'FWST_ga4_measurement_id' );
		register_setting( 'FWST_settings', 'FWST_ga4_api_secret' );
		register_setting( 'FWST_settings', 'FWST_sgtm_endpoint_url' );
		register_setting( 'FWST_settings', 'FWST_sgtm_auth_header' );

		// Advanced settings.
		register_setting( 'FWST_settings', 'FWST_track_on_processing' );
		register_setting( 'FWST_settings', 'FWST_track_on_completed' );
		register_setting( 'FWST_settings', 'FWST_max_retry_attempts' );
		register_setting( 'FWST_settings', 'FWST_queue_interval' );
		register_setting( 'FWST_settings', 'FWST_debug_mode' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-server-tracking' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'fwst-admin',
			FWST_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FWST_VERSION
		);

		wp_enqueue_script(
			'fwst-admin',
			FWST_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FWST_VERSION,
			true
		);

		wp_localize_script(
			'fwst-admin',
			'fwstAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'FWST_admin' ),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'funnelsheet-woo-server-tracking' ) );
		}

		// Get current tab.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		?>
		<div class="wrap fwst-settings">
			<h1><?php esc_html_e( 'Funnelsheet WooCommerce Server-Side Tracking', 'funnelsheet-woo-server-tracking' ); ?></h1>
			
			<nav class="nav-tab-wrapper">
				<a href="?page=wc-server-tracking&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'funnelsheet-woo-server-tracking' ); ?>
				</a>
				<a href="?page=wc-server-tracking&tab=advanced" class="nav-tab <?php echo 'advanced' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Advanced', 'funnelsheet-woo-server-tracking' ); ?>
				</a>
			</nav>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'FWST_settings' );

				if ( 'advanced' === $active_tab ) {
					$this->render_advanced_tab();
				} else {
					$this->render_general_tab();
				}

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general tab.
	 */
	private function render_general_tab() {
		$destination = get_option( 'FWST_destination_type', 'ga4' );
		$ga4_id      = get_option( 'FWST_ga4_measurement_id', '' );
		$ga4_secret  = get_option( 'FWST_ga4_api_secret', '' );
		$sgtm_url    = get_option( 'FWST_sgtm_endpoint_url', '' );
		$sgtm_auth   = get_option( 'FWST_sgtm_auth_header', '' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Destination Type', 'funnelsheet-woo-server-tracking' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="FWST_destination_type" value="ga4" <?php checked( $destination, 'ga4' ); ?> />
							<?php esc_html_e( 'GA4 Measurement Protocol', 'funnelsheet-woo-server-tracking' ); ?>
						</label><br>
						<label>
							<input type="radio" name="FWST_destination_type" value="sgtm" <?php checked( $destination, 'sgtm' ); ?> />
							<?php esc_html_e( 'Server-Side GTM (sGTM)', 'funnelsheet-woo-server-tracking' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr class="fwst-ga4-field">
				<th scope="row">
					<label for="FWST_ga4_measurement_id"><?php esc_html_e( 'GA4 Measurement ID', 'funnelsheet-woo-server-tracking' ); ?></label>
				</th>
				<td>
					<input type="text" id="FWST_ga4_measurement_id" name="FWST_ga4_measurement_id" value="<?php echo esc_attr( $ga4_id ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX" />
					<p class="description"><?php esc_html_e( 'Your GA4 Measurement ID (starts with G-)', 'funnelsheet-woo-server-tracking' ); ?></p>
				</td>
			</tr>
			<tr class="fwst-ga4-field">
				<th scope="row">
					<label for="FWST_ga4_api_secret"><?php esc_html_e( 'GA4 API Secret', 'funnelsheet-woo-server-tracking' ); ?></label>
				</th>
				<td>
					<input type="password" id="FWST_ga4_api_secret" name="FWST_ga4_api_secret" value="<?php echo esc_attr( $ga4_secret ); ?>" class="regular-text" />
					<p class="description">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: GA4 admin URL */
								__( 'Get your API secret from <a href="%s" target="_blank">GA4 Admin → Data Streams → Choose Stream → Measurement Protocol API secrets</a>', 'funnelsheet-woo-server-tracking' ),
								'https://analytics.google.com/analytics/web/#/a/p/admin/streams'
							)
						);
						?>
					</p>
				</td>
			</tr>
			<tr class="fwst-sgtm-field">
				<th scope="row">
					<label for="FWST_sgtm_endpoint_url"><?php esc_html_e( 'sGTM Endpoint URL', 'funnelsheet-woo-server-tracking' ); ?></label>
				</th>
				<td>
					<input type="url" id="FWST_sgtm_endpoint_url" name="FWST_sgtm_endpoint_url" value="<?php echo esc_attr( $sgtm_url ); ?>" class="regular-text" placeholder="https://your-sgtm-domain.com/endpoint" />
					<p class="description"><?php esc_html_e( 'Your server-side GTM endpoint URL', 'funnelsheet-woo-server-tracking' ); ?></p>
				</td>
			</tr>
			<tr class="fwst-sgtm-field">
				<th scope="row">
					<label for="FWST_sgtm_auth_header"><?php esc_html_e( 'Authorization Header (Optional)', 'funnelsheet-woo-server-tracking' ); ?></label>
				</th>
				<td>
					<input type="password" id="FWST_sgtm_auth_header" name="FWST_sgtm_auth_header" value="<?php echo esc_attr( $sgtm_auth ); ?>" class="regular-text" placeholder="Bearer your-token" />
					<p class="description"><?php esc_html_e( 'Optional authorization header for sGTM endpoint', 'funnelsheet-woo-server-tracking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Test Connection', 'funnelsheet-woo-server-tracking' ); ?></th>
				<td>
					<button type="button" id="fwst-test-event" class="button button-secondary"><?php esc_html_e( 'Send Test Event', 'funnelsheet-woo-server-tracking' ); ?></button>
					<span id="fwst-test-result"></span>
					<p class="description"><?php esc_html_e( 'Send a test purchase event to verify your configuration', 'funnelsheet-woo-server-tracking' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render advanced tab.
	 */
	private function render_advanced_tab() {
		$track_processing = get_option( 'FWST_track_on_processing', 'yes' );
		$track_completed  = get_option( 'FWST_track_on_completed', 'yes' );
		$max_attempts     = get_option( 'FWST_max_retry_attempts', 5 );
		$queue_interval   = get_option( 'FWST_queue_interval', 5 );
		$debug_mode       = get_option( 'FWST_debug_mode', 'no' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Track Orders On', 'funnelsheet-woo-server-tracking' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="FWST_track_on_processing" value="yes" <?php checked( $track_processing, 'yes' ); ?> />
							<?php esc_html_e( 'Processing status', 'funnelsheet-woo-server-tracking' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="FWST_track_on_completed" value="yes" <?php checked( $track_completed, 'yes' ); ?> />
							<?php esc_html_e( 'Completed status', 'funnelsheet-woo-server-tracking' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Select which order statuses should trigger tracking', 'funnelsheet-woo-server-tracking' ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="FWST_max_retry_attempts"><?php esc_html_e( 'Max Retry Attempts', 'funnelsheet-woo-server-tracking' ); ?></label>
				</th>
				<td>
					<input type="number" id="FWST_max_retry_attempts" name="FWST_max_retry_attempts" value="<?php echo esc_attr( $max_attempts ); ?>" min="1" max="10" />
					<p class="description"><?php esc_html_e( 'Maximum number of retry attempts for failed events (1-10)', 'funnelsheet-woo-server-tracking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="FWST_queue_interval"><?php esc_html_e( 'Queue Processing Interval', 'funnelsheet-woo-server-tracking' ); ?></label>
				</th>
				<td>
					<select id="FWST_queue_interval" name="FWST_queue_interval">
						<option value="5" <?php selected( $queue_interval, 5 ); ?>><?php esc_html_e( '5 minutes', 'funnelsheet-woo-server-tracking' ); ?></option>
						<option value="10" <?php selected( $queue_interval, 10 ); ?>><?php esc_html_e( '10 minutes', 'funnelsheet-woo-server-tracking' ); ?></option>
						<option value="15" <?php selected( $queue_interval, 15 ); ?>><?php esc_html_e( '15 minutes', 'funnelsheet-woo-server-tracking' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'How often the queue should be processed', 'funnelsheet-woo-server-tracking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug Mode', 'funnelsheet-woo-server-tracking' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="FWST_debug_mode" value="yes" <?php checked( $debug_mode, 'yes' ); ?> />
						<?php esc_html_e( 'Enable debug logging', 'funnelsheet-woo-server-tracking' ); ?>
					</label>
					<p class="description">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: WooCommerce logs URL */
								__( 'Enable detailed logging for troubleshooting. View logs at <a href="%s">WooCommerce → Status → Logs</a>', 'funnelsheet-woo-server-tracking' ),
								admin_url( 'admin.php?page=wc-status&tab=logs' )
							)
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * AJAX handler for test event.
	 */
	public function ajax_test_event() {
		check_ajax_referer( 'FWST_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'funnelsheet-woo-server-tracking' ) ) );
		}

		$result = FWST_Dispatcher::send_test_event();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
