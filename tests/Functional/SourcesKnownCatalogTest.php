<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\AlertSender;
use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * S5: every entry in {@see KnownSources::entries()} is registered by
 * {@see Migrator::install()} so the admin sees them in the catalog
 * before the first email of each kind arrives.
 *
 * @covers \TotalMailQueue\Sources\KnownSources
 * @covers \TotalMailQueue\Database\Migrator::install
 */
final class SourcesKnownCatalogTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `{$this->sourcesTable()}`" );
    }

    public function test_install_seeds_every_known_source(): void {
        Migrator::install();

        $expected = array_map( static fn ( array $entry ): string => $entry[0], KnownSources::entries() );
        foreach ( $expected as $key ) {
            self::assertNotNull(
                SourcesRepository::findByKey( $key ),
                "Expected the catalog to contain '$key' after Migrator::install()."
            );
        }
        self::assertGreaterThanOrEqual( count( $expected ), SourcesRepository::count() );
    }

    public function test_seeding_is_idempotent_so_repeated_installs_do_not_duplicate_rows(): void {
        KnownSources::seed();
        $first_count = SourcesRepository::count();

        KnownSources::seed();
        KnownSources::seed();

        self::assertSame( $first_count, SourcesRepository::count(), 'Repeated seed() calls must not insert duplicate catalog rows.' );
    }

    public function test_seed_does_not_reset_admin_choices_on_existing_rows(): void {
        // Pre-seed once, then disable a row, then run install again — the
        // admin's choice must survive the re-seed (Repository::register()
        // returns the existing id without touching the row).
        KnownSources::seed();
        $row = SourcesRepository::findByKey( 'plugin:woocommerce' );
        self::assertNotNull( $row );
        SourcesRepository::setEnabled( (int) $row['id'], false );

        Migrator::install();

        self::assertFalse( SourcesRepository::isEnabled( 'plugin:woocommerce' ), 'A re-install must not flip the admin-disabled row back to enabled.' );
    }

    public function test_alert_system_source_appears_in_the_seed(): void {
        KnownSources::seed();

        $row = SourcesRepository::findByKey( AlertSender::SOURCE_KEY );
        self::assertNotNull( $row );
        self::assertTrue( SourcesRepository::isSystem( AlertSender::SOURCE_KEY ) );
    }

    private function sourcesTable(): string {
        return Schema::sourcesTable();
    }
}
