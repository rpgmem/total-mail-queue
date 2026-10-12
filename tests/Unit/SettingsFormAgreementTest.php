<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Settings\Sanitizer;

/**
 * The Settings form, the sanitizer and the numeric floors name the same keys.
 *
 * The import path reuses the sanitizer's key list, so a key the form renders
 * but the sanitizer does not know is silently dropped on save AND on import,
 * which is how six keys were lost on import before the two lists became one.
 *
 * @coversNothing
 */
final class SettingsFormAgreementTest extends TestCase {

    private function formSource(): string {
        return (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/admin/settings-api.php' );
    }

    /**
     * @return array<int,string>
     */
    private function renderedKeys(): array {
        $source = $this->formSource();
        preg_match_all( '/wp_tmq_settings\[(\w+)\]/', $source, $named );
        preg_match_all( "/settingToggle\(\s*'(\w+)'/", $source, $toggles );
        $keys = array_unique( array_merge( $named[1], $toggles[1] ) );
        sort( $keys );
        return $keys;
    }

    public function test_the_form_renders_exactly_the_keys_the_sanitizer_keeps(): void {
        $allowed = Sanitizer::ALLOWED_KEYS;
        sort( $allowed );

        self::assertSame( $allowed, $this->renderedKeys() );
    }

    public function test_every_number_input_is_required_and_has_a_floor(): void {
        preg_match_all( '/<input name="wp_tmq_settings\[(\w+)\]"[^>]*type="number"([^>]*)>/', $this->formSource(), $inputs, PREG_SET_ORDER );

        self::assertCount( substr_count( $this->formSource(), 'type="number"' ), $inputs, 'Every number input must be matched by the scan.' );

        $names = array();
        foreach ( $inputs as $input ) {
            $names[] = $input[1];
            self::assertStringContainsString( 'required', $input[2], $input[1] . ' must be required: a cleared field posts an empty string.' );
            self::assertMatchesRegularExpression( '/min="' . Options::NUMBER_FLOORS[ $input[1] ] . '"/', $input[2], $input[1] . ' must show the floor the sanitizer applies.' );
        }
        sort( $names );
        $floors = array_keys( Options::NUMBER_FLOORS );
        sort( $floors );

        self::assertSame( $floors, $names );
    }
}
