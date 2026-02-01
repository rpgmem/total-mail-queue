<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ***************************************************************
Options Page
**************************************************************** */

function wp_tmq_actionlinks ( $actions ) {
    $links = array(
       '<a href="'.esc_url( admin_url('admin.php?page=wp_tmq_mail_queue') ).'">' . esc_html__( 'Settings', 'total-mail-queue' ) . '</a>',
       '<a href="'.esc_url( admin_url('admin.php?page=wp_tmq_mail_queue-tab-log') ).'">' . esc_html__( 'Log', 'total-mail-queue' ) . '</a>',
       '<a href="'.esc_url( admin_url('admin.php?page=wp_tmq_mail_queue-tab-queue') ).'">' . esc_html__( 'Retention', 'total-mail-queue' ) . '</a>',
       '<a href="'.esc_url( admin_url('admin.php?page=wp_tmq_mail_queue-tab-smtp') ).'">' . esc_html__( 'SMTP', 'total-mail-queue' ) . '</a>',
       '<a href="'.esc_url( admin_url('admin.php?page=wp_tmq_mail_queue-tab-faq') ).'">' . esc_html__( 'FAQ', 'total-mail-queue' ) . '</a>',
    );
    return array_merge($actions,$links );
}
add_filter('plugin_action_links_total-mail-queue/total-mail-queue.php','wp_tmq_actionlinks');

// Options Page
function wp_tmq_settings_page_menuitem() {
    add_menu_page('Total Mail Queue','Total Mail Queue','manage_options','wp_tmq_mail_queue','wp_tmq_settings_page','data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTc4IiBoZWlnaHQ9IjE3OCIgdmlld0JveD0iMCAwIDE3OCAxNzgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNNzguNzI0MSA1LjI1MzA5Qzc2LjkwNzQgNC43MDgxIDc0Ljk0MDEgNS4wNTQxMyA3My40MTg0IDYuMTg2MjlDNzEuODk2OCA3LjMxODQ1IDcxIDkuMTAzNDEgNzEgMTEuMDAwMVYxNjdDNzEgMTY4Ljg5NyA3MS44OTY4IDE3MC42ODIgNzMuNDE4NCAxNzEuODE0Qzc0Ljk0MDEgMTcyLjk0NiA3Ni45MDc0IDE3My4yOTIgNzguNzI0MSAxNzIuNzQ3TDE1OC43MjQgMTQ4Ljc0N0MxNjEuMjYyIDE0Ny45ODYgMTYzIDE0NS42NSAxNjMgMTQzVjM1QzE2MyAzMi4zNTA0IDE2MS4yNjIgMzAuMDE0NSAxNTguNzI0IDI5LjI1MzFMNzguNzI0MSA1LjI1MzA5Wk04NS43ODg3IDIyLjM4NDZDODcuNzg1NCAyMS40Mzk0IDkwLjE3MDMgMjIuMjkxOSA5MS4xMTU0IDI0LjI4ODdMMTIyLjg1MiA5MS4zMzc4TDE0My4zMzQgNDQuNDAwMkMxNDQuMjE3IDQyLjM3NTUgMTQ2LjU3NSA0MS40NTAzIDE0OC42IDQyLjMzMzhDMTUwLjYyNSA0My4yMTc0IDE1MS41NSA0NS41NzUgMTUwLjY2NiA0Ny41OTk4TDEyNi42NjYgMTAyLjZDMTI2LjAzOSAxMDQuMDM3IDEyNC42MjkgMTA0Ljk3NiAxMjMuMDYxIDEwNUMxMjEuNDkzIDEwNS4wMjQgMTIwLjA1NiAxMDQuMTI5IDExOS4zODUgMTAyLjcxMUw4My44ODQ2IDI3LjcxMTNDODIuOTM5NCAyNS43MTQ2IDgzLjc5MTkgMjMuMzI5NyA4NS43ODg3IDIyLjM4NDZaIiBmaWxsPSIjYTdhYWFkIi8+CjxwYXRoIGQ9Ik00OSAxM0M1Mi4zMTM3IDEzIDU1IDE1LjY4NjMgNTUgMTlWMTU5QzU1IDE2Mi4zMTQgNTIuMzEzNyAxNjUgNDkgMTY1QzQ1LjY4NjMgMTY1IDQzIDE2Mi4zMTQgNDMgMTU5VjE5QzQzIDE1LjY4NjMgNDUuNjg2MyAxMyA0OSAxM1oiIGZpbGw9IiNhN2FhYWQiLz4KPHBhdGggZD0iTTIxIDIxQzI0LjMxMzcgMjEgMjcgMjMuNjg2MyAyNyAyN1YxNTFDMjcgMTU0LjMxNCAyNC4zMTM3IDE1NyAyMSAxNTdDMTcuNjg2MyAxNTcgMTUgMTU0LjMxNCAxNSAxNTFWMjdDMTUgMjMuNjg2MyAxNy42ODYzIDIxIDIxIDIxWiIgZmlsbD0iI2E3YWFhZCIvPgo8L3N2Zz4=');
    add_submenu_page('wp_tmq_mail_queue', __( 'Settings', 'total-mail-queue' ), __( 'Settings', 'total-mail-queue' ),'manage_options','wp_tmq_mail_queue','wp_tmq_settings_page');
    add_submenu_page('wp_tmq_mail_queue', __( 'Log', 'total-mail-queue' ), __( 'Log', 'total-mail-queue' ),'manage_options','wp_tmq_mail_queue-tab-log','wp_tmq_settings_page');
    add_submenu_page('wp_tmq_mail_queue', __( 'Retention', 'total-mail-queue' ), __( 'Retention', 'total-mail-queue' ),'manage_options','wp_tmq_mail_queue-tab-queue','wp_tmq_settings_page');
    add_submenu_page('wp_tmq_mail_queue', __( 'SMTP Accounts', 'total-mail-queue' ), __( 'SMTP Accounts', 'total-mail-queue' ),'manage_options','wp_tmq_mail_queue-tab-smtp','wp_tmq_settings_page');
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        add_submenu_page('wp_tmq_mail_queue', __( 'Cron Information', 'total-mail-queue' ), __( 'Cron Information', 'total-mail-queue' ),'manage_options','wp_tmq_mail_queue-tab-croninfo','wp_tmq_settings_page');
    }
    add_submenu_page('wp_tmq_mail_queue', __( 'FAQ', 'total-mail-queue' ), __( 'FAQ', 'total-mail-queue' ),'manage_options','wp_tmq_mail_queue-tab-faq','wp_tmq_settings_page');
}
add_action('admin_menu','wp_tmq_settings_page_menuitem');

function wp_tmq_settings_page_assets () {
    global $wp_tmq_version;
    $screen = get_current_screen();
    if ( preg_match( '#wp_tmq_mail_queue#', $screen->base ) ) {
        wp_enqueue_style( 'wp_tmq_style', plugins_url( 'assets/css/admin.css', __FILE__ ), [], $wp_tmq_version );
        wp_enqueue_script( 'wp_tmq_script', plugins_url( 'assets/js/tmq-admin.js', __FILE__ ), [ 'jquery' ], $wp_tmq_version, true );
		wp_add_inline_script( 'wp_tmq_script', wp_tmq_settings_page_inline_script(), 'before' );
    }
}
add_action( 'admin_enqueue_scripts', 'wp_tmq_settings_page_assets' );

// Options Page Script
function wp_tmq_settings_page_inline_script () {
    $d  = '';
    $d .= '( function ( global ) {';
    $d .=   '"use strict";';
    $d .=   'const tmq = global.tmq = global.tmq || {};';
    $d .=   'tmq.restUrl = "'.esc_url( wp_make_link_relative( rest_url() ) ).'";';
    $d .=   'tmq.restNonce = "'.esc_html( wp_create_nonce( 'wp_rest' ) ).'";';
    $d .=   'tmq.i18n = tmq.i18n || {};';
    $d .=   'tmq.i18n.errorLoadingMessage = "'.esc_js( __( 'There was an error loading the message.', 'total-mail-queue' ) ).'";';
    $d .=   'tmq.i18n.confirmDelete = "'.esc_js( __( 'Are you sure you want to delete the selected items? This action cannot be undone.', 'total-mail-queue' ) ).'";';
    $d .=   'tmq.i18n.testing = "'.esc_js( __( 'Testing...', 'total-mail-queue' ) ).'";';
    $d .=   'tmq.i18n.testConnection = "'.esc_js( __( 'Test Connection', 'total-mail-queue' ) ).'";';
    $d .=   'tmq.ajaxUrl = "'.esc_url( admin_url( 'admin-ajax.php' ) ).'";';
    $d .=   'tmq.testSmtpNonce = "'.esc_js( wp_create_nonce( 'wp_tmq_test_smtp' ) ).'";';
    $d .= '}) ( this );';
    return $d;
}

