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
	 * The topics, grouped and ordered like the plugin's tabs, so the
	 * documentation reads in the order the menu does.
	 *
	 * Each group names the tabs it documents (`tabs`); its title is the tab's
	 * own label, read from {@see PluginPage::tabs()}, so a renamed tab renames
	 * its group. A group with no tab of its own opens or closes the list.
	 * `DocumentationTest` holds the order to the menu's.
	 *
	 * @return list<array{tabs:list<string>,icon:string,title:string,topics:list<array{anchor:string,icon:string,title:string,file:string}>}>
	 */
	public static function groups(): array {
		return array(
			array(
				'tabs'   => array(),
				'icon'   => 'send',
				'title'  => __( 'Getting started', 'total-mail-queue' ),
				'topics' => array(
					array(
						'anchor' => 'getting-started',
						'icon'   => 'send',
						'title'  => __( 'Getting started', 'total-mail-queue' ),
						'file'   => 'getting-started.php',
					),
				),
			),
			array(
				'tabs'   => array( PluginPage::TAB_SETTINGS ),
				'icon'   => 'settings',
				'title'  => self::tabsTitle( array( PluginPage::TAB_SETTINGS ) ),
				'topics' => array(
					array(
						'anchor' => 'modes-and-queue',
						'icon'   => 'cron',
						'title'  => __( 'Modes and the queue', 'total-mail-queue' ),
						'file'   => 'modes-and-queue.php',
					),
					array(
						'anchor' => 'alerts',
						'icon'   => 'alert',
						'title'  => __( 'Alerts', 'total-mail-queue' ),
						'file'   => 'alerts.php',
					),
					array(
						'anchor' => 'access',
						'icon'   => 'access',
						'title'  => __( 'Access by role', 'total-mail-queue' ),
						'file'   => 'access.php',
					),
					array(
						'anchor' => 'export-import',
						'icon'   => 'export',
						'title'  => __( 'Export and import', 'total-mail-queue' ),
						'file'   => 'export-import.php',
					),
				),
			),
			array(
				'tabs'   => array( PluginPage::TAB_LOG, PluginPage::TAB_QUEUE ),
				'icon'   => 'log',
				'title'  => self::tabsTitle( array( PluginPage::TAB_LOG, PluginPage::TAB_QUEUE ) ),
				'topics' => array(
					array(
						'anchor' => 'log-and-retention',
						'icon'   => 'log',
						'title'  => __( 'Log and retention', 'total-mail-queue' ),
						'file'   => 'log-and-retention.php',
					),
				),
			),
			array(
				'tabs'   => array( PluginPage::TAB_SMTP ),
				'icon'   => 'smtp',
				'title'  => self::tabsTitle( array( PluginPage::TAB_SMTP ) ),
				'topics' => array(
					array(
						'anchor' => 'smtp-accounts',
						'icon'   => 'smtp',
						'title'  => __( 'SMTP accounts', 'total-mail-queue' ),
						'file'   => 'smtp-accounts.php',
					),
				),
			),
			array(
				'tabs'   => array( PluginPage::TAB_SOURCES ),
				'icon'   => 'sources',
				'title'  => self::tabsTitle( array( PluginPage::TAB_SOURCES ) ),
				'topics' => array(
					array(
						'anchor' => 'sources',
						'icon'   => 'sources',
						'title'  => __( 'Sources', 'total-mail-queue' ),
						'file'   => 'sources.php',
					),
				),
			),
			array(
				'tabs'   => array( PluginPage::TAB_TEMPLATES ),
				'icon'   => 'templates',
				'title'  => self::tabsTitle( array( PluginPage::TAB_TEMPLATES ) ),
				'topics' => array(
					array(
						'anchor' => 'html-templates',
						'icon'   => 'templates',
						'title'  => __( 'HTML templates', 'total-mail-queue' ),
						'file'   => 'html-templates.php',
					),
				),
			),
			array(
				'tabs'   => array( PluginPage::TAB_CRON_INFO ),
				'icon'   => 'cron',
				'title'  => self::tabsTitle( array( PluginPage::TAB_CRON_INFO ) ),
				'topics' => array(
					array(
						'anchor' => 'cron',
						'icon'   => 'cron',
						'title'  => __( 'Cron', 'total-mail-queue' ),
						'file'   => 'cron.php',
					),
				),
			),
			array(
				'tabs'   => array(),
				'icon'   => 'faq',
				'title'  => __( 'Reference and help', 'total-mail-queue' ),
				'topics' => array(
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
				),
			),
		);
	}

	/**
	 * Every topic, in reading order: the groups, flattened.
	 *
	 * @return list<array{anchor:string,icon:string,title:string,file:string}>
	 */
	public static function topics(): array {
		$topics = array();
		foreach ( self::groups() as $group ) {
			foreach ( $group['topics'] as $topic ) {
				$topics[] = $topic;
			}
		}
		return $topics;
	}

	/**
	 * The menu labels of one or more tabs, joined: the title of the group that
	 * documents them, read from {@see PluginPage::tabs()}.
	 *
	 * @param string[] $tabs PluginPage::TAB_* slugs.
	 */
	private static function tabsTitle( array $tabs ): string {
		$labels = PluginPage::tabs();
		$names  = array();
		foreach ( $tabs as $tab ) {
			if ( isset( $labels[ $tab ] ) ) {
				$names[] = $labels[ $tab ];
			}
		}
		/* translators: separator between two tab names in a documentation group title */
		return implode( __( ' and ', 'total-mail-queue' ), $names );
	}

	/**
	 * Print the tab: banner, index, then every topic.
	 *
	 * @param string $banner_url  URL of the hero image shown above the index.
	 * @param string $plugin_file The plugin's main file, read for the credits line.
	 */
	public static function render( string $banner_url, string $plugin_file ): void {
		echo '<div class="tmq-banner"><img src="' . esc_url( $banner_url ) . '" alt="" height="300" loading="lazy" /></div>';

		echo '<div class="tmq-box tmq-docs-intro">';
		echo Icons::heading( 'docs', __( 'Documentation', 'total-mail-queue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
		echo '<p>' . esc_html__( 'How to set up and run Total Mail Queue, one topic per card. Search or pick a topic below; the navigation stays at the top while you read.', 'total-mail-queue' ) . '</p>';
		echo self::credits( $plugin_file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- credits() escapes every part it prints.
		echo '</div>';

		// Quick navigation: stays in view while the topics scroll under it,
		// and the search box filters both its links and the topic cards.
		echo '<nav class="tmq-box tmq-docs-index" id="' . esc_attr( self::INDEX_ANCHOR ) . '" aria-label="' . esc_attr__( 'Quick navigation', 'total-mail-queue' ) . '">';
		echo Icons::heading( 'search', __( 'Quick navigation', 'total-mail-queue' ), 'h3' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
		echo '<p class="tmq-docs-search"><input type="search" id="tmq-docs-search" class="regular-text" placeholder="' . esc_attr__( 'Search the documentation…', 'total-mail-queue' ) . '" aria-label="' . esc_attr__( 'Search the documentation', 'total-mail-queue' ) . '" /></p>';
		// One row per group, in the menu's order, in a single column like the
		// Free Form Certificate documentation's tree. A group with one topic is
		// a link; a longer one is a branch that opens to list its topics.
		echo '<ul class="tmq-docs-index__groups">';
		foreach ( self::groups() as $group ) {
			$title = Icons::tag( $group['icon'] ) . esc_html( $group['title'] );
			echo '<li class="tmq-docs-index__group">';
			if ( 1 === count( $group['topics'] ) ) {
				echo '<a class="tmq-docs-index__tile" href="#' . esc_attr( $group['topics'][0]['anchor'] ) . '" data-topic="' . esc_attr( $group['topics'][0]['anchor'] ) . '">' . $title . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $title is Icons::tag() and an escaped label.
			} else {
				echo '<details><summary class="tmq-docs-index__tile">' . Icons::tag( 'chevron' ) . $title . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $title is Icons::tag() and an escaped label.
				echo '<ul class="tmq-docs-index__list">';
				foreach ( $group['topics'] as $topic ) {
					echo '<li><a href="#' . esc_attr( $topic['anchor'] ) . '" data-topic="' . esc_attr( $topic['anchor'] ) . '">' . Icons::tag( $topic['icon'] ) . esc_html( $topic['title'] ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::tag() is fixed markup.
				}
				echo '</ul></details>';
			}
			echo '</li>';
		}
		echo '</ul>';
		echo '<p class="tmq-docs-search-empty" hidden>' . esc_html__( 'No topic matches this search.', 'total-mail-queue' ) . '</p>';
		echo '</nav>';

		foreach ( self::topics() as $topic ) {
			echo '<section class="tmq-box tmq-docs-topic" id="' . esc_attr( $topic['anchor'] ) . '">';
			echo Icons::heading( $topic['icon'], $topic['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
			require __DIR__ . '/docs/' . $topic['file'];
			echo '<p class="tmq-docs-back"><a href="#' . esc_attr( self::INDEX_ANCHOR ) . '">' . esc_html__( 'Back to the index', 'total-mail-queue' ) . '</a></p>';
			echo '</section>';
		}
	}

	/**
	 * Who develops the plugin and where its code lives, read from the plugin
	 * header: the same lines WordPress shows on the Plugins screen, so the two
	 * cannot disagree.
	 *
	 * @param string $plugin_file The plugin's main file.
	 */
	public static function credits( string $plugin_file ): string {
		$meta = get_file_data(
			$plugin_file,
			array(
				'author'     => 'Author',
				'author_uri' => 'Author URI',
				'plugin_uri' => 'Plugin URI',
			)
		);

		$author = '<a href="' . esc_url( $meta['author_uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $meta['author'] ) . '</a>';
		$repo   = '<a href="' . esc_url( $meta['plugin_uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) preg_replace( '#^https?://#', '', $meta['plugin_uri'] ) ) . '</a>';

		return '<p class="tmq-docs-credits">'
			/* translators: %s: plugin author's name, linked to the author's page */
			. sprintf( esc_html__( 'Developed by %s.', 'total-mail-queue' ), $author ) . ' '
			/* translators: %s: link to the plugin's source repository */
			. sprintf( esc_html__( 'Source code and issue tracker: %s', 'total-mail-queue' ), $repo )
			. '</p>';
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
