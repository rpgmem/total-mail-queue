<?php
/**
 * Screen Options column toggles for the Log and Retention tabs.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Admin\Tables\LogTable;

/**
 * Lets each user choose which Log and Retention columns they see, through
 * WordPress's own Screen Options panel.
 *
 * WordPress does the rest: it stores each user's choice in the
 * `manage{screen}columnshidden` user option, and core's script toggles the
 * columns and saves the choice. This class only lists the columns and picks
 * the defaults. It is separate from {@see LogTable} so that boot does not load
 * the list-table class on every request: the table loads only inside the
 * column filter, on the two screens that show it.
 */
final class LogColumns {

	/**
	 * Columns hidden on a first visit, before the user has chosen any.
	 *
	 * Headers and attachments are the widest columns and the least often
	 * read; both stay one click away in Screen Options.
	 */
	public const DEFAULT_HIDDEN = array( 'headers', 'attachments' );

	/**
	 * Hook the column list and the defaults.
	 *
	 * `current_screen` is the first hook where the screen id is known and the
	 * last one before WordPress builds the Screen Options panel, which reads
	 * the `manage_{screen}_columns` filter.
	 */
	public static function register(): void {
		add_action( 'current_screen', array( self::class, 'registerScreenColumns' ) );
		add_filter( 'default_hidden_columns', array( self::class, 'defaultHiddenColumns' ), 10, 2 );
	}

	/**
	 * The table mode a screen shows, or '' when it is not one of ours.
	 *
	 * @param mixed $screen A WP_Screen (anything with an `id`).
	 */
	public static function modeForScreen( $screen ): string {
		$id = is_object( $screen ) && isset( $screen->id ) ? (string) $screen->id : '';
		if ( str_ends_with( $id, '_page_' . PluginPage::TAB_LOG ) ) {
			return LogTable::MODE_LOG;
		}
		if ( str_ends_with( $id, '_page_' . PluginPage::TAB_QUEUE ) ) {
			return LogTable::MODE_QUEUE;
		}
		return '';
	}

	/**
	 * `current_screen` callback: list the columns for Screen Options.
	 *
	 * @param mixed $screen The current WP_Screen.
	 */
	public static function registerScreenColumns( $screen ): void {
		$mode = self::modeForScreen( $screen );
		if ( '' === $mode ) {
			return;
		}
		add_filter(
			'manage_' . $screen->id . '_columns',
			static function () use ( $mode ): array {
				return LogTable::columnsFor( $mode );
			}
		);
	}

	/**
	 * `default_hidden_columns` callback.
	 *
	 * @param mixed $hidden Hidden column keys WordPress proposes.
	 * @param mixed $screen The screen being rendered.
	 * @return mixed
	 */
	public static function defaultHiddenColumns( $hidden, $screen ) {
		return '' === self::modeForScreen( $screen ) ? $hidden : self::DEFAULT_HIDDEN;
	}
}
