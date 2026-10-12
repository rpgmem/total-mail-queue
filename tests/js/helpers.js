import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const require = createRequire( import.meta.url );

/**
 * Real jQuery, bound to the test's window and published where the admin
 * scripts look for it.
 *
 * @return {Function} jQuery.
 */
export function installJquery() {
	const $ = require( 'jquery' );
	window.jQuery = window.$ = $;
	globalThis.jQuery = globalThis.$ = $;
	$.fx.off = true;
	// jsdom has already fired DOMContentLoaded by the time jQuery listens for it,
	// so ready would never resolve and every $( fn ) would stay unbound.
	if ( ! $.isReady ) {
		$.ready();
	}
	return $;
}

/**
 * Run a browser script in the test's window, under its real file name, so
 * V8 attributes its coverage to it (new Function() would leave it at 0%).
 *
 * @param {string} file Path from the repository root.
 */
export function runScript( file ) {
	const full = path.join( root, file );
	vm.runInThisContext( readFileSync( full, 'utf8' ), { filename: full } );
}

/**
 * A jQuery Deferred already settled, standing in for $.post / $.get.
 *
 * @param {Function} $       jQuery.
 * @param {boolean}  ok      Resolve (true) or reject (false).
 * @param {Array}    args    Arguments the callbacks receive.
 * @return {Object} The promise.
 */
export function settled( $, ok, args ) {
	const deferred = $.Deferred();
	if ( ok ) {
		deferred.resolve( ...args );
	} else {
		deferred.reject( ...args );
	}
	return deferred.promise();
}
