<?php
/**
 * Seed a throwaway WordPress with fictitious mail for the README screenshots.
 *
 * Usage: php .github/scripts/screenshots/seed.php /path/to/wp-load.php
 *
 * Run it once, against an EMPTY install with only this plugin active. Every
 * name, address and server below is invented, on the reserved example.org /
 * example.com / example.net domains. Never point this at a site with real
 * data: it writes queue rows, SMTP accounts and settings.
 *
 * @package TotalMailQueue
 */

// phpcs:ignoreFile -- Developer tooling run from the CLI against a throwaway install, never shipped.

use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\Repository as SmtpRepository;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Support\Encryption;
use TotalMailQueue\Support\Serializer;
use TotalMailQueue\Templates\Options as TemplateOptions;

if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) {
	fwrite( STDERR, "Usage: php seed.php /path/to/wp-load.php\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $argv[1];

wp_set_current_user( 1 );
mt_srand( 2110 );

// Nothing reaches a real inbox while seeding.
add_filter( 'pre_wp_mail', '__return_false' );

// Queue mode, an alert address and a short retention window.
update_option(
	Options::OPTION_NAME,
	array_merge(
		Options::get(),
		array(
			'enabled'        => '1',
			'alert_enabled'  => '1',
			'email'          => 'ops@example.org',
			'email_amount'   => '25',
			'queue_amount'   => '10',
			'queue_interval' => '5',
			'clear_queue'    => '30',
			'from_email'     => 'no-reply@example.org',
			'from_name'      => 'Example School',
		)
	)
);
TemplateOptions::update(
	array(
		'enabled'           => true,
		'header_bg'         => '#1f3a5f',
		'footer_text'       => 'Example School · 12 Sample Street · {site_url}',
		'footer_powered_by' => true,
	)
);

// SMTP accounts: two sending, one held in reserve.
$now      = current_time( 'mysql', false );
$accounts = array(
	array( 'Primary relay', 'smtp.example.org', 587, 'tls', 'news@example.org', 'Example School', 1, 2000, 40000, 312, 5120, 1 ),
	array( 'Transactional', 'mail.example.net', 465, 'ssl', 'orders@example.org', 'Example School Shop', 2, 500, 10000, 87, 1460, 1 ),
	array( 'Backup relay', 'relay.example.com', 587, 'tls', 'no-reply@example.org', 'Example School', 3, 300, 0, 0, 0, 0 ),
);
$smtp_ids = array();
foreach ( $accounts as $a ) {
	$smtp_ids[ $a[0] ] = SmtpRepository::insert(
		array(
			'name'               => $a[0],
			'host'               => $a[1],
			'port'               => $a[2],
			'encryption'         => $a[3],
			'auth'               => 1,
			'username'           => $a[4],
			'password'           => Encryption::encrypt( 'not-a-real-password' ),
			'from_email'         => $a[4],
			'from_name'          => $a[5],
			'priority'           => $a[6],
			'daily_limit'        => $a[7],
			'monthly_limit'      => $a[8],
			'daily_sent'         => $a[9],
			'monthly_sent'       => $a[10],
			'enabled'            => $a[11],
			'last_daily_reset'   => substr( $now, 0, 10 ),
			'last_monthly_reset' => substr( $now, 0, 8 ) . '01',
			'last_sent_at'       => $a[11] ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - mt_rand( 60, 900 ) ) : '2000-01-01 00:00:00',
		)
	);
}

// Sources: the shop on its own account, password resets first in line, and
// one source switched off.
$shop = SourcesRepository::findByKey( 'plugin:woocommerce' );
if ( $shop ) {
	SourcesRepository::updatePreferredAccount( (int) $shop['id'], (int) $smtp_ids['Transactional'] );
	SourcesRepository::updatePriority( (int) $shop['id'], 20 );
}
$reset = SourcesRepository::findByKey( 'wp_core:password_reset' );
if ( $reset ) {
	SourcesRepository::updatePriority( (int) $reset['id'], 1 );
}
$moderation = SourcesRepository::findByKey( 'wp_core:comment_moderation' );
if ( $moderation ) {
	SourcesRepository::setEnabled( (int) $moderation['id'], false );
}
$newsletter_id = SourcesRepository::register( 'plugin:newsletter', 'Newsletter', 'Plugins' );

// Invented people.
$people = array(
	'Ana Ribeiro', 'Bruno Costa', 'Carla Mendes', 'Daniel Souza', 'Elisa Martins',
	'Felipe Alves', 'Gabriela Rocha', 'Henrique Lima', 'Isabela Nunes', 'João Pereira',
	'Karina Duarte', 'Lucas Barros', 'Mariana Teixeira', 'Nicolas Freitas', 'Olivia Campos',
);
$domains = array( 'example.org', 'example.com', 'example.net' );
$address = static function ( string $name ) use ( $domains ): string {
	$slug = strtolower( remove_accents( str_replace( ' ', '.', $name ) ) );
	return $slug . '@' . $domains[ crc32( $slug ) % 3 ];
};

// What each source sends.
$kinds = array(
	array( 'plugin:woocommerce', 'Your Example School Shop order #%d has been received', 'Transactional', 50 ),
	array( 'plugin:woocommerce', 'Your order #%d is on its way', 'Transactional', 50 ),
	array( 'wp_core:password_reset', '[Example School] Password Reset', 'Primary relay', 1 ),
	array( 'wp_core:new_user', '[Example School] Login Details', 'Primary relay', 50 ),
	array( 'plugin:contact-form-7', 'Contact form: question about enrolment', 'Primary relay', 50 ),
	array( 'plugin:newsletter', 'October newsletter: open days and the new library', 'Primary relay', 50 ),
	array( 'wp_core:comment_notification', '[Example School] Comment: "Welcome to the new term"', 'Primary relay', 50 ),
);
$body = static function ( string $name, string $subject ): string {
	$first = strtok( $name, ' ' );
	return "Hello {$first},\n\n{$subject}.\n\nThis is a sample message written for the plugin's screenshots; nobody received it.\n\nExample School";
};

$insert = static function ( array $row ): int {
	foreach ( array( 'headers', 'attachments', 'info', 'error_log' ) as $column ) {
		$row[ $column ] = $row[ $column ] ?? '';
	}
	return QueueRepository::insert( $row );
};

$order = 1040;
$ts    = current_time( 'timestamp' );

// Three days of sent mail, newest last.
for ( $i = 60; $i >= 1; $i-- ) {
	$kind   = $kinds[ $i % count( $kinds ) ];
	$person = $people[ $i % count( $people ) ];
	$to     = 'plugin:newsletter' === $kind[0] ? 'parents@example.org' : $address( $person );
	$subj   = sprintf( $kind[1], $order++ );
	$insert(
		array(
			'timestamp'       => gmdate( 'Y-m-d H:i:s', $ts - $i * 71 * MINUTE_IN_SECONDS ),
			'status'          => 'sent',
			'recipient'       => Serializer::encode( $to ),
			'subject'         => $subj,
			'message'         => $body( $person, $subj ),
			'source_key'      => $kind[0],
			'priority'        => $kind[3],
			'smtp_account_id' => (int) $smtp_ids[ $kind[2] ],
		)
	);
}

// Two failures with their history, and the alert they raised.
$failures = array(
	array( 'karina.duarte@example.invalid', 'Your order #1102 has been received', 'plugin:woocommerce', '550 Mailbox not found', 'SMTP Error: The following recipients failed: karina.duarte@example.invalid: 550 5.1.1 Mailbox does not exist' ),
	array( 'lucas.barros@example.com', 'Contact form: question about transport', 'plugin:contact-form-7', 'Connection timed out', 'SMTP Error: Could not connect to SMTP host. Connection timed out (110)' ),
);
foreach ( $failures as $n => $f ) {
	$when = gmdate( 'Y-m-d H:i:s', $ts - ( 95 - $n * 30 ) * MINUTE_IN_SECONDS );
	$insert(
		array(
			'timestamp'   => $when,
			'status'      => 'error',
			'recipient'   => Serializer::encode( $f[0] ),
			'subject'     => $f[1],
			'message'     => $body( 'Customer', $f[1] ),
			'source_key'  => $f[2],
			'info'        => $f[3],
			'error_log'   => "[{$when}] Attempt 1: {$f[4]}\n[{$when}] Attempt 2: {$f[4]}\n[{$when}] Attempt 3: {$f[4]}",
			'retry_count' => 3,
		)
	);
}

// A comment-moderation email the disabled source held back.
$insert(
	array(
		'timestamp'  => gmdate( 'Y-m-d H:i:s', $ts - 40 * MINUTE_IN_SECONDS ),
		'status'     => 'blocked_by_source',
		'recipient'  => Serializer::encode( 'admin@example.org' ),
		'subject'    => '[Example School] Please moderate: "Welcome to the new term"',
		'message'    => 'A new comment is waiting for your approval.',
		'source_key' => 'wp_core:comment_moderation',
	)
);

// Waiting to be sent: a password reset at the front, then the newsletter.
$insert(
	array(
		'timestamp'  => gmdate( 'Y-m-d H:i:s', $ts - 2 * MINUTE_IN_SECONDS ),
		'status'     => 'queue',
		'recipient'  => Serializer::encode( 'olivia.campos@example.net' ),
		'subject'    => '[Example School] Password Reset',
		'message'    => $body( 'Olivia Campos', 'Someone asked to reset the password for your account' ),
		'source_key' => 'wp_core:password_reset',
		'priority'   => 1,
	)
);
foreach ( array_slice( $people, 0, 9 ) as $n => $person ) {
	$insert(
		array(
			'timestamp'  => gmdate( 'Y-m-d H:i:s', $ts - ( 9 - $n ) * MINUTE_IN_SECONDS ),
			'status'     => 'queue',
			'recipient'  => Serializer::encode( $address( $person ) ),
			'subject'    => 'October newsletter: open days and the new library',
			'message'    => $body( $person, 'October newsletter: open days and the new library' ),
			'source_key' => 'plugin:newsletter',
			'priority'   => 50,
		)
	);
}

// Counts on the Sources tab, as if the site had been running for a while.
global $wpdb;
$counts = array(
	'plugin:woocommerce'           => 1284,
	'wp_core:password_reset'       => 96,
	'wp_core:new_user'             => 41,
	'plugin:contact-form-7'        => 213,
	'plugin:newsletter'            => 3620,
	'wp_core:comment_notification' => 58,
	'wp_core:comment_moderation'   => 12,
);
$table = $wpdb->prefix . 'total_mail_queue_sources';
foreach ( $counts as $key => $count ) {
	$wpdb->update(
		$table,
		array(
			'total_count'  => $count,
			'detected_at'  => gmdate( 'Y-m-d H:i:s', $ts - 120 * DAY_IN_SECONDS ),
			'last_seen_at' => gmdate( 'Y-m-d H:i:s', $ts - mt_rand( 2, 600 ) * MINUTE_IN_SECONDS ),
		),
		array( 'source_key' => $key )
	);
}

// Sources the site never used show "Never", not the moment they were seeded.
$wpdb->query( "UPDATE `$table` SET `last_seen_at` = '2000-01-01 00:00:00' WHERE `total_count` = 0" );

// A role with read-only access, for the Access card.
\TotalMailQueue\Admin\Access::setRole( 'editor', \TotalMailQueue\Admin\Access::MANAGE, true );
\TotalMailQueue\Admin\Access::setRole( 'author', \TotalMailQueue\Admin\Access::VIEW, true );

echo "Seeded: 60 sent, 2 failed, 1 blocked, 10 queued; 3 SMTP accounts; newsletter source #{$newsletter_id}.\n";
