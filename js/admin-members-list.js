/**
 * Members list screen: pre-fill our Quick Edit fields.
 *
 * Values come from the hidden per-row inline data output by
 * DCMM_Post_Type\dcmm_add_quick_edit_inline_data().
 */
( function ( $ ) {
	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}

	var coreEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( id ) {
		coreEdit.apply( this, arguments );

		var postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : id;
		if ( ! postId ) {
			return;
		}

		var $data = $( '#inline_' + postId );
		var $row = $( '#edit-' + postId );

		var field = function ( name ) {
			return $data.find( '.' + name ).text();
		};

		$row.find( 'input[name="dcmm_qe_first_name"]' ).val( field( 'dcmm_first_name' ) );
		$row.find( 'input[name="dcmm_qe_last_name"]' ).val( field( 'dcmm_last_name' ) );
		$row.find( 'input[name="dcmm_qe_email"]' ).val( field( 'dcmm_email' ) );
		$row.find( 'select[name="dcmm_qe_status"]' ).val( field( 'dcmm_status' ) );

		var hasWpUser = field( 'dcmm_has_wp_user' ) === '1';

		$row.find( '.dcmm-qe-wp-user-linked' ).toggle( hasWpUser );
		$row.find( '.dcmm-qe-wp-user-login' ).text( field( 'dcmm_wp_user_login' ) );
		$row.find( '.dcmm-qe-wp-user-create' ).toggle( ! hasWpUser );
		$row.find( 'input[name="dcmm_qe_create_wp_user"]' ).prop( 'checked', false );
	};
} )( jQuery );
