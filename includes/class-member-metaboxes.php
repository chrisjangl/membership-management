<?php
/**
 * Metaboxes class - extends DC_Member
 *
 * @class 		Member_metaboxes
 * @version		1.0.0
 * @package		Yonkers TLC Events / Includes
 * @category	Class
 * @author 		Digitally Cultured
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Member_metaboxes {

    function __construct() {
        add_action( 'load-post.php', array ( $this, 'post_meta_box_setup' ) );
        add_action( 'load-post-new.php', array ( $this, 'post_meta_box_setup' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'dcm_enqueue_admin_scripts' ) );

    }

    /** register our functions that relate to the metaboxes
    */
     function post_meta_box_setup() {
        add_action( 'add_meta_boxes_dc-member', array( $this, 'add_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
    }

    /** register the metaboxes and their callbacks
    **/
     function add_metaboxes() {
        add_meta_box( 'contact_info', 'Contact Info', array( $this, 'create_metabox_contact_info' ), 'dc-member', 'normal', 'high' );
        add_meta_box( 'membership_status', "Membership Status", array( $this, 'create_metabox_membership_status' ), 'dc-member', 'side' );
        // add_meta_box( 'event_guest', "Guest Speaker", array( $this, 'create_metabox_guest' ), 'dc-member', 'side' );
        // add_meta_box( 'event_location', 'Location', array( $this, 'create_metabox_location' ), 'normal', 'default'  );
        // add_meta_box( 'event_appointment', 'Add to Calendar', array( $this, 'create_metabox_ics' ), 'side', 'normal' );
        // add_meta_box( 'event_cost', 'Cost of Admission', array( $this, 'create_metabox_ics' ), 'side', 'normal' );
    }

    /**
     * Enqueue admin styles
     *
     * @param [type] $hook
     *
     * @return void
     */
    function dcm_enqueue_admin_scripts( $hook ) {
        if ( 'edit.php' != $hook
        && 'post.php' != $hook
        && 'post-new.php' != $hook ) {
            return;
        }
        wp_enqueue_style( 'dc_member_admin_styles', plugin_dir_url( dirname(__FILE__)  ) . 'assets/css/member.css', array(), '1.0' );
    }

    

    /** 
     * callback to create Contact Info metabox
     * 
     * TODO: make email required
     * 
     * @uses DC_Member
    */
    function create_metabox_contact_info() {

        require_once('class-member.php');

        $user_id = get_post_meta( get_the_id(), 'dc_member_wp_user_id', true );
        $first_name = get_user_meta( $user_id, 'dc_member_first_name', true );
        $last_name = get_user_meta( $user_id, 'dc_member_last_name', true );
        $mailing_address = get_user_meta( $user_id, 'dc_member_mailing_address', true );
        $email = get_post_meta( get_the_id(), 'dc_member_email', true );
        $phone = get_user_meta( $user_id, 'dc_member_phone', true );

        // nonces for the fields
        wp_nonce_field( basename( __FILE__ ), 'dc_member_first_name_nonce' );
        wp_nonce_field( basename( __FILE__ ), 'dc_member_last_name_nonce' );
        wp_nonce_field( basename( __FILE__ ), 'dc_member_mailing_address_nonce' );
        wp_nonce_field( basename( __FILE__ ), 'dc_member_email_nonce' );
        wp_nonce_field( basename( __FILE__ ), 'dc_member_phone_nonce' );
        ?>

        <div class="dcm-metabox">
            <h3>Name</h3>
            <div class="form-section">
                <div class="form-row">
                    <!-- first name -->
                    <div class="form-group half">
                        <label for="dc_member_first_name">First Name:</label>
                        <input type="text" name="dc_member_first_name" id="dc_member_first_name" <?php echo !empty( $first_name ) ? ' value="' . $first_name . '"' : 'placeholder=""'; ?> />
                    </div>
                        
                    <!-- last name -->
                    <div class="form-group half">
                        <label for="dc_member_last_name">Last Name:</label>
                        <input type="text" name="dc_member_last_name" id="dc_member_last_name" <?php echo !empty( $last_name ) ? ' value="' . $last_name . '"' : 'placeholder=""'; ?> />
                    </div>
                </div>
            </div>

            <h3>Contact Info</h3>
            <div class="form-section">
                
                <!-- Email -->
                <div class="form-row">
                    <label for="dc_member_email">Email:</label>
                    <input type="email" name="dc_member_email" id="dc_member_email" <?php echo !empty( $email ) ? ' value="' . $email . '"' : 'placeholder="member@example.com"'; ?> required />
                </div>

                <!-- Phone -->
                <div class="form-row">
                    <label for="dc_member_phone">Phone Number:</label>
                    <input type="tel" name="dc_member_phone" id="dc_member_phone" <?php echo !empty( $phone ) ? ' value="' . $phone . '"' : 'placeholder="Phone"'; ?> />
                </div>
            </div>


            <!-- Mailing Address -->
            <div class="form-section">
                <h4>Mailing Address</h4>
                <div class="form-row">
                    <label for="dc_member_mailing_address[street1]" >Street:</label>
                    <input type="text" name="dc_member_mailing_address[street1]" id="dc_member_mailing_address_street1" <?php echo !empty( $mailing_address['street1'] ) ? ' value="' . $mailing_address["street1"] . '"' : 'placeholder="Street"'; ?> />
                    <br />
                    <input type="text" name="dc_member_mailing_address[street2]" id="dc_member_mailing_address_street2" <?php echo !empty( $mailing_address['street2'] ) ? ' value="' . $mailing_address["street2"] . '"' : 'placeholder=""'; ?> />
                </div>

                <div class="form-row">
                    <label for="dc_member_mailing_address[city]" >City:</label>
                    <input type="text" name="dc_member_mailing_address[city]" id="dc_member_mailing_address_city" <?php echo !empty( $mailing_address['city'] ) ? ' value="' . $mailing_address["city"] . '"' : 'placeholder="City"'; ?> />
                </div>

                <div class="form-row">
                    <div class="form-group half">
                        <label for="dc_member_mailing_address[state]" >State:</label>
                        <input type="text" name="dc_member_mailing_address[state]" id="dc_member_mailing_address_state" <?php echo !empty( $mailing_address['state'] ) ? ' value="' . $mailing_address["state"] . '"' : 'placeholder="State"'; ?> />
                    </div>

                    <div class="form-group half">
                        <label for="dc_member_mailing_address[zip]" >Zip:</label>
                        <input type="text" name="dc_member_mailing_address[zip]" id="dc_member_mailing_address_zip" <?php echo !empty( $mailing_address['zip'] ) ? ' value="' . $mailing_address["zip"] . '"' : 'placeholder="Zip"'; ?> />
                    </div>
                </div>
            </div>

        </div>
        <?
    }

    /**
     * Create the metabox for the WP user
     * 
     * TODO: Show a link to the WP User profile if the user exists.
     */
    function create_metabox_wp_user() {

        // Toggle for creating a WP User for this member.
        $wp_user_id = get_post_meta( get_the_id(), "dc_member_wp_user_id", true );

        ?>

        <?php

    }

    /**
     * Create the metabox for the membership status
     */
    function create_metabox_membership_status() {
        $user_id = get_post_meta( get_the_id(), 'dc_member_wp_user_id', true );
        $membership_status = get_user_meta( $user_id, "dc_membership_status", true );
        ?>
        <h3>Membership Status</h3>
        <?php wp_nonce_field( basename( __FILE__ ), 'dc_membership_status_nonce' ); ?>
        
        <p>
            <label for="dc_membership_status">Membership status:</label>
            <select name="dc_membership_status" id="dc_membership_status">
                <option value="--" <?php selected( $membership_status, '' ); ?>>--</option>
                <option value="active" <?php selected( $membership_status, 'active' ); ?>>Active</option>
                <option value="inactive" <?php selected( $membership_status, 'inactive' ); ?>>Inactive</option>
            </select>
        </p>
        <?php

    }

    function save_meta( $post_id, $post ) {
        
        $post_type = get_post_type_object( $post->post_type );

        //check current user permissions
        if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) {
            return $post_id;
        }

        $post_metakeys = array(
            'dc_member_email',
        );
        
        $user_metakeys = array(
            'dc_member_first_name',
            'dc_member_last_name',
            'dc_member_phone',
            'dc_member_mailing_address',
            'dc_membership_status'
        );

        
        // loop through post fields and save the data
        foreach ( $post_metakeys as $meta_key ) {

            //check our nonce to maker sure this came from Edit a Grouped page 
            if ( !isset( $_POST[$meta_key.'_nonce'] ) || !wp_verify_nonce( $_POST[$meta_key.'_nonce'], basename( __FILE__  ) ) ) {
                continue;
            }

            //get posted data
            $new_meta_value = ( isset( $_POST[$meta_key] )  ? $_POST[$meta_key] : '' );

            if ( $meta_key == 'dc_events_date' ) {
                $new_meta_value = strtotime( $new_meta_value );
            }

            //get meta value of the post
            $meta_value = get_post_meta( $post_id, $meta_key, true );

            //if new meta was added, and there was no previous value, add it
            if ( $new_meta_value && empty( $meta_value ) ) {
                add_post_meta( $post_id, $meta_key, $new_meta_value, true );
            }

            //if there was  existing meta, but it doesn't match the new meta, update it
            elseif ( $new_meta_value && $new_meta_value != $meta_value ) {
                update_post_meta( $post_id, $meta_key, $new_meta_value );
            }

            //if there is no new meta, but an old one exists, delete it
            elseif ( '' == $new_meta_value && $meta_value ) {
                delete_post_meta( $post_id, $meta_key, $meta_value );
            }
        }

        // check if we have a user for this member, and create one if not
        if ( ! $user_id = get_post_meta( $post_id, 'dc_member_wp_user_id', true ) ) {

            $email = get_post_meta( $post_id, 'dc_member_email', true );
            
            include_once( 'class-member.php');
            $member = new \DC_Member( $email );

            $user_id = $member->get_wp_user_id();
            update_post_meta( $post_id, 'dc_member_wp_user_id', $user_id );
        }

        // store the post ID in the user's meta
        update_user_meta( $user_id, 'dc_member_post_id', $post_id );

        // loop through the user fields and save the data to the corresponding user
        foreach ( $user_metakeys as $meta_key ) {
            
            //check our nonce to maker sure this came from Edit  page 
            if ( !isset( $_POST[$meta_key.'_nonce'] ) || !wp_verify_nonce( $_POST[$meta_key.'_nonce'], basename( __FILE__  ) ) ) {
                continue;
            }

            // get posted data
            $new_meta_value = ( isset( $_POST[$meta_key] )  ? $_POST[$meta_key] : '' );

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