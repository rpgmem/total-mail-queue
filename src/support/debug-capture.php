<?php
/**
 * In-process buffer for PHPMailer's SMTP debug output.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

/**
 * Collects the SMTP conversation PHPMailer emits while a single email is
 * being sent, so the failure handler can attach it to that row's error log.
 *
 * When the `smtp_debug` setting is on, {@see \TotalMailQueue\Smtp\Configurator}
 * points PHPMailer's `Debugoutput` at {@see DebugCapture::append()} and the
 * cron worker calls {@see DebugCapture::reset()} before each send so one row's
 * transcript never bleeds into the next. The lifecycle is strictly synchronous
 * (one send at a time per request), so a static buffer is sufficient — it
 * mirrors {@see \TotalMailQueue\Queue\Tracker}.
 */
final class DebugCapture {

	/**
	 * Accumulated debug lines for the send currently in flight.
	 *
	 * @var string
	 */
	private static string $buffer = '';

	/**
	 * Append one chunk of PHPMailer debug output. PHPMailer calls its
	 * `Debugoutput` callable with `($str, $level)`; the wrapper in
	 * {@see \TotalMailQueue\Smtp\Configurator} drops the level and forwards
	 * only the line, which is kept verbatim.
	 *
	 * @param string $str The debug line PHPMailer produced.
	 */
	public static function append( string $str ): void {
		self::$buffer .= rtrim( $str, "\r\n" ) . "\n";
	}

	/**
	 * Read the buffered transcript for the in-flight send (empty when nothing
	 * was captured).
	 */
	public static function get(): string {
		return self::$buffer;
	}

	/**
	 * Clear the buffer. Called before each send and between tests.
	 */
	public static function reset(): void {
		self::$buffer = '';
	}
}
