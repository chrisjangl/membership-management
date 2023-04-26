<?php
/**
 * Functions related to the Member user role
 * 
 * TODO: Member to be a CPT, extending the User class
 *  can we do this?
 * TODO: create a role for membership manager
 * 
 * TODO: create custom user meta for our user role
 */

namespace DC_Membership_Users;

\DC_Membership_Users\create_member_role();

function create_member_role() {
    add_role( 'member', 'Organization Member', get_role( 'subscriber' )->capabilities );
}

/** 
 * add our own section with info about the member
 * 
 * TODO: adjust to fit here
 */
function add_user_fields($user) { 
    ob_start();
    ?>

    <hr>
	

    <h3>Personal Info</h3>

    <hr>

    <?php
    // TODO: list the exams they are / have been registered
    echo ob_get_clean();
}
add_action( 'show_user_profile', __NAMESPACE__ . '\\add_user_fields', 10 );
add_action( 'edit_user_profile', __NAMESPACE__ . '\\add_user_fields', 10 );

/**
 * Creates a WP User with the role of 'member'
 * 
 * @return int $user_id
 */
function create_member_as_user( $email ) {

    // check if email is registered to WP user
    $user = \get_user_by( 'email', $email );
    if ( $user ) {
        // if so, return the user
        return $user->ID;
    } else {
        // if not, create a WP user, giving it a role of "Member"
        $user_id = \wp_create_user( $email, \wp_generate_password(), $email );
        $user = new \WP_User( $user_id );
        $user->set_role( 'member' );
        return $user->ID;
    }
}

/** 
 * Check if a given user is a member.
 * 
 * This is based on whether the user ID is set to our "Organization Member"
 * WP User role, *not* the user's organizational status.
 * 
 * @uses get_userdata()
 * @uses in_array()
 * 
 * @param int $user_ID
 * 
 * @return bool true if user is a member, false if not
 */
function is_organizational_member( $user_ID ) {
        
    // get WP_User object
    $user = \get_userdata( $user_ID );

    // check whether user is a Organizational Member
    if ( \in_array( 'member', $user->roles ) ) {
        return true;
    } else {
        return false;
    }

}


/**
 * Update member meta
 * 
 * For now, this info is being stored in the CPT.
 * 
 * TODO:convert the post meta to user meta here
 */
 function update_member_meta( $member_id, $meta_key, $meta_value ) {

 }

/**
 * Save the custom meta for our members.
 * 
 * Requires 'edit_user' capabilities
 * 
 * @TODO: create custom capability to edit students without having the 'edit_user' capability
 *
 * @param int $user_id
 *
 * @return void
 */
function save_user_fields( $user_id ) {
    if ( !current_user_can('edit_user', $user_id) ) return false;

    // update_user_meta($user_id, 'clms_dob', esc_attr($_POST['clms_dob']));
    // update_user_meta($user_id, 'clms_street1', esc_attr($_POST['clms_street1']));
    // update_user_meta($user_id, 'clms_street2', esc_attr($_POST['clms_street2']));
    // update_user_meta($user_id, 'clms_city', esc_attr($_POST['clms_city']));
    // update_user_meta($user_id, 'clms_state', esc_attr($_POST['clms_state']));
    // update_user_meta($user_id, 'clms_zip', esc_attr($_POST['clms_zip']));
	
}
add_action( 'personal_options_update', __NAMESPACE__ . '\\save_user_fields' );
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\save_user_fields' );

