<?php
/**
 * Site Health tests and debug information.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\M365SecretExpiry;
use TotalMailQueue\Smtp\Repository as SmtpRepository;

/**
 * Puts the queue's health where an administrator already looks: Tools →
 * Site Health (#182).
 *
 * Four direct tests, each the same check a screen of the plugin already
 * makes, so the two can never disagree:
 * - the worker is running ({@see Notices::isWorkerStalled()}, the admin
 *   notice's own rule);
 * - the queue is under the alert threshold of Settings → Alerts;
 * - with "Plugin SMTP only", an account can take the waiting mail
 *   ({@see SmtpRepository::blockSummary()}, the Retention tab's line);
 * - no SMTP account signs in to Microsoft 365 with a password, which
 *   Microsoft is retiring for SMTP.
 *
 * And a section in the Info tab, which a support request copies.
 */
final class SiteHealth {

	/**
	 * Hosts of Microsoft 365's SMTP submission service.
	 */
	private const M365_HOSTS = array( 'smtp.office365.com', 'smtp-legacy.office365.com', 'outlook.office365.com' );

	/**
	 * Hook the tests and the debug section.
	 *
	 * Both are WordPress's own registry filters for Site Health; the default
	 * priority is enough, because the order of the tests changes nothing.
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', array( self::class, 'addTests' ) );
		add_filter( 'debug_information', array( self::class, 'addDebugInfo' ) );
	}

	/**
	 * `site_status_tests` filter.
	 *
	 * @param array<string,array<string,mixed>> $tests Registered tests.
	 * @return array<string,array<string,mixed>>
	 */
	public static function addTests( $tests ): array {
		$tests           = (array) $tests;
		$tests['direct'] = (array) ( $tests['direct'] ?? array() );
		foreach ( array(
			'tmq_worker'       => array( __( 'Email queue worker', 'total-mail-queue' ), 'testWorker' ),
			'tmq_backlog'      => array( __( 'Email queue size', 'total-mail-queue' ), 'testBacklog' ),
			'tmq_smtp_waiting' => array( __( 'SMTP account for the queue', 'total-mail-queue' ), 'testSmtpWaiting' ),
			'tmq_m365_auth'    => array( __( 'Microsoft 365 SMTP sign-in', 'total-mail-queue' ), 'testM365Auth' ),
		) as $id => $spec ) {
			$tests['direct'][ $id ] = array(
				'label' => $spec[0],
				'test'  => array( self::class, $spec[1] ),
			);
		}
		return $tests;
	}

	/**
	 * The worker sends the queue.
	 *
	 * @return array<string,mixed>
	 */
	public static function testWorker(): array {
		$options = Options::get();
		$next    = wp_next_scheduled( Scheduler::HOOK );
		$stalled = '1' === (string) $options['enabled'] && Notices::isWorkerStalled(
			time(),
			QueueRepository::pendingCount(),
			false === $next ? null : (int) $next,
			(int) $options['queue_interval'],
			Diagnostics::lastSendTimestamp()
		);
		if ( ! $stalled ) {
			return self::result( 'tmq_worker', 'good', __( 'The email queue is being sent', 'total-mail-queue' ), __( 'The worker that sends the queue ran on time, or nothing is waiting.', 'total-mail-queue' ) );
		}
		return self::result(
			'tmq_worker',
			'critical',
			__( 'Emails are waiting and the queue is not being sent', 'total-mail-queue' ),
			__( 'Nothing has been sent for more than two hours while emails wait, and the worker is not scheduled to run. WP-Cron runs only when the site gets visits, so on a quiet or cached site it may barely run. Add a server cron job that calls wp-cron.php; the plugin\'s Cron tab shows the line for this server.', 'total-mail-queue' ),
			PluginPage::TAB_CRON_INFO
		);
	}

	/**
	 * The queue is under the alert threshold.
	 *
	 * @return array<string,mixed>
	 */
	public static function testBacklog(): array {
		$options   = Options::get();
		$pending   = QueueRepository::pendingCount();
		$threshold = max( 1, (int) $options['email_amount'] );
		if ( '1' !== (string) $options['enabled'] || $pending <= $threshold ) {
			return self::result( 'tmq_backlog', 'good', __( 'The email queue is within its usual size', 'total-mail-queue' ), __( 'The number of waiting emails is not above the alert threshold set in the plugin\'s settings.', 'total-mail-queue' ) );
		}
		return self::result(
			'tmq_backlog',
			'recommended',
			__( 'The email queue is larger than usual', 'total-mail-queue' ),
			sprintf(
				/* translators: 1: emails waiting, 2: alert threshold */
				__( '%1$d emails are waiting, above the alert threshold of %2$d. A sudden flood may be a form abused by a bot; check the Retention tab for what is waiting and where it comes from.', 'total-mail-queue' ),
				$pending,
				$threshold
			),
			PluginPage::TAB_QUEUE
		);
	}

