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
    $dashboard_js_file = 'js/member-dashboard.js';
    $dashboard_js_path = DCMM_PATH . $dashboard_js_file;
    $dashboard_js_ver = filemtime( $dashboard_js_path );
    $dashboard_js_src = DCMM_URL . $dashboard_js_file;
    $dashboard_js_dependencies = array( 'jquery' );

    // TODO: 
    // Member dashboard CSS
    // $dashboard_css_file = 'css/member-dashboard.css';
    // $dashboard_css_path = DCMM_PATH . $dashboard_css_file;
    // $dashboard_css_ver = filemtime( $dashboard_css_path );
    // $dashboard_css_src = DCMM_URL . $dashboard_css_file;

    // wp_enqueue_style( 'dcmm-member-dashboard', $dashboard_css_src, array(), $dashboard_css_ver );
    wp_enqueue_script( 'dcmm-member-dashboard', $dashboard_js_src, $dashboard_js_dependencies, $dashboard_js_ver, true );
    wp_localize_script( 'dcmm-member-dashboard', 'dcmm', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'dcmm_renew_nonce' ),
        'cancel_nonce' => wp_create_nonce( 'dcmm_cancel_subscription_nonce' )
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
// cancel subscription
add_action( 'wp_ajax_dcmm_cancel_subscription', 'ajax_cancel_subscription' );


/** 
 * Membership login form
 * 
 * Used with shortcode
 * 
 * TODO: get Login & Dashboard URLs from settings
 */
