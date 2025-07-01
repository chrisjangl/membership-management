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
        wp_enqueue_style( 'dcmm_admin_styles', plugin_dir_url( dirname(__FILE__)  ) . 'assets/css/member-admin.css', array(), '1.0' );
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
            echo '<p><a href="#" class="button button-secondary" data-action="create_wp_user">Create WP User Account</a></p>';

            // when clicked, use AJAX to run DCMM_Member::create_wp_user_account()
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.button-secondary').on('click', function(e) {
                    e.preventDefault();
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
                                alert('Success: ' + response.data.message);
                                location.reload();
                            } else {
                                alert('Error: ' + response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                        }
                    })
                });
            });
            </script>

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

        // Get the membership status
        $member = new DCMM_Member( $CPT_post_id );
        $member_id = $member->get_member_id();
        $meta_keys = $member->get_meta_keys();
        $nonce_prefix = $meta_keys['nonce_prefix'];
        $membership_status = $member->get( 'status' );

        wp_nonce_field( $nonce_prefix, 'dcmm_status_nonce' ); ?>
        
        <p>
            <label for="dcmm_status">Membership status:</label>
            <select name="dcmm_status" id="dcmm_status">
                <option value="--" <?php selected( $membership_status, '' ); ?>>--</option>
                <option value="active" <?php selected( $membership_status, 'active' ); ?>>Active</option>
                <option value="inactive" <?php selected( $membership_status, 'inactive' ); ?>>Inactive</option>
            </select>
        </p>
        <?php

        // Renew link
        $renew_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=dcmm_manual_renew&member_id=' . $member_id ),
            'dcmm_manual_renew_' . $member_id
        );

        // Cancel link
        $cancel_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=dcmm_manual_cancel&member_id=' . $member_id ),
            'dcmm_manual_cancel_' . $member_id
        );
        ?>

        <p>
            <a href="<?php echo esc_url( $renew_url ); ?>" class="button button-primary">Renew Membership</a>
            <br>
            <br>
            <a href="<?php echo esc_url( $cancel_url ); ?>" class="button">Cancel Membership</a>
        </p>

        <?php
    }

    /**
     * Create metabox for action logs
     * 
     */
    function create_metabox_logs() {

        $logs = get_post_meta( get_the_ID(), 'dcmm_log', true );

        if ( is_array( $logs ) && ! empty( $logs ) ) {
            echo '<h4>Action Log:</h4><ul>';
            foreach ( array_reverse( $logs ) as $log ) {
                $user = get_user_by( 'id', $log['user_id'] );
                printf(
                    '<li><strong>%s</strong> by %s (%s) — %s</li>',
                    esc_html( $log['action'] ),
                    $user ? esc_html( $user->display_name ) : 'System',
                    esc_html( $log['context'] ),
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
            'status'
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