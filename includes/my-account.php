<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Functionality relating to the user's My Account page
 */
use \DCMM_Users\is_organizational_member;  

// create shortcode to display the user's My Account page
function dcmm_my_account_shortcode() {

    wp_enqueue_style( 'dcmm_member_admin_styles', plugin_dir_url( dirname(__FILE__)  ) . 'assets/css/member.css', array(), '1.0' );
    // enqueue the JS, requiring jQuery as a dependency & passding the AJAX URL
    wp_enqueue_script( 'dcmm_member_info', plugin_dir_url( dirname(__FILE__)  ) . 'assets/js/member-my-account.js', array('jquery'), '1.0' );
    wp_localize_script( 'dcmm_member_info', 'dcmm', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    wp_enqueue_script( 'dcmm_member_info', plugin_dir_url( dirname(__FILE__)  ) . 'assets/js/member-my-account.js', array('jquery'), '1.0' );

    // check if user is logged in
    if ( ! is_user_logged_in() ) {

        ob_start();

        // if not, display login form
        echo wp_kses( wp_login_form(), 'post' );

        return ob_get_clean();
    } else {

        // get current user's ID
        $user_id = get_current_user_id();
        $CPT_post_id = get_user_meta( $user_id, 'dcmm_post_id', true );

        // and then get the Member object
        require_once('class-member.php');
        $member = new DCMM_Member( $CPT_post_id );

        // TODO: user DC_Member class to get this info
        // $first_name = get_user_meta( $user_id, 'dcmm_first_name', true );
        // $last_name = get_user_meta( $user_id, 'dcmm_last_name', true );
        // $email = get_post_meta( $CPT_post_id, 'dcmm_email', true );
        // $phone = get_user_meta( $user_id, 'dcmm_phone', true );
        // $mailing_address = get_user_meta( $user_id, 'dcmm_mailing_address', true );
        $membership_status = $member->get( 'status' );

        // TODO: do we still need this?
        require_once( 'functions-user-role.php' );

        ob_start();

        // check if user is a member...
        if ( \DCMM_Users\is_organizational_member( $user_id ) ) {

            // ...if so, display My Account page
            ?>
            <h3>Membership Status</h3>
            <p>Your membership is: <b><?php echo esc_html( $membership_status ); ?></b>.</p>

            <form action="" method="post" id="update-own-info">

                <?php
                wp_nonce_field( basename( __FILE__ ), 'dcmm_update_nonce' );

                $member->get_member_info_form();
                ?>

                <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />
                <input type="submit" name="update_own_info" value="Update Info" />
            </form>

            <?php
        } else {
            // if not, display My Account page
            ?>
            <h2>My Account</h2>
            <hr>
            <h3>Membership Status</h3>
            <p>You are not a member.</p>
            <?php
        }

        // get the user's info
        return ob_get_clean();
    }
}
add_shortcode( 'dcms_my_account', 'dcmm_my_account_shortcode' );

/**
 * Allow users to update their own info, submitted by AJAX
 * 
 */
function dcmm_update_user_data() {

    // get the logged-in user's ID
    $user_id = get_current_user_id();

    // if we have a User ID from the update form, sanitize it
    if ( isset( $_POST['user_id'] ) ) {
        $user_id_to_edit = sanitize_text_field( $_POST['user_id'] );
    } 

    // check that our user's ID matches the one passed in the AJAX request
    if ( $user_id == $user_id_to_edit ) {

        // get the user's info
        $user_info = get_userdata( $user_id );
        
        // get the user's CPT post ID
        $CPT_post_id = get_user_meta( $user_id, 'dcmm_post_id', true );

        // get the nonces
        $dcmm_member_update_nonce = isset( $_POST['dcmm_update_nonce'] ) 
            ? sanitize_key( $_POST['dcmm_update_nonce'] )
            : false;
        
        // verify the nonce
        if ( wp_verify_nonce( $dcmm_member_update_nonce, basename( __FILE__ ) ) ) {

            // get the user's info; check if it's set, then sanitize it if so:

            // TODO: handle the situation where the value isn't passed in as a POST variable
            // user's first name
            $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : false;

            // update the user's first name
            update_user_meta( $user_id, 'dcmm_first_name', $first_name );

            // user's last name 
            $last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : false;

            // update the user's last name
            update_user_meta( $user_id, 'dcmm_last_name', $last_name );

            // (maybe) get the user's mailing address and sanitize it
            $mailing_address = [];
            $mailing_address['street1'] = isset( $_POST['street1'] ) ? sanitize_text_field( $_POST['street1'] ) : null;
            $mailing_address['street2'] = isset( $_POST['street2'] ) ? sanitize_text_field( $_POST['street2'] ) : null;
            $mailing_address['city'] = isset( $_POST['city'] ) ? sanitize_text_field( $_POST['city'] ) : null;
            $mailing_address['state'] = isset( $_POST['state'] ) ? sanitize_text_field( $_POST['state'] ) : null;
            $mailing_address['zip'] = isset( $_POST['zip'] ) ? sanitize_text_field( $_POST['zip'] ) : null;

            // update the user's mailing address
            update_user_meta( $user_id, 'dcmm_mailing_address', $mailing_address );

            // email
            $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : false;

            // update the user's email address
            update_post_meta( $CPT_post_id, 'dcmm_email', $email );

            // phone
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : false;

            // update the user's phone number
            update_user_meta( $user_id, 'dcmm_phone', $phone );

            wp_send_json_success( 'Your info has been updated.' );

            wp_die();
        } else {
            return false;
        }
    }
}

// register the ajax action
add_action( 'wp_ajax_dcms_update_own_info', 'dcmm_update_user_data' );