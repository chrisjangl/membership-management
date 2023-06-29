<?php
/**
 * Metaboxes class - extends DCMM_Member
 *
 * @class 		DCMM_metaboxes
 * @version		1.0.0
 * @package		Membership Management / Includes
 * @category	Class
 * @author 		Digitally Cultured
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCMM_metaboxes {

    function __construct() {
        add_action( 'load-post.php', array ( $this, 'post_meta_box_setup' ) );
        add_action( 'load-post-new.php', array ( $this, 'post_meta_box_setup' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'dcmm_enqueue_admin_scripts' ) );

    }

    /** register our functions that relate to the metaboxes
    */
     function post_meta_box_setup() {
        add_action( 'add_meta_boxes_dcmm-member', array( $this, 'add_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
    }

    /** register the metaboxes and their callbacks
    **/
     function add_metaboxes() {
        add_meta_box( 'contact_info', 'Contact Info', array( $this, 'create_metabox_contact_info' ), 'dcmm-member', 'normal', 'high' );
        add_meta_box( 'membership_status', "Membership Status", array( $this, 'create_metabox_membership_status' ), 'dcmm-member', 'side' );
    }

    /**
     * Enqueue admin styles
     * 
     * @TODO: consider removing dcmm prefix from function name
     *
     * @param [type] $hook
     *
     * @return void
     */
    function dcmm_enqueue_admin_scripts( $hook ) {
        if ( 'edit.php' != $hook
        && 'post.php' != $hook
        && 'post-new.php' != $hook ) {
            return;
        }
        wp_enqueue_style( 'dcmm_admin_styles', plugin_dir_url( dirname(__FILE__)  ) . 'assets/css/member.css', array(), '1.0' );
    }

    /** 
     * callback to create Contact Info metabox
     * 
     * TODO: make email required
     * 
     * @uses DCMM_Member
    */
    function create_metabox_contact_info() {

        require_once('class-member.php');
        $CPT_post_id = get_the_id();

        // TODO: pass in the email or post ID
        $member = new DCMM_Member( $CPT_post_id );
        $member->get_member_info_form( );
    }

    /**
     * Create the metabox for the WP user
     * 
     * TODO: Show a link to the WP User profile if the user exists.
     */
    function create_metabox_wp_user() {

        // Toggle for creating a WP User for this member.
        $wp_user_id = get_post_meta( get_the_id(), "dcmm_wp_user_id", true );
        ?>

        <?php

    }

    /**
     * Create the metabox for the membership status
     */
    function create_metabox_membership_status() {

        require_once('class-member.php');
        $CPT_post_id = get_the_id();

        // Get the membership status
        $member = new DCMM_Member( $CPT_post_id );
        $meta_keys = $member->get_meta_keys();
        $nonce_prefix = $meta_keys['nonce_prefix'];
        $membership_status = $member->get( 'status' );
        ?>

        <h3>Membership Status</h3>
        <?php wp_nonce_field( $nonce_prefix, 'dcmm_status_nonce' ); ?>
        
        <p>
            <label for="dcmm_status">Membership status:</label>
            <select name="dcmm_status" id="dcmm_status">
                <option value="--" <?php selected( $membership_status, '' ); ?>>--</option>
                <option value="active" <?php selected( $membership_status, 'active' ); ?>>Active</option>
                <option value="inactive" <?php selected( $membership_status, 'inactive' ); ?>>Inactive</option>
            </select>
        </p>
        <?php
    }

    /**
     * @TODO: reuse this between here & the AJAX save user info in my-account.php
     * TODO: I'm repeating this exact code in wp-content/plugins/dc-membership/includes/importer.php, dc_membership_importer_handler_import(). How can I make it DRY?
     */
    function save_meta( $post_id, $post ) {
        
        $post_type = get_post_type_object( $post->post_type );

        //check current user permissions
        if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) {
            return $post_id;
        }

        $post_metakeys = array(
            'dcmm_email',
        );

        require_once('class-member.php');

        // get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();
        $nonce_prefix = $meta_keys['nonce_prefix'];

        $user_metakeys = array(
            'dcmm_first_name',
            'dcmm_last_name',
            'dcmm_phone',
            'dcmm_mailing_address',
            'dcmm_status'
        );

        
        // loop through post fields and save the data
        foreach ( $post_metakeys as $meta_key ) {

            $nonce_key = $meta_key . '_nonce';

            // get and sanitize the nonce, if we have one
            if ( isset( $_POST[$nonce_key] ) ) {
                $nonce = sanitize_key( $_POST[$nonce_key] );
            } else {
                continue;
            }

            // check our nonce to make sure this came from Edit screen 
            if ( !wp_verify_nonce( $nonce, $nonce_prefix ) ) {
                continue;
            }

            // get posted data
            $new_meta_value = ( isset( $_POST[$meta_key] )  ? sanitize_text_field( $_POST[$meta_key] ) : '' );

            // get meta value of the post
            $meta_value = get_post_meta( $post_id, $meta_key, true );

            // if new meta was added, and there was no previous value, add it
            if ( $new_meta_value && empty( $meta_value ) ) {
                add_post_meta( $post_id, $meta_key, $new_meta_value, true );
            }

            // if there was existing meta, but it doesn't match the new meta, update it
            elseif ( $new_meta_value && $new_meta_value != $meta_value ) {
                update_post_meta( $post_id, $meta_key, $new_meta_value );
            }

            // if there is no new meta, but an old one exists, delete it
            elseif ( '' == $new_meta_value && $meta_value ) {
                delete_post_meta( $post_id, $meta_key, $meta_value );
            }
        }

        // check if we have a user for this member, and create one if not
        if ( ! $user_id = get_post_meta( $post_id, 'dcmm_wp_user_id', true ) ) {

            $email = get_post_meta( $post_id, 'dcmm_email', true );
            
            include_once( 'class-member.php');
            $member = new \DCMM_Member( $email );

            $user_id = $member->get_wp_user_id();
            update_post_meta( $post_id, 'dcmm_wp_user_id', $user_id );
        }

        // store the post ID in the user's meta
        update_user_meta( $user_id, 'dcmm_post_id', $post_id );

        // loop through the user fields and save the data to the corresponding user
        foreach ( $user_metakeys as $meta_key ) {
            
            $nonce_key = $meta_key . '_nonce';

            // get and sanitize the nonce, if we have one
            if ( isset( $_POST[$nonce_key] ) ) {
                $nonce = sanitize_key( $_POST[$nonce_key] );
            } else {
                continue;
            }

            // check our nonce to make sure this came from Edit screen 
            if ( !wp_verify_nonce( $nonce, $nonce_prefix ) ) {
                continue;
            }

            // get posted data
            // check if the field is an array...
            if ( is_array( $_POST[$meta_key] ) ) {
                // ...if so, sanitize each value in the array...
                $new_meta_value = array_map( 'sanitize_text_field', $_POST[$meta_key] );
            } else {
                // ...if not, sanitize the value
                $new_meta_value = sanitize_text_field( $_POST[$meta_key] );
            }

            // get meta value of the user
            $meta_value = get_user_meta( $user_id, $meta_key, true);
            
            // if new meta was added, and there was no previous value, add it
            if ( $new_meta_value && empty( $meta_value ) ) {
                update_user_meta( $user_id, $meta_key, $new_meta_value );
            }

            // if there was  existing meta, but it doesn't match the new meta, update it
            elseif ( $new_meta_value && $new_meta_value != $meta_value ) {
                update_user_meta( $user_id, $meta_key, $new_meta_value );
            }

            // if there is no new meta, but an old one exists, delete it
            elseif ( '' == $new_meta_value && $meta_value ) {
                delete_user_meta( $user_id, $meta_key, $meta_value );
            }
        }
    }
}