<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use PHPMailer\PHPMailer\PHPMailer;
use TotalMailQueue\Admin\ExportImport;
use TotalMailQueue\Admin\Pages\SmtpPage;
use TotalMailQueue\Admin\SiteHealth;
use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Queue\MailFailedHandler;
use TotalMailQueue\Queue\Tracker;
use TotalMailQueue\Smtp\Configurator;
use TotalMailQueue\Smtp\M365Credentials;
use TotalMailQueue\Smtp\M365Token;
use TotalMailQueue\Smtp\Repository as SmtpRepository;
use TotalMailQueue\Support\Encryption;

/**
 * SMTP accounts that sign in to Microsoft 365 with an app (#186): the token
 * request, its cache and its errors, the XOAUTH2 credentials, the account
 * form and its save, the failure log and the schema revision.
 *
 * @covers \TotalMailQueue\Smtp\M365Token
 * @covers \TotalMailQueue\Smtp\M365Credentials
 * @covers \TotalMailQueue\Smtp\Configurator
 * @covers \TotalMailQueue\Admin\Pages\SmtpActions
 * @covers \TotalMailQueue\Admin\Pages\SmtpFormRenderer
 * @covers \TotalMailQueue\Database\Migrator
 */
final class M365OAuthTest extends FunctionalTestCase {

    private const TENANT = 'contoso.onmicrosoft.com';
    private const CLIENT = '11111111-2222-3333-4444-555555555555';

    /**
     * Requests sent to Microsoft.
     *
     * @var list<array<string,mixed>>
     */
    private array $requests = array();

    /**
     * What the fake Microsoft answers.
     *
     * @var array<string,mixed>|\WP_Error
     */
    private $answer = array();

    protected function setUp(): void {
        parent::setUp();
        $this->requests = array();
        $this->answer   = $this->tokenResponse( 'tok-1', 3599 );
        add_filter( 'pre_http_request', array( $this, 'fakeMicrosoft' ), 10, 3 );
        M365Token::takeLastError();
    }

    protected function tearDown(): void {
        remove_filter( 'pre_http_request', array( $this, 'fakeMicrosoft' ), 10 );
        $_GET  = array();
        $_POST = array();
        Tracker::reset();
        parent::tearDown();
    }

    /**
     * `pre_http_request` stand-in for login.microsoftonline.com.
     *
     * @param mixed               $pre  Short-circuit value.
     * @param array<string,mixed> $args Request arguments.
     * @param string              $url  Request URL.
     * @return mixed
     */
    public function fakeMicrosoft( $pre, $args, $url ) {
        if ( 0 !== strpos( $url, 'https://login.microsoftonline.com/' ) ) {
            return $pre;
        }
        $this->requests[] = array( 'url' => $url ) + $args;
        return $this->answer;
    }

