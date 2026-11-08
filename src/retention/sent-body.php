<?php
/**
 * Keep only the metadata of a sent email.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Retention;

use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;

/**
 * When the "Message after sending" setting is on (`log_drop_body`), a row
 * that reaches `sent` loses its message, its headers (which can name Cc and
 * Bcc addresses) and its attachment list, and its info says so (#180). The
 * date, status, recipients and subject stay, so the Log still answers "was
 * it sent, to whom, when".
 *
 * Only `sent` rows are touched: a failed email keeps its message so it can
 * be resent, and the Log never resends a sent one. The note goes into the
 * row itself, not into a check of the setting at display time, so turning
 * the setting off later never makes an emptied row look like an empty email.
 */
final class SentBody {

	/**
	 * Empty the row's message if the setting asks for it.
	 *
	 * Called by both paths that mark a row sent: the batch processor and the
	 * instant-send success handler.
	 *
	 * @param int $id Row that was just marked sent.
	 * @return bool Whether the row was emptied.
	 */
	public static function afterSent( int $id ): bool {
		if ( '1' !== (string) Options::get()['log_drop_body'] ) {
			return false;
		}
		QueueRepository::update(
			$id,
			array(
				'message'     => '',
				'headers'     => '',
				'attachments' => '',
				'info'        => __( 'Message deleted after sending, as the Log settings ask.', 'total-mail-queue' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
		return true;
	}
}
