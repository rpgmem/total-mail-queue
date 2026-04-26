<?php
/**
 * Plugin Name:       Total Mail Queue
 * Plugin URI:        https://github.com/rpgmem/total-mail-queue
 * Description:       Take Control and improve Security of wp_mail(). Queue and log outgoing emails, and get alerted, if your website wants to send more emails than usual.
 * Version:           2.3.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Alex Meusburger
 * Author URI:        https://github.com/rpgmem
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       total-mail-queue
 *
 * This plugin is a fork of Mail Queue by WDM (https://www.webdesign-muenchen.de).
 * Original plugin: https://wordpress.org/plugins/mail-queue/
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// Composer autoloader (provides the TotalMailQueue\* namespace + dev-mode test classes).
require_once __DIR__ . '/vendor/autoload.php';

\TotalMailQueue\Plugin::boot( __FILE__ );

/*
 ***************************************************************
PLUGIN VERSION
****************************************************************
*/

$wp_tmq_version = \TotalMailQueue\Plugin::VERSION;





// Plugin settings (defaults + parsing) live in \TotalMailQueue\Settings\Options.
// The legacy $wp_tmq_options global is still populated below from Options::get()
// so callers across the procedural plugin files keep reading the same array.


// Support helpers (Encryption, Serializer, Paths, HtmlPreview) are now provided
// by the namespaced classes under TotalMailQueue\Support\, autoloaded via Composer.





/*
 ***************************************************************
Overwrite wp_mail() if Plugin enabled and no Cron is running
Modes: 1=queue (retain+send), 2=block (retain only)
****************************************************************
*/
$wp_tmq_mailid               = 0;
$wp_tmq_options              = \TotalMailQueue\Settings\Options::get(); // Get Settings.
$wp_tmq_pre_wp_mail_priority = 99999;

// Enable interception for both queue mode (1) and block mode (2).
if ( in_array( $wp_tmq_options['enabled'], array( '1', '2' ), true ) && wp_doing_cron() === false ) {
	// High priority: run late in the game to react to previous filters.
	add_filter( 'pre_wp_mail', 'wp_tmq_prewpmail', $wp_tmq_pre_wp_mail_priority, 2 );
}

// pre WP Mail Filter.
/**
 * Prewpmail.
 *
 * @since 2.3.0
 *
 * @param mixed $return Parameter description.
 * @param mixed $atts Parameter description.
 *
 * @return mixed Function output.
 */
