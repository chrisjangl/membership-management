<?php
/**
 * Set up the Member post type
 * 
 * TODO: NEED TO CONVERT THIS FROM A CLASS TO A COLLECTION OF FUNCTIONS THAT SET UP THE POST TYPE
 * 
 * @todo: create user role (here? or in contact? or both?)
 * @todo: create way to check if user is member
 * 
 */
namespace DCMM_Post_Type;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


function get_post_type() {
	return 'dcmm-member';
}

function construct_member_post_type( ) {

	register_action_hooks();

	// register meta boxes
	add_member_meta_boxes();

}

/**
 * register different functions to fire on action hooks
 */
function register_action_hooks() {

	$our_post_type = get_post_type();
	
	// register our post type
	\add_action( 'init', '\DCMM_Post_Type\dcmm_register_post_type' );

	// customize the columns shown in the All Members screen
	\add_action( "manage_{$our_post_type}_posts_columns", '\DCMM_Post_Type\dcmm_custom_columns', 10, 1 );

	// populate our custom columns
	\add_action( "manage_{$our_post_type}_posts_custom_column", '\DCMM_Post_Type\dcmm_populate_custom_columns', 10, 2 );

	// make the columns sortable
	\add_filter( "manage_edit-{$our_post_type}_sortable_columns", '\DCMM_Post_Type\dcmm_sortable_columns' );

	// handle custom sorting
	\add_action( 'pre_get_posts', '\DCMM_Post_Type\dcmm_sortable_columns_orderby' );

	// add member status filter dropdown
	\add_action( 'restrict_manage_posts', '\DCMM_Post_Type\dcmm_status_filter_dropdown' );

	// apply member status filter to query
	\add_action( 'pre_get_posts', '\DCMM_Post_Type\dcmm_filter_by_status' );

	// add renewal method filter dropdown
	\add_action( 'restrict_manage_posts', '\DCMM_Post_Type\dcmm_renewal_method_filter_dropdown' );

	// apply renewal method filter to query
	\add_action( 'pre_get_posts', '\DCMM_Post_Type\dcmm_filter_by_renewal_method' );

	// add Pause/Resume Subscriptions to the bulk actions dropdown
	\add_filter( "bulk_actions-edit-{$our_post_type}", '\DCMM_Post_Type\dcmm_register_subscription_bulk_actions' );

	// handle the Pause/Resume Subscriptions bulk actions
	\add_filter( "handle_bulk_actions-edit-{$our_post_type}", '\DCMM_Post_Type\dcmm_handle_subscription_bulk_actions', 10, 3 );

	// show a notice with the results of a subscription bulk action
	\add_action( 'admin_notices', '\DCMM_Post_Type\dcmm_bulk_action_notices' );

	// add WP User account filter dropdown
	\add_action( 'restrict_manage_posts', '\DCMM_Post_Type\dcmm_wp_user_filter_dropdown' );

	// apply WP User account filter to query
	\add_action( 'pre_get_posts', '\DCMM_Post_Type\dcmm_filter_by_wp_user' );

	// expose member data to Quick Edit via the row's hidden inline data
	\add_action( 'add_inline_data', '\DCMM_Post_Type\dcmm_add_quick_edit_inline_data', 10, 2 );

	// add our fields to the Quick Edit panel
	\add_action( 'quick_edit_custom_box', '\DCMM_Post_Type\dcmm_quick_edit_fields', 10, 2 );

	// save our Quick Edit fields
	\add_action( "save_post_{$our_post_type}", '\DCMM_Post_Type\dcmm_save_quick_edit', 10, 2 );

	// load the Quick Edit script on the Members list screen
	\add_action( 'admin_enqueue_scripts', '\DCMM_Post_Type\dcmm_enqueue_members_list_scripts' );

}

/**
 * Register our post type
 *
 * @return void
 */
