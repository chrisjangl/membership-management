<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * TODO: build out the plugin settings
 *
 * @return void
 */
function add_membership_menu() {
   
    add_menu_page( "Membership", 'Membership', 'administrator', 'membership', "\DCMM_Users\create_membership_menu_page", '', 20 );
   
}
add_action( 'admin_menu', '\DCMM_Users\add_membership_menu');