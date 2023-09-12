// jquery ready function
jQuery( document ).ready( function() {

    jQuery( '#update-own-info' ).submit( function( event ) {
        // stop the form from submitting normally
        event.preventDefault();

        // get the form data
        var userId = jQuery( 'input[name="user_id"]' ).val(),
            firstName = jQuery( '#dc_member_first_name' ).val(),
            lastName = jQuery( '#dc_member_last_name' ).val(),
            email = jQuery( '#dc_member_email' ).val(),
            phone = jQuery( '#dc_member_phone' ).val(),
            street1 = jQuery( '#dc_member_mailing_address_street1' ).val(),
            street2 = jQuery( '#dc_member_mailing_address_street2' ).val(),
            city = jQuery( '#dc_member_mailing_address_city' ).val(),
            state = jQuery( '#dc_member_mailing_address_state' ).val(),
            zip = jQuery( '#dc_member_mailing_address_zip' ).val();

        // get the nonce
        var dcmm_member_update_nonce = jQuery( '#dcmm_member_update_nonce' ).val();

        // send the data via ajax
        jQuery.ajax({
            type: 'POST',
            url: dc_membership.ajax_url,
            data: {
                action: 'dcms_update_own_info',
                user_id: userId,
                first_name: firstName,
                last_name: lastName,
                email: email,
                phone: phone,
                street1: street1,
                street2: street2,
                city: city,
                state: state,
                zip: zip,
                dcmm_member_update_nonce: dcmm_member_update_nonce,
            },
            // while the ajax is running, display a loading message & disable the submit button
            beforeSend: function() {
                // delete any previous messages
                jQuery( '#update-own-info p' ).remove();
                jQuery( '#update-own-info' ).append( '<p class="loading">Loading...</p>' );
                jQuery( '#update-own-info input[type="submit"]' ).attr( 'disabled', 'disabled' );
            },
            // when the ajax is complete, remove the loading message & re-enable the submit button
            complete: function() {
                jQuery( '#update-own-info p.loading' ).remove();
                jQuery( '#update-own-info input[type="submit"]' ).removeAttr( 'disabled' );
            },
            // if the ajax is successful, display a success message
            success: function( response ) {
                // if the response is successful, display a success message
                if ( response.success ) {
                    jQuery( '#update-own-info' ).append( '<p class="success">'+response.data +'</p>' );
                } else {
                    // if not, display an error message
                    jQuery( '#update-own-info' ).append( '<p class="error">There was an error updating your information. Please try again.</p>' );
                }
            },
            // if the ajax fails, display an error message in a way where if there are multiple errors, only the latest will be displayed
            error: function( jqXHR, textStatus, errorThrown ) {
                // remove any previous error messages
                jQuery( '#update-own-info p.error' ).remove();
                // display the error
                jQuery( '#update-own-info' ).append( '<p class="error">There was an error updating your information. Please try again.<br>Here\'s what we got from the server: <b>' + errorThrown +'<b></p>' );
            }
        });
    });
});