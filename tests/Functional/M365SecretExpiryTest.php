<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\ExportImport;
use TotalMailQueue\Admin\Notices;
use TotalMailQueue\Admin\Pages\SmtpPage;
use TotalMailQueue\Admin\SiteHealth;
use TotalMailQueue\Smtp\M365SecretExpiry;
use TotalMailQueue\Smtp\M365Token;
use TotalMailQueue\Smtp\Repository as SmtpRepository;
use TotalMailQueue\Support\Encryption;

/**
 * The warning before a Microsoft 365 app's client secret expires (#188):
 * the date's arithmetic in the site's time zone, the form, the list, the
 * admin notice, Site Health and Microsoft's own "expired" refusal.
 *
 * @covers \TotalMailQueue\Smtp\M365SecretExpiry
 * @covers \TotalMailQueue\Smtp\M365Token
 * @covers \TotalMailQueue\Admin\Notices
 * @covers \TotalMailQueue\Admin\SiteHealth
 * @covers \TotalMailQueue\Admin\Pages\SmtpActions
 * @covers \TotalMailQueue\Admin\Pages\SmtpFormRenderer
 * @covers \TotalMailQueue\Admin\Pages\SmtpListRenderer
 */
final class M365SecretExpiryTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        update_option( 'timezone_string', 'America/Sao_Paulo' );
        update_option( 'date_format', 'd/m/Y' );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $this->setPluginOptions( array( 'enabled' => '1' ) );
    }

    protected function tearDown(): void {
        $_GET  = array();
        $_POST = array();
        set_current_screen( 'front' );
        remove_all_filters( 'pre_http_request' );
        delete_option( 'timezone_string' );
        parent::tearDown();
    }

    /**
     * A day relative to today, in the site's time zone.
     */
    private function day( int $offset ): string {
        return ( new \DateTimeImmutable( 'today', wp_timezone() ) )->modify( sprintf( '%+d days', $offset ) )->format( 'Y-m-d' );
    }

    /**
     * @param array<string,mixed> $data
     */
    private function appAccount( string $name, ?string $expires, array $data = array() ): int {
        return $this->insertSmtpAccount(
            array_merge(
                array(
                    'name'                 => $name,
                    'host'                 => 'smtp.office365.com',
                    'auth_type'            => M365Token::AUTH_TYPE,
                    'oauth_tenant'         => 'contoso.onmicrosoft.com',
                    'oauth_client_id'      => '11111111-2222-3333-4444-555555555555',
                    'oauth_client_secret'  => Encryption::encrypt( 's3cr3t' ),
                    'oauth_secret_expires' => $expires,
                ),
                $data
            )
        );
    }

    private function capture( callable $render ): string {
        ob_start();
        $render();
        return (string) ob_get_clean();
    }

    public function test_a_date_is_kept_only_when_it_is_a_real_day(): void {
        self::assertSame( '2027-02-28', M365SecretExpiry::sanitizeDate( ' 2027-02-28 ' ) );
        foreach ( array( '2027-02-30', '28/02/2027', '2027-2-8', '', null, array() ) as $bad ) {
            self::assertSame( '', M365SecretExpiry::sanitizeDate( $bad ), (string) wp_json_encode( $bad ) );
        }
    }

    public function test_the_warning_starts_thirty_days_before_and_turns_to_expired_the_day_after(): void {
        $now  = time();
        $base = array( 'enabled' => 1, 'auth' => 1, 'auth_type' => M365Token::AUTH_TYPE );

        self::assertNull( M365SecretExpiry::status( $base + array( 'oauth_secret_expires' => $this->day( 31 ) ), $now ) );
        self::assertSame( array( 'state' => 'soon', 'date' => $this->day( 30 ), 'days' => 30 ), M365SecretExpiry::status( $base + array( 'oauth_secret_expires' => $this->day( 30 ) ), $now ) );
        self::assertSame( 0, M365SecretExpiry::status( $base + array( 'oauth_secret_expires' => $this->day( 0 ) ), $now )['days'], 'The last day is still valid.' );
        self::assertSame( array( 'state' => 'expired', 'date' => $this->day( -3 ), 'days' => 3 ), M365SecretExpiry::status( $base + array( 'oauth_secret_expires' => $this->day( -3 ) ), $now ) );

        foreach ( array(
            'no date'          => array( 'oauth_secret_expires' => null ),
            'not a date'       => array( 'oauth_secret_expires' => 'soon' ),
            'disabled account' => array( 'enabled' => 0, 'oauth_secret_expires' => $this->day( -1 ) ),
            'password account' => array( 'auth_type' => 'password', 'oauth_secret_expires' => $this->day( -1 ) ),
        ) as $case => $override ) {
            self::assertNull( M365SecretExpiry::status( array_merge( $base, $override ), $now ), $case );
        }
    }

    public function test_the_day_ends_at_midnight_in_the_sites_time_zone(): void {
        $base  = array( 'enabled' => 1, 'auth' => 1, 'auth_type' => M365Token::AUTH_TYPE, 'oauth_secret_expires' => '2027-03-10' );
        $zone  = new \DateTimeZone( 'America/Sao_Paulo' );
        $late  = ( new \DateTimeImmutable( '2027-03-10 23:59:00', $zone ) )->getTimestamp();
        $after = ( new \DateTimeImmutable( '2027-03-11 00:01:00', $zone ) )->getTimestamp();

        // 23:59 in São Paulo is already the 11th in UTC; the site's day decides.
        self::assertSame( '2027-03-11', gmdate( 'Y-m-d', $late ) );
        self::assertSame( array( 'state' => 'soon', 'date' => '2027-03-10', 'days' => 0 ), M365SecretExpiry::status( $base, $late ) );
        self::assertSame( array( 'state' => 'expired', 'date' => '2027-03-10', 'days' => 1 ), M365SecretExpiry::status( $base, $after ) );
    }

    public function test_accounts_put_the_expired_first_and_the_labels_count_days(): void {
        $this->appAccount( 'Later', $this->day( 20 ) );
        $this->appAccount( 'Gone', $this->day( -2 ) );
        $this->appAccount( 'Fine', $this->day( 90 ) );
        $this->appAccount( 'Undated', null );

        $accounts = M365SecretExpiry::accounts( time() );
        self::assertSame( array( 'Gone', 'Later' ), array_column( $accounts, 'name' ) );

        self::assertSame( 'Secret expired', M365SecretExpiry::label( $accounts[0] ) );
        self::assertSame( 'Secret expires in 20 days', M365SecretExpiry::label( $accounts[1] ) );
        self::assertSame( 'Secret expires in 1 day', M365SecretExpiry::label( array( 'state' => 'soon', 'date' => '', 'days' => 1 ) ) );
        self::assertSame( 'Secret expires today', M365SecretExpiry::label( array( 'state' => 'soon', 'date' => '', 'days' => 0 ) ) );
        self::assertSame( 'Expired 2 days ago', M365SecretExpiry::note( $accounts[0] ) );
        self::assertSame( 'Expires in 20 days', M365SecretExpiry::note( $accounts[1] ) );
        self::assertSame( 'Expires today', M365SecretExpiry::note( array( 'state' => 'soon', 'date' => '', 'days' => 0 ) ) );
    }

    public function test_save_stores_the_date_drops_a_bad_one_and_keeps_it_while_locked(): void {
        $id   = $this->appAccount( 'School', null );
        $post = array(
            'wp_tmq_smtp_save'  => '1',
            'wp_tmq_smtp_nonce' => wp_create_nonce( 'wp_tmq_smtp_save' ),
            'smtp_id'           => (string) $id,
            'smtp_name'         => 'School',
            'smtp_enabled'      => '1',
        );

        $_POST = $post + array( 'smtp_oauth_secret_expires' => '2027-05-01' );
        $this->capture( array( SmtpPage::class, 'render' ) );
        self::assertSame( '2027-05-01', SmtpRepository::findById( $id )['oauth_secret_expires'] );

        $_POST = $post;
        $this->capture( array( SmtpPage::class, 'render' ) );
        self::assertSame( '2027-05-01', SmtpRepository::findById( $id )['oauth_secret_expires'], 'A locked field is not posted and keeps the date.' );

        $_POST = $post + array( 'smtp_oauth_secret_expires' => '2027-02-30' );
        $this->capture( array( SmtpPage::class, 'render' ) );
        self::assertNull( SmtpRepository::findById( $id )['oauth_secret_expires'] );
    }

    public function test_the_form_and_the_list_show_where_the_secret_stands(): void {
        $id = $this->appAccount( 'School', $this->day( 12 ) );

        $_GET = array( 'smtp-action' => 'edit', 'smtp-id' => (string) $id );
        $form = $this->capture( array( SmtpPage::class, 'render' ) );
        self::assertStringContainsString( 'name="smtp_oauth_secret_expires" value="' . $this->day( 12 ) . '"', $form );
        self::assertStringContainsString( 'tmq-status-secret-soon">Expires in 12 days<', $form );

        $_GET = array();
        $list = $this->capture( array( SmtpPage::class, 'render' ) );
        self::assertStringContainsString( 'tmq-status-secret-soon">Secret expires in 12 days<', $list );

        SmtpRepository::update( $id, array( 'oauth_secret_expires' => $this->day( -1 ) ) );
        self::assertStringContainsString( 'tmq-status-secret-expired">Secret expired<', $this->capture( array( SmtpPage::class, 'render' ) ) );
    }

    public function test_the_notice_shows_on_the_plugins_screens_and_the_dashboard_only(): void {
        $this->appAccount( 'School', $this->day( 12 ) );

        set_current_screen( 'edit-post' );
        self::assertStringNotContainsString( 'client secret', $this->capture( array( Notices::class, 'render' ) ) );

        set_current_screen( 'dashboard' );
        $html = $this->capture( array( Notices::class, 'render' ) );
        self::assertStringContainsString( 'notice-warning', $html );
        self::assertStringContainsString( 'client secret expires soon: &quot;School&quot; (' . wp_date( 'd/m/Y', ( new \DateTimeImmutable( $this->day( 12 ), wp_timezone() ) )->getTimestamp() ) . ')', $html );
        self::assertStringContainsString( 'page=wp_tmq_mail_queue-tab-smtp', $html );

        set_current_screen( 'edit-post' );
        $_GET['page'] = 'wp_tmq_mail_queue-tab-log';
        $this->appAccount( 'Office', $this->day( -1 ) );
        $html = $this->capture( array( Notices::class, 'render' ) );
        self::assertStringContainsString( 'notice-error', $html );
        self::assertStringContainsString( 'has expired', $html );
        self::assertLessThan( strpos( $html, 'School' ), strpos( $html, 'Office' ), 'The expired account comes first.' );

        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
        self::assertStringNotContainsString( 'client secret', $this->capture( array( Notices::class, 'render' ) ) );
    }

    public function test_site_health_is_critical_once_expired_and_recommended_before(): void {
        self::assertSame( 'good', SiteHealth::testM365Auth()['status'] );

        $id     = $this->appAccount( 'School', $this->day( 10 ) );
        $result = SiteHealth::testM365Auth();
        self::assertSame( 'recommended', $result['status'] );
        self::assertSame( 'A Microsoft 365 app\'s client secret expires soon', $result['label'] );
        self::assertStringContainsString( 'expires within 30 days', $result['description'] );

        SmtpRepository::update( $id, array( 'oauth_secret_expires' => $this->day( -1 ) ) );
        $this->insertSmtpAccount( array( 'name' => 'Legacy', 'host' => 'smtp.office365.com' ) );
        $result = SiteHealth::testM365Auth();
        self::assertSame( 'critical', $result['status'] );
        self::assertSame( 'red', $result['badge']['color'] );
        self::assertStringContainsString( 'has expired', $result['description'] );
        self::assertStringContainsString( 'Accounts affected: Legacy.', $result['description'], 'Password sign-in is still reported beside it.' );
    }

    public function test_microsofts_expired_secret_refusal_is_said_plainly(): void {
        add_filter(
            'pre_http_request',
            static function () {
                return array(
                    'headers'  => array(),
                    'body'     => (string) wp_json_encode(
                        array(
                            'error'             => 'invalid_client',
                            'error_description' => "AADSTS7000222: The provided client secret keys for app '1111' are expired.\r\nTrace ID: x",
                            'error_codes'       => array( 7000222 ),
                        )
                    ),
                    'response' => array( 'code' => 401, 'message' => '' ),
                    'cookies'  => array(),
                    'filename' => null,
                );
            }
        );

        $error = M365Token::fetch( 'contoso.onmicrosoft.com', '11111111-2222-3333-4444-555555555555', 'old', false );
        self::assertWPError( $error );
        self::assertSame( 'tmq_m365_secret_expired', $error->get_error_code() );
        self::assertStringContainsString( 'the client secret has expired', $error->get_error_message() );
        self::assertStringNotContainsString( 'AADSTS', $error->get_error_message() );
    }

    public function test_export_carries_the_date(): void {
        $this->appAccount( 'School', '2027-05-01' );
        self::assertStringContainsString( '<oauth_secret_expires>2027-05-01</oauth_secret_expires>', ExportImport::buildExportXml() );
    }
}
