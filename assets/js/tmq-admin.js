( function ( $, tmq ) {
	"use strict";

	const $doc = $( document );
	const { restUrl, restNonce, i18n } = tmq;

	// select/deselect all table rows
	$doc.on( "click", '.tmq-select-all', function () {
		const checked = this.checked;
		$( 'table[class*="total-mail-queue_page"] input[name="id[]"],.tmq-select-all' ).each( function () {
			this.checked = checked;
		});
	});

	// confirm before bulk delete
	$doc.on( "submit", 'form', function ( e ) {
		const action = $( 'select[name="action"]', this ).val() || $( 'select[name="action2"]', this ).val();
		if ( action === "delete" ) {
			if ( ! confirm( i18n.confirmDelete ) ) {
				e.preventDefault();
			}
		}
	});

	// test SMTP connection
	$doc.on( "click", "#tmq-test-smtp", function () {
		const $btn = $( this );
		const $result = $( "#tmq-test-smtp-result" );

		$btn.prop( "disabled", true ).text( i18n.testing );
		$result.removeAttr( "class" ).hide();

		$.post( tmq.ajaxUrl, {
			action:     "wp_tmq_test_smtp",
			_nonce:     tmq.testSmtpNonce,
			host:       $( "#smtp_host" ).val(),
			port:       $( "#smtp_port" ).val(),
			encryption: $( "#smtp_encryption" ).val(),
			auth:       $( "#smtp_auth" ).is( ":checked" ) ? 1 : 0,
			username:   $( "#smtp_username" ).val(),
			password:   $( "#smtp_password" ).val(),
			smtp_id:    $btn.data( "smtp-id" ) || 0
		}).done( function ( response ) {
			const ok = response.success;
			const msg = response.data && response.data.message ? response.data.message : i18n.errorLoadingMessage;
			$result
				.addClass( ok ? "notice notice-success" : "notice notice-error" )
				.html( "<p>" + $( "<span>" ).text( msg ).html() + "</p>" )
				.show();
			$btn.prop( "disabled", false ).text( i18n.testConnection );
		}).fail( function ( jqXHR ) {
			const data = jqXHR.responseJSON;
			const msg = data && data.data && data.data.message ? data.data.message : i18n.errorLoadingMessage;
			$result
				.addClass( "notice notice-error" )
				.html( "<p>" + $( "<span>" ).text( msg ).html() + "</p>" )
				.show();
			$btn.prop( "disabled", false ).text( i18n.testConnection );
		});
	});

	// dynamically load message when opening a details element for the first time
	$doc.on( "click", '[data-tmq-list-message-toggle]',function () {
		const $btn = $( this );
		const id = $btn.attr( "data-tmq-list-message-toggle" );
		$btn.attr( "data-tmq-list-message-toggle", null );
		$.get( `${restUrl}tmq/v1/message/${id}`, { _wpnonce: restNonce } ).always( function ( response, status ) {
			if ( status === "success" && response.status === "ok" ) {
				$( '[data-tmq-list-message-content]', $btn.closest( 'details' ) ).html( response.data.html );
			} else {
				const responseData = response.responseJSON || response.data;
				console.log( responseData );
				alert( i18n.errorLoadingMessage );
			}
		});
	});

}) ( jQuery, tmq );
