<?php
/**
 * Interface for payment gateways in the DC Membership plugin.
 *
 * This interface defines the methods required for payment gateways to implement
 * in order to integrate with the DC Membership plugin.
 *
 * @package DC Membership
 * @since 1.1.0
 */

namespace DCMM\Gateways;

interface Payment_Gateway_Interface {

    /**
     * Initiate a payment process (redirect to PayPal, show Stripe modal, etc).
	 *
	 * @param \DCMM_Member $member The member object.
	 * @param float        $amount The amount to charge.
     *
     * @return bool True on success, false on failure.
     */
    public function start_payment( $member_id, $amount );

    /**
	 * Complete a payment after return from gateway or webhook.
	 *
	 * @param int   $member_id     The member's CPT ID or WP_User ID.
	 * @param array $payment_data  Gateway-provided data (txn ID, status, etc).
	 */
	public function complete_payment( $member_id, $payment_data );

    /**
     * Handle a payment notification from the gateway.
     *
     * @param array $data The data received from the payment gateway.
     * @return void
     */
    public function handle_notification( $data );

}