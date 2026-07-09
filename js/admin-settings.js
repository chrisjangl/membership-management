/* global jQuery, tinymce, ajaxurl, dcmmSettings */
(function ($) {
    'use strict';

    // --- Tab switching ---
    function initTabs() {
        var $nav    = $('.dcmm-settings-nav');
        var $panels = $('.dcmm-tab-panel');

        if ( ! $nav.length ) {
            return;
        }

        $nav.find('.nav-tab').on('click', function (e) {
            e.preventDefault();

            var tabKey = $( this ).data('tab');

            $nav.find('.nav-tab')
                .removeClass('nav-tab-active')
                .attr('aria-selected', 'false');
            $( this )
                .addClass('nav-tab-active')
                .attr('aria-selected', 'true');

            $panels.hide().attr('aria-hidden', 'true');
            $( '#dcmm-tab-' + tabKey ).show().attr('aria-hidden', 'false');

            // Update the URL bar without a page reload so the active tab
            // is preserved on a manual refresh and in the options.php redirect.
            if ( window.history && window.history.replaceState ) {
                var url = new URL( window.location.href );
                url.searchParams.set('tab', tabKey);
                window.history.replaceState(null, '', url.toString());
            }
        });
    }

    // --- Sync TinyMCE editors back to their textareas before form submit ---
    function initFormSave() {
        $( '#dcmm-settings-form' ).on('submit', function () {
            if ( typeof tinymce !== 'undefined' ) {
                tinymce.triggerSave();
            }
        });
    }

    // --- General tab: show/hide dues amount based on "Enable Dues" checkbox ---
    function toggleDuesAmountField() {
        var $row = $( '#dcmm_dues_amount' ).closest('tr');
        $row.toggle( $( '#dcmm_enable_dues' ).is(':checked') );
    }

    // --- General tab: show/hide anchor date based on join policy ---
    function toggleAnchorDateField() {
        var $row = $( '#dcmm_anchor_date' ).closest('tr');
        $row.toggle( $( '#dcmm_join_policy' ).val() === 'anchored_full_term' );
    }

    // --- General tab: update anchor date placeholder/description ---
    function updateAnchorDateField() {
        var termLength   = $( '#dcmm_membership_term_length' ).val();
        var $input       = $( '#dcmm_anchor_date' );
        var $description = $( '#dcmm-anchor-date-description' );

        if ( termLength === 'monthly' ) {
            $input.attr('placeholder', '15');
            $description.text( dcmmSettings.anchorDateMonthly );
        } else {
            $input.attr('placeholder', '08-15');
            $description.text( dcmmSettings.anchorDateYearly );
        }
    }

    function initGeneralTabBehavior() {
        toggleDuesAmountField();
        toggleAnchorDateField();
        updateAnchorDateField();

        $( '#dcmm_enable_dues' ).on('change', toggleDuesAmountField);
        $( '#dcmm_join_policy' ).on('change', toggleAnchorDateField);
        $( '#dcmm_membership_term_length' ).on('change', updateAnchorDateField);
    }

    // --- Email merge tag insertion ---
    function initMergeTags() {
        $( document ).on('click', '.dcmm-merge-tag, .dcmm-expiration-merge-tag', function (e) {
            e.preventDefault();
            var tag = $( this ).data('tag');

            if ( typeof tinymce !== 'undefined' ) {
                var active = tinymce.activeEditor;
                if ( active && ! active.isHidden() ) {
                    active.execCommand('mceInsertContent', false, tag);
                    return;
                }
            }

            var $textarea = $( '.dcmm-email-template-editor textarea:focus, .dcmm-email-template-editor textarea' ).last();
            if ( $textarea.length ) {
                var el    = $textarea[0];
                var start = el.selectionStart;
                var end   = el.selectionEnd;
                el.value  = el.value.substring(0, start) + tag + el.value.substring(end);
                el.selectionStart = el.selectionEnd = start + tag.length;
                el.focus();
            }
        });
    }

    // --- Email template preview ---
    function initEmailPreviews() {
        $( document ).on( 'click', '.dcmm-preview-email', function ( e ) {
            e.preventDefault();

            var $btn      = $( this );
            var emailType = $btn.data( 'email-type' );
            var editorId  = $btn.data( 'editor-id' );

            // Grab current editor content (may be unsaved).
            var template = '';
            if ( typeof tinymce !== 'undefined' ) {
                var editor = tinymce.get( editorId );
                if ( editor && ! editor.isHidden() ) {
                    template = editor.getContent();
                }
            }
            if ( ! template ) {
                template = $( '#' + editorId ).val() || '';
            }

            $btn.prop( 'disabled', true ).text( dcmmSettings.previewLoading );

            $.post( ajaxurl, {
                action:     'dcmm_preview_email',
                email_type: emailType,
                template:   template,
                nonce:      dcmmSettings.previewNonce,
            } ).done( function ( response ) {
                if ( response.success ) {
                    var win = window.open( '', '_blank', 'width=720,height=640,scrollbars=yes,resizable=yes' );
                    if ( ! win ) {
                        alert( 'Please allow pop-ups for this site to preview emails.' );
                        return;
                    }
                    win.document.write(
                        '<!DOCTYPE html><html><head>' +
                        '<meta charset="UTF-8">' +
                        '<title>' + response.data.subject + '</title>' +
                        '<style>' +
                        'body{margin:0;padding:0;background:#f0f0f1;font-family:Arial,sans-serif;}' +
                        '.dcmm-preview-bar{background:#1d2327;color:#f0f0f1;padding:12px 20px;}' +
                        '.dcmm-preview-bar .subject{font-size:14px;font-weight:600;margin-bottom:3px;}' +
                        '.dcmm-preview-bar .note{font-size:11px;color:#8c8f94;}' +
                        '.dcmm-preview-body{max-width:680px;margin:24px auto;background:#fff;}' +
                        '</style>' +
                        '</head><body>' +
                        '<div class="dcmm-preview-bar">' +
                        '<div class="subject">Subject: ' + response.data.subject + '</div>' +
                        '<div class="note">Preview with sample data &mdash; merge tags have been replaced with placeholder values.</div>' +
                        '</div>' +
                        '<div class="dcmm-preview-body">' + response.data.html + '</div>' +
                        '</body></html>'
                    );
                    win.document.close();
                } else {
                    alert( dcmmSettings.previewError );
                }
            } ).fail( function () {
                alert( dcmmSettings.previewError );
            } ).always( function () {
                $btn.prop( 'disabled', false ).text( dcmmSettings.previewLabel );
            } );
        } );
    }

    // --- MailChimp AJAX ---
    function initMailChimp() {
        if ( ! $( '#dcmm-mailchimp-api-form' ).length ) {
            return;
        }

        $( '#dcmm-mailchimp-api-form' ).on('submit', function (e) {
            e.preventDefault();

            var $status = $( '#dcmm-connection-status' );

            $.post(ajaxurl, {
                action:  'dcmm_save_mailchimp_api_key',
                api_key: $( '#dcmm_mailchimp_api_key' ).val(),
                nonce:   $( '#dcmm_mailchimp_nonce' ).val(),
            }).done(function (response) {
                if ( response.success ) {
                    $status.html( '<span class="success">&#10003; ' + response.data.message + '</span>' );
                } else {
                    $status.html( '<span class="error">&#10007; ' + response.data.message + '</span>' );
                }
            }).fail(function () {
                $status.html( '<span class="error">&#10007; Failed to save API key</span>' );
            });
        });

        $( '#dcmm-test-connection' ).on('click', function () {
            var apiKey  = $( '#dcmm_mailchimp_api_key' ).val();
            var $btn    = $( this );
            var $status = $( '#dcmm-connection-status' );

            if ( ! apiKey ) {
                $status.html( '<span class="error">&#10007; Please enter an API key first</span>' );
                return;
            }

            $status.html( '<span class="testing">Testing connection&hellip;</span>' );
            $btn.prop('disabled', true);

            $.post(ajaxurl, {
                action:  'dcmm_test_mailchimp_connection',
                api_key: apiKey,
                nonce:   $( '#dcmm_mailchimp_nonce' ).val(),
            }).done(function (response) {
                if ( response.success ) {
                    $status.html( '<span class="success">&#10003; ' + response.data.message + '</span>' );
                } else {
                    $status.html( '<span class="error">&#10007; ' + response.data.message + '</span>' );
                }
            }).fail(function () {
                $status.html( '<span class="error">&#10007; Connection test failed</span>' );
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    $( document ).ready(function () {
        initTabs();
        initFormSave();
        initGeneralTabBehavior();
        initMergeTags();
        initEmailPreviews();
        initMailChimp();
    });

}(jQuery));
