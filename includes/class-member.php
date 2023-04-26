<?php

use \DC_Membership_Users\create_member_as_user;

/**
 * Set up the Member post type
 * 
 * TODO: Need to convert this from a singleton-eque class to a regular class
 * 
 * @todo: create user role (here? or in contact? or both?)
 * @todo: create way to check if user is member
 * 
 */

class DC_Member extends WP_User {

	protected $member_id = null;
	protected $membership_status = null;
	protected static $our_post_type = 'dc-member';
	// protected static $db_table_name = 'custom_lms_exam_strations';
	static $instance;

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
		
		// if not, create a WP user, giving it a role of "Organizational Member"

		// register meta boxes
		require_once( 'class-member-metaboxes.php' );
		new Member_metaboxes();

	}

	/**
	 * Get an array of info about the Student
	 *
	 * @param int $student_ID
	 *
	 * @return array $member[
	 *                  'id'
	 *                  'first_name'
	 *                  'last_name'
	 *                  'email'
	 *                  'dob'
	 *                  'address' => [
	 *                      'street1'
	 *                      'street2'
	 *                      'city'
	 *                      'state'
	 *                      'zip'
	 *                  ]
	 *              ]
	 */
	function get_member_info( $member_ID ) {

		// make sure we have a Member ID to work with
		if ( !$member_ID ) {
			return false;
		}

		$member_info = [];

		$member['id'] = $member_ID;
		$member['first_name'] = $wordpress_user->get('user_firstname');
		$member['last_name'] = $wordpress_user->get('user_lastname');
		$member['email'] = $wordpress_user->get('user_email');
		$member['phone'] = get_user_meta( $student_ID, 'clms_dob', true );

		// TODO: need check if we only have the full address as one string or parsed
		$member['address'] = array(
			'street1'   =>  get_user_meta( $student_ID, 'clms_street1', true ),
			'street2'   =>  get_user_meta( $student_ID, 'clms_street2', true ),
			'city'      =>  get_user_meta( $student_ID, 'clms_city', true ),
			'state'     =>  get_user_meta( $student_ID, 'clms_state', true ),
			'zip'       =>  get_user_meta( $student_ID, 'clms_zip', true ),
		);

		return $student;

	}

	function get_wp_user_id() {
		return $this->member_id;
	}


}

new DC_Member();