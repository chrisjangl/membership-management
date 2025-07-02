<?php
/**
 * PayPal Gateway for DC Membership Plugin
 *
 * This class implements the PayPal payment gateway for the DC Membership plugin.
 * Uses PayPal REST API for secure payment processing.
 *
 * @package DC Membership
 * @since 1.1.0
 */

namespace DCMM\Gateways;

use DCMM_Settings;

class Gateway_PayPal extends Abstract_Gateway {

	protected static $gateway_name = 'PayPal';

	/**
	 * Start payment process with PayPal.
	 *
	 * @param \DCMM_Member $member The member object.
	 * @param float        $amount The amount to charge.
	 * @return array|WP_Error Payment data or error.
	 */
	public function start_payment( $member, $amount ) {
		
		// Check if PayPal is configured
		if ( ! DCMM_Settings\is_paypal_configured() ) {
			return new \WP_Error( 'paypal_not_configured', 'PayPal is not properly configured. Please check your settings.' );
		}

		// Log event
		$this->log_payment_event( $member->get_member_id(), "Starting PayPal payment for $" . $amount );

		// Get access token
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Create PayPal order
		$order_data = $this->create_paypal_order( $member, $amount, $access_token );
		if ( is_wp_error( $order_data ) ) {
			return $order_data;
		}

		// Store order data for later verification
		$this->store_payment_session( $member->get_member_id(), $order_data );

		// Get approval URL for redirect
		$approval_url = $this->get_approval_url( $order_data );
		if ( ! $approval_url ) {
			return new \WP_Error( 'no_approval_url', 'Could not get PayPal approval URL.' );
		}

		// Return redirect data instead of immediately redirecting
		// This allows AJAX requests to handle the redirect properly
		return [
			'redirect_url' => $approval_url,
			'order_id' => $order_data['id'],
			'type' => 'paypal_redirect'
		];
	}

	/**
	 * Complete payment after PayPal approval.
	 *
	 * @param int   $member_id     The member's CPT ID.
	 * @param array $payment_data  Payment data from PayPal.
	 */
	public function complete_payment( $member_id, $payment_data ) {
		
		error_log( 'PayPal complete_payment called for member ID: ' . $member_id );
		error_log( 'Payment data: ' . print_r( $payment_data, true ) );
		
		// Verify payment with PayPal
		$verification = $this->verify_payment( $payment_data );
		if ( is_wp_error( $verification ) ) {
			$error_msg = "Payment verification failed: " . $verification->get_error_message();
			$this->log_payment_event( $member_id, $error_msg );
			error_log( 'PayPal verification failed: ' . $error_msg );
			return;
		}

		error_log( 'PayPal payment verified successfully' );

		// Record transaction meta
		update_post_meta( $member_id, 'dcmm_last_payment', $payment_data );
		update_post_meta( $member_id, 'dcmm_last_dues_payment', current_time( 'mysql' ) );

		// Log successful payment
		$this->log_payment_event( $member_id, "Completed PayPal payment: " . $payment_data['id'] );

		// Renew membership
		$member = new \DCMM_Member( $member_id );
		error_log( 'About to call renew_membership for member: ' . $member_id );
		
		// Get member's email for better logging
		$member_email = $member->get( 'email' );
		$renewal_context = 'paypal (self-renewal by ' . $member_email . ')';
		
		$renewal_result = $member->renew_membership( $renewal_context );
		error_log( 'Membership renewal result: ' . print_r( $renewal_result, true ) );
	}

	/**
	 * Handle PayPal webhook notifications.
	 *
	 * @param array $data Webhook data from PayPal.
	 */
	public function handle_notification( $data ) {
		
		// Verify webhook signature
		if ( ! $this->verify_webhook_signature( $data ) ) {
			wp_die( 'Invalid webhook signature', 'Webhook Error', [ 'response' => 403 ] );
		}

		// Handle different event types
		$event_type = $data['event_type'] ?? '';
		
		switch ( $event_type ) {
			case 'CHECKOUT.ORDER.APPROVED':
				$this->handle_order_approved( $data );
				break;
			case 'PAYMENT.CAPTURE.COMPLETED':
				$this->handle_payment_completed( $data );
				break;
			case 'PAYMENT.CAPTURE.DENIED':
				$this->handle_payment_failed( $data );
				break;
		}

		// Respond to PayPal
		http_response_code( 200 );
		echo 'OK';
		exit;
	}

