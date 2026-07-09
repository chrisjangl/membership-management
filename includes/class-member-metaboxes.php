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

        // register AJAX action for creating WP user account (used in WP user metabox)
        require_once('class-member.php');
        add_action( 'wp_ajax_dcmm_create_wp_user_account', array( 'DCMM_Member', 'ajax_create_wp_user_account' ) );


        // add_filter( 'post_row_actions', array ( $this, 'dcmm_add_member_row_actions' ), 10, 2 );


    }

    /** 
     * register our functions that relate to the metaboxes
     */
     function post_meta_box_setup() {
        add_action( 'add_meta_boxes_dcmm-member', array( $this, 'add_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
    }

    /** 
     * register the metaboxes and their callbacks
     */
     function add_metaboxes() {
        add_meta_box( 'contact_info', 'Contact Info', array( $this, 'create_metabox_contact_info' ), 'dcmm-member', 'normal', 'high' );
        add_meta_box( 'membership_status', "Membership Status", array( $this, 'create_metabox_membership_status' ), 'dcmm-member', 'side' );
        add_meta_box( 'user_account', "User Account", array( $this, 'create_metabox_wp_user' ), 'dcmm-member', 'side' );
        add_meta_box( 'logs', "Action Log", array( $this, 'create_metabox_logs' ), 'dcmm-member', 'side' );
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
        wp_enqueue_style( 'dcmm_admin_styles', plugin_dir_url( dirname(__FILE__)  ) . 'css/member-admin.css', array(), '1.0' );
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
     * TODO: move the JS to a separate file
     */
    function create_metabox_wp_user() {

        // Toggle for creating a WP User for this member.
        $wp_user_id = get_post_meta( get_the_id(), "dcmm_wp_user_id", true );
        
        require_once('class-member.php');
        $CPT_post_id = get_the_id();

        // Get the membership status
        $member = new DCMM_Member( $CPT_post_id );

        if ( ! $member->has_wp_user() ) {
            echo '<p>This member does not have a WordPress user account.</p>';
            echo '<p><a href="#" class="button button-secondary" id="create-wp-user-btn" data-action="create_wp_user">Create WP User Account</a></p>';

            // when clicked, use AJAX to run DCMM_Member::create_wp_user_account()
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('#create-wp-user-btn').on('click', function(e) {
                    e.preventDefault();
                    
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    // Show loading state
                    $button.prop('disabled', true)
                           .addClass('dcmm-loading')
                           .html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Creating User...');
                    
                    var data = {
                        'action': 'dcmm_create_wp_user_account',
                        'cpt_id': <?php echo get_the_id(); ?>,
                        'nonce': '<?php echo wp_create_nonce( 'dcmm_create_wp_user_account' ); ?>'
                    };

                    // AJAX call, with success, failure, and always handlers
                    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
                    console.log(ajaxurl);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            console.log('AJAX Success:', response);

                            if ( response.success ) {
                                // Show success state briefly before reload
                                $button.removeClass('dcmm-loading')
                                       .addClass('dcmm-success')
                                       .html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span>Success!');
                                
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                alert('Error: ' + response.data.message);
                                // Reset button on error
                                $button.prop('disabled', false)
                                       .removeClass('dcmm-loading')
                                       .text(originalText);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                            
                            // Reset button on error
                            $button.prop('disabled', false)
                                   .removeClass('dcmm-loading')
                                   .text(originalText);
                        }
                    })
                });
            });
            </script>
            
            <style>
            .dcmm-loading {
                opacity: 0.7;
                cursor: not-allowed !important;
            }
            .dcmm-success {
                background-color: #00a32a !important;
                border-color: #00a32a !important;
                color: #fff !important;
            }
            </style>

            <?php
            return;
        }

        $meta_keys = $member->get_meta_keys();
        $nonce_prefix = $meta_keys['nonce_prefix'];
        $membership_status = $member->get( 'status' );
        ?>
        
        <p>
            <?php
            $user_info = get_userdata( $wp_user_id );
            if ( $user_info ) {
                echo 'This member has a WordPress user account: <a href="' . esc_url( get_edit_user_link( $wp_user_id ) ) . '">' . esc_html( $user_info->user_login ) . '</a>';
            } else {
                echo 'This member has a WordPress user account with ID ' . esc_html( $wp_user_id ) . ', but the user could not be found.';
            }
            ?>
        </p>
        <?php

    }

    /**
     * Create the metabox for the membership status
     */
    function create_metabox_membership_status() {

        require_once('class-member.php');
        $CPT_post_id = get_the_id();

        // prep the Member object
        $member = new DCMM_Member( $CPT_post_id );
        $member_id = $member->get_member_id();
        $meta_keys = $member->get_meta_keys();
        $nonce_prefix = $meta_keys['nonce_prefix'];

        // Get the membership status
        $membership_status = $member->get( 'status' );
        // By calling get_expiration_date(), we ensure that the expiration date is calculated if settings have changed
        $expiration_date = $member->get_expiration_date();

        wp_nonce_field( $nonce_prefix, 'dcmm_status_nonce' );
        wp_nonce_field( $nonce_prefix, 'dcmm_expiration_date_nonce' ); ?>
        
        <?php
        /**
         * Membership status dropdown
         */ 
        ?>
        <p>
            <label for="dcmm_status">Membership status:</label>
            <select name="dcmm_status" id="dcmm_status">
                <?php 
                $valid_statuses = DCMM_Member::get_valid_statuses();
                foreach ( $valid_statuses as $status_value ) {
                    $status_label = ucfirst( $status_value );
                    $selected = selected( $membership_status, $status_value, false );
                    echo "<option value=\"{$status_value}\" {$selected}>{$status_label}</option>";
                }
                ?>
            </select>
        </p>

        <?php
        // if status is active, show expiration date
        if ( 'active' === $membership_status ) {
            ?>
            <p>
                <label for="dcmm_expiration_date">Expiration Date:</label>
                <input type="date" name="dcmm_expiration_date" id="dcmm_expiration_date" value="<?php echo esc_attr( $expiration_date ); ?>"  />
            </p>
            <?php
        }

        // Renew link with email option
        $renew_url_base = wp_nonce_url(
            admin_url( 'admin-post.php?action=dcmm_manual_renew&member_id=' . $member_id ),
            'dcmm_manual_renew_' . $member_id
        );

        // Cancel link
        $cancel_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=dcmm_manual_cancel&member_id=' . $member_id ),
            'dcmm_manual_cancel_' . $member_id
        );
        ?>

        <div class="dcmm-renewal-options">
            <?php
            // Check if offline payments are enabled
            if ( function_exists( 'DCMM_Settings\are_offline_payments_enabled' ) && DCMM_Settings\are_offline_payments_enabled() ) {
                // Show payment form on button click
                ?>
                <p>
                    <a href="#" class="button button-primary dcmm-show-payment-form">Renew Membership</a>
                    <a href="<?php echo esc_url( $cancel_url ); ?>" class="button">Cancel Membership</a>
                </p>
                
                <div id="dcmm-payment-form" style="display: none; margin-top: 15px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
                    <h4>Record Payment Details</h4>
                    <div id="dcmm-offline-payment-form">
                        <?php wp_nonce_field( 'dcmm_offline_payment_' . $member_id, 'dcmm_offline_payment_nonce' ); ?>
                        <input type="hidden" id="dcmm_action" value="dcmm_offline_payment">
                        <input type="hidden" id="dcmm_member_id" value="<?php echo esc_attr( $member_id ); ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="payment_amount">Payment Amount:</label></th>
                                <td>
                                    $<input type="number" id="payment_amount" name="payment_amount" 
                                           value="<?php echo esc_attr( DCMM_Settings\get_dues_amount() ); ?>" 
                                           step="0.01" min="0" style="width: 100px;">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="payment_method">Payment Method:</label></th>
                                <td>
                                    <select id="payment_method" name="payment_method">
                                        <option value="">Select method...</option>
                                        <?php
                                        $methods = DCMM_Settings\get_offline_payment_methods();
                                        foreach ( $methods as $key => $label ) {
                                            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="payment_reference">Reference #:</label></th>
                                <td>
                                    <input type="text" id="payment_reference" name="payment_reference" 
                                           placeholder="Check #, Transaction ID, etc." style="width: 200px;">
                                    <?php if ( DCMM_Settings\is_offline_reference_required() ): ?>
                                        <span style="color: red;">*</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="payment_date">Payment Date:</label></th>
                                <td>
                                    <input type="date" id="payment_date" name="payment_date" 
                                           value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" style="width: 150px;">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="payment_notes">Notes:</label></th>
                                <td>
                                    <textarea id="payment_notes" name="payment_notes" rows="3" 
                                              placeholder="Optional notes about this payment..."></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th></th>
                                <td>
                                    <label for="send_email_receipt">
                                        <input type="checkbox" id="send_email_receipt" name="send_email_receipt" value="1" checked>
                                        Send email receipt to member
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="button" class="button button-primary dcmm-submit-payment" data-member-id="<?php echo esc_attr( $member_id ); ?>">Record Payment & Subscribe</button>
                            <a href="#" class="button dcmm-cancel-payment-form">Cancel</a>
                        </p>
                    </div>
                </div>
                <?php
            } else {
                // Original simple renewal flow
                ?>
                <p>
                    <label for="dcmm_send_email">
                        <input type="checkbox" id="dcmm_send_email" checked> 
                        Send email receipt
                    </label>
                </p>
                <p>
                    <a href="#" class="button button-primary dcmm-renew-button" data-base-url="<?php echo esc_url( $renew_url_base ); ?>">Renew Membership</a>
                    <br><br>
                    <a href="<?php echo esc_url( $cancel_url ); ?>" class="button">Cancel Membership</a>
                </p>
                <?php
            }
            ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            <?php if ( function_exists( 'DCMM_Settings\are_offline_payments_enabled' ) && DCMM_Settings\are_offline_payments_enabled() ): ?>
            // Offline payment form handling
            $('.dcmm-show-payment-form').on('click', function(e) {
                e.preventDefault();
                $('#dcmm-payment-form').slideDown();
                $(this).hide();
            });
            
            $('.dcmm-cancel-payment-form').on('click', function(e) {
                e.preventDefault();
                $('#dcmm-payment-form').slideUp();
                $('.dcmm-show-payment-form').show();
            });
            
            $('.dcmm-submit-payment').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var originalText = $button.text();
                
                // Validate required fields
                var amount = $('#payment_amount').val();
                var method = $('#payment_method').val();
                var date = $('#payment_date').val();
                var reference = $('#payment_reference').val();
                
                if (!amount || parseFloat(amount) <= 0) {
                    alert('Please enter a valid payment amount');
                    $('#payment_amount').focus();
                    return;
                }
                
                if (!method) {
                    alert('Please select a payment method');
                    $('#payment_method').focus();
                    return;
                }
                
                if (!date) {
                    alert('Please enter a payment date');
                    $('#payment_date').focus();
                    return;
                }
                
                // Check if reference is required
                <?php if ( DCMM_Settings\is_offline_reference_required() ): ?>
                if (!reference) {
                    alert('Reference number is required');
                    $('#payment_reference').focus();
                    return;
                }
                <?php endif; ?>
                
                // Set loading state
                $button.prop('disabled', true).text('Processing...');
                
                // Collect form data
                var formData = new FormData();
                formData.append('action', $('#dcmm_action').val());
                formData.append('member_id', $('#dcmm_member_id').val());
                formData.append('dcmm_offline_payment_nonce', $('input[name="dcmm_offline_payment_nonce"]').val());
                formData.append('payment_amount', $('#payment_amount').val());
                formData.append('payment_method', $('#payment_method').val());
                formData.append('payment_reference', $('#payment_reference').val());
                formData.append('payment_date', $('#payment_date').val());
                formData.append('payment_notes', $('#payment_notes').val());
                formData.append('send_email_receipt', $('#send_email_receipt').is(':checked') ? '1' : '0');
                
                // Submit via AJAX first to check for errors, then redirect
                $.ajax({
                    url: '<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // If we get here, submission was successful
                        if (response.success && response.data.redirect_url) {
                            $button.text('Success! Redirecting...');
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert('Payment processed but unable to redirect');
                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Try to parse JSON error response
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.redirect_url) {
                                $button.text('Success! Redirecting...');
                                window.location.href = response.data.redirect_url;
                            } else {
                                alert('Error submitting payment: ' + (response.data.message || error));
                                $button.prop('disabled', false).text(originalText);
                            }
                        } catch (e) {
                            alert('Error submitting payment: ' + error);
                            $button.prop('disabled', false).text(originalText);
                        }
                    }
                });
            });
            <?php else: ?>
            // Original renewal button handling
            $('.dcmm-renew-button').on('click', function(e) {
                e.preventDefault();
                var baseUrl = $(this).data('base-url');
                var sendEmailCheckbox = $('#dcmm_send_email');
                var sendEmail = sendEmailCheckbox.length && sendEmailCheckbox.is(':checked') ? '1' : '0';
                var finalUrl = baseUrl + '&send_email=' + sendEmail;
                window.location.href = finalUrl;
            });
            <?php endif; ?>
        });
        </script>

        <?php
    }

    /**
     * Create metabox for action logs
     * 
     */
    function create_metabox_logs() {

        $logs = get_post_meta( get_the_ID(), 'dcmm_log', true );
        $payment_logs = get_post_meta( get_the_ID(), 'dcmm_payment_log', true );

        // Combine and sort all logs by time
        $all_logs = array();
        
        // Add general logs
        if ( is_array( $logs ) && ! empty( $logs ) ) {
            foreach ( $logs as $log ) {
                $all_logs[] = array(
                    'type' => 'general',
                    'time' => $log['time'],
                    'user_id' => $log['user_id'],
                    'action' => $log['action'],
                    'context' => $log['context'],
                    'message' => $log['action'] . ' (' . $log['context'] . ')'
                );
            }
        }
        
        // Add payment logs
        if ( is_array( $payment_logs ) && ! empty( $payment_logs ) ) {
            foreach ( $payment_logs as $log ) {
                $all_logs[] = array(
                    'type' => 'payment',
                    'time' => $log['time'],
                    'user_id' => $log['user_id'],
                    'message' => $log['message']
                );
            }
        }

        if ( ! empty( $all_logs ) ) {
            // Sort by time (newest first)
            usort( $all_logs, function( $a, $b ) {
                return strtotime( $b['time'] ) - strtotime( $a['time'] );
            });
            
            echo '<h4>Action Log:</h4><ul>';
            foreach ( $all_logs as $log ) {
                $user = get_user_by( 'id', $log['user_id'] );
                $user_display = $user ? esc_html( $user->display_name ) : 'System';
                
                printf(
                    '<li><strong>%s</strong> by %s — %s</li>',
                    esc_html( $log['message'] ),
                    $user_display,
                    esc_html( $log['time'] )
                );
            }
            echo '</ul>';
        }

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

        // list of keys to 
        $post_metakeys = array(
            'email',
            'first_name',
            'last_name',
            'phone',
            'address',
            'status',
            'expiration_date',
        );

        require_once('class-member.php');
        $member = new \DCMM_Member( $post_id );

        // get the meta keys for the CPT
		$meta_keys = $member::get_meta_keys();
        $nonce_prefix = $meta_keys['nonce_prefix'];

        $user_metakeys = array(
        );

        
        // loop through each field we want to save ...
        foreach ( $post_metakeys as $post_keys_index ) {

            // get the meta key for the field
            $meta_key = $meta_keys[$post_keys_index];

            // build the nonce key based on the meta key
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

            // get posted data, skipping if not present
            if ( ! isset( $_POST[$meta_key] ) ) {
                continue;
            }

            // get posted data, checking if the field is an array...
            if ( is_array( $_POST[$meta_key] ) ) {
                // ...if so, sanitize each value in the array...
                $new_meta_value = array_map( 'sanitize_text_field', $_POST[$meta_key] );
            } else {
                // ...if not, sanitize the value
                $new_meta_value = sanitize_text_field( $_POST[$meta_key] );
            }

            // update the object & post meta
            $member->save( $post_keys_index, $new_meta_value );
        }

        // Not using WP user functionality for now - come back to this in the future
        if ( false ){
            
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
}