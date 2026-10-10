<?php
/**
 * The Documentation tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Renders the in-plugin documentation: an index card, then one card per
 * topic, in the order {@see Documentation::topics()} lists them.
 *
 * Each topic is a partial under `src/admin/docs/`, so adding or moving one is
 * a one-line edit here. `DocumentationTest` checks that the list and the files
 * agree.
 */
final class Documentation {

	/**
	 * Id of the index card, the target of every "Back to the index" link.
	 */
	public const INDEX_ANCHOR = 'tmq-docs-index';

	/**
	 * Id of the Troubleshooting topic, where the plugin's own notices and the
	 * old FAQ links point.
	 */
	public const TROUBLESHOOTING_ANCHOR = 'troubleshooting';

	/**
	 * Every topic, in reading order.
	 *
	 * @return list<array{anchor:string,icon:string,title:string,file:string}>
	 */
	public static function topics(): array {
		return array(
			array(
				'anchor' => 'getting-started',
				'icon'   => 'send',
				'title'  => __( 'Getting started', 'total-mail-queue' ),
				'file'   => 'getting-started.php',
			),
			array(
				'anchor' => 'modes-and-queue',
				'icon'   => 'cron',
				'title'  => __( 'Modes and the queue', 'total-mail-queue' ),
				'file'   => 'modes-and-queue.php',
			),
			array(
				'anchor' => 'log-and-retention',
				'icon'   => 'log',
				'title'  => __( 'Log and retention', 'total-mail-queue' ),
				'file'   => 'log-and-retention.php',
			),
			array(
				'anchor' => 'smtp-accounts',
				'icon'   => 'smtp',
				'title'  => __( 'SMTP accounts', 'total-mail-queue' ),
				'file'   => 'smtp-accounts.php',
			),
			array(
				'anchor' => 'sources',
				'icon'   => 'sources',
				'title'  => __( 'Sources', 'total-mail-queue' ),
				'file'   => 'sources.php',
			),
			array(
				'anchor' => 'html-templates',
				'icon'   => 'templates',
				'title'  => __( 'HTML templates', 'total-mail-queue' ),
				'file'   => 'html-templates.php',
			),
			array(
				'anchor' => 'alerts',
				'icon'   => 'alert',
				'title'  => __( 'Alerts', 'total-mail-queue' ),
				'file'   => 'alerts.php',
			),
			array(
				'anchor' => 'export-import',
				'icon'   => 'export',
				'title'  => __( 'Export and import', 'total-mail-queue' ),
				'file'   => 'export-import.php',
			),
			array(
				'anchor' => 'developers',
				'icon'   => 'advanced',
				'title'  => __( 'For developers', 'total-mail-queue' ),
				'file'   => 'developers.php',
			),
			array(
				'anchor' => 'updates-and-data',
				'icon'   => 'retention',
				'title'  => __( 'Updates, stored data and uninstall', 'total-mail-queue' ),
				'file'   => 'updates-and-data.php',
			),
			array(
				'anchor' => self::TROUBLESHOOTING_ANCHOR,
				'icon'   => 'faq',
				'title'  => __( 'Troubleshooting', 'total-mail-queue' ),
				'file'   => 'troubleshooting.php',
			),
		);
	}

	/**
	 * Print the tab: banner, index, then every topic.
	 *
	 * @param string $banner_url URL of the hero image shown above the index.
	 */
	public static function render( string $banner_url ): void {
		echo '<div class="tmq-banner"><img src="' . esc_url( $banner_url ) . '" alt="" height="300" loading="lazy" /></div>';

		echo '<div class="tmq-box tmq-docs-index" id="' . esc_attr( self::INDEX_ANCHOR ) . '">';
		echo Icons::heading( 'docs', __( 'Documentation', 'total-mail-queue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
		echo '<ol class="tmq-docs-index__list">';
		foreach ( self::topics() as $topic ) {
			echo '<li><a href="#' . esc_attr( $topic['anchor'] ) . '">' . Icons::tag( $topic['icon'] ) . esc_html( $topic['title'] ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::tag() is fixed markup.
		}
		echo '</ol>';
		echo '</div>';

		foreach ( self::topics() as $topic ) {
			echo '<section class="tmq-box tmq-docs-topic" id="' . esc_attr( $topic['anchor'] ) . '">';
			echo Icons::heading( $topic['icon'], $topic['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
			require __DIR__ . '/docs/' . $topic['file'];
			echo '<p class="tmq-docs-back"><a href="#' . esc_attr( self::INDEX_ANCHOR ) . '">' . esc_html__( 'Back to the index', 'total-mail-queue' ) . '</a></p>';
			echo '</section>';
		}
	}

	/**
	 * A link to one of the plugin's tabs, for use inside a topic.
	 *
	 * @param string $tab   A PluginPage::TAB_* slug.
	 * @param string $label Link text, already translated.
	 */
	public static function tabLink( string $tab, string $label ): string {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=' . $tab ) ) . '">' . esc_html( $label ) . '</a>';
	}
}
