<?php
/**
 * Event log viewer for Funnelsheet WooCommerce Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event log class.
 */
class FWST_Event_Log {

	/**
	 * Single instance.
	 *
	 * @var FWST_Event_Log
	 */
	protected static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return FWST_Event_Log
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
		add_action( 'wp_ajax_FWST_retry_event', array( $this, 'ajax_retry_event' ) );
		add_action( 'wp_ajax_FWST_export_csv', array( $this, 'ajax_export_csv' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Server Tracking Events', 'funnelsheet-woo-server-tracking' ),
			__( 'Tracking Events', 'funnelsheet-woo-server-tracking' ),
			'manage_woocommerce',
			'wc-server-tracking-events',
			array( $this, 'render_event_log_page' )
		);
	}

	/**
	 * Render event log page.
	 */
	public function render_event_log_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'funnelsheet-woo-server-tracking' ) );
		}

		// Get filter.
		$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : 'all';

		// Get events.
		$events = FWST_Database::get_events_by_status( $status_filter, 100 );

		// Get counts.
		$count_all     = FWST_Database::get_event_count( 'all' );
		$count_pending = FWST_Database::get_event_count( 'pending' );
		$count_sent    = FWST_Database::get_event_count( 'sent' );
		$count_failed  = FWST_Database::get_event_count( 'failed' );
		?>
		<div class="wrap fwst-event-log">
			<h1><?php esc_html_e( 'Server-Side Tracking Events', 'funnelsheet-woo-server-tracking' ); ?></h1>

			<div class="fwst-stats">
				<div class="fwst-stat">
					<span class="label"><?php esc_html_e( 'Total Events:', 'funnelsheet-woo-server-tracking' ); ?></span>
					<span class="value"><?php echo esc_html( $count_all ); ?></span>
				</div>
				<div class="fwst-stat pending">
					<span class="label"><?php esc_html_e( 'Pending:', 'funnelsheet-woo-server-tracking' ); ?></span>
					<span class="value"><?php echo esc_html( $count_pending ); ?></span>
				</div>
				<div class="fwst-stat sent">
					<span class="label"><?php esc_html_e( 'Sent:', 'funnelsheet-woo-server-tracking' ); ?></span>
					<span class="value"><?php echo esc_html( $count_sent ); ?></span>
				</div>
				<div class="fwst-stat failed">
					<span class="label"><?php esc_html_e( 'Failed:', 'funnelsheet-woo-server-tracking' ); ?></span>
					<span class="value"><?php echo esc_html( $count_failed ); ?></span>
				</div>
			</div>

			<div class="fwst-filters">
				<form method="get">
					<input type="hidden" name="page" value="wc-server-tracking-events" />
					
					<label for="status-filter"><?php esc_html_e( 'Filter by Status:', 'funnelsheet-woo-server-tracking' ); ?></label>
					<select name="status_filter" id="status-filter" onchange="this.form.submit()">
						<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All', 'funnelsheet-woo-server-tracking' ); ?></option>
						<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'funnelsheet-woo-server-tracking' ); ?></option>
						<option value="sent" <?php selected( $status_filter, 'sent' ); ?>><?php esc_html_e( 'Sent', 'funnelsheet-woo-server-tracking' ); ?></option>
						<option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'funnelsheet-woo-server-tracking' ); ?></option>
					</select>

					<button type="button" id="fwst-export-csv" class="button button-secondary">
						<?php esc_html_e( 'Export to CSV', 'funnelsheet-woo-server-tracking' ); ?>
					</button>
				</form>
			</div>

			<?php if ( empty( $events ) ) : ?>
				<p><?php esc_html_e( 'No events found.', 'funnelsheet-woo-server-tracking' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Order ID', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Event Type', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Status', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Attempts', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Created', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Last Error', 'funnelsheet-woo-server-tracking' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'funnelsheet-woo-server-tracking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $events as $event ) : ?>
							<tr>
								<td><?php echo esc_html( $event['id'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $event['order_id'] . '&action=edit' ) ); ?>" target="_blank">
										#<?php echo esc_html( $event['order_id'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $event['event_type'] ); ?></td>
								<td>
									<span class="fwst-status fwst-status-<?php echo esc_attr( $event['status'] ); ?>">
										<?php echo esc_html( ucfirst( $event['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $event['attempts'] ); ?></td>
								<td><?php echo esc_html( $event['created_at'] ); ?></td>
								<td>
									<?php if ( ! empty( $event['last_error'] ) ) : ?>
										<span class="fwst-error" title="<?php echo esc_attr( $event['last_error'] ); ?>">
											<?php echo esc_html( wp_trim_words( $event['last_error'], 10 ) ); ?>
										</span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'failed' === $event['status'] || 'pending' === $event['status'] ) : ?>
										<button type="button" class="button button-small fwst-retry-event" data-event-id="<?php echo esc_attr( $event['id'] ); ?>">
											<?php esc_html_e( 'Retry', 'funnelsheet-woo-server-tracking' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for retry event.
	 */
	public function ajax_retry_event() {
		check_ajax_referer( 'FWST_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'funnelsheet-woo-server-tracking' ) ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;

		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid event ID', 'funnelsheet-woo-server-tracking' ) ) );
		}

		$result = FWST_Worker::retry_event( $event_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for CSV export.
	 */
	public function ajax_export_csv() {
		check_ajax_referer( 'FWST_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied', 'funnelsheet-woo-server-tracking' ) );
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		$events        = FWST_Database::get_events_by_status( $status_filter, 10000 );

		// Set headers for CSV download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=fwst-events-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv' );

		// Create output stream.
		$output = fopen( 'php://output', 'w' );

		// Add CSV headers.
		fputcsv( $output, array( 'ID', 'Order ID', 'Event Type', 'Status', 'Attempts', 'Created At', 'Last Error' ) );

		// Add data rows.
		foreach ( $events as $event ) {
			fputcsv(
				$output,
				array(
					$event['id'],
					$event['order_id'],
					$event['event_type'],
					$event['status'],
					$event['attempts'],
					$event['created_at'],
					$event['last_error'],
				)
			);
		}

		fclose( $output );
		exit;
	}
}
