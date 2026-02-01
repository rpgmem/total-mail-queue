<?php

<<<<<<< Updated upstream
/**
 * Plugin Name:       Total Mail Queue
 * Plugin URI:
 * Description:       Take Control and improve Security of wp_mail(). Queue and log outgoing emails, and get alerted, if your website wants to send more emails than usual.
 * Version:           2.2.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:
 * Author URI:
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       total-mail-queue
 *
 * This plugin is a fork of Mail Queue by WDM (https://www.webdesign-muenchen.de)
 * Original plugin: https://wordpress.org/plugins/mail-queue/
=======
/*
 Plugin Name:         Total Mail Queue
 Plugin URI:          https://github.com/rpgmem/total-mail-queue
 Description:         Take Control and improve Security of wp_mail(). Queue and log outgoing emails, and get alerted, if your website wants to send more emails than usual.
 Version:             2.2.0
 Requires at least:   5.9
 Requires PHP:        7.4
 Author:              Alex Meusburger
 Author URI:          https://github.com/rpgmem
 License:             GPLv3 or later
 License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 Text Domain:         total-mail-queue
 
 This plugin is a fork of Mail Queue by WDM (https://www.webdesign-muenchen.de)
 Original plugin: https://wordpress.org/plugins/mail-queue/
>>>>>>> Stashed changes
 */

 if (!defined('ABSPATH')) { exit; }


/* ***************************************************************
PLUGIN VERSION
**************************************************************** */

$wp_tmq_version = '2.2.1';





/* ***************************************************************
PLUGIN DEFAULT SETTINGS
**************************************************************** */
function wp_tmq_get_settings() {
    $defaults = array(
        'enabled'        => '0',   // 0=disabled, 1=queue (retain+send), 2=block (retain only, no sending)
        'alert_enabled'  => '0',
        'email'          => get_option('admin_email'),
        'email_amount'   => '10',
        'queue_amount'   => '1',
        'queue_interval' => '5',
        'queue_interval_unit' => 'minutes',
        'clear_queue'    => '14',
        'log_max_records'=> '0',   // 0=unlimited, >0=max number of log entries to keep
        'send_method'    => 'auto', // auto=SMTP if available then captured then default, smtp=only plugin SMTP, php=default wp_mail only
        'max_retries'    => '3',   // 0=no retries, >0=auto-retry failed emails up to N times
        'cron_lock_ttl'  => '300', // seconds — safety timeout for the cross-process cron lock
        'smtp_timeout'   => '30',  // seconds — per-connection SMTP timeout during queue sending
        'tableName'      => 'total_mail_queue',
        'smtpTableName'  => 'total_mail_queue_smtp',
        'triggercount'   => 0,
    );
    $args = get_option('wp_tmq_settings');
    $options = wp_parse_args($args,$defaults);

    if ($options['queue_interval_unit'] === 'seconds') {
        $options['queue_interval'] = intval($options['queue_interval']);
        if ($options['queue_interval'] < 10) { $options['queue_interval'] = 10; } // Minimum Interval 10 Seconds
    } else {
        $options['queue_interval'] = intval($options['queue_interval']) * 60;
    }

    $options['clear_queue'] = intval($options['clear_queue']) * 24;
    return $options;
}


/* ***************************************************************
SMTP Password Encryption Helpers
**************************************************************** */
function wp_tmq_encrypt_password( $plain_text ) {
    if ( empty( $plain_text ) ) return '';
    $key = wp_salt( 'auth' );
    $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
    $iv = openssl_random_pseudo_bytes( $iv_length );
    $encrypted = openssl_encrypt( $plain_text, 'aes-256-cbc', $key, 0, $iv );
    return base64_encode( $iv . '::' . $encrypted );
}

function wp_tmq_decrypt_password( $encrypted_text ) {
    if ( empty( $encrypted_text ) ) return '';
    $key = wp_salt( 'auth' );
    $data = base64_decode( $encrypted_text );
    $parts = explode( '::', $data, 2 );
    if ( count( $parts ) !== 2 ) return '';
    $iv = $parts[0];
    $encrypted = $parts[1];
    return openssl_decrypt( $encrypted, 'aes-256-cbc', $key, 0, $iv );
}


/* ***************************************************************
Attachments directory helper
**************************************************************** */
function wp_tmq_attachments_dir() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['basedir'] ) . 'tmq-attachments/';
}

/* ***************************************************************
Safe serialization helpers (JSON, with backwards-compat for old PHP-serialized data)
**************************************************************** */
function wp_tmq_encode( $value ) {
    return wp_json_encode( $value );
}

function wp_tmq_decode( $raw ) {
    if ( empty( $raw ) || ! is_string( $raw ) ) {
        return $raw;
    }
    // Try JSON first (new format)
    $json = json_decode( $raw, true );
    if ( json_last_error() === JSON_ERROR_NONE ) {
        return $json;
    }
    // Fall back to PHP unserialize for legacy data (read-only, safe with allowed_classes)
    if ( is_serialized( $raw ) ) {
        return @unserialize( $raw, array( 'allowed_classes' => false ) );
    }
    return $raw;
}

/* ***************************************************************
SMTP Account Helpers
**************************************************************** */
function wp_tmq_reset_smtp_counters() {
    global $wpdb, $wp_tmq_options;
    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];

    $today = current_time( 'Y-m-d' );
    $this_month = current_time( 'Y-m' );
    $now = current_time( 'mysql', false );

    // Reset per-cycle bulk counter:
    // - Accounts with send_interval=0 (global): reset every cron run (each cron = one cycle)
    // - Accounts with send_interval>0: reset only when the interval has elapsed (new cycle)
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        "UPDATE `$smtpTable` SET `cycle_sent` = 0 WHERE `send_interval` = 0"
    );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare(
        "UPDATE `$smtpTable` SET `cycle_sent` = 0 WHERE `send_interval` > 0 AND DATE_ADD(`last_sent_at`, INTERVAL `send_interval` MINUTE) <= %s",
        $now
    ) );

    // Reset daily counters if day changed
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare(
        "UPDATE `$smtpTable` SET `daily_sent` = 0, `last_daily_reset` = %s WHERE `last_daily_reset` < %s",
        $today, $today
    ) );

    // Reset monthly counters if month changed
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare(
        "UPDATE `$smtpTable` SET `monthly_sent` = 0, `last_monthly_reset` = %s WHERE DATE_FORMAT(`last_monthly_reset`, '%%Y-%%m') < %s",
        $today, $this_month
    ) );
}

