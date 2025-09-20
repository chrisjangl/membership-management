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

    /**
     * Create a recurring subscription for a member.
     *
     * @param int   $member_id The member's CPT ID.
     * @param float $amount    The recurring amount to charge.
     * @param string $interval The billing interval (monthly, yearly, etc).
     * @return array|WP_Error Array with subscription data on success, WP_Error on failure.
     */
    public function create_subscription( $member_id, $amount, $interval );

    /**
     * Cancel a recurring subscription.
     *
     * @param int    $member_id       The member's CPT ID.
     * @param string $subscription_id The gateway's subscription ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function cancel_subscription( $member_id, $subscription_id );

    /**
     * Get subscription status from the gateway.
     *
     * @param string $subscription_id The gateway's subscription ID.
     * @return array|WP_Error Subscription status data on success, WP_Error on failure.
     */
    public function get_subscription_status( $subscription_id );

}