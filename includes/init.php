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