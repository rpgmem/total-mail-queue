<?php
/**
 * Compile every `languages/*.po` into the `.mo` and the `.l10n.php`
 * WordPress reads.
 *
 *   php bin/compile-translations.php
 *
 * Edit the `.po` (by hand or in Loco), then run this; never edit a compiled
 * catalogue. `TranslationCatalogueAgreementTest` fails when the three files
 * disagree, so a `.po` change committed without recompiling is caught in CI.
 *
 * The `.mo` comes from GNU gettext's `msgfmt --check`. The `.l10n.php` is
 * written by `bin/po-catalogue.php`, the reader the test uses, so the
 * compiler and the test cannot disagree about what an entry is.
 *
 * @package TotalMailQueue\Build
 */

declare(strict_types=1);

require_once __DIR__ . '/po-catalogue.php';

use TotalMailQueue\Build\PoCatalogue;

$files = glob( dirname( __DIR__ ) . '/languages/*.po' ) ?: array();
if ( array() === $files ) {
	fwrite( STDERR, "No .po file under languages/.\n" );
	exit( 1 );
}

foreach ( $files as $po ) {
	$base = substr( $po, 0, -3 );
	exec( 'msgfmt --check -o ' . escapeshellarg( $base . '.mo' ) . ' ' . escapeshellarg( $po ) . ' 2>&1', $output, $status );
	if ( 0 !== $status ) {
		fwrite( STDERR, implode( "\n", $output ) . "\nmsgfmt failed on {$po} (is GNU gettext installed?).\n" );
		exit( 1 );
	}
	file_put_contents( $base . '.l10n.php', PoCatalogue::l10n( $po, 'total-mail-queue' ) );
	echo basename( $po ) . "\n";
}
