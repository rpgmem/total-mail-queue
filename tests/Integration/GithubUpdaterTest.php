<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Update\GithubUpdater;

/**
 * @covers \TotalMailQueue\Update\GithubUpdater
 */
final class GithubUpdaterTest extends IntegrationTestCase {

	private const PLUGIN = 'total-mail-queue/total-mail-queue.php';
	private const ZIP    = 'https://github.com/rpgmem/total-mail-queue/releases/download/v2.10.0/total-mail-queue-2.10.0.zip';

	/** @var array<string, mixed> Site transients written during the test. */
	private array $transients = array();

	/** @var list<string> URLs requested through wp_remote_get. */
	private array $requested = array();

	/** @var array<string, array{code: int, body: string, etag?: string}|null> Response per URL fragment; null = WP_Error. */
	private array $responses = array();

	protected function setUp(): void {
		parent::setUp();

		$this->transients = array();
		$this->requested  = array();
		$this->responses  = array(
			'api.github.com' => array(
				'code' => 200,
				'body' => (string) json_encode( $this->release() ),
				'etag' => '"abc"',
			),
			'raw.githubusercontent.com' => array(
				'code' => 200,
				'body' => "=== Total Mail Queue ===\nRequires at least: 6.0\nTested up to: 6.9\nRequires PHP: 8.1\n\n== Upgrade Notice ==\n\n= 2.10.0 =\nRequires PHP 8.1.\n\nSee CHANGELOG.md.\n",
			),
		);

		Functions\when( 'plugin_basename' )->justReturn( self::PLUGIN );
		Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.org/wp-content/plugins/total-mail-queue/' );
		Functions\when( 'get_site_transient' )->alias( fn ( $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'set_site_transient' )->alias(
			function ( $key, $value ) {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_site_transient' )->alias(
			function ( $key ) {
				unset( $this->transients[ $key ] );
				return true;
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( $v ) => $v instanceof \WP_Error );
		Functions\when( 'wp_remote_get' )->alias(
			function ( $url ) {
				$this->requested[] = $url;
				foreach ( $this->responses as $fragment => $response ) {
					if ( false !== strpos( $url, $fragment ) ) {
						return null === $response ? new \WP_Error( 'http', 'down' ) : $response;
					}
				}
				return new \WP_Error( 'http', 'unexpected' );
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn ( $r ) => $r['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn ( $r ) => $r['body'] );
		Functions\when( 'wp_remote_retrieve_header' )->alias( static fn ( $r, $h ) => $r[ $h ] ?? '' );
		Functions\when( 'get_file_data' )->alias(
			static function ( $file, $headers ) {
				$installed = array(
					'requires'     => '5.9',
					'requires_php' => '7.4',
					'tested'       => '6.8',
				);
				return array_intersect_key( $installed, $headers );
			}
		);
		Functions\when( 'wpautop' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();

		unset( $_GET['force-check'] );
	}

	protected function tearDown(): void {
		unset( $_GET['force-check'] );
		parent::tearDown();
	}

	/**
	 * A `releases/latest` payload carrying the built asset and its digest.
	 *
	 * @return array<string, mixed>
	 */
	private function release( string $tag = 'v2.10.0', string $digest = 'sha256:ABCDEF' ): array {
		return array(
			'tag_name'     => $tag,
			'html_url'     => 'https://github.com/rpgmem/total-mail-queue/releases/tag/' . $tag,
			'body'         => 'Release notes',
			'published_at' => '2026-10-10T00:00:00Z',
			'assets'       => array(
				array(
					'name'                 => 'source.zip',
					'browser_download_url' => 'https://example.test/source.zip',
				),
				array(
					'name'                 => 'total-mail-queue-2.10.0.zip',
					'browser_download_url' => self::ZIP,
					'digest'               => $digest,
				),
			),
		);
	}

	private function transient( string $installed ): \stdClass {
		$t          = new \stdClass();
		$t->checked = array( self::PLUGIN => $installed );
		return $t;
	}

	public function test_an_older_install_is_offered_the_release_with_its_own_compat_fields(): void {
		$t = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		$offer = $t->response[ self::PLUGIN ];
		self::assertSame( '2.10.0', $offer->new_version );
		self::assertSame( self::ZIP, $offer->package );
		self::assertSame( 'total-mail-queue', $offer->slug );
		// The offered release's readme wins over the installed headers.
		self::assertSame( '6.0', $offer->requires );
		self::assertSame( '8.1', $offer->requires_php );
		self::assertSame( '6.9', $offer->tested );
		self::assertSame( 'Requires PHP 8.1.', $offer->upgrade_notice, 'The notice stops at the first blank line.' );
		self::assertArrayNotHasKey( self::PLUGIN, $t->no_update );
	}

	public function test_a_current_install_lands_in_no_update_so_the_auto_update_toggle_shows(): void {
		$t = GithubUpdater::checkForUpdate( $this->transient( '2.10.0' ) );

		self::assertArrayNotHasKey( self::PLUGIN, $t->response );
		self::assertSame( '2.10.0', $t->no_update[ self::PLUGIN ]->new_version );
	}

	public function test_a_write_without_checked_is_left_alone_and_nothing_is_fetched(): void {
		$t = new \stdClass();

		self::assertSame( $t, GithubUpdater::checkForUpdate( $t ) );
		self::assertObjectNotHasProperty( 'response', $t );
		self::assertSame( array(), $this->requested );
	}

	public function test_the_filter_switches_the_updater_off(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn ( $tag, $value ) => 'wp_tmq_github_updater_enabled' === $tag ? false : $value
		);

		$t = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		self::assertObjectNotHasProperty( 'response', $t );
		self::assertSame( array(), $this->requested );
	}

	public function test_a_release_without_the_built_asset_offers_nothing(): void {
		$release           = $this->release();
		$release['assets'] = array( $release['assets'][0] );
		$this->responses['api.github.com']['body'] = (string) json_encode( $release );

		$t = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		self::assertSame( array(), $t->response ?? array() );
	}

	public function test_an_unreachable_github_never_breaks_the_scan(): void {
		$this->responses['api.github.com'] = null;

		$t = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		self::assertObjectNotHasProperty( 'response', $t );
	}

	public function test_a_fresh_cache_answers_without_a_request(): void {
		GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );
		$this->requested = array();

		$t = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		self::assertSame( array(), $this->requested );
		self::assertSame( '2.10.0', $t->response[ self::PLUGIN ]->new_version );
	}

	public function test_check_again_drops_the_cache_and_asks_github(): void {
		GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );
		$this->requested    = array();
		$_GET['force-check'] = '1';

		GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		self::assertStringContainsString( 'api.github.com', $this->requested[0] );
	}

	public function test_a_304_keeps_the_payload_and_sends_the_etag(): void {
		GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );
		$this->transients[ GithubUpdater::CACHE_KEY ]['fetched_at'] = time() - 50000;
		$this->responses['api.github.com'] = array(
			'code' => 304,
			'body' => '',
		);
		$sent = array();
		Functions\when( 'wp_remote_get' )->alias(
			function ( $url, $args ) use ( &$sent ) {
				$sent = $args['headers'];
				return $this->responses['api.github.com'];
			}
		);

		$t = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );

		self::assertSame( '"abc"', $sent['If-None-Match'] );
		self::assertSame( '2.10.0', $t->response[ self::PLUGIN ]->new_version );
		self::assertGreaterThan( time() - 5, $this->transients[ GithubUpdater::CACHE_KEY ]['fetched_at'] );
	}

	public function test_an_unreadable_release_readme_falls_back_to_the_installed_files(): void {
		$this->responses['raw.githubusercontent.com'] = array(
			'code' => 404,
			'body' => '',
		);

		$offer = GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) )->response[ self::PLUGIN ];

		self::assertSame( '5.9', $offer->requires );
		self::assertSame( '7.4', $offer->requires_php );
		self::assertSame( '6.8', $offer->tested );
		self::assertSame( '', $offer->upgrade_notice );
	}

