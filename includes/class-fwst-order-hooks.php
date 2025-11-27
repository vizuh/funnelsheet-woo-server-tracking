<?php
/**
 * WooCommerce order hooks for Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order hooks class.
 */
class FWST_Order_Hooks {

	/**
	 * Single instance.
	 *
	 * @var FWST_Order_Hooks
	 */
	protected static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return FWST_Order_Hooks
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
		// Hook into order status changes.
		add_action( 'woocommerce_order_status_processing', array( $this, 'capture_purchase' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_purchase' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'capture_refund' ), 10, 1 );
	}

	/**
	 * Capture purchase event.
	 *
	 * @param int $order_id Order ID.
	 */
	public function capture_purchase( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if order should be tracked.
		if ( ! FWST_Helpers::should_track_order( $order ) ) {
			FWST_Helpers::log_debug( "Order #{$order_id} skipped (already queued or status not enabled)" );
			return;
		}

		// Build event data.
		$event_data = $this->build_purchase_event_data( $order );

		// Insert into queue.
		$event_id = FWST_Database::insert_event( $order_id, 'purchase', $event_data );

		if ( $event_id ) {
			// Mark order as queued.
			FWST_Helpers::mark_order_as_queued( $order );

			FWST_Helpers::log_debug( "Purchase event queued for order #{$order_id} (Event ID: {$event_id})" );
		} else {
			FWST_Helpers::log_debug( "Failed to queue purchase event for order #{$order_id}", 'error' );
		}
	}

	/**
	 * Capture refund event.
	 *
	 * @param int $order_id Order ID.
	 */
	public function capture_refund( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Build refund event data.
		$event_data = $this->build_refund_event_data( $order );

		// Insert into queue.
		$event_id = FWST_Database::insert_event( $order_id, 'refund', $event_data );

		if ( $event_id ) {
			FWST_Helpers::log_debug( "Refund event queued for order #{$order_id} (Event ID: {$event_id})" );
		} else {
			FWST_Helpers::log_debug( "Failed to queue refund event for order #{$order_id}", 'error' );
		}
	}

	/**
	 * Build purchase event data.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	private function build_purchase_event_data( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product ) {
				continue;
			}

			$items[] = array(
				'item_id'       => $product->get_id(),
				'item_name'     => $product->get_name(),
				'item_sku'      => $product->get_sku(),
				'item_category' => FWST_Helpers::get_product_categories( $product->get_id() ),
				'quantity'      => $item->get_quantity(),
				'price'         => FWST_Helpers::format_price( $item->get_total() / $item->get_quantity() ),
			);
		}

		// Get attribution data.
		$attribution = FWST_Helpers::get_attribution_data( $order );

		// Get or generate client_id.
		$client_id = isset( $attribution['client_id'] ) ? $attribution['client_id'] : FWST_Helpers::generate_client_id( $order->get_billing_email() );

		// Build event data.
		$event_data = array(
			'event_name'      => 'purchase',
			'client_id'       => $client_id,
			'transaction_id'  => $order->get_order_number(),
			'value'           => FWST_Helpers::format_price( $order->get_total() ),
			'currency'        => $order->get_currency(),
			'tax'             => FWST_Helpers::format_price( $order->get_total_tax() ),
			'shipping'        => FWST_Helpers::format_price( $order->get_shipping_total() ),
			'coupon'          => implode( ',', $order->get_coupon_codes() ),
			'items'           => $items,
			'user_data'       => array(
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'country'    => $order->get_billing_country(),
				'postal_code' => $order->get_billing_postcode(),
			),
			'attribution'     => $attribution,
			'timestamp'       => time(),
		);

		return $event_data;
	}

	/**
	 * Build refund event data.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	private function build_refund_event_data( $order ) {
		// Get attribution data.
		$attribution = FWST_Helpers::get_attribution_data( $order );

		// Get or generate client_id.
		$client_id = isset( $attribution['client_id'] ) ? $attribution['client_id'] : FWST_Helpers::generate_client_id( $order->get_billing_email() );

		// Build refund event data.
		$event_data = array(
			'event_name'     => 'refund',
			'client_id'      => $client_id,
			'transaction_id' => $order->get_order_number(),
			'value'          => FWST_Helpers::format_price( $order->get_total() ),
			'currency'       => $order->get_currency(),
			'attribution'    => $attribution,
			'timestamp'      => time(),
		);

		return $event_data;
	}
}
