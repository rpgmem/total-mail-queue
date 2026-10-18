import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { installJquery, runScript, settled } from './helpers.js';

let $;

beforeAll( () => {
	$ = installJquery();
	window.tmq = globalThis.tmq = {
		restUrl: 'https://example.test/wp-json/',
		restNonce: 'rest-nonce',
		ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
		testSmtpNonce: 'smtp-nonce',
		i18n: {
			confirmDelete: 'Delete?',
			testing: 'Testing…',
			testConnection: 'Test Connection',
			errorLoadingMessage: 'Could not load.',
		},
	};
	vi.useFakeTimers();
	// jsdom has no form navigation; a submit nothing cancels would log noise.
	HTMLFormElement.prototype.submit = () => {};
	document.body.innerHTML = '<input class="tmq-no-autofill" readonly><input class="tmq-no-autofill" readonly disabled>';
	runScript( 'assets/js/tmq-admin.js' );
} );

afterEach( () => {
	vi.restoreAllMocks();
	document.body.innerHTML = '';
} );

describe( 'autofill guard', () => {
	it( 'unlocks the enabled fields half a second after load, and only those', () => {
		const [ enabled, disabled ] = document.querySelectorAll( '.tmq-no-autofill' );
		expect( enabled.hasAttribute( 'readonly' ) ).toBe( true );

		vi.advanceTimersByTime( 500 );

		expect( enabled.hasAttribute( 'readonly' ) ).toBe( false );
		expect( disabled.hasAttribute( 'readonly' ) ).toBe( true );
	} );
} );

describe( 'select all', () => {
	it( 'checks and unchecks every row of the list table', () => {
		document.body.innerHTML = `
			<table class="wp-list-table toplevel_page_total-mail-queue_page">
				<tr><td><input type="checkbox" class="tmq-select-all"></td></tr>
				<tr><td><input type="checkbox" name="id[]"></td></tr>
				<tr><td><input type="checkbox" name="id[]"></td></tr>
			</table>
			<input type="checkbox" name="id[]" id="elsewhere">`;
		const toggle = document.querySelector( '.tmq-select-all' );

		toggle.click();
		expect( $( 'table input[name="id[]"]:checked' ).length ).toBe( 2 );
		expect( document.getElementById( 'elsewhere' ).checked ).toBe( false );

		toggle.click();
		expect( $( 'table input[name="id[]"]:checked' ).length ).toBe( 0 );
	} );
} );

describe( 'bulk delete confirmation', () => {
	function submit( action, field = 'action' ) {
		document.body.innerHTML = `<form><select name="${ field }"><option value="${ action }" selected></option></select></form>`;
		const event = $.Event( 'submit' );
		$( 'form' ).trigger( event );
		return event;
	}

	it( 'cancels the submit when the delete is not confirmed', () => {
		const confirm = vi.spyOn( window, 'confirm' ).mockReturnValue( false );

		expect( submit( 'delete' ).isDefaultPrevented() ).toBe( true );
		expect( confirm ).toHaveBeenCalledWith( 'Delete?' );
	} );

	it( 'lets a confirmed delete through, from the bottom selector too', () => {
		vi.spyOn( window, 'confirm' ).mockReturnValue( true );

		expect( submit( 'delete', 'action2' ).isDefaultPrevented() ).toBe( false );
	} );

	it( 'asks nothing for another action', () => {
		const confirm = vi.spyOn( window, 'confirm' );

		expect( submit( 'resend' ).isDefaultPrevented() ).toBe( false );
		expect( confirm ).not.toHaveBeenCalled();
	} );
} );

describe( 'family buttons that ask first', () => {
	function click() {
		document.body.innerHTML = '<a href="#reset" class="tmq-btn tmq-btn-danger" data-tmq-confirm="Reset?">Reset</a>';
		const event = $.Event( 'click' );
		$( 'a' ).trigger( event );
		return event;
	}

	it( 'asks the button\'s own question and stops the action when cancelled', () => {
		const confirm = vi.spyOn( window, 'confirm' ).mockReturnValue( false );

		expect( click().isDefaultPrevented() ).toBe( true );
		expect( confirm ).toHaveBeenCalledWith( 'Reset?' );
	} );

	it( 'lets a confirmed action through', () => {
		vi.spyOn( window, 'confirm' ).mockReturnValue( true );

		expect( click().isDefaultPrevented() ).toBe( false );
	} );
} );

