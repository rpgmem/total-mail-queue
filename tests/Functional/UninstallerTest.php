<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Cron\AutoBlock;
use TotalMailQueue\Cron\CronLock;
use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Lifecycle\Uninstaller;
use TotalMailQueue\Sources\WpVersionNotice;
use TotalMailQueue\Support\Paths;
use TotalMailQueue\Templates\Options as TemplateOptions;
use TotalMailQueue\Update\GithubUpdater;

/**
 * Uninstall removes every option, capability, table and attachment folder
 * the plugin wrote, including the folder older versions kept beside the
 * plugin.
 *
 * The test database wraps each case in a transaction and turns DROP TABLE
 * into DROP TEMPORARY TABLE, so the drop is checked by the statements sent
 * rather than by the tables left; the fresh-install job is what checks the
 * real footprint.
 *
 * @covers \TotalMailQueue\Lifecycle\Uninstaller
 * @covers \TotalMailQueue\Support\Paths
 */
final class UninstallerTest extends FunctionalTestCase {

    private string $root;

    /**
     * Statements the uninstall sent.
     *
     * @var list<string>
     */
    private array $queries = array();

    protected function setUp(): void {
        parent::setUp();
        $this->root = trailingslashit( get_temp_dir() ) . 'tmq-uninstall-' . wp_generate_password( 8, false );
        wp_mkdir_p( $this->root . '/plugin/attachments' );
        file_put_contents( $this->root . '/plugin/attachments/old.txt', 'legacy' );
        Paths::reset();
        wp_mkdir_p( Paths::attachmentsDir() );
        file_put_contents( Paths::attachmentsDir() . 'staged.txt', 'current' );
        add_filter( 'query', array( $this, 'record' ) );
    }

    protected function tearDown(): void {
        remove_filter( 'query', array( $this, 'record' ) );
        Paths::reset();
        Migrator::install();
        global $wp_filesystem;
        if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
            $wp_filesystem->delete( $this->root, true, 'd' );
        }
        parent::tearDown();
    }

    /**
     * Keep each statement and pass it on unchanged.
     *
     * @param string $query SQL.
     */
    public function record( $query ) {
        $this->queries[] = (string) $query;
        return $query;
    }

    public function test_everything_the_plugin_wrote_is_removed(): void {
        $options = array(
            'wp_tmq_settings'                 => array( 'enabled' => '1' ),
            AutoBlock::OPTION                 => time(),
            Migrator::VERSION_OPTION          => '9.9.9',
            'wp_tmq_last_cron'                => array( 'result' => 'ok' ),
            'wp_tmq_last_send'                => '2026-10-12 10:00:00',
            CronLock::OPTION                  => 'token|1',
            TemplateOptions::OPTION_NAME      => array( 'enabled' => '1' ),
            WpVersionNotice::OPTION_NAME      => '6.8',
        );
        foreach ( $options as $name => $value ) {
            update_option( $name, $value );
        }
        set_site_transient( GithubUpdater::CACHE_KEY, array( 'version' => '9.9.9' ) );
        Access::setRole( 'editor', Access::MANAGE, true );

        Uninstaller::uninstall( $this->root . '/plugin/total-mail-queue.php' );

        foreach ( array_keys( $options ) as $name ) {
            self::assertFalse( get_option( $name ), $name );
        }
        self::assertFalse( get_site_transient( GithubUpdater::CACHE_KEY ) );
        self::assertFalse( Access::roleHas( 'editor', Access::MANAGE ) );
        self::assertDirectoryDoesNotExist( Paths::attachmentsDir() );
        self::assertDirectoryDoesNotExist( $this->root . '/plugin/attachments' );

        $drops = implode( "\n", preg_grep( '/DROP/i', $this->queries ) );
        foreach ( array( Schema::queueTable(), Schema::smtpTable(), Schema::sourcesTable() ) as $table ) {
            self::assertStringContainsString( $table, $drops );
        }
    }

    public function test_missing_folders_are_not_an_error(): void {
        global $wp_filesystem;
        $wp_filesystem = null;
        Uninstaller::uninstall( $this->root . '/elsewhere/total-mail-queue.php' );
        Paths::reset();
        Uninstaller::uninstall( $this->root . '/elsewhere/total-mail-queue.php' );

        self::assertInstanceOf( \WP_Filesystem_Base::class, $wp_filesystem, 'Uninstall sets up the filesystem itself.' );
        self::assertDirectoryDoesNotExist( Paths::attachmentsDir() );
    }
}
