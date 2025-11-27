<?php
/**
 * Queue worker for Funnelsheet WooCommerce Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Worker class.
 */
class FWST_Worker {

	/**
	 * Single instance.
	 *
	 * @var FWST_Worker
	 */
	protected static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return FWST_Worker
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
		// Register action for queue processing.
		add_action( 'FWST_process_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Process event queue.
	 */
	public function process_queue() {
		FWST_Helpers::log_debug( 'Queue processing started' );

		// Get max retry attempts.
		$max_attempts = (int) FWST_Helpers::get_setting( 'max_retry_attempts', 5 );

		// Get pending events.
		$events = FWST_Database::get_pending_events( 10 );

		if ( empty( $events ) ) {
			FWST_Helpers::log_debug( 'No pending events to process' );
			return;
		}

		$processed = 0;
		$sent      = 0;
		$failed    = 0;

		foreach ( $events as $event ) {
			$event_id  = $event['id'];
			$attempts  = (int) $event['attempts'];
			$order_id  = $event['order_id'];

			// Check if we should retry (exponential backoff).
			if ( $attempts > 0 && ! $this->should_retry( $event ) ) {
				continue;
			}

			// Check if max attempts reached.
			if ( $attempts >= $max_attempts ) {
				FWST_Database::mark_as_failed( $event_id, 'Max retry attempts reached' );
				FWST_Helpers::log_debug( "Event #{$event_id} failed permanently (max attempts)", 'error' );
				$failed++;
				continue;
			}

			// Send event.
			$result = FWST_Dispatcher::send( $event );

			if ( $result['success'] ) {
				// Mark as sent.
				FWST_Database::mark_as_sent( $event_id );
				FWST_Helpers::log_debug( "Event #{$event_id} sent successfully: {$result['message']}" );
				$sent++;
			} else {
				// Increment attempts and store error.
				FWST_Database::increment_attempts( $event_id, $result['message'] );
				FWST_Helpers::log_debug( "Event #{$event_id} failed (attempt " . ( $attempts + 1 ) . "): {$result['message']}", 'warning' );
				$failed++;
			}

			$processed++;
		}

		FWST_Helpers::log_debug( "Queue processing completed: {$processed} processed, {$sent} sent, {$failed} failed" );
	}

	/**
	 * Check if event should be retried based on exponential backoff.
	 *
	 * @param array $event Event data.
	 * @return bool
	 */
	private function should_retry( $event ) {
		$attempts    = (int) $event['attempts'];
		$updated_at  = strtotime( $event['updated_at'] );
		
		// Calculate backoff time in seconds: 2^attempts minutes.
		$backoff_minutes = pow( 2, $attempts );
		$backoff_seconds = $backoff_minutes * 60;

		// Check if enough time has passed since last attempt.
		$time_since_last_attempt = time() - $updated_at;

		return $time_since_last_attempt >= $backoff_seconds;
	}

	/**
	 * Manually retry a specific event.
	 *
	 * @param int $event_id Event ID.
	 * @return array Result array with success and message.
	 */
	public static function retry_event( $event_id ) {
		$event = FWST_Database::get_event( $event_id );

		if ( ! $event ) {
			return array(
				'success' => false,
				'message' => 'Event not found',
			);
		}

		// Reset status to pending.
		FWST_Database::update_event_status( $event_id, 'pending' );

		// Send immediately.
		$result = FWST_Dispatcher::send( $event );

		if ( $result['success'] ) {
			FWST_Database::mark_as_sent( $event_id );
		} else {
			FWST_Database::increment_attempts( $event_id, $result['message'] );
		}

		return $result;
	}

	/**
	 * Retry all failed events.
	 *
	 * @return array Result with count of retried events.
	 */
	public static function retry_all_failed() {
		$events  = FWST_Database::get_events_by_status( 'failed', 100 );
		$retried = 0;

		foreach ( $events as $event ) {
			FWST_Database::update_event_status( $event['id'], 'pending' );
			$retried++;
		}

		return array(
			'success' => true,
			'message' => "Retried {$retried} failed events",
			'count'   => $retried,
		);
	}
}
