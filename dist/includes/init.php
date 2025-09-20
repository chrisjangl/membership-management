<?php
/**
 * Initialization file for the DC Membership plugin.
 * 
 * This file includes necessary components and sets up the plugin's functionality
 * 
 * TODO: implement logic to dynamically load gateways based on settings
 */

/**
 * Member
 */
include( 'register-post-type.php' );

/**
 * Admin logic
 */
include( 'dcmm-admin.php' );

/**
 * My Account
 */
include( 'my-account.php' );

/**
 * Importer
 */
include( 'importer.php' );

/**
 * Settings
 */
include( 'settings.php' );

/**
 * Payment Gateways
 */
include_once( 'gateways.php' );

/**
 * Email Handler
 */
include_once( 'class-email-handler.php' );

/**
 * Notification Logger
 */
include_once( 'class-notification-logger.php' );

/**
 * Expiration Scheduler
 */
include_once( 'class-expiration-scheduler.php' );

/**
 * MailChimp API
 */
include_once( 'premium/integrations/mailchimp/class-mailchimp-api.php' );

/**
 * Premium Features System
 */
include_once( 'premium/interface-premium-feature.php' );
include_once( 'premium/class-premium-manager.php' );

// Initialize premium manager early
add_action( 'plugins_loaded', function() {
    // Instantiate the premium manager to ensure it's initialized
    \DCMM\Premium\Premium_Manager::get_instance();
}, 5 );