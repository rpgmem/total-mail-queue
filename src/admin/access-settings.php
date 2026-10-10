<?php
/**
 * The Access card of the Settings tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * One row per role with two switches, "Can view" and "Can manage", that
 * save on flip. Shown to administrators only, who always have full access
 * and are therefore not listed.
 */
final class AccessSettings {

	/**
	 * Print the card.
	 */
	public static function render(): void {
		echo '<div class="tmq-box tmq-settings-card tmq-access-card">';
		echo Icons::heading( 'access', __( 'Access', 'total-mail-queue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
		echo '<p class="description">' . esc_html__( 'Administrators always have full access. Other roles see the plugin only when allowed here. View opens the Log, Retention, Sources and the documentation, read-only. Manage also changes settings, sources and templates, runs bulk actions and shows message bodies. SMTP accounts, Export / Import and this card stay with administrators, and so do the bodies of the emails that carry a one-time account link, such as a password reset.', 'total-mail-queue' ) . '</p>';

		$roles = Access::grantableRoles();
		if ( empty( $roles ) ) {
			echo '<p>' . esc_html__( 'There are no other roles on this site.', 'total-mail-queue' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped tmq-access-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Role', 'total-mail-queue' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Can view', 'total-mail-queue' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Can manage', 'total-mail-queue' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $roles as $slug => $name ) {
			echo '<tr><th scope="row">' . esc_html( $name ) . '</th>';
			foreach ( array(
				'view'   => Access::VIEW,
				'manage' => Access::MANAGE,
			) as $level => $capability ) {
				echo '<td>';
				Toggle::render(
					array(
						'name'     => 'tmq_access[' . $slug . '][' . $level . ']',
						'id'       => 'tmq-access-' . $slug . '-' . $level,
						'checked'  => Access::roleHas( $slug, $capability ),
						'label'    => 'view' === $level ? __( 'View', 'total-mail-queue' ) : __( 'Manage', 'total-mail-queue' ),
						'autosave' => 'access:' . $slug . ':' . $level,
					)
				);
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}
