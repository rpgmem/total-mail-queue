<?php
/**
 * The queue's single priority scale.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

/**
 * One numeric axis governs how soon a queued message is sent. It unifies the
 * two urgency signals the plugin used to track separately — the
 * `X-Mail-Queue-Prio: High` header and the (previously binary) `high` row
 * status — with the per-source priority an admin configures on the Sources
 * tab.
 *
 * Lower number = more urgent, matching the SMTP-account "priority" field
 * ("lower number = higher priority"). {@see MIN} (1) is the most urgent a row
 * can be; {@see NORMAL} (50) is the default applied to ordinary mail; the
 * queue worker drains rows in ascending priority order.
 */
final class Priority {

	/**
	 * Most urgent value an admin can assign (clamped floor).
	 */
	public const MIN = 1;

	/**
	 * Least urgent value (clamped ceiling).
	 */
	public const MAX = 100;

	/**
	 * Default priority for ordinary mail and freshly catalogued sources.
	 */
	public const NORMAL = 50;

	/**
	 * Priority assigned to messages carrying `X-Mail-Queue-Prio: High` — more
	 * urgent than normal, but still beatable by a source the admin pins below
	 * it (e.g. priority 1 for password resets).
	 */
	public const HIGH = 10;

	/**
	 * Constrain an arbitrary integer to the valid [{@see MIN}, {@see MAX}] range.
	 *
	 * @param int $priority Candidate value.
	 */
	public static function clamp( int $priority ): int {
		return max( self::MIN, min( self::MAX, $priority ) );
	}

	/**
	 * The more urgent (numerically smaller) of two priorities.
	 *
	 * @param int $a First priority.
	 * @param int $b Second priority.
	 */
	public static function mostUrgent( int $a, int $b ): int {
		return min( $a, $b );
	}
}
