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
namespace DCMM_Users;

use DCMM_Member;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


\DCMM_Users\create_member_role();
\DCMM_Users\create_membership_management_roles();

function create_member_role() {
    add_role( 'member', 'Organization Member', get_role( 'subscriber' )->capabilities );
}

/**
 * Create membership management roles and their capabilities
 * 
 * @since 1.1.1
 */
function create_membership_management_roles() {
    // Define membership-specific capabilities
    $membership_capabilities = get_membership_capabilities();
    
    // CPT-specific capabilities that WordPress generates
    $cpt_caps = [
        'edit_dcmm_member',
        'read_dcmm_member', 
        'delete_dcmm_member',
        'edit_dcmm_members',
        'edit_others_dcmm_members',
        'publish_dcmm_members',
        'read_private_dcmm_members',
        'delete_dcmm_members',
        'delete_private_dcmm_members',
        'delete_published_dcmm_members',
        'delete_others_dcmm_members',
        'edit_private_dcmm_members',
        'edit_published_dcmm_members',
        'create_dcmm_members',
    ];

    // Create Membership Manager role
    $manager_caps = array_merge(
        get_role( 'subscriber' )->capabilities, // Base subscriber capabilities
        array_fill_keys( $membership_capabilities['manager'], true ),
        array_fill_keys( $cpt_caps, true )
    );
    add_role( 'membership_manager', 'Membership Manager', $manager_caps );
    
    // Create Membership Administrator role  
    $admin_caps = array_merge(
        get_role( 'subscriber' )->capabilities, // Base subscriber capabilities
        array_fill_keys( $membership_capabilities['administrator'], true ),
        array_fill_keys( $cpt_caps, true )
    );
    add_role( 'membership_administrator', 'Membership Administrator', $admin_caps );
    
    // Grant all membership capabilities to WordPress Administrators
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        foreach ( $membership_capabilities['administrator'] as $cap ) {
            $admin_role->add_cap( $cap );
        }
        
        // Also add the CPT-specific capabilities that WordPress generates
        $cpt_caps = [
            'edit_dcmm_member',
            'read_dcmm_member', 
            'delete_dcmm_member',
            'edit_dcmm_members',
            'edit_others_dcmm_members',
            'publish_dcmm_members',
            'read_private_dcmm_members',
            'delete_dcmm_members',
            'delete_private_dcmm_members',
            'delete_published_dcmm_members',
            'delete_others_dcmm_members',
            'edit_private_dcmm_members',
            'edit_published_dcmm_members',
            'create_dcmm_members',
        ];
        
        foreach ( $cpt_caps as $cap ) {
            $admin_role->add_cap( $cap );
        }
    }
}

/**
 * Get membership-specific capabilities
 * 
 * @return array Capabilities organized by role
 * @since 1.1.1
 */
function get_membership_capabilities() {
    $base_caps = [
        'manage_dcmm_members',      // CRUD operations on members
        'record_dcmm_payments',     // Record offline payments
        'create_dcmm_wp_users',     // Create WP user accounts from Edit Member screen
        'view_dcmm_reports',        // Future: view membership statistics/reports
        'send_dcmm_emails',         // Future: send manual emails to members
        'export_dcmm_members',      // Future: export member data
        'import_dcmm_members',      // Import member data (current feature)
    ];
    
    return [
        'manager' => $base_caps,
        'administrator' => array_merge( $base_caps, [
            'manage_dcmm_settings',  // Access plugin settings (Admin only)
        ] ),
    ];
}

/**
 * Check if current user can manage members
 * 
 * @return bool True if user has edit_dcmm_members capability
 * @since 1.1.1
 */
function can_manage_members() {
    return current_user_can( 'edit_dcmm_members' );
}

/**
 * Check if current user can manage membership settings
 * 
 * @return bool True if user has manage_dcmm_settings capability
 * @since 1.1.1
 */
function can_manage_settings() {
    return current_user_can( 'manage_dcmm_settings' );
}

/**
 * Check if current user can record payments
 * 
 * @return bool True if user has record_dcmm_payments capability
 * @since 1.1.1
 */
function can_record_payments() {
    return current_user_can( 'record_dcmm_payments' );
}

/**
 * Check if current user can import members
 * 
 * @return bool True if user has import_dcmm_members capability
 * @since 1.1.1
 */
function can_import_members() {
    return current_user_can( 'import_dcmm_members' );
}

/**
 * Check if current user can export members (future feature)
 * 
 * @return bool True if user has export_dcmm_members capability
 * @since 1.1.1
 */
function can_export_members() {
    return current_user_can( 'export_dcmm_members' );
}

/**
 * Check if current user can view reports (future feature)
 * 
 * @return bool True if user has view_dcmm_reports capability
 * @since 1.1.1
 */
