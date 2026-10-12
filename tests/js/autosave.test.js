import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { runScript } from './helpers.js';

function toggle( key = 'settings:dark_mode', checked = true ) {
	document.body.innerHTML = `<label class="tmq-toggle"><input type="checkbox" data-tmq-autosave="${ key }"></label>`;
	const input = document.querySelector( 'input' );
	input.checked = checked;
	return input;
}

function flip( input ) {
	input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

function answer( json ) {
	return Promise.resolve( { json: () => ( json instanceof Error ? Promise.reject( json ) : Promise.resolve( json ) ) } );
}

async function settle() {
	for ( let i = 0; i < 5; i++ ) {
		await Promise.resolve();
	}
}

describe( 'without the localized config', () => {
	it( 'does nothing', () => {
		delete window.tmq;
		globalThis.fetch = vi.fn();
		runScript( 'assets/js/tmq-autosave.js' );

		flip( toggle() );

		expect( globalThis.fetch ).not.toHaveBeenCalled();
	} );
} );

describe( 'with the localized config', () => {
	beforeAll( () => {
		window.tmq = {
			autosave: {
				action: 'tmq_update_setting',
				nonce: 'autosave-nonce',
				ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
				i18n: { saving: 'Saving…', saved: 'Saved', error: 'Error' },
			},
		};
		runScript( 'assets/js/tmq-autosave.js' );
	} );

	beforeEach( () => {
		vi.useFakeTimers();
	} );

	afterEach( () => {
		vi.useRealTimers();
		document.body.innerHTML = '';
	} );

	it( 'saves the flip, shows Saving… then Saved, and hides the badge later', async () => {
		globalThis.fetch = vi.fn( () => answer( { success: true } ) );
		const input = toggle();

		flip( input );

		const badge = document.querySelector( '.tmq-autosave-badge' );
		expect( badge.textContent ).toBe( 'Saving…' );
		expect( input.disabled ).toBe( true );
		const [ url, request ] = globalThis.fetch.mock.calls[ 0 ];
		expect( url ).toBe( 'https://example.test/wp-admin/admin-ajax.php' );
		expect( Object.fromEntries( request.body ) ).toEqual( {
			action: 'tmq_update_setting',
			nonce: 'autosave-nonce',
			key: 'settings:dark_mode',
			value: '1',
		} );

		await settle();
		expect( badge.textContent ).toBe( 'Saved' );
		expect( badge.classList.contains( 'tmq-autosave-badge--saved' ) ).toBe( true );
		expect( input.disabled ).toBe( false );
		expect( input.checked ).toBe( true );

		vi.advanceTimersByTime( 1800 );
		expect( badge.hidden ).toBe( true );
	} );

	it( 'reuses the badge already beside the switch', async () => {
		globalThis.fetch = vi.fn( () => answer( { success: true } ) );
		const input = toggle( 'settings:smtp_debug', false );

		flip( input );
		await settle();
		flip( input );
		await settle();

		expect( document.querySelectorAll( '.tmq-autosave-badge' ) ).toHaveLength( 1 );
		expect( Object.fromEntries( globalThis.fetch.mock.calls[ 1 ][ 1 ].body ).value ).toBe( '0' );
	} );

	it( 'puts the switch back and shows the server message when the save is refused', async () => {
		globalThis.fetch = vi.fn( () => answer( { success: false, data: { message: 'Not allowed' } } ) );
		const input = toggle();

		flip( input );
		await settle();

		expect( input.checked ).toBe( false );
		expect( document.querySelector( '.tmq-autosave-badge' ).textContent ).toBe( 'Not allowed' );
	} );

	it( 'shows the generic error when the answer is not JSON', async () => {
		globalThis.fetch = vi.fn( () => answer( new Error( 'Unexpected token <' ) ) );
		const input = toggle();

		flip( input );
		await settle();

		expect( input.checked ).toBe( false );
		expect( document.querySelector( '.tmq-autosave-badge' ).textContent ).toBe( 'Error' );
	} );

	it( 'ignores a checkbox that is not an autosave toggle', () => {
		globalThis.fetch = vi.fn();
		document.body.innerHTML = '<input type="checkbox">';

		flip( document.querySelector( 'input' ) );

		expect( globalThis.fetch ).not.toHaveBeenCalled();
	} );
} );
