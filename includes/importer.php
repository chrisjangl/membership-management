<?php 
/**
 * Functionality for importing members from a CSV file.
 */

namespace DCMM_Importer;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// add a submenu page to the Members CPT menu
\add_action( 'admin_menu', __NAMESPACE__ . '\membership_importer_menu' );

/**
 * Gets the slug for the member importer submenu page
 * 
 * @return string
 */
function get_importer_page_slug() {
    return 'dcmm-importer';
}

/**
 * Gets the slug for the Members CPT menu page
 * 
 * @return string
 */
function get_membership_menu_page_slug() {

    include_once( 'class-member.php' );
    $DCMM_info = new \DCMM_Member();
    $our_post_type = $DCMM_info->get_post_type();

    return 'edit.php?post_type=' . $our_post_type;
}

/**
 * Add a submenu page to Import Members via CSV.
 * 
 * @uses add_submenu_page()
 *
 * @return void
 */
function membership_importer_menu() {

    $membership_menu_page_slug = get_membership_menu_page_slug();
    $importer_page_slug = get_importer_page_slug();

    add_submenu_page(
        $membership_menu_page_slug,
        __( 'Import Members', 'dcmm-membership' ),
        __( 'Import Members', 'dcmm-membership' ),
        'manage_options',
        $importer_page_slug,
        __NAMESPACE__ . '\membership_importer_page'
    );
}

/**
 * Display the importer page.
 * 
 * Currently has instructions and a form for uploading a CSV file. 
 * Conditionally shows a success message if members were imported.
 * 
 * TODO: can we do this via AJAX?
 * TODO: move styling to CSS file
 * 
 * @uses wp_nonce_field()
 * @uses submit_button()
 *
 * @return void
 */
function membership_importer_page() {

    // give report if we've already imported
    // TODO: need to report if there's been errors
    if ( isset( $_GET['imported'] ) ) {
        $imported_count = absint( $_GET['imported'] );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d member imported.', '%d members imported.', $imported_count, 'default' ), $imported_count ) ) . '</p></div>';
    }
    ?>
    <style>
        table.borders {
            border-collapse: collapse;
        }

        table.borders th,
        table.borders td {
            border: 1px solid #ccc;
            padding: 5px;
        }
    </style>
    <div class="wrap">
        <h1><?php _e( 'Import Members', 'dc-membership' ); ?></h1>
        <p>You'll need to format your .csv with the headings shown below. <b>Membership Status</b> should be either "Active" or "Inactive".</p>
        <p><b>NOTE:</b> At present, this will only <i>import</i> members. It will not update existing members.</p>
        <table class="borders">
            <tbody>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Membership status</th>
                    <th>Street</th>
                    <th>Street 2</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Zip</th>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>###-###-####</td>
                    <td><em>[active | inactive]</em></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <div class="notice notice-info">
            <p>Some programs will encode your .csv file in a way that might cause the first column to not get imported. If you're running into this issue, try adding a blank column to the left of your data.</p>
        </div>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'dcmm-membership-importer', 'dcmm-membership-importer-nonce' ); ?>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="dcmm-membership-importer-file"><?php _e( 'CSV File', 'default' ); ?></label>
                        </th>
                        <td>
                            <input type="file" name="dcmm-membership-importer-file" id="dcmm-membership-importer-file" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Import', 'default' ) ); ?>
        </form>
    </div>
    <?php
}

// handle the import
\add_action( 'admin_init', __NAMESPACE__ . '\handle_import' );

/**
 * Handle the import.
 * 
 * Loops through each row in a .csv and creates Member & corresponding WP_User.
 * Upons success, redirects to import page with a success message.
 * We're currently only importing the following fields:
 * - First Name
 * - Last Name
 * - Email
 * - Phone
 * - Membership Status
 * - Street
 * - Street 2
 * - City
 * - State
 * - Zip
 * 
 * TODO: allow user to map fields to their own column headings
 * TODO: Create error handlers and messages
 * TODO: Create a log of imports
 * TODO: Create way to update existing members
 * TODO: Suppress email notifications when creating user!!
 * 
 * @uses wp_verify_nonce()
 * @uses wp_safe_redirect()
 * @uses admin_url()
 * @uses wp_insert_post()
 * @uses is_wp_error()
 * @uses update_post_meta()
 * @uses update_user_meta()
 * @uses get_user_by()
 * @uses get_post_meta()
 *
 * @return void
 */
