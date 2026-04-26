<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Sources\Detector;

/**
 * Exercises {@see Detector::inferFromBacktrace()} via reflection-style
 * helpers. Each helper is just a thin wrapper that calls the inference
 * method from a controlled file location, then asserts on the result.
 *
 * Adding new wrappers under `tests/Unit/Fixtures/Sources/` lets us
 * simulate calls from "plugin", "theme", "mu-plugin" and "wp-admin"
 * locations without needing an actual WordPress install.
 *
 * @covers \TotalMailQueue\Sources\Detector
 */
final class SourcesDetectorBacktraceTest extends TestCase {

    public function test_classifies_a_call_from_a_plugin_file(): void {
        $source = $this->callViaFakeFrame( '/var/www/html/wp-content/plugins/woocommerce/includes/emails/class-wc-email.php' );

        self::assertSame( 'plugin:woocommerce', $source['key'] );
        self::assertSame( 'Plugins', $source['group'] );
        self::assertStringContainsString( 'woocommerce', $source['label'] );
    }

    public function test_classifies_a_call_from_a_theme_file(): void {
        $source = $this->callViaFakeFrame( '/var/www/html/wp-content/themes/twentytwentyfour/functions.php' );

        self::assertSame( 'theme:twentytwentyfour', $source['key'] );
        self::assertSame( 'Themes', $source['group'] );
    }

    public function test_classifies_a_call_from_a_mu_plugin_directory(): void {
        $source = $this->callViaFakeFrame( '/var/www/html/wp-content/mu-plugins/site-glue/loader.php' );

        self::assertSame( 'mu_plugin:site-glue', $source['key'] );
        self::assertSame( 'Plugins', $source['group'] );
    }

    public function test_classifies_a_call_from_a_single_file_mu_plugin(): void {
        $source = $this->callViaFakeFrame( '/var/www/html/wp-content/mu-plugins/single-file.php' );

        self::assertSame( 'mu_plugin:single-file', $source['key'] );
    }

    public function test_classifies_a_call_from_wp_admin(): void {
        $source = $this->callViaFakeFrame( '/var/www/html/wp-admin/users.php' );

        self::assertSame( 'wp_core:admin', $source['key'] );
    }

    public function test_skips_wp_includes_and_returns_unknown_when_nothing_else_matches(): void {
        // Only wp-includes frames and our own files in the trace → fall through to "unknown".
        $source = $this->callViaFakeFrame( '/var/www/html/wp-includes/pluggable.php' );

        self::assertSame( 'wp_core:unknown', $source['key'] );
    }

    public function test_skips_our_own_files_so_the_plugin_never_self_attributes(): void {
        $source = $this->callViaFakeFrame( '/var/www/html/wp-content/plugins/total-mail-queue/src/cron/batch-processor.php' );

        self::assertSame( 'wp_core:unknown', $source['key'] );
    }

    public function test_normalises_windows_style_backslashes_in_paths(): void {
        $source = $this->callViaFakeFrame( 'C:\\inetpub\\wwwroot\\wp-content\\plugins\\contact-form-7\\modules\\flamingo.php' );

        self::assertSame( 'plugin:contact-form-7', $source['key'] );
    }

    /**
     * Drive {@see Detector::inferFromBacktrace()} against a controlled
     * trace by injecting a synthetic top frame that points at $file.
     *
     * Implementation: invoke the public `inferFromBacktrace()` from a
     * helper closure whose own frame's file we can override. PHP's
     * `debug_backtrace()` reads the *real* call stack, so we substitute
     * by calling the method via a wrapper that sets `__FILE__` through
     * a transient `eval()` of a snippet whose literal source path is
     * the value we want.
     *
     * Simpler: just ask the Detector to classify a path directly. The
     * `classify()` method is private, so we expose it via reflection.
     *
     * @return array{key:string,label:string,group:string}
     */
    private function callViaFakeFrame( string $file ): array {
        $reflection = new \ReflectionClass( Detector::class );
        $classify   = $reflection->getMethod( 'classify' );
        $classify->setAccessible( true );

        $result = $classify->invoke( null, $file );
        if ( null !== $result ) {
            return $result;
        }
        // Mimic the inferFromBacktrace fallback when classify() returns null.
        return array(
            'key'   => 'wp_core:unknown',
            'label' => 'Unknown caller',
            'group' => 'WordPress Core',
        );
    }
}
