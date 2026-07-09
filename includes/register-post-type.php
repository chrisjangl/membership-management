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
		'menu_icon'		=> 'dashicons-money', 
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
		'renewal_method' => __( 'Renewal Method', DCMM_PLUGIN_SLUG ),
		'email' => __( 'Email', DCMM_PLUGIN_SLUG ),
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
		case 'renewal_method':
			$renewal_method = $member->get_renewal_method();
			echo $renewal_method ? esc_html( $renewal_method ) : '---';
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

	if ( $renewal_method === 'subscription' ) {
		$query->set( 'meta_query', array(
			array(
				'key'     => 'dcmm_subscription_status',
				'value'   => array( 'active', 'trialing' ),
				'compare' => 'IN',
			),
		) );
	} elseif ( $renewal_method === 'manual' ) {
		$query->set( 'meta_query', array(
			'relation' => 'AND',
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
		) );
	}
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