function handle_import() {

    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    WP_Filesystem();
    global $wp_filesystem;

    // skip if we already imported
    if ( isset( $_GET['imported'] ) ) {
        return;
    }

    // only run if the nonce is set
    if ( ! isset( $_POST['dcmm-membership-importer-nonce'] ) ) {
        return;
    }

    // verify the nonce
    if ( ! wp_verify_nonce( sanitize_text_field( $_POST['dcmm-membership-importer-nonce'] ), 'dcmm-membership-importer' ) ) {
        return;
    }

    // Check user capabilities
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( __( 'Sorry, you do not have the required permissions to upload files.' ) );
    }

    // only run if a file was uploaded
    if ( empty( $_FILES['dcmm-membership-importer-file'] ) ) {
        return;
    }

    // retrieve the uploaded file
    $file = $_FILES['dcmm-membership-importer-file'];

    // only run if a file was actually uploaded
    if ( UPLOAD_ERR_OK !== $file['error'] ) {
        return;
    }

    // make sure we're dealing with a CSV
    if ( ! wp_check_filetype( $file['name'], array( 'csv' ) ) ) {
        return false;
    }


    // retrieve the file extension
    $extension = pathinfo( $file['name'], PATHINFO_EXTENSION );

    // only run if the file is a CSV
    if ( 'csv' !== $extension ) {
        return;
    }
    
    // get the post type for Members
    include_once( 'class-member.php' );
    $DCMM_info = new \DCMM_Member();
    $our_post_type = $DCMM_info->get_post_type();


    // start processing the CSV data

    // retrieve the file contents
    $csv = $wp_filesystem->get_contents( $file['tmp_name'] );

    // convert the CSV to an array
    $rows = array_map( 'str_getcsv', explode( "\n", $csv ) );

    // retrieve the header row
    $header = array_shift( $rows );

    // convert each column header to all lower-case (makes it easier to check existence)
    $header = array_map( 'strtolower', $header );

    // initialize counters
    $successful_imports = 0;
    $failed_imports = 0;

    // loop through the rows
    foreach ( $rows as $row ) {

        // combine the header and row into an associative array
        $data = array_combine( $header, $row );

        // skip if $data isn't an array
        if ( ! is_array( $data ) ) {
            continue;
        }

        // (maybe) initiliaze info from $data
        $first_name = isset( $data['first name'] ) ? sanitize_text_field( $data['first name'] ) : null;
        $last_name = isset( $data['last name'] ) ? sanitize_text_field( $data['last name'] ): null;
        $email = isset( $data['email'] ) ? sanitize_email( $data['email'] ): null;
        $phone = isset( $data['phone'] ) ? preg_replace('/[^0-9]/', '', $data['phone'] ) : null;
        $membership_status = isset( $data['membership status'] ) ? sanitize_text_field( $data['membership status'] ) : null;
        $street = isset( $data['street'] ) ? sanitize_text_field( $data['street'] ) : null;
        $street2 = isset( $data['street 2'] ) ? sanitize_text_field( $data['street 2'] ) : null;
        $city = isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null;
        $state = isset( $data['state'] ) ? sanitize_text_field( $data['state'] ) : null;
        $zip = isset( $data['zip'] ) ? sanitize_text_field( $data['zip'] ) : null;

        // Build post title (LastName, FirstName)
        $post_title = '';
        if ( !is_null( $last_name ) && !empty( $last_name ) ) {
            $post_title .= $last_name;

            if ( !is_null( $first_name ) && !empty( $first_name ) ) {
                $post_title .= ', ';
            }
        }

        if ( !is_null( $first_name ) && !empty( $first_name ) ) {
            $post_title .= $first_name;
        }

        if ( empty( $post_title ) ) {
            $post_title = 'unknown member';
        }

        // create the CPT
        $post_id = wp_insert_post( array(
            'post_type' => $our_post_type,
            'post_title' => $post_title,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) { 

            $failed_imports++;

            continue;
        } else {
            $successful_imports++;
        }
        
        // TODO: I'm repeating this exact code in wp-content/plugins/dc-membership/includes/class-member-metaboxes.php, save_meta(). How can I make it DRY?
        // check if we have a user for this member, and create one if not
        // if ( ! $user_id = get_post_meta( $post_id, 'dcmm_wp_user_id', true ) ) {
            
            //     include_once( 'class-member.php');
            //     $member = new \DCMM_Member( $email );
            
            //     // TODO: need to check if $member is successful
            
            //     $user_id = $member->get_wp_user_id();
            //     update_post_meta( $post_id, 'dcmm_wp_user_id', $user_id );
        // }
            
            // store the post ID in the user's meta
            // update_user_meta( $user_id, 'dcmm_post_id', $post_id );

        // get the newly created CPT post, as a Member object
        require_once( 'class-member.php' );
        $member = new \DCMM_Member( $post_id );

        // get meta keys for the CPT
        $meta_keys = $member->get_meta_keys();

        // update the CPT post's meta with Member info:
        // First name
        if ( isset( $first_name ) ) {
            $member->save( 'first_name', $first_name );
        }

        // Last name
        if ( isset( $last_name ) ) {
            $member->save( 'last_name', $last_name );
        }

        // Email
        if ( isset( $email ) ) {
            $member->save( 'email', $email );
        }

        // Phone
        if ( isset( $phone ) ) {
            $member->save( 'phone', $phone );
        }

        // Address:
        // Street 1
        if ( isset( $street ) ) {
            $address['street1'] = $street;
        }

        // Street 2
        if ( isset( $street2 ) ) {
            $address['street2'] = $street2;
        }

        // City
        if ( isset( $city ) ) {
            $address['city'] = $city;
        }

        // State
        if ( isset( $state ) ) {
            $address['state'] = $state;
        }

        // Zip
        if ( isset( $zip ) ) {
            $address['zip'] = $zip;
        }

        // If we had any of them, set the address
        if ( is_array( $address ) ) {
            $member->save( 'address', $address );
        }

        // Set the membership status
        if ( isset( $membership_status ) ) {
            $member->save( 'status', $membership_status );
        }
    }

    // redirect back to the importer page
    // URL gets built based on the original URL, and pass in the # of successful imports as a URL parameter
    $redirect_url = get_membership_menu_page_slug() . '&page=' . get_importer_page_slug() . '&imported=' . $successful_imports;
    wp_safe_redirect( admin_url( $redirect_url ) );

    exit;
}