<?php
/**
 * `pre_wp_mail` filter: redirect outgoing mail into the queue.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\PhpMailerCapturer;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use TotalMailQueue\Support\Serializer;
use TotalMailQueue\Templates\Engine as TemplateEngine;

/**
 * Backbone of the queue: hooks `pre_wp_mail` at very high priority so it
 * runs after any third-party filter that might short-circuit the send,
 * and either inserts the email into the queue table for the cron to send
 * later or — for "instant" priority headers — records the row id and
 * lets `wp_mail()` proceed normally.
 */
final class MailInterceptor {

	/**
	 * `pre_wp_mail` priority — intentionally near INT_MAX so we run
	 * after every other filter that might already have decided to
	 * short-circuit the send.
	 */
	public const FILTER_PRIORITY = 99999;

	/**
	 * Register the filter on `pre_wp_mail` for queue (`enabled=1`) and block
	 * (`enabled=2`) modes. No-op when disabled (`enabled=0`) or when the
	 * current request is a WP cron run.
	 */
	public static function register(): void {
		$options = Options::get();
		if ( ! in_array( $options['enabled'], array( '1', '2' ), true ) ) {
			return;
		}
		if ( wp_doing_cron() ) {
			return;
		}
		add_filter( 'pre_wp_mail', array( self::class, 'handle' ), self::FILTER_PRIORITY, 2 );
	}

	/**
	 * `pre_wp_mail` filter callback.
	 *
	 * @param mixed               $return Whatever an earlier filter already returned (null = nothing yet).
	 * @param array<string,mixed> $atts   wp_mail() arguments (to/subject/message/headers/attachments).
	 * @return bool|null True when the row was queued; false when the insert
	 *                   failed; null for "instant" priority (lets wp_mail()
	 *                   proceed normally) or when an earlier filter already
	 *                   returned a value.
	 */
	public static function handle( $return, $atts ) {
		if ( null !== $return ) {
			// Another pre_wp_mail filter already short-circuited.
			return $return;
		}

		$options = Options::get();

		// Resolve the source: prefer the marker a primary listener set; fall
		// back to the call stack. The source decides two things: which
		// `source_key` to persist, and (since S4) whether the admin has
		// disabled this source — in which case the message is stored as
		// `blocked_by_source` instead of being scheduled.
		$source = Detector::consume();
		if ( null === $source ) {
			$source = Detector::inferFromBacktrace();
		}

		$to          = $atts['to'];
		$subject     = $atts['subject'];
		$message     = $atts['message'];
		$headers     = $atts['headers'];
		$attachments = $atts['attachments'];
		$status      = 'queue';

		$headers = self::normaliseHeaders( $headers );
		$status  = self::scanPriorityHeaders( $headers, $status, (string) $options['enabled'], $has_content_type, $has_from );

		// Per-source enforcement: a disabled source overrides the priority
		// headers (Instant included — otherwise a third-party plugin could
		// bypass the admin's block by setting `X-Mail-Queue-Prio: Instant`).
		// System sources are always allowed (Repository::isEnabled() short-
		// circuits to true for the `total_mail_queue:` prefix).
		$blocked_by_source = ! SourcesRepository::isEnabled( $source['key'] );
		if ( $blocked_by_source ) {
			$status = 'blocked_by_source';
		}

		// Apply the HTML template wrapper before the row hits the queue, so
		// the persisted message is the same byte string the recipient will
		// see. Skipped for `instant` (wp_mail() proceeds normally and the
		// engine wraps via the `wp_mail` filter) and `blocked_by_source`
		// (no point — the row is never sent). The Engine self-gates on the
		// templates toggle and on block mode, so we can call it
		// unconditionally for the queue / high paths.
		if ( 'queue' === $status || 'high' === $status ) {
			$wrapped = TemplateEngine::apply(
				array(
					'to'          => $to,
					'subject'     => $subject,
					'message'     => $message,
					'headers'     => $headers,
					'attachments' => $attachments,
				)
			);
			if ( isset( $wrapped['message'] ) && is_string( $wrapped['message'] ) ) {
				$message = $wrapped['message'];
			}
		}

		if ( 'instant' !== $status && 'blocked_by_source' !== $status ) {
			self::backfillContentTypeHeader( $headers, $has_content_type );
			self::backfillFromHeader( $headers, $has_from );
		}

		// Snapshot any third-party SMTP config so the cron can replay it on
		// send. Skipped for blocked rows since they will never be sent.
		if ( 'blocked_by_source' !== $status ) {
			$phpmailer_config = PhpMailerCapturer::capture();
			if ( $phpmailer_config ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport-encoding the captured config so it survives as an email header
				$headers[] = 'X-TMQ-PHPMailer-Config: ' . base64_encode( Serializer::encode( $phpmailer_config ) );
			}
		}

		$data = array(
			'timestamp'   => current_time( 'mysql', false ),
			'recipient'   => Serializer::encode( $to ),
			'subject'     => $subject,
			'message'     => $message,
			'status'      => $status,
			'attachments' => '',
			'source_key'  => $source['key'],
		);
		if ( ! empty( $headers ) ) {
			$data['headers'] = Serializer::encode( $headers );
		}

		if ( ! empty( $attachments ) ) {
			$staged = AttachmentStore::stage( $attachments );
			if ( null === $staged ) {
				$data['info'] = __( 'Error: Could not store attachments', 'total-mail-queue' );
			} else {
				$data['attachments'] = Serializer::encode( $staged );
			}
		}

		$insert_id = QueueRepository::insert( $data );

		// Update the source catalog so the admin can find this row in the
		// Sources tab even if it never sent before. UNIQUE on source_key
		// makes the insert path a no-op when the row already exists.
		if ( $insert_id > 0 ) {
			$source_id = SourcesRepository::register( $source['key'], $source['label'], $source['group'] );
			if ( $source_id > 0 ) {
				SourcesRepository::markSeen( $source_id );
			}
		}

		if ( 'instant' === $status ) {
			// wp_mail() will proceed normally. Stash the row id so the
			// success/failed handlers can update it.
			Tracker::set( $insert_id );
			return null;
		}
		if ( 0 === $insert_id ) {
			return false; // No DB entry, email cannot be sent — wp_mail() returns false.
		}
		// `blocked_by_source` shares the queue/high return path: the caller
		// sees a successful enqueue, the message just never leaves the row.
		return true;
	}

