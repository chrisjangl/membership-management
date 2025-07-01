<?php
/**
 * DC Membership Plugin - Payment Gateways
 *
 * This file includes the payment gateway classes and interfaces for the DC Membership plugin.
 * 
 * TODO: implement logic to dynamically load gateways based on settings.
 *
 * @package DC Membership
 * @since 1.1.0
 */

require_once( 'gateways/interface-payment-gateway.php' );
require_once( 'gateways/class-abstract-gateway.php' );
require_once( 'gateways/class-gateway-paypal.php' );
require_once( 'gateways/class-gateway-manager.php' );
