<?php
/**
 * `pre_wp_mail` filter: redirect outgoing mail into the queue.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\PhpMailerCapturer;
use TotalMailQueue\Sources\CoreTemplates;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use TotalMailQueue\Support\Serializer;
use TotalMailQueue\Templates\Engine as TemplateEngine;
use TotalMailQueue\Templates\Tokens;

/**
 * Backbone of the queue: hooks `pre_wp_mail` at very high priority so it
 * runs after any third-party filter that might short-circuit the send,
 * and either inserts the email into the queue table for the cron to send
 * later or — for "instant" priority headers — records the row id and
 * lets `wp_mail()` proceed normally.
 *
 * Cron-context behavior: the filter stays registered during cron requests so
 * outgoing mail from other plugins' scheduled events (ffcertificate-style
 * deferred user notifications, WooCommerce abandoned-cart, etc.) lands in
 * the queue / log just like in a frontend request. The plugin's own
 * {@see BatchProcessor::run()} flips a `draining` flag while it is sending
 * queued rows, and {@see handle()} short-circuits when that flag is true so
 * we do not re-queue what we are already sending.
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
	 * (`enabled=2`) modes. No-op when disabled (`enabled=0`).
	 *
	 * Cron requests register too — see the class docblock for why.
	 */
	public static function register(): void {
		$options = Options::get();
		if ( ! in_array( $options['enabled'], array( '1', '2' ), true ) ) {
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

		// Defensive: if our own cron drainer is currently sending a queued
		// row, do not re-queue it. {@see BatchProcessor::dispatchOnce()}
		// already strips every `pre_wp_mail` filter before each send, so this
		// branch is belt-and-suspenders for any path that calls wp_mail()
		// during the drain without going through dispatchOnce (e.g.
		// {@see \TotalMailQueue\Cron\AlertSender}).
		if ( BatchProcessor::isDraining() ) {
			return null;
		}

		$options = Options::get();

		// Resolve the source: prefer the marker a primary listener set; fall
		// back to the call stack. The source decides two things: which
		// `source_key` to persist, and whether the admin has disabled this
		// source — in which case the message is stored as
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

		// wp_core template override pipeline (v2.6.0). When the source is
		// one of the wp_core templates the admin can edit AND a non-empty
		// override is saved, replace the subject / body with the
		// admin-supplied template before tokens are substituted. The
		// `skip_template_wrap` flag bypasses the global HTML envelope for
		// this specific source.
		$skip_template_wrap = self::applyCoreTemplateOverride( $source['key'], $to, $subject, $message );

		// Apply the HTML template wrapper before the row hits the queue, so
		// the persisted message is the same byte string the recipient will
		// see. Skipped for `instant` (wp_mail() proceeds normally and the
		// engine wraps via the `wp_mail` filter), `blocked_by_source` (no
		// point — the row is never sent), and per-source skip_template_wrap
		// (admin chose to keep this template raw).
		if ( ! $skip_template_wrap && ( 'queue' === $status || 'high' === $status ) ) {
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
	 * Apply the wp_core template override step: when the source resolves to one
	 * of the wp_core templates and a row exists with non-empty subject/body
	 * overrides, render those overrides into the outgoing $subject / $message
	 * in place. Returns the row's `skip_template_wrap` flag so the caller knows
	 * whether to bypass the global HTML envelope further down the pipeline.
	 *
	 * Pulled out of {@see handle()} to keep the orchestrator linear — the
	 * conditional nest here was four levels deep before the extraction.
	 *
	 * @param string $source_key Resolved source key (e.g. `wp_core:password_reset`).
	 * @param mixed  $to         wp_mail() recipient(s) — passed through to token globals.
	 * @param string $subject    Outgoing subject — modified in place.
	 * @param string $message    Outgoing message body — modified in place.
	 * @return bool Whether the source has `skip_template_wrap=1` (caller bypasses the wrapper).
	 */
	private static function applyCoreTemplateOverride( string $source_key, $to, string &$subject, string &$message ): bool {
		if ( ! CoreTemplates::isCoreTemplate( $source_key ) ) {
			return false;
		}
		$row = SourcesRepository::findByKey( $source_key );
		if ( null === $row ) {
			return false;
		}

		$context          = Detector::consumeData();
		$subject_override = isset( $row['subject_override'] ) ? (string) $row['subject_override'] : '';
		$body_override    = isset( $row['body_override'] ) ? (string) $row['body_override'] : '';
		if ( '' !== $subject_override || '' !== $body_override ) {
			$args_for_tokens = array(
				'to'               => $to,
				'subject'          => $subject,
				'message'          => $message,
				'message_original' => $message,
			);
			if ( '' !== $subject_override ) {
				$subject = self::renderOverride( $subject_override, $args_for_tokens, $context );
			}
			if ( '' !== $body_override ) {
				$message = self::renderOverride( $body_override, $args_for_tokens, $context );
			}
		}

		return ! empty( $row['skip_template_wrap'] );
	}

	/**
	 * Substitute `{token}` placeholders in an admin-supplied wp_core
	 * template override. Globals come from {@see Tokens::globals()};
	 * `$extra_context` carries per-call dynamic values that
	 * {@see Detector::consumeData()} stashed (username, reset_url,
	 * etc.). Per-call values win over globals so a template can rely
	 * on the most specific source for each token.
	 *
	 * Implemented inline (not via Tokens::replace) so the per-call
	 * context lives in this single pipeline step without leaking to
	 * the global `wp_tmq_template_tokens` filter scope.
	 *
	 * @param string               $template      User-supplied template body or subject.
	 * @param array<string,mixed>  $args          wp_mail args (drives globals).
	 * @param array<string,string> $extra_context Per-call dynamic values from Detector.
	 * @return string
	 */
	private static function renderOverride( string $template, array $args, array $extra_context ): string {
		$tokens                     = Tokens::globals( $args );
		$tokens['subject']          = isset( $args['subject'] ) ? (string) $args['subject'] : '';
		$tokens['message_original'] = isset( $args['message_original'] ) ? (string) $args['message_original'] : '';
		// Per-call context wins (e.g. {username} from the listener
		// over the empty global default).
		foreach ( $extra_context as $name => $value ) {
			$tokens[ (string) $name ] = (string) $value;
		}

		$search  = array();
		$replace = array();
		foreach ( $tokens as $name => $value ) {
			$search[]  = '{' . $name . '}';
			$replace[] = $value;
		}
		return str_replace( $search, $replace, $template );
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
