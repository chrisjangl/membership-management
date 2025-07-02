<?php
/**
 * Abstract class for payment gateways.
 *
 * This class provides a base implementation for payment gateways in the DC Membership plugin.
 *
 * @package DC Membership
 * @since 1.1.0
 */

namespace DCMM\Gateways;

use DCMM_Settings;

abstract class Abstract_Gateway implements Payment_Gateway_Interface {

    /**
     * Get the payment gateway name.
     *
     * @return string The name of the payment gateway.
     */
    public function get_name() {
        return static::gateway_name;
    }


	/**
	 * Log a payment or event (for internal or admin use).
	 *
	 * @param int    $member_id The member's CPT ID.
	 * @param string $message   A log entry (e.g., "Payment started with PayPal").
	 */
	protected function log_payment_event( $member_id, $message ) {
		$logs = get_post_meta( $member_id, 'dcmm_payment_log', true );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		$user_id = get_current_user_id();
		// If no user is logged in (webhook/return context), use 0 and note it's a system event
		if ( ! $user_id ) {
			$user_id = 0;
			$message = '[System] ' . $message;
		}

		$logs[] = [
			'time'    => current_time( 'mysql' ),
			'user_id' => $user_id,
			'message' => $message,
		];

		$result = update_post_meta( $member_id, 'dcmm_payment_log', $logs );
		error_log( 'Payment log updated for member ' . $member_id . ': ' . ($result ? 'success' : 'failed') );
	}

    function handle_notification( $data ) {
        // This method should be implemented by subclasses to handle notifications from the payment gateway.
        // It can be used for webhooks or return URLs.
        throw new \Exception( 'handle_notification() must be implemented in the subclass.' );
    }

	/**
	 * Get the dues amount from settings.
	 *
	 * @return float
	 */
	protected function get_dues_amount() {
		$raw = DCMM_Settings\get_dues_amount();
		return is_numeric( $raw ) ? floatval( $raw ) : 0.0;
	}
}
