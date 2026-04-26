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

    /**
     * Regression guard for the WP-core coverage extension: every email a
     * stock single-site WordPress install can produce must appear in the
     * Sources catalog right after activation, with the right group label
     * so it folds under "WordPress Core" in the admin tab.
     */
    public function test_every_wp_core_email_appears_in_the_seed_with_the_wordpress_core_group(): void {
        KnownSources::seed();

        $expected_keys = array(
            'wp_core:password_reset',
            'wp_core:new_user',
            'wp_core:new_user_admin',
            'wp_core:password_change',
            'wp_core:password_change_admin_notify',
            'wp_core:email_change',
            'wp_core:admin_email_change_confirm',
            'wp_core:auto_update',
            'wp_core:auto_update_plugins_themes',
            'wp_core:comment_notification',
            'wp_core:comment_moderation',
            'wp_core:user_action_confirm',
            'wp_core:privacy_export_ready',
            'wp_core:privacy_erasure_done',
            'wp_core:recovery_mode',
        );
        foreach ( $expected_keys as $key ) {
            $row = SourcesRepository::findByKey( $key );
            self::assertNotNull( $row, "Expected '$key' to be pre-seeded so the admin sees it without sending the email first." );
            self::assertSame( 'WordPress Core', $row['group_label'], "Expected '$key' to be grouped under 'WordPress Core'." );
            // None of the wp_core:* sources are system; admin must be allowed to toggle them.
            self::assertFalse( SourcesRepository::isSystem( $key ), "wp_core:* sources must not be locked as system." );
        }
    }

    private function sourcesTable(): string {
        return Schema::sourcesTable();
    }
}
