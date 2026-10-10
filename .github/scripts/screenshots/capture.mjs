/**
 * Capture the README screenshots from a WordPress seeded by seed.php.
 *
 * Usage:
 *   node .github/scripts/screenshots/capture.mjs <base-url> [out-dir]
 *
 * The admin account is read from TMQ_SHOT_USER / TMQ_SHOT_PASS (default
 * admin / admin, which is what a throwaway install uses). TMQ_SHOT_RESOLVE maps
 * the base URL's host to a local address (`127.0.0.1:8081`), so the site can
 * run under a fictitious domain and the URLs in the shots stay neutral.
 *
 * Every shot is the light theme except one, which switches the plugin's Dark
 * Mode setting on for that capture and back off afterwards. The seeded queue
 * must not drain while capturing: a site served under a fictitious domain
 * cannot reach its own wp-cron.php, so nothing runs it. Do not define
 * DISABLE_WP_CRON instead: the plugin then warns on every screen.
 */

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

const [ base, outDir = 'docs/screenshots' ] = process.argv.slice( 2 );
if ( ! base ) {
	console.error( 'Usage: node capture.mjs <base-url> [out-dir]' );
	process.exit( 1 );
}
const user = process.env.TMQ_SHOT_USER || 'admin';
const pass = process.env.TMQ_SHOT_PASS || 'admin';
mkdirSync( outDir, { recursive: true } );

const tab = ( slug ) => `${ base }/wp-admin/admin.php?page=${ slug }`;

async function open( page, url ) {
	await page.goto( url );
	await page.waitForLoadState( 'networkidle' );
}

async function shot( page, name, settle = 300 ) {
	// Park the pointer where it hovers nothing: the corner holds the admin bar.
	await page.mouse.move( 1439, 899 );
	await page.waitForTimeout( settle );
	await page.screenshot( { path: join( outDir, `${ name }.png` ) } );
	console.log( name );
}

// Bring an element to the top of the screen, below the admin bar. Instant,
// because the plugin's screens scroll smoothly and a shot would catch the
// page mid-way.
async function scrollTo( page, selector ) {
	await page.locator( selector ).first().evaluate( ( el ) => {
		window.scrollTo( { top: el.getBoundingClientRect().top + window.scrollY - 48, behavior: 'instant' } );
	} );
}

async function setDarkMode( page, value ) {
	await open( page, tab( 'wp_tmq_mail_queue' ) );
	await page.selectOption( '#wp_tmq_dark_mode', value );
	await Promise.all( [ page.waitForNavigation(), page.click( '#submit' ) ] );
}

const resolve = process.env.TMQ_SHOT_RESOLVE;
const browser = await chromium.launch(
	resolve ? { args: [ `--host-resolver-rules=MAP ${ new URL( base ).hostname } ${ resolve }` ] } : {}
);
const page = await ( await browser.newContext( { viewport: { width: 1440, height: 900 } } ) ).newPage();

await page.goto( `${ base }/wp-login.php` );
await page.fill( '#user_login', user );
await page.fill( '#user_pass', pass );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

await open( page, tab( 'wp_tmq_mail_queue-tab-log' ) );
await shot( page, '01-log' );

await open( page, tab( 'wp_tmq_mail_queue-tab-queue' ) );
await shot( page, '02-retention' );

await open( page, tab( 'wp_tmq_mail_queue-tab-smtp' ) );
await shot( page, '03-smtp-accounts' );

await open( page, tab( 'wp_tmq_mail_queue-tab-sources' ) );
await shot( page, '04-sources' );

await open( page, tab( 'wp_tmq_mail_queue-tab-templates' ) );
await shot( page, '05-templates-preview', 800 );

await open( page, tab( 'wp_tmq_mail_queue' ) );
await shot( page, '06-settings' );

await scrollTo( page, '.tmq-access-card, #tmq-access' );
await shot( page, '07-access' );

await open( page, tab( 'wp_tmq_mail_queue-tab-docs' ) );
await shot( page, '08-documentation' );

// The one dark capture.
await setDarkMode( page, 'on' );
await open( page, tab( 'wp_tmq_mail_queue-tab-log' ) );
await shot( page, '09-dark-mode', 800 );
await setDarkMode( page, 'off' );

await browser.close();