function dcmm_register_post_type() {

	$labels = array(
		'name'               => __( 'Members', DCMM_PLUGIN_SLUG ),
		'singular_name'      => __( 'Member', DCMM_PLUGIN_SLUG ),
		'add_new'            =>   ( 'Add New' ),
		'add_new_item'       => __( 'Add New Member', DCMM_PLUGIN_SLUG ),
		'edit_item'          => __( 'Edit Member', DCMM_PLUGIN_SLUG ),
		'new_item'           => __( 'New Member', DCMM_PLUGIN_SLUG ),
		'all_items'          => __( 'All Members', DCMM_PLUGIN_SLUG ),
		'view_item'          => __( 'View Member', DCMM_PLUGIN_SLUG ),
		'search_items'       => __( 'Search Members', DCMM_PLUGIN_SLUG ),
		'not_found'          => __( 'No Members found', DCMM_PLUGIN_SLUG ),
		'not_found_in_trash' => __( 'No Members found in the Trash', DCMM_PLUGIN_SLUG) , 
		'parent_item_colon'  => '',
		'menu_name'          => 'Members',
	);

	$args = array(
		'labels'        => $labels,
		'description'   => 'Organization member',
		'public'        => true,
		'publicly_queryable' => false,
		'show_ui'	   => true,
		'show_in_rest'	=> true,
		'slug'			=> 'member',
		'exclude_from_search' => true,
		'menu_position' => 5,
		'menu_icon'		=> 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( DCMM_PATH . 'assets/images/dashicon.svg' ) ),
		'supports'      => array( 'title', 'custom_fields' ),
		'has_archive'   => false,
		'capability_type' => 'dcmm_member',
		'capabilities' => array(
			'edit_post'          => 'edit_dcmm_member',
			'read_post'          => 'read_dcmm_member',
			'delete_post'        => 'delete_dcmm_member',
			'edit_posts'         => 'edit_dcmm_members',
			'edit_others_posts'  => 'edit_others_dcmm_members',
			'publish_posts'      => 'publish_dcmm_members',
			'read_private_posts' => 'read_private_dcmm_members',
			'delete_posts'       => 'delete_dcmm_members',
			'delete_private_posts' => 'delete_private_dcmm_members',
			'delete_published_posts' => 'delete_published_dcmm_members',
			'delete_others_posts' => 'delete_others_dcmm_members',
			'edit_private_posts' => 'edit_private_dcmm_members',
			'edit_published_posts' => 'edit_published_dcmm_members',
			'create_posts'       => 'create_dcmm_members',
		),
		'map_meta_cap' => true,
	);
	
	\register_post_type( get_post_type(), $args ); 

}

/**
 * Customize the columns shown in the All Members screen.
 * 
 * Add a Name column and a Membership Status column, and brings the 
 * cb column from what we were passed.
 * 
 * TODO: If Join policy is "rolling", add expiration date column
 * 
 * @param array $default_columns
 * 
 * @return array $columns
 */
function dcmm_custom_columns( $default_columns ) {

	$dcmm_columns = array(
		'cb' => $default_columns['cb'],
		'name' => 'Name',
		'status' => __( 'Membership Status', DCMM_PLUGIN_SLUG ),
		'expiration_date' => __( 'Expiration Date', DCMM_PLUGIN_SLUG ),
		'renewal_method' => __( 'Renewal Method', DCMM_PLUGIN_SLUG ),
		'email' => __( 'Email', DCMM_PLUGIN_SLUG ),
		'wp_user' => __( 'WP User', DCMM_PLUGIN_SLUG ),
		'address' => __( 'Address', DCMM_PLUGIN_SLUG ),
		'phone' => __( 'Phone', DCMM_PLUGIN_SLUG ),
	);

	return $dcmm_columns;
}

/**
 * Populate our custom columns
 * 
 * @param string $column_name
 * @param int $post_id
 * 
 * @return void
 */
