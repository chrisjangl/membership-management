<?php 
/**
 * Functionality for importing members from a CSV file.
 */

add_action( 'init', 'stop_heartbeat', 1 );
function stop_heartbeat() {
    wp_deregister_script('heartbeat');
}

// add a submenu page to the Members CPT menu
add_action( 'admin_menu', 'dc_membership_importer_menu' );

/**
 * Add a submenu page to Import Members via CSV.
 * 
 * @uses add_submenu_page()
 *
 * @return void
 */
function dc_membership_importer_menu() {
    add_submenu_page(
        'edit.php?post_type=dc-member',
        __( 'Import Members', 'dc-membership' ),
        __( 'Import Members', 'dc-membership' ),
        'manage_options',
        'dc-membership-importer',
        'dc_membership_importer_page'
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
function dc_membership_importer_page() {

    // give report if we've already imported
    // TODO: need to report if there's been errors
    if ( isset( $_GET['imported'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%d member imported.', '%d members imported.', $_GET['imported'], 'dc-membership' ), $_GET['imported'] ) . '</p></div>';
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
            <?php wp_nonce_field( 'dc-membership-importer', 'dc-membership-importer-nonce' ); ?>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="dc-membership-importer-file"><?php _e( 'CSV File', 'dc-membership' ); ?></label>
                        </th>
                        <td>
                            <input type="file" name="dc-membership-importer-file" id="dc-membership-importer-file" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Import', 'dc-membership' ) ); ?>
        </form>
    </div>
    <?php
}

// handle the import
add_action( 'admin_init', 'dc_membership_importer_handle_import' );

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
 * TODO: sanitize inputs 
 * TODO: Create error handlers and messages
 * TODO: Create a log of imports
 * TODO: Create way to update existing members
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
function dc_membership_importer_handle_import() {

    // skip if we already imported
    if ( isset( $_GET['imported'] ) ) {
        return;
    }

    // only run if the nonce is set
    if ( ! isset( $_POST['dc-membership-importer-nonce'] ) ) {
        return;
    }

    // verify the nonce
    if ( ! wp_verify_nonce( $_POST['dc-membership-importer-nonce'], 'dc-membership-importer' ) ) {
        return;
    }

    // only run if a file was uploaded
    if ( empty( $_FILES['dc-membership-importer-file'] ) ) {
        return;
    }

    // retrieve the uploaded file
    $file = $_FILES['dc-membership-importer-file'];

    // only run if a file was actually uploaded
    if ( UPLOAD_ERR_OK !== $file['error'] ) {
        return;
    }

    // retrieve the file extension
    $extension = pathinfo( $file['name'], PATHINFO_EXTENSION );

    // only run if the file is a CSV
    if ( 'csv' !== $extension ) {
        return;
    }

    // retrieve the file contents
    $csv = file_get_contents( $file['tmp_name'] );

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
        $first_name = isset( $data['first name'] ) ? $data['first name'] : null;
        $last_name = isset( $data['last name'] ) ? $data['last name'] : null;
        $email = isset( $data['email'] ) ? $data['email'] : null;

        // bail if we don't have an email
        if ( ! isset( $email ) ) {
            continue;
        }

        $phone = isset( $data['phone'] ) ? $data['phone'] : null;
        $membership_status = isset( $data['membership status'] ) ? $data['membership status'] : null;
        $street = isset( $data['street'] ) ? $data['street'] : null;
        $street2 = isset( $data['street 2'] ) ? $data['street 2'] : null;
        $city = isset( $data['city'] ) ? $data['city'] : null;
        $state = isset( $data['state'] ) ? $data['state'] : null;
        $zip = isset( $data['zip'] ) ? $data['zip'] : null;


        // Build post title (First Name + Last Name)
        $post_title = '';
        if ( isset( $first_name ) ) {
            $post_title .= $first_name;
        }

        if ( isset( $last_name ) ) {
            $post_title .= ' ' . $last_name;
        }

        // create the CPT
        $post_id = wp_insert_post( array(
            'post_type' => 'dc-member',
            'post_title' => $post_title,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {

            // record the email address 

            $failed_imports++;

            continue;
        } else {
            $successful_imports++;
        }

        // save the member's email to the post
        update_post_meta( $post_id, 'dc_member_email', $email );


        // TODO: I'm repeating this exact code in wp-content/plugins/dc-membership/includes/class-member-metaboxes.php, save_meta(). How can I make it DRY?
        // check if we have a user for this member, and create one if not
        if ( ! $user_id = get_post_meta( $post_id, 'dc_member_wp_user_id', true ) ) {
            
            include_once( 'class-member.php');
            $member = new \DC_Member( $email );

            // TODO: need to check if $member is successful

            $user_id = $member->get_wp_user_id();
            update_post_meta( $post_id, 'dc_member_wp_user_id', $user_id );
        }

        // store the post ID in the user's meta
        update_user_meta( $user_id, 'dc_member_post_id', $post_id );

        // update the WP_User's meta:
        // First name
        if ( isset( $first_name ) ) {
            update_user_meta( $user_id, 'dc_member_first_name', $first_name );
        }

        // Last name
        if ( isset( $last_name ) ) {
            update_user_meta( $user_id, 'dc_member_last_name', $last_name );
        }

        // Phone
        if ( isset( $phone ) ) {
            update_user_meta( $user_id, 'dc_member_phone', $phone );
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
            update_user_meta( $user_id, 'dc_member_mailing_address', $address );
        }

        // Set the membership status
        if ( isset( $membership_status ) ) {
            update_user_meta( $user_id, 'dc_membership_status', strtolower($membership_status) );
        }
    }

    // redirect back to the importer page
    wp_safe_redirect( admin_url( 'edit.php?post_type=dc-member&page=dc-membership-importer&imported=' . $successful_imports ) );

    exit;
}