function can_view_reports() {
    return current_user_can( 'view_dcmm_reports' );
}

/**
 * Check if current user can send emails (future feature)
 * 
 * @return bool True if user has send_dcmm_emails capability
 * @since 1.1.1
 */
function can_send_emails() {
    return current_user_can( 'send_dcmm_emails' );
}

/**
 * Add the 'member' role to a user while preserving existing roles
 * 
 * This allows users to have both management roles (manager/administrator) 
 * and the member role simultaneously.
 * 
 * @param int $user_id The user ID to add the member role to
 * @return bool True on success, false on failure
 * @since 1.1.1
 */
function add_member_role( $user_id ) {
    $user = new \WP_User( $user_id );
    
    // Only add the member role if they don't already have it
    if ( ! in_array( 'member', $user->roles ) ) {
        $user->add_role( 'member' );
        return true;
    }
    
    return false; // Role already exists
}

/**
 * Check if a user has management capabilities (manager or administrator roles)
 * 
 * @param int $user_id The user ID to check
 * @return bool True if user has management capabilities
 * @since 1.1.1
 */
function has_management_role( $user_id ) {
    $user = new \WP_User( $user_id );
    $management_roles = [ 'membership_manager', 'membership_administrator', 'administrator' ];
    
    return ! empty( array_intersect( $user->roles, $management_roles ) );
}

/**
 * Remove membership management roles (for plugin deactivation)
 * 
 * @since 1.1.1
 */
function remove_membership_management_roles() {
    // Remove custom roles
    remove_role( 'membership_manager' );
    remove_role( 'membership_administrator' );
    
    // Remove capabilities from WordPress Administrator role
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $capabilities = get_membership_capabilities();
        foreach ( $capabilities['administrator'] as $cap ) {
            $admin_role->remove_cap( $cap );
        }
        
        // Also remove the CPT-specific capabilities
        $cpt_caps = [
            'edit_dcmm_member',
            'read_dcmm_member', 
            'delete_dcmm_member',
            'edit_dcmm_members',
            'edit_others_dcmm_members',
            'publish_dcmm_members',
            'read_private_dcmm_members',
            'delete_dcmm_members',
            'delete_private_dcmm_members',
            'delete_published_dcmm_members',
            'delete_others_dcmm_members',
            'edit_private_dcmm_members',
            'edit_published_dcmm_members',
            'create_dcmm_members',
        ];
        
        foreach ( $cpt_caps as $cap ) {
            $admin_role->remove_cap( $cap );
        }
    }
}

/**
 * Get a user meta key
 * 
 * (for now) This is used to store the CPT ID of the Member (cpt) associated with the WP User.
 * 
 * TODO: flesh this out to include more user meta keys in the future
 * 
 * @param string $key The key to get, defaults to false
 * 
 * @return string The user meta key
 */
function get_user_meta_key( $key = false ) {

    // return the user meta key used to store the CPT ID
    return 'dcmm_cpt_id';
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
    echo esc_html( ob_get_clean() );
}
add_action( 'show_user_profile', __NAMESPACE__ . '\\add_user_fields', 10 );
add_action( 'edit_user_profile', __NAMESPACE__ . '\\add_user_fields', 10 );

/**
 * Creates a WP User with the role of 'Organizational Member'
 * 
 * TODO: Need to handle the case where the email is already registered to another member
 * 
 * @param string $email The email address of the user to create
 * @param int $cpt_id The post ID of the Member (cpt) to associate with the WP User
 * 
 * @return int $user_id
 */
