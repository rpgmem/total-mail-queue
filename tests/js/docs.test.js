import { beforeAll, describe, expect, it } from 'vitest';
import { runScript } from './helpers.js';

const page = `
	<nav id="tmq-docs-index">
		<input id="tmq-docs-search">
		<div class="tmq-docs-index__group"><details><summary>Start</summary><ul>
			<li><a data-topic="topic-queue">Queue</a></li>
			<li><a data-topic="topic-smtp">SMTP</a></li>
		</ul></details></div>
		<div class="tmq-docs-index__group"><details><summary>Data</summary><ul>
			<li><a data-topic="topic-export">Export</a></li>
		</ul></details></div>
		<p class="tmq-docs-search-empty" hidden>Nothing found.</p>
	</nav>
	<section class="tmq-docs-topic" id="topic-queue">How the queue holds email.</section>
	<section class="tmq-docs-topic" id="topic-smtp">SMTP accounts and limits.</section>
	<section class="tmq-docs-topic" id="topic-export">Export the settings to XML.</section>`;

function search( query ) {
	const input = document.getElementById( 'tmq-docs-search' );
	input.value = query;
	input.dispatchEvent( new Event( 'input' ) );
}

function hidden( id ) {
	return document.getElementById( id ).hidden;
}

describe( 'without the navigation', () => {
	it( 'leaves the page alone', () => {
		document.body.innerHTML = '<section class="tmq-docs-topic" id="x">Text</section>';
		runScript( 'assets/js/tmq-docs.js' );

		expect( hidden( 'x' ) ).toBe( false );
	} );
} );

describe( 'search', () => {
	beforeAll( () => {
		document.body.innerHTML = page;
		runScript( 'assets/js/tmq-docs.js' );
	} );

	it( 'publishes the navigation height on load', () => {
		expect( document.documentElement.style.getPropertyValue( '--tmq-docs-nav-h' ) ).toBe( '0px' );
	} );

	it( 'hides the topics, links and groups that do not match, and opens the group that does', () => {
		search( 'SMTP' );

		expect( hidden( 'topic-smtp' ) ).toBe( false );
		expect( hidden( 'topic-queue' ) ).toBe( true );
		expect( hidden( 'topic-export' ) ).toBe( true );
		expect( document.querySelector( '[data-topic="topic-queue"]' ).parentNode.hidden ).toBe( true );
		const [ start, data ] = document.querySelectorAll( '.tmq-docs-index__group' );
		expect( start.hidden ).toBe( false );
		expect( start.querySelector( 'details' ).open ).toBe( true );
		expect( data.hidden ).toBe( true );
		expect( document.querySelector( '.tmq-docs-search-empty' ).hidden ).toBe( true );
	} );

	it( 'matches regardless of case and surrounding spaces', () => {
		search( '  xml ' );

		expect( hidden( 'topic-export' ) ).toBe( false );
		expect( hidden( 'topic-smtp' ) ).toBe( true );
	} );

	it( 'says so when nothing matches', () => {
		search( 'nothing like this' );

		expect( document.querySelector( '.tmq-docs-search-empty' ).hidden ).toBe( false );
	} );

	it( 'shows everything again and closes the groups when the query is cleared', () => {
		search( '' );

		expect( document.querySelectorAll( '.tmq-docs-topic[hidden]' ) ).toHaveLength( 0 );
		document.querySelectorAll( '.tmq-docs-index__group details' ).forEach( ( details ) => {
			expect( details.open ).toBe( false );
		} );
	} );
} );
