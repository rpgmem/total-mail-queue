/**
 * Documentation tab: quick-navigation search and the sticky offset.
 *
 * The search hides every topic whose card does not contain the text typed,
 * together with its link, and a group left with no visible link; groups that
 * still match open, so the result is visible without a click. An empty query
 * closes them and shows everything again.
 *
 * The navigation card stays at the top while the topics scroll, so its height
 * is published as `--tmq-docs-nav-h` for the topics' `scroll-margin-top`: a
 * topic opened from a link lands below the card, not under it.
 *
 * Progressive enhancement, no dependencies: without this script the page is a
 * plain index followed by the topics.
 */
( function () {
	'use strict';

	function init() {
		var nav = document.getElementById( 'tmq-docs-index' );
		var input = document.getElementById( 'tmq-docs-search' );
		if ( ! nav || ! input ) {
			return;
		}

		var topics = Array.prototype.slice.call( document.querySelectorAll( '.tmq-docs-topic' ) );
		var groups = Array.prototype.slice.call( nav.querySelectorAll( '.tmq-docs-index__group' ) );
		var empty = nav.querySelector( '.tmq-docs-search-empty' );

		function publishHeight() {
			document.documentElement.style.setProperty( '--tmq-docs-nav-h', nav.offsetHeight + 'px' );
		}

		function apply() {
			var query = input.value.trim().toLowerCase();
			var shown = 0;

			topics.forEach( function ( topic ) {
				var hit = '' === query || topic.textContent.toLowerCase().indexOf( query ) !== -1;
				topic.hidden = ! hit;
				var link = nav.querySelector( '[data-topic="' + topic.id + '"]' );
				if ( link && 'LI' === link.parentNode.nodeName ) {
					link.parentNode.hidden = ! hit;
				}
				if ( link ) {
					link.hidden = ! hit;
				}
				if ( hit ) {
					shown++;
				}
			} );

			groups.forEach( function ( group ) {
				var visible = group.querySelector( '[data-topic]:not([hidden])' );
				var details = group.querySelector( 'details' );
				group.hidden = ! visible;
				if ( details ) {
					details.open = '' !== query && !! visible;
				}
			} );

			if ( empty ) {
				empty.hidden = 0 !== shown;
			}
			publishHeight();
		}

		input.addEventListener( 'input', apply );
		nav.addEventListener( 'toggle', publishHeight, true );
		window.addEventListener( 'resize', publishHeight );
		publishHeight();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
