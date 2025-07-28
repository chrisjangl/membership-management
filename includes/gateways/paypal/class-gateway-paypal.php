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
			// One-time payment events
			case 'CHECKOUT.ORDER.APPROVED':
				$this->handle_order_approved( $data );
				break;
			case 'PAYMENT.CAPTURE.COMPLETED':
				$this->handle_payment_completed( $data );
				break;
			case 'PAYMENT.CAPTURE.DENIED':
				$this->handle_payment_failed( $data );
				break;
			
			// Subscription events
			case 'BILLING.SUBSCRIPTION.ACTIVATED':
				$this->handle_subscription_activated( $data );
				break;
			case 'BILLING.SUBSCRIPTION.CANCELLED':
				$this->handle_subscription_cancelled( $data );
				break;
			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$this->handle_subscription_suspended( $data );
				break;
			case 'PAYMENT.SALE.COMPLETED':
				$this->handle_subscription_payment_completed( $data );
				break;
			case 'PAYMENT.SALE.DENIED':
				$this->handle_subscription_payment_failed( $data );
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

	/**
	 * Check if this gateway supports recurring payments.
	 *
	 * @return bool True - PayPal supports subscriptions.
	 */
	public function supports_subscriptions() {
		return true;
	}

	/**
	 * Create a PayPal recurring subscription.
	 *
	 * @param int    $member_id The member's CPT ID.
	 * @param float  $amount    The recurring amount to charge.
	 * @param string $interval  The billing interval (monthly, yearly, etc).
	 * @return array|WP_Error Subscription data on success, WP_Error on failure.
	 */
	public function create_subscription( $member_id, $amount, $interval ) {
		
		// Check if PayPal is configured
		if ( ! DCMM_Settings\is_paypal_configured() ) {
			return new \WP_Error( 'paypal_not_configured', 'PayPal is not properly configured. Please check your settings.' );
		}

		// Get member object
		$member = new \DCMM_Member( $member_id );
		if ( ! $member->exists() ) {
			return new \WP_Error( 'invalid_member', 'Member not found.' );
		}

		// Log subscription creation attempt
		$this->log_subscription_event( $member_id, "Creating PayPal subscription for $" . $amount . " " . $interval );

		// Get access token
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// First, create a product for this subscription
		$product_id = $this->create_or_get_product( $access_token );
		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		// Create a billing plan
		$plan_id = $this->create_billing_plan( $product_id, $amount, $interval, $access_token );
		if ( is_wp_error( $plan_id ) ) {
			return $plan_id;
		}

		// Create the subscription
		$subscription_data = $this->create_paypal_subscription( $member, $plan_id, $access_token );
		if ( is_wp_error( $subscription_data ) ) {
			return $subscription_data;
		}

		// Get approval URL for subscription
		$approval_url = $this->get_subscription_approval_url( $subscription_data );
		if ( ! $approval_url ) {
			return new \WP_Error( 'no_approval_url', 'Could not get PayPal subscription approval URL.' );
		}

		// Store subscription session data
		$this->store_subscription_session( $member_id, $subscription_data );

		// Log successful creation
		$this->log_subscription_event( $member_id, "PayPal subscription created: " . $subscription_data['id'] );

		return [
			'subscription_id' => $subscription_data['id'],
			'status' => $subscription_data['status'],
			'approval_url' => $approval_url,
			'plan_id' => $plan_id,
			'type' => 'paypal_subscription'
		];
	}

	/**
	 * Cancel a PayPal subscription.
	 *
	 * @param int    $member_id       The member's CPT ID.
	 * @param string $subscription_id The PayPal subscription ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function cancel_subscription( $member_id, $subscription_id ) {
		
		// Get access token
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Cancel subscription with PayPal
		$api_base = DCMM_Settings\get_paypal_api_base_url();
		
		$response = wp_remote_post( $api_base . '/v1/billing/subscriptions/' . $subscription_id . '/cancel', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			],
			'body' => json_encode([
				'reason' => 'Member requested cancellation'
			]),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 204 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new \WP_Error( 'paypal_cancel_failed', 'PayPal subscription cancellation failed: ' . ( $body['message'] ?? 'Unknown error' ) );
		}

		// Log cancellation
		$this->log_subscription_event( $member_id, "PayPal subscription cancelled: " . $subscription_id );

		return true;
	}

	/**
	 * Get PayPal subscription status.
	 *
	 * @param string $subscription_id The PayPal subscription ID.
	 * @return array|WP_Error Subscription status data on success, WP_Error on failure.
	 */
	public function get_subscription_status( $subscription_id ) {
		
		// Get access token
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Get subscription details from PayPal
		$api_base = DCMM_Settings\get_paypal_api_base_url();
		
		$response = wp_remote_get( $api_base . '/v1/billing/subscriptions/' . $subscription_id, [
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
		
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error( 'paypal_status_failed', 'PayPal subscription status check failed: ' . ( $body['message'] ?? 'Unknown error' ) );
		}

		return [
			'id' => $body['id'],
			'status' => strtolower( $body['status'] ),
			'next_billing_time' => $body['billing_info']['next_billing_time'] ?? null,
			'last_payment_amount' => $body['billing_info']['last_payment']['amount']['value'] ?? null,
			'plan_id' => $body['plan_id'] ?? null,
		];
	}

	/**
	 * Create or get existing product for subscriptions.
	 *
	 * @param string $access_token PayPal access token.
	 * @return string|WP_Error Product ID on success, WP_Error on failure.
	 */
	private function create_or_get_product( $access_token ) {
		
		// Check if we already have a stored product ID
		$product_id = get_option( 'dcmm_paypal_product_id' );
		if ( $product_id ) {
			return $product_id;
		}

		$api_base = DCMM_Settings\get_paypal_api_base_url();
		$site_name = get_bloginfo( 'name' );
		
		$product_data = [
			'name' => $site_name . ' Membership',
			'description' => 'Recurring membership dues for ' . $site_name,
			'type' => 'SERVICE',
			'category' => 'SOFTWARE',
		];

		$response = wp_remote_post( $api_base . '/v1/catalogs/products', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			],
			'body' => json_encode( $product_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
			return new \WP_Error( 'paypal_product_failed', 'PayPal product creation failed: ' . ( $body['message'] ?? 'Unknown error' ) );
		}

		// Store product ID for future use
		$product_id = $body['id'];
		update_option( 'dcmm_paypal_product_id', $product_id );

		return $product_id;
	}

	/**
	 * Create a billing plan for the subscription.
	 *
	 * @param string $product_id   PayPal product ID.
	 * @param float  $amount       Subscription amount.
	 * @param string $interval     Billing interval.
	 * @param string $access_token PayPal access token.
	 * @return string|WP_Error Plan ID on success, WP_Error on failure.
	 */
	private function create_billing_plan( $product_id, $amount, $interval, $access_token ) {
		
		// Convert interval to PayPal format
		$paypal_interval = $this->convert_interval_to_paypal( $interval );
		if ( is_wp_error( $paypal_interval ) ) {
			return $paypal_interval;
		}

		$api_base = DCMM_Settings\get_paypal_api_base_url();
		$site_name = get_bloginfo( 'name' );
		
		$plan_data = [
			'product_id' => $product_id,
			'name' => $site_name . ' Membership - ' . ucfirst( $interval ),
			'description' => ucfirst( $interval ) . ' membership dues for ' . $site_name,
			'status' => 'ACTIVE',
			'billing_cycles' => [
				[
					'frequency' => $paypal_interval,
					'tenure_type' => 'REGULAR',
					'sequence' => 1,
					'total_cycles' => 0, // Infinite cycles
					'pricing_scheme' => [
						'fixed_price' => [
							'value' => number_format( $amount, 2, '.', '' ),
							'currency_code' => 'USD',
						]
					]
				]
			],
			'payment_preferences' => [
				'auto_bill_outstanding' => true,
				'setup_fee_failure_action' => 'CONTINUE',
				'payment_failure_threshold' => 3,
			]
		];


		$response = wp_remote_post( $api_base . '/v1/billing/plans', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			],
			'body' => json_encode( $plan_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
			return new \WP_Error( 'paypal_plan_failed', 'PayPal plan creation failed: ' . ( $body['message'] ?? 'Unknown error' ) );
		}

		return $body['id'];
	}

	/**
	 * Create PayPal subscription.
	 *
	 * @param \DCMM_Member $member       Member object.
	 * @param string       $plan_id      PayPal plan ID.
	 * @param string       $access_token PayPal access token.
	 * @return array|WP_Error Subscription data on success, WP_Error on failure.
	 */
	private function create_paypal_subscription( $member, $plan_id, $access_token ) {
		
		$api_base = DCMM_Settings\get_paypal_api_base_url();
		$return_url = site_url( '/wp-json/dcmm/v1/paypal-subscription-return' );
		$cancel_url = site_url( '/wp-json/dcmm/v1/paypal-subscription-cancel' );

		// Get member details
		$member_email = $member->get_email();
		$member_first_name = $member->get_first_name();
		$member_last_name = $member->get_last_name();
		
		// If member email is empty, try to get it from the associated WordPress user
		if ( empty( $member_email ) ) {
			$wp_user_id = $member->get_wp_user_id();
			if ( $wp_user_id ) {
				$wp_user = get_userdata( $wp_user_id );
				if ( $wp_user ) {
					$member_email = $wp_user->user_email;
				}
			}
		}

		$subscription_data = [
			'plan_id' => $plan_id,
			'custom_id' => $member->get_member_id(),
			'application_context' => [
				'brand_name' => get_bloginfo( 'name' ),
				'user_action' => 'SUBSCRIBE_NOW',
				'payment_method' => [
					'payer_selected' => 'PAYPAL',
					'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
				],
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
			],
			'subscriber' => [
				'email_address' => $member_email,
				'name' => [
					'given_name' => $member_first_name ?: 'Member',
					'surname' => $member_last_name ?: '',
				]
			]
		];


		$response = wp_remote_post( $api_base . '/v1/billing/subscriptions', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			],
			'body' => json_encode( $subscription_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
			return new \WP_Error( 'paypal_subscription_failed', 'PayPal subscription creation failed: ' . ( $body['message'] ?? 'Unknown error' ) );
		}

		return $body;
	}

	/**
	 * Get subscription approval URL from PayPal subscription response.
	 *
	 * @param array $subscription_data PayPal subscription data.
	 * @return string|false Approval URL or false.
	 */
	private function get_subscription_approval_url( $subscription_data ) {
		
		if ( ! isset( $subscription_data['links'] ) ) {
			return false;
		}

		foreach ( $subscription_data['links'] as $link ) {
			if ( $link['rel'] === 'approve' ) {
				return $link['href'];
			}
		}

		return false;
	}

	/**
	 * Store subscription session data.
	 *
	 * @param int   $member_id         Member ID.
	 * @param array $subscription_data PayPal subscription data.
	 */
	private function store_subscription_session( $member_id, $subscription_data ) {
		
		$session_data = [
			'subscription_id' => $subscription_data['id'],
			'member_id' => $member_id,
			'status' => $subscription_data['status'],
			'plan_id' => $subscription_data['plan_id'],
			'created' => current_time( 'mysql' ),
		];

		update_post_meta( $member_id, 'dcmm_paypal_subscription_session', $session_data );
	}

	/**
	 * Convert interval to PayPal billing frequency format.
	 *
	 * @param string $interval Interval (monthly, yearly, etc).
	 * @return array|WP_Error PayPal frequency array or error.
	 */
	private function convert_interval_to_paypal( $interval ) {
		
		$intervals = [
			'monthly' => [
				'interval_unit' => 'MONTH',
				'interval_count' => 1,
			],
			'yearly' => [
				'interval_unit' => 'YEAR',
				'interval_count' => 1,
			],
			'seasonal' => [
				'interval_unit' => 'MONTH',
				'interval_count' => 6,
			],
		];

		if ( ! isset( $intervals[ $interval ] ) ) {
			return new \WP_Error( 'invalid_interval', 'Unsupported billing interval: ' . $interval );
		}

		return $intervals[ $interval ];
	}

	/**
	 * Find member ID by PayPal subscription ID.
	 *
	 * @param string $subscription_id PayPal subscription ID.
	 * @return int|false Member ID or false if not found.
	 */
	private function find_member_by_subscription_id( $subscription_id ) {
		
		global $wpdb;
		
		$member_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = 'dcmm_subscription_id' 
			AND meta_value = %s",
			$subscription_id
		) );

		return $member_id ? (int) $member_id : false;
	}

	/**
	 * Handle subscription activated webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_subscription_activated( $data ) {
		
		$subscription_id = $data['resource']['id'] ?? '';
		if ( empty( $subscription_id ) ) {
			return;
		}

		// Find member by subscription ID
		$member_id = $this->find_member_by_subscription_id( $subscription_id );
		if ( ! $member_id ) {
			return;
		}

		// Update member's subscription status
		$member = new \DCMM_Member( $member_id );
		$member->save( 'subscription_status', 'active' );

		// Log activation
		$this->log_subscription_event( $member_id, "PayPal subscription activated: " . $subscription_id );

		// Activate membership if not already active
		if ( ! $member->is_active() ) {
			$member->renew_membership( 'paypal_subscription_activated' );
		}
	}

	/**
	 * Handle subscription cancelled webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_subscription_cancelled( $data ) {
		
		$subscription_id = $data['resource']['id'] ?? '';
		if ( empty( $subscription_id ) ) {
			return;
		}

		// Find member by subscription ID
		$member_id = $this->find_member_by_subscription_id( $subscription_id );
		if ( ! $member_id ) {
			return;
		}

		// Update member's subscription status
		$member = new \DCMM_Member( $member_id );
		$member->save( 'subscription_status', 'cancelled' );
		$member->save( 'subscription_cancelled', current_time( 'mysql' ) );

		// Log cancellation
		$this->log_subscription_event( $member_id, "PayPal subscription cancelled via webhook: " . $subscription_id );

		// Fire cancellation hook for any additional processing
		do_action( 'dcmm_subscription_cancelled', $member_id, $subscription_id, 'paypal' );
	}

	/**
	 * Handle subscription suspended webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_subscription_suspended( $data ) {
		
		$subscription_id = $data['resource']['id'] ?? '';
		if ( empty( $subscription_id ) ) {
			return;
		}

		// Find member by subscription ID
		$member_id = $this->find_member_by_subscription_id( $subscription_id );
		if ( ! $member_id ) {
			return;
		}

		// Update member's subscription status
		$member = new \DCMM_Member( $member_id );
		$member->save( 'subscription_status', 'suspended' );

		// Log suspension
		$this->log_subscription_event( $member_id, "PayPal subscription suspended: " . $subscription_id );

		// Fire suspension hook for any additional processing
		do_action( 'dcmm_subscription_suspended', $member_id, $subscription_id, 'paypal' );
	}

	/**
	 * Handle subscription payment completed webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_subscription_payment_completed( $data ) {
		
		$payment_id = $data['resource']['id'] ?? '';
		$subscription_id = $data['resource']['billing_agreement_id'] ?? '';
		
		if ( empty( $subscription_id ) ) {
			return;
		}

		// Find member by subscription ID
		$member_id = $this->find_member_by_subscription_id( $subscription_id );
		if ( ! $member_id ) {
			return;
		}

		// Get payment details
		$payment_amount = $data['resource']['amount']['total'] ?? 0;
		$payment_currency = $data['resource']['amount']['currency'] ?? 'USD';

		// Process the recurring payment
		$member = new \DCMM_Member( $member_id );
		$payment_data = [
			'id' => $payment_id,
			'subscription_id' => $subscription_id,
			'amount' => $payment_amount,
			'currency' => $payment_currency,
			'status' => 'completed',
			'date' => current_time( 'mysql' ),
		];

		$member->process_recurring_payment( $payment_data );

		// Log payment
		$this->log_subscription_event( $member_id, "Recurring payment completed: $" . $payment_amount . " (Payment ID: " . $payment_id . ")" );
	}

	/**
	 * Handle subscription payment failed webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_subscription_payment_failed( $data ) {
		
		$payment_id = $data['resource']['id'] ?? '';
		$subscription_id = $data['resource']['billing_agreement_id'] ?? '';
		
		if ( empty( $subscription_id ) ) {
			return;
		}

		// Find member by subscription ID
		$member_id = $this->find_member_by_subscription_id( $subscription_id );
		if ( ! $member_id ) {
			return;
		}

		// Log payment failure
		$this->log_subscription_event( $member_id, "Recurring payment failed (Payment ID: " . $payment_id . ")" );

		// Fire payment failure hook for any additional processing
		do_action( 'dcmm_recurring_payment_failed', $member_id, $subscription_id, $payment_id, 'paypal' );
	}
}