function wp_tmq_get_available_smtp() {
    global $wpdb, $wp_tmq_options;
    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];

    // Get enabled SMTP accounts ordered by priority, where:
    // - daily/monthly limits not reached
    // - per-account bulk limit not reached (cycle_sent < send_bulk, or send_bulk=0 means unlimited)
    //
    // Note: send_interval is NOT checked here. The interval controls when
    // cycle_sent is reset (in wp_tmq_reset_smtp_counters). As long as the
    // current cycle still has room (cycle_sent < send_bulk), the account
    // remains available. The interval only blocks new cycles — not mid-cycle sends.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $accounts = $wpdb->get_results(
        "SELECT * FROM `$smtpTable` WHERE `enabled` = 1
         AND (`daily_limit` = 0 OR `daily_sent` < `daily_limit`)
         AND (`monthly_limit` = 0 OR `monthly_sent` < `monthly_limit`)
         AND (`send_bulk` = 0 OR `cycle_sent` < `send_bulk`)
         ORDER BY `priority` ASC",
        ARRAY_A
    );

    return $accounts ? $accounts : array();
}

/**
 * Pick the first available SMTP account from the in-memory list.
 *
 * Checks cycle_sent against send_bulk without hitting the database.
 * Returns the account array or null if none available.
 */
function wp_tmq_pick_available_smtp( $smtp_accounts ) {
    foreach ( $smtp_accounts as $acct ) {
        $bulk = intval( $acct['send_bulk'] );
        if ( $bulk === 0 || intval( $acct['cycle_sent'] ) < $bulk ) {
            return $acct;
        }
    }
    return null;
}

/**
 * Update the in-memory SMTP accounts list after a successful send.
 *
 * Increments cycle_sent for the given account key in the array.
 */
function wp_tmq_update_memory_counter( &$smtp_accounts, $smtp_id ) {
    $target_id = intval( $smtp_id );
    foreach ( $smtp_accounts as $key => $acct ) {
        if ( intval( $acct['id'] ) === $target_id ) {
            $smtp_accounts[ $key ]['cycle_sent'] = intval( $acct['cycle_sent'] ) + 1;
            break;
        }
    }
}

function wp_tmq_increment_smtp_counter( $smtp_id ) {
    global $wpdb, $wp_tmq_options;
    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
    $now = current_time( 'mysql', false );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare(
        "UPDATE `$smtpTable` SET `daily_sent` = `daily_sent` + 1, `monthly_sent` = `monthly_sent` + 1, `cycle_sent` = `cycle_sent` + 1, `last_sent_at` = %s WHERE `id` = %d",
        $now, intval( $smtp_id )
    ) );
}

function wp_tmq_configure_phpmailer( $phpmailer, $smtp_account ) {
    // Close any existing SMTP connection so we start fresh
    // (WordPress reuses the same PHPMailer instance across wp_mail calls)
    if ( method_exists( $phpmailer, 'smtpClose' ) ) {
        $phpmailer->smtpClose();
    }
    $phpmailer->isSMTP();
    $phpmailer->Host       = $smtp_account['host'];
    $phpmailer->Port       = intval( $smtp_account['port'] );
    $phpmailer->SMTPSecure = $smtp_account['encryption'] === 'none' ? '' : $smtp_account['encryption'];
    $phpmailer->SMTPAuth   = (bool) $smtp_account['auth'];
    if ( $smtp_account['auth'] ) {
        $phpmailer->Username = $smtp_account['username'];
        $phpmailer->Password = wp_tmq_decrypt_password( $smtp_account['password'] );
    }
    if ( ! empty( $smtp_account['from_email'] ) ) {
        $phpmailer->From     = $smtp_account['from_email'];
        $phpmailer->Sender   = $smtp_account['from_email'];
        $phpmailer->FromName = ! empty( $smtp_account['from_name'] ) ? $smtp_account['from_name'] : $phpmailer->FromName;
    }
    // Apply configurable SMTP timeout to prevent a stalled connection from blocking the batch
    global $wp_tmq_options;
    $smtp_timeout = intval( $wp_tmq_options['smtp_timeout'] );
    if ( $smtp_timeout > 0 ) {
        $phpmailer->Timeout = $smtp_timeout;
    }
}


/* ***************************************************************
Overwrite wp_mail() if Plugin enabled and no Cron is running
Modes: 1=queue (retain+send), 2=block (retain only)
**************************************************************** */
$wp_tmq_mailid = 0;
$wp_tmq_options = wp_tmq_get_settings(); // Get Settings
$wp_tmq_pre_wp_mail_priority = 99999;

// Enable interception for both queue mode (1) and block mode (2)
if (in_array($wp_tmq_options['enabled'], array('1','2')) && wp_doing_cron() === false) {
    // High priority: run late in the game to react to previous filters
    add_filter('pre_wp_mail', 'wp_tmq_prewpmail', $wp_tmq_pre_wp_mail_priority, 2);
}

