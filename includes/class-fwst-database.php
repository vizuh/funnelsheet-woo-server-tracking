<?php
/**
 * Database operations for Funnelsheet WooCommerce Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class.
 */
class FWST_Database {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private static $table_name = 'wc_server_events';

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	/**
	 * Create database table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(20) NOT NULL DEFAULT 'purchase',
			event_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int(11) NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY status (status),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert event into queue.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $event_type Event type (purchase, refund).
	 * @param array  $event_data Event data.
	 * @return int|false Event ID on success, false on failure.
	 */
	public static function insert_event( $order_id, $event_type, $event_data ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$result = $wpdb->insert(
			$table_name,
			array(
				'order_id'   => $order_id,
				'event_type' => $event_type,
				'event_data' => wp_json_encode( $event_data ),
				'status'     => 'pending',
				'attempts'   => 0,
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get pending events.
	 *
	 * @param int $limit Number of events to retrieve.
	 * @return array
	 */
	public static function get_pending_events( $limit = 10 ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE status = 'pending' 
				ORDER BY created_at ASC 
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get event by ID.
	 *
	 * @param int $event_id Event ID.
	 * @return array|null
	 */
	public static function get_event( $event_id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$event_id
			),
			ARRAY_A
		);

		return $result;
	}

	/**
	 * Update event status.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $status Status (pending, sent, failed).
	 * @param string $error Error message (optional).
	 * @return bool
	 */
	public static function update_event_status( $event_id, $status, $error = null ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$data = array(
			'status' => $status,
		);

		$format = array( '%s' );

		if ( ! is_null( $error ) ) {
			$data['last_error'] = $error;
			$format[]           = '%s';
		}

		return $wpdb->update(
			$table_name,
			$data,
			array( 'id' => $event_id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Mark event as sent.
	 *
	 * @param int $event_id Event ID.
	 * @return bool
	 */
	public static function mark_as_sent( $event_id ) {
		return self::update_event_status( $event_id, 'sent' );
	}

	/**
	 * Mark event as failed.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $error Error message.
	 * @return bool
	 */
	public static function mark_as_failed( $event_id, $error ) {
		return self::update_event_status( $event_id, 'failed', $error );
	}

	/**
	 * Increment event attempts.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $error Error message (optional).
	 * @return bool
	 */
	public static function increment_attempts( $event_id, $error = null ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$data = array(
			'attempts' => $wpdb->prepare( 'attempts + %d', 1 ),
		);

		if ( ! is_null( $error ) ) {
			$data['last_error'] = $error;
		}

		// Use raw query for increment.
		$sql = "UPDATE {$table_name} SET attempts = attempts + 1";

		if ( ! is_null( $error ) ) {
			$sql .= $wpdb->prepare( ', last_error = %s', $error );
		}

		$sql .= $wpdb->prepare( ' WHERE id = %d', $event_id );

		return $wpdb->query( $sql );
	}

	/**
	 * Get events by status.
	 *
	 * @param string $status Status filter (all, pending, sent, failed).
	 * @param int    $limit Number of events.
	 * @param int    $offset Offset.
	 * @return array
	 */
	public static function get_events_by_status( $status = 'all', $limit = 100, $offset = 0 ) {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( 'all' === $status ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} 
					ORDER BY created_at DESC 
					LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} 
					WHERE status = %s 
					ORDER BY created_at DESC 
					LIMIT %d OFFSET %d",
					$status,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		return $results ? $results : array();
	}

	/**
	 * Get event count by status.
	 *
	 * @param string $status Status filter.
	 * @return int
	 */
	public static function get_event_count( $status = 'all' ) {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( 'all' === $status ) {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		} else {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
					$status
				)
			);
		}

		return (int) $count;
	}

	/**
	 * Delete old events.
	 *
	 * @param int $days Delete events older than X days.
	 * @return int Number of deleted rows.
	 */
	public static function delete_old_events( $days = 30 ) {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				WHERE status = 'sent' 
				AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
