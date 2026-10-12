<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Admin\AccessSettings;

/**
 * The Access card: one row per role that is not an administrator, with the
 * two switches showing what the role holds.
 *
 * @covers \TotalMailQueue\Admin\AccessSettings
 * @covers \TotalMailQueue\Admin\Access
 */
final class AccessSettingsTest extends FunctionalTestCase {

    protected function tearDown(): void {
        Access::revokeAll();
        parent::tearDown();
    }

    private function render(): string {
        ob_start();
        AccessSettings::render();
        return (string) ob_get_clean();
    }

    public function test_every_role_but_the_administrator_gets_a_row_with_both_switches(): void {
        $html = $this->render();

        self::assertStringContainsString( '<th scope="row">Editor</th>', $html );
        self::assertStringContainsString( '<th scope="row">Subscriber</th>', $html );
        self::assertStringNotContainsString( '<th scope="row">Administrator</th>', $html );
        self::assertStringContainsString( 'name="tmq_access[editor][view]"', $html );
        self::assertStringContainsString( 'name="tmq_access[editor][manage]"', $html );
        self::assertStringContainsString( 'access:editor:manage', $html );
    }

    public function test_a_switch_is_on_exactly_when_the_role_holds_the_capability(): void {
        Access::setRole( 'editor', Access::VIEW, true );

        $html = $this->render();

        self::assertMatchesRegularExpression( '/id="tmq-access-editor-view"[^>]*checked/', $html );
        self::assertDoesNotMatchRegularExpression( '/id="tmq-access-editor-manage"[^>]*checked/', $html );
        self::assertDoesNotMatchRegularExpression( '/id="tmq-access-author-view"[^>]*checked/', $html );
    }

    public function test_a_site_with_only_administrators_says_so(): void {
        $roles       = wp_roles();
        $saved       = $roles->roles;
        $roles->roles = array( 'administrator' => $saved['administrator'] );

        try {
            $html = $this->render();
        } finally {
            $roles->roles = $saved;
        }

        self::assertStringContainsString( 'There are no other roles on this site.', $html );
        self::assertStringNotContainsString( '<table', $html );
    }
}
