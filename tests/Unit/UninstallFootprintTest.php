<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every option the plugin writes is deleted when the plugin is deleted.
 *
 * Read statically on both sides: each `update_option()` first argument under
 * `src/` and each `delete_option()` in the uninstaller, resolving `self::X`
 * and imported `Class::X` constants. It caught the template settings and the
 * WordPress-version marker, which deleting the plugin used to leave behind.
 *
 * @coversNothing
 */
final class UninstallFootprintTest extends TestCase {

    private const SRC         = __DIR__ . '/../../src/';
    private const UNINSTALLER = self::SRC . 'lifecycle/uninstaller.php';

    public function test_every_written_option_is_deleted_on_uninstall(): void {
        $written = array();
        $files   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::SRC, \FilesystemIterator::SKIP_DOTS ) );
        $scanned = 0;
        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }
            ++$scanned;
            foreach ( self::optionNames( (string) $file, 'update_option' ) as $name ) {
                $written[ $name ] = true;
            }
        }
        $deleted = array_fill_keys( self::optionNames( self::UNINSTALLER, 'delete_option' ), true );

        self::assertGreaterThan( 0, $scanned );
        self::assertArrayHasKey( 'wp_tmq_settings', $written, 'The scan stopped finding the settings write.' );
        self::assertSame( array(), array_keys( array_diff_key( $written, $deleted ) ), 'Written but never deleted on uninstall.' );
    }

    /**
     * First argument of every `$func(` call in a file, as an option name.
     *
     * @return list<string>
     */
    private static function optionNames( string $path, string $func ): array {
        $src = (string) file_get_contents( $path );
        preg_match_all( '/\b' . $func . '\(\s*([^,)]+?)\s*[,)]/', $src, $m );

        $names = array();
        foreach ( $m[1] as $arg ) {
            $names[] = self::resolve( $arg, $src, $path );
        }
        return $names;
    }

    private static function resolve( string $arg, string $src, string $path ): string {
        if ( preg_match( "/^'([^']+)'$/", $arg, $lit ) ) {
            return $lit[1];
        }
        self::assertMatchesRegularExpression( '/^[A-Za-z_\\\\]+::[A-Z_]+$/', $arg, 'Cannot resolve ' . $arg . ' in ' . $path );
        [ $class, $const ] = explode( '::', $arg );

        preg_match( '/^namespace\s+([^;]+);/m', $src, $ns );
        if ( 'self' === $class ) {
            preg_match( '/^(?:final\s+)?class\s+(\w+)/m', $src, $cls );
            $fqcn = $ns[1] . '\\' . $cls[1];
        } elseif ( preg_match( '/^use\s+([^;\s]+)\s+as\s+' . preg_quote( $class, '/' ) . ';/m', $src, $use )
            || preg_match( '/^use\s+([^;\s]*\\\\' . preg_quote( $class, '/' ) . ');/m', $src, $use ) ) {
            $fqcn = $use[1];
        } else {
            $fqcn = ltrim( $class, '\\' );
            if ( false === strpos( $fqcn, '\\' ) ) {
                $fqcn = $ns[1] . '\\' . $fqcn;
            }
        }
        return (string) constant( $fqcn . '::' . $const );
    }
}
