<?php

use \DC_Membership_Users\create_member_as_user;

/**
 * Set up the Member post type
 * 
 * TODO: Need to convert this from a singleton-eque class to a regular class
 * 
 * TODO: create method to check if user is member
 * TODO: this class should only be a Member object; get rid of developer helpers (post type, etc.)
 * 
 */

class DC_Member extends WP_User {

	protected $member_id = null;
	protected $membership_status = null;
	protected static $our_post_type = 'dc-member';
	// protected static $db_table_name = 'custom_lms_exam_strations';
	static $instance;

	// TODO: change this to construct using user ID, post ID or email
	// TODO: Should we return something?
	function __construct( $email = null ) {

		// check if email is registered to WP user
		if ( !is_null ( $email ) ) {
			$wordpress_user = \get_user_by( 'email', $email );

			if ( $wordpress_user ) {
				// if so, check if WP User has role of "Organizational Member"
				if ( !in_array( 'organizational_member', $wordpress_user->roles ) ) {
					// if not, add the role
					$wordpress_user->set_role( 'organizational_member' );
				}

				$this->member_id = $wordpress_user->ID;

			} else {
				
				// if not, create a WP user, giving it a role of "Organizational Member"
				include_once( 'functions-user-role.php' );
				$member_ID = \DC_Membership_Users\create_member_as_user( $email );

				if ( ! \is_wp_error( $member_ID ) ) {
					$this->member_id = $member_ID;
				}
			}
		}
		
		// register meta boxes
		// TODO: move this elsewhere
		require_once( 'class-member-metaboxes.php' );
		new Member_metaboxes();

	}
	
	function get_wp_user_id() {
		return $this->member_id;
	}

}

new DC_Member();