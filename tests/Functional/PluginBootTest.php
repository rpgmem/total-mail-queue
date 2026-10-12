<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Lifecycle\Activator;
use TotalMailQueue\Lifecycle\Deactivator;
use TotalMailQueue\Plugin;
use TotalMailQueue\Support\Paths;

/**
 * Booting the plugin: the container it publishes, the lifecycle and
 * load-time hooks it wires, and that a second boot changes nothing.
 *
 * @covers \TotalMailQueue\Plugin
 * @covers \TotalMailQueue\Support\Paths
 */
final class PluginBootTest extends FunctionalTestCase {

    private string $mainFile;

    protected function setUp(): void {
        parent::setUp();
        $this->mainFile = (string) Plugin::container()->get( 'plugin.file' );
    }

    protected function tearDown(): void {
        // Leave the process booted on the real file, as the bootstrap did.
        Plugin::reset();
        Plugin::boot( $this->mainFile );
        Paths::reset();
        parent::tearDown();
    }

    public function test_the_container_is_unavailable_before_boot(): void {
        Plugin::reset();

        $this->expectException( \LogicException::class );
        Plugin::container();
    }

    public function test_boot_publishes_the_main_file_and_wires_the_hooks(): void {
        Plugin::reset();

        Plugin::boot( $this->mainFile );

        self::assertSame( $this->mainFile, Plugin::container()->get( 'plugin.file' ) );
        $basename = plugin_basename( $this->mainFile );
        self::assertSame( 10, has_action( 'activate_' . $basename, array( Activator::class, 'activate' ) ) );
        self::assertSame( 10, has_action( 'deactivate_' . $basename, array( Deactivator::class, 'deactivate' ) ) );
        self::assertSame( 10, has_action( 'plugins_loaded', array( Migrator::class, 'maybeMigrate' ) ) );
        self::assertSame( 10, has_filter( 'cron_schedules', array( Scheduler::class, 'addInterval' ) ) );
    }

    public function test_a_second_boot_keeps_the_first_container(): void {
        $container = Plugin::container();

        Plugin::boot( '/elsewhere/other.php' );

        self::assertSame( $container, Plugin::container() );
        self::assertSame( $this->mainFile, Plugin::container()->get( 'plugin.file' ) );
    }

    public function test_paths_point_into_uploads_and_beside_the_main_file_and_are_cached(): void {
        Paths::reset();

        self::assertSame( trailingslashit( wp_upload_dir()['basedir'] ) . 'tmq-attachments/', Paths::attachmentsDir() );
        self::assertSame( plugin_dir_path( $this->mainFile ) . 'attachments/', Paths::legacyAttachmentsDir( $this->mainFile ) );
        self::assertSame( plugin_dir_path( $this->mainFile ) . 'attachments/', Paths::legacyAttachmentsDir( '/elsewhere/other.php' ), 'The first answer is kept for the request.' );
    }
}
