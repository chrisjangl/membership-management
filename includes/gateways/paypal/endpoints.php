<?php
/**
 * PayPal REST API Endpoints for DC Membership Plugin
 *
 * Handles PayPal return URLs, cancel URLs, and webhook notifications.
 *
 * @package DC Membership
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Register PayPal REST API endpoints.
 */
function dcmm_register_paypal_endpoints() {
    
    // PayPal return URL (after successful payment)
    register_rest_route( 'dcmm/v1', '/paypal-return', [
        'methods' => 'GET',
        'callback' => 'dcmm_handle_paypal_return',
        'permission_callback' => '__return_true', // Public endpoint
    ] );

    // PayPal cancel URL (user cancelled payment)
    register_rest_route( 'dcmm/v1', '/paypal-cancel', [
        'methods' => 'GET',
        'callback' => 'dcmm_handle_paypal_cancel',
        'permission_callback' => '__return_true', // Public endpoint
    ] );

    // PayPal webhook URL (payment notifications)
    register_rest_route( 'dcmm/v1', '/paypal-webhook', [
        'methods' => 'POST',
        'callback' => 'dcmm_handle_paypal_webhook',
        'permission_callback' => '__return_true', // Public endpoint
    ] );
}
add_action( 'rest_api_init', 'dcmm_register_paypal_endpoints' );

/**
 * Handle PayPal return (successful payment approval).
 *
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response The response.
 */
function dcmm_handle_paypal_return( $request ) {
    
    $order_id = $request->get_param( 'token' ); // PayPal sends order ID as 'token'
    $payer_id = $request->get_param( 'PayerID' );

    // Log the return parameters for debugging
    error_log( 'PayPal return - Order ID: ' . $order_id );
    error_log( 'PayPal return - Payer ID: ' . $payer_id );

    if ( empty( $order_id ) ) {
        return new WP_REST_Response( [
            'error' => 'Missing PayPal order ID'
        ], 400 );
    }

    // Find member by order ID
    $member_id = dcmm_find_member_by_paypal_order( $order_id );
    if ( ! $member_id ) {
        return new WP_REST_Response( [
            'error' => 'Payment session not found for order: ' . $order_id
        ], 404 );
    }

    // Get PayPal gateway and complete payment
    $gateway = new \DCMM\Gateways\Gateway_PayPal();
    
    // Capture the payment with PayPal
    $capture_result = dcmm_capture_paypal_payment( $order_id );
    if ( is_wp_error( $capture_result ) ) {
        return dcmm_redirect_with_error( 
            'Payment capture failed: ' . $capture_result->get_error_message() 
        );
    }

    // Complete the payment in our system
    $payment_data = [
        'id' => $capture_result['id'],
        'orderID' => $order_id,
        'status' => 'COMPLETED',
        'payer_id' => $payer_id,
    ];

    $gateway->complete_payment( $member_id, $payment_data );

    // Redirect to success page
    return dcmm_redirect_with_success( 'Payment completed successfully!' );
}

/**
 * Handle PayPal cancel (user cancelled payment).
 *
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response The response.
 */
function dcmm_handle_paypal_cancel( $request ) {
    
    $order_id = $request->get_param( 'token' );

    if ( ! empty( $order_id ) ) {
        // Find member and log cancellation
        $member_id = dcmm_find_member_by_paypal_order( $order_id );
        if ( $member_id ) {
            // Log the cancellation
            $logs = get_post_meta( $member_id, 'dcmm_payment_log', true );
            if ( ! is_array( $logs ) ) {
                $logs = [];
            }

            $logs[] = [
                'time'    => current_time( 'mysql' ),
                'user_id' => get_current_user_id(),
                'message' => 'PayPal payment cancelled by user: ' . $order_id,
            ];

            update_post_meta( $member_id, 'dcmm_payment_log', $logs );
        }
    }

    // Redirect to cancellation page
    return dcmm_redirect_with_error( 'Payment was cancelled.' );
}

/**
 * Handle PayPal webhook notifications.
 *
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response The response.
 */
function dcmm_handle_paypal_webhook( $request ) {
    
    $webhook_data = $request->get_json_params();
    
    if ( empty( $webhook_data ) ) {
        return new WP_REST_Response( [
            'error' => 'No webhook data received'
        ], 400 );
    }

    // Get PayPal gateway and handle notification
    $gateway = new \DCMM\Gateways\Gateway_PayPal();
    $gateway->handle_notification( $webhook_data );

    // This will exit with 200 response
}

/**
 * Capture PayPal payment after approval.
 *
 * @param string $order_id PayPal order ID.
 * @return array|WP_Error Capture data or error.
 */
function dcmm_capture_paypal_payment( $order_id ) {
    
    // Get access token
    $gateway = new \DCMM\Gateways\Gateway_PayPal();
    $access_token = $gateway->get_access_token();
    if ( is_wp_error( $access_token ) ) {
        return $access_token;
    }

    $api_base = \DCMM_Settings\get_paypal_api_base_url();
    
    $response = wp_remote_post( $api_base . '/v2/checkout/orders/' . $order_id . '/capture', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ],
        'body' => '{}',
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
        return new WP_Error( 'paypal_capture_failed', 'PayPal capture failed: ' . ( $body['message'] ?? 'Unknown error' ) );
    }

    // Return the capture details
    $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? null;
    if ( ! $capture ) {
        return new WP_Error( 'no_capture_data', 'No capture data in PayPal response' );
    }

    return $capture;
}

/**
 * Find member ID by PayPal order ID.
 *
 * @param string $order_id PayPal order ID.
 * @return int|false Member ID or false if not found.
 */
function dcmm_find_member_by_paypal_order( $order_id ) {
    
    global $wpdb;
    
    // First, let's get all PayPal sessions to debug
    $all_sessions = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
        WHERE meta_key = 'dcmm_paypal_session'"
    );
    
    // Search through sessions manually since serialized data search can be unreliable
    foreach ( $all_sessions as $session ) {
        $session_data = maybe_unserialize( $session->meta_value );
        if ( is_array( $session_data ) && isset( $session_data['order_id'] ) && $session_data['order_id'] === $order_id ) {
            return (int) $session->post_id;
        }
    }
    
    // If not found, log for debugging
    error_log( 'PayPal order ID not found: ' . $order_id );
    error_log( 'Available sessions: ' . print_r( $all_sessions, true ) );
    
    return false;
}

/**
 * Redirect to member dashboard with success message.
 *
 * @param string $message Success message.
 * @return WP_REST_Response Redirect response.
 */
function dcmm_redirect_with_success( $message ) {
    
    $dashboard_url = home_url( '/member-dashboard/' );
    $redirect_url = add_query_arg( [
        'payment' => 'success',
        'message' => urlencode( $message )
    ], $dashboard_url );

    return new WP_REST_Response( '', 302, [
        'Location' => $redirect_url
    ] );
}

/**
 * Redirect to member dashboard with error message.
 *
 * @param string $message Error message.
 * @return WP_REST_Response Redirect response.
 */
function dcmm_redirect_with_error( $message ) {
    
    $dashboard_url = home_url( '/member-dashboard/' );
    $redirect_url = add_query_arg( [
        'payment' => 'error',
        'message' => urlencode( $message )
    ], $dashboard_url );

    return new WP_REST_Response( '', 302, [
        'Location' => $redirect_url
    ] );
}