<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The admin draws booleans as toggles, never as bare checkboxes (#89).
 *
 * A checkbox literal in the admin code is a field that skipped
 * `Admin\Toggle`. The only ones that stay are the list tables' row
 * selectors, which choose rows for a bulk action rather than store a value.
 *
 * @coversNothing
 */
final class ToggleTest extends TestCase {

    private const DIR = __DIR__ . '/../../src/admin/';

    /**
     * Checkbox literals that stay, each with why.
     */
    private const ALLOWED = array(
        // Admin\Toggle itself: the checkbox inside the switch.
        'toggle.php',
        // Row selectors of the list tables, for bulk actions.
        'tables/log-table.php',
        'tables/sources-table.php',
    );

    public function test_no_admin_screen_prints_a_bare_checkbox(): void {
        $bare  = array();
        $files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::DIR, \FilesystemIterator::SKIP_DOTS ) );
        $count = 0;
        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }
            ++$count;
            $relative = substr( (string) $file, strlen( self::DIR ) );
            if ( in_array( $relative, self::ALLOWED, true ) ) {
                continue;
            }
            if ( preg_match( '/type=(\\\\?["\'])checkbox/', (string) file_get_contents( (string) $file ) ) ) {
                $bare[] = $relative;
            }
        }

        $recount = count( (array) glob( self::DIR . '*.php' ) ) + count( (array) glob( self::DIR . '*/*.php' ) );
        self::assertSame( $recount, $count, 'The two traversals disagree: the scan is reading the wrong set.' );
        self::assertSame( array(), $bare, 'A bare checkbox: render it with Admin\Toggle.' );
    }

    public function test_the_allowlist_still_matches_real_checkboxes(): void {
        foreach ( self::ALLOWED as $relative ) {
            self::assertMatchesRegularExpression( '/checkbox/', (string) file_get_contents( self::DIR . $relative ), $relative . ' no longer prints a checkbox; drop it from the list.' );
        }
    }

    public function test_every_autosave_key_in_the_markup_is_allowlisted(): void {
        $src = '';
        foreach ( array( 'settings-api.php', 'pages/templates-page.php' ) as $file ) {
            $src .= (string) file_get_contents( self::DIR . $file );
        }
        preg_match_all( "/'autosave'\\s*=>\\s*'([a-z_]+:[a-z_]+)'/", $src, $m );
        preg_match_all( "/'autosave'\\s*=>\\s*'settings:' \\. \\\$key/", $src, $dynamic );
        preg_match_all( "/settingToggle\\(\\s*'([a-z_]+)'/", $src, $settings );

        $keys = array_merge( $m[1], array_map( static fn ( string $k ): string => 'settings:' . $k, $settings[1] ) );
        sort( $keys );
        $allowed = array_keys( \TotalMailQueue\Admin\SettingsAutosave::KEYS );
        sort( $allowed );

        self::assertCount( 1, $dynamic[0], 'settingToggle() must keep building its key from the setting name.' );
        self::assertSame( $allowed, $keys, 'Every autosave toggle needs an allowlisted key, and every key a toggle.' );
    }
}
