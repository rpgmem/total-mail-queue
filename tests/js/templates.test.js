import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { installJquery, runScript, settled } from './helpers.js';

let $;
let pickerOptions;

const page = `
	<form class="tmq-templates-form">
		<input name="tmq_template[header_color]" class="tmq-color-picker" value="#000000">
		<input name="tmq_template[logo]" id="tmq_logo" value="">
		<input name="unrelated" value="x">
		<button class="tmq-media-pick" data-target="tmq_logo">Choose</button>
	</form>
	<iframe class="tmq-templates-preview-frame" data-action="tmq_template_preview" data-nonce="preview-nonce"></iframe>
	<span class="tmq-templates-preview-status"></span>
	<input id="tmq_test_email_to" value="me@example.test">
	<button id="tmq_send_test_email" data-nonce="test-nonce" data-action="tmq_send_test_email">Send test email</button>
	<div id="tmq_test_email_result"></div>`;

beforeAll( () => {
	$ = installJquery();
	window.tmq = {
		ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
		i18n: {
			mediaTitle: 'Logo',
			mediaButton: 'Use',
			sending: 'Sending…',
			testFailed: 'Failed: ',
			previewUpdating: 'Updating…',
			previewFailed: 'Preview unavailable',
		},
	};
	$.fn.wpColorPicker = function ( options ) {
		pickerOptions = options;
		return this;
	};
	vi.useFakeTimers();
	document.body.innerHTML = page;
	runScript( 'assets/js/tmq-templates.js' );
	// jQuery 3 defers the ready callback through setTimeout, which fake timers hold back.
	vi.runOnlyPendingTimers();
} );

afterEach( () => {
	vi.restoreAllMocks();
} );

describe( 'live preview', () => {
	it( 'hands the colour picker the refresh', () => {
		const options = pickerOptions;
		expect( typeof options.change ).toBe( 'function' );
		expect( options.clear ).toBe( options.change );
	} );

	it( 'posts only the template fields, debounced, and shows the returned email', () => {
		const post = vi.spyOn( $, 'post' ).mockReturnValue(
			settled( $, true, [ { success: true, data: { html: '<p>Preview</p>' } } ] )
		);

		$( '[name="tmq_template[header_color]"]' ).val( '#ff0000' ).trigger( 'input' );
		$( '[name="tmq_template[header_color]"]' ).trigger( 'change' );
		expect( post ).not.toHaveBeenCalled();
		vi.advanceTimersByTime( 400 );

		expect( post ).toHaveBeenCalledTimes( 1 );
		const sent = new URLSearchParams( post.mock.calls[ 0 ][ 1 ] );
		expect( sent.get( 'tmq_template[header_color]' ) ).toBe( '#ff0000' );
		expect( sent.get( 'unrelated' ) ).toBeNull();
		expect( sent.get( 'action' ) ).toBe( 'tmq_template_preview' );
		expect( sent.get( 'nonce' ) ).toBe( 'preview-nonce' );
		expect( $( '.tmq-templates-preview-frame' ).attr( 'srcdoc' ) ).toBe( '<p>Preview</p>' );
		expect( $( '.tmq-templates-preview-status' ).text() ).toBe( '' );
	} );

	it( 'says the preview failed when the server refuses or the request fails', () => {
		vi.spyOn( $, 'post' ).mockReturnValue( settled( $, true, [ { success: false } ] ) );
		$( '.tmq-templates-form' ).trigger( 'change' );
		vi.advanceTimersByTime( 400 );
		expect( $( '.tmq-templates-preview-status' ).text() ).toBe( 'Preview unavailable' );

		$.post.mockReturnValue( settled( $, false, [ {} ] ) );
		$( '.tmq-templates-preview-status' ).text( '' );
		$( '.tmq-templates-form' ).trigger( 'change' );
		vi.advanceTimersByTime( 400 );
		expect( $( '.tmq-templates-preview-status' ).text() ).toBe( 'Preview unavailable' );
	} );

	it( 'never lets a late answer overwrite a newer one', () => {
		const first = $.Deferred();
		const second = $.Deferred();
		vi.spyOn( $, 'post' ).mockReturnValueOnce( first.promise() ).mockReturnValueOnce( second.promise() );

		$( '.tmq-templates-form' ).trigger( 'change' );
		vi.advanceTimersByTime( 400 );
		$( '.tmq-templates-form' ).trigger( 'change' );
		vi.advanceTimersByTime( 400 );

		second.resolve( { success: true, data: { html: 'newer' } } );
		first.resolve( { success: true, data: { html: 'older' } } );
		first.reject();

		expect( $( '.tmq-templates-preview-frame' ).attr( 'srcdoc' ) ).toBe( 'newer' );
	} );
} );

