<?php
/**
 * Functionality relating to the user's My Account page
 */
use \DC_Membership_Users\is_organizational_member;  

// create shortcode to display the user's My Account page
function dcms_my_account_shortcode() {

    wp_enqueue_style( 'dc_member_admin_styles', plugin_dir_url( dirname(__FILE__)  ) . 'assets/css/member.css', array(), '1.0' );
    // enqueue the JS, requiring jQuery as a dependency & passding the AJAX URL
    wp_enqueue_script( 'dc_member_info', plugin_dir_url( dirname(__FILE__)  ) . 'assets/js/member-my-account.js', array('jquery'), '1.0' );
    wp_localize_script( 'dc_member_info', 'dc_membership', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    wp_enqueue_script( 'dc_member_info', plugin_dir_url( dirname(__FILE__)  ) . 'assets/js/member-my-account.js', array('jquery'), '1.0' );

    // check if user is logged in
    if ( ! is_user_logged_in() ) {
        // if not, display login form
        return wp_login_form();
    } else {

        // get current user's ID
        $user_id = get_current_user_id();
        $CPT_post_id = get_user_meta( $user_id, 'dc_member_post_id', true );
        
        // TODO: user DC_Member class to get this ino
        $first_name = get_user_meta( $user_id, 'dc_member_first_name', true );
        $last_name = get_user_meta( $user_id, 'dc_member_last_name', true );
        $email = get_post_meta( $CPT_post_id, 'dc_member_email', true );
        $phone = get_user_meta( $user_id, 'dc_member_phone', true );
        $mailing_address = get_user_meta( $user_id, 'dc_member_mailing_address', true );
        $membership_status = get_user_meta( $user_id, "dc_membership_status", true );

        require_once( 'functions-user-role.php' );

        ob_start();

        // check if user is a member
        if ( \DC_Membership_Users\is_organizational_member( $user_id ) ) {
            // if so, display My Account page
            ?>
            <h2>My Account</h2>
            <hr>
            <h3>Membership Status</h3>
            <p>Your membership is: <b><?= $membership_status; ?></b>.</p>
            <?php

            // nonces for the fields
            wp_nonce_field( basename( __FILE__ ), 'dc_member_first_name_nonce' );
            wp_nonce_field( basename( __FILE__ ), 'dc_member_last_name_nonce' );
            wp_nonce_field( basename( __FILE__ ), 'dc_member_mailing_address_nonce' );
            wp_nonce_field( basename( __FILE__ ), 'dc_member_email_nonce' );
            wp_nonce_field( basename( __FILE__ ), 'dc_member_phone_nonce' );
            ?>

            <form action="" method="post" id="update-own-info">
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

                <input type="hidden" name="user_id" value="<?= $user_id; ?>" />
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


        // if so, display My Account page
        ?>
        Membership status
        <?php

        // get the user's info
        return ob_get_clean();
    }
}
add_shortcode( 'dcms_my_account', 'dcms_my_account_shortcode' );

/**
 * Allow users to update their own info, submitted by AJAX
 * 
 */
function update_user_data() {

    // get the user's ID
    $user_id = get_current_user_id();

    // get the form data
    parse_str( $_POST['formData'], $form_data );

    // check that our user's ID matches the one passed in the AJAX request
    if ( $user_id == $form_data['user_id'] ) {

        // get the user's info
        $user_info = get_userdata( $user_id );
        // get the user's CPT post ID
        $CPT_post_id = get_user_meta( $user_id, 'dc_member_post_id', true );

        // get the nonces
        $dc_member_first_name_nonce = $_POST['dc_member_first_name_nonce'];
        $dc_member_last_name_nonce = $_POST['dc_member_last_name_nonce'];
        $dc_member_mailing_address_nonce = $_POST['dc_member_mailing_address_nonce'];
        $dc_member_email_nonce = $_POST['dc_member_email_nonce'];
        $dc_member_phone_nonce = $_POST['dc_member_phone_nonce'];
        
        // verify the nonce
        if ( wp_verify_nonce( $dc_member_first_name_nonce, basename( __FILE__ ) ) && wp_verify_nonce( $dc_member_last_name_nonce, basename( __FILE__ ) ) && wp_verify_nonce( $dc_member_mailing_address_nonce, basename( __FILE__ ) ) && wp_verify_nonce( $dc_member_email_nonce, basename( __FILE__ ) ) && wp_verify_nonce( $dc_member_phone_nonce, basename( __FILE__ ) ) ) {

            
            // get the user's first name and sanitize it
            $first_name = sanitize_text_field( $form_data['dc_member_first_name'] );

            // update the user's first name
            update_user_meta( $user_id, 'dc_member_first_name', $first_name );

            // get the user's last name and sanitize it
            $last_name = sanitize_text_field( $form_data['dc_member_last_name'] );

            // update the user's last name
            update_user_meta( $user_id, 'dc_member_last_name', $last_name );

            // get the user's mailing address and sanitize it
            $mailing_address = sanitize_text_field( $form_data['dc_member_mailing_address'] );

            // update the user's mailing address
            update_user_meta( $user_id, 'dc_member_mailing_address', $mailing_address );

            // get the user's email address and sanitize it
            $email = sanitize_email( $form_data['dc_member_email'] );

            // update the user's email address
            update_post_meta( $CPT_post_id, 'dc_member_email', $email );

            // get the user's phone number and sanitize it
            $phone = sanitize_text_field( $form_data['dc_member_phone'] );

            // update the user's phone number
            update_user_meta( $user_id, 'dc_member_phone', $phone );

            wp_send_json_success( 'Your info has been updated.' );

            wp_die();
        } else {
            // return false;
        }

    }
}
// register the ajax action
add_action( 'wp_ajax_dcms_update_own_info', 'update_user_data' );