    /**
     * @return array<string,mixed>
     */
    private function tokenResponse( string $token, int $expires_in ): array {
        return $this->response( 200, array( 'token_type' => 'Bearer', 'expires_in' => $expires_in, 'access_token' => $token ) );
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function response( int $code, array $body ): array {
        return array(
            'headers'  => array(),
            'body'     => (string) wp_json_encode( $body ),
            'response' => array( 'code' => $code, 'message' => '' ),
            'cookies'  => array(),
            'filename' => null,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function m365Account( array $data = array() ): array {
        $id = $this->insertSmtpAccount(
            array_merge(
                array(
                    'name'                => 'School',
                    'host'                => 'smtp.office365.com',
                    'username'            => 'mailbox@contoso.test',
                    'password'            => '',
                    'auth_type'           => M365Token::AUTH_TYPE,
                    'oauth_tenant'        => self::TENANT,
                    'oauth_client_id'     => self::CLIENT,
                    'oauth_client_secret' => Encryption::encrypt( 's3cr3t~' ),
                ),
                $data
            )
        );
        return (array) SmtpRepository::findById( $id );
    }

    private function render(): string {
        ob_start();
        SmtpPage::render();
        return (string) ob_get_clean();
    }

    public function test_a_token_is_requested_with_client_credentials_and_cached(): void {
        $token = M365Token::fetch( self::TENANT, self::CLIENT, 's3cr3t~' );

        self::assertSame( 'tok-1', $token );
        self::assertCount( 1, $this->requests );
        self::assertSame( 'https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token', $this->requests[0]['url'] );
        self::assertSame(
            array(
                'grant_type'    => 'client_credentials',
                'client_id'     => self::CLIENT,
                'client_secret' => 's3cr3t~',
                'scope'         => 'https://outlook.office365.com/.default',
            ),
            $this->requests[0]['body']
        );

        $this->answer = $this->tokenResponse( 'tok-2', 3599 );
        self::assertSame( 'tok-1', M365Token::fetch( self::TENANT, self::CLIENT, 's3cr3t~' ), 'A batch asks Microsoft once.' );
        self::assertCount( 1, $this->requests );

        self::assertSame( 'tok-2', M365Token::fetch( self::TENANT, self::CLIENT, 's3cr3t~', false ), 'Test Connection skips the cache.' );
        self::assertSame( 'tok-2', M365Token::fetch( self::TENANT, self::CLIENT, 'another' ), 'A new secret is a new cache entry.' );
        self::assertCount( 3, $this->requests );
    }

    public function test_the_cached_token_expires_before_microsofts(): void {
        $this->answer = $this->tokenResponse( 'tok-1', 3600 );
        M365Token::fetch( self::TENANT, self::CLIENT, 's3cr3t~' );

        $key = '_transient_timeout_tmq_m365_token_' . md5( self::TENANT . '|' . self::CLIENT . '|' . hash( 'sha256', 's3cr3t~' ) );
        $ttl = (int) get_option( $key ) - time();
        self::assertGreaterThanOrEqual( 3470, $ttl );
        self::assertLessThanOrEqual( 3480, $ttl );
    }

    public function test_microsofts_own_reason_is_returned_and_kept(): void {
        $this->answer = $this->response( 401, array( 'error' => 'invalid_client', 'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: abc" ) );

        $token = M365Token::fetch( self::TENANT, self::CLIENT, 'wrong' );

        self::assertWPError( $token );
        self::assertSame( 'Microsoft refused the sign-in: AADSTS7000215: Invalid client secret provided.', $token->get_error_message() );
        self::assertSame( $token->get_error_message(), M365Token::takeLastError() );
        self::assertSame( '', M365Token::takeLastError(), 'Read once.' );
        self::assertFalse( get_transient( 'tmq_m365_token_' . md5( self::TENANT . '|' . self::CLIENT . '|' . hash( 'sha256', 'wrong' ) ) ), 'A failure is not cached.' );
    }

    public function test_an_answer_with_no_reason_names_the_http_status_and_a_network_error_says_so(): void {
        $this->answer = $this->response( 503, array() );
        self::assertSame( 'Microsoft refused the sign-in: HTTP 503', M365Token::fetch( self::TENANT, self::CLIENT, 'x' )->get_error_message() );

        $this->answer = new \WP_Error( 'http_request_failed', 'cURL error 6' );
        self::assertSame( 'Microsoft could not be reached: cURL error 6', M365Token::fetch( self::TENANT, self::CLIENT, 'x' )->get_error_message() );
    }

    public function test_bad_ids_and_a_missing_secret_fail_before_any_request(): void {
        self::assertSame( 'tmq_m365_tenant', M365Token::fetch( 'contoso/../x', self::CLIENT, 'x' )->get_error_code() );
        self::assertSame( 'tmq_m365_client', M365Token::fetch( self::TENANT, 'not-a-guid', 'x' )->get_error_code() );
        self::assertSame( 'tmq_m365_secret', M365Token::fetch( self::TENANT, self::CLIENT, '' )->get_error_code() );
        self::assertSame( array(), $this->requests );

        self::assertTrue( M365Token::validTenant( '9188040d-6c67-4c5b-b112-36a304b66dad' ) );
        self::assertFalse( M365Token::validTenant( '-contoso.com' ) );
        self::assertFalse( M365Token::validClientId( '11111111-2222-3333-4444-55555555555' ) );
    }

    public function test_the_credentials_are_xoauth2_for_the_mailbox(): void {
        $mailer = new PHPMailer();
        M365Credentials::apply( $mailer, 'mailbox@contoso.test', 'tok-1' );

        self::assertTrue( $mailer->SMTPAuth );
        self::assertSame( 'XOAUTH2', $mailer->AuthType );
        self::assertSame( 'mailbox@contoso.test', $mailer->Username );
        self::assertSame( '', $mailer->Password );
        $provider = $mailer->getOAuth();
        self::assertInstanceOf( M365Credentials::class, $provider );
        self::assertSame( "user=mailbox@contoso.test\001auth=Bearer tok-1\001\001", base64_decode( $provider->getOauth64() ) );
    }

    public function test_the_configurator_signs_an_app_account_in_with_a_token(): void {
        $mailer = new PHPMailer();
        Configurator::apply( $mailer, $this->m365Account() );

        self::assertSame( 'XOAUTH2', $mailer->AuthType );
        self::assertSame( "user=mailbox@contoso.test\001auth=Bearer tok-1\001\001", base64_decode( $mailer->getOAuth()->getOauth64() ) );
        self::assertSame( 's3cr3t~', $this->requests[0]['body']['client_secret'], 'The stored secret is decrypted.' );
    }

    public function test_a_password_account_after_an_app_account_signs_in_with_its_password(): void {
        $this->answer = $this->response( 401, array( 'error_description' => 'AADSTS7000215: Invalid client secret provided.' ) );
        $mailer       = new PHPMailer();
        Configurator::apply( $mailer, $this->m365Account() );
        Configurator::apply( $mailer, (array) SmtpRepository::findById( $this->insertSmtpAccount() ) );

        self::assertSame( '', $mailer->AuthType );
        self::assertSame( 'pass', $mailer->Password );
        self::assertSame( '', M365Token::takeLastError(), 'Microsoft\'s reason belongs to the earlier email only.' );
    }

    public function test_a_failed_send_logs_microsofts_reason_instead_of_the_smtp_error(): void {
        $this->setPluginOptions( array( 'max_retries' => 0 ) );
        $mail_id      = $this->insertQueueItem( array( 'status' => 'queue' ) );
        $this->answer = $this->response( 401, array( 'error_description' => 'AADSTS7000215: Invalid client secret provided.' ) );
        Configurator::apply( new PHPMailer(), $this->m365Account() );

        Tracker::set( $mail_id );
        MailFailedHandler::handle( new \WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate.' ) );

        $info = (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT info FROM %i WHERE id = %d', $this->queueTable(), $mail_id ) );
        self::assertStringContainsString( 'Microsoft refused the sign-in: AADSTS7000215', $info );
        self::assertStringNotContainsString( 'Could not authenticate', $info );
    }

    public function test_the_form_offers_the_app_and_shows_its_rows_for_an_app_account(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $_GET['smtp-action'] = 'add';
        $html                = $this->render();
        self::assertStringContainsString( 'name="smtp_auth_type" value="m365"', $html );
        self::assertStringContainsString( 'How to set this up in Microsoft 365', $html );
        self::assertStringNotContainsString( 'tmq-auth-m365', $html, 'A new account starts on Password.' );

        $account             = $this->m365Account();
        $_GET['smtp-action'] = 'edit';
        $_GET['smtp-id']     = (string) $account['id'];
        $html                = $this->render();
        self::assertStringContainsString( 'tmq-smtp-form tmq-auth-m365', $html );
        self::assertStringContainsString( 'value="' . self::TENANT . '"', $html );
        self::assertStringContainsString( 'value="' . self::CLIENT . '"', $html );
        self::assertStringNotContainsString( 's3cr3t~', $html );
        self::assertStringNotContainsString( (string) $account['oauth_client_secret'], $html, 'The secret is never printed, not even encrypted.' );
        self::assertStringContainsString( 'placeholder="' . str_repeat( "\xE2\x80\xA2", 8 ) . '"', $html );
    }

    public function test_save_stores_the_app_fields_encrypts_the_secret_and_a_blank_keeps_it(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $post  = array(
            'wp_tmq_smtp_save'         => '1',
            'wp_tmq_smtp_nonce'        => wp_create_nonce( 'wp_tmq_smtp_save' ),
            'smtp_name'                => 'School',
            'smtp_host'                => 'smtp.office365.com',
            'smtp_port'                => '587',
            'smtp_encryption'          => 'tls',
            'smtp_auth'                => '1',
            'smtp_auth_type'           => 'm365',
            'smtp_username'            => 'mailbox@contoso.test',
            'smtp_oauth_tenant'        => ' ' . self::TENANT . ' ',
            'smtp_oauth_client_id'     => self::CLIENT,
            'smtp_oauth_client_secret' => 'a"b\'c~1',
            'smtp_enabled'             => '1',
        );
        $_POST = $post;
        $this->render();

        $row = SmtpRepository::all()[0];
        self::assertSame( 'm365', $row['auth_type'] );
        self::assertSame( self::TENANT, $row['oauth_tenant'] );
        self::assertSame( self::CLIENT, $row['oauth_client_id'] );
        self::assertNotSame( 'a"b\'c~1', $row['oauth_client_secret'] );
        self::assertSame( 'a"b\'c~1', Encryption::decrypt( (string) $row['oauth_client_secret'] ) );

        $_POST = array_merge(
            $post,
            array(
                'smtp_id'                  => (string) $row['id'],
                'smtp_oauth_client_secret' => '',
                'smtp_oauth_client_id'     => 'not-a-guid',
            )
        );
        $this->render();

        $row = (array) SmtpRepository::findById( (int) $row['id'] );
        self::assertSame( 'a"b\'c~1', Encryption::decrypt( (string) $row['oauth_client_secret'] ), 'A blank secret keeps the stored one.' );
        self::assertSame( '', $row['oauth_client_id'], 'A value that is not a GUID is not stored.' );

        $_POST = array_merge( $post, array( 'smtp_id' => (string) $row['id'], 'smtp_auth_type' => 'anything' ) );
        $this->render();
        self::assertSame( 'password', SmtpRepository::findById( (int) $row['id'] )['auth_type'] );
    }

    public function test_export_and_import_carry_the_app_fields(): void {
        $account = $this->m365Account();
        $xml     = ExportImport::buildExportXml();

        self::assertStringContainsString( '<auth_type>m365</auth_type>', $xml );
        self::assertStringContainsString( '<oauth_tenant>' . self::TENANT . '</oauth_tenant>', $xml );
        self::assertStringContainsString( '<oauth_client_id>' . self::CLIENT . '</oauth_client_id>', $xml );
        self::assertStringContainsString( '<oauth_client_secret>' . esc_xml( (string) $account['oauth_client_secret'] ) . '</oauth_client_secret>', $xml );
        self::assertStringNotContainsString( 's3cr3t~', $xml, 'The secret leaves encrypted.' );
    }

    public function test_site_health_does_not_flag_an_app_account(): void {
        $this->m365Account();
        self::assertSame( array(), SiteHealth::m365PasswordAccounts() );
    }

    public function test_a_schema_revision_behind_the_code_reapplies_the_schema(): void {
        update_option( Migrator::SCHEMA_REVISION_OPTION, Schema::REVISION - 1 );
        Migrator::maybeMigrate();
        self::assertSame( Schema::REVISION, (int) get_option( Migrator::SCHEMA_REVISION_OPTION ) );

        $columns = SmtpRepository::tableColumns();
        foreach ( array( 'auth_type', 'oauth_tenant', 'oauth_client_id', 'oauth_client_secret' ) as $column ) {
            self::assertContains( $column, $columns, $column );
        }
    }
}
