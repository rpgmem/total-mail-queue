<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Queue\AttachmentStore;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Queue\Tracker;
use TotalMailQueue\Support\Paths;
use TotalMailQueue\Support\RuntimeState;
use TotalMailQueue\Support\Serializer;

/**
 * Staged attachments live exactly as long as their row needs them (#81).
 *
 * @covers \TotalMailQueue\Queue\AttachmentStore
 * @covers \TotalMailQueue\Cron\BatchProcessor::run
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 * @covers \TotalMailQueue\Queue\MailFailedHandler::handle
 */
final class AttachmentLifecycleTest extends FunctionalTestCase {

    /** @var list<string> */
    private array $sources = array();

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        // Plugin::boot() ran before the option was set, so hook the interceptor here.
        \TotalMailQueue\Queue\MailInterceptor::register();
    }

    protected function tearDown(): void {
        foreach ( $this->sources as $file ) {
            if ( file_exists( $file ) ) {
                unlink( $file );
            }
            $dir = dirname( $file );
            if ( is_dir( $dir ) && str_starts_with( basename( $dir ), 'tmq-src-' ) ) {
                rmdir( $dir );
            }
        }
        remove_all_filters( 'pre_wp_mail' );
        parent::tearDown();
    }

    public function test_a_failed_send_waiting_for_a_retry_keeps_its_attachments(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'max_retries' => '3', 'queue_amount' => 1 ) );
        $id    = $this->queueWithAttachment( 'report.pdf', 'first' );
        $files = $this->stagedFiles( $id );

        $this->setRecipient( $id, 'not-an-address' );
        BatchProcessor::run();

        self::assertSame( 'queue', QueueRepository::statusFor( $id ), 'The failure must go back to the queue for a retry.' );
        self::assertFileExists( $files[0], 'The retry needs the staged file; releasing it ships the email without the attachment.' );
    }

    public function test_the_retry_delivers_the_attachment_and_then_releases_it(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'max_retries' => '3', 'queue_amount' => 1 ) );
        $id    = $this->queueWithAttachment( 'report.pdf', 'first' );
        $files = $this->stagedFiles( $id );

        $this->setRecipient( $id, 'not-an-address' );
        BatchProcessor::run();
        $this->setRecipient( $id, 'user@example.test' );
        RuntimeState::reset();
        reset_phpmailer_instance();
        BatchProcessor::run();

        self::assertSame( 'sent', QueueRepository::statusFor( $id ) );
        $sent = tests_retrieve_phpmailer_instance()->get_sent();
        self::assertNotFalse( $sent );
        self::assertFileDoesNotExist( $files[0], 'A sent row no longer needs its staged files.' );
    }

    public function test_a_final_failure_releases_the_attachments(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'max_retries' => '0', 'queue_amount' => 1 ) );
        $id    = $this->queueWithAttachment( 'report.pdf', 'first' );
        $files = $this->stagedFiles( $id );

        $this->setRecipient( $id, 'not-an-address' );
        BatchProcessor::run();

        self::assertSame( 'error', QueueRepository::statusFor( $id ) );
        self::assertFileDoesNotExist( $files[0] );
    }

    public function test_two_attachments_with_the_same_name_are_both_staged(): void {
        $first  = $this->sourceFile( 'invoice.pdf', 'from-a' );
        $second = $this->sourceFile( 'invoice.pdf', 'from-b' );

        $staged = AttachmentStore::stage( array( $first, $second ) );

        self::assertIsArray( $staged );
        self::assertCount( 2, $staged );
        self::assertSame( array( 'invoice.pdf', 'invoice-2.pdf' ), array_map( 'basename', $staged ) );
        self::assertSame( 'from-a', file_get_contents( $staged[0] ) );
        self::assertSame( 'from-b', file_get_contents( $staged[1] ), 'The second file must keep its own bytes.' );
        AttachmentStore::releaseFor( $staged );
    }

    public function test_a_failed_copy_stages_nothing_and_leaves_no_folder(): void {
        $good   = $this->sourceFile( 'a.txt', 'ok' );
        $before = $this->subfolders();

        $staged = AttachmentStore::stage( array( $good, '/nonexistent/tmq/missing.txt' ) );

        self::assertNull( $staged, 'A partial set of attachments must not be queued.' );
        self::assertSame( $before, $this->subfolders(), 'The partially copied folder must be removed.' );
    }

    public function test_an_email_whose_attachment_cannot_be_staged_is_queued_without_it(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );

        wp_mail( 'user@example.test', 'Subject', 'Body', '', array( '/nonexistent/tmq/missing.txt' ) );

        $row = $this->lastRow();
        self::assertSame( '', $row['attachments'] );
        self::assertStringContainsString( 'Could not store attachments', (string) $row['info'] );
    }

    public function test_release_never_deletes_outside_the_staging_directory(): void {
        $outside = $this->sourceFile( 'keep.txt', 'mine' );

        AttachmentStore::releaseFor( array( $outside ) );
        AttachmentStore::releaseFor( array( Paths::attachmentsDir() . 'x/../../keep.txt' ) );
        AttachmentStore::releaseFor( array( Paths::attachmentsDir() . 'file-at-the-root.txt' ) );

        self::assertFileExists( $outside );
        self::assertDirectoryExists( Paths::attachmentsDir() );
    }

    public function test_a_blocked_source_does_not_stage_attachments(): void {
        global $wpdb;
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        $wpdb->insert(
            \TotalMailQueue\Database\Schema::sourcesTable(),
            array( 'source_key' => 'plugin:blocked-one', 'label' => 'Blocked', 'group_label' => 'Test', 'enabled' => 0, 'body_override' => '' )
        );
        $before = $this->subfolders();

        wp_mail( 'user@example.test', 'Subject', 'Body', array( 'X-TMQ-Source-Key: plugin:blocked-one' ), array( $this->sourceFile( 'a.txt', 'x' ) ) );

        $row = $this->lastRow();
        self::assertSame( 'blocked_by_source', $row['status'] );
        self::assertSame( '', $row['attachments'] );
        self::assertSame( $before, $this->subfolders() );
    }

    public function test_deleting_a_queued_row_removes_its_attachments(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        $id    = $this->queueWithAttachment( 'report.pdf', 'first' );
        $files = $this->stagedFiles( $id );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

        $table              = new \TotalMailQueue\Admin\Tables\LogTable( \TotalMailQueue\Admin\Tables\LogTable::MODE_QUEUE, array( 'plural' => 'tmq_items' ) );
        $_POST['_wpnonce']  = wp_create_nonce( 'bulk-tmq_items' );
        $_REQUEST['action'] = 'delete';
        $_REQUEST['id']     = array( (string) $id );
        try {
            $table->process_bulk_action();
        } finally {
            unset( $_POST['_wpnonce'], $_REQUEST['action'], $_REQUEST['id'] );
        }

        self::assertNull( QueueRepository::findById( $id ) );
        self::assertFileDoesNotExist( $files[0] );
    }

    public function test_a_failure_clears_the_in_flight_row(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'max_retries' => '0' ) );
        $id = $this->insertQueueItem();
        Tracker::set( $id );

        $error         = new \stdClass();
        $error->errors = array( 'wp_mail_failed' => array( 'boom' ) );
        \TotalMailQueue\Queue\MailFailedHandler::handle( $error );

        self::assertSame( 0, Tracker::get(), 'A later failure in the same request must not reach this row.' );
    }

    /**
     * Queue an email with one attachment through the real wp_mail() path.
     */
    private function queueWithAttachment( string $name, string $content ): int {
        wp_mail( 'user@example.test', 'Subject', 'Body', '', array( $this->sourceFile( $name, $content ) ) );
        return (int) $this->lastRow()['id'];
    }

    /**
     * @return list<string>
     */
    private function stagedFiles( int $id ): array {
        $row   = QueueRepository::findById( $id );
        $files = Serializer::decode( (string) $row['attachments'] );
        self::assertIsArray( $files );
        self::assertFileExists( $files[0] );
        return $files;
    }

    /**
     * Point the row at a recipient. An invalid one makes PHPMailer refuse the
     * send inside wp_mail(), which fires wp_mail_failed like a refused SMTP login.
     */
    private function setRecipient( int $id, string $address ): void {
        global $wpdb;
        $wpdb->update( $this->queueTable(), array( 'recipient' => wp_json_encode( $address ) ), array( 'id' => $id ) );
    }

    private function sourceFile( string $name, string $content ): string {
        $dir = get_temp_dir() . 'tmq-src-' . wp_generate_password( 8, false );
        wp_mkdir_p( $dir );
        $path = trailingslashit( $dir ) . $name;
        file_put_contents( $path, $content );
        $this->sources[] = $path;
        return $path;
    }

    /**
     * @return list<string>
     */
    private function subfolders(): array {
        $dirs = glob( Paths::attachmentsDir() . '*', GLOB_ONLYDIR );
        return is_array( $dirs ) ? $dirs : array();
    }

    /**
     * @return array<string,mixed>
     */
    private function lastRow(): array {
        global $wpdb;
        return (array) $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
    }
}