function wp_tmq_prewpmail( $return, $atts ) {

	global $wpdb, $wp_tmq_options, $wp_tmq_mailid;

	if ( ! is_null( $return ) ) {
		// Another pre_wp_mail filter has already returned a value, so the mail is not added to the queue.
		return $return;
	}

	// Mail Variables.
	$to          = $atts['to'];
	$subject     = $atts['subject'];
	$message     = $atts['message'];
	$headers     = $atts['headers'];
	$attachments = $atts['attachments'];
	$status      = 'queue';

	// Make sure that $headers always is an array.
	if ( $headers ) {
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}
	} else {
		$headers = array();
	}

	// Loop through email headers.
	// - Instant Sending or Prio Mail?
	// - Track if ContentType header is set.
	$has_content_type_header = false;
	$has_from_header         = false;
	foreach ( $headers as $index => $val ) {
		$val = trim( $val );
		if ( preg_match( '#^X-Mail-Queue-Prio: +Instant *$#i', $val ) ) {
			array_splice( $headers, $index, 1 );
			// In block mode, instant emails are also blocked (retained).
			if ( '2' === $wp_tmq_options['enabled'] ) {
				$status = 'queue';
			} else {
				$status = 'instant';
			}
			break;
		} elseif ( preg_match( '#^X-Mail-Queue-Prio: +High *$#i', $val ) ) {
			array_splice( $headers, $index, 1 );
			$status = 'high';
			break;
		} elseif ( preg_match( '#^Content-Type:#i', $val ) ) {
			$has_content_type_header = true;
		} elseif ( preg_match( '#^From:#i', $val ) ) {
			$has_from_header = true;
		}
	}

	// For all emails that are stored in the queue to be sent later:
	// Store custom filtered values in headers if available.
	// Support the following hooks used in wp_mail:
	// - wp_mail_content_type.
	// - wp_mail_charset.
	// - wp_mail_from.
	// - wp_mail_from_name.
	if ( 'instant' !== $status ) {
		if ( ! $has_content_type_header ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
			$content_type = apply_filters( 'wp_mail_content_type', 'text/plain' );
			if ( $content_type ) {
				if ( stripos( $content_type, 'multipart' ) === false ) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
					$charset = apply_filters( 'wp_mail_charset', get_bloginfo( 'charset' ) );
				} else {
					$charset = '';
				}
				$headers[] = 'Content-Type: ' . $content_type . ( $charset ? '; charset="' . $charset . '"' : '' );
			}
		}
		if ( ! $has_from_header ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
			$from_email = apply_filters( 'wp_mail_from', '' );
			if ( $from_email ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
				$from_name = apply_filters( 'wp_mail_from_name', '' );
				if ( $from_name ) {
					$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
				} else {
					$headers[] = 'From: ' . $from_email;
				}
			}
		}
	}

	// Capture phpmailer_init configurations from other plugins.
	// We store them so when sending from queue we can replay them.
	$phpmailer_config = \TotalMailQueue\Smtp\PhpMailerCapturer::capture();
	if ( $phpmailer_config ) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport-encoding the captured config so it survives as an email header
		$headers[] = 'X-TMQ-PHPMailer-Config: ' . base64_encode( \TotalMailQueue\Support\Serializer::encode( $phpmailer_config ) );
	}

	// Write email in Queue.
	$table_name = $wpdb->prefix . $wp_tmq_options['tableName'];
	$data       = array(
		'timestamp'   => current_time( 'mysql', false ),
		'recipient'   => \TotalMailQueue\Support\Serializer::encode( $to ),
		'subject'     => $subject,
		'message'     => $message,
		'status'      => $status,
		'attachments' => '',
	);
	if ( isset( $headers ) && $headers ) {
		$data['headers'] = \TotalMailQueue\Support\Serializer::encode( $headers ); }

	// store attachments in /attachments/ Folder, to address them later.
	if ( ! empty( $attachments ) ) {

		$attachments_base = \TotalMailQueue\Support\Paths::attachmentsDir();
		// Protect attachments directory from web access.
		if ( ! file_exists( $attachments_base . '.htaccess' ) ) {
			wp_mkdir_p( $attachments_base );
			global $wp_filesystem;
			if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
				include_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->put_contents( $attachments_base . '.htaccess', "Deny from all\n" );
			$wp_filesystem->put_contents( $attachments_base . 'index.php', "<?php // Silence is golden.\n" );
		}
		$subfolder     = time() . '-' . wp_generate_password( 12, false );
		$foldercreated = wp_mkdir_p( $attachments_base . $subfolder );
		if ( ! $foldercreated ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional diagnostic for server logs
			error_log( 'Total Mail Queue: Could not create subfolder for email attachment' );
			$data['info'] = __( 'Error: Could not store attachments', 'total-mail-queue' );
		} else {
			if ( ! is_array( $attachments ) ) {
				$attachments = array( $attachments ); }
			$newattachments = array();
			global $wp_filesystem;
			if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
				include_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			foreach ( $attachments as $item ) {
				$newfile = $attachments_base . $subfolder . '/' . basename( $item );
				$wp_filesystem->copy( $item, $newfile );
				array_push( $newattachments, $newfile );
			}
			$data['attachments'] = \TotalMailQueue\Support\Serializer::encode( $newattachments );
		}
	}
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->insert( $table_name, $data );

	if ( 'instant' === $status ) {
		$wp_tmq_mailid = $wpdb->insert_id;
		return null;
	} elseif ( ! $inserted ) {
		// No database entry, email cannot be send.
		return false;
	} else {
		// Fake Submit by returning 'True'.
		return true;
	}
}


// show wp_mail() errors — with auto-retry support.
/**
 * Mail failed.
 *
 * @since 2.3.0
 *
 * @param mixed $wp_error Parameter description.
 *
 * @return void
 */