function dcmm_populate_custom_columns( $column_name, $post_id ) {
	
	$member = new \DCMM_Member( $post_id );

	switch ( $column_name ) {
		case 'name':
			echo $member->get('last_name') . ', ' . $member->get('first_name');

			// Core only prints Quick Edit's hidden inline data in the Title
			// column, which we replace; without it Quick Edit opens empty and
			// saving blanks the post title.
			if ( function_exists( 'get_inline_data' ) ) {
				get_inline_data( get_post( $post_id ) );
			}
			break;
		case 'email':
			echo $member->get('email');
			break;
		case 'address':
			$address = $member->get('address');

			if ( ! is_array( $address ) || empty( $address ) ) {
				echo '---';
				
			} else {

				$street_1 = isset ( $address['street1'] ) ? $address['street1'] : '';
				$street_2 = isset ( $address['street2'] ) ? $address['street2'] : '';
				$city = isset ( $address['city'] ) ? $address['city'] : '';
				$state = isset ( $address['state'] ) ? $address['state'] : '';
				$zip = isset ( $address['zip'] ) ? $address['zip'] : '';

				$formatted_address = ( $street_1 ? $street_1 . "<br>" : '' ) . ( $street_2 ? $street_2 . "<br>": '' ) . ( $city ? $city . ", " : "" ) . ( $state ? $state . " " : "" ) . ' ' . $zip;
				
				echo $formatted_address;
			}
			break;
		case "phone":
			if ( ! $member->get( 'phone' ) ) {
				echo '---';
			} else {
				echo $member->get('phone');
			}
			break;
		case 'status':
			$status = $member->get( 'status');
			$renewal_status = $member->get_renewal_status();
			
			// Status with renewal window indicator
			echo '<span class="dcmm-status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span>';
			
			// Add renewal window indicator
			if ($status === 'active') {
				switch ($renewal_status) {
					case 'available':
						echo '<br><span class="dcmm-renewal-indicator dcmm-available" title="Renewal available">🟡 Renewable</span>';
						break;
					case 'too_early':
						$days = $member->get_days_until_renewal_window();
						echo '<br><span class="dcmm-renewal-indicator dcmm-pending" title="Renewal in ' . $days . ' days">🟢</span>';
						break;
					case 'grace':
						echo '<br><span class="dcmm-renewal-indicator dcmm-grace" title="In grace period">🟠 Grace</span>';
						break;
				}
			} elseif ($status === 'inactive') {
				// For inactive members, still show renewal status if available
				if ($renewal_status === 'grace') {
					echo '<br><span class="dcmm-renewal-indicator dcmm-grace" title="Grace period - can still renew">🟠 Grace</span>';
				} elseif ($renewal_status === 'suspended') {
					echo '<br><span class="dcmm-renewal-indicator dcmm-suspended" title="Membership suspended">🔴 Suspended</span>';
				}
			}
			break;
		case 'expiration_date':
			$expiration_date = $member->get_expiration_date();
			echo $expiration_date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expiration_date ) ) ) : '---';
			break;
		case 'renewal_method':
			$renewal_method = $member->get_renewal_method();
			echo $renewal_method ? esc_html( $renewal_method ) : '---';
			break;
		case 'wp_user':
			if ( ! $member->has_wp_user() ) {
				echo '---';
				break;
			}

			$wp_user = get_userdata( $member->get_wp_user_id() );
			if ( $wp_user ) {
				echo '<a href="' . esc_url( get_edit_user_link( $wp_user->ID ) ) . '">' . esc_html( $wp_user->user_login ) . '</a>';
			} else {
				/* translators: %d: WordPress user ID */
				echo esc_html( sprintf( __( 'Missing (ID %d)', DCMM_PLUGIN_SLUG ), $member->get_wp_user_id() ) );
			}
			break;
	}
}

/**
 * Make the columns sortable
 * 
 * @param array $columns
 * 
 * @return array $columns
 */
function dcmm_sortable_columns( $columns ) {
	
	$columns['name'] = 'dcmm_name';
	$columns['status'] = 'dcmm_status';
	$columns['expiration_date'] = 'dcmm_expiration_date';

	return $columns;
}

/**
 * Handle custom sorting for columns.
 * 
 * Handles sorting for the Name and Status columns.
 * 
 * @param WP_Query $query
 * 
 * @return void
 */
