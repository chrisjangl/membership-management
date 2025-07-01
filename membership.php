<?php
/**
 * Plugin Name: Membership Management
 * Description: Manage your organization's membership.
 * Author: Digitally Cultured
 * Author URI: https://digitallycultured.com/
 * Version: 1.0.2
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly    

define( 'DCMM_VERSION', '1.0.0' );

define( 'DCMM_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCMM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize
 */
require_once( DCMM_PATH . 'includes/init.php' );
