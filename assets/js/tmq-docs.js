/**
 * Documentation tab: quick-navigation search.
 *
 * The search hides every topic whose card does not contain the text typed,
 * together with its link, and a group left with no visible link; groups that
 * still match open, so the result is visible without a click. An empty query
 * closes them and shows everything again.
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
		}

		input.addEventListener( 'input', apply );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