function create_member_as_user( $email, $cpt_id ) {

    // check if email is registered to WP user
    $user = \get_user_by( 'email', $email );

    // get the user meta key for the CPT ID
    $cpt_id_meta_key = get_user_meta_key( 'cpt_id');

    // does a user with this email already exist?
    if ( $user ) {

        // if so, check if WP user has role of "Member"
        if ( !is_organizational_member( $user->get( 'ID' ) ) ) {

            // if not, add the "Member" role (preserving existing roles)
            add_member_role( $user->ID );
        }

        // check if the user already has a CPT ID saved
        $existing_cpt_id = \get_user_meta( $user->ID, $cpt_id_meta_key, true );

        // if the user already has a CPT ID saved, check if it matches the one we are trying to save
        // careful here, need to make sure we don't miss where they match, but one is a string, and the other is an int
        if ( $existing_cpt_id && $existing_cpt_id != $cpt_id ) {
            
            // if it does not match, throw an WP error
            \wp_die(
                sprintf(
                    __( 'This email address is already associated with a member (CPT ID: %s). Please use a different email address.', DCMM_PLUGIN_SLUG ),
                    $existing_cpt_id
                ),
                __( 'Email Address Already Registered', DCMM_PLUGIN_SLUG ),
                array( 'response' => 400 )
            );
        } else if ( ! $existing_cpt_id ) {
            
            // if it does not exist, add user meta with the CPT ID we were passed
            $cpt_id_saved = \add_user_meta( $user->ID, $cpt_id_meta_key, $cpt_id, true );
        } 

        // return the user
        return $user->ID;

    } else {
        
        // if not, create a WP user, giving it a role of "Member"
        $user_id = \wp_create_user( $email, \wp_generate_password(), $email );
        $user = new \WP_User( $user_id );
        $user->add_role( 'member' );

        // update the user meta with the CPT ID
        $cpt_id_saved = \update_user_meta( $user_id, $cpt_id_meta_key, $cpt_id );

        if ( ! $cpt_id_saved ) {
            
            //log the error
            \error_log( 'Error saving CPT ID for user: ' . $user_id . ' with CPT ID: ' . $cpt_id );
            
        }

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
    if ( \is_array( $user->roles ) && \in_array( 'member', $user->roles ) 
        || is_organizational_manager( $user )
        || is_organizational_admin( $user )
        ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Check if a given user is a Membership Manager
 * 
 * This is based on whether the user ID is set to our "Membership Manager"
 * WP User role, *not* the user's organizational status.
 * 
 * @uses get_userdata()
 * @uses in_array()
 * 
 * @param WP_User|int $user
 * 
 * @return bool true if user is a manager, false if not
 */
function is_organizational_manager( $user ) {

    // if $user passed is not a WP_User object, get it
    if ( ! $user instanceof \WP_User ) {
        $user = \get_userdata( $user );
    }

    // check whether user is a Organizational Member
    if ( \is_array( $user->roles ) && \in_array( 'membership_manager', $user->roles ) ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Check if a given user is a Membership Admin
 * 
 * This is based on whether the user ID is set to our "Membership Admin"
 * WP User role, *not* the user's organizational status.
 * 
 * @uses get_userdata()
 * @uses in_array()
 * 
 * @param WP_User|int $user
 * 
 * @return bool true if user is a manager, false if not
 */
function is_organizational_admin( $user ) {

    // if $user passed is not a WP_User object, get it
    if ( ! $user instanceof \WP_User ) {
        $user = \get_userdata( $user );
    }

    // check whether user is a Organizational Member
    if ( \is_array( $user->roles ) && \in_array( 'membership_administrator', $user->roles ) ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Get the DCMM_Member object for a WP User
 * 
 * If no user ID is passed, it will try to get the currently logged in User.
 * 
 * @param int $user_ID The user ID to get the WP_User object for
 * 
 * @return WP_User|false The WP_User object if the user is a member, false if not
 */
function get_member( $user_ID = false ) {

    // if no user ID is passed, try to get the current user ID
    if ( ! $user_ID ) {
        $user_ID = \get_current_user_id();
    }
    
    // if no user ID is set, return false
    if ( ! $user_ID || ! is_int( $user_ID ) ) {
        return false;
    }

    // check whether user is a Organizational Member
    if ( ! is_organizational_member( $user_ID ) ) {
        return false;
    }

    $cpt_id_meta_key = get_user_meta_key();

    // otherwise, get the DCMM_Member object for the user
    $cpt_id = \get_user_meta( $user_ID, $cpt_id_meta_key, true );

   if ( ! $cpt_id ) {
        return false; // no CPT ID found for the user
    }

    // get the member post object
    $member = new \DCMM_Member( $cpt_id );

    // if the member post object is valid, return it
    if ( $member instanceof DCMM_Member ) {
        return $member;
    } else {
        return false; // no valid member post object found
    }
}


/**
 * Save the member's CPT ID as a user meta key.
 * 
 * Used to associate the member's CPT with their WP User account.
 * 
 * TODO: evaluate whether this is the best way to do this.
 * 
 */
 function update_member_meta( $member_id, $meta_key, $meta_value ) {

 }

/**
 * Save the custom meta for our members.
 * 
 * Requires 'edit_user' capabilities
 * 
 * @TODO: create custom capability to edit members without having the 'edit_user' capability
 * @TODO: need to be able to update member's user meta here
 *
 * @param int $user_id
 *
 * @return void
 */
function save_user_fields( $user_id ) {
    if ( !current_user_can('edit_user', $user_id) ) return false;

    // Get associated member CPT ID
    $cpt_id_meta_key = get_user_meta_key();
    $cpt_id = \get_user_meta( $user_id, $cpt_id_meta_key, true );

    if ( $cpt_id ) {
        // Sync WP user changes back to member
        $member = new \DCMM_Member( $cpt_id );
        if ( $member->exists() ) {
            $member->sync_from_wp_user( $user_id );
        }
    }
}
add_action( 'personal_options_update', __NAMESPACE__ . '\\save_user_fields' );
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\save_user_fields' );

