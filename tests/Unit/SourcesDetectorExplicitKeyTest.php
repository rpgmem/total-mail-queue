<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Sources\Detector;

/**
 * Exercises {@see Detector::fromExplicitKey()} — the header-declared source
 * strategy a sending plugin uses to label each of its emails distinctly.
 *
 * @covers \TotalMailQueue\Sources\Detector::fromExplicitKey
 */
final class SourcesDetectorExplicitKeyTest extends TestCase {

    public function test_accepts_a_plugin_key_with_a_feature_suffix(): void {
        $source = Detector::fromExplicitKey( 'plugin:ffcertificate_certificate', 'FFCertificate — Certificado' );

        self::assertNotNull( $source );
        self::assertSame( 'plugin:ffcertificate_certificate', $source['key'] );
        self::assertSame( 'FFCertificate — Certificado', $source['label'] );
        self::assertSame( 'Plugins', $source['group'] );
    }

    public function test_derives_group_themes_for_a_theme_key(): void {
        $source = Detector::fromExplicitKey( 'theme:twentytwentyfour_contact' );

        self::assertNotNull( $source );
        self::assertSame( 'Themes', $source['group'] );
    }

    public function test_groups_mu_plugin_keys_under_plugins(): void {
        $source = Detector::fromExplicitKey( 'mu_plugin:site-glue_alerts' );

        self::assertNotNull( $source );
        self::assertSame( 'Plugins', $source['group'] );
    }

    public function test_falls_back_to_a_slug_label_when_none_supplied(): void {
        $source = Detector::fromExplicitKey( 'plugin:ffcertificate_scheduling' );

        self::assertNotNull( $source );
        self::assertSame( 'Plugin: ffcertificate_scheduling', $source['label'] );
    }

    public function test_trims_surrounding_whitespace_on_key_and_label(): void {
        $source = Detector::fromExplicitKey( '  plugin:ffcertificate_audience  ', '  FFCertificate — Audiência  ' );

        self::assertNotNull( $source );
        self::assertSame( 'plugin:ffcertificate_audience', $source['key'] );
        self::assertSame( 'FFCertificate — Audiência', $source['label'] );
    }

    /**
     * A header must never be able to claim a wp_core: source — that namespace
     * belongs to the primary listeners, and letting outgoing mail assert it
     * would let a third-party plugin impersonate a core email.
     */
    public function test_rejects_a_wp_core_key(): void {
        self::assertNull( Detector::fromExplicitKey( 'wp_core:password_reset' ) );
    }

    public function test_rejects_an_unprefixed_or_malformed_key(): void {
        self::assertNull( Detector::fromExplicitKey( 'ffcertificate_certificate' ) );
        self::assertNull( Detector::fromExplicitKey( '' ) );
        self::assertNull( Detector::fromExplicitKey( 'plugin:' ) );
        self::assertNull( Detector::fromExplicitKey( 'plugin:bad slug' ) );
        self::assertNull( Detector::fromExplicitKey( 'total_mail_queue:alert' ) );
    }
}