// pre WP Mail Filter
function wp_tmq_prewpmail($return, $atts) {

    global $wpdb, $wp_tmq_options, $wp_tmq_mailid;

    if (!is_null($return)) {
        // Another pre_wp_mail filter has already returned a value, so the mail is not added to the queue
        return $return;
    }

    // Mail Variables
    $to          = $atts['to'];
    $subject     = $atts['subject'];
    $message     = $atts['message'];
    $headers     = $atts['headers'];
    $attachments = $atts['attachments'];
    $status      = 'queue';

    // Make sure that $headers always is an array
    if ($headers) {
        if (!is_array($headers)) {
            $headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
        }
    } else {
        $headers = [];
    }

    // Loop through email headers
    // - Instant Sending or Prio Mail?
    // - Track if ContentType header is set
    $hasContentTypeHeader = false;
    $hasFromHeader = false;
    foreach($headers as $index => $val) {
        $val = trim($val);
        if (preg_match("#^X-Mail-Queue-Prio: +Instant *$#i",$val)) {
            array_splice($headers,$index,1);
            // In block mode, instant emails are also blocked (retained)
            if ($wp_tmq_options['enabled'] === '2') {
                $status = 'queue';
            } else {
                $status = 'instant';
            }
            break;
        } else if (preg_match("#^X-Mail-Queue-Prio: +High *$#i",$val)) {
            array_splice($headers,$index,1);
            $status = 'high';
            break;
        } else if (preg_match('#^Content-Type:#i',$val)) {
            $hasContentTypeHeader = true;
        } else if (preg_match('#^From:#i',$val)) {
            $hasFromHeader = true;
        }
    }

    // For all emails that are stored in the queue to be sent later:
    // Store custom filtered values in headers if available.
    // Support the following hooks used in wp_mail:
    // - wp_mail_content_type
    // - wp_mail_charset
    // - wp_mail_from
    // - wp_mail_from_name
    if ($status !== 'instant') {
        if (!$hasContentTypeHeader) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
            $contentType = apply_filters('wp_mail_content_type','text/plain');
            if ( $contentType ) {
                if (stripos($contentType,'multipart') === false) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
                    $charset = apply_filters('wp_mail_charset',get_bloginfo('charset'));
                } else {
                    $charset = '';
                }
                $headers[] = 'Content-Type: '.$contentType.($charset ? '; charset="'.$charset.'"' : '');
            }
        }
        if (!$hasFromHeader) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
            $from_Email = apply_filters('wp_mail_from','');
            if ($from_Email) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
                $fromName = apply_filters('wp_mail_from_name','');
                if ($fromName) {
                    $headers[] = 'From: '.$fromName.' <'.$from_Email.'>';
                } else {
                    $headers[] = 'From: '.$from_Email;
                }
            }
        }
    }

    // Capture phpmailer_init configurations from other plugins
    // We store them so when sending from queue we can replay them
    $phpmailer_config = wp_tmq_capture_phpmailer_config();
    if ( $phpmailer_config ) {
        $headers[] = 'X-TMQ-PHPMailer-Config: ' . base64_encode( wp_tmq_encode( $phpmailer_config ) );
    }


    // Write email in Queue
    $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
    $data = array(
        'timestamp'=> current_time('mysql',false),
        'recipient'=> wp_tmq_encode($to),
        'subject'=> $subject,
        'message'=> $message,
        'status' => $status,
        'attachments' => ''
    );
    if (isset($headers) && $headers) { $data['headers'] = wp_tmq_encode($headers); }

    // store attachments in /attachments/ Folder, to address them later
    if ( ! empty( $attachments ) ) {

        $attachments_base = wp_tmq_attachments_dir();
        // Protect attachments directory from web access
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
        $subfolder = time().'-'.wp_generate_password(12, false);
        $foldercreated = wp_mkdir_p( $attachments_base . $subfolder );
        if (!$foldercreated) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional diagnostic for server logs
            error_log( 'Total Mail Queue: Could not create subfolder for email attachment' );
            $data['info'] = __( 'Error: Could not store attachments', 'total-mail-queue' );
        } else {
            if (!is_array($attachments)) { $attachments = array($attachments); }
            $newattachments = array();
            global $wp_filesystem;
            if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
                include_once(ABSPATH . 'wp-admin/includes/file.php');
                WP_Filesystem();
            }
            foreach($attachments as $item) {
                $newfile = $attachments_base . $subfolder . '/' . basename($item);
                $wp_filesystem->copy($item,$newfile);
                array_push($newattachments,$newfile);
            }
            $data['attachments'] = wp_tmq_encode($newattachments);
        }
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $inserted = $wpdb->insert($tableName,$data);

    if ($status === 'instant') {
        $wp_tmq_mailid = $wpdb->insert_id;
        return null;
    } else if ( !$inserted ) {
        // No database entry, email cannot be send
        return false;
    } else {
        // Fake Submit by returning 'True'
        return true;
    }

}


/* ***************************************************************
Capture phpmailer_init configurations from other plugins
**************************************************************** */
function wp_tmq_capture_phpmailer_config() {
    global $wp_tmq_capturing_phpmailer;
    $wp_tmq_capturing_phpmailer = true;
    $config = array();

    // Create a temporary PHPMailer to capture configurations
    if ( ! class_exists( 'PHPMailer\PHPMailer\PHPMailer' ) ) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }

    $test_mailer = new PHPMailer\PHPMailer\PHPMailer( true );

    // Store defaults before hooks
    $default_host = $test_mailer->Host;
    $default_port = $test_mailer->Port;
    $default_auth = $test_mailer->SMTPAuth;

    // Apply phpmailer_init hooks to capture what other plugins configure
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core action
    do_action_ref_array( 'phpmailer_init', array( &$test_mailer ) );

    // Only store config if something was changed by hooks
    if ( $test_mailer->Host !== $default_host || $test_mailer->Port !== $default_port || $test_mailer->SMTPAuth !== $default_auth ) {
        $config['Mailer']     = $test_mailer->Mailer;
        $config['Host']       = $test_mailer->Host;
        $config['Port']       = $test_mailer->Port;
        $config['SMTPSecure'] = $test_mailer->SMTPSecure;
        $config['SMTPAuth']   = $test_mailer->SMTPAuth;
        $config['Username']   = $test_mailer->Username;
        $config['Password']   = wp_tmq_encrypt_password( $test_mailer->Password );
        $config['From']       = $test_mailer->From;
        $config['FromName']   = $test_mailer->FromName;
    }

    $wp_tmq_capturing_phpmailer = false;
    return ! empty( $config ) ? $config : null;
}


