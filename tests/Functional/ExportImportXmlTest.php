<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

/**
 * Exercises the XML export/import round-trip and the XXE protection on import.
 *
 * @covers ::wp_tmq_build_export_xml
 * @covers ::wp_tmq_handle_import
 */
final class ExportImportXmlTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );
    }

    public function test_export_xml_includes_settings_and_smtp_accounts(): void {
        update_option( 'wp_tmq_settings', array(
            'enabled'     => '1',
            'send_method' => 'smtp',
            'email'       => 'me@example.test',
        ) );
        $this->insertSmtpAccount( array( 'name' => 'Mailgun', 'host' => 'smtp.mailgun.org' ) );

        $xml_string = wp_tmq_build_export_xml();

        self::assertStringContainsString( '<total-mail-queue', $xml_string );
        self::assertStringContainsString( '<enabled>1</enabled>', $xml_string );
        self::assertStringContainsString( '<send_method>smtp</send_method>', $xml_string );
        self::assertStringContainsString( '<host>smtp.mailgun.org</host>', $xml_string );
        self::assertStringContainsString( '<name>Mailgun</name>', $xml_string );
    }

    public function test_export_strips_auto_increment_id_and_resets_counters(): void {
        $this->insertSmtpAccount( array( 'daily_sent' => 50, 'monthly_sent' => 1000 ) );

        $xml = simplexml_load_string( wp_tmq_build_export_xml() );

        self::assertNotFalse( $xml );
        $account = $xml->smtp_accounts->account[0];
        self::assertEmpty( (string) $account->id, 'Database id must not appear in export.' );
        self::assertSame( '0', (string) $account->daily_sent );
        self::assertSame( '0', (string) $account->monthly_sent );
    }

    public function test_round_trip_preserves_settings_and_smtp_accounts(): void {
        update_option( 'wp_tmq_settings', array(
            'enabled'     => '1',
            'send_method' => 'smtp',
            'email'       => 'export@example.test',
            'max_retries' => '7',
        ) );
        $this->insertSmtpAccount( array( 'name' => 'Primary', 'host' => 'smtp.primary.test' ) );
        $this->insertSmtpAccount( array( 'name' => 'Fallback', 'host' => 'smtp.fallback.test', 'priority' => 10 ) );

        $exported = wp_tmq_build_export_xml();

        // Wipe the DB so the import has to recreate state from the XML alone.
        global $wpdb;
        delete_option( 'wp_tmq_settings' );
        $wpdb->query( "TRUNCATE TABLE `{$this->smtpTable()}`" );

        $this->importXml( $exported );

        $settings = get_option( 'wp_tmq_settings' );
        self::assertSame( '1', $settings['enabled'] );
        self::assertSame( 'smtp', $settings['send_method'] );
        self::assertSame( '7', $settings['max_retries'] );
        self::assertSame( 'export@example.test', $settings['email'] );

        $accounts = $wpdb->get_results( "SELECT * FROM `{$this->smtpTable()}` ORDER BY name", ARRAY_A );
        self::assertCount( 2, $accounts );
        self::assertSame( 'Fallback', $accounts[0]['name'] );
        self::assertSame( 'Primary', $accounts[1]['name'] );
    }

    public function test_import_drops_unknown_setting_keys(): void {
        // tableName/smtpTableName must never be writable from an import file —
        // they would let an attacker redirect SQL queries to a different table.
        $payload = '<?xml version="1.0" encoding="UTF-8"?>
            <total-mail-queue version="2.2.1" exported_at="2026-01-01">
                <settings>
                    <enabled>1</enabled>
                    <tableName>wp_users</tableName>
                    <smtpTableName>wp_options</smtpTableName>
                </settings>
                <smtp_accounts/>
            </total-mail-queue>';

        $this->importXml( $payload );

        $settings = get_option( 'wp_tmq_settings' );
        self::assertSame( '1', $settings['enabled'] );
        self::assertArrayNotHasKey( 'tableName', $settings );
        self::assertArrayNotHasKey( 'smtpTableName', $settings );
    }

    public function test_import_rejects_payload_with_external_entity_for_xxe_protection(): void {
        // Classic XXE payload: if external entities were resolved this would
        // expose /etc/passwd. LIBXML_NONET prevents that.
        $payload = "<?xml version=\"1.0\"?>
<!DOCTYPE root [
    <!ENTITY xxe SYSTEM \"file:///etc/passwd\">
]>
<total-mail-queue version=\"2.2.1\" exported_at=\"2026-01-01\">
    <settings>
        <email>&xxe;</email>
    </settings>
    <smtp_accounts/>
</total-mail-queue>";

        $this->importXml( $payload );

        $settings = get_option( 'wp_tmq_settings', array() );
        // The email value must NOT contain anything resembling /etc/passwd content
        // (root:, daemon:, etc.). Either the entity stays as &xxe; or is empty.
        $email = isset( $settings['email'] ) ? (string) $settings['email'] : '';
        self::assertStringNotContainsString( 'root:', $email );
        self::assertStringNotContainsString( '/bin/', $email );
    }

    public function test_import_returns_error_notice_for_invalid_xml(): void {
        $notice = $this->importXml( '<<this is not xml' );

        self::assertStringContainsString( 'Invalid XML file', $notice );
    }

    /**
     * Stage an uploaded XML file and run wp_tmq_handle_import().
     *
     * Returns the notice HTML the function emits.
     */
    private function importXml( string $xml ): string {
        $tmp = tempnam( sys_get_temp_dir(), 'tmq-import-' );
        file_put_contents( $tmp, $xml );

        $_POST['wp_tmq_import']       = '1';
        $_POST['wp_tmq_import_nonce'] = wp_create_nonce( 'wp_tmq_import' );
        $_FILES['wp_tmq_import_file'] = array(
            'name'     => 'import.xml',
            'type'     => 'application/xml',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize( $tmp ),
        );

        $notice = wp_tmq_handle_import();

        unset( $_POST['wp_tmq_import'], $_POST['wp_tmq_import_nonce'], $_FILES['wp_tmq_import_file'] );
        @unlink( $tmp );

        return (string) $notice;
    }
}
