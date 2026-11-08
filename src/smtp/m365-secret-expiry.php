<?php
/**
 * When a Microsoft 365 app's client secret runs out.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

/**
 * Reads the date an administrator gave for the end of an app account's
 * client secret (#188), so the plugin can warn before Microsoft refuses the
 * sign-in and say so plainly after.
 *
 * The date is a calendar day with no time zone (`DATE`, what Microsoft
 * Entra shows under "Expires"): the secret counts as valid until the end of
 * that day in the site's time zone. Accounts with no date, disabled
 * accounts and password accounts are never reported.
 */
final class M365SecretExpiry {

	/**
	 * Days before the date at which the warning starts.
	 */
	public const WARN_DAYS = 30;

	/**
	 * The secret is still valid but ends within WARN_DAYS.
	 */
	public const SOON = 'soon';

	/**
	 * The date has passed.
	 */
	public const EXPIRED = 'expired';

	/**
	 * A stored or typed date as `Y-m-d`, or '' when it is not a real day.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitizeDate( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Where one account's secret stands, or null when there is nothing to say.
	 *
	 * @param array<string,mixed> $account Row from the SMTP accounts table.
	 * @param int                 $now     Unix time.
	 * @return array{state: string, date: string, days: int}|null `days` is
	 *         what is left (0 on the last day) or, once expired, how many days ago.
	 */
	public static function status( array $account, int $now ): ?array {
		if ( empty( $account['enabled'] ) || empty( $account['auth'] ) || M365Token::AUTH_TYPE !== (string) ( $account['auth_type'] ?? '' ) ) {
			return null;
		}
		$date = self::sanitizeDate( $account['oauth_secret_expires'] ?? '' );
		if ( '' === $date ) {
			return null;
		}
		$zone  = wp_timezone();
		$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $zone )->setTime( 0, 0 );
		$ends  = new \DateTimeImmutable( $date, $zone );
		$left  = (int) $today->diff( $ends )->format( '%r%a' );
		if ( $left < 0 ) {
			return array(
				'state' => self::EXPIRED,
				'date'  => $date,
				'days'  => -$left,
			);
		}
		if ( $left <= self::WARN_DAYS ) {
			return array(
				'state' => self::SOON,
				'date'  => $date,
				'days'  => $left,
			);
		}
		return null;
	}

	/**
	 * Every account with something to say, expired first.
	 *
	 * @param int $now Unix time.
	 * @return list<array{name: string, state: string, date: string, days: int}>
	 */
	public static function accounts( int $now ): array {
		$found = array();
		foreach ( Repository::all() as $account ) {
			$status = self::status( $account, $now );
			if ( null !== $status ) {
				$found[] = array( 'name' => (string) $account['name'] ) + $status;
			}
		}
		usort(
			$found,
			static function ( array $a, array $b ): int {
				return array( self::EXPIRED === $a['state'] ? 0 : 1, $a['date'] ) <=> array( self::EXPIRED === $b['state'] ? 0 : 1, $b['date'] );
			}
		);
		return $found;
	}

	/**
	 * The date in the site's own format.
	 *
	 * @param string $date `Y-m-d`.
	 */
	public static function displayDate( string $date ): string {
		$day = new \DateTimeImmutable( $date, wp_timezone() );
		return (string) wp_date( (string) get_option( 'date_format' ), $day->getTimestamp() );
	}

	/**
	 * The note beside the date field: "Expires in 12 days", "Expired 3 days ago".
	 *
	 * @param array{state: string, date: string, days: int} $status From status().
	 */
	public static function note( array $status ): string {
		if ( self::EXPIRED === $status['state'] ) {
			/* translators: %d: days since the secret expired */
			return sprintf( _n( 'Expired %d day ago', 'Expired %d days ago', $status['days'], 'total-mail-queue' ), $status['days'] );
		}
		if ( 0 === $status['days'] ) {
			return __( 'Expires today', 'total-mail-queue' );
		}
		/* translators: %d: days left */
		return sprintf( _n( 'Expires in %d day', 'Expires in %d days', $status['days'], 'total-mail-queue' ), $status['days'] );
	}

	/**
	 * A short line for one status: "Secret expires in 12 days", "Secret expired".
	 *
	 * @param array{state: string, date: string, days: int} $status From status().
	 */
	public static function label( array $status ): string {
		if ( self::EXPIRED === $status['state'] ) {
			return __( 'Secret expired', 'total-mail-queue' );
		}
		if ( 0 === $status['days'] ) {
			return __( 'Secret expires today', 'total-mail-queue' );
		}
		/* translators: %d: days left */
		return sprintf( _n( 'Secret expires in %d day', 'Secret expires in %d days', $status['days'], 'total-mail-queue' ), $status['days'] );
	}
}
