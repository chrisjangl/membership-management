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

}

/**
 * Register our post type
 *
 * @return void
 */
function dcmm_register_post_type() {

	$labels = array(
		'name'               => __( 'Members', 'post type general name' ),
		'singular_name'      => __( 'Member', 'post type singular name' ),
		'add_new'            =>   ( 'Add New' ),
		'add_new_item'       => __( 'Add New Member' ),
		'edit_item'          => __( 'Edit Member' ),
		'new_item'           => __( 'New Member' ),
		'all_items'          => __( 'All Members' ),
		'view_item'          => __( 'View Member' ),
		'search_items'       => __( 'Search Members' ),
		'not_found'          => __( 'No Members found' ),
		'not_found_in_trash' => __( 'No Members found in the Trash' ), 
		'parent_item_colon'  => '',
		'menu_name'          => 'Members'
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
		'menu_icon'		=> 'dashicons-user', 
		'supports'      => array( 'title', 'custom_fields' ),
		'has_archive'   => false,
	);
	
	\register_post_type( get_post_type(), $args ); 

}

/**
 * Customize the columns shown in the All Members screen.
 * 
 * Add a Name column and a Membership Status column, and brings the 
 * cb column from what we were passed.
 * 
 * @param array $default_columns
 * 
 * @return array $columns
 */
function dcmm_custom_columns( $default_columns ) {

	$dcmm_columns = array(
		'cb' => $default_columns['cb'],
		'name' => 'Name',
		'status' => __( 'Membership Status', 'dcmm' ),
		'address' => __( 'Address', 'dcmm' )
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
		case 'address':
			$address = $member->get('address');
			$street_1 = $address['street1'];
			$street_2 = $address['street2'];
			$city = $address['city'];
			$state = $address['state'];
			$zip = $address['zip'];

			$formatted_address = ( $street_1 ? $street_1 . "<br>" : '' ) . ( $street_2 ? $street_2 . "<br>": '' ) . ( $city ? $city . ", " : "" ) . ( $state ? $state . " " : "" ) . ' ' . $zip;

			echo $formatted_address;
			break;
		case 'status':
			echo $member->get( 'status');
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