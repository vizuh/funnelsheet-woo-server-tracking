<?php
/**
 * Helper functions for Funnelsheet WooCommerce Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers class.
 */
class FWST_Helpers {

	/**
	 * Generate client ID from order email.
	 *
	 * @param string $email Customer email.
	 * @return string
	 */
	public static function generate_client_id( $email ) {
		// Format: {timestamp}.{hash} (similar to GA4 client_id format).
		$hash = substr( md5( $email ), 0, 10 );
		return time() . '.' . $hash;
	}

	/**
	 * Get attribution data from order.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	public static function get_attribution_data( $order ) {
		$attribution = array();

		// Try to get ClickTrail attribution data.
		$clicktrail_data = $order->get_meta( '_clicktrail_attribution', true );
		if ( ! empty( $clicktrail_data ) && is_array( $clicktrail_data ) ) {
			$attribution = $clicktrail_data;
		}

		// Try to get individual UTM parameters.
		$utm_params = array(
			'utm_source'   => $order->get_meta( '_utm_source', true ),
			'utm_medium'   => $order->get_meta( '_utm_medium', true ),
			'utm_campaign' => $order->get_meta( '_utm_campaign', true ),
			'utm_term'     => $order->get_meta( '_utm_term', true ),
			'utm_content'  => $order->get_meta( '_utm_content', true ),
		);

		// Filter out empty values.
		$utm_params = array_filter( $utm_params );

		if ( ! empty( $utm_params ) ) {
			$attribution = array_merge( $attribution, $utm_params );
		}

		// Try to get GA client_id.
		$ga_client_id = $order->get_meta( '_ga_client_id', true );
		if ( ! empty( $ga_client_id ) ) {
			$attribution['client_id'] = $ga_client_id;
		}

		// Try to get fbp/fbc.
		$fbp = $order->get_meta( '_fbp', true );
		$fbc = $order->get_meta( '_fbc', true );
		if ( ! empty( $fbp ) ) {
			$attribution['fbp'] = $fbp;
		}
		if ( ! empty( $fbc ) ) {
			$attribution['fbc'] = $fbc;
		}

		return $attribution;
	}

	/**
	 * Sanitize event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public static function sanitize_event_data( $event_data ) {
		// Recursively sanitize array.
		$sanitized = array();

		foreach ( $event_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_event_data( $value );
			} elseif ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = $value;
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 * @param string $level Log level (info, warning, error).
	 */
	public static function log_debug( $message, $level = 'info' ) {
		// Only log if debug mode is enabled.
		$debug_mode = get_option( 'FWST_debug_mode', 'no' );

		if ( 'yes' !== $debug_mode ) {
			return;
		}

		// Use WooCommerce logger if available.
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'funnelsheet-woo-server-tracking' ) );
		} else {
			// Fallback to error_log.
			error_log( '[WC SST] ' . $message );
		}
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		return get_option( 'FWST_' . $key, $default );
	}

	/**
	 * Format price for tracking.
	 *
	 * @param float  $price Price.
	 * @param string $currency Currency code.
	 * @return float
	 */
	public static function format_price( $price, $currency = 'USD' ) {
		// Return price as float with 2 decimals.
		return round( floatval( $price ), 2 );
	}

	/**
	 * Get product categories for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string Comma-separated category names.
	 */
	public static function get_product_categories( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = $term->name;
		}

		return implode( ', ', $categories );
	}

	/**
	 * Check if order should be tracked.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public static function should_track_order( $order ) {
		// Check if order has already been queued.
		$already_queued = $order->get_meta( '_FWST_event_queued', true );
		if ( 'yes' === $already_queued ) {
			return false;
		}

		// Check if order status should be tracked.
		$status                 = $order->get_status();
		$track_on_processing    = self::get_setting( 'track_on_processing', 'yes' );
		$track_on_completed     = self::get_setting( 'track_on_completed', 'yes' );

		$allowed_statuses = array();
		if ( 'yes' === $track_on_processing ) {
			$allowed_statuses[] = 'processing';
		}
		if ( 'yes' === $track_on_completed ) {
			$allowed_statuses[] = 'completed';
		}

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Mark order as queued.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function mark_order_as_queued( $order ) {
		$order->update_meta_data( '_FWST_event_queued', 'yes' );
		$order->save();
	}
}