	public function test_readme_metadata_drops_what_is_not_a_version(): void {
		$meta = GithubUpdater::readmeMetadata( "Requires at least: 6.0 <script>\nTested up to: 6.9\nRequires PHP: 8.1\n" );

		self::assertSame( '', $meta['requires'] );
		self::assertSame( '6.9', $meta['tested'] );
		self::assertSame( '8.1', $meta['requires_php'] );
	}

	public function test_parse_release_picks_our_asset_and_its_digest(): void {
		$parsed = GithubUpdater::parseRelease( $this->release() );

		self::assertSame( '2.10.0', $parsed['version'] );
		self::assertSame( self::ZIP, $parsed['package'] );
		self::assertSame( 'ABCDEF', $parsed['sha256'] );
	}

	public function test_the_update_and_the_details_modal_carry_the_installed_icon(): void {
		$base     = 'https://example.org/wp-content/plugins/total-mail-queue/' . 'assets/img/brand/';
		$expected = array(
			'1x'  => $base . 'icon-128.png',
			'2x'  => $base . 'icon-256.png',
			'svg' => $base . 'icon.svg',
		);

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );
		self::assertSame( $expected, $info->icons );

		$older = GithubUpdater::checkForUpdate( $this->transient( '0.0.1' ) );
		self::assertSame( $expected, current( $older->response )->icons );
	}

	public function test_the_details_modal_has_the_banner_and_the_readme_tabs_in_order(): void {
		$this->responses['raw.githubusercontent.com']['body'] = "=== Total Mail Queue ===\nRequires PHP: 8.1\n\n== Description ==\n\nQueues **every** email.\n\n== Frequently Asked Questions ==\n\n= Does it send? =\n\nYes.\n\n== Screenshots ==\n\nThe images live in the repository.\n\n1. The log\n2. Retention\n\n== Installation ==\n\nActivate it.\n\n== Privacy ==\n\nNothing leaves.\n\n== Changelog ==\n\nSee CHANGELOG.md.\n";
		$release         = $this->release();
		$release['body'] = "### Changed\n\n- **The licence** moved (#73).";
		$this->responses['api.github.com']['body'] = (string) json_encode( $release );

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );

		$base = 'https://example.org/wp-content/plugins/total-mail-queue/assets/img/brand/';
		self::assertSame(
			array(
				'low'  => $base . 'banner-772x250.png',
				'high' => $base . 'banner-1544x500.png',
			),
			$info->banners
		);
		self::assertSame( array( 'description', 'installation', 'faq', 'changelog', 'screenshots', 'other_notes' ), array_keys( $info->sections ) );
		self::assertSame( '<p>Queues <strong>every</strong> email.</p>', $info->sections['description'] );
		self::assertSame( '<h4>Does it send?</h4><p>Yes.</p>', $info->sections['faq'] );
		self::assertSame( '<h3>Privacy</h3><p>Nothing leaves.</p>', $info->sections['other_notes'] );
		self::assertStringContainsString( '<h4>Changed</h4><ul><li><strong>The licence</strong> moved (<a href="https://github.com/rpgmem/total-mail-queue/issues/73">#73</a>).</li></ul>', $info->sections['changelog'] );
		self::assertStringContainsString( 'https://raw.githubusercontent.com/rpgmem/total-mail-queue/v2.10.0/docs/screenshots/01-log.png', $info->sections['screenshots'] );
		self::assertStringNotContainsString( 'live in the repository', $info->sections['screenshots'], 'Prose before the caption list is not a caption.' );
	}

	public function test_a_release_readme_that_cannot_be_fetched_falls_back_to_the_installed_one(): void {
		$this->responses['raw.githubusercontent.com'] = null;

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );

		self::assertArrayHasKey( 'description', $info->sections );
		self::assertArrayHasKey( 'screenshots', $info->sections );
	}

	public function test_plugin_info_answers_only_for_our_slug(): void {
		$other = (object) array( 'slug' => 'akismet' );
		self::assertFalse( GithubUpdater::pluginInfo( false, 'plugin_information', $other ) );
		self::assertFalse( GithubUpdater::pluginInfo( false, 'query_plugins', (object) array( 'slug' => 'total-mail-queue' ) ) );

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => 'total-mail-queue' ) );

		self::assertSame( 'Total Mail Queue', $info->name );
		self::assertSame( '2.10.0', $info->version );
		self::assertSame( self::ZIP, $info->download_link );
		self::assertSame( '8.1', $info->requires_php );
	}

	/**
	 * Write a temp file to stand in for the downloaded package.
	 */
	private function downloaded( string $contents ): string {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'tmq' );
		file_put_contents( $tmp, $contents );
		Functions\when( 'download_url' )->justReturn( $tmp );
		return $tmp;
	}

	public function test_a_package_matching_its_digest_is_handed_to_the_installer(): void {
		$tmp = $this->downloaded( 'zip-bytes' );
		$this->responses['api.github.com']['body'] = (string) json_encode( $this->release( 'v2.10.0', 'sha256:' . hash( 'sha256', 'zip-bytes' ) ) );

		self::assertSame( $tmp, GithubUpdater::verifyDownload( false, self::ZIP ) );
		unlink( $tmp );
	}

	public function test_a_tampered_package_aborts_the_install_and_is_deleted(): void {
		$tmp     = $this->downloaded( 'tampered' );
		$deleted = array();
		Functions\when( 'wp_delete_file' )->alias(
			static function ( $file ) use ( &$deleted ) {
				$deleted[] = $file;
			}
		);
		$this->responses['api.github.com']['body'] = (string) json_encode( $this->release( 'v2.10.0', 'sha256:' . hash( 'sha256', 'zip-bytes' ) ) );

		$result = GithubUpdater::verifyDownload( false, self::ZIP );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'wp_tmq_updater_checksum', $result->get_error_code() );
		self::assertSame( array( $tmp ), $deleted );
		unlink( $tmp );
	}

	public function test_someone_elses_package_passes_through_untouched(): void {
		Functions\expect( 'download_url' )->never();

		self::assertFalse( GithubUpdater::verifyDownload( false, 'https://downloads.wordpress.org/plugin/akismet.zip' ) );
	}

	public function test_a_release_without_a_digest_is_not_verified(): void {
		Functions\expect( 'download_url' )->never();
		$this->responses['api.github.com']['body'] = (string) json_encode( $this->release( 'v2.10.0', '' ) );

		self::assertFalse( GithubUpdater::verifyDownload( false, self::ZIP ) );
	}

	public function test_an_update_flushes_the_cache(): void {
		GithubUpdater::checkForUpdate( $this->transient( '2.9.0' ) );
		self::assertArrayHasKey( GithubUpdater::CACHE_KEY, $this->transients );

		GithubUpdater::flushCache();

		self::assertArrayNotHasKey( GithubUpdater::CACHE_KEY, $this->transients );
	}
}