describe( 'logo picker', () => {
	it( 'writes the chosen image URL into the field', () => {
		let onSelect;
		const frame = {
			on: ( event, callback ) => {
				onSelect = callback;
			},
			open: vi.fn( () => onSelect() ),
			state: () => ( { get: () => ( { first: () => ( { toJSON: () => ( { url: 'https://example.test/logo.png' } ) } ) } ) } ),
		};
		window.wp = { media: vi.fn( () => frame ) };
		vi.spyOn( $, 'post' ).mockReturnValue( settled( $, true, [ {} ] ) );

		$( '.tmq-media-pick' ).trigger( 'click' );

		expect( window.wp.media.mock.calls[ 0 ][ 0 ] ).toMatchObject( { title: 'Logo', library: { type: 'image' }, multiple: false } );
		expect( $( '#tmq_logo' ).val() ).toBe( 'https://example.test/logo.png' );
	} );

	it( 'does nothing when the media library is not loaded', () => {
		delete window.wp;
		$( '#tmq_logo' ).val( '' );

		$( '.tmq-media-pick' ).trigger( 'click' );

		expect( $( '#tmq_logo' ).val() ).toBe( '' );
	} );
} );

describe( 'send test email', () => {
	it( 'posts the address and shows the success message, then restores the button', () => {
		const post = vi.spyOn( $, 'post' ).mockReturnValue(
			settled( $, true, [ { success: true, data: { message: 'Sent to me@example.test' } } ] )
		);

		$( '#tmq_send_test_email' ).trigger( 'click' );

		expect( post.mock.calls[ 0 ][ 1 ] ).toEqual( { action: 'tmq_send_test_email', _nonce: 'test-nonce', to: 'me@example.test' } );
		expect( $( '#tmq_test_email_result' ).hasClass( 'notice-success' ) ).toBe( true );
		expect( $( '#tmq_test_email_result' ).text() ).toBe( 'Sent to me@example.test' );
		expect( $( '#tmq_send_test_email' ).prop( 'disabled' ) ).toBe( false );
		expect( $( '#tmq_send_test_email' ).text() ).toBe( 'Send test email' );
	} );

	it( 'shows the failure with the server message', () => {
		vi.spyOn( $, 'post' ).mockReturnValue( settled( $, true, [ { success: false, data: { message: 'No SMTP' } } ] ) );

		$( '#tmq_send_test_email' ).trigger( 'click' );

		expect( $( '#tmq_test_email_result' ).hasClass( 'notice-error' ) ).toBe( true );
		expect( $( '#tmq_test_email_result' ).text() ).toBe( 'Failed: No SMTP' );
	} );

	it( 'shows the failure when the request itself fails', () => {
		vi.spyOn( $, 'post' ).mockReturnValue(
			settled( $, false, [ { responseJSON: { data: { message: 'Forbidden' } } } ] )
		);

		$( '#tmq_send_test_email' ).trigger( 'click' );

		expect( $( '#tmq_test_email_result' ).text() ).toBe( 'Failed: Forbidden' );

		$.post.mockReturnValue( settled( $, false, [ null ] ) );
		$( '#tmq_send_test_email' ).trigger( 'click' );
		expect( $( '#tmq_test_email_result' ).text() ).toBe( 'Failed: ' );
	} );
} );
