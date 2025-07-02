<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Functionality relating to the user's My Account page
 */
use \DCMM_Users\is_organizational_member;

/**
 * Enqueue styles & scripts used on Member Account area (frontend)
 */
function dcmm_enqueue_member_dashboard_styles_scripts() {

    // Member dashboard JS
    $dashboard_js_file = 'assets/js/member-dashboard.js';
    $dashboard_js_path = DCMM_PATH . $dashboard_js_file;
    $dashboard_js_ver = filemtime( $dashboard_js_path );
    $dashboard_js_src = DCMM_URL . $dashboard_js_file;
    $dashboard_js_dependencies = array( 'jquery' );

    // TODO: 
    // Member dashboard CSS
    // $dashboard_css_file = 'assets/css/member-dashboard.css';
    // $dashboard_css_path = DCMM_PATH . $dashboard_css_file;
    // $dashboard_css_ver = filemtime( $dashboard_css_path );
    // $dashboard_css_src = DCMM_URL . $dashboard_css_file;

    // wp_enqueue_style( 'dcmm-member-dashboard', $dashboard_css_src, array(), $dashboard_css_ver );
    wp_enqueue_script( 'dcmm-member-dashboard', $dashboard_js_src, $dashboard_js_dependencies, $dashboard_js_ver, true );
    wp_localize_script( 'dcmm-member-dashboard', 'dcmm', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'dcmm_renew_nonce' )
    ] );

}

/**
 * Register AJAX actions
 */
// update own info
add_action( 'wp_ajax_dcms_update_own_info', 'dcmm_update_user_data' );
// renew membership
require_once('class-member.php');
add_action( 'wp_ajax_dcmm_renew_membership', 'ajax_renew_membership' );


// create shortcode to display the user's My Account page
function dcmm_my_account_shortcode() {
    dcmm_enqueue_member_dashboard_styles_scripts();

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
// add_shortcode( 'dcms_my_account', 'dcmm_my_account_shortcode' );
add_shortcode( 'member_login', 'dcmm_render_login_form' );

/** 
 * Membership login form
 * 
 * Used with shortcode
 */
function dcmm_render_login_form() {

    if ( is_user_logged_in() ) {
        wp_redirect( home_url( '/member-dashboard/' ) );
        exit;
    }

    ob_start();

    if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
        echo '<div class="dcmm-error">Invalid username or password.</div>';
    }


    $args = [
        'echo'           => true,
        'redirect'       => home_url( '/member-dashboard/' ),
        'form_id'        => 'dcmm-loginform',
        'label_username' => __( 'Username' ),
        'label_password' => __( 'Password' ),
        'label_remember' => __( 'Remember Me' ),
        'label_log_in'   => __( 'Log In' ),
        'remember'       => true
    ];

    wp_login_form( $args );

    return ob_get_clean();
}

/**
 * Redirect back to Member login on failed login
 * 
 * 
 */
function dcmm_login_failed_redirect() {
    $referrer = wp_get_referer();

    if ( ! empty( $referrer ) && strpos( $referrer, 'member-login' ) !== false ) {
        wp_redirect( add_query_arg( 'login', 'failed', $referrer ) );
        exit;
    }
}
add_action( 'wp_login_failed', 'dcmm_login_failed_redirect' );

/**
 * Member's personal dashboard
 * 
 */
function dcmm_render_dashboard() {

    if ( ! is_user_logged_in() ) {
        wp_redirect( home_url( '/member-login/' ) );
        exit;
    }

    dcmm_enqueue_member_dashboard_styles_scripts();

    $user_id = get_current_user_id();
    
    // Check if the user is an organizational member
    include_once( 'functions-user-role.php' );
    if ( ! DCMM_Users\is_organizational_member( $user_id ) ) {
        wp_redirect( home_url( '/member-login/' ) );
        exit;
    }

    // get the member post ID for the current user
    $member = DCMM_Users\get_member( $user_id );
    // $user_id = $member ? $member->get_wp_user_id() : null;
    $cpt_id = $member ? $member->get_member_id() : null;

    ob_start();

    echo '<h2>Welcome, ' . esc_html( wp_get_current_user()->display_name ) . '</h2>';

    if ( $cpt_id ) {
        
        ob_start(); ?>
        <p>Member ID: <?php echo esc_html( $cpt_id ); ?></p>
        <p>Status: <?php echo esc_html( $member->get( 'status' ) ); ?></p>
        <div id="dcmm-renew-response"></div>

        <?php // if ( dcmm_member_needs_renewal( $member_id ) ) : ?>
            <button id="dcmm-renew-button" class="button button-primary">Renew Membership</button>
        <?php //endif; ?>

        <?php 

        echo ob_get_clean();

    } else {
        echo '<p>No member record found.</p>';
    }

    echo '<p><a href="' . esc_url( wp_logout_url( home_url( '/member-login/' ) ) ) . '">Log out</a></p>';

    return ob_get_clean();

}
add_shortcode( 'dcmm_member_dashboard', 'dcmm_render_dashboard' );
    
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


/**
 * AJAX handler to renew a Member's membership
 * 
 * Linked to the 'dcmm_renew_membership' AJAX action
 * 
 * @uses \DCMM_Member->renew_membership()
 * 
 * @return void
 */
function ajax_renew_membership() {

    // Check nonce
    check_ajax_referer( 'dcmm_renew_nonce', 'nonce' );

    // Require login
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'User not logged in' ] );
    }

    $wp_user_id = get_current_user_id();

    include_once( 'functions-user-role.php' );
    // check if the user is an organizational member
    if ( ! DCMM_Users\is_organizational_member( $wp_user_id ) ) {
        wp_send_json_error( [ 'message' => 'User is not a member' ] );
    }

    // get the DCMM_Member object for the current user
    $member  = DCMM_Users\get_member( $wp_user_id );

    $result = $member->maybe_charge_for_renewal( 'self-renewal' );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    // alternative:
    // wp_send_json_success( [ 'message' => 'Renewal flow started.', 'result' => $result ] );
    wp_send_json_success( [
        'message'     => 'Membership renewed successfully.',
        'last_payment' => get_user_meta( $wp_user_id, 'last_dues_payment', true )
    ] );
}
