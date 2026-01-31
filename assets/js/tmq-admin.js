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