function dcmm_sortable_columns_orderby( $query ) {
	
	if( ! is_admin()  || ! $query->is_main_query() ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	$member = new \DCMM_Member();
	$meta_keys = $member->get_meta_keys();


	if( 'dcmm_name' == $orderby ) {
		$meta_key = $meta_keys['last_name'];
		$query->set( 'meta_key', $meta_key );
		$query->set( 'orderby', 'meta_value' );
	}

	if( 'dcmm_status' == $orderby ) {
		$meta_key = $meta_keys['status'];
		$query->set( 'meta_key', $meta_key );
		$query->set( 'orderby', 'meta_value' );
	}

	if ( 'dcmm_expiration_date' == $orderby ) {
		$meta_key = $meta_keys['expiration_date'];
		$query->set( 'meta_key', $meta_key );
		$query->set( 'orderby', 'meta_value' );
	}
}


/**
 * Output the Member Status filter dropdown on the Members list screen.
 */
function dcmm_status_filter_dropdown() {
	global $typenow;
	if ( $typenow !== get_post_type() ) {
		return;
	}

	$selected = isset( $_GET['dcmm_status'] ) ? sanitize_key( $_GET['dcmm_status'] ) : '';

	echo '<select name="dcmm_status">';
	echo '<option value="">' . esc_html__( 'All Statuses', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '<option value="active"' . selected( $selected, 'active', false ) . '>' . esc_html__( 'Active', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '<option value="inactive"' . selected( $selected, 'inactive', false ) . '>' . esc_html__( 'Inactive', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '</select>';
}

/**
 * Filter the Members query by status when the dropdown is used.
 *
 * @param WP_Query $query
 */
function dcmm_filter_by_status( $query ) {
	global $pagenow;

	if ( ! is_admin() || ! $query->is_main_query() || $pagenow !== 'edit.php' ) {
		return;
	}

	if ( $query->get( 'post_type' ) !== get_post_type() ) {
		return;
	}

	$status = isset( $_GET['dcmm_status'] ) ? sanitize_key( $_GET['dcmm_status'] ) : '';

	if ( empty( $status ) ) {
		return;
	}

	$existing_meta_query = $query->get( 'meta_query' ) ?: array();

	$query->set( 'meta_query', array_merge( $existing_meta_query, array(
		array(
			'key'     => 'dcmm_status',
			'value'   => $status,
			'compare' => '=',
		),
	) ) );
}

/**
 * Output the Renewal Method filter dropdown on the Members list screen.
 */
function dcmm_renewal_method_filter_dropdown() {
	global $typenow;
	if ( $typenow !== get_post_type() ) {
		return;
	}

	$selected = isset( $_GET['dcmm_renewal_method'] ) ? sanitize_key( $_GET['dcmm_renewal_method'] ) : '';

	echo '<select name="dcmm_renewal_method">';
	echo '<option value="">' . esc_html__( 'All Renewal Types', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '<option value="subscription"' . selected( $selected, 'subscription', false ) . '>' . esc_html__( 'Subscription', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '<option value="manual"' . selected( $selected, 'manual', false ) . '>' . esc_html__( 'Manual', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '</select>';
}

/**
 * Filter the Members query by renewal method when the dropdown is used.
 *
 * @param WP_Query $query
 */
function dcmm_filter_by_renewal_method( $query ) {
	global $pagenow;

	if ( ! is_admin() || ! $query->is_main_query() || $pagenow !== 'edit.php' ) {
		return;
	}

	if ( $query->get( 'post_type' ) !== get_post_type() ) {
		return;
	}

	$renewal_method = isset( $_GET['dcmm_renewal_method'] ) ? sanitize_key( $_GET['dcmm_renewal_method'] ) : '';

	if ( empty( $renewal_method ) ) {
		return;
	}

	// merge with other filters' clauses (e.g. Membership Status) instead of replacing them
	$existing_meta_query = $query->get( 'meta_query' ) ?: array();

	if ( $renewal_method === 'subscription' ) {
		$query->set( 'meta_query', array_merge( $existing_meta_query, array(
			array(
				'key'     => 'dcmm_subscription_status',
				'value'   => array( 'active', 'trialing' ),
				'compare' => 'IN',
			),
		) ) );
	} elseif ( $renewal_method === 'manual' ) {
		$query->set( 'meta_query', array_merge( $existing_meta_query, array(
			array(
				'key'     => 'dcmm_status',
				'value'   => 'active',
				'compare' => '=',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => 'dcmm_subscription_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'dcmm_subscription_status',
					'value'   => array( 'active', 'trialing' ),
					'compare' => 'NOT IN',
				),
			),
		) ) );
	}
}

/**
 * Add "Pause Subscriptions" and "Resume Subscriptions" to the bulk actions
 * dropdown on the Members list screen.
 *
 * @param array $bulk_actions
 *
 * @return array $bulk_actions
 */
function dcmm_register_subscription_bulk_actions( $bulk_actions ) {

	$bulk_actions['dcmm_pause_subscriptions'] = __( 'Pause Subscriptions', DCMM_PLUGIN_SLUG );
	$bulk_actions['dcmm_resume_subscriptions'] = __( 'Resume Subscriptions', DCMM_PLUGIN_SLUG );

	return $bulk_actions;
}

/**
 * Handle the "Pause Subscriptions" / "Resume Subscriptions" bulk actions.
 *
 * Members with no gateway subscription are silently skipped. Gateway
 * failures are counted and reported back via the redirect URL instead of
 * halting the rest of the batch.
 *
 * @param string $redirect_to
 * @param string $doaction
 * @param int[]  $post_ids
 *
 * @return string $redirect_to
 */
function dcmm_handle_subscription_bulk_actions( $redirect_to, $doaction, $post_ids ) {

	if ( ! in_array( $doaction, array( 'dcmm_pause_subscriptions', 'dcmm_resume_subscriptions' ), true ) ) {
		return $redirect_to;
	}

	if ( ! current_user_can( 'edit_dcmm_members' ) ) {
		return $redirect_to;
	}

	$succeeded = 0;
	$failed = 0;

	foreach ( $post_ids as $post_id ) {
		$member = new \DCMM_Member( $post_id );

		// skip members with no subscription to act on
		if ( ! $member->get_subscription_data() ) {
			continue;
		}

		$result = ( 'dcmm_pause_subscriptions' === $doaction )
			? $member->pause_subscription()
			: $member->resume_subscription();

		if ( is_wp_error( $result ) ) {
			$failed++;
		} else {
			$succeeded++;
		}
	}

	$redirect_to = remove_query_arg( array( 'dcmm_bulk_action', 'dcmm_bulk_succeeded', 'dcmm_bulk_failed' ), $redirect_to );

	return add_query_arg( array(
		'dcmm_bulk_action'    => $doaction,
		'dcmm_bulk_succeeded' => $succeeded,
		'dcmm_bulk_failed'    => $failed,
	), $redirect_to );
}

/**
 * Show an admin notice with the results of a subscription bulk action.
 *
 * @return void
 */
function dcmm_bulk_action_notices() {
	global $pagenow;

	if ( $pagenow !== 'edit.php' || ! isset( $_GET['dcmm_bulk_action'] ) ) {
		return;
	}

	$action = sanitize_key( $_GET['dcmm_bulk_action'] );
	if ( ! in_array( $action, array( 'dcmm_pause_subscriptions', 'dcmm_resume_subscriptions' ), true ) ) {
		return;
	}

	$succeeded = isset( $_GET['dcmm_bulk_succeeded'] ) ? (int) $_GET['dcmm_bulk_succeeded'] : 0;
	$failed = isset( $_GET['dcmm_bulk_failed'] ) ? (int) $_GET['dcmm_bulk_failed'] : 0;
	$verb = ( 'dcmm_pause_subscriptions' === $action ) ? 'paused' : 'resumed';

	$message = sprintf( '%d subscription(s) %s.', $succeeded, $verb );
	if ( $failed > 0 ) {
		$message .= ' ' . sprintf( '%d failed.', $failed );
	}

	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		esc_attr( $failed > 0 ? 'warning' : 'success' ),
		esc_html( $message )
	);
}

/**
 * Output the WP User account filter dropdown on the Members list screen.
 */
function dcmm_wp_user_filter_dropdown() {
	global $typenow;
	if ( $typenow !== get_post_type() ) {
		return;
	}

	$selected = isset( $_GET['dcmm_wp_user'] ) ? sanitize_key( $_GET['dcmm_wp_user'] ) : '';

	echo '<select name="dcmm_wp_user">';
	echo '<option value="">' . esc_html__( 'All WP User Accounts', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '<option value="has"' . selected( $selected, 'has', false ) . '>' . esc_html__( 'Has WP User', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '<option value="none"' . selected( $selected, 'none', false ) . '>' . esc_html__( 'No WP User', DCMM_PLUGIN_SLUG ) . '</option>';
	echo '</select>';
}

/**
 * Filter the Members query by whether the member has a linked WP User.
 *
 * @param WP_Query $query
 */
function dcmm_filter_by_wp_user( $query ) {
	global $pagenow;

	if ( ! is_admin() || ! $query->is_main_query() || $pagenow !== 'edit.php' ) {
		return;
	}

	if ( $query->get( 'post_type' ) !== get_post_type() ) {
		return;
	}

	$filter = isset( $_GET['dcmm_wp_user'] ) ? sanitize_key( $_GET['dcmm_wp_user'] ) : '';

	if ( ! in_array( $filter, array( 'has', 'none' ), true ) ) {
		return;
	}

	$meta_key = \DCMM_Member::get_meta_keys( 'wp_user_id' );

	if ( 'has' === $filter ) {
		$clause = array(
			'key'     => $meta_key,
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
	} else {
		// no meta, or an empty / zero value
		$clause = array(
			'relation' => 'OR',
			array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $meta_key,
				'value'   => 0,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
		);
	}

	$existing_meta_query = $query->get( 'meta_query' ) ?: array();

	$query->set( 'meta_query', array_merge( $existing_meta_query, array( $clause ) ) );
}

/**
 * Add member data to the row's hidden inline data, so Quick Edit can
 * pre-fill our fields (see js/admin-members-list.js).
 *
 * @param WP_Post      $post
 * @param WP_Post_Type $post_type_object
 *
 * @return void
 */
function dcmm_add_quick_edit_inline_data( $post, $post_type_object ) {

	if ( get_post_type() !== $post->post_type ) {
		return;
	}

	$member = new \DCMM_Member( $post->ID );

	$wp_user_login = '';
	if ( $member->has_wp_user() ) {
		$wp_user = get_userdata( $member->get_wp_user_id() );
		$wp_user_login = $wp_user ? $wp_user->user_login : '';
	}

	$fields = array(
		'dcmm_first_name'    => $member->get( 'first_name' ),
		'dcmm_last_name'     => $member->get( 'last_name' ),
		'dcmm_email'         => $member->get( 'email' ),
		'dcmm_status'        => $member->get( 'status' ),
		'dcmm_has_wp_user'   => $member->has_wp_user() ? '1' : '0',
		'dcmm_wp_user_login' => $wp_user_login,
	);

	foreach ( $fields as $class => $value ) {
		echo '<div class="' . esc_attr( $class ) . '">' . esc_html( is_scalar( $value ) ? $value : '' ) . '</div>';
	}
}

/**
 * Output our fields in the Quick Edit panel.
 *
 * Fires once per column; we output everything once, on the Status column.
 * Field names are prefixed with dcmm_qe_ because the Quick Edit row is moved
 * inside the list's filter form, which already uses names like dcmm_status.
 *
 * Core's Title, Date, Password, Private and Status fields are hidden via CSS
 * (src/scss/_members-list.scss).
 *
 * @param string $column_name
 * @param string $post_type
 *
 * @return void
 */
function dcmm_quick_edit_fields( $column_name, $post_type ) {

	if ( get_post_type() !== $post_type || 'status' !== $column_name ) {
		return;
	}

	wp_nonce_field( 'dcmm_quick_edit', 'dcmm_quick_edit_nonce', false );
	?>
	<fieldset class="dcmm-quick-edit">
		<div class="inline-edit-col dcmm-qe-grid">
			<label>
				<span class="title"><?php esc_html_e( 'First name', DCMM_PLUGIN_SLUG ); ?></span>
				<span class="input-text-wrap"><input type="text" name="dcmm_qe_first_name" value="" /></span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Last name', DCMM_PLUGIN_SLUG ); ?></span>
				<span class="input-text-wrap"><input type="text" name="dcmm_qe_last_name" value="" /></span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Email', DCMM_PLUGIN_SLUG ); ?></span>
				<span class="input-text-wrap"><input type="email" name="dcmm_qe_email" value="" /></span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Membership status', DCMM_PLUGIN_SLUG ); ?></span>
				<span class="input-text-wrap">
					<select name="dcmm_qe_status">
						<?php foreach ( \DCMM_Member::get_valid_statuses() as $status ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			</label>
			<div class="dcmm-qe-wp-user-linked">
				<span class="title"><?php esc_html_e( 'WP User', DCMM_PLUGIN_SLUG ); ?></span>
				<span class="dcmm-qe-wp-user-login"></span>
			</div>
			<label class="dcmm-qe-wp-user-create">
				<input type="checkbox" name="dcmm_qe_create_wp_user" value="1" />
				<span class="checkbox-title"><?php esc_html_e( 'Create a WP User account (uses the email above)', DCMM_PLUGIN_SLUG ); ?></span>
			</label>
		</div>
	</fieldset>
	<?php
}

/**
 * Save our Quick Edit fields.
 *
 * Only runs for the Quick Edit AJAX request; the Edit screen saves through
 * DCMM_metaboxes::save_meta().
 *
 * @param int     $post_id
 * @param WP_Post $post
 *
 * @return void
 */
function dcmm_save_quick_edit( $post_id, $post ) {

	if ( ! wp_doing_ajax() || ! isset( $_POST['action'] ) || 'inline-save' !== $_POST['action'] ) {
		return;
	}

	if ( ! isset( $_POST['dcmm_quick_edit_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dcmm_quick_edit_nonce'] ) ), 'dcmm_quick_edit' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$member = new \DCMM_Member( $post_id );

	foreach ( array( 'first_name', 'last_name' ) as $key ) {
		if ( isset( $_POST[ 'dcmm_qe_' . $key ] ) ) {
			$member->save( $key, sanitize_text_field( wp_unslash( $_POST[ 'dcmm_qe_' . $key ] ) ) );
		}
	}

	if ( isset( $_POST['dcmm_qe_email'] ) ) {
		$raw_email = trim( wp_unslash( $_POST['dcmm_qe_email'] ) );

		// an empty field clears the email; an invalid one is skipped rather
		// than overwriting a good address (sanitize_email() returns '' for it)
		if ( '' === $raw_email ) {
			$member->save( 'email', '' );
		} elseif ( is_email( sanitize_email( $raw_email ) ) ) {
			$member->save( 'email', sanitize_email( $raw_email ) );
		}
	}

	if ( isset( $_POST['dcmm_qe_status'] ) ) {
		$status = sanitize_key( wp_unslash( $_POST['dcmm_qe_status'] ) );

		if ( in_array( $status, \DCMM_Member::get_valid_statuses(), true ) ) {
			$member->save( 'status', $status );
		}
	}

	// runs after the email is saved, so a newly entered address is used
	if ( ! empty( $_POST['dcmm_qe_create_wp_user'] ) && ! $member->has_wp_user() ) {
		$result = $member->create_wp_user();

		if ( is_wp_error( $result ) ) {
			error_log( 'DCMM: Quick Edit could not create a WP user for member ' . $post_id . ': ' . $result->get_error_message() );
		}
	}
}

/**
 * Enqueue the Quick Edit script on the Members list screen.
 *
 * @param string $hook
 *
 * @return void
 */
function dcmm_enqueue_members_list_scripts( $hook ) {

	if ( 'edit.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || get_post_type() !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'dcmm-admin-members-list',
		DCMM_URL . 'js/admin-members-list.js',
		array( 'jquery', 'inline-edit-post' ),
		DCMM_VERSION,
		true
	);
}

/**
 * add our meta boxes to the edit Member screen
 *
 * TODO: link to the metabox class
 * @return void
 */
function add_member_meta_boxes() {

	require_once( 'class-member-metaboxes.php' );
	new \DCMM_metaboxes();

}

construct_member_post_type();