<?php
/**
 * Handles manual membership renewals and cancellations.
 * 
 * This file contains functions to handle manual renewals and cancellations of memberships.
 * 
 * @package DC Membership
 * @since 1.1.0
 */

add_action( 'admin_post_dcmm_manual_renew', 'dcmm_handle_manual_renew' );
add_action( 'admin_post_dcmm_manual_cancel', 'dcmm_handle_manual_cancel' );
add_action( 'admin_post_dcmm_offline_payment', 'dcmm_handle_offline_payment' );

/**
 * Handles the manual renewal of a membership.
 *
 * This function checks if the user has the capability to edit posts, verifies the nonce,
 * and then renews the membership for the specified member ID.
 * 
 * TODO: Consider adding a check to ensure the member is not already active before renewing.
 * TODO: Add a confirmation step before renewing.
 *
 * @return void
 */
function dcmm_handle_manual_renew() {
	if (
		! current_user_can( 'edit_posts' ) ||
		! isset( $_GET['member_id'] ) ||
		! wp_verify_nonce( $_GET['_wpnonce'], 'dcmm_manual_renew_' . $_GET['member_id'] )
	) {
		wp_die( 'Unauthorized' );
	}

	$member = new DCMM_Member( (int) $_GET['member_id'] );
	$member->renew_membership( 'manual' );

	// Send email if requested
	$send_email = isset( $_GET['send_email'] ) && $_GET['send_email'] === '1';
	if ( $send_email ) {
		$email_handler = DCMM_Email_Handler::get_instance();
		$email_handler->send_manual_renewal_email( $member, true );
	}

	wp_redirect( get_edit_post_link( $member->get_member_id(), 'url' ) . '&dcmm_msg=renewed' );
	exit;
}

/**
 * Handles the manual cancellation of a membership.
 *
 * This function checks if the user has the capability to edit posts, verifies the nonce,
 * and then cancels the membership for the specified member ID.
 * 
 * TODO: This should be in DCMM_Member class.
 * TODO: Add a confirmation step before cancellation.
 *
 * @return void
 */
function dcmm_handle_manual_cancel() {
	if (
		! current_user_can( 'edit_posts' ) ||
		! isset( $_GET['member_id'] ) ||
		! wp_verify_nonce( $_GET['_wpnonce'], 'dcmm_manual_cancel_' . $_GET['member_id'] )
	) {
		wp_die( 'Unauthorized' );
	}

	$member = new DCMM_Member( (int) $_GET['member_id'] );
	$member->set_status( 'inactive' ); // Use new simplified status system
	$member->save( 'end_date', current_time( 'Y-m-d' ) );
	$member->save( 'expiration_date', '' ); // Clear expiration date - canceled memberships don't expire

	// Log the cancellation
	$member->log( 'cancel_membership', 'manual' );

	wp_redirect( get_edit_post_link( $member->get_member_id(), 'url' ) . '&dcmm_msg=cancelled' );
	exit;
}

/**
 * Handles offline payment recording and membership renewal.
 *
 * This function processes the offline payment form submission, validates the data,
 * records the payment using the offline gateway, and renews the membership.
 *
 * @return void
 */
function dcmm_handle_offline_payment() {
	
	// Check user capabilities
	if ( ! current_user_can( 'edit_posts' ) ) {
		error_log( 'DCMM: User capability check failed' );
		wp_die( 'Unauthorized' );
	}

	// Verify nonce
	$member_id = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;
	if ( ! $member_id || ! wp_verify_nonce( $_POST['dcmm_offline_payment_nonce'], 'dcmm_offline_payment_' . $member_id ) ) {
		wp_die( 'Unauthorized or invalid request' );
	}

	// Check if offline payments are enabled
	if ( ! function_exists( 'DCMM_Settings\are_offline_payments_enabled' ) || ! DCMM_Settings\are_offline_payments_enabled() ) {
		wp_die( 'Offline payments are not enabled' );
	}

	try {
		// Validate required fields
		$required_fields = [ 'payment_amount', 'payment_method', 'payment_date' ];
		foreach ( $required_fields as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				throw new Exception( 'Missing required field: ' . $field );
			}
		}

		// Check if reference is required
		if ( DCMM_Settings\is_offline_reference_required() && empty( $_POST['payment_reference'] ) ) {
			throw new Exception( 'Reference number is required' );
		}

		// Validate payment amount
		$payment_amount = floatval( $_POST['payment_amount'] );
		if ( $payment_amount <= 0 ) {
			throw new Exception( 'Payment amount must be greater than 0' );
		}

		// Validate payment date
		$payment_date = sanitize_text_field( $_POST['payment_date'] );
		if ( ! strtotime( $payment_date ) ) {
			throw new Exception( 'Invalid payment date' );
		}

		// Prepare payment data
		$payment_data = [
			'amount'    => $payment_amount,
			'method'    => sanitize_text_field( $_POST['payment_method'] ),
			'reference' => sanitize_text_field( $_POST['payment_reference'] ),
			'date'      => $payment_date,
			'notes'     => sanitize_textarea_field( $_POST['payment_notes'] )
		];

		// Load the offline gateway
		require_once( DCMM_PATH . 'includes/gateways/class-gateway-offline.php' );
		$gateway = new DCMM\Gateways\Gateway_Offline();

		// Process the payment
		$payment_record = $gateway->complete_payment( $member_id, $payment_data );

		// Send email if requested
		$send_email = isset( $_POST['send_email_receipt'] ) && $_POST['send_email_receipt'] === '1';
		if ( $send_email ) {
			$member = new DCMM_Member( $member_id );
			$email_handler = DCMM_Email_Handler::get_instance();
			$email_handler->send_manual_renewal_email( $member, true );
		}

		// For AJAX requests, return JSON success response
		wp_send_json_success( array(
			'message' => 'Payment recorded and membership renewed successfully',
			'redirect_url' => get_edit_post_link( $member_id, 'url' ) . '&dcmm_msg=payment_recorded'
		) );

	} catch ( Exception $e ) {
		// Log the error
		error_log( 'Offline payment error: ' . $e->getMessage() );
		
		// For AJAX requests, return JSON error response
		wp_send_json_error( array(
			'message' => $e->getMessage(),
			'redirect_url' => get_edit_post_link( $member_id, 'url' ) . '&dcmm_msg=payment_error&error=' . urlencode( $e->getMessage() )
		) );
	}
}

/**
 * Displays admin notices for feedback on membership actions.
 *
 * This function checks the current page and post type, and displays appropriate notices
 * based on the action performed (renewal or cancellation).
 *
 * @return void
 */
function dcmm_admin_feedback_notices() {
	global $pagenow;
	if ( $pagenow !== 'post.php' || get_post_type() !== 'dcmm-member' ) return;

	if ( isset( $_GET['dcmm_msg'] ) ) {
		switch ( $_GET['dcmm_msg'] ) {
			case 'renewed':
				echo '<div class="notice notice-success is-dismissible"><p>Membership renewed.</p></div>';
				break;
			case 'cancelled':
				echo '<div class="notice notice-warning is-dismissible"><p>Membership cancelled.</p></div>';
				break;
			case 'payment_recorded':
				echo '<div class="notice notice-success is-dismissible"><p>Payment recorded and membership renewed successfully.</p></div>';
				break;
			case 'payment_error':
				$error_message = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : 'Unknown error occurred';
				echo '<div class="notice notice-error is-dismissible"><p>Payment error: ' . esc_html( $error_message ) . '</p></div>';
				break;
		}
	}
}
add_action( 'admin_notices', 'dcmm_admin_feedback_notices' );