	/**
	 * With "Plugin SMTP only", an account can take the waiting mail.
	 *
	 * @return array<string,mixed>
	 */
	public static function testSmtpWaiting(): array {
		$options = Options::get();
		if ( '1' !== (string) $options['enabled'] || 'smtp' !== (string) $options['send_method'] || QueueRepository::pendingCount() <= 0 || array() !== SmtpRepository::available() ) {
			return self::result( 'tmq_smtp_waiting', 'good', __( 'An SMTP account can send the queue', 'total-mail-queue' ), __( 'Either the queue does not depend on the plugin\'s SMTP accounts, or one of them still has room within its limits.', 'total-mail-queue' ) );
		}
		return self::result(
			'tmq_smtp_waiting',
			'recommended',
			__( 'Emails wait for an SMTP account with room in its limits', 'total-mail-queue' ),
			SmtpRepository::blockSummary( (int) $options['queue_interval'] ),
			PluginPage::TAB_SMTP
		);
	}

	/**
	 * No account signs in to Microsoft 365 SMTP with a password, and no app
	 * account's client secret is expired or about to be (#188).
	 *
	 * An expired secret is critical, because those emails are not going out;
	 * one within M365SecretExpiry::WARN_DAYS, or password sign-in, is
	 * recommended. The description names every account involved.
	 *
	 * @return array<string,mixed>
	 */
	public static function testM365Auth(): array {
		$names   = self::m365PasswordAccounts();
		$secrets = M365SecretExpiry::accounts( time() );
		if ( array() === $names && array() === $secrets ) {
			return self::result(
				'tmq_m365_auth',
				'good',
				__( 'No SMTP account depends on Microsoft 365 password sign-in', 'total-mail-queue' ),
				sprintf(
				/* translators: %d: days of warning */
					__( 'None of the plugin\'s enabled SMTP accounts signs in to Microsoft 365 with a password, and no app\'s client secret expires within %d days.', 'total-mail-queue' ),
					M365SecretExpiry::WARN_DAYS
				)
			);
		}

		$parts   = array();
		$expired = array();
		$soon    = array();
		foreach ( $secrets as $account ) {
			$entry = sprintf(
				/* translators: 1: account name, 2: date */
				__( '%1$s (%2$s)', 'total-mail-queue' ),
				$account['name'],
				M365SecretExpiry::displayDate( $account['date'] )
			);
			if ( M365SecretExpiry::EXPIRED === $account['state'] ) {
				$expired[] = $entry;
			} else {
				$soon[] = $entry;
			}
		}
		if ( array() !== $expired ) {
			/* translators: %s: comma-separated accounts with their dates */
			$parts[] = sprintf( __( 'The client secret of a Microsoft 365 app has expired, and Microsoft refuses the sign-in until a new one is saved. Accounts affected: %s.', 'total-mail-queue' ), implode( ', ', $expired ) );
		}
		if ( array() !== $soon ) {
			/* translators: 1: days of warning, 2: comma-separated accounts with their dates */
			$parts[] = sprintf( __( 'The client secret of a Microsoft 365 app expires within %1$d days: create a new one in Microsoft Entra and save it in the account before then. Accounts affected: %2$s.', 'total-mail-queue' ), M365SecretExpiry::WARN_DAYS, implode( ', ', $soon ) );
		}
		if ( array() !== $names ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated account names */
				__( 'Microsoft is retiring password sign-in (Basic authentication) for SMTP in Exchange Online, and an account that uses it stops sending once it is turned off for your tenant. Accounts affected: %s.', 'total-mail-queue' ),
				implode( ', ', $names )
			);
		}