describe( 'connection lock on the SMTP form', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<input type="checkbox" id="tmq-unlock-conn">
			<div class="tmq-conn-row tmq-conn-locked">
				<input class="tmq-conn-field" id="host" disabled>
				<input class="tmq-conn-field tmq-no-autofill" id="user" disabled readonly>
			</div>`;
	} );

	it( 'unlocks the fields, the autofill-guarded ones included, and locks them again', () => {
		const lock = $( '#tmq-unlock-conn' );

		lock.prop( 'checked', true ).trigger( 'change' );
		expect( $( '#host' ).prop( 'disabled' ) ).toBe( false );
		expect( $( '#user' ).attr( 'readonly' ) ).toBeUndefined();
		expect( $( '.tmq-conn-row' ).hasClass( 'tmq-conn-locked' ) ).toBe( false );

		lock.prop( 'checked', false ).trigger( 'change' );
		expect( $( '#host' ).prop( 'disabled' ) ).toBe( true );
		expect( $( '.tmq-conn-row' ).hasClass( 'tmq-conn-locked' ) ).toBe( true );
	} );
} );

describe( 'test SMTP connection', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<input id="smtp_host" value="smtp.example.test"><input id="smtp_port" value="587">
			<select id="smtp_encryption"><option value="tls" selected></option></select>
			<input type="checkbox" id="smtp_auth" checked>
			<input id="smtp_username" value="user"><input id="smtp_password" value="">
			<button id="tmq-test-smtp" data-smtp-id="7">Test Connection</button>
			<div id="tmq-test-smtp-result"></div>`;
	} );

	it( 'posts the form values and shows a success notice, escaping the message', () => {
		const post = vi.spyOn( $, 'post' ).mockReturnValue(
			settled( $, true, [ { success: true, data: { message: '<b>Connected</b>' } } ] )
		);

		$( '#tmq-test-smtp' ).trigger( 'click' );

		expect( post.mock.calls[ 0 ][ 1 ] ).toMatchObject( {
			action: 'wp_tmq_test_smtp',
			_nonce: 'smtp-nonce',
			host: 'smtp.example.test',
			auth: 1,
			smtp_id: 7,
		} );
		const result = $( '#tmq-test-smtp-result' );
		expect( result.hasClass( 'notice-success' ) ).toBe( true );
		expect( result.find( 'b' ).length ).toBe( 0 );
		expect( result.text() ).toBe( '<b>Connected</b>' );
		expect( $( '#tmq-test-smtp' ).prop( 'disabled' ) ).toBe( false );
		expect( $( '#tmq-test-smtp' ).text() ).toBe( 'Test Connection' );
	} );

	it( 'shows an error notice when the server answers with a failure', () => {
		vi.spyOn( $, 'post' ).mockReturnValue( settled( $, true, [ { success: false } ] ) );

		$( '#tmq-test-smtp' ).trigger( 'click' );

		expect( $( '#tmq-test-smtp-result' ).hasClass( 'notice-error' ) ).toBe( true );
		expect( $( '#tmq-test-smtp-result' ).text() ).toBe( 'Could not load.' );
	} );

	it( 'shows the server message when the request itself fails', () => {
		vi.spyOn( $, 'post' ).mockReturnValue(
			settled( $, false, [ { responseJSON: { data: { message: 'Forbidden' } } } ] )
		);

		$( '#tmq-test-smtp' ).trigger( 'click' );

		expect( $( '#tmq-test-smtp-result' ).text() ).toBe( 'Forbidden' );
		expect( $( '#tmq-test-smtp' ).prop( 'disabled' ) ).toBe( false );
	} );

	it( 'falls back to the generic message when a failed request says nothing', () => {
		vi.spyOn( $, 'post' ).mockReturnValue( settled( $, false, [ {} ] ) );

		$( '#tmq-test-smtp' ).trigger( 'click' );

		expect( $( '#tmq-test-smtp-result' ).text() ).toBe( 'Could not load.' );
	} );
} );

