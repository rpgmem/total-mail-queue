<?php
/**
 * Coordinated reset for the plugin's per-request static state.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Queue\Tracker;
use TotalMailQueue\Smtp\PhpMailerCapturer;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Templates\WooCommerceTokens;

/**
 * Single entry point for clearing every "per-request scratch" static the
 * plugin relies on between phases of a single request (or between PHPUnit
 * tests). Each class still owns its own `reset()` — this just bundles them
 * so test base classes don't have to remember the catalog and stay in sync
 * as new statics are introduced.
 *
 * What is reset:
 * - {@see BatchProcessor}      — invocation guard + drainer flag.
 * - {@see Detector}            — current source marker + per-call context
 *                               + listener-registration idempotency guard.
 * - {@see PhpMailerCapturer}   — the `$capturing` flag (in case a capture
 *                               threw mid-flight and left it stuck on).
 * - {@see Tracker}             — the in-flight "instant" mail row id.
 * - {@see WooCommerceTokens}   — the captured order context.
 *
 * What is NOT reset:
 * - {@see \TotalMailQueue\Plugin}'s container — set once at boot, kept
 *   for the process's lifetime.
 * - {@see Paths} memoized lookups — bootstrap-time cache, not per-request.
 */
final class RuntimeState {

	/**
	 * Reset every per-request static slot. Idempotent — safe to call from
	 * test setUp(), tearDown(), or both.
	 */
	public static function reset(): void {
		BatchProcessor::reset();
		Detector::reset();
		PhpMailerCapturer::reset();
		Tracker::reset();
		WooCommerceTokens::reset();
	}
}
