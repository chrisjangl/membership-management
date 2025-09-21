<?php
/**
 * Plugin Name: Membership Management
 * Description: Manage your organization's membership.
 * Author: Digitally Cultured
 * Author URI: https://digitallycultured.com/
 * Version: 1.2.0
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html * Plugin URI: https://github.com/chrisjangl/membership-management * Text Domain: dcmm-membership * Requires at least: 5.0 * Tested up to: 6.5 * Requires PHP: 7.4





 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

define( 'DCMM_PLUGIN_NAME', 'Membership Management' );
define( 'DCMM_VERSION', '1.2.0' );

define( 'DCMM_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCMM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin activation hook
 */
function dcmm_activate_plugin() {
    require_once( DCMM_PATH . 'includes/class-notification-logger.php' );
    DCMM_Notification_Logger::create_table();
    
    // Create membership management roles and capabilities
    require_once( DCMM_PATH . 'includes/functions-user-role.php' );
    \DCMM_Users\create_membership_management_roles();
}
register_activation_hook( __FILE__, 'dcmm_activate_plugin' );

/**
 * Plugin deactivation hook
 */
function dcmm_deactivate_plugin() {
    require_once( DCMM_PATH . 'includes/class-expiration-scheduler.php' );
    DCMM_Expiration_Scheduler::unschedule_cron_job();
    
    // Remove membership management roles and capabilities
    require_once( DCMM_PATH . 'includes/functions-user-role.php' );
    \DCMM_Users\remove_membership_management_roles();
}
register_deactivation_hook( __FILE__, 'dcmm_deactivate_plugin' );

/**
 * Initialize
 */
require_once( DCMM_PATH . 'includes/init.php' );