describe( 'message preview in the log', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<details><summary><button data-tmq-list-message-toggle="42">Show</button></summary>
			<div data-tmq-list-message-content></div></details>`;
	} );

	it( 'loads the message once through the REST route', () => {
		const get = vi.spyOn( $, 'get' ).mockReturnValue(
			settled( $, true, [ { status: 'ok', data: { html: '<p>Body</p>' } }, 'success' ] )
		);
		const button = $( '[data-tmq-list-message-toggle]' );

		button.trigger( 'click' );

		expect( get ).toHaveBeenCalledWith( 'https://example.test/wp-json/tmq/v1/message/42', { _wpnonce: 'rest-nonce' } );
		expect( $( '[data-tmq-list-message-content]' ).html() ).toBe( '<p>Body</p>' );
		expect( button.attr( 'data-tmq-list-message-toggle' ) ).toBeUndefined();
	} );

	it( 'alerts when the message cannot be loaded', () => {
		vi.spyOn( $, 'get' ).mockReturnValue(
			settled( $, false, [ { responseJSON: { code: 'rest_forbidden' } }, 'error' ] )
		);
		const alert = vi.spyOn( window, 'alert' ).mockImplementation( () => {} );
		const log = vi.spyOn( console, 'log' ).mockImplementation( () => {} );

		$( '[data-tmq-list-message-toggle]' ).trigger( 'click' );

		expect( alert ).toHaveBeenCalledWith( 'Could not load.' );
		expect( log ).toHaveBeenCalledWith( { code: 'rest_forbidden' } );
		expect( $( '[data-tmq-list-message-content]' ).html() ).toBe( '' );
	} );
} );

describe( 'cron tab', () => {
	const lines = { 'wpcli:5': 'cli five', 'wpcli:1': 'cli one', 'curl:1': 'curl one' };

	beforeEach( () => {
		document.body.innerHTML = `
			<select id="tmq-cron-frequency" data-tmq-cron-part="frequency"><option value="1"></option><option value="5" selected></option></select>
			<select id="tmq-cron-method" data-tmq-cron-part="method"><option value="wpcli" selected></option><option value="curl"></option><option value="wget"></option></select>
			<input id="tmq-cron-line" value="cli five" data-tmq-cron-lines='${ JSON.stringify( lines ) }'>
			<button data-tmq-copy="#tmq-cron-line" data-tmq-copied="Copied">Copy</button>`;
	} );

	it( 'switches the line to the chosen frequency and method, and keeps it for a missing pair', () => {
		$( '#tmq-cron-frequency' ).val( '1' ).trigger( 'change' );
		expect( $( '#tmq-cron-line' ).val() ).toBe( 'cli one' );

		$( '#tmq-cron-method' ).val( 'curl' ).trigger( 'change' );
		expect( $( '#tmq-cron-line' ).val() ).toBe( 'curl one' );

		$( '#tmq-cron-method' ).val( 'wget' ).trigger( 'change' );
		expect( $( '#tmq-cron-line' ).val() ).toBe( 'curl one' );
	} );

	it( 'copies the line and says so on the button for a moment', async () => {
		const writeText = vi.fn( () => Promise.resolve() );
		vi.stubGlobal( 'navigator', { clipboard: { writeText } } );
		vi.stubGlobal( 'isSecureContext', true );

		$( '[data-tmq-copy]' ).trigger( 'click' );
		await Promise.resolve();
		await Promise.resolve();

		expect( writeText ).toHaveBeenCalledWith( 'cli five' );
		expect( $( '[data-tmq-copy]' ).text() ).toBe( 'Copied' );
		vi.advanceTimersByTime( 1500 );
		expect( $( '[data-tmq-copy]' ).text() ).toBe( 'Copy' );
		vi.unstubAllGlobals();
	} );

	it( 'selects the text when the clipboard is unavailable or refuses', async () => {
		vi.stubGlobal( 'isSecureContext', false );
		$( '[data-tmq-copy]' ).trigger( 'click' );
		const field = document.getElementById( 'tmq-cron-line' );
		expect( document.activeElement ).toBe( field );
		expect( field.selectionEnd - field.selectionStart ).toBe( field.value.length );

		field.blur();
		vi.stubGlobal( 'isSecureContext', true );
		vi.stubGlobal( 'navigator', { clipboard: { writeText: () => Promise.reject( new Error( 'denied' ) ) } } );
		$( '[data-tmq-copy]' ).trigger( 'click' );
		await Promise.resolve();
		await Promise.resolve();
		expect( document.activeElement ).toBe( field );
		expect( $( '[data-tmq-copy]' ).text() ).toBe( 'Copy' );
		vi.unstubAllGlobals();
	} );

	it( 'does nothing when the target field is missing', () => {
		document.body.innerHTML = '<button data-tmq-copy="#nowhere">Copy</button>';
		expect( () => $( '[data-tmq-copy]' ).trigger( 'click' ) ).not.toThrow();
	} );
} );
