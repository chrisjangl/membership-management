<?php
/**
 * Handles manual membership renewals and cancellations.
 * 
 * This file contains functions to handle manual renewals and cancellations of memberships.
 * 
 * @package DC Membership
 * @since 1.1.0
 */

add_action( 'admin_post_dcmm_manual_renew', 'dcmm_handle_manual_renew' );
add_action( 'admin_post_dcmm_manual_cancel', 'dcmm_handle_manual_cancel' );

/**
 * Handles the manual renewal of a membership.
 *
 * This function checks if the user has the capability to edit posts, verifies the nonce,
 * and then renews the membership for the specified member ID.
 *
 * @return void
 */
function dcmm_handle_manual_renew() {
	if (
		! current_user_can( 'edit_posts' ) ||
		! isset( $_GET['member_id'] ) ||
		! wp_verify_nonce( $_GET['_wpnonce'], 'dcmm_manual_renew_' . $_GET['member_id'] )
	) {
		wp_die( 'Unauthorized' );
	}

	$member = new DCMM_Member( (int) $_GET['member_id'] );
	$member->renew_membership( 'manual' );

	wp_redirect( get_edit_post_link( $member->get_member_id(), 'url' ) . '&dcmm_msg=renewed' );
	exit;
}

/**
 * Handles the manual cancellation of a membership.
 *
 * This function checks if the user has the capability to edit posts, verifies the nonce,
 * and then cancels the membership for the specified member ID.
 *
 * @return void
 */
function dcmm_handle_manual_cancel() {
	if (
		! current_user_can( 'edit_posts' ) ||
		! isset( $_GET['member_id'] ) ||
		! wp_verify_nonce( $_GET['_wpnonce'], 'dcmm_manual_cancel_' . $_GET['member_id'] )
	) {
		wp_die( 'Unauthorized' );
	}

	$member = new DCMM_Member( (int) $_GET['member_id'] );
	$member->save( 'status', 'cancelled' );
	$member->save( 'end_date', current_time( 'Y-m-d' ) );

	wp_redirect( get_edit_post_link( $member->get_member_id(), 'url' ) . '&dcmm_msg=cancelled' );
	exit;
}

/**
 * Displays admin notices for feedback on membership actions.
 *
 * This function checks the current page and post type, and displays appropriate notices
 * based on the action performed (renewal or cancellation).
 *
 * @return void
 */
function dcmm_admin_feedback_notices() {
	global $pagenow;
	if ( $pagenow !== 'post.php' || get_post_type() !== 'dcmm-member' ) return;

	if ( isset( $_GET['dcmm_msg'] ) ) {
		switch ( $_GET['dcmm_msg'] ) {
			case 'renewed':
				echo '<div class="notice notice-success is-dismissible"><p>Membership renewed.</p></div>';
				break;
			case 'cancelled':
				echo '<div class="notice notice-warning is-dismissible"><p>Membership cancelled.</p></div>';
				break;
		}
	}
}
add_action( 'admin_notices', 'dcmm_admin_feedback_notices' );
