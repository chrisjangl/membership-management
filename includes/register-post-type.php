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

namespace DC_Member_Post_Type;

function get_post_type() {
	return 'dc-member';
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
	
	// register our post type
	\add_action( 'init', '\DC_Member_Post_Type\dcm_register_post_type' );

}

/**
 * Register our post type
 *
 * @return void
 */
function dcm_register_post_type() {

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
 * add meta box to the edit Member screen, associate an exam with that course
 * 
 * TODO: link to the metabox class
 * @return void
 */
function add_member_meta_boxes() {

	require_once( 'class-member-metaboxes.php' );
	new \Member_metaboxes();

}

construct_member_post_type();