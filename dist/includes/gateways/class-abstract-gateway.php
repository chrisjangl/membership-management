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

	/**
	 * Default implementation for creating subscriptions.
	 * Subclasses should override this if they support recurring payments.
	 *
	 * @param int    $member_id The member's CPT ID.
	 * @param float  $amount    The recurring amount to charge.
	 * @param string $interval  The billing interval.
	 * @return WP_Error Always returns error for base implementation.
	 */
	public function create_subscription( $member_id, $amount, $interval ) {
		return new \WP_Error( 
			'subscription_not_supported', 
			sprintf( 'Recurring subscriptions are not supported by the %s gateway.', $this->get_name() )
		);
	}

	/**
	 * Default implementation for canceling subscriptions.
	 * Subclasses should override this if they support recurring payments.
	 *
	 * @param int    $member_id       The member's CPT ID.
	 * @param string $subscription_id The gateway's subscription ID.
	 * @return WP_Error Always returns error for base implementation.
	 */
	public function cancel_subscription( $member_id, $subscription_id ) {
		return new \WP_Error( 
			'subscription_not_supported', 
			sprintf( 'Subscription cancellation is not supported by the %s gateway.', $this->get_name() )
		);
	}

	/**
	 * Default implementation for getting subscription status.
	 * Subclasses should override this if they support recurring payments.
	 *
	 * @param string $subscription_id The gateway's subscription ID.
	 * @return WP_Error Always returns error for base implementation.
	 */
	public function get_subscription_status( $subscription_id ) {
		return new \WP_Error( 
			'subscription_not_supported', 
			sprintf( 'Subscription status checking is not supported by the %s gateway.', $this->get_name() )
		);
	}

	/**
	 * Check if this gateway supports recurring payments.
	 *
	 * @return bool True if gateway supports subscriptions.
	 */
	public function supports_subscriptions() {
		return false; // Override in subclasses that support subscriptions
	}

	/**
	 * Log a subscription event.
	 *
	 * @param int    $member_id The member's CPT ID.
	 * @param string $message   A log entry for the subscription.
	 */
	protected function log_subscription_event( $member_id, $message ) {
		$this->log_payment_event( $member_id, '[Subscription] ' . $message );
	}
}