// show wp_mail() errors — with auto-retry support
function wp_tmq_mail_failed( $wp_error ) {
    global $wpdb,$wp_tmq_options,$wp_tmq_mailid;
    if (isset($wp_tmq_mailid) && $wp_tmq_mailid != 0) {
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        $wpMailFailedError = isset( $wp_error->errors ) && isset( $wp_error->errors['wp_mail_failed'][0] ) ? implode( '; ', $wp_error->errors['wp_mail_failed'] ) : '<em>' . __( 'Unknown', 'total-mail-queue' ) . '</em>';

        // Get current retry count for this email
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT `retry_count`, `status` FROM `$tableName` WHERE `id` = %d", intval( $wp_tmq_mailid ) ), ARRAY_A );
        $retry_count = $current ? intval( $current['retry_count'] ) : 0;
        $max_retries = intval( $wp_tmq_options['max_retries'] );

        if ( $max_retries > 0 && $retry_count < $max_retries ) {
            // Auto-retry: increment counter and return to queue for another attempt
            $new_retry = $retry_count + 1;
            /* translators: %1$d: current attempt number, %2$d: max attempts, %3$s: error message */
            $retry_info = sprintf( __( 'Retry %1$d/%2$d — %3$s', 'total-mail-queue' ), $new_retry, $max_retries, $wpMailFailedError );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $tableName,
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
            // Max retries reached or retries disabled: mark as final error
            if ( $max_retries > 0 && $retry_count >= $max_retries ) {
                /* translators: %1$d: total attempts, %2$s: error message */
                $wpMailFailedError = sprintf( __( 'Failed after %1$d attempt(s) — %2$s', 'total-mail-queue' ), $retry_count + 1, $wpMailFailedError );
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $tableName,
                array(
                    'timestamp' => current_time( 'mysql', false ),
                    'status'    => 'error',
                    'info'      => $wpMailFailedError,
                ),
                array( 'id' => intval( $wp_tmq_mailid ) ),
                array( '%s', '%s', '%s' ),
                '%d'
            );
        }
    }
}

add_action('wp_mail_failed','wp_tmq_mail_failed',10,1);

