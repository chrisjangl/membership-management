<?php
/**
 * Plugin Name: Membership Management
 * Description: Manage your organization's membership.
 * Author: Digitally Cultured
 * Author URI: https://digitallycultured.com/
 * Version: 0.1.0
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly      

/**
 * Member
 */
include( 'includes/register-post-type.php' );

/**
 * My Account
 */
include( 'includes/my-account.php' );

/**
 * Importer
 */
include( 'includes/importer.php' );
  
