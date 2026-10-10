/**
 * Autosave for toggle switches.
 *
 * A checkbox carrying `data-tmq-autosave="target:key"` saves on every flip
 * through the `tmq_update_setting` AJAX endpoint, and a badge beside the
 * switch says Saving…, Saved or Error. On error the switch goes back to where
 * it was, so what the screen shows is always what is stored.
 *
 * The switch is still a field of its form: the form's own Save keeps working
 * and writes the same value. Progressive enhancement, no dependencies.
 */
( function () {
	'use strict';

	const config = ( window.tmq && window.tmq.autosave ) || null;
	if ( ! config ) {
		return;
	}

	const BADGE = 'tmq-autosave-badge';
	const SAVED_LINGER = 1800;

	function badgeFor( input ) {
		const anchor = input.closest( '.tmq-toggle' ) || input;
		let badge = anchor.nextElementSibling;
		if ( ! badge || ! badge.classList.contains( BADGE ) ) {
			badge = document.createElement( 'span' );
			badge.className = BADGE;
			badge.setAttribute( 'aria-live', 'polite' );
			badge.hidden = true;
			anchor.after( badge );
		}
		return badge;
	}

	function show( badge, state, text ) {
		badge.classList.remove( BADGE + '--saving', BADGE + '--saved', BADGE + '--error' );
		badge.classList.add( BADGE + '--' + state );
		badge.textContent = text;
		badge.hidden = false;
	}

	function save( input ) {
		const badge = badgeFor( input );
		const wanted = input.checked;
		const body = new URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
			key: input.dataset.tmqAutosave,
			value: wanted ? '1' : '0',
		} );

		clearTimeout( input.tmqHide );
		show( badge, 'saving', config.i18n.saving );
		input.disabled = true;

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( response ) => response.json().catch( () => ( {} ) ) )
			.then( ( json ) => {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || config.i18n.error );
				}
				show( badge, 'saved', config.i18n.saved );
				input.tmqHide = setTimeout( () => {
					badge.hidden = true;
				}, SAVED_LINGER );
			} )
			.catch( ( error ) => {
				input.checked = ! wanted;
				show( badge, 'error', error.message || config.i18n.error );
			} )
			.finally( () => {
				input.disabled = false;
				input.focus();
			} );
	}

	document.addEventListener( 'change', ( event ) => {
		const input = event.target;
		if ( input instanceof HTMLInputElement && 'checkbox' === input.type && input.dataset.tmqAutosave ) {
			save( input );
		}
	} );
}() );
