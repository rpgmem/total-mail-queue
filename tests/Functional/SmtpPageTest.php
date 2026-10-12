<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\SmtpPage;
use TotalMailQueue\Smtp\Repository as SmtpRepository;
use TotalMailQueue\Support\Encryption;

/**
 * The SMTP Accounts tab end to end: routing, the add and edit forms, the
 * list, and the Save / Delete / Reset Counters handlers with their nonces.
 *
 * @covers \TotalMailQueue\Admin\Pages\SmtpPage
 * @covers \TotalMailQueue\Admin\Pages\SmtpActions
 * @covers \TotalMailQueue\Admin\Pages\SmtpFormRenderer
 * @covers \TotalMailQueue\Admin\Pages\SmtpListRenderer
 */
final class SmtpPageTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    protected function tearDown(): void {
        $_GET  = array();
        $_POST = array();
        parent::tearDown();
    }

    private function render(): string {
        ob_start();
        SmtpPage::render();
        return (string) ob_get_clean();
    }

    public function test_the_empty_list_offers_only_the_add_button(): void {
        $html = $this->render();

        self::assertStringContainsString( 'No SMTP accounts configured yet.', $html );
        self::assertStringContainsString( 'smtp-action=add', $html );
        self::assertStringNotContainsString( 'wp_tmq_smtp_reset_counters', $html );
    }

    public function test_the_list_shows_limits_sender_interval_and_last_send(): void {
        $this->insertSmtpAccount(
            array(
                'name'          => 'Primary',
                'from_name'     => 'Team',
                'daily_limit'   => 100,
                'monthly_limit' => 0,
                'send_interval' => 5,
                'send_bulk'     => 0,
                'last_sent_at'  => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - 600 ),
            )
        );
        $this->insertSmtpAccount(
            array(
                'name'      => 'Backup',
                'from_name' => '',
                'enabled'   => 0,
                'send_bulk' => 20,
            )
        );

        $html = $this->render();

        self::assertStringContainsString( 'wp_tmq_smtp_reset_counters', $html );
        self::assertStringContainsString( 'Team &lt;noreply@example.test&gt;', $html );
        self::assertStringContainsString( '0 / 100', $html );
        self::assertStringContainsString( '0 / unlimited', $html );
        self::assertStringContainsString( '5 min', $html );
        self::assertStringContainsString( '0 / 20', $html );
        self::assertStringContainsString( 'minutes ago', $html );
        self::assertStringContainsString( 'Never', $html, 'An account that never sent shows Never, not 2000-01-01.' );
        self::assertStringContainsString( '<span class="tmq-ok">Yes</span>', $html );
        self::assertMatchesRegularExpression( '/smtp-action=delete&(amp;|#038;)smtp-id=\d+&(amp;|#038;)_wpnonce=/', $html );
    }

    public function test_the_add_form_is_unlocked_and_carries_no_id(): void {
        $_GET['smtp-action'] = 'add';

        $html = $this->render();

        self::assertStringContainsString( 'Add SMTP Account', $html );
        self::assertStringNotContainsString( 'name="smtp_id"', $html );
        self::assertStringNotContainsString( 'tmq-unlock-conn', $html );
        self::assertStringNotContainsString( 'disabled="disabled"', $html );
        self::assertStringContainsString( 'name="wp_tmq_smtp_nonce"', $html );
    }

    public function test_the_edit_form_locks_the_connection_and_never_prints_the_password(): void {
        $id                  = $this->insertSmtpAccount( array( 'name' => 'Primary', 'encryption' => 'ssl' ) );
        $_GET['smtp-action'] = 'edit';
        $_GET['smtp-id']     = (string) $id;

        $html = $this->render();

        self::assertStringContainsString( 'Edit SMTP Account', $html );
        self::assertStringContainsString( 'name="smtp_id" value="' . $id . '"', $html );
        self::assertStringContainsString( 'tmq-unlock-conn', $html );
        self::assertStringContainsString( 'value="ssl" selected', $html );
        self::assertStringContainsString( 'Leave blank to keep the current password.', $html );
        self::assertStringNotContainsString( Encryption::encrypt( 'pass' ), $html );
        self::assertSame( 6, substr_count( $html, 'tmq-conn-row tmq-conn-locked' ), 'Host, port, encryption, auth, username and password.' );
    }

    public function test_editing_a_missing_account_falls_back_to_an_empty_form(): void {
        $_GET['smtp-action'] = 'edit';
        $_GET['smtp-id']     = '999';

        $html = $this->render();

        self::assertStringContainsString( 'name="smtp_name" value=""', $html );
        self::assertStringContainsString( 'name="smtp_port" value="587"', $html );
    }

    public function test_save_adds_an_account_with_an_encrypted_password_then_lists(): void {
        $_POST = array(
            'wp_tmq_smtp_save'   => '1',
            'wp_tmq_smtp_nonce'  => wp_create_nonce( 'wp_tmq_smtp_save' ),
            'smtp_name'          => 'New',
            'smtp_host'          => 'mail.example.test',
            'smtp_port'          => '465',
            'smtp_encryption'    => 'ssl',
            'smtp_auth'          => '1',
            'smtp_username'      => 'me',
            'smtp_password'      => 'p@ss\'w"rd',
            'smtp_from_email'    => 'from@example.test',
            'smtp_daily_limit'   => '10',
            'smtp_enabled'       => '1',
        );
        $_GET['smtp-action'] = 'add';

        $html = $this->render();

        self::assertStringContainsString( 'SMTP account added.', $html );
        self::assertStringContainsString( 'mail.example.test:465', $html, 'A save falls through to the list.' );
        $rows = SmtpRepository::all();
        self::assertCount( 1, $rows );
        self::assertSame( 'ssl', $rows[0]['encryption'] );
        self::assertSame( 'p@ss\'w"rd', Encryption::decrypt( (string) $rows[0]['password'] ) );
    }

    public function test_save_without_a_password_on_add_stores_an_empty_one(): void {
        $_POST = array(
            'wp_tmq_smtp_save'  => '1',
            'wp_tmq_smtp_nonce' => wp_create_nonce( 'wp_tmq_smtp_save' ),
            'smtp_name'         => 'No auth',
            'smtp_host'         => 'relay.example.test',
        );

        $this->render();

        $rows = SmtpRepository::all();
        self::assertSame( '', $rows[0]['password'] );
        self::assertSame( 0, (int) $rows[0]['enabled'] );
        self::assertSame( 0, (int) $rows[0]['auth'] );
    }

    public function test_an_update_with_the_lock_closed_keeps_connection_and_password(): void {
        $id    = $this->insertSmtpAccount( array( 'host' => 'keep.example.test' ) );
        $_POST = array(
            'wp_tmq_smtp_save'  => '1',
            'wp_tmq_smtp_nonce' => wp_create_nonce( 'wp_tmq_smtp_save' ),
            'smtp_id'           => (string) $id,
            'smtp_name'         => 'Renamed',
            'smtp_priority'     => '3',
            'smtp_enabled'      => '1',
        );

        $html = $this->render();

        self::assertStringContainsString( 'SMTP account updated.', $html );
        $row = SmtpRepository::findById( $id );
        self::assertSame( 'Renamed', $row['name'] );
        self::assertSame( 3, (int) $row['priority'] );
        self::assertSame( 'keep.example.test', $row['host'] );
        self::assertSame( 'pass', Encryption::decrypt( (string) $row['password'] ) );
    }

    public function test_save_with_a_bad_nonce_dies_and_writes_nothing(): void {
        $_POST = array(
            'wp_tmq_smtp_save'  => '1',
            'wp_tmq_smtp_nonce' => 'forged',
            'smtp_name'         => 'Evil',
        );

        try {
            $this->render();
            self::fail( 'A forged nonce must stop the save.' );
        } catch ( \WPDieException $e ) {
            ob_end_clean();
            self::assertSame( array(), SmtpRepository::all() );
        }
    }

    public function test_delete_removes_the_row_with_its_nonce_and_refuses_without(): void {
        $id                  = $this->insertSmtpAccount();
        $_GET['smtp-action'] = 'delete';
        $_GET['smtp-id']     = (string) $id;

        try {
            $this->render();
            self::fail( 'A delete without its nonce must die.' );
        } catch ( \WPDieException $e ) {
            ob_end_clean();
        }
        self::assertNotNull( SmtpRepository::findById( $id ) );

        $_GET['_wpnonce'] = wp_create_nonce( 'wp_tmq_smtp_delete_' . $id );
        $html             = $this->render();

        self::assertStringContainsString( 'SMTP account deleted.', $html );
        self::assertNull( SmtpRepository::findById( $id ) );
    }

    public function test_reset_counters_zeroes_every_account_and_needs_its_nonce(): void {
        $id = $this->insertSmtpAccount( array( 'daily_sent' => 7, 'monthly_sent' => 30 ) );

        $_POST = array(
            'wp_tmq_smtp_reset_counters' => '1',
            'wp_tmq_smtp_reset_nonce'    => 'forged',
        );
        try {
            $this->render();
            self::fail( 'A forged reset nonce must die.' );
        } catch ( \WPDieException $e ) {
            ob_end_clean();
        }
        self::assertSame( 7, (int) SmtpRepository::findById( $id )['daily_sent'] );

        $_POST['wp_tmq_smtp_reset_nonce'] = wp_create_nonce( 'wp_tmq_smtp_reset_counters' );
        $html                             = $this->render();

        self::assertStringContainsString( 'All sending counters have been reset.', $html );
        $row = SmtpRepository::findById( $id );
        self::assertSame( 0, (int) $row['daily_sent'] );
        self::assertSame( 0, (int) $row['monthly_sent'] );
    }
}
