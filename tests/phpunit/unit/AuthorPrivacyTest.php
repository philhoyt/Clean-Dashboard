<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PH_Cleanup_Author_Privacy;
use PH_Cleanup_Settings;

/**
 * Unit tests for the author privacy feature.
 *
 * This is the plugin's only front-end code, so alongside the blocking logic
 * these check that every filter callback survives being handed something it
 * did not expect. A fatal in any of them would take down the public site.
 */
final class AuthorPrivacyTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__, 3 ) . '/includes/class-ph-cleanup-settings.php';
		require_once dirname( __DIR__, 3 ) . '/includes/class-ph-cleanup-author-privacy.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Stubs get_option() per option name.
	 *
	 * A blanket justReturn() would hand the settings array back for
	 * permalink_structure too, and casting that array to string in
	 * is_enumeration_request() emits an Array to string conversion warning.
	 *
	 * @param array<string, bool> $settings  Value for the hardening option.
	 * @param string              $permalink Value for permalink_structure.
	 */
	private function stub_get_option( array $settings, string $permalink = '/%postname%/' ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) use ( $settings, $permalink ) {
				if ( 'permalink_structure' === $name ) {
					return $permalink;
				}
				if ( PH_Cleanup_Settings::OPTION_NAME === $name ) {
					return $settings;
				}
				return $default_value;
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * should_block() — the whole decision, as a truth table
	 * ------------------------------------------------------------------- */

	/**
	 * @param bool   $is_author_query   Whether this is an author archive request.
	 * @param bool   $global_disabled   Whether archives are off site-wide.
	 * @param bool   $author_disabled   Whether this author opted out.
	 * @param bool   $is_enumeration    Whether the author was looked up by ID.
	 * @param bool   $block_enumeration Whether ID lookups are blocked.
	 * @param bool   $expected          Expected decision.
	 * @param string $because           Why, for the failure message.
	 */
	#[DataProvider( 'provide_blocking_scenarios' )]
	public function test_should_block(
		bool $is_author_query,
		bool $global_disabled,
		bool $author_disabled,
		bool $is_enumeration,
		bool $block_enumeration,
		bool $expected,
		string $because
	): void {
		$this->assertSame(
			$expected,
			PH_Cleanup_Author_Privacy::should_block(
				$is_author_query,
				$global_disabled,
				$author_disabled,
				$is_enumeration,
				$block_enumeration
			),
			$because
		);
	}

	/**
	 * @return array<string, array{0: bool, 1: bool, 2: bool, 3: bool, 4: bool, 5: bool, 6: string}>
	 */
	public static function provide_blocking_scenarios(): array {
		return array(
			'not an author request, everything on' => array(
				false, true, true, true, true, false,
				'Only author archive requests are ever blocked.',
			),
			'nothing enabled'                      => array(
				true, false, false, false, false, false,
				'A default install must serve author archives.',
			),
			'disabled site-wide'                   => array(
				true, true, false, false, false, true,
				'The site-wide toggle blocks every author archive.',
			),
			'this author opted out'                => array(
				true, false, true, false, false, true,
				'A per-author opt-out works without the site-wide toggle.',
			),
			'enumeration blocked'                  => array(
				true, false, false, true, true, true,
				'An ID lookup is blocked when enumeration blocking is on.',
			),
			'enumeration seen but not blocked'     => array(
				true, false, false, true, false, false,
				'An ID lookup is fine when enumeration blocking is off.',
			),
			'enumeration blocking on, pretty URL'  => array(
				true, false, false, false, true, false,
				'A normal author archive URL is not an enumeration attempt.',
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * is_enumeration_request() — the plain permalinks guard
	 * ------------------------------------------------------------------- */

	public function test_plain_permalinks_are_never_treated_as_enumeration(): void {
		// On plain permalinks /?author=1 IS the author archive URL. Blocking it
		// would kill archives the site owner never asked to disable.
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_query_var' )->justReturn( '1' );

		$this->assertFalse( PH_Cleanup_Author_Privacy::is_enumeration_request() );
	}

	public function test_an_id_lookup_on_pretty_permalinks_is_enumeration(): void {
		Functions\when( 'get_option' )->justReturn( '/%postname%/' );
		Functions\when( 'get_query_var' )->alias(
			static function ( $var ) {
				return 'author' === $var ? '1' : '';
			}
		);

		$this->assertTrue( PH_Cleanup_Author_Privacy::is_enumeration_request() );
	}

	public function test_a_named_author_archive_is_not_enumeration(): void {
		Functions\when( 'get_option' )->justReturn( '/%postname%/' );
		Functions\when( 'get_query_var' )->alias(
			static function ( $var ) {
				return 'author_name' === $var ? 'bob' : '';
			}
		);

		$this->assertFalse( PH_Cleanup_Author_Privacy::is_enumeration_request() );
	}

	public function test_an_array_query_var_is_not_treated_as_enumeration(): void {
		Functions\when( 'get_option' )->justReturn( '/%postname%/' );
		// WP::parse_request() keeps arrays for public query vars, so /?author[]=1
		// arrives here as an array. Casting it would emit a PHP warning.
		Functions\when( 'get_query_var' )->alias(
			static function ( $var ) {
				return 'author' === $var ? array( '1' ) : '';
			}
		);

		$this->assertFalse( PH_Cleanup_Author_Privacy::is_enumeration_request() );
	}

	/* ---------------------------------------------------------------------
	 * is_author_archive_disabled()
	 * ------------------------------------------------------------------- */

	public function test_an_unknown_author_is_not_disabled(): void {
		Functions\expect( 'get_user_meta' )->never();

		$this->assertFalse( PH_Cleanup_Author_Privacy::is_author_archive_disabled( 0 ) );
	}

	public function test_the_per_author_meta_key_is_read(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 7, 'ph_cleanup_disable_author_archive', true )
			->andReturn( '1' );

		$this->assertTrue( PH_Cleanup_Author_Privacy::is_author_archive_disabled( 7 ) );
	}

	/* ---------------------------------------------------------------------
	 * filter_rest_endpoints()
	 * ------------------------------------------------------------------- */

	/**
	 * Returns a stand-in for the registered REST routes.
	 *
	 * @return array<string, string>
	 */
	private function endpoints(): array {
		return array(
			'/wp/v2'                        => 'index',
			'/wp/v2/users'                  => 'collection',
			'/wp/v2/users/(?P<id>[\d]+)'    => 'single',
			'/wp/v2/users/me'               => 'me',
			'/wp/v2/posts'                  => 'posts',
		);
	}

	public function test_rest_users_routes_are_removed_for_logged_out_visitors(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );

		$filtered = PH_Cleanup_Author_Privacy::filter_rest_endpoints( $this->endpoints() );

		$this->assertArrayNotHasKey( '/wp/v2/users', $filtered );
		$this->assertArrayNotHasKey( '/wp/v2/users/(?P<id>[\d]+)', $filtered );
		// Already 401s anonymously, and clients use it to detect login state.
		$this->assertArrayHasKey( '/wp/v2/users/me', $filtered );
		$this->assertArrayHasKey( '/wp/v2/posts', $filtered );
	}

	public function test_logged_in_users_keep_core_behaviour(): void {
		// This is what stops the feature from breaking the block editor.
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$endpoints = $this->endpoints();

		$this->assertSame( $endpoints, PH_Cleanup_Author_Privacy::filter_rest_endpoints( $endpoints ) );
	}

	public function test_cron_keeps_core_behaviour(): void {
		// rest_do_request() dispatches with no current user.
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( true );

		$endpoints = $this->endpoints();

		$this->assertSame( $endpoints, PH_Cleanup_Author_Privacy::filter_rest_endpoints( $endpoints ) );
	}

	public function test_rest_endpoints_passes_through_unexpected_input(): void {
		$this->assertNull( PH_Cleanup_Author_Privacy::filter_rest_endpoints( null ) );
	}

	/* ---------------------------------------------------------------------
	 * Sitemaps, oEmbed, and author links
	 * ------------------------------------------------------------------- */

	public function test_only_the_users_sitemap_provider_is_dropped(): void {
		$provider = new \stdClass();

		$this->assertFalse( PH_Cleanup_Author_Privacy::filter_sitemaps_provider( $provider, 'users' ) );
		$this->assertSame( $provider, PH_Cleanup_Author_Privacy::filter_sitemaps_provider( $provider, 'posts' ) );
	}

	public function test_opted_out_authors_are_excluded_from_the_users_sitemap(): void {
		$args = PH_Cleanup_Author_Privacy::filter_sitemaps_users_query_args( array( 'number' => 10 ) );

		$this->assertSame( 10, $args['number'] );
		$this->assertSame( 'OR', $args['meta_query']['relation'] );
		$this->assertSame( 'ph_cleanup_disable_author_archive', $args['meta_query'][0]['key'] );
	}

	public function test_oembed_author_fields_are_stripped(): void {
		$data = PH_Cleanup_Author_Privacy::filter_oembed_response_data(
			array(
				'title'       => 'A post',
				'author_name' => 'bob',
				'author_url'  => 'https://example.com/author/bob/',
				'html'        => '<blockquote></blockquote>',
			)
		);

		$this->assertSame(
			array(
				'title' => 'A post',
				'html'  => '<blockquote></blockquote>',
			),
			$data
		);
	}

	public function test_oembed_filter_passes_through_unexpected_input(): void {
		$this->assertSame( 'nonsense', PH_Cleanup_Author_Privacy::filter_oembed_response_data( 'nonsense' ) );
	}

	public function test_author_link_points_home_when_the_archive_is_disabled(): void {
		$this->stub_get_option( array( 'disable_author_archives' => true ) );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );

		$this->assertSame(
			'https://example.com/',
			PH_Cleanup_Author_Privacy::filter_author_link( 'https://example.com/author/bob/', 7 )
		);
	}

	public function test_author_link_is_untouched_when_archives_are_available(): void {
		$this->stub_get_option( array() );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$this->assertSame(
			'https://example.com/author/bob/',
			PH_Cleanup_Author_Privacy::filter_author_link( 'https://example.com/author/bob/', 7 )
		);
	}

	public function test_author_link_respects_a_per_author_opt_out(): void {
		$this->stub_get_option( array() );
		Functions\when( 'get_user_meta' )->justReturn( '1' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );

		$this->assertSame(
			'https://example.com/',
			PH_Cleanup_Author_Privacy::filter_author_link( 'https://example.com/author/bob/', 7 )
		);
	}

	/* ---------------------------------------------------------------------
	 * maybe_block_author_archive()
	 * ------------------------------------------------------------------- */

	public function test_non_author_requests_are_left_alone(): void {
		Functions\when( 'is_author' )->justReturn( false );

		Functions\expect( 'status_header' )->never();
		Functions\expect( 'get_query_var' )->never();

		PH_Cleanup_Author_Privacy::maybe_block_author_archive();
	}

	public function test_a_disabled_archive_sends_a_real_404(): void {
		Functions\when( 'is_author' )->justReturn( true );
		Functions\when( 'get_query_var' )->justReturn( '7' );
		$this->stub_get_option( array( 'disable_author_archives' => true ) );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		// A 404 status without the query flag would render the archive template
		// with a 404 header; both are needed.
		Functions\expect( 'status_header' )->once()->with( 404 );
		Functions\expect( 'nocache_headers' )->once();

		PH_Cleanup_Author_Privacy::maybe_block_author_archive();
	}

	public function test_an_allowed_archive_is_served_normally(): void {
		Functions\when( 'is_author' )->justReturn( true );
		Functions\when( 'get_query_var' )->justReturn( '7' );
		$this->stub_get_option( array() );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		Functions\expect( 'status_header' )->never();
		Functions\expect( 'nocache_headers' )->never();

		PH_Cleanup_Author_Privacy::maybe_block_author_archive();
	}

	/* ---------------------------------------------------------------------
	 * Profile field registration
	 * ------------------------------------------------------------------- */

	public function test_the_per_author_field_is_registered(): void {
		Functions\when( '__' )->returnArg( 1 );
		$this->stub_get_option( array() );

		$fields = PH_Cleanup_Author_Privacy::register_profile_field( array() );

		$this->assertCount( 1, $fields );
		$this->assertSame( 'ph_cleanup_disable_author_archive', $fields[0]['key'] );
		$this->assertIsCallable( $fields[0]['visible'] );
	}

	public function test_the_per_author_field_mentions_the_site_wide_toggle(): void {
		Functions\when( '__' )->returnArg( 1 );
		$this->stub_get_option( array( 'disable_author_archives' => true ) );

		$fields = PH_Cleanup_Author_Privacy::register_profile_field( array() );

		$this->assertStringContainsString( 'site-wide', $fields[0]['description'] );
	}

	public function test_the_field_is_visible_to_anyone_who_can_publish(): void {
		$user = \Mockery::mock( 'WP_User' );
		$user->ID = 7;

		Functions\expect( 'user_can' )->once()->with( $user, 'edit_posts' )->andReturn( true );
		// Short-circuits, so the uncached post count query is skipped.
		Functions\expect( 'count_user_posts' )->never();

		$this->assertTrue( PH_Cleanup_Author_Privacy::profile_field_visible( $user ) );
	}

	public function test_the_field_is_visible_to_a_subscriber_with_orphaned_posts(): void {
		$user = \Mockery::mock( 'WP_User' );
		$user->ID = 7;

		Functions\when( 'user_can' )->justReturn( false );
		Functions\expect( 'count_user_posts' )->once()->with( 7 )->andReturn( 3 );

		$this->assertTrue( PH_Cleanup_Author_Privacy::profile_field_visible( $user ) );
	}

	public function test_the_field_is_hidden_from_a_subscriber_with_no_posts(): void {
		$user = \Mockery::mock( 'WP_User' );
		$user->ID = 7;

		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'count_user_posts' )->justReturn( 0 );

		$this->assertFalse( PH_Cleanup_Author_Privacy::profile_field_visible( $user ) );
	}
}