// Mark instant emails as sent on success
function wp_tmq_mail_succeeded( $mail_data ) {
    global $wpdb, $wp_tmq_options, $wp_tmq_mailid;
    if ( isset( $wp_tmq_mailid ) && $wp_tmq_mailid != 0 ) {
        $tableName = $wpdb->prefix . $wp_tmq_options['tableName'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT `status` FROM `$tableName` WHERE `id` = %d", intval( $wp_tmq_mailid ) ), ARRAY_A );
        if ( $current && $current['status'] === 'instant' ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $tableName,
                array( 'timestamp' => current_time( 'mysql', false ), 'status' => 'sent', 'info' => '' ),
                array( 'id' => intval( $wp_tmq_mailid ) ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
        }
        $wp_tmq_mailid = 0;
    }
}
add_action( 'wp_mail_succeeded', 'wp_tmq_mail_succeeded', 10, 1 );




/* ***************************************************************
CRON
**************************************************************** */
function wp_tmq_search_mail_from_queue() {

    global $wpdb,$wp_tmq_options, $wp_tmq_mailid, $wp_tmq_pre_wp_mail_priority;

    // Track cron execution for diagnostics
    $diag = array( 'time' => current_time( 'mysql', false ) );

    // Only process queue in mode 1 (queue). Mode 2 (block) retains but never sends.
    if ($wp_tmq_options['enabled'] !== '1') {
        $diag['result'] = 'skipped: plugin not in queue mode (enabled=' . $wp_tmq_options['enabled'] . ')';
        update_option( 'wp_tmq_last_cron', $diag, false );
        return;
    }
    $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];

    // Triggercount to avoid multiple runs within the same PHP request
    $wp_tmq_options['triggercount']++;
    if ($wp_tmq_options['triggercount'] > 1) {
        $diag['result'] = 'skipped: duplicate trigger';
        update_option( 'wp_tmq_last_cron', $diag, false );
        return;
    }

    // Clean up legacy transient lock (from older version)
    delete_transient( 'wp_tmq_cron_lock' );

    // Cross-process lock: prevent overlapping cron batches using MySQL GET_LOCK.
    // This is truly atomic — impossible for two processes to acquire simultaneously.
    // If PHP crashes, MySQL releases the lock when the connection closes.
    $lock_name    = 'wp_tmq_cron_lock';
    $lock_timeout = intval( $wp_tmq_options['cron_lock_ttl'] );
    if ( $lock_timeout < 30 ) { $lock_timeout = 30; }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $got_lock = $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK(%s, 0)", $lock_name ) );
    if ( ! $got_lock ) {
        $diag['result'] = 'skipped: another batch is still running';
        update_option( 'wp_tmq_last_cron', $diag, false );
        return;
    }

    // Register a shutdown function to release the lock even if PHP fatals mid-batch.
    // Also set a MySQL-level timeout so the lock auto-expires if the connection hangs.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare( "SET @tmq_lock_timeout = %d", $lock_timeout ) );
    register_shutdown_function( function() use ( $wpdb, $lock_name ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->get_var( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
    } );

    // Total Mails waiting in the Queue?
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $mailjobsTotal = $wpdb->get_var( "SELECT COUNT(*) FROM `$tableName` WHERE `status` = 'queue' OR `status` = 'high'" );

    // Mails to send — fetch only IDs to keep memory low; full row loaded per-email inside the loop
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $mailjobIds   = $wpdb->get_col("SELECT `id` FROM `$tableName` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `status` ASC, `retry_count` ASC, `id` ASC LIMIT ".intval($wp_tmq_options['queue_amount']));
    $mailsInQueue = is_array($mailjobIds) ? count($mailjobIds) : 0;

    // Alert Admin, if too many mails in the Queue.
    if ($wp_tmq_options['alert_enabled'] === '1' && $mailjobsTotal > intval($wp_tmq_options['email_amount'])) {

        // Last alerts older than 6 hours?
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $alerts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `status` = 'alert' AND `timestamp` > DATE_SUB(%s, INTERVAL 6 HOUR)", current_time( 'mysql', false ) ), 'ARRAY_A' );

        // If no alerts, then send one
       if (!$alerts) {
            $alertMessage = __( 'Hi,', 'total-mail-queue' );
            $alertMessage .= "\n\n";
            /* translators: %s: site URL */
            $alertMessage .= sprintf( __( 'this is an important message from your WordPress website %s.', 'total-mail-queue' ), esc_url( get_option( 'siteurl' ) ) );
            $alertMessage .= "\n";
            /* translators: %s: number of emails in queue */
            $alertMessage .= "\n" . sprintf( __( 'The Total Mail Queue Plugin has detected that your website tries to send more emails than expected (currently %s).', 'total-mail-queue' ), $mailjobsTotal );
            $alertMessage .= "\n" . __( 'Please take a close look at the email queue, because it contains more messages than the specified limit.', 'total-mail-queue' );
            $alertMessage .= "\n";
            $alertMessage .= "\n" . __( 'In case this is the usual amount of emails, you can adjust the threshold for alerts in the settings of your Total Mail Queue Plugin.', 'total-mail-queue' );
            $alertMessage .= "\n\n";
            $alertMessage .= "-- ";
            $alertMessage .= "\n";
            $alertMessage .= admin_url();
            /* translators: %s: blog name */
            $alertSubject = sprintf( __( '🔴 WordPress Total Mail Queue Alert - %s', 'total-mail-queue' ), esc_html( get_option( 'blogname' ) ) );
            $data = array(
                'timestamp'=> current_time('mysql',false),
                'recipient'=> sanitize_email($wp_tmq_options['email']),
                'subject'  => $alertSubject,
                'message'  => $alertMessage,
                'status'   => 'alert',
                'info'     => json_encode([
                    'in_queue'       => strval( $mailsInQueue ),
                    'email_amount'   => intval($wp_tmq_options['email_amount']),
                    'queue_amount'   => intval($wp_tmq_options['queue_amount']),
                    'queue_interval' => intval($wp_tmq_options['queue_interval']),
                ]),
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($tableName,$data);
            wp_mail($wp_tmq_options['email'],$alertSubject,$alertMessage);
        }

    }

    // Reset SMTP counters once per cron run, then get available accounts
    wp_tmq_reset_smtp_counters();
    $smtp_accounts = wp_tmq_get_available_smtp();

    // Track diagnostics
    $diag['queue_total']    = $mailjobsTotal;
    $diag['queue_batch']    = $mailsInQueue;
    $diag['smtp_accounts']  = count( $smtp_accounts );
    $diag['send_method']    = isset( $wp_tmq_options['send_method'] ) ? $wp_tmq_options['send_method'] : 'auto';
    $diag['sent']           = 0;
    $diag['errors']         = 0;

    // Send Mails in Queue
    if ($mailsInQueue > 0) {
        foreach($mailjobIds as $mail_id) {
                // Load full row on demand (keeps only one email body in memory at a time)
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `id` = %d", $mail_id ), ARRAY_A );
                if ( ! $item ) { continue; }
                if ( ! empty( $item['recipient'] ) ) { $to = wp_tmq_decode($item['recipient']); } else { $to = $wp_tmq_options['email']; $item['subject'] = __( 'ERROR', 'total-mail-queue' ) . ' // '.$item['subject']; }
                if ( ! empty( $item['headers'] ) ) { $headers = wp_tmq_decode($item['headers']); } else { $headers = ''; }
                if ( ! empty( $item['attachments'] ) ) { $attachments = wp_tmq_decode($item['attachments']); } else { $attachments = ''; }
                $wp_tmq_mailid = $item['id'];

                // Extract captured phpmailer config from headers if present
                $captured_phpmailer_config = null;
                if ( is_array( $headers ) ) {
                    foreach ( $headers as $hindex => $hval ) {
                        if ( preg_match( '/^X-TMQ-PHPMailer-Config: (.+)$/i', $hval, $matches ) ) {
                            $captured_phpmailer_config = wp_tmq_decode( base64_decode( trim( $matches[1] ) ) );
                            array_splice( $headers, $hindex, 1 );
                            break;
                        }
                    }
                }

                // Determine send method
                $send_method = isset( $wp_tmq_options['send_method'] ) ? $wp_tmq_options['send_method'] : 'auto';

                // Find available SMTP account from in-memory list (skip if send_method is 'php')
                $smtp_to_use = null;
                if ( $send_method !== 'php' && ! empty( $smtp_accounts ) ) {
                    $smtp_to_use = wp_tmq_pick_available_smtp( $smtp_accounts );
                }

                // In 'smtp' mode, if no SMTP account is available, skip remaining emails
                if ( $send_method === 'smtp' && ! $smtp_to_use ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update(
                        $tableName,
                        array( 'info' => __( 'Waiting: no SMTP account available (check if accounts are enabled and limits are not exceeded).', 'total-mail-queue' ) ),
                        array( 'id' => $item['id'] ),
                        array( '%s' ),
                        array( '%d' )
                    );
                    $diag['result'] = 'smtp_unavailable';
                    break; // No SMTP available — no point trying other emails in this batch
                }

                // Configure phpmailer_init hook for this email
                $tmq_phpmailer_hook = null;
                if ( $smtp_to_use ) {
                    $tmq_phpmailer_hook = function( $phpmailer ) use ( $smtp_to_use ) {
                        wp_tmq_configure_phpmailer( $phpmailer, $smtp_to_use );
                    };
                    add_action( 'phpmailer_init', $tmq_phpmailer_hook, 999999 );
                } else if ( $send_method === 'auto' && $captured_phpmailer_config && is_array( $captured_phpmailer_config ) ) {
                    // Replay captured phpmailer config only in 'auto' mode
                    $tmq_phpmailer_hook = function( $phpmailer ) use ( $captured_phpmailer_config ) {
                        if ( method_exists( $phpmailer, 'smtpClose' ) ) {
                            $phpmailer->smtpClose();
                        }
                        foreach ( $captured_phpmailer_config as $prop => $val ) {
                            if ( property_exists( $phpmailer, $prop ) ) {
                                if ( $prop === 'Password' ) {
                                    $val = wp_tmq_decrypt_password( $val );
                                }
                                $phpmailer->$prop = $val;
                            }
                        }
                        // Apply configurable SMTP timeout
                        global $wp_tmq_options;
                        $smtp_timeout = intval( $wp_tmq_options['smtp_timeout'] );
                        if ( $smtp_timeout > 0 ) {
                            $phpmailer->Timeout = $smtp_timeout;
                        }
                    };
                    add_action( 'phpmailer_init', $tmq_phpmailer_hook, 999999 );
                }

                // Remove ALL other pre_wp_mail filters to prevent conflicts, keep only ours to re-add later
                global $wp_filter;
                $saved_pre_wp_mail = null;
                if ( isset( $wp_filter['pre_wp_mail'] ) ) {
                    $saved_pre_wp_mail = clone $wp_filter['pre_wp_mail'];
                }
                remove_all_filters( 'pre_wp_mail' );

                $sendstatus = wp_mail($to,$item['subject'],$item['message'],$headers,$attachments); // Finally sends the email for real

                // Restore all pre_wp_mail filters that were present before
                if ( $saved_pre_wp_mail ) {
                    $wp_filter['pre_wp_mail'] = $saved_pre_wp_mail;
                }
                // Note: we do NOT re-add wp_tmq_prewpmail here — during cron
                // it was never registered, and re-adding it would intercept
                // subsequent wp_mail() calls in this batch.

                // Remove temporary phpmailer hook
                if ( $tmq_phpmailer_hook ) {
                    remove_action( 'phpmailer_init', $tmq_phpmailer_hook, 999999 );
                }

                if ($sendstatus) {
                    $sent_smtp_id = $smtp_to_use ? intval( $smtp_to_use['id'] ) : 0;
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update(
                        $tableName,
                        array( 'timestamp' => current_time( 'mysql', false ), 'status' => 'sent', 'info' => '', 'smtp_account_id' => $sent_smtp_id ),
                        array( 'id' => $item['id'] ),
                        array( '%s', '%s', '%s', '%d' ),
                        array( '%d' )
                    );
                    // Increment SMTP counter (DB for persistence + in-memory for next iteration)
                    if ( $smtp_to_use ) {
                        wp_tmq_increment_smtp_counter( $smtp_to_use['id'] );
                        wp_tmq_update_memory_counter( $smtp_accounts, $smtp_to_use['id'] );
                    }
                    $diag['sent']++;
                } else {
                    $diag['errors']++;
                    // If wp_mail_failed hook didn't update info, write a fallback diagnostic
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $current_info = $wpdb->get_var( $wpdb->prepare( "SELECT `info` FROM `$tableName` WHERE `id` = %d", $item['id'] ) );
                    if ( empty( $current_info ) ) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->update(
                            $tableName,
                            array( 'info' => __( 'wp_mail() returned false. Check for conflicting email plugins or server mail configuration.', 'total-mail-queue' ) ),
                            array( 'id' => $item['id'] ),
                            array( '%s' ),
                            array( '%d' )
                        );
                    }
                }
                if (is_array($attachments)) {
                    global $wp_filesystem;
                    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
                        include_once(ABSPATH . 'wp-admin/includes/file.php');
                        WP_Filesystem();
                    }
                    $attachmentfolder = pathinfo($attachments[0]);
                    $wp_filesystem->delete($attachmentfolder['dirname'],true,'d');
                }
            }
    }

    // Delete old logs (by date)
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare( "DELETE FROM `$tableName` WHERE `status` != 'queue' AND `status` != 'high' AND `timestamp` < DATE_SUB(%s, INTERVAL %d HOUR)", current_time( 'mysql', false ), intval( $wp_tmq_options['clear_queue'] ) ) );

    // Delete excess log entries (by total records limit)
    $log_max = intval( $wp_tmq_options['log_max_records'] );
    if ( $log_max > 0 ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_log = $wpdb->get_var( "SELECT COUNT(*) FROM `$tableName` WHERE `status` != 'queue' AND `status` != 'high'" );
        if ( $total_log > $log_max ) {
            $excess = $total_log - $log_max;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM `$tableName` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `timestamp` ASC LIMIT %d",
                intval( $excess )
            ) );
        }
    }

    // Release cross-process lock (also released by shutdown function as safety net)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->get_var( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );

    // Save cron diagnostics
    if ( ! isset( $diag['result'] ) ) {
        $diag['result'] = 'ok';
    }
    update_option( 'wp_tmq_last_cron', $diag, false );

}
add_action('wp_tmq_mail_queue_hook','wp_tmq_search_mail_from_queue');