	/**
	 * Coerce the wp_mail() $headers argument into an array form.
	 *
	 * @param mixed $headers Raw value from wp_mail() (string or array).
	 * @return list<string>
	 */
	private static function normaliseHeaders( $headers ): array {
		if ( ! $headers ) {
			return array();
		}
		if ( is_array( $headers ) ) {
			return array_values( $headers );
		}
		return explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
	}

	/**
	 * Scan the headers for `X-Mail-Queue-Prio: Instant|High`, strip them,
	 * and decide the row's stored status. Also tracks whether the caller
	 * supplied a `Content-Type:` or `From:` header (so we don't duplicate
	 * them when filling in defaults).
	 *
	 * @param array     $headers          Headers — modified in place.
	 * @param string    $status           Current status guess.
	 * @param string    $enabled          Plugin mode (`0`/`1`/`2`).
	 * @param bool|null $has_content_type Out param.
	 * @param bool|null $has_from         Out param.
	 * @return string Resolved status (`queue` / `high` / `instant`).
	 */
	private static function scanPriorityHeaders( array &$headers, string $status, string $enabled, ?bool &$has_content_type, ?bool &$has_from ): string {
		$has_content_type = false;
		$has_from         = false;
		foreach ( $headers as $index => $val ) {
			$val = trim( $val );
			if ( preg_match( '#^X-Mail-Queue-Prio: +Instant *$#i', $val ) ) {
				array_splice( $headers, $index, 1 );
				// In block mode, instant emails are also retained.
				$status = '2' === $enabled ? 'queue' : 'instant';
				return $status;
			}
			if ( preg_match( '#^X-Mail-Queue-Prio: +High *$#i', $val ) ) {
				array_splice( $headers, $index, 1 );
				return 'high';
			}
			if ( preg_match( '#^Content-Type:#i', $val ) ) {
				$has_content_type = true;
			} elseif ( preg_match( '#^From:#i', $val ) ) {
				$has_from = true;
			}
		}
		return $status;
	}

	/**
	 * Persist the Content-Type that wp_mail() would otherwise compute at send
	 * time — needed because cron sends drop the active filter context.
	 *
	 * @param array $headers          Headers — modified in place.
	 * @param bool  $has_content_type Whether the caller already set Content-Type.
	 */
	private static function backfillContentTypeHeader( array &$headers, bool $has_content_type ): void {
		if ( $has_content_type ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
		$content_type = apply_filters( 'wp_mail_content_type', 'text/plain' );
		if ( ! $content_type ) {
			return;
		}
		$charset = '';
		if ( false === stripos( (string) $content_type, 'multipart' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
			$charset = apply_filters( 'wp_mail_charset', get_bloginfo( 'charset' ) );
		}
		$headers[] = 'Content-Type: ' . $content_type . ( $charset ? '; charset="' . $charset . '"' : '' );
	}

	/**
	 * Persist the From header from the wp_mail_from / wp_mail_from_name
	 * filters — same rationale as Content-Type.
	 *
	 * @param array $headers  Headers — modified in place.
	 * @param bool  $has_from Whether the caller already set From.
	 */
	private static function backfillFromHeader( array &$headers, bool $has_from ): void {
		if ( $has_from ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
		$from_email = apply_filters( 'wp_mail_from', '' );
		if ( ! $from_email ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
		$from_name = apply_filters( 'wp_mail_from_name', '' );
		$headers[] = $from_name
			? 'From: ' . $from_name . ' <' . $from_email . '>'
			: 'From: ' . $from_email;
	}
}
