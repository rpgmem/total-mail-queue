<?php
/**
 * Documentation topic: Appearance.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h3><?php esc_html_e( 'Dark mode', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Settings → Appearance → "Dark Mode" paints the Total Mail Queue screens dark: Off, On, or "Follow the operating system". The rest of the WordPress admin keeps its own colour scheme.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'When Free Form Certificate is active, its Dark Mode setting decides for both plugins, so their screens always match. The field here is then shown disabled with the note "Controlled by Free Form Certificate"; change the theme in Free Form Certificate\'s settings. What you chose here is kept, and applies again if Free Form Certificate is deactivated.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Buttons', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Every button carries an icon, and its colour says what it does: solid blue saves or completes the form, grey opens, tests or navigates, green adds something, amber resets or switches something off without losing data, and red deletes or replaces. Buttons that cannot be undone ask before they act.', 'total-mail-queue' ); ?></p>
