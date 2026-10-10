<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Admin\LogColumns;
use TotalMailQueue\Admin\Tables\LogTable;

/**
 * Screen Options column toggles for the Log and Retention tabs.
 *
 * @covers \TotalMailQueue\Admin\LogColumns
 * @covers \TotalMailQueue\Admin\Tables\LogTable::columnsFor
 */
final class LogColumnsTest extends IntegrationTestCase {

    private static function screen( string $id ): object {
        return (object) array( 'id' => $id );
    }

    public function test_only_the_log_and_retention_screens_have_a_mode(): void {
        self::assertSame( LogTable::MODE_LOG, LogColumns::modeForScreen( self::screen( 'total-mail-queue_page_wp_tmq_mail_queue-tab-log' ) ) );
        self::assertSame( LogTable::MODE_QUEUE, LogColumns::modeForScreen( self::screen( 'total-mail-queue_page_wp_tmq_mail_queue-tab-queue' ) ) );
        self::assertSame( '', LogColumns::modeForScreen( self::screen( 'total-mail-queue_page_wp_tmq_mail_queue-tab-smtp' ) ) );
        self::assertSame( '', LogColumns::modeForScreen( self::screen( 'edit-post' ) ) );
        self::assertSame( '', LogColumns::modeForScreen( null ) );
    }

    public function test_our_screens_hide_the_wide_columns_by_default_and_others_are_untouched(): void {
        self::assertSame(
            array( 'headers', 'attachments' ),
            LogColumns::defaultHiddenColumns( array(), self::screen( 'total-mail-queue_page_wp_tmq_mail_queue-tab-log' ) )
        );
        self::assertSame(
            array( 'author' ),
            LogColumns::defaultHiddenColumns( array( 'author' ), self::screen( 'edit-post' ) )
        );
    }

    public function test_every_default_hidden_column_exists_in_both_modes(): void {
        foreach ( array( LogTable::MODE_LOG, LogTable::MODE_QUEUE ) as $mode ) {
            $columns = LogTable::columnsFor( $mode );
            foreach ( LogColumns::DEFAULT_HIDDEN as $key ) {
                self::assertArrayHasKey( $key, $columns, "{$mode} has no {$key} column." );
            }
        }
    }

    public function test_the_screen_lists_the_columns_of_its_own_mode(): void {
        $filters = array();
        Functions\when( 'add_filter' )->alias(
            static function ( $tag, $cb ) use ( &$filters ) {
                $filters[ $tag ] = $cb;
                return true;
            }
        );

        LogColumns::registerScreenColumns( self::screen( 'total-mail-queue_page_wp_tmq_mail_queue-tab-queue' ) );
        LogColumns::registerScreenColumns( self::screen( 'edit-post' ) );

        self::assertSame( array( 'manage_total-mail-queue_page_wp_tmq_mail_queue-tab-queue_columns' ), array_keys( $filters ) );
        $columns = ( $filters['manage_total-mail-queue_page_wp_tmq_mail_queue-tab-queue_columns'] )();
        self::assertSame( LogTable::columnsFor( LogTable::MODE_QUEUE ), $columns );
        self::assertArrayNotHasKey( 'smtp_account_id', $columns, 'Retention has no SMTP column.' );
    }
}
