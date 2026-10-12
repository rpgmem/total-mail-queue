<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Plugin;

/**
 * The version and the two floors are declared in several files that
 * WordPress, Composer and PHPCS read before any PHP runs. Each must agree with
 * the constant the code uses, or one of them silently lies.
 *
 * @coversNothing
 */
final class PluginIdentityTest extends TestCase {

    private const ROOT = __DIR__ . '/../..';

    public function test_the_version_agrees_everywhere(): void {
        $this->assertSame( Plugin::VERSION, $this->header( 'Version' ), 'Plugin header Version.' );
        $this->assertSame( Plugin::VERSION, $this->readme( 'Stable tag' ), 'readme.txt Stable tag.' );
    }

    public function test_the_php_floor_agrees_everywhere(): void {
        $this->assertSame( Plugin::REQUIRES_PHP, $this->header( 'Requires PHP' ), 'Plugin header.' );
        $this->assertSame( Plugin::REQUIRES_PHP, $this->readme( 'Requires PHP' ), 'readme.txt.' );

        $composer = json_decode( (string) file_get_contents( self::ROOT . '/composer.json' ), true );
        $this->assertSame( '>=' . Plugin::REQUIRES_PHP, $composer['require']['php'], 'composer.json.' );

        $this->assertStringContainsString( '<config name="testVersion" value="' . Plugin::REQUIRES_PHP . '-"/>', $this->phpcs(), 'PHPCompatibility testVersion.' );
    }

    public function test_the_wordpress_floor_agrees_everywhere(): void {
        $this->assertSame( Plugin::REQUIRES_WP, $this->header( 'Requires at least' ), 'Plugin header.' );
        $this->assertSame( Plugin::REQUIRES_WP, $this->readme( 'Requires at least' ), 'readme.txt.' );
        $this->assertStringContainsString( '<config name="minimum_wp_version" value="' . Plugin::REQUIRES_WP . '"/>', $this->phpcs(), 'WPCS minimum_wp_version.' );
    }

    public function test_the_released_version_has_a_changelog_section(): void {
        // release.yml publishes this section as the release notes.
        $this->assertMatchesRegularExpression(
            '/^## \[' . preg_quote( Plugin::VERSION, '/' ) . '\] \(\d{4}-\d{2}-\d{2}\)/m',
            (string) file_get_contents( self::ROOT . '/CHANGELOG.md' )
        );
    }

    private function header( string $field ): string {
        $main = (string) file_get_contents( self::ROOT . '/total-mail-queue.php' );
        $this->assertSame( 1, preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(.+)$/m', $main, $m ), "Header field {$field} is missing." );
        return trim( $m[1] );
    }

    private function readme( string $field ): string {
        $readme = (string) file_get_contents( self::ROOT . '/readme.txt' );
        $this->assertSame( 1, preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+)$/m', $readme, $m ), "readme.txt field {$field} is missing." );
        return trim( $m[1] );
    }

    private function phpcs(): string {
        return (string) file_get_contents( self::ROOT . '/phpcs.xml.dist' );
    }
}