// Custom Cron Interval
function wp_tmq_cron_interval( $schedules ) {
    global $wp_tmq_options;
    $schedules['wp_tmq_interval'] = array(
        'interval' => $wp_tmq_options['queue_interval'],
        'display'  => esc_html__('Total Mail Queue', 'total-mail-queue'), );
    return $schedules;
}
add_filter('cron_schedules','wp_tmq_cron_interval');

// Set or Remove Cron (enabled=1 needs cron, enabled=2 block mode doesn't need cron)
$wp_tmq_next_cron_timestamp = wp_next_scheduled('wp_tmq_mail_queue_hook');
if ($wp_tmq_next_cron_timestamp && $wp_tmq_options['enabled'] !== '1') {
    wp_unschedule_event($wp_tmq_next_cron_timestamp,'wp_tmq_mail_queue_hook');
} else if (!$wp_tmq_next_cron_timestamp && $wp_tmq_options['enabled'] === '1') {
    wp_schedule_event(time(),'wp_tmq_interval','wp_tmq_mail_queue_hook');
}



/* ***************************************************************
Install/Uninstall/Upgrade
**************************************************************** */


/* Delete plugin options and database table */
function wp_tmq_uninstall () {
    global $wpdb;

    $optionName = 'wp_tmq_settings';
    delete_option( $optionName );

    $optionName = 'wp_tmq_version';
    delete_option( $optionName );

    delete_option( 'wp_tmq_last_cron' );

    $tableName = $wpdb->prefix . 'total_mail_queue';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS `$tableName`" );

    $smtpTableName = $wpdb->prefix . 'total_mail_queue_smtp';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS `$smtpTableName`" );

    // Clean up attachments directories (new location in uploads + legacy in plugin dir)
    global $wp_filesystem;
    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
        include_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }
    $upload_dir = wp_upload_dir();
    $new_path = trailingslashit( $upload_dir['basedir'] ) . 'tmq-attachments/';
    if ( is_dir( $new_path ) ) {
        $wp_filesystem->delete( $new_path, true, 'd' );
    }
    $legacy_path = plugin_dir_path( __FILE__ ) . 'attachments/';
    if ( is_dir( $legacy_path ) ) {
        $wp_filesystem->delete( $legacy_path, true, 'd' );
    }
}