		if ( array() !== $expired ) {
			$status = 'critical';
			$label  = __( 'A Microsoft 365 app\'s client secret has expired', 'total-mail-queue' );
		} elseif ( array() !== $soon ) {
			$status = 'recommended';
			$label  = __( 'A Microsoft 365 app\'s client secret expires soon', 'total-mail-queue' );
		} else {
			$status = 'recommended';
			$label  = __( 'An SMTP account signs in to Microsoft 365 with a password', 'total-mail-queue' );
		}
		return self::result( 'tmq_m365_auth', $status, $label, implode( ' ', $parts ), PluginPage::TAB_SMTP );
	}

	/**
	 * Names of the enabled accounts that sign in to Microsoft 365 with a
	 * password.
	 *
	 * @return list<string>
	 */
	public static function m365PasswordAccounts(): array {
		$names = array();
		foreach ( SmtpRepository::all() as $account ) {
			$host = strtolower( trim( (string) ( $account['host'] ?? '' ) ) );
			if ( empty( $account['enabled'] ) || empty( $account['auth'] ) || ! in_array( $host, self::M365_HOSTS, true ) ) {
				continue;
			}
			// The Microsoft 365 app signs in with a token, not a password (#186).
			if ( \TotalMailQueue\Smtp\M365Token::AUTH_TYPE === (string) ( $account['auth_type'] ?? '' ) ) {
				continue;
			}
			$names[] = (string) $account['name'];
		}
		return $names;
	}

	/**
	 * `debug_information` filter: the plugin's section in Site Health → Info.
	 *
	 * @param array<string,array<string,mixed>> $info Sections.
	 * @return array<string,array<string,mixed>>
	 */
	public static function addDebugInfo( $info ): array {
		$info    = (array) $info;
		$options = Options::get();
		$modes   = array(
			'0' => __( 'Disabled', 'total-mail-queue' ),
			'1' => __( 'Queue', 'total-mail-queue' ),
			'2' => __( 'Block', 'total-mail-queue' ),
		);
		$last    = Diagnostics::lastSendTimestamp();
		$next    = wp_next_scheduled( Scheduler::HOOK );

		$info['total-mail-queue'] = array(
			'label'  => __( 'Total Mail Queue', 'total-mail-queue' ),
			'fields' => array(
				'mode'          => array(
					'label' => __( 'Operation mode', 'total-mail-queue' ),
					'value' => $modes[ (string) $options['enabled'] ] ?? (string) $options['enabled'],
				),
				'send_method'   => array(
					'label' => __( 'Send method', 'total-mail-queue' ),
					'value' => (string) $options['send_method'],
				),
				'interval'      => array(
					'label' => __( 'Queue interval (seconds)', 'total-mail-queue' ),
					'value' => (int) $options['queue_interval'],
				),
				'pending'       => array(
					'label' => __( 'Emails waiting', 'total-mail-queue' ),
					'value' => QueueRepository::pendingCount(),
				),
				'last_send'     => array(
					'label' => __( 'Last send', 'total-mail-queue' ),
					'value' => $last > 0 ? gmdate( 'Y-m-d H:i:s', $last ) . ' UTC' : __( 'Never', 'total-mail-queue' ),
				),
				'next_run'      => array(
					'label' => __( 'Next worker run', 'total-mail-queue' ),
					'value' => false === $next ? __( 'Not scheduled', 'total-mail-queue' ) : gmdate( 'Y-m-d H:i:s', (int) $next ) . ' UTC',
				),
				'smtp_accounts' => array(
					'label' => __( 'SMTP accounts', 'total-mail-queue' ),
					'value' => count( SmtpRepository::all() ),
				),
				'wp_cron'       => array(
					'label' => __( 'WP-Cron disabled', 'total-mail-queue' ),
					'value' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Yes', 'total-mail-queue' ) : __( 'No', 'total-mail-queue' ),
					'debug' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'yes' : 'no',
				),
			),
		);
		return $info;
	}

	/**
	 * One test result in the shape Site Health expects.
	 *
	 * @param string $test        Test id.
	 * @param string $status      good, recommended or critical.
	 * @param string $label       Headline.
	 * @param string $description What it means and what to do.
	 * @param string $tab         Plugin tab to link to, or '' for none.
	 * @return array<string,mixed>
	 */
	private static function result( string $test, string $status, string $label, string $description, string $tab = '' ): array {
		$actions = '';
		if ( '' !== $tab ) {
			$actions = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . $tab ) ),
				esc_html__( 'Open Total Mail Queue', 'total-mail-queue' )
			);
		}
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Total Mail Queue', 'total-mail-queue' ),
				'color' => 'critical' === $status ? 'red' : 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => $actions,
			'test'        => $test,
		);
	}
}
