<?php

/**
 * TODO: build out the plugin settings
 *
 * @return void
 */
function add_membership_menu() {
   
    add_menu_page( "Membership", 'Membership', 'administrator', 'membership', "\DC_Membership_Users\create_membership_menu_page", '', 20 );
   
}
add_action( 'admin_menu', '\DC_Membership_Users\add_membership_menu');