function dcmm_render_login_form() {

    if ( is_user_logged_in() ) {
        // Check if there's a redirect URL in the query string
        $redirect_to = isset($_GET['redirect_to']) ? urldecode($_GET['redirect_to']) : home_url( '/member-dashboard/' );
        
        // Validate the redirect URL is from our site for security
        if (strpos($redirect_to, home_url()) === 0) {
            wp_redirect( $redirect_to );
        } else {
            // TODO: get Dashboard URL from settings
            wp_redirect( home_url( '/member-dashboard/' ) );
        }
        exit;
    }

    ob_start();

    if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
        echo '<div class="dcmm-error">Invalid username or password.</div>';
    }

    // TODO: get Login & Dashboard URLs from settings
    // Preserve the original destination URL if provided
    $redirect_to = isset($_GET['redirect_to']) ? urldecode($_GET['redirect_to']) : home_url( '/member-dashboard/' );

    $args = [
        'echo'           => true,
        'redirect'       => $redirect_to,
        'form_id'        => 'dcmm-loginform',
        'label_username' => __( 'Username' ),
        'label_password' => __( 'Password' ),
        'label_remember' => __( 'Remember Me' ),
        'label_log_in'   => __( 'Log In' ),
        'remember'       => true
    ];

    wp_login_form( $args );

    // Add forgot password and create account links
    echo '<div class="dcmm-login-links" style="margin-top: 15px; text-align: center;">';
    echo '<p>';
    echo '<a href="' . esc_url( wp_lostpassword_url() ) . '" style="color: #0073aa; text-decoration: none;">Forgot your password?</a>';

    // if site allows user registration, show the "Create an account" link
    if ( get_option( 'users_can_register' ) ) {

        echo ' | ';
        echo '<a href="' . esc_url( wp_registration_url() ) . '" style="color: #0073aa; text-decoration: none;">Create an account</a>';
    }
    echo '</p>';
    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'member_login', 'dcmm_render_login_form' );

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

    // TODO: we should be showing this during "renewal period", not based on click from email
    // Check if this is a renewal request from email
    $show_renewal_notice = isset($_GET['dcmm_action']) && $_GET['dcmm_action'] === 'renew';
    
    if ($show_renewal_notice) {
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 15px 0;">';
        echo '<h4 style="margin-top: 0; color: #856404;">🔔 Renewal Reminder</h4>';
        echo '<p>You\'ve clicked a renewal link from an expiration notification email. Use the "Renew Membership" button below to renew your membership.</p>';
        echo '</div>';
    }

    // (maybe) Display payment status messages
    if ( isset( $_GET['payment'] ) && isset( $_GET['message'] ) ) {
        $payment_status = sanitize_text_field( $_GET['payment'] );
        $message = sanitize_text_field( $_GET['message'] );
        
        if ( $payment_status === 'success' ) {
            echo '<div class="notice notice-success"><p>' . esc_html( urldecode( $message ) ) . '</p></div>';
        } elseif ( $payment_status === 'error' ) {
            echo '<div class="notice notice-error"><p>' . esc_html( urldecode( $message ) ) . '</p></div>';
        }
    }

    if ( $cpt_id ) {
        
        $expiration_date = $member->get_expiration_date();
        $is_expired = $member->is_expired();
        $status = $member->get( 'status' );
        
        ob_start(); ?>
        
        <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0;">
            <h3 style="margin-top: 0;">Membership Information</h3>
            <p><strong>Member ID:</strong> <?php echo esc_html( $cpt_id ); ?></p>
            <p><strong>Status:</strong> 
                <span style="color: <?php echo $status === 'active' ? '#28a745' : '#dc3545'; ?>;">
                    <?php echo esc_html( ucfirst($status) ); ?>
                </span>
            </p>
            <?php if ($expiration_date): ?>
                <p><strong>Expiration Date:</strong> 
                    <span style="color: <?php echo $is_expired ? '#dc3545' : '#666'; ?>;">
                        <?php echo esc_html( date('F j, Y', strtotime($expiration_date)) ); ?>
                        <?php if ($is_expired): ?>
                            <em>(Expired)</em>
                        <?php else: ?>
                            <?php 
                            $days_left = ceil((strtotime($expiration_date) - time()) / (24 * 60 * 60));
                            echo "($days_left days remaining)";
                            ?>
                        <?php endif; ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
        
        <div id="dcmm-renew-response"></div>

        <?php 
        // Check renewal window status
        $renewal_status = $member->get_renewal_status();
        $days_until_window = $member->get_days_until_renewal_window();
        
        // Get subscription status info if member has an active subscription
        $subscription_info = $member->get_subscription_status_info();
        
        // Display subscription status if member has one
        if ($subscription_info && !empty($subscription_info['id'])): ?>
            <div style="background: #e7f3ff; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px; margin: 15px 0;">
                <h4 style="color: #0c5460; margin-top: 0;">
                    🔄 Automatic Renewal Subscription
                    <?php if ($subscription_info['status'] === 'active'): ?>
                        <span style="background: #28a745; color: white; font-size: 12px; padding: 2px 8px; border-radius: 12px; margin-left: 10px;">ACTIVE</span>
                    <?php else: ?>
                        <span style="background: #dc3545; color: white; font-size: 12px; padding: 2px 8px; border-radius: 12px; margin-left: 10px;"><?php echo esc_html(strtoupper($subscription_info['status'])); ?></span>
                    <?php endif; ?>
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;">
                    <div>
                        <p style="margin: 5px 0;"><strong>Billing Frequency:</strong><br>
                        <span style="color: #0c5460;"><?php echo esc_html(ucfirst($subscription_info['interval'] ?? 'monthly')); ?></span></p>
                        
                        <p style="margin: 5px 0;"><strong>Payment Method:</strong><br>
                        <span style="color: #0c5460;"><?php echo esc_html(ucfirst($subscription_info['gateway'])); ?></span></p>
                    </div>
                    
                    <div>
                        <?php if (!empty($subscription_info['next_billing'])): ?>
                            <p style="margin: 5px 0;"><strong>Next Billing:</strong><br>
                            <span style="color: #0c5460;"><?php echo esc_html(date('F j, Y', strtotime($subscription_info['next_billing']))); ?></span></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($subscription_info['last_payment_amount'])): ?>
                            <p style="margin: 5px 0;"><strong>Amount:</strong><br>
                            <span style="color: #0c5460;">$<?php echo esc_html(number_format($subscription_info['last_payment_amount'], 2)); ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($subscription_info['status'] === 'active'): ?>
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">
                        <p style="margin: 0; color: #155724;">
                            ✅ <strong>You're all set!</strong> Your membership will automatically renew 
                            <?php if (!empty($subscription_info['next_billing'])): ?>
                                on <?php echo esc_html(date('F j, Y', strtotime($subscription_info['next_billing']))); ?>.
                            <?php else: ?>
                                according to your billing schedule.
                            <?php endif; ?>
                            No action needed from you.
                        </p>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <button id="dcmm-cancel-subscription" class="button" data-subscription-id="<?php echo esc_attr($subscription_info['id']); ?>" style="background: #dc3545; color: white; border-color: #dc3545;">
                            Cancel Automatic Renewal
                        </button>
                        <p style="font-size: 12px; color: #666; margin: 5px 0;">
                            Your membership will remain active until the current billing period ends.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">
                        <p style="margin: 0; color: #721c24;">
                            ⚠️ <strong>Subscription Issue:</strong> Your automatic renewal is currently 
                            <?php echo esc_html($subscription_info['status']); ?>. 
                            <?php if ($subscription_info['status'] === 'cancelled'): ?>
                                You may need to set up a new subscription or renew manually.
                            <?php else: ?>
                                Please contact support if you need assistance.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #0c5460; font-weight: bold;">Subscription Details</summary>
                    <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                        <p><strong>Subscription ID:</strong> <?php echo esc_html($subscription_info['id']); ?></p>
                        <?php if (!empty($subscription_info['created'])): ?>
                            <p><strong>Created:</strong> <?php echo esc_html(date('F j, Y g:i A', strtotime($subscription_info['created']))); ?></p>
                        <?php endif; ?>
                        <p><strong>Status:</strong> <?php echo esc_html($subscription_info['status']); ?></p>
                    </div>
                </details>
            </div>
        <?php endif; ?>
        
        <?php 
        // Member is active and up for renewal - but hide if they have active subscription
        $has_active_subscription = $subscription_info && $subscription_info['status'] === 'active';
        if ($member->is_in_renewal_window() && !$has_active_subscription): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 15px 0;">
                <?php if ($renewal_status === 'available'): ?>
                    <h4 style="color: #155724; margin-top: 0;">✓ Renewal Available</h4>
                    <p>Your membership renewal is now available. Renew today to avoid any interruption in your membership benefits.</p>
                <?php elseif ($renewal_status === 'grace'): ?>
                    <h4 style="color: #721c24; margin-top: 0;">⚠️ Grace Period</h4>
                    <p style="color: #721c24;"><strong>Your membership has expired.</strong> You can still renew during the grace period to restore your benefits.</p>
                <?php endif; ?>
                
                <?php 
                // Get renewal options to show subscription choices
                $renewal_options = $member->get_renewal_options();
                if (!empty($renewal_options['subscription_available'])): ?>
                    <div id="dcmm-renewal-options">
                        <h5 style="margin: 15px 0 10px 0;">Choose Renewal Type:</h5>
                        <div style="margin: 10px 0;">
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="renewal_type" value="one_time" checked style="margin-right: 8px;">
                                One-time payment (<?php echo esc_html($renewal_options['currency'] . number_format($renewal_options['amount'], 2)); ?>)
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="renewal_type" value="subscription" style="margin-right: 8px;">
                                Recurring subscription (<?php echo esc_html($renewal_options['currency'] . number_format($renewal_options['amount'], 2) . ' ' . $renewal_options['subscription_interval']); ?>)
                                <small style="color: #666; display: block; margin-left: 24px;">Automatically renews your membership</small>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button id="dcmm-renew-button" class="button button-primary">Renew Membership</button>
            </div>
        <?php 
        // Renewal status is suspended, can't renew
        // TODO: why would this case be hit?
        elseif ($renewal_status === 'suspended'): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; margin: 15px 0;">
                <h4 style="color: #721c24; margin-top: 0;">❌ Renewal Unavailable</h4>
                <p style="color: #721c24;">Your membership has been suspended. Please contact support to restore your membership.</p>
            </div>
        <?php
        // Member doesn't have an expiration date set (most likely inactive)
        elseif ($renewal_status === 'no_expiration'): 
            
            // if status is 'inactive', allow member to renew (but hide if they have active subscription)
            if ( 'inactive' === $status && !$has_active_subscription ) : ?>
                <div style="background: #f8d7da; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 15px 0;">
                    <h4 style="color: #721c24; margin-top: 0;">❌ Membership Inactive</h4>
                    <p style="color: #721c24;"><strong>Your membership has expired.</strong> Renew today to avoid any interruption in your membership benefits.</p>
                    
                    <?php 
                    // Get renewal options for inactive members
                    $renewal_options = $member->get_renewal_options();
                    if (!empty($renewal_options['subscription_available'])): ?>
                        <div id="dcmm-renewal-options">
                            <h5 style="margin: 15px 0 10px 0; color: #721c24;">Choose Renewal Type:</h5>
                            <div style="margin: 10px 0;">
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="radio" name="renewal_type" value="one_time" checked style="margin-right: 8px;">
                                    One-time payment (<?php echo esc_html($renewal_options['currency'] . number_format($renewal_options['amount'], 2)); ?>)
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="radio" name="renewal_type" value="subscription" style="margin-right: 8px;">
                                    Recurring subscription (<?php echo esc_html($renewal_options['currency'] . number_format($renewal_options['amount'], 2) . ' ' . $renewal_options['subscription_interval']); ?>)
                                    <small style="color: #666; display: block; margin-left: 24px;">Automatically renews your membership</small>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button id="dcmm-renew-button" class="button button-primary">Renew Membership</button>
                </div>
            <?php
            // something else is going on, so don't allow renewal. 
            // TODO: add link to contact support
            else: ?>
                <div style="background: #cce5ff; border: 1px solid #99ccff; border-radius: 4px; padding: 15px; margin: 15px 0;">
                    <h4 style="color: #0056b3; margin-top: 0;">ℹ️ No Expiration Set</h4>
                    <p>Your membership does not have an expiration date configured.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

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
 * Preserve renewal parameters through login
 * 
 * If a user clicks a link in an email, they may be directed to the login page first.
 * This function ensures that the renewal action is preserved through the login process.
 * 
 * @since 1.1.0
 */
