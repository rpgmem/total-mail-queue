<?php
/**
 * Documentation topic: Access by role.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'Administrators always see and change everything. Settings → Access, which only administrators see, lets other roles in: switch on View or Manage for a role and it takes effect at once.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'View opens the Log, Retention, Sources and this documentation, read-only: no bulk actions, no changes to sources and no message bodies. Manage adds the Settings and Templates tabs, bulk actions, source changes and the message bodies, and includes View.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'SMTP accounts, Export / Import and the Access card stay with administrators. So do the bodies of the emails that carry a one-time account link (password reset, a new user\'s set-password email, email change, admin email change and privacy request confirmation): anyone who read them could sign in as the recipient. A manager still sees those rows, with a note where the body would be.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'The permissions are stored on the roles themselves, as the capabilities tmq_view_mail_queue and tmq_manage_mail_queue, so a role editor plugin shows them too. Deleting the plugin removes them.', 'total-mail-queue' ); ?></p>