function wp_tmq_mail_failed( $wp_error ) {
	global $wpdb, $wp_tmq_options, $wp_tmq_mailid;
	if ( isset( $wp_tmq_mailid ) && 0 !== $wp_tmq_mailid ) {
		$table_name           = $wpdb->prefix . $wp_tmq_options['tableName'];
		$wp_mail_failed_error = isset( $wp_error->errors ) && isset( $wp_error->errors['wp_mail_failed'][0] ) ? implode( '; ', $wp_error->errors['wp_mail_failed'] ) : '<em>' . __( 'Unknown', 'total-mail-queue' ) . '</em>';

		// Get current retry count for this email.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current     = $wpdb->get_row( $wpdb->prepare( "SELECT `retry_count`, `status` FROM `$table_name` WHERE `id` = %d", intval( $wp_tmq_mailid ) ), ARRAY_A );
		$retry_count = $current ? intval( $current['retry_count'] ) : 0;
		$max_retries = intval( $wp_tmq_options['max_retries'] );

		if ( $max_retries > 0 && $retry_count < $max_retries ) {
			// Auto-retry: increment counter and return to queue for another attempt.
			$new_retry = $retry_count + 1;
			/* translators: %1$d: current attempt number, %2$d: max attempts, %3$s: error message */
			$retry_info = sprintf( __( 'Retry %1$d/%2$d — %3$s', 'total-mail-queue' ), $new_retry, $max_retries, $wp_mail_failed_error );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table_name,
				array(
					'timestamp'   => current_time( 'mysql', false ),
					'status'      => 'queue',
					'retry_count' => $new_retry,
					'info'        => $retry_info,
				),
				array( 'id' => intval( $wp_tmq_mailid ) ),
				array( '%s', '%s', '%d', '%s' ),
				'%d'
			);
		} else {
			// Max retries reached or retries disabled: mark as final error.
			if ( $max_retries > 0 && $retry_count >= $max_retries ) {
				/* translators: %1$d: total attempts, %2$s: error message */
				$wp_mail_failed_error = sprintf( __( 'Failed after %1$d attempt(s) — %2$s', 'total-mail-queue' ), $retry_count + 1, $wp_mail_failed_error );
			}
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table_name,
				array(
					'timestamp' => current_time( 'mysql', false ),
					'status'    => 'error',
					'info'      => $wp_mail_failed_error,
				),
				array( 'id' => intval( $wp_tmq_mailid ) ),
				array( '%s', '%s', '%s' ),
				'%d'
			);
		}
	}
}

add_action( 'wp_mail_failed', 'wp_tmq_mail_failed', 10, 1 );

// Mark instant emails as sent on success.
// WP fires `do_action( 'wp_mail_succeeded', $mail_data )` but we don't read the.
// payload — we identify the row via the $wp_tmq_mailid global that wp_tmq_prewpmail.
// set when the row was inserted. accepted_args=0 below tells WP not to forward it.
/**
 * Mail succeeded.
 *
 * @since 2.3.0
 *
 * @return void
 */
