<?php
/**
 * Member class - defines a member properties and methods
 * 
 * @todo: create user role (here? or in contact? or both?)
 * @todo: create way to check if user is member
 * 
 */

 class DC_Member extends DC_Contact {

    protected $member_id = null;
    protected $membership_status = null;

    function __construct( $email = null ) {

        // check if email is registered to WP user
      
        // if not, create a WP user, giving it a role of "Member"

    }

 }