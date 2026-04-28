<?php
/**
 * Edit-form renderer for the Sources admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Sources\CoreTemplates;
use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use TotalMailQueue\Templates\TestEmailSender;

/**
 * Renders the per-row edit form for a Sources row: label/group overrides
 * and, for wp_core templates, the subject/body/skip_template_wrap fields
 * with the inline preview-test widget.
 *
 * Pulled out of {@see SourcesPage} so the long markup + the wp_core
 * template-override block live next to each other.
 */
final class SourcesEditRenderer {

	/**
	 * Render the inline edit form for a single source row.
	 *
	 * @param int $source_id Row id to edit.
	 */
	public static function render( int $source_id ): void {
		$row = SourcesRepository::findById( $source_id );
		if ( null === $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Source not found.', 'total-mail-queue' ) . '</p></div>';
			return;
		}

		$is_system  = SourcesRepository::isSystem( (string) $row['source_key'] );
		$cancel_url = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources' );

		echo '<h3>' . esc_html(
			sprintf(
				/* translators: %s: source key */
				__( 'Edit source: %s', 'total-mail-queue' ),
				(string) $row['source_key']
			)
		) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Leave a field empty to fall back to the translated default. The override only affects what you see in the admin — the source_key, detection and enforcement stay identical.', 'total-mail-queue' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources' ) ) . '">';
		wp_nonce_field( 'wp_tmq_source_edit_' . $source_id, 'wp_tmq_source_edit_nonce' );
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		echo '<input type="hidden" name="source_id" value="' . esc_attr( (string) $source_id ) . '" />';

		echo '<table class="form-table">';

		// Label row.
		echo '<tr><th scope="row"><label for="tmq-source-label">' . esc_html__( 'Label', 'total-mail-queue' ) . '</label></th><td>';
		echo '<input type="text" id="tmq-source-label" name="label_override" class="regular-text" value="' . esc_attr( (string) $row['label_override'] ) . '" maxlength="255" />';
		echo '<p class="description">';
		$default_label = KnownSources::translatedLabel( (string) $row['source_key'] );
		if ( null !== $default_label ) {
			echo esc_html(
				sprintf(
					/* translators: %s: default translated label */
					__( 'Default: %s', 'total-mail-queue' ),
					$default_label
				)
			);
		} elseif ( '' !== (string) $row['label'] ) {
			echo esc_html(
				sprintf(
					/* translators: %s: stored fallback label */
					__( 'Default (stored fallback): %s', 'total-mail-queue' ),
					(string) $row['label']
				)
			);
		} else {
			echo esc_html__( 'No translated default available — fill in a label below.', 'total-mail-queue' );
		}
		echo '</p></td></tr>';

		// Group row.
		echo '<tr><th scope="row"><label for="tmq-source-group">' . esc_html__( 'Group', 'total-mail-queue' ) . '</label></th><td>';
		if ( $is_system ) {
			echo '<input type="text" class="regular-text" value="' . esc_attr( (string) $row['group_label'] ) . '" disabled="disabled" />';
			echo '<p class="description">' . esc_html__( 'System sources keep their built-in group; the group cannot be overridden.', 'total-mail-queue' ) . '</p>';
		} else {
			echo '<input type="text" id="tmq-source-group" name="group_override" class="regular-text" value="' . esc_attr( (string) $row['group_override'] ) . '" maxlength="120" />';
			echo '<p class="description">';
			$default_group = KnownSources::translatedGroup( (string) $row['source_key'] );
			if ( null !== $default_group ) {
				echo esc_html(
					sprintf(
						/* translators: %s: default translated group label */
						__( 'Default: %s', 'total-mail-queue' ),
						$default_group
					)
				);
			} else {
				echo esc_html(
					sprintf(
						/* translators: %s: stored fallback group label */
						__( 'Default (stored fallback): %s', 'total-mail-queue' ),
						(string) $row['group_label']
					)
				);
			}
			echo '</p>';
		}
		echo '</td></tr>';

		echo '</table>';

		// wp_core template override section (v2.6.0). Only the 11 wp_core
		// templates we ship overrides for show this block — for plugin /
		// theme / unknown sources it's silently skipped.
		self::renderTemplateOverrideSection( $row );

		echo '<p class="submit">';
		echo '<input type="submit" name="wp_tmq_source_edit_save" class="button button-primary" value="' . esc_attr__( 'Save', 'total-mail-queue' ) . '" /> ';
		echo '<a href="' . esc_url( $cancel_url ) . '" class="button">' . esc_html__( 'Cancel', 'total-mail-queue' ) . '</a>';
		echo '</p>';
		echo '</form>';
	}

	/**
	 * Render the template override fields for a wp_core source. No-op when
	 * the source isn't one of the editable wp_core templates.
	 *
	 * @param array<string,mixed> $row Source row.
	 */
	private static function renderTemplateOverrideSection( array $row ): void {
		$source_key = (string) $row['source_key'];
		if ( ! CoreTemplates::isCoreTemplate( $source_key ) ) {
			return;
		}

		$defaults         = CoreTemplates::get( $source_key );
		$default_subject  = is_array( $defaults ) ? (string) $defaults['subject'] : '';
		$default_body     = is_array( $defaults ) ? (string) $defaults['body'] : '';
		$tokens           = CoreTemplates::tokensFor( $source_key );
		$subject_override = (string) ( $row['subject_override'] ?? '' );
		$body_override    = (string) ( $row['body_override'] ?? '' );
		$skip_wrap        = ! empty( $row['skip_template_wrap'] );

		echo '<h4>' . esc_html__( 'Template override (subject + body)', 'total-mail-queue' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Edit the subject and body sent for this WP-core email. Leave a field empty to keep the WordPress default.', 'total-mail-queue' );

		// Token list for this template.
		if ( ! empty( $tokens ) ) {
			echo ' ' . esc_html__( 'Available tokens:', 'total-mail-queue' ) . ' ';
			$rendered_tokens = array();
			foreach ( $tokens as $token ) {
				$rendered_tokens[] = '<code>{' . esc_html( $token ) . '}</code>';
			}
			echo wp_kses(
				implode( ' ', $rendered_tokens ),
				array( 'code' => array() )
			);
		}
		echo '</p>';

		echo '<table class="form-table"><tbody>';

		// Subject override.
		echo '<tr><th scope="row"><label for="tmq-template-subject">' . esc_html__( 'Subject', 'total-mail-queue' ) . '</label></th><td>';
		echo '<input type="text" id="tmq-template-subject" name="subject_override" class="large-text" value="' . esc_attr( $subject_override ) . '" maxlength="255" />';
		if ( '' !== $default_subject ) {
			echo '<p class="description">';
			echo esc_html(
				sprintf(
					/* translators: %s: WordPress default subject */
					__( 'WP default: %s', 'total-mail-queue' ),
					$default_subject
				)
			);
			echo '</p>';
		}
		echo '</td></tr>';

		// Body override.
		echo '<tr><th scope="row"><label for="tmq-template-body">' . esc_html__( 'Body', 'total-mail-queue' ) . '</label></th><td>';
		echo '<textarea id="tmq-template-body" name="body_override" rows="10" class="large-text code">' . esc_textarea( $body_override ) . '</textarea>';
		if ( '' !== $default_body ) {
			echo '<details><summary>' . esc_html__( 'Show WP default body', 'total-mail-queue' ) . '</summary>';
			echo '<pre style="white-space:pre-wrap; background:#f6f7f7; padding:10px; border-left:4px solid #c3c4c7;">' . esc_html( $default_body ) . '</pre>';
			echo '</details>';
		}
		echo '</td></tr>';

		// Skip template wrap.
		echo '<tr><th scope="row">' . esc_html__( 'Skip template wrapper', 'total-mail-queue' ) . '</th><td>';
		echo '<label><input type="checkbox" name="skip_template_wrap" value="1" ' . checked( $skip_wrap, true, false ) . ' /> ';
		echo esc_html__( 'Send this email raw (bypass the global HTML envelope from the Templates tab).', 'total-mail-queue' );
		echo '</label></td></tr>';

		// Reset row.
		if ( '' !== $subject_override || '' !== $body_override || $skip_wrap ) {
			$reset_url = wp_nonce_url(
				admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources&source-action=reset_template&source-id=' . (int) $row['id'] ),
				'wp_tmq_source_reset_template_' . (int) $row['id']
			);
			echo '<tr><th scope="row">' . esc_html__( 'Reset', 'total-mail-queue' ) . '</th><td>';
			echo '<a href="' . esc_url( $reset_url ) . '" class="button" onclick="return confirm(\'' . esc_js( __( 'Reset subject and body overrides for this template? The WP default will be used again.', 'total-mail-queue' ) ) . '\');">' . esc_html__( 'Reset to WP default', 'total-mail-queue' ) . '</a>';
			echo '</td></tr>';
		}

		// Send preview test row.
		$current_user      = wp_get_current_user();
		$default_recipient = $current_user instanceof \WP_User ? (string) $current_user->user_email : (string) get_option( 'admin_email' );
		echo '<tr><th scope="row">' . esc_html__( 'Send preview test', 'total-mail-queue' ) . '</th><td>';
		echo '<input type="email" id="tmq-template-test-to" value="' . esc_attr( $default_recipient ) . '" class="regular-text" /> ';
		echo '<button type="button" id="tmq-template-test-send" class="button" '
			. 'data-source-key="' . esc_attr( $source_key ) . '" '
			. 'data-nonce="' . esc_attr( wp_create_nonce( TestEmailSender::NONCE ) ) . '" '
			. 'data-action="' . esc_attr( TestEmailSender::ACTION ) . '">'
			. esc_html__( 'Send preview', 'total-mail-queue' )
			. '</button> ';
		echo '<span id="tmq-template-test-result" aria-live="polite"></span>';
		echo '<p class="description">' . esc_html__( 'Sends the current saved template (override OR WP default) to the address above. Save changes first if you want to preview unsaved edits.', 'total-mail-queue' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		// Inline JS for the preview button. Kept inline (not in a separate
		// asset) so the template-override section is self-contained.
		?>
<script>
( function () {
	'use strict';
	var btn = document.getElementById( 'tmq-template-test-send' );
	if ( ! btn ) {
		return;
	}
	btn.addEventListener( 'click', function () {
		var input  = document.getElementById( 'tmq-template-test-to' );
		var result = document.getElementById( 'tmq-template-test-result' );
		var to     = input ? input.value : '';
		var data   = new FormData();
		data.append( 'action', btn.dataset.action );
		data.append( '_nonce', btn.dataset.nonce );
		data.append( 'source_key', btn.dataset.sourceKey );
		data.append( 'to', to );
		btn.disabled = true;
		result.textContent = '…';
		fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				result.textContent = ( json && json.data && json.data.message ) ? json.data.message : '';
			} )
			.catch( function () {
				result.textContent = '<?php echo esc_js( __( 'Request failed.', 'total-mail-queue' ) ); ?>';
			} )
			.finally( function () { btn.disabled = false; } );
	} );
} )();
</script>
		<?php
	}
}
