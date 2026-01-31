<?php

/* ***************************************************************
Options Page
**************************************************************** */

function wp_tmq_actionlinks ( $actions ) {
    $links = array(
       '<a href="'.admin_url('admin.php?page=wp_tmq_mail_queue').'">' . __( 'Settings', 'total-mail-queue' ) . '</a>',
       '<a href="'.admin_url('admin.php?page=wp_tmq_mail_queue-tab-log').'">' . __( 'Log', 'total-mail-queue' ) . '</a>',
       '<a href="'.admin_url('admin.php?page=wp_tmq_mail_queue-tab-queue').'">' . __( 'Retention', 'total-mail-queue' ) . '</a>',
       '<a href="'.admin_url('admin.php?page=wp_tmq_mail_queue-tab-smtp').'">' . __( 'SMTP', 'total-mail-queue' ) . '</a>',
       '<a href="'.admin_url('admin.php?page=wp_tmq_mail_queue-tab-faq').'">' . __( 'FAQ', 'total-mail-queue' ) . '</a>',
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
        wp_die( __( 'Security check failed!', 'total-mail-queue' ) );
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
            wp_die( __( 'Security check failed!', 'total-mail-queue' ) );
        }
        $import_notice = wp_tmq_handle_import();
    }

    // Get the active tab from the $_GET param
    $tab = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'wp_tmq_mail_queue';

    echo '<div class="wrap">';

    // Options Header
    echo '<h1 class="tmq-title"><img class="tmq-logo" src="'.esc_url(plugins_url('assets/img/total-mail-queue-logo-wordmark.svg', __FILE__)).'" alt="Total Mail Queue" width="308" height="56" /></h1>';
    if ($tab != 'wp_tmq_mail_queue-tab-croninfo' ) {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<div class="notice notice-warning notice-large">';
            $url = esc_url(get_option('siteurl').'/wp-cron.php');
            /* translators: %s: wp-cron.php URL */
            echo '<p><strong>' . __( 'Please note:', 'total-mail-queue' ) . '</strong><br />' . sprintf( __( 'Your normal WP Cron is disabled. Please make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ), '<a href="'.$url.'" target="_blank">'.$url.'</a>' ) . '</p>';
            echo '<p><a href="?page=wp_tmq_mail_queue-tab-croninfo">' . __( 'More information', 'total-mail-queue' ) . '</a></p>';
            echo '</div>';
        }
    }
    wp_tmq_settings_page_navi($tab); // Tabs

    // Options Page Content
    if ($tab == 'wp_tmq_mail_queue') {
        echo '<form action="options.php" method="post">';
        settings_fields('wp_tmq_settings');
        do_settings_sections('wp_tmq_settings_page');
        submit_button();
        echo '</form>';

        // Export / Import section
        echo '<hr />';
        echo '<h2>' . __( 'Export / Import', 'total-mail-queue' ) . '</h2>';

        if ( $import_notice ) {
            echo $import_notice;
        }

        echo '<div class="tmq-export-import" style="display:flex;gap:2em;flex-wrap:wrap;">';

        // Export
        echo '<div class="tmq-box" style="flex:1;min-width:300px;">';
        echo '<h3>' . __( 'Export', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . __( 'Download an XML file with all plugin settings and SMTP accounts. Passwords are included in encrypted form and can only be imported on a site with the same WordPress salt keys.', 'total-mail-queue' ) . '</p>';
        echo '<form method="post">';
        wp_nonce_field( 'wp_tmq_export', 'wp_tmq_export_nonce' );
        echo '<button type="submit" name="wp_tmq_export" class="button button-primary">' . __( 'Export Settings', 'total-mail-queue' ) . '</button>';
        echo '</form>';
        echo '</div>';

        // Import
        echo '<div class="tmq-box" style="flex:1;min-width:300px;">';
        echo '<h3>' . __( 'Import', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . __( 'Upload a previously exported XML file to restore settings and SMTP accounts. This will replace all current settings and SMTP accounts.', 'total-mail-queue' ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( 'wp_tmq_import', 'wp_tmq_import_nonce' );
        echo '<input type="file" name="wp_tmq_import_file" accept=".xml" required /> ';
        echo '<button type="submit" name="wp_tmq_import" class="button" onclick="return confirm(\'' . esc_js( __( 'This will replace all current settings and SMTP accounts. Continue?', 'total-mail-queue' ) ) . '\');">' . __( 'Import Settings', 'total-mail-queue' ) . '</button>';
        echo '</form>';
        echo '</div>';

        echo '</div>';

    } else if ($tab == 'wp_tmq_mail_queue-tab-log') {
        echo '<form method="post">';
        $logtable = new wp_tmq_Log_Table();
        $logtable->prepare_items();
        $logtable->display();
        echo '</form>';
    } else if ($tab == 'wp_tmq_mail_queue-tab-queue') {

        if ( isset( $_GET['resent'] ) ) {
            $resent_count = intval( $_GET['resent'] );
            if ( $resent_count > 0 ) {
                /* translators: %d: number of emails resent */
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( '%d email(s) have been added back to the queue for resending.', 'total-mail-queue' ), $resent_count ) . '</p></div>';
            }
        }

        if (isset($_GET['addtestmail'])) {
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
            $wpdb->insert($tableName,$data);
        }

        // Show mode-specific notices
        if ( $wp_tmq_options['enabled'] === '2' ) {
            echo '<div class="notice notice-error"><p><strong>' . __( 'Block Mode Active', 'total-mail-queue' ) . '</strong> — ' . __( 'All outgoing emails are being retained and will NOT be sent. No emails will leave this server.', 'total-mail-queue' ) . ' ' . sprintf( __( 'Change this in %sSettings%s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue">', '</a>' ) . '</p></div>';
        } else if ( $wp_tmq_options['enabled'] === '1' ) {
            $next_cron_timestamp = wp_next_scheduled('wp_tmq_mail_queue_hook');
            if ($next_cron_timestamp) {
                if ($next_cron_timestamp > time()) {
                    /* translators: %1$s: human-readable time diff, %2$s: scheduled time */
                    echo '<div class="notice notice-success"><p>' . sprintf( __( 'Next sending will be triggered in %1$s at %2$s.', 'total-mail-queue' ), esc_html( human_time_diff( $next_cron_timestamp ) ), esc_html( wp_date( 'H:i', $next_cron_timestamp ) ) ) . '</p></div>';
                }
            }
            // Show last cron run diagnostics
            $last_cron = get_option( 'wp_tmq_last_cron' );
            if ( $last_cron && is_array( $last_cron ) ) {
                $diag_parts = array();
                $diag_parts[] = '<strong>' . __( 'Last cron run:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['time'] ?? '—' );
                $diag_parts[] = '<strong>' . __( 'Result:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['result'] ?? '—' );
                if ( isset( $last_cron['queue_total'] ) ) {
                    $diag_parts[] = '<strong>' . __( 'Queue:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['queue_total'] ) . ' total, ' . esc_html( $last_cron['queue_batch'] ) . ' batch';
                    $diag_parts[] = '<strong>' . __( 'SMTP accounts:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['smtp_accounts'] );
                    $diag_parts[] = '<strong>' . __( 'Send method:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['send_method'] );
                    $diag_parts[] = '<strong>' . __( 'Sent:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['sent'] ) . ' | <strong>' . __( 'Errors:', 'total-mail-queue' ) . '</strong> ' . esc_html( $last_cron['errors'] );
                }
                $notice_class = ( $last_cron['result'] ?? '' ) === 'ok' ? 'notice-info' : 'notice-warning';
                echo '<div class="notice ' . $notice_class . '"><p>' . implode( ' &nbsp;|&nbsp; ', $diag_parts ) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p>' . sprintf( __( 'The plugin is currently disabled. Enable it in the %sSettings%s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue">', '</a>' ) . '</p></div>';
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
                    echo '<div class="notice notice-warning"><p><strong>' . __( 'Warning: conflicting email plugin detected.', 'total-mail-queue' ) . '</strong> ';
                    echo sprintf(
                        /* translators: %s: list of filter names */
                        __( 'The following filter(s) on %1$s may interfere with email sending: %2$s. Consider deactivating conflicting email plugins when using SMTP accounts from Total Mail Queue.', 'total-mail-queue' ),
                        '<code>pre_wp_mail</code>',
                        '<code>' . esc_html( implode( '</code>, <code>', $other_filters ) ) . '</code>'
                    );
                    echo '</p></div>';
                }
            }
        }

        echo '<form method="post" action="admin.php?page=wp_tmq_mail_queue-tab-queue">';
        $queuetable = new wp_tmq_Log_Table();
        $queuetable->prepare_items();
        $queuetable->display();
        echo '</form>';
    } else if ($tab == 'wp_tmq_mail_queue-tab-faq') {
        echo '<div class="tmq-box">';
        echo '<h3>' . __( 'How does this Plugin work?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . sprintf( __( 'If enabled this plugin intercepts the %s function. Instead of sending the mails directly, it stores them in the database and sends them step by step with a delay during the %s.', 'total-mail-queue' ), '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>', '<i>WP Cron</i>' ) . '</p>';
        echo '<p>' . __( 'Current state:', 'total-mail-queue' ) . ' ';
        if ($wp_tmq_options['enabled'] === '1') {
            echo '<b class="tmq-ok">' . __( 'The plugin is enabled', 'total-mail-queue' ) . '</b> ' . sprintf( __( 'All Mails sent through %1$s are delayed by the %2$sQueue%3$s.', 'total-mail-queue' ), '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>', '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' );
        } else if ($wp_tmq_options['enabled'] === '2') {
            echo '<b class="tmq-warning">' . __( 'Block mode is active', 'total-mail-queue' ) . '</b>. ' . __( 'All outgoing emails are being retained and will NOT be sent.', 'total-mail-queue' );
        } else {
            echo '<b>' . __( 'The plugin is disabled', 'total-mail-queue' ) . '</b>. ' . __( 'The plugin has no impact at the moment, no Mails inside the Queue are going to be sent.', 'total-mail-queue' );
        }
        echo '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . sprintf( __( 'Does this plugin change the way %sHOW%s emails are sent?', 'total-mail-queue' ), '<b>', '</b>' ) . '</h3>';
        echo '<p>' . sprintf( __( 'No, don\'t worry. This plugin only affects %1$sWHEN%2$s emails are sent, not how. It delays the sending (by the Queue), nonetheless all emails are sent through the standard %3$s function.', 'total-mail-queue' ), '<b>', '</b>', '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>' ) . '</p>';
        echo '<p>' . __( 'If you use SMTP for sending, or an external service like Mailgun, everything will still work as expected.', 'total-mail-queue' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . sprintf( __( 'Does this plugin work, if I have a Caching Plugin installed? E.g. %s or similar?', 'total-mail-queue' ), '<i>W3 Total Cache</i>' ) . '</h3>';
        /* translators: %s: wp-cron.php link */
        echo '<p>' . sprintf( __( 'If you\'re using a Caching plugin like %1$s, %2$s or any other caching solution which generates static html-files and serves them to visitors, you\'ll have to make sure you\'re calling the %3$s manually every couple of minutes.', 'total-mail-queue' ), '<i>W3 Total Cache</i>', '<i>WP Rocket</i>', '<a href="'.esc_url(get_option('siteurl')).'/wp-cron.php" target="_blank">' . __( 'wp-cron file', 'total-mail-queue' ) . '</a>' ) . '</p>';
        echo '<p>' . __( 'Otherwise your normal WP Cron wouldn\'t be called as often as it should be and scheduled messages would be sent with big delays.', 'total-mail-queue' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . __( 'What about Proxy-Caching, e.g. NGINX?', 'total-mail-queue' ) . '</h3>';
        /* translators: %s: WordPress Cron link */
        echo '<p>' . sprintf( __( 'Same situation here. Please make sure you\'re calling the %s by an external service or your webhoster every couple of minutes.', 'total-mail-queue' ), '<a href="'.esc_url(get_option('siteurl')).'/wp-cron.php" target="_blank">' . __( 'WordPress Cron', 'total-mail-queue' ) . '</a>' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . __( 'My form builder supports attachments. What about them?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . __( 'You are covered. All attachments are stored temporarily in the queue until they are sent along with their corresponding emails.', 'total-mail-queue' ) . '</p>';
        echo '</div>';
        echo '<div class="tmq-box">';
        echo '<h3>' . __( 'What are Queue alerts?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . __( 'This is a simple and effective way to improve the security of your WordPress installation.', 'total-mail-queue' ) . '</p>';
        echo '<p>' . sprintf( __( 'Imagine: In case your website is sending spam through %s, the email Queue would fill up very quickly preventing your website from sending so many spam emails at once. This gives you time and avoids a lot of trouble.', 'total-mail-queue' ), '<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>' ) . '</p>';
        echo '<p>' . __( 'Queue Alerts warn you, if the Queue is longer than usual. You decide at which point you want to be alerted. So you get the chance to have a look if there might be something wrong on the website.', 'total-mail-queue' ) . '</p>';
        echo '<p>' . __( 'Current state:', 'total-mail-queue' ) . ' ';
        if ($wp_tmq_options['alert_enabled'] == '1') {
            /* translators: %1$s: email amount threshold, %2$s: alert email address */
            echo '<b class="tmq-ok">' . __( 'Alerts are enabled', 'total-mail-queue' ) . '</b> ' . sprintf( __( 'If more than %1$s emails are waiting in the Queue, WordPress will send an alert email to %2$s.', 'total-mail-queue' ), esc_html( $wp_tmq_options['email_amount'] ), '<i>' . esc_html( $wp_tmq_options['email'] ) . '</i>' );
        } else {
            echo '<b>' . __( 'Alerting is disabled', 'total-mail-queue' ) . '</b>. ' . __( 'No alerts will be sent.', 'total-mail-queue' );
        }
        echo '</p>';
        echo '<p>' . __( 'Please note: This plugin will only send one alert every six hours.', 'total-mail-queue' ) . '</p>';
        echo '</div>';

        echo '<div class="tmq-box">';
            echo '<h3>' . __( 'Can I add emails with a high priority to the queue?', 'total-mail-queue' ) . '</h3>';
            echo '<p>' . __( 'Yes, you can add the custom <i>`X-Mail-Queue-Prio`</i> header set to <i>`High`</i> to your email. High priority emails will be sent through the standard Total Mail Queue sending cycle but before all normal emails lacking a priority header in the queue.', 'total-mail-queue' ) . '</p>';
            /* translators: Example code labels - not translatable code blocks */
            echo '<p><b>' . __( 'Example 1 (add priority to Woocommerce emails):', 'total-mail-queue' ) . '</b></p>';
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
            echo '<p><b>' . __( 'Example 2 (add priority to Contact Form 7 form emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<p>' . __( 'When editing a form in Contact Form 7 just add an additional line to the <i>`Additional Headers`</i> field under the <i>`Mail`</i> tab panel.', 'total-mail-queue' ) . '</p>';
            echo '<pre><code>X-Mail-Queue-Prio: High</code></pre>';
            echo '<p><b>' . __( 'Example 3 (add priority to WordPress reset password emails):', 'total-mail-queue' ) . '</b></p>';
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
            echo '<h3>' . __( 'Can I send emails <i>instantly</i> without going through the queue?', 'total-mail-queue' ) . '</h3>';
            echo '<p>' . __( 'Yes, this is possible (if you absolutely need to do this).', 'total-mail-queue' ) . '</p>';
            echo '<p>' . __( 'For this you can add the custom <i>`X-Mail-Queue-Prio`</i> header set to <i>`Instant`</i> to your email. These emails are sent instantly circumventing the mail queue. They still appear in the Total Mail Queue log flagged as `instant`.', 'total-mail-queue' ) . '</p>';
            echo '<p>' . __( 'Mind that this is a potential security risk and should be considered carefully. Please use only as an exception.', 'total-mail-queue' ) . '</p>';
            echo '<p><b>' . __( 'Example 1 (instantly send Woocommerce emails):', 'total-mail-queue' ) . '</b></p>';
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
            echo '<p><b>' . __( 'Example 2 (instantly send Contact Form 7 form emails):', 'total-mail-queue' ) . '</b></p>';
            echo '<p>' . __( 'When editing a form in Contact Form 7 just add an additional line to the <i>`Additional Headers`</i> field under the <i>`Mail`</i> tab panel.', 'total-mail-queue' ) . '</p>';
            echo '<pre><code>X-Mail-Queue-Prio: Instant</code></pre>';
            echo '<p><b>' . __( 'Example 3 (instantly send WordPress reset password emails):', 'total-mail-queue' ) . '</b></p>';
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
        echo '<h3>' . __( 'What is the "Send Method" setting?', 'total-mail-queue' ) . '</h3>';
        echo '<p>' . __( 'The Send Method setting controls how emails from the retention queue are delivered. There are three options:', 'total-mail-queue' ) . '</p>';
        echo '<ul>';
        echo '<li><b>' . __( 'Automatic', 'total-mail-queue' ) . '</b> — ' . __( 'This is the default. The plugin will first try to use an SMTP account configured in the SMTP Accounts tab. If no SMTP account is available (none configured or all have reached their limits), it will try to replay any captured SMTP configuration from other plugins. If neither is available, it falls back to the standard WordPress wp_mail() function.', 'total-mail-queue' ) . '</li>';
        echo '<li><b>' . __( 'Plugin SMTP only', 'total-mail-queue' ) . '</b> — ' . __( 'Emails will ONLY be sent via SMTP accounts configured in this plugin. If no account is available (limits reached or none configured), emails will remain in the retention queue waiting until an SMTP account becomes available. This is useful if you want to guarantee all emails go through your own SMTP servers.', 'total-mail-queue' ) . '</li>';
        echo '<li><b>' . __( 'WordPress default', 'total-mail-queue' ) . '</b> — ' . __( 'Ignores all SMTP accounts configured in this plugin and any captured configurations. Emails are sent using whatever wp_mail() does by default — which could be the PHP mail() function or another SMTP plugin like WP Mail SMTP.', 'total-mail-queue' ) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="tmq-box">';
        echo '<h3>' . __( 'Want to put a test email into the Queue?', 'total-mail-queue' ) . '</h3>';
        /* translators: %s: admin email address */
        echo '<p><a class="button" href="admin.php?page=wp_tmq_mail_queue-tab-queue&addtestmail">' . sprintf( __( 'Sure! Put a Test Email for %s into the Queue', 'total-mail-queue' ), esc_html( $wp_tmq_options['email'] ) ) . '</a></p>';
        echo '</div>';

    } else if ($tab == 'wp_tmq_mail_queue-tab-smtp') {
        wp_tmq_render_smtp_page();
    } else if ($tab == 'wp_tmq_mail_queue-tab-croninfo') {
        echo '<div class="tmq-box">';
            echo '<h3>' . __( 'Information: Your common WP Cron is disabled', 'total-mail-queue' ) . '</h3>';
            echo '<p>' . __( 'It looks like you deactivated the WP Cron by <i>define( \'DISABLE_WP_CRON\', true )</i>.', 'total-mail-queue' ) . '</p>';
            $url = esc_url(get_option('siteurl').'/wp-cron.php');
            /* translators: %s: wp-cron.php URL */
            echo '<p>' . sprintf( __( 'In general, this is no problem at all. We just want to remind you to make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ), '<a href="'.$url.'" target="_blank">'.$url.'</a>' ) . '</p>';
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
                            echo '<h3>' . __( 'Attention: It seems that your WP Cron is not running. There are some jobs waiting to be completed.', 'total-mail-queue' ) . '</h3>';
                            if ($tasks_of_mailqueue_in_past) {
                                /* translators: %s: human-readable time diff */
                                echo '<p><b>' . sprintf( __( 'The Queue hasn\'t been able to be executed since %s.', 'total-mail-queue' ), esc_html( human_time_diff( $tasks_of_mailqueue_in_past, time() ) ) ) . '</b></p>';
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

    function get_log( $status_filter = '' ) {
        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        $where = "`status` != 'queue' AND `status` != 'high'";
        if ( $status_filter && in_array( $status_filter, array( 'sent', 'error', 'alert' ), true ) ) {
            $where = $wpdb->prepare( "`status` = %s", $status_filter );
        }
        return $wpdb->get_results("SELECT * FROM `$tableName` WHERE $where ORDER BY `timestamp` DESC",'ARRAY_A');
    }

    function get_queue() {
        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
        return $wpdb->get_results("SELECT * FROM `$tableName` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `status` ASC, `retry_count` ASC, `timestamp` ASC",'ARRAY_A');
    }

    function get_columns() {
        $columns = array(
            'cb'          => '<label><span class="screen-reader-text">' . __( 'Select all', 'total-mail-queue' ) . '</span><input class="tmq-select-all" type="checkbox"></label>',
            'timestamp'   => __( 'Time', 'total-mail-queue' ),
            'status'      => __( 'Status', 'total-mail-queue' ),
            'info'        => __( 'Info', 'total-mail-queue' ),
            'recipient'   => __( 'Recipient', 'total-mail-queue' ),
            'subject'     => __( 'Subject', 'total-mail-queue' ),
            'message'     => __( 'Message', 'total-mail-queue' ),
            'headers'     => __( 'Headers', 'total-mail-queue' ),
            'attachments' => __( 'Attachments', 'total-mail-queue' ),
        );
        return $columns;
    }

    protected function extra_tablenav( $which ) {
        $type = sanitize_key( $_GET['page'] );
        if ( $type !== 'wp_tmq_mail_queue-tab-log' || $which !== 'top' ) {
            return;
        }
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
        $type = sanitize_key($_GET['page']);
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();
        if ($type == 'wp_tmq_mail_queue-tab-log') {
            $status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : '';
            $data = $this->get_log( $status_filter );
        } else if ($type == 'wp_tmq_mail_queue-tab-queue') {
            $data = $this->get_queue();
        }
        if ($data && is_array($data)) {
            $perPage = 50;
            $currentPage = $this->get_pagenum();
            $totalItems = count($data);
            $this->set_pagination_args( array(
                'total_items' => $totalItems,
                'per_page'    => $perPage
            ) );
            $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
        }
        $this->items = $data;
    }

    public function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'timestamp':
            case 'subject':
                return esc_html( maybe_unserialize($item[$column_name]) );
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
                $return = maybe_unserialize($item[$column_name]);
                if (is_array($return)) {
                    return esc_html( implode(',',$return) );
                } else {
                    return esc_html( $return );
                }
                break;
            case 'attachments':
                $return = maybe_unserialize($item[$column_name]);
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
                return '<span class="tmq-status tmq-status-' . sanitize_title( $item[$column_name] ) . '">' . esc_html( $item[$column_name] ) . '</span>';
                break;
            case 'message':
                $message = $item[$column_name];
                if ( $message ) {
                    $messageLen = strlen($message);
                    $return  = '<details>';
                    /* translators: %s: message size in bytes */
                    $return .=   '<summary class="tmq-view-source" data-tmq-list-message-toggle="'.esc_attr($item['id']).'">' . sprintf( __( 'View message %s', 'total-mail-queue' ), '<i>(' . esc_html( $messageLen ) . ' bytes)</i>' ) . '</summary>';
                    $return .=   '<div class="tmq-email-source" data-tmq-list-message-content>' . __( 'Loading...', 'total-mail-queue' ) . '</div>';
                    $return .= '</details>';
                } else {
                    $return = '<em>' . __( 'Empty', 'total-mail-queue' ) . '</em>';
                }
                return $return;
                break;
            default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

    protected function column_cb ( $item ) {
        return '<input type="checkbox" name="id[]" value="'.esc_attr($item['id']).'" />';
    }

    public function get_bulk_actions() {
        if (isset($_GET['page']) && $_GET['page'] == 'wp_tmq_mail_queue-tab-queue') {
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

        // security check!
        if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['_wpnonce'] ) ) {
            $nonce  = sanitize_key( $_POST['_wpnonce'] );
            $action = 'bulk-' . $this->_args['plural'];
            if ( ! wp_verify_nonce( $nonce, $action ) )
                wp_die( __( 'Security check failed!', 'total-mail-queue' ) );
        }

        // get IDs
        $request_ids = isset( $_REQUEST['id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['id'] ) ) : array();
        if ( empty( $request_ids ) ) { return; }

        global $wpdb, $wp_tmq_options;
        $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];

        switch ( $this->current_action() ) {
            case 'delete':
                foreach($request_ids as $id) {
                    $wpdb->delete($tableName,array('id'=>intval($id)),'%d');
                }
                break;
            case 'resend':
                $count_resend = 0;
                $count_error  = 0;
                foreach($request_ids as $id) {
                    $maildata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `id` = %d", intval( $id ) ) );
                    if (!$maildata->attachments || $maildata->attachments == '') {
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
                        $wpdb->insert($tableName,$data);
                        // Remove original log entry to prevent duplicate resends
                        $wpdb->delete( $tableName, array( 'id' => intval( $id ) ), '%d' );
                    } else {
                        $count_error++;
                        $notice = '<div class="notice notice-error is-dismissible">';
                        /* translators: %s: recipient email address */
                        $notice .= '<p><b>' . sprintf( __( 'Sorry, your email to %s can\'t be sent again.', 'total-mail-queue' ), esc_html( $maildata->recipient ) ) . '</b></p>';
                        $notice .= '<p>' . __( 'The email used to have attachments, which are not available anymore. Only emails without attachments can be resent.', 'total-mail-queue' ) . '</p>';
                        $notice .= '</div>';
                        echo $notice;
                    }
                }
                if ($count_error == 0 && $count_resend > 0) {
                    wp_redirect('admin.php?page=wp_tmq_mail_queue-tab-queue&resent=' . $count_resend);
                    exit;
                } else if ($count_error > 0 && $count_resend > 0) {
                    $notice = '<div class="notice notice-success is-dismissible">';
                    $notice .= '<p>' . sprintf( __( 'The other emails have been put again into the %sQueue%s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) . '</p>';
                    $notice .= '</div>';
                    echo $notice;
                }
                break;
            case 'force_resend':
                $count_force = 0;
                $count_force_error = 0;
                foreach($request_ids as $id) {
                    $maildata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$tableName` WHERE `id` = %d", intval( $id ) ) );
                    if ( ! $maildata ) { continue; }
                    if ( $maildata->status !== 'error' ) {
                        $count_force_error++;
                        continue;
                    }
                    if ( ! $maildata->attachments || $maildata->attachments == '' ) {
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
                        $wpdb->insert( $tableName, $data );
                        // Remove original log entry to prevent duplicate resends
                        $wpdb->delete( $tableName, array( 'id' => intval( $id ) ), '%d' );
                    } else {
                        $count_force_error++;
                        $notice = '<div class="notice notice-error is-dismissible">';
                        /* translators: %s: recipient email address */
                        $notice .= '<p><b>' . sprintf( __( 'Sorry, your email to %s can\'t be sent again.', 'total-mail-queue' ), esc_html( $maildata->recipient ) ) . '</b></p>';
                        $notice .= '<p>' . __( 'The email used to have attachments, which are not available anymore. Only emails without attachments can be resent.', 'total-mail-queue' ) . '</p>';
                        $notice .= '</div>';
                        echo $notice;
                    }
                }
                if ( $count_force_error == 0 && $count_force > 0 ) {
                    wp_redirect( 'admin.php?page=wp_tmq_mail_queue-tab-queue&resent=' . $count_force );
                    exit;
                } else if ( $count_force > 0 ) {
                    $notice = '<div class="notice notice-success is-dismissible">';
                    /* translators: %1$d: number of emails resent, %2$s: link open, %3$s: link close */
                    $notice .= '<p>' . sprintf( __( '%1$d email(s) have been force-resent to the %2$sRetention%3$s queue.', 'total-mail-queue' ), $count_force, '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) . '</p>';
                    $notice .= '</div>';
                    echo $notice;
                }
                break;
        }

        return;

    }

}

function wp_tmq_settings_page_navi($tab) {
    echo '<nav class="nav-tab-wrapper">';
        echo '<a href="?page=wp_tmq_mail_queue" class="nav-tab'; if($tab==='wp_tmq_mail_queue') { echo ' nav-tab-active'; } echo '">' . __( 'Settings', 'total-mail-queue' ) . '</a>';
        echo '<a href="?page=wp_tmq_mail_queue-tab-log" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-log') { echo ' nav-tab-active'; } echo '">' . __( 'Log', 'total-mail-queue' ) . '</a>';
        echo '<a href="?page=wp_tmq_mail_queue-tab-queue" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-queue') { echo ' nav-tab-active'; } echo '">' . __( 'Retention', 'total-mail-queue' ) . '</a>';
        echo '<a href="?page=wp_tmq_mail_queue-tab-smtp" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-smtp') { echo ' nav-tab-active'; } echo '">' . __( 'SMTP Accounts', 'total-mail-queue' ) . '</a>';
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<a href="?page=wp_tmq_mail_queue-tab-croninfo" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-croninfo') { echo ' nav-tab-active'; } echo '">' . __( 'Cron Information', 'total-mail-queue' ) . '</a>';
        }
        echo '<a href="?page=wp_tmq_mail_queue-tab-faq" class="nav-tab'; if($tab==='wp_tmq_mail_queue-tab-faq') { echo ' nav-tab-active'; } echo '">' . __( 'FAQ', 'total-mail-queue' ) . '</a>';
    echo '</nav>';
}

function wp_tmq_settings_init() {
    global $wp_tmq_options;
    register_setting('wp_tmq_settings','wp_tmq_settings');
    add_settings_section('wp_tmq_settings_section','',null,'wp_tmq_settings_page');
    add_settings_field('wp_tmq_status', __( 'Operation Mode', 'total-mail-queue' ),'wp_tmq_render_option_status','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_queue', __( 'Queue', 'total-mail-queue' ),'wp_tmq_render_option_queue','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_log', __( 'Log', 'total-mail-queue' ),'wp_tmq_render_option_log','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_send_method', __( 'Send Method', 'total-mail-queue' ),'wp_tmq_render_option_send_method','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_retry', __( 'Auto-Retry', 'total-mail-queue' ),'wp_tmq_render_option_retry','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_alert_status', __( 'Alert enabled', 'total-mail-queue' ),'wp_tmq_render_option_alert_status','wp_tmq_settings_page','wp_tmq_settings_section');
    add_settings_field('wp_tmq_sensitivity', __( 'Alert Sensitivity', 'total-mail-queue' ),'wp_tmq_render_option_sensitivity','wp_tmq_settings_page','wp_tmq_settings_section');
}
add_action('admin_init','wp_tmq_settings_init');

function wp_tmq_render_option_status() {
    global $wp_tmq_options;
    $mode = $wp_tmq_options['enabled'];
    echo '<select name="wp_tmq_settings[enabled]">';
    echo '<option value="0"' . selected( $mode, '0', false ) . '>' . __( 'Disabled — No interception, emails sent normally', 'total-mail-queue' ) . '</option>';
    echo '<option value="1"' . selected( $mode, '1', false ) . '>' . __( 'Queue — Retain emails and send via queue', 'total-mail-queue' ) . '</option>';
    echo '<option value="2"' . selected( $mode, '2', false ) . '>' . __( 'Block — Retain all emails, never send (block all outgoing)', 'total-mail-queue' ) . '</option>';
    echo '</select>';
    if ( $mode === '0' ) {
        echo ' <span class="tmq-warning">' . __( 'The plugin is currently disabled and has no effect.', 'total-mail-queue' ) . '</span>';
    } else if ( $mode === '2' ) {
        echo ' <span class="tmq-warning tmq-warning-block">' . __( 'All outgoing emails are being blocked!', 'total-mail-queue' ) . '</span>';
    }
}

function wp_tmq_render_option_alert_status() {
    global $wp_tmq_options;
    if ($wp_tmq_options['alert_enabled'] == '1') {
        echo '<input type="checkbox" name="wp_tmq_settings[alert_enabled]" value="1" checked />';
    } else {
        echo '<input type="checkbox" name="wp_tmq_settings[alert_enabled]" value="1" />';
    }
}

function wp_tmq_render_option_queue() {
    global $wp_tmq_options;
    if ($wp_tmq_options['queue_interval_unit'] == 'seconds') {
        $number = intval($wp_tmq_options['queue_interval']);
    } else {
        $number = intval($wp_tmq_options['queue_interval']) / 60;
    }

    echo __( 'Send max.', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[queue_amount]" type="number" min="1" value="'.esc_attr($wp_tmq_options['queue_amount']).'" /> ' . __( 'email(s) every', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[queue_interval]" type="number" min="1" value="'.esc_attr($number).'" />';

    $min_selected = ($wp_tmq_options['queue_interval_unit'] != 'seconds') ? ' selected' : '';
    $sec_selected = ($wp_tmq_options['queue_interval_unit'] == 'seconds') ? ' selected' : '';
    echo '<select name="wp_tmq_settings[queue_interval_unit]"><option'.$min_selected.' value="minutes">' . __( 'minute(s)', 'total-mail-queue' ) . '</option><option'.$sec_selected.' value="seconds">' . __( 'second(s)', 'total-mail-queue' ) . '</option></select>';

    echo ' ' . sprintf( __( 'by %sWP Cron%s.', 'total-mail-queue' ), '<i><a href="https://developer.wordpress.org/plugins/cron/" target="_blank">', '</a></i>' ) . ' ';

}

function wp_tmq_render_option_log() {
    global $wp_tmq_options;
    echo __( 'Delete Log entries older than', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[clear_queue]" type="number" min="1" value="'.esc_attr(intval($wp_tmq_options['clear_queue']) / 24).'" /> ' . __( 'days.', 'total-mail-queue' );
    echo '<br /><br />';
    echo __( 'Keep a maximum of', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[log_max_records]" type="number" min="0" value="'.esc_attr(intval($wp_tmq_options['log_max_records'])).'" /> ' . __( 'log records.', 'total-mail-queue' );
    echo ' <span class="description">' . __( '0 = unlimited', 'total-mail-queue' ) . '</span>';
}

function wp_tmq_render_option_retry() {
    global $wp_tmq_options;
    echo __( 'If sending fails, retry up to', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[max_retries]" type="number" min="0" value="'.esc_attr(intval($wp_tmq_options['max_retries'])).'" /> ' . __( 'time(s) before marking as error.', 'total-mail-queue' );
    echo ' <span class="description">' . __( '0 = no retries, email is immediately marked as error', 'total-mail-queue' ) . '</span>';
}

function wp_tmq_render_option_send_method() {
    global $wp_tmq_options;
    $method = isset( $wp_tmq_options['send_method'] ) ? $wp_tmq_options['send_method'] : 'auto';
    echo '<select name="wp_tmq_settings[send_method]">';
    echo '<option value="auto"' . selected( $method, 'auto', false ) . '>' . __( 'Automatic — Use plugin SMTP if available, then captured config, then WordPress default', 'total-mail-queue' ) . '</option>';
    echo '<option value="smtp"' . selected( $method, 'smtp', false ) . '>' . __( 'Plugin SMTP only — Only send via SMTP accounts configured in this plugin (hold emails if none available)', 'total-mail-queue' ) . '</option>';
    echo '<option value="php"' . selected( $method, 'php', false ) . '>' . __( 'WordPress default — Ignore plugin SMTP accounts, send via standard wp_mail()', 'total-mail-queue' ) . '</option>';
    echo '</select>';
    if ( $method === 'smtp' ) {
        echo ' <span class="description">' . __( 'Emails will wait in the retention queue until an SMTP account with available limits is found.', 'total-mail-queue' ) . '</span>';
    }
}

function wp_tmq_render_option_sensitivity() {
    global $wp_tmq_options;
    echo __( 'Send alert to', 'total-mail-queue' ) . ' <input type="text" name="wp_tmq_settings[email]" value="'.esc_attr(sanitize_email($wp_tmq_options['email'])).'" /> ' . __( 'if more than', 'total-mail-queue' ) . ' <input name="wp_tmq_settings[email_amount]" type="number" min="1" value="'.esc_attr(intval($wp_tmq_options['email_amount'])).'" /> ' . sprintf( __( 'email(s) in the %sQueue%s.', 'total-mail-queue' ), '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' );
}





/* ***************************************************************
Alert WordPress User if last email in log could not be sent
**************************************************************** */
function wp_tmq_checkLogForErrors() {

    global $wpdb,$wp_tmq_options;
    if ( ! in_array( $wp_tmq_options['enabled'], array( '1', '2' ) ) ) { return; }

    $tableName = $wpdb->prefix.$wp_tmq_options['tableName'];
    $last_mail = $wpdb->get_row("SELECT * FROM `$tableName` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `id` DESC",'ARRAY_A');
    if (!$last_mail) { return; }

    if ($last_mail['status'] == 'error') {
        if (current_user_can('manage_options')) {
            $notice = '<div class="notice notice-error is-dismissible">';
            $notice .= '<h1>' . __( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
            $notice .= '<p>' . sprintf( __( 'This is an important message from your %sTotal Mail Queue%s plugin. Please take a look at your %sMail Log%s. The last email(s) couldn\'t be sent properly.', 'total-mail-queue' ), '<i>', '</i>', '<a href="admin.php?page=wp_tmq_mail_queue-tab-log">', '</a>' ) . '</p>';
            /* translators: %s: error message */
            $notice .= '<p>' . sprintf( __( 'Last error message was: %s', 'total-mail-queue' ), '<b>' . esc_html( $last_mail['info'] ) . '</b>' ) . '</p>';
            $notice .= '</div>';
            echo $notice;
        } else if (current_user_can('edit_posts')) {
            $notice = '<div class="notice notice-error is-dismissible">';
            $notice .= '<h1>' . __( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
            $notice .= '<p>' . __( 'Please contact your Administrator. It seems that WordPress is not able to send emails.', 'total-mail-queue' ) . '</p>';
            /* translators: %s: error message */
            $notice .= '<p>' . sprintf( __( 'Last error message: %s', 'total-mail-queue' ), '<b>' . esc_html( $last_mail['info'] ) . '</b>' ) . '</p>';
            $notice .= '</div>';
            echo $notice;
        }
    }

    // notices for the plugin options page
    $currentScreen = get_current_screen();
    if ($currentScreen->base == 'toplevel_page_wp_tmq_mail_queue') {
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
            $notice .=     '<strong>' . __( 'Please note:', 'total-mail-queue' ) . '</strong>';
            $notice .=     '<br />' . __( 'This plugin is not supported when using in combination with plugins that do not use the standard <i>wp_mail()</i> function.', 'total-mail-queue' );
            $notice .=   '</p>';
            $notice .=   '<p>';
            $notice .=     __( 'It seems you are using the following plugin(s) that do not use <i>wp_mail()</i>:', 'total-mail-queue' );
            $notice .=     '<br />'.implode(', ', array_map(function($plugin) use ($wpMailOmittingPlugins) { return $wpMailOmittingPlugins[$plugin]; },$wpMailOmittingPluginsInstalled));
            $notice .=   '</p>';
            $notice .=   '<p><a href="'.get_admin_url(null,'admin.php?page=wp_tmq_mail_queue-tab-faq').'">' . __( 'More information', 'total-mail-queue' ) . '</a></p>';
            $notice .= '</div>';
            echo $notice;
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
    echo $dom->saveXML();
    exit;
}

function wp_tmq_handle_import() {
    global $wpdb, $wp_tmq_options;

    if ( ! isset( $_FILES['wp_tmq_import_file'] ) || $_FILES['wp_tmq_import_file']['error'] !== UPLOAD_ERR_OK ) {
        return '<div class="notice notice-error"><p>' . __( 'Error uploading file. Please try again.', 'total-mail-queue' ) . '</p></div>';
    }

    $file_content = file_get_contents( $_FILES['wp_tmq_import_file']['tmp_name'] );

    // Suppress XML errors and load
    $use_errors = libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $file_content );
    libxml_use_internal_errors( $use_errors );

    if ( ! $xml ) {
        return '<div class="notice notice-error"><p>' . __( 'Invalid XML file.', 'total-mail-queue' ) . '</p></div>';
    }

    if ( $xml->getName() !== 'total-mail-queue' ) {
        return '<div class="notice notice-error"><p>' . __( 'This file is not a valid Total Mail Queue export.', 'total-mail-queue' ) . '</p></div>';
    }

    // Import settings
    if ( isset( $xml->settings ) ) {
        $settings = array();
        foreach ( $xml->settings->children() as $node ) {
            $settings[ $node->getName() ] = (string) $node;
        }
        if ( ! empty( $settings ) ) {
            update_option( 'wp_tmq_settings', $settings );
        }
    }

    // Import SMTP accounts
    if ( isset( $xml->smtp_accounts ) ) {
        $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
        $columns = $wpdb->get_col( "DESCRIBE `$smtpTable`", 0 );

        $wpdb->query( "TRUNCATE TABLE `$smtpTable`" );

        foreach ( $xml->smtp_accounts->account as $account_node ) {
            $account = array();
            foreach ( $account_node->children() as $field ) {
                $account[ $field->getName() ] = (string) $field;
            }
            unset( $account['id'] );
            $account = array_intersect_key( $account, array_flip( $columns ) );
            if ( ! empty( $account ) ) {
                $wpdb->insert( $smtpTable, $account );
            }
        }
    }

    // Reload settings
    $wp_tmq_options = wp_tmq_get_settings();

    $exported_at = (string) ( $xml['exported_at'] ?? '' );
    /* translators: %s: export date */
    $date_info = $exported_at ? ' ' . sprintf( __( '(exported on %s)', 'total-mail-queue' ), esc_html( $exported_at ) ) : '';
    return '<div class="notice notice-success"><p>' . __( 'Settings and SMTP accounts imported successfully.', 'total-mail-queue' ) . $date_info . '</p></div>';
}
