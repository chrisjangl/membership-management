/**
 * Edit Member screen: refresh the MailChimp metabox over AJAX.
 *
 * Refreshes automatically when the cached status is missing or stale, and
 * whenever the Refresh button is clicked. See
 * DCMM\Premium\Integrations\MailChimp_Sync_Status.
 */
( function ( $ ) {
	$( function () {
		var $box = $( '.dcmm-mailchimp-status' );
		if ( ! $box.length || typeof dcmmMailChimpStatus === 'undefined' ) {
			return;
		}

		var $button = $( '.dcmm-mailchimp-refresh' );
		var $spinner = $button.siblings( '.spinner' );

		function refresh() {
			$button.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$box.find( '.dcmm-mailchimp-error' ).remove();

			$.post( dcmmMailChimpStatus.ajaxUrl, {
				action: dcmmMailChimpStatus.action,
				nonce: dcmmMailChimpStatus.nonce,
				post_id: $box.data( 'post-id' )
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						$box.html( response.data.html );
					} else {
						showError( response && response.data && response.data.message );
					}
				} )
				.fail( function ( xhr ) {
					var json = xhr.responseJSON;
					showError( json && json.data && json.data.message );
				} )
				.always( function () {
					$button.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				} );
		}

		function showError( message ) {
			$( '<p class="dcmm-mailchimp-error notice-error" />' )
				.text( message || dcmmMailChimpStatus.error )
				.appendTo( $box );
		}

		$button.on( 'click', function ( e ) {
			e.preventDefault();
			refresh();
		} );

		if ( parseInt( $box.data( 'stale' ), 10 ) === 1 ) {
			refresh();
		}
	} );
} )( jQuery );
