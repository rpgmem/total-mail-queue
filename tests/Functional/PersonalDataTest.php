<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Privacy\PersonalData;

/**
 * The email log in WordPress's privacy tools (#178): which rows a request
 * reaches, what the export shows and what the erasure removes or keeps.
 *
 * @covers \TotalMailQueue\Privacy\PersonalData
 */
final class PersonalDataTest extends FunctionalTestCase {

    private function rows( string $where = '1=1' ): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}` WHERE $where" ); // phpcs:ignore -- test query on the plugin's own table
    }

    public function test_both_callbacks_are_registered_with_wordpress(): void {
        $exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
        $erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );

        self::assertSame( array( PersonalData::class, 'export' ), $exporters[ PersonalData::KEY ]['callback'] );
        self::assertSame( array( PersonalData::class, 'erase' ), $erasers[ PersonalData::KEY ]['callback'] );
    }

    public function test_recipient_shapes_are_read_address_by_address(): void {
        self::assertSame( array( 'ana@example.org' ), PersonalData::addresses( wp_json_encode( 'Ana@Example.org' ) ) );
        self::assertSame(
            array( 'ana@example.org', 'bia@example.org' ),
            PersonalData::addresses( wp_json_encode( 'Ana <ana@example.org>, bia@example.org' ) )
        );
        self::assertSame(
            array( 'ana@example.org', 'bia@example.org' ),
            PersonalData::addresses( wp_json_encode( array( 'ana@example.org', 'Bia Souza <bia@example.org>' ) ) )
        );
        self::assertSame( array(), PersonalData::addresses( wp_json_encode( 'not an address' ) ) );
    }

    public function test_export_lists_only_the_requesters_rows_with_the_body_as_text(): void {
        $own   = $this->insertQueueItem( array(
            'status'    => 'sent',
            'recipient' => wp_json_encode( array( 'Ana <ana@example.org>' ) ),
            'subject'   => 'Welcome',
            'message'   => '<p>Hello Ana</p><p>See you</p>',
        ) );
        // A longer address that contains the requester's must not match.
        $this->insertQueueItem( array( 'status' => 'sent', 'recipient' => wp_json_encode( 'joana@example.org' ) ) );
        $this->insertQueueItem( array( 'status' => 'sent', 'recipient' => wp_json_encode( 'bia@example.org' ) ) );

        $result = PersonalData::export( 'ANA@example.org', 1 );

        self::assertTrue( $result['done'] );
        self::assertCount( 1, $result['data'] );
        self::assertSame( 'total-mail-queue-log-' . $own, $result['data'][0]['item_id'] );
        $values = array_column( $result['data'][0]['data'], 'value', 'name' );
        self::assertSame( 'Welcome', $values['Subject'] );
        self::assertSame( 'sent', $values['Status'] );
        self::assertSame( "Hello Ana\nSee you", $values['Message'] );
    }

    public function test_export_withholds_the_body_of_a_one_time_link_email(): void {
        $this->insertQueueItem( array(
            'status'     => 'sent',
            'recipient'  => wp_json_encode( 'ana@example.org' ),
            'message'    => 'Reset: https://example.org/wp-login.php?key=secret',
            'source_key' => 'wp_core:password_reset',
        ) );

        $result = PersonalData::export( 'ana@example.org', 1 );
        $values = array_column( $result['data'][0]['data'], 'value', 'name' );

        self::assertStringNotContainsString( 'secret', $values['Message'] );
        self::assertSame( 'Withheld: this email carries a one-time account link.', $values['Message'] );
    }

    public function test_export_pages_until_the_rows_run_out(): void {
        for ( $i = 0; $i < PersonalData::PAGE + 3; $i++ ) {
            $this->insertQueueItem( array( 'status' => 'sent', 'recipient' => wp_json_encode( 'ana@example.org' ) ) );
        }

        $first  = PersonalData::export( 'ana@example.org', 1 );
        $second = PersonalData::export( 'ana@example.org', 2 );

        self::assertFalse( $first['done'] );
        self::assertCount( PersonalData::PAGE, $first['data'] );
        self::assertTrue( $second['done'] );
        self::assertCount( 3, $second['data'] );
    }

    public function test_erasure_deletes_sent_failed_and_alert_rows_and_keeps_pending_ones(): void {
        foreach ( array( 'sent', 'error', 'alert', 'queue', 'high' ) as $status ) {
            $this->insertQueueItem( array( 'status' => $status, 'recipient' => wp_json_encode( 'ana@example.org' ) ) );
        }
        $other = $this->insertQueueItem( array( 'status' => 'sent', 'recipient' => wp_json_encode( 'joana@example.org' ) ) );

        $result = PersonalData::erase( 'ana@example.org', 1 );

        self::assertTrue( $result['done'] );
        self::assertTrue( $result['items_removed'] );
        self::assertTrue( $result['items_retained'] );
        self::assertCount( 1, $result['messages'] );
        self::assertStringContainsString( '2 emails', $result['messages'][0] );
        self::assertSame( 2, $this->rows( "`status` IN ('queue','high')" ) );
        self::assertSame( 0, $this->rows( "`status` IN ('sent','error','alert') AND `id` <> $other" ) );
        self::assertSame( 1, $this->rows( "`id` = $other" ), 'Another address is never touched.' );
    }

    public function test_erasure_works_in_batches_and_always_ends(): void {
        for ( $i = 0; $i < PersonalData::PAGE + 5; $i++ ) {
            $this->insertQueueItem( array( 'status' => 'sent', 'recipient' => wp_json_encode( 'ana@example.org' ) ) );
        }
        // Rows the LIKE finds but the address check rejects are read, never deleted.
        for ( $i = 0; $i < PersonalData::PAGE + 5; $i++ ) {
            $this->insertQueueItem( array( 'status' => 'sent', 'recipient' => wp_json_encode( 'joana@example.org' ) ) );
        }

        $first = PersonalData::erase( 'ana@example.org', 1 );
        self::assertFalse( $first['done'] );
        self::assertTrue( $first['items_removed'] );

        $second = PersonalData::erase( 'ana@example.org', 2 );
        self::assertTrue( $second['done'] );

        $third = PersonalData::erase( 'ana@example.org', 3 );
        self::assertTrue( $third['done'] );
        self::assertFalse( $third['items_removed'] );
        self::assertSame( PersonalData::PAGE + 5, $this->rows(), 'Only the other address\'s rows remain.' );
    }

    public function test_erasure_removes_the_stored_attachments(): void {
        $dir = \TotalMailQueue\Support\Paths::attachmentsDir() . '/privacy-test';
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/file.pdf', 'x' ); // phpcs:ignore -- test fixture
        $this->insertQueueItem( array(
            'status'      => 'error',
            'recipient'   => wp_json_encode( 'ana@example.org' ),
            'attachments' => wp_json_encode( array( $dir . '/file.pdf' ) ),
        ) );

        PersonalData::erase( 'ana@example.org', 1 );

        self::assertDirectoryDoesNotExist( $dir );
    }

    public function test_an_invalid_address_reaches_nothing(): void {
        $this->insertQueueItem( array( 'status' => 'sent' ) );

        self::assertSame( array(), PersonalData::export( 'not an address', 1 )['data'] );
        self::assertFalse( PersonalData::erase( '', 1 )['items_removed'] );
        self::assertSame( 1, $this->rows() );
    }
}
