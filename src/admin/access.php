<?php
/**
 * Who may view and who may manage the plugin.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * The plugin's two capabilities and every decision built on them.
 *
 * - {@see self::VIEW} opens the Log, Retention, Sources, Cron and
 *   the documentation, read-only.
 * - {@see self::MANAGE} also changes settings, sources and templates, runs
 *   bulk actions and reads message bodies. It includes VIEW.
 * - SMTP accounts, Export/Import, the connection test and the Access card
 *   stay with administrators (`manage_options`), who always hold both
 *   capabilities without anything being written to their role.
 *
 * Other roles receive the capabilities on the role itself, the WordPress way,
 * so role-editor plugins show and change them too.
 *
 * Five core emails carry a one-time link that signs in or changes an
 * account. Their bodies are administrator-only: a manager who read them could
 * take over the recipient's account, an administrator's included.
 */
final class Access {

	/**
	 * Read-only access to the plugin's screens.
	 */
	public const VIEW = 'tmq_view_mail_queue';

	/**
	 * Change settings, sources and templates; bulk actions; message bodies.
	 */
	public const MANAGE = 'tmq_manage_mail_queue';

	/**
	 * Sources whose message body only administrators may read.
	 */
	public const ADMIN_ONLY_BODIES = array(
		'wp_core:password_reset',
		'wp_core:new_user',
		'wp_core:email_change',
		'wp_core:admin_email_change_confirm',
		'wp_core:user_action_confirm',
	);

	/**
	 * Hook the capability mapping.
	 */
	public static function register(): void {
		add_filter( 'user_has_cap', array( self::class, 'grant' ), 10, 1 );
		// The Settings form posts to options.php, which asks for
		// manage_options unless the option group says otherwise.
		add_filter( 'option_page_capability_' . SettingsApi::GROUP, static fn (): string => self::MANAGE );
	}

	/**
	 * `user_has_cap` filter: administrators hold both capabilities, and
	 * MANAGE implies VIEW.
	 *
	 * @param array<string,bool> $allcaps The user's capabilities.
	 * @return array<string,bool>
	 */
	public static function grant( $allcaps ): array {
		$allcaps = (array) $allcaps;
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::MANAGE ] = true;
		}
		if ( ! empty( $allcaps[ self::MANAGE ] ) ) {
			$allcaps[ self::VIEW ] = true;
		}
		return $allcaps;
	}

	/**
	 * Whether the current user may open the plugin.
	 */
	public static function canView(): bool {
		return current_user_can( self::VIEW );
	}

	/**
	 * Whether the current user may change things in the plugin.
	 */
	public static function canManage(): bool {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Whether the current user is an administrator.
	 */
	public static function isAdmin(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may read the body of a message from a source.
	 *
	 * @param string $source_key The row's `source_key`.
	 */
	public static function canReadBody( string $source_key ): bool {
		if ( self::isAdmin() ) {
			return true;
		}
		return self::canManage() && ! in_array( $source_key, self::ADMIN_ONLY_BODIES, true );
	}

	/**
	 * The capability a tab needs.
	 *
	 * @param string $tab A {@see PluginPage} tab slug.
	 */
	public static function tabCapability( string $tab ): string {
		switch ( $tab ) {
			case PluginPage::TAB_SMTP:
				return 'manage_options';
			case PluginPage::TAB_SETTINGS:
			case PluginPage::TAB_TEMPLATES:
				return self::MANAGE;
			default:
				return self::VIEW;
		}
	}

	/**
	 * Roles an administrator can grant access to: every role that is not
	 * already an administrator, keyed by slug, with its translated name.
	 *
	 * @return array<string,string>
	 */
	public static function grantableRoles(): array {
		$roles = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( ! empty( $role['capabilities']['manage_options'] ) ) {
				continue;
			}
			$roles[ (string) $slug ] = translate_user_role( (string) $role['name'] );
		}
		return $roles;
	}

	/**
	 * Whether a role holds a capability on the role itself.
	 *
	 * @param string $role       Role slug.
	 * @param string $capability {@see self::VIEW} or {@see self::MANAGE}.
	 */
	public static function roleHas( string $role, string $capability ): bool {
		$object = get_role( $role );
		return null !== $object && $object->has_cap( $capability );
	}

	/**
	 * Grant or revoke a capability on a role. Administrator roles are left
	 * alone: they already hold everything.
	 *
	 * @param string $role       Role slug.
	 * @param string $capability {@see self::VIEW} or {@see self::MANAGE}.
	 * @param bool   $granted    True to grant, false to revoke.
	 * @return bool False when the role does not exist or cannot be changed.
	 */
	public static function setRole( string $role, string $capability, bool $granted ): bool {
		if ( ! in_array( $capability, array( self::VIEW, self::MANAGE ), true ) || ! isset( self::grantableRoles()[ $role ] ) ) {
			return false;
		}
		$object = get_role( $role );
		if ( null === $object ) {
			return false;
		}
		if ( $granted ) {
			$object->add_cap( $capability );
		} else {
			$object->remove_cap( $capability );
		}
		return true;
	}

	/**
	 * Remove both capabilities from every role. Called on uninstall.
	 */
	public static function revokeAll(): void {
		foreach ( array_keys( wp_roles()->roles ) as $slug ) {
			$object = get_role( (string) $slug );
			if ( null !== $object ) {
				$object->remove_cap( self::VIEW );
				$object->remove_cap( self::MANAGE );
			}
		}
	}
}
