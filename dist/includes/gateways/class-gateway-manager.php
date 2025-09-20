<?php
/**
 * Gateway Manager for DC Membership Plugin
 * 
 * This class manages the payment gateways used in the DC Membership plugin.
 * It provides a method to retrieve the default payment gateway.
 * 
 * @package DC Membership
 * @since 1.1.0
 */

namespace DCMM\Gateways;

class Gateway_Manager {

	public static function get_default_gateway(): Payment_Gateway_Interface {
		// Eventually this could be dynamic, from plugin settings
		return new Gateway_PayPal();
	}
	
	/**
	 * Get a specific gateway by name
	 * 
	 * @param string $gateway_name Gateway name
	 * @return Payment_Gateway_Interface|null Gateway instance or null if not found
	 */
	public static function get_gateway( $gateway_name ) {
		switch ( strtolower( $gateway_name ) ) {
			case 'paypal':
				return new Gateway_PayPal();
			default:
				return null;
		}
	}
}