/* Delete Cron when Plugin deactivated */
function wp_tmq_deactivate() {
    wp_clear_scheduled_hook( 'wp_tmq_mail_queue_hook' );
}

/* Create/Upgrade MySQL Table on Activation/Upgrade: https://codex.wordpress.org/Creating_Tables_with_Plugins */
function wp_tmq_updateDatabaseTables() {
    global $wpdb, $wp_tmq_version;

    $charset_collate = $wpdb->get_charset_collate();

    // Main queue table
    $tableName = $wpdb->prefix.'total_mail_queue';
    $sql = "CREATE TABLE $tableName (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    timestamp TIMESTAMP NOT NULL,
    status varchar(55) DEFAULT '' NOT NULL,
    recipient varchar(255) DEFAULT '' NOT NULL,
    subject varchar(255) DEFAULT '' NOT NULL,
    message mediumtext NOT NULL,
    headers text NOT NULL,
    attachments text NOT NULL,
    info varchar(255) DEFAULT '' NOT NULL,
    retry_count smallint(5) DEFAULT 0 NOT NULL,
    smtp_account_id mediumint(9) DEFAULT 0 NOT NULL,
    PRIMARY KEY  (id),
    KEY idx_status_retry (status, retry_count, id),
    KEY idx_status_timestamp (status, timestamp)
    ) $charset_collate;";

    // SMTP accounts table
    $smtpTableName = $wpdb->prefix.'total_mail_queue_smtp';
    $sql .= "CREATE TABLE $smtpTableName (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(100) DEFAULT '' NOT NULL,
    host varchar(255) DEFAULT '' NOT NULL,
    port smallint(5) DEFAULT 587 NOT NULL,
    encryption varchar(10) DEFAULT 'tls' NOT NULL,
    auth tinyint(1) DEFAULT 1 NOT NULL,
    username varchar(255) DEFAULT '' NOT NULL,
    password text NOT NULL,
    from_email varchar(255) DEFAULT '' NOT NULL,
    from_name varchar(255) DEFAULT '' NOT NULL,
    priority mediumint(9) DEFAULT 0 NOT NULL,
    daily_limit int(11) DEFAULT 0 NOT NULL,
    monthly_limit int(11) DEFAULT 0 NOT NULL,
    daily_sent int(11) DEFAULT 0 NOT NULL,
    monthly_sent int(11) DEFAULT 0 NOT NULL,
    last_daily_reset date DEFAULT '2000-01-01' NOT NULL,
    last_monthly_reset date DEFAULT '2000-01-01' NOT NULL,
    enabled tinyint(1) DEFAULT 1 NOT NULL,
    send_interval int(11) DEFAULT 0 NOT NULL,
    send_bulk int(11) DEFAULT 0 NOT NULL,
    last_sent_at datetime DEFAULT '2000-01-01 00:00:00' NOT NULL,
    cycle_sent int(11) DEFAULT 0 NOT NULL,
    PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    update_option( 'wp_tmq_version', $wp_tmq_version, /*autoload*/true );
}

/* Update database on activation */
function wp_tmq_activate() {
    wp_tmq_updateDatabaseTables();
}
register_activation_hook( __FILE__, 'wp_tmq_activate' );
register_deactivation_hook( __FILE__, 'wp_tmq_deactivate' );
register_uninstall_hook( __FILE__, 'wp_tmq_uninstall' );

/* Upgrade routine: check for mismatching version numbers and run database update if necessary */
function wp_tmq_check_update_db () {
    global $wp_tmq_version;
    if ( get_option( 'wp_tmq_version' ) !== $wp_tmq_version ) {
        wp_tmq_updateDatabaseTables();
    }
}
add_action( 'plugins_loaded', 'wp_tmq_check_update_db', 10, 0 );