	/**
	 * Get PayPal OAuth access token.
	 *
	 * @return string|WP_Error Access token or error.
	 */
	public function get_access_token() {
		
		$client_id = DCMM_Settings\get_paypal_client_id();
		$client_secret = DCMM_Settings\get_paypal_client_secret();
		$api_base = DCMM_Settings\get_paypal_api_base_url();

		$response = wp_remote_post( $api_base . '/v1/oauth2/token', [
			'headers' => [
				'Accept' => 'application/json',
				'Accept-Language' => 'en_US',
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
			],
			'body' => 'grant_type=client_credentials',
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error( 'paypal_auth_failed', 'PayPal authentication failed: ' . ( $body['error_description'] ?? 'Unknown error' ) );
		}

		return $body['access_token'];
	}

	/**
	 * Create PayPal order.
	 *
	 * @param \DCMM_Member $member      The member object.
	 * @param float        $amount      Payment amount.
	 * @param string       $access_token PayPal access token.
	 * @return array|WP_Error Order data or error.
	 */
	private function create_paypal_order( $member, $amount, $access_token ) {
		
		$api_base = DCMM_Settings\get_paypal_api_base_url();
		$return_url = site_url( '/wp-json/dcmm/v1/paypal-return' );
		$cancel_url = site_url( '/wp-json/dcmm/v1/paypal-cancel' );

		$order_data = [
			'intent' => 'CAPTURE',
			'purchase_units' => [
				[
					'amount' => [
						'currency_code' => 'USD',
						'value' => number_format( $amount, 2, '.', '' ),
					],
					'description' => 'Membership dues renewal',
					'custom_id' => $member->get_member_id(),
				]
			],
			'application_context' => [
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
				'brand_name' => get_bloginfo( 'name' ),
				'landing_page' => 'LOGIN',
				'user_action' => 'PAY_NOW',
			]
		];

		$response = wp_remote_post( $api_base . '/v2/checkout/orders', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			],
			'body' => json_encode( $order_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
			return new \WP_Error( 'paypal_order_failed', 'PayPal order creation failed: ' . ( $body['message'] ?? 'Unknown error' ) );
		}

		return $body;
	}

	/**
	 * Get approval URL from PayPal order response.
	 *
	 * @param array $order_data PayPal order data.
	 * @return string|false Approval URL or false.
	 */
	private function get_approval_url( $order_data ) {
		
		if ( ! isset( $order_data['links'] ) ) {
			return false;
		}

		foreach ( $order_data['links'] as $link ) {
			if ( $link['rel'] === 'approve' ) {
				return $link['href'];
			}
		}

		return false;
	}

	/**
	 * Store payment session data.
	 *
	 * @param int   $member_id  Member ID.
	 * @param array $order_data PayPal order data.
	 */
	private function store_payment_session( $member_id, $order_data ) {
		
		$session_data = [
			'order_id' => $order_data['id'],
			'member_id' => $member_id,
			'amount' => $order_data['purchase_units'][0]['amount']['value'],
			'created' => current_time( 'mysql' ),
		];

		update_post_meta( $member_id, 'dcmm_paypal_session', $session_data );
	}

	/**
	 * Verify payment with PayPal.
	 *
	 * @param array $payment_data Payment data.
	 * @return bool|WP_Error True if verified, error otherwise.
	 */
	private function verify_payment( $payment_data ) {
		
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$order_id = $payment_data['orderID'] ?? '';
		if ( empty( $order_id ) ) {
			return new \WP_Error( 'missing_order_id', 'Missing PayPal order ID.' );
		}

		$api_base = DCMM_Settings\get_paypal_api_base_url();
		
		$response = wp_remote_get( $api_base . '/v2/checkout/orders/' . $order_id, [
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type' => 'application/json',
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		error_log( 'PayPal verification response code: ' . wp_remote_retrieve_response_code( $response ) );
		error_log( 'PayPal verification response body: ' . print_r( $body, true ) );
		
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error( 'paypal_verification_failed', 'PayPal verification failed.' );
		}

		// Check if payment is approved or completed
		$valid_statuses = [ 'APPROVED', 'COMPLETED' ];
		if ( ! in_array( $body['status'], $valid_statuses ) ) {
			return new \WP_Error( 'payment_not_approved', 'Payment status is: ' . $body['status'] . '. Expected APPROVED or COMPLETED.' );
		}

		error_log( 'PayPal payment status verified: ' . $body['status'] );
		return true;
	}

	/**
	 * Verify PayPal webhook signature.
	 *
	 * @param array $data Webhook data.
	 * @return bool True if signature is valid.
	 */
	private function verify_webhook_signature( $data ) {
		
		// In a production environment, you should verify the webhook signature
		// using PayPal's webhook verification API. For now, we'll do basic validation.
		
		$webhook_id = DCMM_Settings\get_paypal_webhook_id();
		if ( empty( $webhook_id ) ) {
			return false;
		}

		// Basic validation - check if required fields exist
		return isset( $data['event_type'] ) && isset( $data['resource'] );
	}

	/**
	 * Handle order approved webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_order_approved( $data ) {
		
		$order_id = $data['resource']['id'] ?? '';
		if ( empty( $order_id ) ) {
			return;
		}

		// Find member by order ID
		$member_id = $this->find_member_by_order_id( $order_id );
		if ( ! $member_id ) {
			return;
		}

		$this->log_payment_event( $member_id, "PayPal order approved: " . $order_id );
	}

	/**
	 * Handle payment completed webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_payment_completed( $data ) {
		
		$capture_id = $data['resource']['id'] ?? '';
		$order_id = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
		
		if ( empty( $order_id ) ) {
			return;
		}

		// Find member by order ID
		$member_id = $this->find_member_by_order_id( $order_id );
		if ( ! $member_id ) {
			return;
		}

		// Complete the payment
		$payment_data = [
			'id' => $capture_id,
			'orderID' => $order_id,
			'status' => 'COMPLETED',
		];

		$this->complete_payment( $member_id, $payment_data );
	}

	/**
	 * Handle payment failed webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_payment_failed( $data ) {
		
		$order_id = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
		
		if ( empty( $order_id ) ) {
			return;
		}

		// Find member by order ID
		$member_id = $this->find_member_by_order_id( $order_id );
		if ( ! $member_id ) {
			return;
		}

		$this->log_payment_event( $member_id, "PayPal payment failed for order: " . $order_id );
	}

	/**
	 * Find member ID by PayPal order ID.
	 *
	 * @param string $order_id PayPal order ID.
	 * @return int|false Member ID or false if not found.
	 */
	private function find_member_by_order_id( $order_id ) {
		
		global $wpdb;
		
		$member_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = 'dcmm_paypal_session' 
			AND meta_value LIKE %s",
			'%"order_id":"' . $order_id . '"%'
		) );

		return $member_id ? (int) $member_id : false;
	}
}