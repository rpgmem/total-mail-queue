<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Admin\Button;
use TotalMailQueue\Admin\Icons;

/**
 * The admin button family (#164): its markup, and that every button the
 * plugin prints on its own screens goes through it.
 *
 * @covers \TotalMailQueue\Admin\Button
 */
final class ButtonTest extends TestCase {

    private const SRC = __DIR__ . '/../../src';

    /**
     * Files allowed to print a core `.button`, each with its reason.
     */
    private const CORE_BUTTONS = array(
        // The notice shows on every admin screen, where the family's sheet
        // and its colour tokens are not loaded; it keeps core's own button,
        // like the notice around it.
        'sources/wp-version-notice.php',
    );

    public function test_a_submit_button_carries_its_category_icon_and_fields(): void {
        $html = Button::html(
            array(
                'label'    => 'Save Changes',
                'category' => 'primary',
                'icon'     => 'save',
                'type'     => 'submit',
                'name'     => 'submit',
                'id'       => 'submit',
            )
        );

        self::assertSame( '<button type="submit" class="tmq-btn tmq-btn-primary tmq-icon-save" id="submit" name="submit">Save Changes</button>', $html );
    }

    public function test_a_link_button_drops_the_form_fields_and_carries_its_question(): void {
        $html = Button::html(
            array(
                'label'    => 'Reset',
                'category' => 'danger',
                'icon'     => 'restore',
                'href'     => 'https://example.test/?a=1',
                'name'     => 'ignored',
                'value'    => 'ignored',
                'confirm'  => 'Sure?',
            )
        );

        self::assertStringStartsWith( '<a href="https://example.test/?a=1" class="tmq-btn tmq-btn-danger tmq-icon-restore" data-tmq-confirm="Sure?">', $html );
        self::assertStringNotContainsString( 'name=', $html );
        self::assertStringNotContainsString( 'type=', $html );
    }

    public function test_unknown_category_and_icon_fall_back_and_handlers_are_refused(): void {
        $html = Button::html(
            array(
                'label'    => 'Go',
                'category' => 'loud',
                'icon'     => 'no-such-icon',
                'type'     => 'bogus',
                'class'    => 'tmq-hook  other',
                'attrs'    => array(
                    'onclick'     => 'alert(1)',
                    'data-target' => 'a"b',
                    'form'        => true,
                ),
            )
        );

        self::assertSame( '<button type="button" class="tmq-btn tmq-btn-neutral tmq-hook other" data-target="a&quot;b" form>Go</button>', $html );
    }

    public function test_no_label_prints_nothing(): void {
        self::assertSame( '', Button::html( array( 'icon' => 'save' ) ) );
    }

    public function test_render_prints_the_markup(): void {
        ob_start();
        Button::render( array( 'label' => 'Copy', 'icon' => 'copy' ) );
        self::assertSame( Button::html( array( 'label' => 'Copy', 'icon' => 'copy' ) ), ob_get_clean() );
    }

    public function test_every_category_is_drawn_in_the_stylesheet(): void {
        $css = (string) file_get_contents( __DIR__ . '/../../assets/css/admin.css' );
        foreach ( Button::CATEGORIES as $category ) {
            self::assertMatchesRegularExpression( '/\.tmq-btn-' . $category . '[\s,{]/', $css, "{$category} has no rule." );
        }
    }

    /**
     * The plugin's own buttons are the family's: no core `.button`, no
     * `submit_button()`, no inline `onclick` (a question goes through
     * `confirm`), and every icon a button names is drawn.
     */
    public function test_every_button_the_plugin_prints_is_the_familys(): void {
        $found = array();
        $read  = 0;
        $files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::SRC, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }
            ++$read;
            $relative = substr( str_replace( '\\', '/', $file->getPathname() ), strlen( self::SRC ) + 1 );
            $source   = (string) file_get_contents( $file->getPathname() );
            if ( preg_match( '/class="button\b|submit_button\(|\bonclick=/', $source ) && ! in_array( $relative, self::CORE_BUTTONS, true ) ) {
                $found[] = $relative;
            }
            preg_match_all( "/'icon'\s*=>\s*'([\w-]+)'/", $source, $icons );
            foreach ( $icons[1] as $icon ) {
                self::assertArrayHasKey( $icon, Icons::PATHS, "{$relative} names the icon {$icon}, which is not drawn." );
            }
        }

        self::assertSame( array(), $found, 'A button outside the family: use Admin\Button.' );
        $recount = count( glob( self::SRC . '/{,*/,*/*/,*/*/*/}*.php', GLOB_BRACE ) ?: array() );
        self::assertSame( $recount, $read, 'The scan did not read every file under src/.' );
        foreach ( self::CORE_BUTTONS as $allowed ) {
            self::assertFileExists( self::SRC . '/' . $allowed, 'An exception names a file that is gone.' );
        }
    }
}
