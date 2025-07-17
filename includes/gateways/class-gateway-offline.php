<?php
/**
 * Offline payment gateway for recording manual payments.
 *
 * This gateway handles payments recorded by administrators in the WP admin dashboard,
 * such as cash, check, bank transfer, or other offline payment methods.
 *
 * @package DC Membership
 * @since 1.1.0
 */

namespace DCMM\Gateways;

use DCMM_Member;
use DCMM_Settings;

class Gateway_Offline extends Abstract_Gateway {

	const gateway_name = 'offline';

	/**
	 * Start payment process for offline payments.
	 * For offline payments, this is just a placeholder that returns true.
	 *
	 * @param int   $member_id The member's CPT ID.
	 * @param float $amount    The amount to charge.
	 * 
	 * @return bool True on success.
	 */
	public function start_payment( $member_id, $amount ) {
		$this->log_payment_event( $member_id, "Offline payment initiated for amount: $" . number_format( $amount, 2 ) );
		return true;
	}

	/**
	 * Complete an offline payment with admin-provided details.
	 *
	 * @param int   $member_id     The member's CPT ID.
	 * @param array $payment_data  Payment details from admin form.
	 */
	public function complete_payment( $member_id, $payment_data ) {
		// Validate required payment data
		if ( empty( $payment_data['amount'] ) || empty( $payment_data['method'] ) || empty( $payment_data['date'] ) ) {
			throw new \Exception( 'Missing required payment data' );
		}

		$member = new DCMM_Member( $member_id );
		if ( ! $member || ! $member->exists() ) {
			throw new \Exception( 'Invalid member ID' );
		}

		// Prepare payment record
		$payment_record = [
			'gateway'          => 'offline',
			'amount'           => floatval( $payment_data['amount'] ),
			'method'           => sanitize_text_field( $payment_data['method'] ),
			'reference'        => isset( $payment_data['reference'] ) ? sanitize_text_field( $payment_data['reference'] ) : '',
			'notes'            => isset( $payment_data['notes'] ) ? sanitize_textarea_field( $payment_data['notes'] ) : '',
			'payment_date'     => sanitize_text_field( $payment_data['date'] ),
			'recorded_date'    => current_time( 'mysql' ),
			'recorded_by'      => get_current_user_id(),
			'status'           => 'completed'
		];

		// Store payment data
		update_post_meta( $member_id, 'dcmm_last_payment', $payment_record );
		update_post_meta( $member_id, 'dcmm_last_dues_payment', $payment_record['payment_date'] );

		// Log the payment
		$log_message = sprintf(
			'Offline payment recorded: %s for $%s',
			$payment_record['method'],
			number_format( $payment_record['amount'], 2 )
		);

		if ( ! empty( $payment_record['reference'] ) ) {
			$log_message .= ' (Ref: ' . $payment_record['reference'] . ')';
		}

		$this->log_payment_event( $member_id, $log_message );

		// Renew membership
		$member->renew_membership( 'offline' );

		return $payment_record;
	}

	/**
	 * Handle notifications (not applicable for offline payments).
	 *
	 * @param array $data The notification data.
	 */
	public function handle_notification( $data ) {
		// Offline payments don't have external notifications
		return;
	}

	/**
	 * Get available offline payment methods from settings.
	 *
	 * @return array Available payment methods.
	 */
	public function get_payment_methods() {
		$methods = DCMM_Settings\get_offline_payment_methods();
		
		// Default methods if none configured
		if ( empty( $methods ) ) {
			$methods = [
				'cash'     => 'Cash',
				'check'    => 'Check',
				'transfer' => 'Bank Transfer',
				'other'    => 'Other'
			];
		}

		return $methods;
	}

	/**
	 * Check if reference numbers are required for offline payments.
	 *
	 * @return bool True if reference numbers are required.
	 */
	public function is_reference_required() {
		return DCMM_Settings\is_offline_reference_required();
	}
}