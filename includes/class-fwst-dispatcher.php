<?php
/**
 * Event dispatcher for Funnelsheet WooCommerce Server-Side Tracking.
 *
 * @package WC_Server_Side_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatcher class.
 */
class FWST_Dispatcher {

	/**
	 * Send event to configured destination.
	 *
	 * @param array $event Event data from database.
	 * @return array Array with 'success' (bool) and 'message' (string).
	 */
	public static function send( $event ) {
		$destination = FWST_Helpers::get_setting( 'destination_type', 'ga4' );

		if ( 'sgtm' === $destination ) {
			return self::send_to_sgtm( $event );
		} else {
			return self::send_to_ga4( $event );
		}
	}

	/**
	 * Send event to GA4 Measurement Protocol.
	 *
	 * @param array $event Event data.
	 * @return array
	 */
	private static function send_to_ga4( $event ) {
		$measurement_id = FWST_Helpers::get_setting( 'ga4_measurement_id' );
		$api_secret     = FWST_Helpers::get_setting( 'ga4_api_secret' );

		if ( empty( $measurement_id ) || empty( $api_secret ) ) {
			return array(
				'success' => false,
				'message' => 'GA4 Measurement ID or API Secret not configured',
			);
		}

		// Decode event data.
		$event_data = json_decode( $event['event_data'], true );

		if ( empty( $event_data ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid event data',
			);
		}

		// Build GA4 Measurement Protocol payload.
		$payload = self::build_ga4_payload( $event_data );

		// Build URL.
		$url = add_query_arg(
			array(
				'measurement_id' => $measurement_id,
				'api_secret'     => $api_secret,
			),
			'https://www.google-analytics.com/mp/collect'
		);

		// Send request.
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 5,
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code || 204 === $response_code ) {
			return array(
				'success' => true,
				'message' => 'Event sent to GA4 successfully',
			);
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			return array(
				'success' => false,
				'message' => "GA4 returned status {$response_code}: {$response_body}",
			);
		}
	}

	/**
	 * Build GA4 Measurement Protocol payload.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	private static function build_ga4_payload( $event_data ) {
		$event_name = $event_data['event_name'];
		$client_id  = $event_data['client_id'];

		// Build event parameters based on event type.
		if ( 'purchase' === $event_name ) {
			$event_params = array(
				'transaction_id' => $event_data['transaction_id'],
				'value'          => $event_data['value'],
				'currency'       => $event_data['currency'],
				'tax'            => $event_data['tax'],
				'shipping'       => $event_data['shipping'],
				'items'          => $event_data['items'],
			);

			if ( ! empty( $event_data['coupon'] ) ) {
				$event_params['coupon'] = $event_data['coupon'];
			}
		} elseif ( 'refund' === $event_name ) {
			$event_params = array(
				'transaction_id' => $event_data['transaction_id'],
				'value'          => $event_data['value'],
				'currency'       => $event_data['currency'],
			);
		} else {
			$event_params = array();
		}

		// Build payload.
		$payload = array(
			'client_id' => $client_id,
			'events'    => array(
				array(
					'name'   => $event_name,
					'params' => $event_params,
				),
			),
		);

		// Add user data if available (for enhanced conversions).
		if ( ! empty( $event_data['user_data'] ) ) {
			$user_data = $event_data['user_data'];
			
			$payload['user_data'] = array();

			if ( ! empty( $user_data['email'] ) ) {
				$payload['user_data']['email_address'] = hash( 'sha256', strtolower( trim( $user_data['email'] ) ) );
			}
			if ( ! empty( $user_data['phone'] ) ) {
				$payload['user_data']['phone_number'] = hash( 'sha256', preg_replace( '/[^0-9]/', '', $user_data['phone'] ) );
			}
			if ( ! empty( $user_data['first_name'] ) ) {
				$payload['user_data']['address']['first_name'] = hash( 'sha256', strtolower( trim( $user_data['first_name'] ) ) );
			}
			if ( ! empty( $user_data['last_name'] ) ) {
				$payload['user_data']['address']['last_name'] = hash( 'sha256', strtolower( trim( $user_data['last_name'] ) ) );
			}
			if ( ! empty( $user_data['city'] ) ) {
				$payload['user_data']['address']['city'] = hash( 'sha256', strtolower( trim( $user_data['city'] ) ) );
			}
			if ( ! empty( $user_data['state'] ) ) {
				$payload['user_data']['address']['region'] = hash( 'sha256', strtolower( trim( $user_data['state'] ) ) );
			}
			if ( ! empty( $user_data['country'] ) ) {
				$payload['user_data']['address']['country'] = hash( 'sha256', strtolower( trim( $user_data['country'] ) ) );
			}
			if ( ! empty( $user_data['postal_code'] ) ) {
				$payload['user_data']['address']['postal_code'] = hash( 'sha256', strtolower( trim( $user_data['postal_code'] ) ) );
			}
		}

		return $payload;
	}

	/**
	 * Send event to sGTM.
	 *
	 * @param array $event Event data.
	 * @return array
	 */
	private static function send_to_sgtm( $event ) {
		$sgtm_url = FWST_Helpers::get_setting( 'sgtm_endpoint_url' );
		$sgtm_auth = FWST_Helpers::get_setting( 'sgtm_auth_header' );

		if ( empty( $sgtm_url ) ) {
			return array(
				'success' => false,
				'message' => 'sGTM endpoint URL not configured',
			);
		}

		// Decode event data.
		$event_data = json_decode( $event['event_data'], true );

		if ( empty( $event_data ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid event data',
			);
		}

		// Build headers.
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $sgtm_auth ) ) {
			$headers['Authorization'] = $sgtm_auth;
		}

		// Send request.
		$response = wp_remote_post(
			$sgtm_url,
			array(
				'body'    => wp_json_encode( $event_data ),
				'headers' => $headers,
				'timeout' => 5,
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code >= 200 && $response_code < 300 ) {
			return array(
				'success' => true,
				'message' => 'Event sent to sGTM successfully',
			);
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			return array(
				'success' => false,
				'message' => "sGTM returned status {$response_code}: {$response_body}",
			);
		}
	}

	/**
	 * Send test event.
	 *
	 * @return array
	 */
	public static function send_test_event() {
		// Build test event data.
		$test_event = array(
			'event_data' => wp_json_encode(
				array(
					'event_name'     => 'purchase',
					'client_id'      => '12345.67890',
					'transaction_id' => 'TEST-' . time(),
					'value'          => 99.99,
					'currency'       => 'USD',
					'tax'            => 9.99,
					'shipping'       => 5.00,
					'items'          => array(
						array(
							'item_id'   => 'test-product',
							'item_name' => 'Test Product',
							'quantity'  => 1,
							'price'     => 84.00,
						),
					),
					'user_data'      => array(
						'email' => 'test@example.com',
					),
					'timestamp'      => time(),
				)
			),
		);

		return self::send( $test_event );
	}
}
