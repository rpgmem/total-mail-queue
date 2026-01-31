<?php

if (!defined('ABSPATH')) { exit; }

/* ***************************************************************
SMTP Accounts Page
**************************************************************** */

function wp_tmq_render_smtp_page() {

    // Only Admins
    if ( ! current_user_can( 'manage_options' ) ) { return; }

    global $wpdb, $wp_tmq_options;
    $smtpTable = $wpdb->prefix . $wp_tmq_options['smtpTableName'];

    $action = isset( $_GET['smtp-action'] ) ? sanitize_key( $_GET['smtp-action'] ) : '';
    $edit_id = isset( $_GET['smtp-id'] ) ? intval( $_GET['smtp-id'] ) : 0;

    // -------------------------------------------------------
    // Handle POST: Save / Delete / Reset Counters
    // -------------------------------------------------------

    // Save (Add / Edit)
    if ( isset( $_POST['wp_tmq_smtp_save'] ) ) {

        if ( ! isset( $_POST['wp_tmq_smtp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp_tmq_smtp_nonce'] ), 'wp_tmq_smtp_save' ) ) {
            wp_die( __( 'Security check failed!', 'total-mail-queue' ) );
        }

        $save_id = isset( $_POST['smtp_id'] ) ? intval( $_POST['smtp_id'] ) : 0;

        $data = array(
            'name'          => sanitize_text_field( wp_unslash( $_POST['smtp_name'] ?? '' ) ),
            'host'          => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ),
            'port'          => intval( $_POST['smtp_port'] ?? 587 ),
            'encryption'    => sanitize_key( $_POST['smtp_encryption'] ?? 'tls' ),
            'auth'          => isset( $_POST['smtp_auth'] ) ? 1 : 0,
            'username'      => sanitize_text_field( wp_unslash( $_POST['smtp_username'] ?? '' ) ),
            'from_email'    => sanitize_email( wp_unslash( $_POST['smtp_from_email'] ?? '' ) ),
            'from_name'     => sanitize_text_field( wp_unslash( $_POST['smtp_from_name'] ?? '' ) ),
            'priority'      => intval( $_POST['smtp_priority'] ?? 0 ),
            'daily_limit'   => intval( $_POST['smtp_daily_limit'] ?? 0 ),
            'monthly_limit' => intval( $_POST['smtp_monthly_limit'] ?? 0 ),
            'send_interval' => intval( $_POST['smtp_send_interval'] ?? 0 ),
            'send_bulk'     => intval( $_POST['smtp_send_bulk'] ?? 0 ),
            'enabled'       => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
        );

        // Only update password if a new value is provided
        $raw_password = isset( $_POST['smtp_password'] ) ? wp_unslash( $_POST['smtp_password'] ) : '';
        if ( $raw_password !== '' ) {
            $data['password'] = wp_tmq_encrypt_password( $raw_password );
        }

        if ( $save_id > 0 ) {
            $wpdb->update( $smtpTable, $data, array( 'id' => $save_id ), null, '%d' );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'SMTP account updated.', 'total-mail-queue' ) . '</p></div>';
        } else {
            if ( ! isset( $data['password'] ) ) {
                $data['password'] = '';
            }
            $wpdb->insert( $smtpTable, $data );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'SMTP account added.', 'total-mail-queue' ) . '</p></div>';
        }

        // Reset action so we show the list
        $action = '';
        $edit_id = 0;
    }

    // Delete
    if ( $action === 'delete' && $edit_id > 0 ) {

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp_tmq_smtp_delete_' . $edit_id ) ) {
            wp_die( __( 'Security check failed!', 'total-mail-queue' ) );
        }

        $wpdb->delete( $smtpTable, array( 'id' => $edit_id ), '%d' );
        echo '<div class="notice notice-success is-dismissible"><p>' . __( 'SMTP account deleted.', 'total-mail-queue' ) . '</p></div>';
        $action = '';
        $edit_id = 0;
    }

    // Reset Counters
    if ( isset( $_POST['wp_tmq_smtp_reset_counters'] ) ) {

        if ( ! isset( $_POST['wp_tmq_smtp_reset_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp_tmq_smtp_reset_nonce'] ), 'wp_tmq_smtp_reset_counters' ) ) {
            wp_die( __( 'Security check failed!', 'total-mail-queue' ) );
        }

        $wpdb->query( "UPDATE `$smtpTable` SET `daily_sent` = 0, `monthly_sent` = 0" );
        echo '<div class="notice notice-success is-dismissible"><p>' . __( 'All sending counters have been reset.', 'total-mail-queue' ) . '</p></div>';
    }

    // -------------------------------------------------------
    // Show Add/Edit Form
    // -------------------------------------------------------
    if ( $action === 'add' || $action === 'edit' ) {

        $account = array(
            'id'            => 0,
            'name'          => '',
            'host'          => '',
            'port'          => 587,
            'encryption'    => 'tls',
            'auth'          => 1,
            'username'      => '',
            'password'      => '',
            'from_email'    => '',
            'from_name'     => '',
            'priority'      => 0,
            'daily_limit'   => 0,
            'monthly_limit' => 0,
            'send_interval' => 0,
            'send_bulk'     => 0,
            'enabled'       => 1,
        );

        if ( $action === 'edit' && $edit_id > 0 ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$smtpTable` WHERE `id` = %d", $edit_id ), ARRAY_A );
            if ( $row ) {
                $account = $row;
            }
        }

        $is_edit = ( $action === 'edit' && $edit_id > 0 );
        $form_title = $is_edit ? __( 'Edit SMTP Account', 'total-mail-queue' ) : __( 'Add SMTP Account', 'total-mail-queue' );

        echo '<div class="tmq-box">';
        echo '<h3>' . esc_html( $form_title ) . '</h3>';
        echo '<form method="post" autocomplete="off" action="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp' ) ) . '">';
        // Hidden decoy fields to absorb browser autofill
        echo '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">';
        echo '<input type="text" name="tmq_decoy_user" tabindex="-1" />';
        echo '<input type="password" name="tmq_decoy_pass" tabindex="-1" />';
        echo '</div>';
        wp_nonce_field( 'wp_tmq_smtp_save', 'wp_tmq_smtp_nonce' );
        if ( $is_edit ) {
            echo '<input type="hidden" name="smtp_id" value="' . esc_attr( $account['id'] ) . '" />';
        }
        echo '<table class="form-table">';

        // Name
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_name">' . __( 'Name', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="text" id="smtp_name" name="smtp_name" value="' . esc_attr( $account['name'] ) . '" class="regular-text" /></td>';
        echo '</tr>';

        // Host
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_host">' . __( 'Host', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="text" id="smtp_host" name="smtp_host" value="' . esc_attr( $account['host'] ) . '" class="regular-text" /></td>';
        echo '</tr>';

        // Port
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_port">' . __( 'Port', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="number" id="smtp_port" name="smtp_port" value="' . esc_attr( $account['port'] ) . '" min="1" max="65535" /></td>';
        echo '</tr>';

        // Encryption
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_encryption">' . __( 'Encryption', 'total-mail-queue' ) . '</label></th>';
        echo '<td><select id="smtp_encryption" name="smtp_encryption">';
        echo '<option value="none"' . selected( $account['encryption'], 'none', false ) . '>' . __( 'None', 'total-mail-queue' ) . '</option>';
        echo '<option value="tls"' . selected( $account['encryption'], 'tls', false ) . '>' . __( 'TLS', 'total-mail-queue' ) . '</option>';
        echo '<option value="ssl"' . selected( $account['encryption'], 'ssl', false ) . '>' . __( 'SSL', 'total-mail-queue' ) . '</option>';
        echo '</select></td>';
        echo '</tr>';

        // Auth
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_auth">' . __( 'Authentication', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="checkbox" id="smtp_auth" name="smtp_auth" value="1"' . checked( $account['auth'], 1, false ) . ' /></td>';
        echo '</tr>';

        // Username
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_username">' . __( 'Username', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="text" id="smtp_username" name="smtp_username" value="' . esc_attr( $account['username'] ) . '" class="regular-text tmq-no-autofill" readonly="readonly" autocomplete="off" /></td>';
        echo '</tr>';

        // Password
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_password">' . __( 'Password', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="password" id="smtp_password" name="smtp_password" value="" class="regular-text tmq-no-autofill" readonly="readonly" autocomplete="off"';
        if ( $is_edit && ! empty( $account['password'] ) ) {
            echo ' placeholder="' . esc_attr( str_repeat( "\xE2\x80\xA2", 8 ) ) . '"';
        }
        echo ' />';
        if ( $is_edit && ! empty( $account['password'] ) ) {
            echo ' <span class="description">' . __( 'Leave blank to keep the current password.', 'total-mail-queue' ) . '</span>';
        }
        echo '</td>';
        echo '</tr>';

        // From Email
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_from_email">' . __( 'From Email', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="email" id="smtp_from_email" name="smtp_from_email" value="' . esc_attr( $account['from_email'] ) . '" class="regular-text" /></td>';
        echo '</tr>';

        // From Name
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_from_name">' . __( 'From Name', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="text" id="smtp_from_name" name="smtp_from_name" value="' . esc_attr( $account['from_name'] ) . '" class="regular-text" /></td>';
        echo '</tr>';

        // Priority
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_priority">' . __( 'Priority', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="number" id="smtp_priority" name="smtp_priority" value="' . esc_attr( $account['priority'] ) . '" min="0" /> ';
        echo '<span class="description">' . __( 'Lower number = higher priority.', 'total-mail-queue' ) . '</span></td>';
        echo '</tr>';

        // Daily Limit
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_daily_limit">' . __( 'Daily Limit', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="number" id="smtp_daily_limit" name="smtp_daily_limit" value="' . esc_attr( $account['daily_limit'] ) . '" min="0" /> ';
        echo '<span class="description">' . __( '0 = unlimited', 'total-mail-queue' ) . '</span></td>';
        echo '</tr>';

        // Monthly Limit
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_monthly_limit">' . __( 'Monthly Limit', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="number" id="smtp_monthly_limit" name="smtp_monthly_limit" value="' . esc_attr( $account['monthly_limit'] ) . '" min="0" /> ';
        echo '<span class="description">' . __( '0 = unlimited', 'total-mail-queue' ) . '</span></td>';
        echo '</tr>';

        // Send Interval
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_send_interval">' . __( 'Send Interval (minutes)', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="number" id="smtp_send_interval" name="smtp_send_interval" value="' . esc_attr( $account['send_interval'] ) . '" min="0" /> ';
        echo '<span class="description">' . __( 'Minimum minutes between sending cycles for this account. 0 = use global interval.', 'total-mail-queue' ) . '</span></td>';
        echo '</tr>';

        // Send Bulk
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_send_bulk">' . __( 'Emails per Cycle', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="number" id="smtp_send_bulk" name="smtp_send_bulk" value="' . esc_attr( $account['send_bulk'] ) . '" min="0" /> ';
        echo '<span class="description">' . __( 'Maximum emails to send per cycle for this account. 0 = use global limit.', 'total-mail-queue' ) . '</span></td>';
        echo '</tr>';

        // Enabled
        echo '<tr>';
        echo '<th scope="row"><label for="smtp_enabled">' . __( 'Enabled', 'total-mail-queue' ) . '</label></th>';
        echo '<td><input type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1"' . checked( $account['enabled'], 1, false ) . ' /></td>';
        echo '</tr>';

        echo '</table>';
        echo '<p class="submit">';
        echo '<input type="submit" name="wp_tmq_smtp_save" class="button button-primary" value="' . esc_attr__( 'Save SMTP Account', 'total-mail-queue' ) . '" /> ';
        echo '<button type="button" id="tmq-test-smtp" class="button" data-smtp-id="' . esc_attr( $account['id'] ) . '">' . esc_html__( 'Test Connection', 'total-mail-queue' ) . '</button> ';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp' ) ) . '" class="button">' . __( 'Cancel', 'total-mail-queue' ) . '</a>';
        echo '</p>';
        echo '<div id="tmq-test-smtp-result"></div>';
        echo '</form>';
        echo '</div>';

        return;
    }

    // -------------------------------------------------------
    // List SMTP Accounts
    // -------------------------------------------------------

    $accounts = $wpdb->get_results( "SELECT * FROM `$smtpTable` ORDER BY `priority` ASC, `name` ASC", ARRAY_A );

    echo '<div class="tmq-box">';
    echo '<h3>' . __( 'SMTP Accounts', 'total-mail-queue' ) . '</h3>';
    echo '<p>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp&smtp-action=add' ) ) . '" class="button button-primary">' . __( 'Add SMTP Account', 'total-mail-queue' ) . '</a> ';

    if ( ! empty( $accounts ) ) {
        echo '<form method="post" class="tmq-inline-form">';
        wp_nonce_field( 'wp_tmq_smtp_reset_counters', 'wp_tmq_smtp_reset_nonce' );
        echo '<input type="submit" name="wp_tmq_smtp_reset_counters" class="button" value="' . esc_attr__( 'Reset Counters', 'total-mail-queue' ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to reset all sending counters to zero?', 'total-mail-queue' ) ) . '\');" />';
        echo '</form>';
    }

    echo '</p>';

    if ( empty( $accounts ) ) {
        echo '<p>' . __( 'No SMTP accounts configured yet.', 'total-mail-queue' ) . '</p>';
    } else {
        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __( 'Name', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Host:Port', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'From', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Priority', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Daily Limit', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Monthly Limit', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Interval', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Per Cycle', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Enabled', 'total-mail-queue' ) . '</th>';
        echo '<th>' . __( 'Actions', 'total-mail-queue' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $accounts as $acct ) {

            $daily_display  = esc_html( $acct['daily_sent'] ) . ' / ';
            $daily_display .= intval( $acct['daily_limit'] ) === 0 ? __( 'unlimited', 'total-mail-queue' ) : esc_html( $acct['daily_limit'] );

            $monthly_display  = esc_html( $acct['monthly_sent'] ) . ' / ';
            $monthly_display .= intval( $acct['monthly_limit'] ) === 0 ? __( 'unlimited', 'total-mail-queue' ) : esc_html( $acct['monthly_limit'] );

            $from_display = esc_html( $acct['from_email'] );
            if ( ! empty( $acct['from_name'] ) ) {
                $from_display = esc_html( $acct['from_name'] ) . ' &lt;' . $from_display . '&gt;';
            }

            $edit_url = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp&smtp-action=edit&smtp-id=' . intval( $acct['id'] ) );
            $delete_url = wp_nonce_url(
                admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp&smtp-action=delete&smtp-id=' . intval( $acct['id'] ) ),
                'wp_tmq_smtp_delete_' . intval( $acct['id'] )
            );

            echo '<tr>';
            echo '<td>' . esc_html( $acct['name'] ) . '</td>';
            echo '<td>' . esc_html( $acct['host'] ) . ':' . esc_html( $acct['port'] ) . '</td>';
            echo '<td>' . $from_display . '</td>';
            echo '<td>' . esc_html( $acct['priority'] ) . '</td>';
            echo '<td>' . $daily_display . '</td>';
            echo '<td>' . $monthly_display . '</td>';

            $interval_display = intval( $acct['send_interval'] ) === 0 ? __( 'global', 'total-mail-queue' ) : esc_html( $acct['send_interval'] ) . ' min';
            $bulk_display     = intval( $acct['send_bulk'] ) === 0 ? __( 'global', 'total-mail-queue' ) : esc_html( $acct['send_bulk'] );
            echo '<td>' . $interval_display . '</td>';
            echo '<td>' . $bulk_display . '</td>';

            echo '<td>' . ( intval( $acct['enabled'] ) ? '<span class="tmq-ok">' . __( 'Yes', 'total-mail-queue' ) . '</span>' : __( 'No', 'total-mail-queue' ) ) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'total-mail-queue' ) . '</a> | ';
            echo '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this SMTP account?', 'total-mail-queue' ) ) . '\');">' . __( 'Delete', 'total-mail-queue' ) . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    echo '</div>';
}