/* ***************************************************************
Options Page
**************************************************************** */
if (is_admin()) {
    require_once( plugin_dir_path( __FILE__ ) . 'total-mail-queue-options.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'total-mail-queue-smtp.php' );
}




/* ***************************************************************
SMTP Test Connection (AJAX)
**************************************************************** */

function wp_tmq_ajax_test_smtp_connection() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'total-mail-queue' ) ), 403 );
    }

    if ( ! check_ajax_referer( 'wp_tmq_test_smtp', '_nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'total-mail-queue' ) ), 403 );
    }

    $host       = sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) );
    $port       = intval( $_POST['port'] ?? 587 );
    $encryption = sanitize_key( $_POST['encryption'] ?? 'tls' );
    $auth       = intval( $_POST['auth'] ?? 0 );
    $username   = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password must preserve special characters
    $password   = wp_unslash( $_POST['password'] ?? '' );
    $smtp_id    = intval( $_POST['smtp_id'] ?? 0 );

    if ( empty( $host ) ) {
        wp_send_json_error( array( 'message' => __( 'SMTP host is required.', 'total-mail-queue' ) ) );
    }

    // If password is empty and we are editing an existing account, use the stored password
    if ( $password === '' && $smtp_id > 0 ) {
        global $wpdb, $wp_tmq_options;
        $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stored = $wpdb->get_var( $wpdb->prepare( "SELECT `password` FROM `$smtpTable` WHERE `id` = %d", $smtp_id ) );
        if ( $stored ) {
            $password = wp_tmq_decrypt_password( $stored );
        }
    }

    // Use WordPress built-in PHPMailer
    global $phpmailer;
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer( true );

    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        $mail->SMTPAuth   = (bool) $auth;
        if ( $auth ) {
            $mail->Username = $username;
            $mail->Password = $password;
        }
        $mail->Timeout = 15;

        $mail->smtpConnect();
        $mail->smtpClose();

        wp_send_json_success( array( 'message' => __( 'Connection successful! SMTP server responded correctly.', 'total-mail-queue' ) ) );

    } catch ( PHPMailer\PHPMailer\Exception $e ) {
        wp_send_json_error( array( 'message' => $mail->ErrorInfo ) );
    } catch ( \Exception $e ) {
        wp_send_json_error( array( 'message' => $e->getMessage() ) );
    }
}
add_action( 'wp_ajax_wp_tmq_test_smtp', 'wp_tmq_ajax_test_smtp_connection' );


/* ***************************************************************
REST API
**************************************************************** */


function wp_tmq_add_rest_endpoints () {
    register_rest_route('tmq/v1', '/message/(?P<id>[\d]+)', array(
        'methods'             => 'GET',
        'callback'            => 'wp_tmq_rest_get_message',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ));
}
add_action('rest_api_init', 'wp_tmq_add_rest_endpoints', 10, 0);


function wp_tmq_rest_get_message ($request) {
    global $wpdb, $wp_tmq_options;
    $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
    $id        = intval($request['id']);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $row       = $wpdb->get_row( $wpdb->prepare("SELECT * FROM `$tableName` WHERE `id` = %d", $id ), ARRAY_A );
    if ($row) {
        // Search for content-type header to detect html emails
        $is_content_type_html = false;
        $headers = wp_tmq_decode( $row['headers'] );
        if (is_string($headers)) {
            $headers = [ $headers ];
        } else if (!is_array($headers)) {
            $headers = [];
        }
        foreach ( $headers as $header )  {
            if ( preg_match( '/content-type: ?text\/html/i', $header ) ) {
                $is_content_type_html = true;
                break;
            }
        }
        return array(
            'status' => 'ok',
            'data'   => array(
                'html'   => wp_tmq_render_list_message(wp_tmq_decode($row['message']),$is_content_type_html),
            ),
        );
    } else {
        return new WP_Error( 'no_message', __( 'Message not found', 'total-mail-queue' ), array( 'status' => 404 ) );
    }
}

function wp_tmq_render_list_message ($message, $is_content_type_html) {
    // Split html emails into parts and extract plain text preview
    $parts   = explode( '<body', $message );
    $is_html = $is_content_type_html || count($parts) > 1;
    if ($is_html) {
        if (count($parts) > 1) {
            $header = $parts[0];
            $body   = '<body'.$parts[1];
        } else {
            $header = '';
            $body   = $parts[0];
        }
        $parts = explode('</body>', $body);
        if (count($parts) > 1) {
            $body   = $parts[0].'</body>';
            $footer = $parts[1];
        } else {
            $body   = $parts[0];
            $footer = '';
        }
        if (!function_exists('convert_html_to_text'))  {
            require_once __DIR__.'/lib/html2text/html2text.php';
        }
        // ignore warnings when converting html containing non-converted HTML entities
        $internal_errors = libxml_use_internal_errors(true);
        $text            = convert_html_to_text( $body );
        libxml_use_internal_errors($internal_errors);
    } else {
        $text   = $message;
        $header = '';
        $body   = '';
        $footer = '';
    }
    $html  = '';
    $html .= '<details class="tmq-email-source-meta" open><summary>' . esc_html__( 'Text', 'total-mail-queue' ) . '</summary><pre class="tmq-email-plain-text">'.esc_html( $text ).'</pre></details>';
    $html .= $header ? '<details class="tmq-email-source-meta"><summary>' . esc_html__( 'HTML Header', 'total-mail-queue' ) . '</summary><pre>'.esc_html( wp_tmq_render_html_for_display($header) ).'</pre></details>' : '';
    $html .= $body   ? '<details class="tmq-email-source-meta"><summary>' . esc_html__( 'HTML Body', 'total-mail-queue' ) . '</summary><pre>'.esc_html( wp_tmq_render_html_for_display($body) ).'</pre></details>' : '';
    $html .= $footer ? '<details class="tmq-email-source-meta"><summary>' . esc_html__( 'HTML Footer', 'total-mail-queue' ) . '</summary><pre>'.esc_html( wp_tmq_render_html_for_display($footer) ).'</pre></details>' : '';
    return $html;
}

function wp_tmq_render_html_for_display ($html) {
    $html = preg_replace( '/;base64,[^"\']+("|\')+/', ';base64, [...] $1', $html );
    return $html;
}