function wp_tmq_mail_succeeded() {
	global $wpdb, $wp_tmq_options, $wp_tmq_mailid;
	if ( isset( $wp_tmq_mailid ) && 0 !== $wp_tmq_mailid ) {
		$table_name = $wpdb->prefix . $wp_tmq_options['tableName'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT `status` FROM `$table_name` WHERE `id` = %d", intval( $wp_tmq_mailid ) ), ARRAY_A );
		if ( $current && 'instant' === $current['status'] ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table_name,
				array(
					'timestamp' => current_time( 'mysql', false ),
					'status'    => 'sent',
					'info'      => '',
				),
				array( 'id' => intval( $wp_tmq_mailid ) ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
		$wp_tmq_mailid = 0;
	}
}
add_action( 'wp_mail_succeeded', 'wp_tmq_mail_succeeded', 10, 0 );




/**
 * **************************************************************
CRON
 * ***************************************************************
 */
function wp_tmq_search_mail_from_queue() {

	global $wpdb, $wp_tmq_options, $wp_tmq_mailid, $wp_tmq_pre_wp_mail_priority;

	// Track cron execution for diagnostics.
	$diag = array( 'time' => current_time( 'mysql', false ) );

	// Only process queue in mode 1 (queue). Mode 2 (block) retains but never sends.
	if ( '1' !== $wp_tmq_options['enabled'] ) {
		$diag['result'] = 'skipped: plugin not in queue mode (enabled=' . $wp_tmq_options['enabled'] . ')';
		update_option( 'wp_tmq_last_cron', $diag, false );
		return;
	}
	$table_name = $wpdb->prefix . $wp_tmq_options['tableName'];

	// Triggercount to avoid multiple runs within the same PHP request.
	++$wp_tmq_options['triggercount'];
	if ( $wp_tmq_options['triggercount'] > 1 ) {
		$diag['result'] = 'skipped: duplicate trigger';
		update_option( 'wp_tmq_last_cron', $diag, false );
		return;
	}

	// Clean up legacy transient lock (from older version).
	delete_transient( 'wp_tmq_cron_lock' );

	// Cross-process lock: prevent overlapping cron batches using MySQL GET_LOCK.
	// This is truly atomic — impossible for two processes to acquire simultaneously.
	// If PHP crashes, MySQL releases the lock when the connection closes.
	$lock_name    = 'wp_tmq_cron_lock';
	$lock_timeout = intval( $wp_tmq_options['cron_lock_ttl'] );
	if ( $lock_timeout < 30 ) {
		$lock_timeout = 30; }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$got_lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );
	if ( ! $got_lock ) {
		$diag['result'] = 'skipped: another batch is still running';
		update_option( 'wp_tmq_last_cron', $diag, false );
		return;
	}

	// Register a shutdown function to release the lock even if PHP fatals mid-batch.
	// Also set a MySQL-level timeout so the lock auto-expires if the connection hangs.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( 'SET @tmq_lock_timeout = %d', $lock_timeout ) );
	register_shutdown_function(
		function () use ( $wpdb, $lock_name ) {
       // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	);

	// Total Mails waiting in the Queue?
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$mailjobs_total = $wpdb->get_var( "SELECT COUNT(*) FROM `$table_name` WHERE `status` = 'queue' OR `status` = 'high'" );

	// Mails to send — fetch only IDs to keep memory low; full row loaded per-email inside the loop.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$mailjob_ids    = $wpdb->get_col( "SELECT `id` FROM `$table_name` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `status` ASC, `retry_count` ASC, `id` ASC LIMIT " . intval( $wp_tmq_options['queue_amount'] ) );
	$mails_in_queue = is_array( $mailjob_ids ) ? count( $mailjob_ids ) : 0;

	// Alert Admin, if too many mails in the Queue.
	if ( '1' === $wp_tmq_options['alert_enabled'] && $mailjobs_total > intval( $wp_tmq_options['email_amount'] ) ) {

		// Last alerts older than 6 hours?
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$alerts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `status` = 'alert' AND `timestamp` > DATE_SUB(%s, INTERVAL 6 HOUR)", current_time( 'mysql', false ) ), 'ARRAY_A' );

		// If no alerts, then send one.
		if ( ! $alerts ) {
			$alert_message  = __( 'Hi,', 'total-mail-queue' );
			$alert_message .= "\n\n";
			/* translators: %s: site URL */
			$alert_message .= sprintf( __( 'this is an important message from your WordPress website %s.', 'total-mail-queue' ), esc_url( get_option( 'siteurl' ) ) );
			$alert_message .= "\n";
			/* translators: %s: number of emails in queue */
			$alert_message .= "\n" . sprintf( __( 'The Total Mail Queue Plugin has detected that your website tries to send more emails than expected (currently %s).', 'total-mail-queue' ), $mailjobs_total );
			$alert_message .= "\n" . __( 'Please take a close look at the email queue, because it contains more messages than the specified limit.', 'total-mail-queue' );
			$alert_message .= "\n";
			$alert_message .= "\n" . __( 'In case this is the usual amount of emails, you can adjust the threshold for alerts in the settings of your Total Mail Queue Plugin.', 'total-mail-queue' );
			$alert_message .= "\n\n";
			$alert_message .= '-- ';
			$alert_message .= "\n";
			$alert_message .= admin_url();
			/* translators: %s: blog name */
			$alert_subject = sprintf( __( '🔴 WordPress Total Mail Queue Alert - %s', 'total-mail-queue' ), esc_html( get_option( 'blogname' ) ) );
			$data          = array(
				'timestamp' => current_time( 'mysql', false ),
				'recipient' => sanitize_email( $wp_tmq_options['email'] ),
				'subject'   => $alert_subject,
				'message'   => $alert_message,
				'status'    => 'alert',
				'info'      => wp_json_encode(
					array(
						'in_queue'       => strval( $mails_in_queue ),
						'email_amount'   => intval( $wp_tmq_options['email_amount'] ),
						'queue_amount'   => intval( $wp_tmq_options['queue_amount'] ),
						'queue_interval' => intval( $wp_tmq_options['queue_interval'] ),
					)
				),
			);
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $table_name, $data );
			wp_mail( $wp_tmq_options['email'], $alert_subject, $alert_message );
		}
	}

	// Reset SMTP counters once per cron run, then get available accounts.
	\TotalMailQueue\Smtp\Repository::resetCounters();
	$smtp_accounts = \TotalMailQueue\Smtp\Repository::available();

	// Track diagnostics.
	$diag['queue_total']   = $mailjobs_total;
	$diag['queue_batch']   = $mails_in_queue;
	$diag['smtp_accounts'] = count( $smtp_accounts );
	$diag['send_method']   = isset( $wp_tmq_options['send_method'] ) ? $wp_tmq_options['send_method'] : 'auto';
	$diag['sent']          = 0;
	$diag['errors']        = 0;

	// Send Mails in Queue.
	if ( $mails_in_queue > 0 ) {
		foreach ( $mailjob_ids as $mail_id ) {
				// Load full row on demand (keeps only one email body in memory at a time).
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `id` = %d", $mail_id ), ARRAY_A );
			if ( ! $item ) {
				continue; }
			if ( ! empty( $item['recipient'] ) ) {
				$to = \TotalMailQueue\Support\Serializer::decode( $item['recipient'] );
			} else {
				$to              = $wp_tmq_options['email'];
				$item['subject'] = __( 'ERROR', 'total-mail-queue' ) . ' // ' . $item['subject']; }
			if ( ! empty( $item['headers'] ) ) {
				$headers = \TotalMailQueue\Support\Serializer::decode( $item['headers'] );
			} else {
				$headers = ''; }
			if ( ! empty( $item['attachments'] ) ) {
				$attachments = \TotalMailQueue\Support\Serializer::decode( $item['attachments'] );
			} else {
				$attachments = ''; }
				$wp_tmq_mailid = $item['id'];

				// Extract captured phpmailer config from headers if present.
				$captured_phpmailer_config = null;
			if ( is_array( $headers ) ) {
				foreach ( $headers as $hindex => $hval ) {
					if ( preg_match( '/^X-TMQ-PHPMailer-Config: (.+)$/i', $hval, $matches ) ) {
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reading the encoded payload our own wp_tmq_prewpmail() wrote
						$captured_phpmailer_config = \TotalMailQueue\Support\Serializer::decode( base64_decode( trim( $matches[1] ) ) );
						array_splice( $headers, $hindex, 1 );
						break;
					}
				}
			}

				// Determine send method.
				$send_method = isset( $wp_tmq_options['send_method'] ) ? $wp_tmq_options['send_method'] : 'auto';

				// Find available SMTP account from in-memory list (skip if send_method is 'php').
				$smtp_to_use = null;
			if ( 'php' !== $send_method && ! empty( $smtp_accounts ) ) {
				$smtp_to_use = \TotalMailQueue\Smtp\Repository::pickAvailable( $smtp_accounts );
			}

				// In 'smtp' mode, if no SMTP account is available, skip remaining emails.
			if ( 'smtp' === $send_method && ! $smtp_to_use ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table_name,
					array( 'info' => __( 'Waiting: no SMTP account available (check if accounts are enabled and limits are not exceeded).', 'total-mail-queue' ) ),
					array( 'id' => $item['id'] ),
					array( '%s' ),
					array( '%d' )
				);
				$diag['result'] = 'smtp_unavailable';
				break; // No SMTP available — no point trying other emails in this batch.
			}

				// Configure phpmailer_init hook for this email.
				$tmq_phpmailer_hook = null;
			if ( $smtp_to_use ) {
				$tmq_phpmailer_hook = function ( $phpmailer ) use ( $smtp_to_use ) {
					\TotalMailQueue\Smtp\Configurator::apply( $phpmailer, $smtp_to_use );
				};
					add_action( 'phpmailer_init', $tmq_phpmailer_hook, 999999 );
			} elseif ( 'auto' === $send_method && $captured_phpmailer_config && is_array( $captured_phpmailer_config ) ) {
				// Replay captured phpmailer config only in 'auto' mode.
				$tmq_phpmailer_hook = function ( $phpmailer ) use ( $captured_phpmailer_config ) {
					if ( method_exists( $phpmailer, 'smtpClose' ) ) {
						$phpmailer->smtpClose();
					}
					foreach ( $captured_phpmailer_config as $prop => $val ) {
						if ( property_exists( $phpmailer, $prop ) ) {
							if ( 'Password' === $prop ) {
								$val = \TotalMailQueue\Support\Encryption::decrypt( $val );
							}
							$phpmailer->$prop = $val;
						}
					}
					// Apply configurable SMTP timeout.
					global $wp_tmq_options;
					$smtp_timeout = intval( $wp_tmq_options['smtp_timeout'] );
					if ( $smtp_timeout > 0 ) {
						$phpmailer->Timeout = $smtp_timeout;
					}
				};
					add_action( 'phpmailer_init', $tmq_phpmailer_hook, 999999 );
			}

				// Remove ALL other pre_wp_mail filters to prevent conflicts, keep only ours to re-add later.
				global $wp_filter;
				$saved_pre_wp_mail = null;
			if ( isset( $wp_filter['pre_wp_mail'] ) ) {
				$saved_pre_wp_mail = clone $wp_filter['pre_wp_mail'];
			}
				remove_all_filters( 'pre_wp_mail' );

				$sendstatus = wp_mail( $to, $item['subject'], $item['message'], $headers, $attachments ); // Finally sends the email for real.

				// Restore all pre_wp_mail filters that were present before.
			if ( $saved_pre_wp_mail ) {
				$wp_filter['pre_wp_mail'] = $saved_pre_wp_mail;
			}
				// Note: we do NOT re-add wp_tmq_prewpmail here — during cron.
				// it was never registered, and re-adding it would intercept.
				// subsequent wp_mail() calls in this batch.

				// Remove temporary phpmailer hook.
			if ( $tmq_phpmailer_hook ) {
				remove_action( 'phpmailer_init', $tmq_phpmailer_hook, 999999 );
			}

			if ( $sendstatus ) {
				$sent_smtp_id = $smtp_to_use ? intval( $smtp_to_use['id'] ) : 0;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table_name,
					array(
						'timestamp'       => current_time( 'mysql', false ),
						'status'          => 'sent',
						'info'            => '',
						'smtp_account_id' => $sent_smtp_id,
					),
					array( 'id' => $item['id'] ),
					array( '%s', '%s', '%s', '%d' ),
					array( '%d' )
				);
				// Increment SMTP counter (DB for persistence + in-memory for next iteration).
				if ( $smtp_to_use ) {
					\TotalMailQueue\Smtp\Repository::incrementCounter( $smtp_to_use['id'] );
					\TotalMailQueue\Smtp\Repository::bumpMemoryCounter( $smtp_accounts, $smtp_to_use['id'] );
				}
				++$diag['sent'];
			} else {
				++$diag['errors'];
				// If wp_mail_failed hook didn't update info, write a fallback diagnostic.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$current_info = $wpdb->get_var( $wpdb->prepare( "SELECT `info` FROM `$table_name` WHERE `id` = %d", $item['id'] ) );
				if ( empty( $current_info ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table_name,
						array( 'info' => __( 'wp_mail() returned false. Check for conflicting email plugins or server mail configuration.', 'total-mail-queue' ) ),
						array( 'id' => $item['id'] ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
			if ( is_array( $attachments ) ) {
				global $wp_filesystem;
				if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
					include_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}
				$attachmentfolder = pathinfo( $attachments[0] );
				$wp_filesystem->delete( $attachmentfolder['dirname'], true, 'd' );
			}
		}
	}

	// Delete old logs (by date).
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM `$table_name` WHERE `status` != 'queue' AND `status` != 'high' AND `timestamp` < DATE_SUB(%s, INTERVAL %d HOUR)", current_time( 'mysql', false ), intval( $wp_tmq_options['clear_queue'] ) ) );

	// Delete excess log entries (by total records limit).
	$log_max = intval( $wp_tmq_options['log_max_records'] );
	if ( $log_max > 0 ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_log = $wpdb->get_var( "SELECT COUNT(*) FROM `$table_name` WHERE `status` != 'queue' AND `status` != 'high'" );
		if ( $total_log > $log_max ) {
			$excess = $total_log - $log_max;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `$table_name` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `timestamp` ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
					intval( $excess )
				)
			);
		}
	}

	// Release cross-process lock (also released by shutdown function as safety net).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );

	// Save cron diagnostics.
	if ( ! isset( $diag['result'] ) ) {
		$diag['result'] = 'ok';
	}
	update_option( 'wp_tmq_last_cron', $diag, false );
}
add_action( 'wp_tmq_mail_queue_hook', 'wp_tmq_search_mail_from_queue' );

// Custom Cron Interval.
/**
 * Cron interval.
 *
 * @since 2.3.0
 *
 * @param mixed $schedules Parameter description.
 *
 * @return mixed Function output.
 */
function wp_tmq_cron_interval( $schedules ) {
	global $wp_tmq_options;
	$schedules['wp_tmq_interval'] = array(
		'interval' => $wp_tmq_options['queue_interval'],
		'display'  => esc_html__( 'Total Mail Queue', 'total-mail-queue' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'wp_tmq_cron_interval' );

// Set or Remove Cron (enabled=1 needs cron, enabled=2 block mode doesn't need cron).
$wp_tmq_next_cron_timestamp = wp_next_scheduled( 'wp_tmq_mail_queue_hook' );
if ( $wp_tmq_next_cron_timestamp && '1' !== $wp_tmq_options['enabled'] ) {
	wp_unschedule_event( $wp_tmq_next_cron_timestamp, 'wp_tmq_mail_queue_hook' );
} elseif ( ! $wp_tmq_next_cron_timestamp && '1' === $wp_tmq_options['enabled'] ) {
	wp_schedule_event( time(), 'wp_tmq_interval', 'wp_tmq_mail_queue_hook' );
}



/*
 ***************************************************************
Install/Uninstall/Upgrade
****************************************************************
*/

// Lifecycle is now driven by \TotalMailQueue\Plugin::boot():
// - Activation:   \TotalMailQueue\Lifecycle\Activator::activate (registered via register_activation_hook).
// - Deactivation: \TotalMailQueue\Lifecycle\Deactivator::deactivate (registered via register_deactivation_hook).
// - Uninstall:    \TotalMailQueue\Lifecycle\Uninstaller::uninstall (driven by uninstall.php at the plugin root).
// - Upgrades:     \TotalMailQueue\Database\Migrator::maybeMigrate on every plugins_loaded.

// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- needed for self-hosted installs and custom language paths
/**
 * Load textdomain.
 *
 * @since 2.3.0
 *
 * @return void
 */
function wp_tmq_load_textdomain() {
	load_plugin_textdomain( 'total-mail-queue', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wp_tmq_load_textdomain' );




/*
 ***************************************************************
Options Page
****************************************************************
*/
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'total-mail-queue-options.php';
	require_once plugin_dir_path( __FILE__ ) . 'total-mail-queue-smtp.php';
}






/**
 * **************************************************************
REST API
 * ***************************************************************
 */
function wp_tmq_add_rest_endpoints() {
	register_rest_route(
		'tmq/v1',
		'/message/(?P<id>[\d]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'wp_tmq_rest_get_message',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'wp_tmq_add_rest_endpoints', 10, 0 );
/**
 * Rest get message.
 *
 * @since 2.3.0
 *
 * @param mixed $request Parameter description.
 *
 * @return mixed Function output.
 */
function wp_tmq_rest_get_message( $request ) {
	global $wpdb, $wp_tmq_options;
	$table_name = $wpdb->prefix . $wp_tmq_options['tableName'];
	$id         = intval( $request['id'] );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `id` = %d", $id ), ARRAY_A );
	if ( $row ) {
		// Search for content-type header to detect html emails.
		$is_content_type_html = false;
		$headers              = \TotalMailQueue\Support\Serializer::decode( $row['headers'] );
		if ( is_string( $headers ) ) {
			$headers = array( $headers );
		} elseif ( ! is_array( $headers ) ) {
			$headers = array();
		}
		foreach ( $headers as $header ) {
			if ( preg_match( '/content-type: ?text\/html/i', $header ) ) {
				$is_content_type_html = true;
				break;
			}
		}
		return array(
			'status' => 'ok',
			'data'   => array(
				'html' => \TotalMailQueue\Support\HtmlPreview::renderListMessage( \TotalMailQueue\Support\Serializer::decode( $row['message'] ), $is_content_type_html ),
			),
		);
	} else {
		return new WP_Error( 'no_message', __( 'Message not found', 'total-mail-queue' ), array( 'status' => 404 ) );
	}
}
// HTML preview helpers moved to \TotalMailQueue\Support\HtmlPreview.
