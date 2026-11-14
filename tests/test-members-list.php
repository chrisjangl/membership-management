<?php
/**
 * Test script for the Members list screen (issue #27)
 *
 * Covers:
 * 1. Quick Edit saves first name, last name, email and membership status
 * 2. Quick Edit rejects invalid emails and statuses without overwriting data
 * 3. Quick Edit "Create a WP User account" links a new WP user
 * 4. The WP User filter ("Has WP User" / "No WP User") splits members correctly
 *    and combines with the Membership Status filter
 *
 * Creates throwaway members/users and deletes them at the end.
 * Run through WordPress, e.g.: wp eval-file tests/test-members-list.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "This script must be run in WordPress context.";
	exit;
}

require_once DCMM_PATH . 'includes/class-member.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$dcmm_results = array( 'pass' => 0, 'fail' => 0 );

function dcmm_test_assert( $label, $condition, &$results ) {
	if ( $condition ) {
		$results['pass']++;
		echo "PASS: {$label}\n";
	} else {
		$results['fail']++;
		echo "FAIL: {$label}\n";
	}
}

// Quick Edit saves need an admin and an inline-save AJAX request
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );
add_filter( 'wp_doing_ajax', '__return_true' );

function dcmm_test_quick_edit( $post_id, $fields ) {
	$_POST = array_merge(
		array(
			'action'                => 'inline-save',
			'post_ID'               => $post_id,
			'dcmm_quick_edit_nonce' => wp_create_nonce( 'dcmm_quick_edit' ),
		),
		$fields
	);
	\DCMM_Post_Type\dcmm_save_quick_edit( $post_id, get_post( $post_id ) );
	$_POST = array();

	return new \DCMM_Member( $post_id );
}

$unique   = 'dcmmtest' . wp_rand( 100000, 999999 );
$post_id  = wp_insert_post( array( 'post_type' => 'dcmm-member', 'post_status' => 'publish', 'post_title' => 'Test, Quick Edit' ) );
$other_id = wp_insert_post( array( 'post_type' => 'dcmm-member', 'post_status' => 'publish', 'post_title' => 'Test, Filter' ) );
$created_user_id = 0;

update_post_meta( $post_id, 'dcmm_status', 'inactive' );
update_post_meta( $other_id, 'dcmm_status', 'inactive' );

echo "== Quick Edit: saving fields ==\n";

$member = dcmm_test_quick_edit( $post_id, array(
	'dcmm_qe_first_name' => 'Quinn',
	'dcmm_qe_last_name'  => 'Edit',
	'dcmm_qe_email'      => "{$unique}@example.com",
	'dcmm_qe_status'     => 'active',
) );

dcmm_test_assert( 'first name saved', 'Quinn' === $member->get( 'first_name' ), $dcmm_results );
dcmm_test_assert( 'last name saved', 'Edit' === $member->get( 'last_name' ), $dcmm_results );
dcmm_test_assert( 'email saved', "{$unique}@example.com" === $member->get( 'email' ), $dcmm_results );
dcmm_test_assert( 'status saved', 'active' === $member->get( 'status' ), $dcmm_results );
dcmm_test_assert( 'no WP user created without the checkbox', ! $member->has_wp_user(), $dcmm_results );

echo "== Quick Edit: invalid input ==\n";

$member = dcmm_test_quick_edit( $post_id, array(
	'dcmm_qe_email'  => 'not-an-email',
	'dcmm_qe_status' => 'bogus',
) );

dcmm_test_assert( 'invalid email ignored', "{$unique}@example.com" === $member->get( 'email' ), $dcmm_results );
dcmm_test_assert( 'invalid status ignored', 'active' === $member->get( 'status' ), $dcmm_results );

$_POST_backup = array( 'dcmm_qe_first_name' => 'Nope' );
$_POST = array_merge( array( 'action' => 'inline-save', 'dcmm_quick_edit_nonce' => 'bad' ), $_POST_backup );
\DCMM_Post_Type\dcmm_save_quick_edit( $post_id, get_post( $post_id ) );
$_POST = array();
$member = new \DCMM_Member( $post_id );
dcmm_test_assert( 'bad nonce ignored', 'Quinn' === $member->get( 'first_name' ), $dcmm_results );

echo "== Quick Edit: create WP user ==\n";

$member = dcmm_test_quick_edit( $post_id, array( 'dcmm_qe_create_wp_user' => '1' ) );
$created_user_id = (int) $member->get_wp_user_id();
$created_user    = $created_user_id ? get_userdata( $created_user_id ) : false;

dcmm_test_assert( 'WP user linked to member', $member->has_wp_user(), $dcmm_results );
dcmm_test_assert( 'WP user uses member email', $created_user && "{$unique}@example.com" === $created_user->user_email, $dcmm_results );

echo "== WP User filter ==\n";

function dcmm_test_filtered_ids( $get ) {
	global $pagenow, $wp_the_query;

	$saved_get     = $_GET;
	$saved_pagenow = $pagenow;
	$_GET          = $get;
	$pagenow       = 'edit.php';

	// the filters only touch the admin main query
	set_current_screen( 'edit-dcmm-member' );
	$query        = new WP_Query();
	$wp_the_query = $query;
	$ids          = $query->query( array(
		'post_type'      => 'dcmm-member',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$_GET    = $saved_get;
	$pagenow = $saved_pagenow;

	return array_map( 'intval', $ids );
}

$all  = dcmm_test_filtered_ids( array() );
$has  = dcmm_test_filtered_ids( array( 'dcmm_wp_user' => 'has' ) );
$none = dcmm_test_filtered_ids( array( 'dcmm_wp_user' => 'none' ) );

dcmm_test_assert( '"Has WP User" includes the linked member', in_array( $post_id, $has, true ), $dcmm_results );
dcmm_test_assert( '"No WP User" includes the unlinked member', in_array( $other_id, $none, true ), $dcmm_results );
dcmm_test_assert( '"Has" and "No" don\'t overlap', ! array_intersect( $has, $none ), $dcmm_results );
dcmm_test_assert( '"Has" + "No" covers all members', count( $has ) + count( $none ) === count( $all ), $dcmm_results );

$active_none = dcmm_test_filtered_ids( array( 'dcmm_wp_user' => 'none', 'dcmm_status' => 'active' ) );
dcmm_test_assert( 'combines with the status filter', ! in_array( $other_id, $active_none, true ), $dcmm_results );

$active_manual = dcmm_test_filtered_ids( array( 'dcmm_status' => 'inactive', 'dcmm_renewal_method' => 'manual' ) );
dcmm_test_assert( 'renewal method filter keeps the status filter', ! in_array( $post_id, $active_manual, true ) && ! in_array( $other_id, $active_manual, true ), $dcmm_results );

// cleanup
wp_delete_post( $post_id, true );
wp_delete_post( $other_id, true );
if ( $created_user_id ) {
	wp_delete_user( $created_user_id );
}

echo "\n{$dcmm_results['pass']} passed, {$dcmm_results['fail']} failed\n";