// Handle export early (before any output) via admin_init
function wp_tmq_maybe_handle_export() {
    if ( ! isset( $_POST['wp_tmq_export'] ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( ! isset( $_POST['wp_tmq_export_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp_tmq_export_nonce'] ), 'wp_tmq_export' ) ) {
        wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
    }
    wp_tmq_handle_export();
}
add_action( 'admin_init', 'wp_tmq_maybe_handle_export' );

// Options Page Settings
function wp_tmq_settings_page() {

    // Only Admins
    if ( ! current_user_can( 'manage_options' ) ) { return; }

    // Settings
    global $wp_tmq_options;

    // Handle import
    $import_notice = '';
    if ( isset( $_POST['wp_tmq_import'] ) ) {
        if ( ! isset( $_POST['wp_tmq_import_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp_tmq_import_nonce'] ), 'wp_tmq_import' ) ) {
            wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
        }
        $import_notice = wp_tmq_handle_import();
    }

    // Get the active tab from the $_GET param
    $tab = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'wp_tmq_mail_queue';

    echo '<div class="wrap">';

    // Options Header
    echo '<h1 class="tmq-title"><img class="tmq-logo" src="'.esc_url(plugins_url('assets/img/total-mail-queue-logo-wordmark.svg', __FILE__)).'" alt="' . esc_attr__( 'Total Mail Queue', 'total-mail-queue' ) . '" width="308" height="56" /></h1>';
    if ($tab !== 'wp_tmq_mail_queue-tab-croninfo' ) {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<div class="notice notice-warning notice-large">';
            $url = esc_url(get_option('siteurl').'/wp-cron.php');
            /* translators: %s: wp-cron.php URL */
            echo '<p><strong>' . esc_html__( 'Please note:', 'total-mail-queue' ) . '</strong><br />' . wp_kses_post( sprintf( __( 'Your normal WP Cron is disabled. Please make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ), '<a href="'.$url.'" target="_blank">'.$url.'</a>' ) ) . '</p>';
            echo '<p><a href="?page=wp_tmq_mail_queue-tab-croninfo">' . esc_html__( 'More information', 'total-mail-queue' ) . '</a></p>';
            echo '</div>';
        }
    }
    wp_tmq_settings_page_navi($tab); // Tabs

    // Options Page Content
    if ($tab === 'wp_tmq_mail_queue') {
        echo '<form action="options.php" method="post">';
        settings_fields('wp_tmq_settings');
        do_settings_sections('wp_tmq_settings_page');
        submit_button();
        echo '</form>';

        // Export / Import section
        echo '<hr />';
        echo '<h2>' . esc_html__( 'Export / Import', 'total-mail-queue' ) . '</h2>';

        if ( $import_notice ) {
            echo wp_kses_post( $import_notice );
        }

        echo '<div class="tmq-export-import">';

        // Export
        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'Export', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . esc_html__( 'Download an XML file with all plugin settings and SMTP accounts. Passwords are included in encrypted form and can only be imported on a site with the same WordPress salt keys.', 'total-mail-queue' ) . '</p>';
        echo '<form method="post">';
        wp_nonce_field( 'wp_tmq_export', 'wp_tmq_export_nonce' );
        echo '<button type="submit" name="wp_tmq_export" class="button button-primary">' . esc_html__( 'Export Settings', 'total-mail-queue' ) . '</button>';
        echo '</form>';
        echo '</div>';

        // Import
        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'Import', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . esc_html__( 'Upload a previously exported XML file to restore settings and SMTP accounts. This will replace all current settings and SMTP accounts.', 'total-mail-queue' ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( 'wp_tmq_import', 'wp_tmq_import_nonce' );
        echo '<input type="file" name="wp_tmq_import_file" accept=".xml" required /> ';
        echo '<button type="submit" name="wp_tmq_import" class="button" onclick="return confirm(\'' . esc_js( __( 'This will replace all current settings and SMTP accounts. Continue?', 'total-mail-queue' ) ) . '\');">' . esc_html__( 'Import Settings', 'total-mail-queue' ) . '</button>';
        echo '</form>';
        echo '</div>';

        echo '</div>';

    } else if ($tab === 'wp_tmq_mail_queue-tab-log') {
        echo '<form method="post">';
        $logtable = new wp_tmq_Log_Table();
        $logtable->prepare_items();
        $logtable->display();
        echo '</form>';
    } else if ($tab === 'wp_tmq_mail_queue-tab-queue') {

        if ( isset( $_GET['resent'] ) ) {
            $resent_count = intval( $_GET['resent'] );
            if ( $resent_count > 0 ) {
                /* translators: %d: number of emails resent */
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%d email(s) have been added back to the queue for resending.', 'total-mail-queue' ), $resent_count ) ) . '</p></div>';
            }
        }

        if ( isset( $_GET['addtestmail'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp_tmq_addtestmail' ) ) {
            global $wpdb;
            $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
            $data = array(
                'timestamp'=>current_time('mysql',false),
                'recipient'=>$wp_tmq_options['email'],
                /* translators: %s: timestamp */
                'subject'=> sprintf( __( 'Testmail #%s', 'total-mail-queue' ), time() ),
                'message'=> __( 'This is just a test email sent by the Total Mail Queue plugin.', 'total-mail-queue' ),
                'status' => 'queue'
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($tableName,$data);
        }

        // Show mode-specific notices
        if ( $wp_tmq_options['enabled'] === '2' ) {
            /* translators: %1$s: opening link tag, %2$s: closing link tag */
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Block Mode Active', 'total-mail-queue' ) . '</strong> — ' . esc_html__( 'All outgoing emails are being retained and will NOT be sent. No emails will leave this server.', 'total-mail-queue' ) . ' ' . wp_kses_post( sprintf( __( 'Change this in %1$sSettings%2$s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue">', '</a>' ) ) . '</p></div>';
        } else if ( $wp_tmq_options['enabled'] === '1' ) {
            $next_cron_timestamp = wp_next_scheduled('wp_tmq_mail_queue_hook');
            if ($next_cron_timestamp) {
                if ($next_cron_timestamp > time()) {
                    /* translators: %1$s: human-readable time diff, %2$s: scheduled time */
                    echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Next sending will be triggered in %1$s at %2$s.', 'total-mail-queue' ), human_time_diff( $next_cron_timestamp ), wp_date( 'H:i', $next_cron_timestamp ) ) ) . '</p></div>';
                }
            }
            // Show last cron run diagnostics
            $last_cron = get_option( 'wp_tmq_last_cron' );
            if ( $last_cron && is_array( $last_cron ) ) {
                $diag_parts = array();
                $diag_parts[] = '<strong>' . esc_html__( 'Last cron run:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['time'] ?? '—' );
                $diag_parts[] = '<strong>' . esc_html__( 'Result:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['result'] ?? '—' );
                if ( isset( $last_cron['queue_total'] ) ) {
                    $diag_parts[] = '<strong>' . esc_html__( 'Queue:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['queue_total'] ) . ' total, ' . esc_html( $last_cron['queue_batch'] ) . ' batch';
                    $diag_parts[] = '<strong>' . esc_html__( 'SMTP accounts:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['smtp_accounts'] );
                    $diag_parts[] = '<strong>' . esc_html__( 'Send method:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['send_method'] );
                    $diag_parts[] = '<strong>' . esc_html__( 'Sent:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['sent'] ) . ' | <strong>' . esc_html__( 'Errors:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['errors'] );
                }
                $notice_class = ( $last_cron['result'] ?? '' ) === 'ok' ? 'notice-info' : 'notice-warning';
                echo '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . wp_kses_post( implode( ' &nbsp;|&nbsp; ', $diag_parts ) ) . '</p></div>';
            }
        } else {
            /* translators: %1$s: opening link tag, %2$s: closing link tag */
            echo '<div class="notice notice-warning"><p>' . wp_kses_post( sprintf( __( 'The plugin is currently disabled. Enable it in the %1$sSettings%2$s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue">', '</a>' ) ) . '</p></div>';
        }

        // Warn about conflicting email plugins
        if ( $wp_tmq_options['enabled'] === '1' ) {
            global $wp_filter;
            if ( isset( $wp_filter['pre_wp_mail'] ) ) {
                $other_filters = array();
                foreach ( $wp_filter['pre_wp_mail']->callbacks as $priority => $callbacks ) {
                    foreach ( $callbacks as $id => $callback ) {
                        $func = $callback['function'];
                        if ( is_string( $func ) && $func === 'wp_tmq_prewpmail' ) { continue; }
                        if ( is_array( $func ) ) {
                            $other_filters[] = ( is_object( $func[0] ) ? get_class( $func[0] ) : $func[0] ) . '::' . $func[1];
                        } else if ( is_string( $func ) ) {
                            $other_filters[] = $func;
                        } else {
                            $other_filters[] = __( '(closure)', 'total-mail-queue' );
                        }
                    }
                }
                if ( ! empty( $other_filters ) ) {
                    echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Warning: conflicting email plugin detected.', 'total-mail-queue' ) . '</strong> ';
                    echo wp_kses_post( sprintf(
                        /* translators: %s: list of filter names */
                        __( 'The following filter(s) on %1$s may interfere with email sending: %2$s. Consider deactivating conflicting email plugins when using SMTP accounts from Total Mail Queue.', 'total-mail-queue' ),
                        '<code>pre_wp_mail</code>',
                        '<code>' . esc_html( implode( '</code>, <code>', $other_filters ) ) . '</code>'
                    ) );
                    echo '</p></div>';
                }
            }
        }

        echo '<form method="post" action="admin.php?page=wp_tmq_mail_queue-tab-queue">';
        $queuetable = new wp_tmq_Log_Table();
        $queuetable->prepare_items();
        $queuetable->display();
        echo '</form>';
    } else if ($tab === 'wp_tmq_mail_queue-tab-faq') {
        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'How does this Plugin work?', 'total-mail-queue' ) . '</h3>';
        /* translators: %1$s: wp_mail() link, %2$s: WP Cron label */
        echo '<p>' . wp_kses_post( sprintf( __( 'If enabled this plugin intercepts the %1$s function. Instead of sending the mails directly, it stores them in the database and sends them step by step with a delay during the %2$s.', 'total-mail-queue' ), '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>', '<i>WP Cron</i>' ) ) . '</p>';
        echo '<p>' . esc_html__( 'Current state:', 'total-mail-queue' ) . ' ';
        if ($wp_tmq_options['enabled'] === '1') {
            /* translators: %1$s: wp_mail() link, %2$s: opening Queue link tag, %3$s: closing link tag */
            echo '<b class="tmq-ok">' . esc_html__( 'The plugin is enabled', 'total-mail-queue' ) . '</b> ' . wp_kses_post( sprintf( __( 'All Mails sent through %1$s are delayed by the %2$sQueue%3$s.', 'total-mail-queue' ), '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>', '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) );
        } else if ($wp_tmq_options['enabled'] === '2') {
            echo '<b class="tmq-warning">' . esc_html__( 'Block mode is active', 'total-mail-queue' ) . '</b>. ' . esc_html__( 'All outgoing emails are being retained and will NOT be sent.', 'total-mail-queue' );
        } else {
            echo '<b>' . esc_html__( 'The plugin is disabled', 'total-mail-queue' ) . '</b>. ' . esc_html__( 'The plugin has no impact at the moment, no Mails inside the Queue are going to be sent.', 'total-mail-queue' );
        }
        echo '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        /* translators: %1$s: opening bold tag, %2$s: closing bold tag */
        echo '<h3>' . wp_kses_post( sprintf( __( 'Does this plugin change the way %1$sHOW%2$s emails are sent?', 'total-mail-queue' ), '<b>', '</b>' ) ) . '</h3>';
        /* translators: %1$s: opening bold tag, %2$s: closing bold tag, %3$s: wp_mail() link */
        echo '<p>' . wp_kses_post( sprintf( __( 'No, don\'t worry. This plugin only affects %1$sWHEN%2$s emails are sent, not how. It delays the sending (by the Queue), nonetheless all emails are sent through the standard %3$s function.', 'total-mail-queue' ), '<b>', '</b>', '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>' ) ) . '</p>';
        echo '<p>' . esc_html__( 'If you use SMTP for sending, or an external service like Mailgun, everything will still work as expected.', 'total-mail-queue' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        /* translators: %1$s: example caching plugin name */
        echo '<h3>' . wp_kses_post( sprintf( __( 'Does this plugin work, if I have a Caching Plugin installed? E.g. %1$s or similar?', 'total-mail-queue' ), '<i>W3 Total Cache</i>' ) ) . '</h3>';
        /* translators: %1$s: W3 Total Cache, %2$s: WP Rocket, %3$s: wp-cron.php link */
        echo '<p>' . wp_kses_post( sprintf( __( 'If you\'re using a Caching plugin like %1$s, %2$s or any other caching solution which generates static html-files and serves them to visitors, you\'ll have to make sure you\'re calling the %3$s manually every couple of minutes.', 'total-mail-queue' ), '<i>W3 Total Cache</i>', '<i>WP Rocket</i>', '<a href="'.esc_url(get_option('siteurl')).'/wp-cron.php" target="_blank">' . esc_html__( 'wp-cron file', 'total-mail-queue' ) . '</a>' ) ) . '</p>';
        echo '<p>' . esc_html__( 'Otherwise your normal WP Cron wouldn\'t be called as often as it should be and scheduled messages would be sent with big delays.', 'total-mail-queue' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'What about Proxy-Caching, e.g. NGINX?', 'total-mail-queue' ) . '</h3>';
        /* translators: %s: WordPress Cron link */
        echo '<p>' . wp_kses_post( sprintf( __( 'Same situation here. Please make sure you\'re calling the %s by an external service or your webhoster every couple of minutes.', 'total-mail-queue' ), '<a href="'.esc_url(get_option('siteurl')).'/wp-cron.php" target="_blank">' . esc_html__( 'WordPress Cron', 'total-mail-queue' ) . '</a>' ) ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'My form builder supports attachments. What about them?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . esc_html__( 'You are covered. All attachments are stored temporarily in the queue until they are sent along with their corresponding emails.', 'total-mail-queue' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'What are Queue alerts?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . esc_html__( 'This is a simple and effective way to improve the security of your WordPress installation.', 'total-mail-queue' ) . '</p>';
        /* translators: %1$s: wp_mail() link */
        echo '<p>' . wp_kses_post( sprintf( __( 'Imagine: In case your website is sending spam through %1$s, the email Queue would fill up very quickly preventing your website from sending so many spam emails at once. This gives you time and avoids a lot of trouble.', 'total-mail-queue' ), '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>' ) ) . '</p>';
        echo '<p>' . esc_html__( 'Queue Alerts warn you, if the Queue is longer than usual. You decide at which point you want to be alerted. So you get the chance to have a look if there might be something wrong on the website.', 'total-mail-queue' ) . '</p>';
        echo '<p>' . esc_html__( 'Current state:', 'total-mail-queue' ) . ' ';
        if ($wp_tmq_options['alert_enabled'] === '1') {
            /* translators: %1$s: email amount threshold, %2$s: alert email address */
            echo '<b class="tmq-ok">' . esc_html__( 'Alerts are enabled', 'total-mail-queue' ) . '</b> ' . wp_kses_post( sprintf( __( 'If more than %1$s emails are waiting in the Queue, WordPress will send an alert email to %2$s.', 'total-mail-queue' ), esc_html( $wp_tmq_options['email_amount'] ), '<i>' . esc_html( $wp_tmq_options['email'] ) . '</i>' ) );
        } else {
            echo '<b>' . esc_html__( 'Alerting is disabled', 'total-mail-queue' ) . '</b>. ' . esc_html__( 'No alerts will be sent.', 'total-mail-queue' );
        }
        echo '</p>';
        echo '<p>' . esc_html__( 'Please note: This plugin will only send one alert every six hours.', 'total-mail-queue' ) . '</p>';
        echo '</div>';

        echo '<div class="tmq-box">';
            echo '<h3>' . esc_html__( 'Can I add emails with a high priority to the queue?', 'total-mail-queue' ) . '</h3>';
            echo '<p>' . wp_kses_post( __( 'Yes, you can add the custom <i>`X-Mail-Queue-Prio`</i> header set to <i>`High`</i> to your email. High priority emails will be sent through the standard Total Mail Queue sending cycle but before all normal emails lacking a priority header in the queue.', 'total-mail-queue' ) ) . '</p>';
            /* translators: Example code labels - not translatable code blocks */
            echo '<p><b>' . esc_html__( 'Example 1 (add priority to Woocommerce emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<pre><code>add_filter(\'woocommerce_mail_callback_params\',function ( $array ) {
    $prio_header = \'X-Mail-Queue-Prio: High\';
    if (is_array($array[3])) {
        $array[3][] = $prio_header;
    } else {
        $array[3] .= $array[3] ? "\r\n" : \'\';
        $array[3] .= $prio_header;
    }
    return $array;
},10,1);</code></pre>';
            echo '<p><b>' . esc_html__( 'Example 2 (add priority to Contact Form 7 form emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<p>' . wp_kses_post( __( 'When editing a form in Contact Form 7 just add an additional line to the <i>`Additional Headers`</i> field under the <i>`Mail`</i> tab panel.', 'total-mail-queue' ) ) . '</p>';
            echo '<pre><code>X-Mail-Queue-Prio: High</code></pre>';
            echo '<p><b>' . esc_html__( 'Example 3 (add priority to WordPress reset password emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<pre><code>add_filter(\'retrieve_password_notification_email\', function ($defaults, $key, $user_login, $user_data) {
    $prio_header = \'X-Mail-Queue-Prio: High\';
    if (is_array($defaults[\'headers\'])) {
        $defaults[\'headers\'][] = $prio_header;
    } else {
        $defaults[\'headers\'] .= $defaults[\'headers\'] ? "\r\n" : \'\';
        $defaults[\'headers\'] .= $prio_header;
    }
    return $defaults;
}, 10, 4);</code></pre>';
        echo '</div>';

        echo '<div class="tmq-box">';
            echo '<h3>' . wp_kses_post( __( 'Can I send emails <i>instantly</i> without going through the queue?', 'total-mail-queue' ) ) . '</h3>';
            echo '<p>' . esc_html__( 'Yes, this is possible (if you absolutely need to do this).', 'total-mail-queue' ) . '</p>';
            echo '<p>' . wp_kses_post( __( 'For this you can add the custom <i>`X-Mail-Queue-Prio`</i> header set to <i>`Instant`</i> to your email. These emails are sent instantly circumventing the mail queue. They still appear in the Total Mail Queue log flagged as `instant`.', 'total-mail-queue' ) ) . '</p>';
            echo '<p>' . esc_html__( 'Mind that this is a potential security risk and should be considered carefully. Please use only as an exception.', 'total-mail-queue' ) . '</p>';
            echo '<p><b>' . esc_html__( 'Example 1 (instantly send Woocommerce emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<pre><code>add_filter(\'woocommerce_mail_callback_params\',function ( $array ) {
    $prio_header = \'X-Mail-Queue-Prio: Instant\';
    if (is_array($array[3])) {
        $array[3][] = $prio_header;
    } else {
        $array[3] .= $array[3] ? "\r\n" : \'\';
        $array[3] .= $prio_header;
    }
    return $array;
},10,1);</code></pre>';
            echo '<p><b>' . esc_html__( 'Example 2 (instantly send Contact Form 7 form emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<p>' . wp_kses_post( __( 'When editing a form in Contact Form 7 just add an additional line to the <i>`Additional Headers`</i> field under the <i>`Mail`</i> tab panel.', 'total-mail-queue' ) ) . '</p>';
            echo '<pre><code>X-Mail-Queue-Prio: Instant</code></pre>';
            echo '<p><b>' . esc_html__( 'Example 3 (instantly send WordPress reset password emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<pre><code>add_filter(\'retrieve_password_notification_email\', function ($defaults, $key, $user_login, $user_data) {
    $prio_header = \'X-Mail-Queue-Prio: Instant\';
    if (is_array($defaults[\'headers\'])) {
        $defaults[\'headers\'][] = $prio_header;
    } else {
        $defaults[\'headers\'] .= $defaults[\'headers\'] ? "\r\n" : \'\';
        $defaults[\'headers\'] .= $prio_header;
    }
    return $defaults;
}, 10, 4);</code></pre>';
        echo '</div>';

        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'What is the "Send Method" setting?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . esc_html__( 'The Send Method setting controls how emails from the retention queue are delivered. There are three options:', 'total-mail-queue' ) . '</p>';
        echo '<ul>';
        echo '<li><b>' . esc_html__( 'Automatic', 'total-mail-queue' ) . '</b> — ' . esc_html__( 'This is the default. The plugin will first try to use an SMTP account configured in the SMTP Accounts tab. If no SMTP account is available (none configured or all have reached their limits), it will try to replay any captured SMTP configuration from other plugins. If neither is available, it falls back to the standard WordPress wp_mail() function.', 'total-mail-queue' ) . '</li>';
        echo '<li><b>' . esc_html__( 'Plugin SMTP only', 'total-mail-queue' ) . '</b> — ' . esc_html__( 'Emails will ONLY be sent via SMTP accounts configured in this plugin. If no account is available (limits reached or none configured), emails will remain in the retention queue waiting until an SMTP account becomes available. This is useful if you want to guarantee all emails go through your own SMTP servers.', 'total-mail-queue' ) . '</li>';
        echo '<li><b>' . esc_html__( 'WordPress default', 'total-mail-queue' ) . '</b> — ' . esc_html__( 'Ignores all SMTP accounts configured in this plugin and any captured configurations. Emails are sent using whatever wp_mail() does by default — which could be the PHP mail() function or another SMTP plugin like WP Mail SMTP.', 'total-mail-queue' ) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html__( 'Want to put a test email into the Queue?', 'total-mail-queue' ) . '</h3>';
        /* translators: %s: admin email address */
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( 'admin.php?page=wp_tmq_mail_queue-tab-queue&addtestmail', 'wp_tmq_addtestmail' ) ) . '">' . esc_html( sprintf( __( 'Sure! Put a Test Email for %s into the Queue', 'total-mail-queue' ), $wp_tmq_options['email'] ) ) . '</a></p>';
        echo '</div>';

    } else if ($tab === 'wp_tmq_mail_queue-tab-smtp') {
        wp_tmq_render_smtp_page();
    } else if ($tab === 'wp_tmq_mail_queue-tab-croninfo') {
        echo '<div class="tmq-box">';
            echo '<h3>' . esc_html__( 'Information: Your common WP Cron is disabled', 'total-mail-queue' ) . '</h3>';
            echo '<p>' . wp_kses_post( __( 'It looks like you deactivated the WP Cron by <i>define( \'DISABLE_WP_CRON\', true )</i>.', 'total-mail-queue' ) ) . '</p>';
            $url = esc_url(get_option('siteurl').'/wp-cron.php');
            /* translators: %s: wp-cron.php URL */
            echo '<p>' . wp_kses_post( sprintf( __( 'In general, this is no problem at all. We just want to remind you to make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ), '<a href="'.$url.'" target="_blank">'.$url.'</a>' ) ) . '</p>';
        echo '</div>';


            if (function_exists('_get_cron_array')) {
                $next_tasks = _get_cron_array();
                if ($next_tasks) {
                    $tasks_in_past = false;
                    $tasks_of_mailqueue_in_past = false;
                    foreach($next_tasks as $key => $val) {
                        if (time() > intval($key) + intval($wp_tmq_options['queue_interval'])) {
                            if (array_keys($val)[0] == 'wp_tmq_mail_queue_hook') { $tasks_of_mailqueue_in_past = intval($key); }
                            $tasks_in_past = true;
                        }
                    }
                    if ($tasks_in_past) {
                        echo '<div class="tmq-box">';
                            echo '<h3>' . esc_html__( 'Attention: It seems that your WP Cron is not running. There are some jobs waiting to be completed.', 'total-mail-queue' ) . '</h3>';
                            if ($tasks_of_mailqueue_in_past) {
                                /* translators: %s: human-readable time diff */
                                echo '<p><b>' . esc_html( sprintf( __( 'The Queue hasn\'t been able to be executed since %s.', 'total-mail-queue' ), human_time_diff( $tasks_of_mailqueue_in_past, time() ) ) ) . '</b></p>';
                            }
                        echo '</div>';
                    }
                }
            }


    }

    echo '</div>';

}

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/screen.php' );
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class wp_tmq_Log_Table extends WP_List_Table {

    function get_log_where( $status_filter = '' ) {
        global $wpdb;
        if ( $status_filter && in_array( $status_filter, array( 'sent', 'error', 'alert' ), true ) ) {
            return $wpdb->prepare( "`status` = %s", $status_filter );
        }
        return "`status` != 'queue' AND `status` != 'high'";
    }

    function get_log_count( $status_filter = '' ) {
        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        $where = $this->get_log_where( $status_filter );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$tableName` WHERE $where" );
    }

    function get_log( $status_filter = '', $per_page = 50, $offset = 0 ) {
        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        $where = $this->get_log_where( $status_filter );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE $where ORDER BY `timestamp` DESC LIMIT %d OFFSET %d", $per_page, $offset ), 'ARRAY_A' );
    }

    function get_queue_count() {
        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$tableName` WHERE `status` = 'queue' OR `status` = 'high'" );
    }

    function get_queue( $per_page = 50, $offset = 0 ) {
        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `status` ASC, `retry_count` ASC, `id` ASC LIMIT %d OFFSET %d", $per_page, $offset ), 'ARRAY_A' );
    }

    function get_columns() {
        $columns = array(
            'cb'          => '<label><span class="screen-reader-text">' . esc_html__( 'Select all', 'total-mail-queue' ) . '</span><input class="tmq-select-all" type="checkbox"></label>',
            'timestamp'   => __( 'Time', 'total-mail-queue' ),
            'status'      => __( 'Status', 'total-mail-queue' ),
            'smtp_account_id' => __( 'SMTP', 'total-mail-queue' ),
            'info'        => __( 'Info', 'total-mail-queue' ),
            'recipient'   => __( 'Recipient', 'total-mail-queue' ),
            'subject'     => __( 'Subject', 'total-mail-queue' ),
            'message'     => __( 'Message', 'total-mail-queue' ),
            'headers'     => __( 'Headers', 'total-mail-queue' ),
            'attachments' => __( 'Attachments', 'total-mail-queue' ),
        );
        // Only show SMTP column on the log tab (queued items haven't been sent yet)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
        $type = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( $type !== 'wp_tmq_mail_queue-tab-log' ) {
            unset( $columns['smtp_account_id'] );
        }
        return $columns;
    }

    protected function extra_tablenav( $which ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
        $type = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( $type !== 'wp_tmq_mail_queue-tab-log' || $which !== 'top' ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter parameter, not destructive action
        $current = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : '';
        $statuses = array(
            ''      => __( 'All statuses', 'total-mail-queue' ),
            'sent'  => __( 'Sent', 'total-mail-queue' ),
            'error' => __( 'Error', 'total-mail-queue' ),
            'alert' => __( 'Alert', 'total-mail-queue' ),
        );
        echo '<div class="alignleft actions">';
        echo '<select name="status_filter">';
        foreach ( $statuses as $value => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $value ),
                selected( $current, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        submit_button( __( 'Filter', 'total-mail-queue' ), '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
        $type = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();

        $perPage     = 50;
        $currentPage = $this->get_pagenum();
        $offset      = ( $currentPage - 1 ) * $perPage;
        $totalItems  = 0;
        $data        = array();

        if ($type === 'wp_tmq_mail_queue-tab-log') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter parameter, not destructive action
            $status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : '';
            $totalItems = $this->get_log_count( $status_filter );
            $data = $this->get_log( $status_filter, $perPage, $offset );
        } else if ($type === 'wp_tmq_mail_queue-tab-queue') {
            $totalItems = $this->get_queue_count();
            $data = $this->get_queue( $perPage, $offset );
        }

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage,
        ) );
        $this->items = $data;
    }

    public function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'timestamp':
            case 'subject':
                return esc_html( wp_tmq_decode($item[$column_name]) );
                break;
            case 'info':
                $info = isset( $item['info'] ) && $item['info'] ? $item['info'] : '';
                $retry_count = isset( $item['retry_count'] ) ? intval( $item['retry_count'] ) : 0;
                $parts = array();
                if ( $retry_count > 0 ) {
                    /* translators: %d: number of retry attempts */
                    $parts[] = '<strong>' . sprintf( esc_html__( 'Attempt #%d', 'total-mail-queue' ), $retry_count + 1 ) . '</strong>';
                }
                if ( $info ) {
                    $parts[] = esc_html( $info );
                }
                return implode( '<br>', $parts );
                break;
            case 'recipient':
            case 'headers':
                $return = wp_tmq_decode($item[$column_name]);
                if (is_array($return)) {
                    return esc_html( implode(',',$return) );
                } else {
                    return esc_html( $return );
                }
                break;
            case 'attachments':
                $return = wp_tmq_decode($item[$column_name]);
                if (is_array($return)) {
                    $betterreturn = array();
                    foreach($return as $item) {
                        array_push($betterreturn,basename($item));
                    }
                    return esc_html( implode('<br />',$betterreturn) );
                } else {
                    return esc_html( basename($return) );
                }
                break;
            case 'status':
                $status_labels = array(
                    'sent'  => __( 'Sent', 'total-mail-queue' ),
                    'error' => __( 'Error', 'total-mail-queue' ),
                    'alert' => __( 'Alert', 'total-mail-queue' ),
                    'queue' => __( 'Queue', 'total-mail-queue' ),
                    'high'  => __( 'High', 'total-mail-queue' ),
                );
                $raw    = $item[ $column_name ];
                $label  = isset( $status_labels[ $raw ] ) ? $status_labels[ $raw ] : esc_html( $raw );
                return '<span class="tmq-status tmq-status-' . sanitize_title( $raw ) . '">' . esc_html( $label ) . '</span>';
                break;
            case 'smtp_account_id':
                $acct_id = intval( $item['smtp_account_id'] ?? 0 );
                if ( $acct_id === 0 ) {
                    return '<span class="description">—</span>';
                }
                // Static cache: load all SMTP account names once
                static $smtp_names = null;
                if ( $smtp_names === null ) {
                    global $wpdb, $wp_tmq_options;
                    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $rows = $wpdb->get_results( "SELECT `id`, `name` FROM `$smtpTable`", ARRAY_A );
                    $smtp_names = array();
                    foreach ( $rows as $r ) {
                        $smtp_names[ intval( $r['id'] ) ] = $r['name'];
                    }
                }
                $name = isset( $smtp_names[ $acct_id ] ) ? $smtp_names[ $acct_id ] : __( 'Deleted', 'total-mail-queue' );
                return '<span title="#' . esc_attr( $acct_id ) . '">#' . esc_html( $acct_id ) . ' ' . esc_html( $name ) . '</span>';
                break;
            case 'message':
                $message = $item[$column_name];
                if ( $message ) {
                    $messageLen = strlen($message);
                    $return  = '<details>';
                    /* translators: %s: message size in bytes */
                    $return .=   '<summary class="tmq-view-source" data-tmq-list-message-toggle="'.esc_attr($item['id']).'">' . wp_kses_post( sprintf( __( 'View message %s', 'total-mail-queue' ), '<i>(' . esc_html( $messageLen ) . ' bytes)</i>' ) ) . '</summary>';
                    $return .=   '<div class="tmq-email-source" data-tmq-list-message-content>' . esc_html__( 'Loading...', 'total-mail-queue' ) . '</div>';
                    $return .= '</details>';
                } else {
                    $return = '<em>' . esc_html__( 'Empty', 'total-mail-queue' ) . '</em>';
                }
                return $return;
                break;
            default:
                return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
        }
    }

    protected function column_cb ( $item ) {
        return '<input type="checkbox" name="id[]" value="'.esc_attr($item['id']).'" />';
    }

    public function get_bulk_actions() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
        if ( isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) === 'wp_tmq_mail_queue-tab-queue' ) {
            $actions = array(
                'delete' => __( 'Delete', 'total-mail-queue')
            );
        } else {
            $actions = array(
                'delete'       => __( 'Delete', 'total-mail-queue'),
                'resend'       => __( 'Resend', 'total-mail-queue'),
                'force_resend' => __( 'Force Resend (ignore retry limit)', 'total-mail-queue'),
            );
        }

        return $actions;
    }

    public function process_bulk_action() {

        // No action selected — nothing to do
        if ( ! $this->current_action() ) { return; }

        // security check — nonce is mandatory for any bulk action
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( $_POST['_wpnonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
            wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
        }

        // get IDs
        $request_ids = isset( $_REQUEST['id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['id'] ) ) : array();
        if ( empty( $request_ids ) ) { return; }

        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];

        switch ( $this->current_action() ) {
            case 'delete':
                foreach($request_ids as $id) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->delete($tableName,array('id'=>intval($id)),'%d');
                }
                break;
            case 'resend':
                $count_resend = 0;
                $count_error  = 0;
                $count_skipped_sent  = 0;
                $count_skipped_queue = 0;
                foreach($request_ids as $id) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $maildata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `id` = %d", intval( $id ) ) );
                    if ( ! $maildata ) { continue; }
                    // Block emails that were already sent successfully
                    if ( $maildata->status === 'sent' ) { $count_skipped_sent++; continue; }
                    // Block emails already in the queue
                    if ( in_array( $maildata->status, array( 'queue', 'high' ), true ) ) { $count_skipped_queue++; continue; }
                    if (!$maildata->attachments || $maildata->attachments === '') {
                        $count_resend++;
                        $data = array(
                            'timestamp'=> current_time('mysql',false),
                            'recipient'=> $maildata->recipient,
                            'subject'=> $maildata->subject,
                            'message'=> $maildata->message,
                            'status' => 'queue',
                            'attachments' => '',
                            'headers' => $maildata->headers,
                        );
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->insert($tableName,$data);
                        // Remove original log entry to prevent duplicate resends
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->delete( $tableName, array( 'id' => intval( $id ) ), '%d' );
                    } else {
                        $count_error++;
                        $notice = '<div class="notice notice-error is-dismissible">';
                        /* translators: %s: recipient email address */
                        $notice .= '<p><b>' . sprintf( __( 'Sorry, your email to %s can\'t be sent again.', 'total-mail-queue' ), esc_html( $maildata->recipient ) ) . '</b></p>';
                        $notice .= '<p>' . esc_html__( 'The email used to have attachments, which are not available anymore. Only emails without attachments can be resent.', 'total-mail-queue' ) . '</p>';
                        $notice .= '</div>';
                        echo wp_kses_post( $notice );
                    }
                }
                // Show warnings for skipped items
                if ( $count_skipped_sent > 0 ) {
                    $notice = '<div class="notice notice-warning is-dismissible">';
                    /* translators: %d: number of skipped emails */
                    $notice .= '<p>' . sprintf( __( '%d email(s) were skipped because they have already been sent successfully. Sent emails cannot be re-queued.', 'total-mail-queue' ), $count_skipped_sent ) . '</p>';
                    $notice .= '</div>';
                    echo wp_kses_post( $notice );
                }
                if ( $count_skipped_queue > 0 ) {
                    $notice = '<div class="notice notice-warning is-dismissible">';
                    /* translators: %d: number of skipped emails */
                    $notice .= '<p>' . sprintf( __( '%d email(s) were skipped because they are already in the queue waiting to be sent.', 'total-mail-queue' ), $count_skipped_queue ) . '</p>';
                    $notice .= '</div>';
                    echo wp_kses_post( $notice );
                }
                $has_skips = ( $count_error > 0 || $count_skipped_sent > 0 || $count_skipped_queue > 0 );
                if ( ! $has_skips && $count_resend > 0 ) {
                    wp_safe_redirect('admin.php?page=wp_tmq_mail_queue-tab-queue&resent=' . $count_resend);
                    exit;
                } else if ( $count_resend > 0 ) {
                    $notice = '<div class="notice notice-success is-dismissible">';
                    /* translators: %1$d: count, %2$s: link open, %3$s: link close */
                    $notice .= '<p>' . sprintf( __( '%1$d email(s) have been put again into the %2$sQueue%3$s.', 'total-mail-queue' ), $count_resend, '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) . '</p>';
                    $notice .= '</div>';
                    echo wp_kses_post( $notice );
                }
                break;
            case 'force_resend':
                $count_force = 0;
                $count_force_error = 0;
                $count_force_skipped_sent  = 0;
                $count_force_skipped_queue = 0;
                foreach($request_ids as $id) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $maildata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `id` = %d", intval( $id ) ) );
                    if ( ! $maildata ) { continue; }
                    // Block emails that were already sent successfully
                    if ( $maildata->status === 'sent' ) { $count_force_skipped_sent++; continue; }
                    // Block emails already in the queue
                    if ( in_array( $maildata->status, array( 'queue', 'high' ), true ) ) { $count_force_skipped_queue++; continue; }
                    if ( $maildata->status !== 'error' ) {
                        $count_force_error++;
                        continue;
                    }
                    if ( ! $maildata->attachments || $maildata->attachments === '' ) {
                        $count_force++;
                        $data = array(
                            'timestamp'   => current_time( 'mysql', false ),
                            'recipient'   => $maildata->recipient,
                            'subject'     => $maildata->subject,
                            'message'     => $maildata->message,
                            'status'      => 'queue',
                            'attachments' => '',
                            'headers'     => $maildata->headers,
                            'retry_count' => 0,
                            /* translators: %s: original error info */
                            'info'        => sprintf( __( 'Force resent — Original: %s', 'total-mail-queue' ), $maildata->info ),
                        );
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->insert( $tableName, $data );
                        // Remove original log entry to prevent duplicate resends
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->delete( $tableName, array( 'id' => intval( $id ) ), '%d' );
                    } else {
                        $count_force_error++;
                        $notice = '<div class="notice notice-error is-dismissible">';
                        /* translators: %s: recipient email address */
                        $notice .= '<p><b>' . sprintf( __( 'Sorry, your email to %s can\'t be sent again.', 'total-mail-queue' ), esc_html( $maildata->recipient ) ) . '</b></p>';
                        $notice .= '<p>' . esc_html__( 'The email used to have attachments, which are not available anymore. Only emails without attachments can be resent.', 'total-mail-queue' ) . '</p>';
                        $notice .= '</div>';
                        echo wp_kses_post( $notice );
                    }
                }
                // Show warnings for skipped items
                if ( $count_force_skipped_sent > 0 ) {
                    $notice = '<div class="notice notice-warning is-dismissible">';
                    /* translators: %d: number of skipped emails */
                    $notice .= '<p>' . sprintf( __( '%d email(s) were skipped because they have already been sent successfully. Sent emails cannot be re-queued.', 'total-mail-queue' ), $count_force_skipped_sent ) . '</p>';
                    $notice .= '</div>';
                    echo wp_kses_post( $notice );
                }
                if ( $count_force_skipped_queue > 0 ) {
                    $notice = '<div class="notice notice-warning is-dismissible">';
                    /* translators: %d: number of skipped emails */
                    $notice .= '<p>' . sprintf( __( '%d email(s) were skipped because they are already in the queue waiting to be sent.', 'total-mail-queue' ), $count_force_skipped_queue ) . '</p>';
                    $notice .= '</div>';
                    echo wp_kses_post( $notice );
                }
                $has_force_skips = ( $count_force_error > 0 || $count_force_skipped_sent > 0 || $count_force_skipped_queue > 0 );
                if ( ! $has_force_skips && $count_force > 0 ) {
                    wp_safe_redirect( 'admin.php?page=wp_tmq_mail_queue-tab-queue&resent=' . $count_force );
                    exit;
                } else if ( $count_force > 0 ) {
                    $notice = '<div class="notice notice-success is-dismissible">';
                    /* translators: %1$d: number of emails resent, %2$s: link open, %3$s: link close */
                    $notice .= '<p>' . sprintf( __( '%1$d email(s) have been force-resent to the %2$sRetention%3$s queue.', 'total-mail-queue' ), $count_force, '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) . '</p>';
                    $notice .= '</div>';
                    echo wp_kses_post( $notice );
                }
                break;
        }

        return;

    }

}

function wp_tmq_settings_page_navi($tab) {
    echo '<nav class="nav-tab-wrapper">';
        echo '<a href="?page=wp_tmq_mail_queue" class="nav-tab'; if($tab==='wp_tmq_mail_queue') { echo ' nav-tab-active'; } echo '">' . esc_html__( 'Settings', 'total-mail-queue' ) . '</a>';
        echo '<a href="?page=wp_tmq_mail_queue-tab-log" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-log') { echo ' nav-tab-active'; } echo '">' . esc_html__( 'Log', 'total-mail-queue' ) . '</a>';
        echo '<a href="?page=wp_tmq_mail_queue-tab-queue" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-queue') { echo ' nav-tab-active'; } echo '">' . esc_html__( 'Retention', 'total-mail-queue' ) . '</a>';
        echo '<a href="?page=wp_tmq_mail_queue-tab-smtp" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-smtp') { echo ' nav-tab-active'; } echo '">' . esc_html__( 'SMTP Accounts', 'total-mail-queue' ) . '</a>';
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<a href="?page=wp_tmq_mail_queue-tab-croninfo" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-croninfo') { echo ' nav-tab-active'; } echo '">' . esc_html__( 'Cron Information', 'total-mail-queue' ) . '</a>';
        }
        echo '<a href="?page=wp_tmq_mail_queue-tab-faq" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-faq') { echo ' nav-tab-active'; } echo '">' . esc_html__( 'FAQ', 'total-mail-queue' ) . '</a>';
    echo '</nav>';
}

function wp_tmq_sanitize_settings( $input ) {
    $allowed_keys = array(
        'enabled', 'alert_enabled', 'email', 'email_amount',
        'queue_amount', 'queue_interval', 'queue_interval_unit',
        'clear_queue', 'log_max_records', 'send_method', 'max_retries',
        'cron_lock_ttl', 'smtp_timeout',
    );
    $sanitized = array();
    if ( is_array( $input ) ) {
        foreach ( $input as $key => $value ) {
            if ( in_array( $key, $allowed_keys, true ) ) {
                $sanitized[ $key ] = sanitize_text_field( $value );
            }
        }
    }
    return $sanitized;
}

function wp_tmq_settings_init() {
    global $wp_tmq_options;
    register_setting('wp_tmq_settings','wp_tmq_settings', array(
        'sanitize_callback' => 'wp_tmq_sanitize_settings',
    ));
    add_settings_section('wp_tmq_settings_section','',null,'wp_tmq_settings_page');
    add_settings_field('wp_tmq_status', __( 'Operation Mode', 'total-mail-queue' ),'wp_tmq_render_option_status','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_queue', __( 'Queue', 'total-mail-queue' ),'wp_tmq_render_option_queue','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_log', __( 'Log', 'total-mail-queue' ),'wp_tmq_render_option_log','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_send_method', __( 'Send Method', 'total-mail-queue' ),'wp_tmq_render_option_send_method','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_retry', __( 'Auto-Retry', 'total-mail-queue' ),'wp_tmq_render_option_retry','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_smtp_timeout', __( 'SMTP Timeout', 'total-mail-queue' ),'wp_tmq_render_option_smtp_timeout','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_cron_lock_ttl', __( 'Cron Lock Timeout', 'total-mail-queue' ),'wp_tmq_render_option_cron_lock_ttl','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_alert_status', __( 'Alert enabled', 'total-mail-queue' ),'wp_tmq_render_option_alert_status','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_sensitivity', __( 'Alert Sensitivity', 'total-mail-queue' ),'wp_tmq_render_option_sensitivity','wp_tmq_settings_page','wp_tmq_settings_section');
}
add_action('admin_init','wp_tmq_settings_init');

function wp_tmq_render_option_status() {
    global $wp_tmq_options;
    $mode = $wp_tmq_options['enabled'];
    echo '<select name="wp_tmq_settings[enabled]">';
    echo '<option value="0"' . selected( $mode, '0', false ) . '>' . esc_html__( 'Disabled — No interception, emails sent normally', 'total-mail-queue' ) . '</option>';
    echo '<option value="1"' . selected( $mode, '1', false ) . '>' . esc_html__( 'Queue — Retain emails and send via queue', 'total-mail-queue' ) . '</option>';
    echo '<option value="2"' . selected( $mode, '2', false ) . '>' . esc_html__( 'Block — Retain all emails, never send (block all outgoing)', 'total-mail-queue' ) . '</option>';
    echo '</select>';
    if ( $mode === '0' ) {
        echo ' <span class="tmq-warning">' . esc_html__( 'The plugin is currently disabled and has no effect.', 'total-mail-queue' ) . '</span>';
    } else if ( $mode === '2' ) {
        echo ' <span class="tmq-warning tmq-warning-block">' . esc_html__( 'All outgoing emails are being blocked!', 'total-mail-queue' ) . '</span>';
    }
}

function wp_tmq_render_option_alert_status() {
    global $wp_tmq_options;
    if ($wp_tmq_options['alert_enabled'] === '1') {
        echo '<input type="checkbox" name="wp_tmq_settings[alert_enabled]" value="1" checked />';
    } else {
        echo '<input type="checkbox" name="wp_tmq_settings[alert_enabled]" value="1" />';
    }
}

function wp_tmq_render_option_queue() {
    global $wp_tmq_options;
    if ($wp_tmq_options['queue_interval_unit'] === 'seconds') {
        $number = intval($wp_tmq_options['queue_interval']);
    } else {
        $number = intval($wp_tmq_options['queue_interval']) / 60;
    }

    echo esc_html__( 'Send max.', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[queue_amount]" type="number" min="1" value="'.esc_attr($wp_tmq_options['queue_amount']).'" /> ' . esc_html__( 'email(s) every', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[queue_interval]" type="number" min="1" value="'.esc_attr($number).'" />';

    echo '<select name="wp_tmq_settings[queue_interval_unit]">';
    echo '<option value="minutes"' . selected( $wp_tmq_options['queue_interval_unit'], 'minutes', false ) . '>' . esc_html__( 'minute(s)', 'total-mail-queue' ) . '</option>';
    echo '<option value="seconds"' . selected( $wp_tmq_options['queue_interval_unit'], 'seconds', false ) . '>' . esc_html__( 'second(s)', 'total-mail-queue' ) . '</option>';
    echo '</select>';

    /* translators: %1$s: opening link tag, %2$s: closing link tag */
    echo ' ' . wp_kses_post( sprintf( __( 'by %1$sWP Cron%2$s.', 'total-mail-queue' ), '<i><a href="https://developer.wordpress.org/plugins/cron/" target="_blank">', '</a></i>' ) ) . ' ';

    // Warn if any SMTP account has per-cycle bulk higher than global queue_amount
    global $wpdb;
    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
    $global_amount = intval( $wp_tmq_options['queue_amount'] );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $exceeding = $wpdb->get_results( $wpdb->prepare(
        "SELECT `name`, `send_bulk` FROM `$smtpTable` WHERE `enabled` = 1 AND `send_bulk` > %d AND `send_bulk` > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
        $global_amount
    ), ARRAY_A );
    if ( ! empty( $exceeding ) ) {
        $names = array_map( function ( $a ) { return '<strong>' . esc_html( $a['name'] ) . '</strong> (' . intval( $a['send_bulk'] ) . ')'; }, $exceeding );
        echo '<br /><span class="tmq-warning">' . wp_kses_post( sprintf(
            /* translators: %1$d: global email limit, %2$s: list of SMTP account names */
            __( 'Warning: The following SMTP account(s) have a per-cycle limit higher than the global limit of %1$d: %2$s. The global limit will be applied as the ceiling.', 'total-mail-queue' ),
            $global_amount,
            implode( ', ', $names )
        ) ) . '</span>';
    }
}

function wp_tmq_render_option_log() {
    global $wp_tmq_options;
    echo esc_html__( 'Delete Log entries older than', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[clear_queue]" type="number" min="1" value="'.esc_attr(intval($wp_tmq_options['clear_queue']) / 24).'" /> ' . esc_html__( 'days.', 'total-mail-queue' );
    echo '<br /><br />';
    echo esc_html__( 'Keep a maximum of', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[log_max_records]" type="number" min="0" value="'.esc_attr(intval($wp_tmq_options['log_max_records'])).'" /> ' . esc_html__( 'log records.', 'total-mail-queue' );
    echo ' <span class="description">' . esc_html__( '0 = unlimited', 'total-mail-queue' ) . '</span>';
}

function wp_tmq_render_option_retry() {
    global $wp_tmq_options;
    echo esc_html__( 'If sending fails, retry up to', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[max_retries]" type="number" min="0" value="'.esc_attr(intval($wp_tmq_options['max_retries'])).'" /> ' . esc_html__( 'time(s) before marking as error.', 'total-mail-queue' );
    echo ' <span class="description">' . esc_html__( '0 = no retries, email is immediately marked as error', 'total-mail-queue' ) . '</span>';
}

function wp_tmq_render_option_smtp_timeout() {
    global $wp_tmq_options;
    $timeout = intval( $wp_tmq_options['smtp_timeout'] );
    echo '<input name="wp_tmq_settings[smtp_timeout]" type="number" min="5" step="1" value="' . esc_attr( $timeout ) . '" /> ' . esc_html__( 'seconds', 'total-mail-queue' );
    echo '<p class="description">';
    /* translators: %s: recommended value */
    echo wp_kses_post( sprintf( __( 'Maximum time to wait for a response from the SMTP server per email. If the server does not respond within this time, the email is marked as failed and the batch continues to the next email. Recommended: %s seconds. Minimum: 5 seconds.', 'total-mail-queue' ), '<strong>30</strong>' ) );
    echo '</p>';
}

function wp_tmq_render_option_cron_lock_ttl() {
    global $wp_tmq_options;
    $ttl = intval( $wp_tmq_options['cron_lock_ttl'] );
    echo '<input name="wp_tmq_settings[cron_lock_ttl]" type="number" min="30" step="1" value="' . esc_attr( $ttl ) . '" /> ' . esc_html__( 'seconds', 'total-mail-queue' );
    echo '<p class="description">';
    /* translators: %s: recommended value */
    echo wp_kses_post( sprintf( __( 'Maximum time a cron batch can hold the processing lock. If the batch finishes normally, the lock is released immediately. This timeout is a safety net: if PHP crashes mid-batch, the lock expires after this period and the queue resumes automatically. Recommended: %s seconds (5 minutes). Minimum: 30 seconds.', 'total-mail-queue' ), '<strong>300</strong>' ) );
    echo '</p>';
    echo '<p class="description tmq-warning-block">';
    echo esc_html__( 'Too low: the lock may expire while a large batch is still sending, allowing overlapping batches (risk of duplicate emails). Too high: if the process crashes, the queue will be blocked until the timeout expires.', 'total-mail-queue' );
    echo '</p>';
}

function wp_tmq_render_option_send_method() {
    global $wp_tmq_options;
    $method = isset( $wp_tmq_options['send_method'] ) ? $wp_tmq_options['send_method'] : 'auto';
    echo '<select name="wp_tmq_settings[send_method]">';
    echo '<option value="auto"' . selected( $method, 'auto', false ) . '>' . esc_html__( 'Automatic — Use plugin SMTP if available, then captured config, then WordPress default', 'total-mail-queue' ) . '</option>';
    echo '<option value="smtp"' . selected( $method, 'smtp', false ) . '>' . esc_html__( 'Plugin SMTP only — Only send via SMTP accounts configured in this plugin (hold emails if none available)', 'total-mail-queue' ) . '</option>';
    echo '<option value="php"' . selected( $method, 'php', false ) . '>' . esc_html__( 'WordPress default — Ignore plugin SMTP accounts, send via standard wp_mail()', 'total-mail-queue' ) . '</option>';
    echo '</select>';
    if ( $method === 'smtp' ) {
        echo ' <span class="description">' . esc_html__( 'Emails will wait in the retention queue until an SMTP account with available limits is found.', 'total-mail-queue' ) . '</span>';
    }
}

function wp_tmq_render_option_sensitivity() {
    global $wp_tmq_options;
    /* translators: %1$s: opening link tag, %2$s: closing link tag */
    echo esc_html__( 'Send alert to', 'total-mail-queue' ) . ' <input type="text" name="wp_tmq_settings[email]" value="'.esc_attr(sanitize_email($wp_tmq_options['email'])).'" /> ' . esc_html__( 'if more than', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[email_amount]" type="number" min="1" value="'.esc_attr(intval($wp_tmq_options['email_amount'])).'" /> ' . wp_kses_post( sprintf( __( 'email(s) in the %1$sQueue%2$s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) );
}





/* ***************************************************************
Alert WordPress User if last email in log could not be sent
**************************************************************** */
function wp_tmq_checkLogForErrors() {

    global $wpdb,$wp_tmq_options;
    if ( ! in_array( $wp_tmq_options['enabled'], array( '1', '2' ) ) ) { return; }

    $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $last_mail = $wpdb->get_row("SELECT * FROM `$tableName` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `id` DESC",'ARRAY_A');
    if (!$last_mail) { return; }

    if ($last_mail['status'] === 'error') {
        if (current_user_can('manage_options')) {
            $notice = '<div class="notice notice-error is-dismissible">';
            $notice .= '<h1>' . esc_html__( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
            /* translators: %1$s: opening italic tag, %2$s: closing italic tag, %3$s: opening Mail Log link tag, %4$s: closing link tag */
            $notice .= '<p>' . sprintf( __( 'This is an important message from your %1$sTotal Mail Queue%2$s plugin. Please take a look at your %3$sMail Log%4$s. The last email(s) couldn\'t be sent properly.', 'total-mail-queue' ), '<i>', '</i>', '<a href="admin.php?page=wp_tmq_mail_queue-tab-log">', '</a>' ) . '</p>';
            /* translators: %s: error message */
            $notice .= '<p>' . sprintf( __( 'Last error message was: %s', 'total-mail-queue' ), '<b>' . esc_html( $last_mail['info'] ) . '</b>' ) . '</p>';
            $notice .= '</div>';
            echo wp_kses_post( $notice );
        } else if (current_user_can('edit_posts')) {
            $notice = '<div class="notice notice-error is-dismissible">';
            $notice .= '<h1>' . esc_html__( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
            $notice .= '<p>' . esc_html__( 'Please contact your Administrator. It seems that WordPress is not able to send emails.', 'total-mail-queue' ) . '</p>';
            /* translators: %s: error message */
            $notice .= '<p>' . sprintf( __( 'Last error message: %s', 'total-mail-queue' ), '<b>' . esc_html( $last_mail['info'] ) . '</b>' ) . '</p>';
            $notice .= '</div>';
            echo wp_kses_post( $notice );
        }
    }

    // notices for the plugin options page
    $currentScreen = get_current_screen();
    if ($currentScreen->base === 'toplevel_page_wp_tmq_mail_queue') {
        $wpMailOmittingPlugins = [
            'mailpoet/mailpoet.php' => 'MailPoet',
        ];
        $wpMailOmittingPluginsInstalled = [];
        foreach (array_keys($wpMailOmittingPlugins) as $plugin) {
            if(is_plugin_active($plugin)) {
                $wpMailOmittingPluginsInstalled[] = $plugin;
            }
        }
        if (count($wpMailOmittingPluginsInstalled) > 0) {
            $notice  = '<div class="notice notice-warning is-dismissible">';
            $notice .=   '<p>';
            $notice .=     '<strong>' . esc_html__( 'Please note:', 'total-mail-queue' ) . '</strong>';
            $notice .=     '<br />' . wp_kses_post( __( 'This plugin is not supported when using in combination with plugins that do not use the standard <i>wp_mail()</i> function.', 'total-mail-queue' ) );
            $notice .=   '</p>';
            $notice .=   '<p>';
            $notice .=     wp_kses_post( __( 'It seems you are using the following plugin(s) that do not use <i>wp_mail()</i>:', 'total-mail-queue' ) );
            $notice .=     '<br />'.implode(', ', array_map(function($plugin) use ($wpMailOmittingPlugins) { return esc_html( $wpMailOmittingPlugins[$plugin] ); },$wpMailOmittingPluginsInstalled));
            $notice .=   '</p>';
            $notice .=   '<p><a href="'.esc_url( get_admin_url(null,'admin.php?page=wp_tmq_mail_queue-tab-faq') ).'">' . esc_html__( 'More information', 'total-mail-queue' ) . '</a></p>';
            $notice .= '</div>';
            echo wp_kses_post( $notice );
        }
    }
}
add_action('admin_notices', 'wp_tmq_checkLogForErrors');


/* ***************************************************************
Export / Import Settings
**************************************************************** */

function wp_tmq_handle_export() {
    global $wpdb, $wp_tmq_options;

    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $smtp_accounts = $wpdb->get_results( "SELECT * FROM `$smtpTable`", ARRAY_A );

    // Remove auto-increment IDs and transient counters from SMTP accounts
    if ( $smtp_accounts ) {
        foreach ( $smtp_accounts as &$account ) {
            unset( $account['id'] );
            $account['daily_sent']  = 0;
            $account['monthly_sent'] = 0;
        }
        unset( $account );
    }

    $settings = get_option( 'wp_tmq_settings', array() );

    // Build XML
    $xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><total-mail-queue/>' );
    $xml->addAttribute( 'version', get_option( 'wp_tmq_version', '' ) );
    $xml->addAttribute( 'exported_at', current_time( 'mysql', false ) );

    // Settings
    $settings_node = $xml->addChild( 'settings' );
    if ( is_array( $settings ) ) {
        foreach ( $settings as $key => $value ) {
            $settings_node->addChild( sanitize_key( $key ), esc_xml( $value ) );
        }
    }

    // SMTP accounts
    $smtp_node = $xml->addChild( 'smtp_accounts' );
    if ( $smtp_accounts ) {
        foreach ( $smtp_accounts as $account ) {
            $account_node = $smtp_node->addChild( 'account' );
            foreach ( $account as $key => $value ) {
                $account_node->addChild( sanitize_key( $key ), esc_xml( $value ) );
            }
        }
    }

    $filename = 'total-mail-queue-export-' . wp_date( 'Y-m-d-His' ) . '.xml';

    header( 'Content-Type: application/xml; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );

    $dom = new DOMDocument( '1.0', 'UTF-8' );
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML( $xml->asXML() );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML file download, not HTML context
    echo $dom->saveXML();
    exit;
}

function wp_tmq_handle_import() {
    // Nonce is verified in the caller wp_tmq_settings_page() before calling this function.
    global $wpdb, $wp_tmq_options;

    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- nonce verified in caller; isset checks are on same line
    if ( ! isset( $_FILES['wp_tmq_import_file'] ) || ! isset( $_FILES['wp_tmq_import_file']['error'] ) || $_FILES['wp_tmq_import_file']['error'] !== UPLOAD_ERR_OK ) {
        return '<div class="notice notice-error"><p>' . esc_html__( 'Error uploading file. Please try again.', 'total-mail-queue' ) . '</p></div>';
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- reading uploaded tmp file; nonce verified in caller; validated via isset above
    $file_content = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['wp_tmq_import_file']['tmp_name'] ) ) );

    // Suppress XML errors and disable external entities (XXE protection)
    $use_errors = libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $file_content, 'SimpleXMLElement', LIBXML_NONET );
    libxml_use_internal_errors( $use_errors );

    if ( ! $xml ) {
        return '<div class="notice notice-error"><p>' . esc_html__( 'Invalid XML file.', 'total-mail-queue' ) . '</p></div>';
    }

    if ( $xml->getName() !== 'total-mail-queue' ) {
        return '<div class="notice notice-error"><p>' . esc_html__( 'This file is not a valid Total Mail Queue export.', 'total-mail-queue' ) . '</p></div>';
    }

    // Import settings (whitelist allowed keys to prevent injection of tableName etc.)
    $allowed_keys = array(
        'enabled', 'alert_enabled', 'email', 'email_amount',
        'queue_amount', 'queue_interval', 'queue_interval_unit',
        'clear_queue', 'log_max_records', 'send_method', 'max_retries',
    );
    if ( isset( $xml->settings ) ) {
        $current_settings = get_option( 'wp_tmq_settings', array() );
        if ( ! is_array( $current_settings ) ) { $current_settings = array(); }
        foreach ( $xml->settings->children() as $node ) {
            $key = $node->getName();
            if ( in_array( $key, $allowed_keys, true ) ) {
                $current_settings[ $key ] = (string) $node;
            }
        }
        if ( ! empty( $current_settings ) ) {
            update_option( 'wp_tmq_settings', $current_settings );
        }
    }

    // Import SMTP accounts
    if ( isset( $xml->smtp_accounts ) ) {
        $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $columns = $wpdb->get_col( "DESCRIBE `$smtpTable`", 0 );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "TRUNCATE TABLE `$smtpTable`" );

        foreach ( $xml->smtp_accounts->account as $account_node ) {
            $account = array();
            foreach ( $account_node->children() as $field ) {
                $account[ $field->getName() ] = (string) $field;
            }
            unset( $account['id'] );
            $account = array_intersect_key( $account, array_flip( $columns ) );
            if ( ! empty( $account ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert( $smtpTable, $account );
            }
        }
    }

    // Reload settings
    $wp_tmq_options = wp_tmq_get_settings();

    $exported_at = (string) ( $xml['exported_at'] ?? '' );
    /* translators: %s: export date */
    $date_info = $exported_at ? ' ' . sprintf( __( '(exported on %s)', 'total-mail-queue' ), esc_html( $exported_at ) ) : '';
    return '<div class="notice notice-success"><p>' . esc_html__( 'Settings and SMTP accounts imported successfully.', 'total-mail-queue' ) . $date_info . '</p></div>';
}