function dcmm_preserve_renewal_parameters() {

    // TODO: get Login & Dashboard URLs from settings
    // Only run on login pages or when redirecting for login
    if (!is_user_logged_in() && (is_page('member-login') || is_page('member-dashboard'))) {
        
        // If someone visits member-dashboard with dcmm_action=renew but isn't logged in,
        // redirect them to login with the original URL preserved
        if (isset($_GET['dcmm_action']) && $_GET['dcmm_action'] === 'renew' && !is_user_logged_in()) {
            $current_url = home_url($_SERVER['REQUEST_URI']);
            $login_url = home_url('/member-login/');
            $redirect_url = add_query_arg('redirect_to', urlencode($current_url), $login_url);
            
            wp_redirect($redirect_url);
            exit;
        }
    }
}
add_action('template_redirect', 'dcmm_preserve_renewal_parameters');

/**
 * Handle login redirect to preserve renewal parameters
 * 
 * TODO: Make sure this isn't duplicating logic in dcmm_preserve_renewal_parameters()
 * 
 * @param string $redirect_to The URL to redirect to
 * @param string $request The requested redirect URL
 * @param WP_User|WP_Error $user The logged-in user object or WP_Error on failure
 * @return string The URL to redirect to after login
 * @since 1.1.0
 */
function dcmm_login_redirect($redirect_to, $request, $user) {

    // If there's a specific redirect_to parameter, use it
    if (!empty($request) && strpos($request, home_url()) === 0) {
        return $request;
    }
    
    // Default to member dashboard
    return home_url('/member-dashboard/');
}
add_filter('login_redirect', 'dcmm_login_redirect', 10, 3);
    
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
    
    // Get renewal type from POST data
    $renewal_type = isset($_POST['renewal_type']) ? sanitize_text_field($_POST['renewal_type']) : 'one_time';
    
    // Get the billing interval for subscriptions
    $billing_interval = null;
    if ( $renewal_type === 'subscription' ) {
        $renewal_options = $member->get_renewal_options();
        $billing_interval = $renewal_options['subscription_interval'] ?? 'monthly';
    }
    
    $result = $member->maybe_charge_for_renewal( $renewal_type, $billing_interval );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    // Check if result contains PayPal redirect data (one-time or subscription)
    if ( is_array( $result ) && isset( $result['type'] ) && 
         ( $result['type'] === 'paypal_redirect' || $result['type'] === 'paypal_subscription' ) ) {
        
        $message = $result['type'] === 'paypal_subscription' 
            ? 'Redirecting to PayPal to set up your subscription...'
            : 'Redirecting to PayPal for payment...';
        
        wp_send_json_success( [
            'message' => $message,
            'redirect_url' => $result['redirect_url'] ?? $result['approval_url'],
            'requires_redirect' => true
        ] );
    }

    // For non-payment renewals (no dues), membership was renewed directly
    wp_send_json_success( [
        'message'     => 'Membership renewed successfully.',
        'last_payment' => get_user_meta( $wp_user_id, 'last_dues_payment', true )
    ] );
}

/**
 * AJAX handler to cancel a Member's subscription
 * 
 * Linked to the 'dcmm_cancel_subscription' AJAX action
 * 
 * @return void
 */
function ajax_cancel_subscription() {
    
    // Check nonce
    check_ajax_referer( 'dcmm_cancel_subscription_nonce', 'nonce' );
    
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
    $member = DCMM_Users\get_member( $wp_user_id );
    
    // Get subscription ID from POST data
    $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
    
    if (empty($subscription_id)) {
        wp_send_json_error( [ 'message' => 'No subscription ID provided' ] );
    }
    
    $result = $member->cancel_subscription();
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    
    wp_send_json_success( [
        'message' => 'Subscription cancelled successfully. Your membership will remain active until the current billing period ends.'
    ] );
}
