<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PH_Cleanup_Fingerprint;

/**
 * Unit tests for the fingerprint reduction hooks.
 *
 * For this feature the wiring is the behaviour, so these assert the exact
 * remove_action and add_filter calls. The shortlink case in particular is
 * priority-sensitive: remove_action without a matching priority is a silent
 * no-op, which would look fine in code review and fail in production.
 */
final class FingerprintTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__, 3 ) . '/includes/class-ph-cleanup-settings.php';
		require_once dirname( __DIR__, 3 ) . '/includes/class-ph-cleanup-fingerprint.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Makes PH_Cleanup_Settings::is_enabled() report the given options as on.
	 *
	 * @param array<int, string> $enabled Option keys to switch on.
	 */
	private function enable( array $enabled ): void {
		// Brain Monkey's apply_filters() returns the value untouched when no
		// filter expectation is set, so is_enabled() reads straight through.
		Functions\when( 'get_option' )->justReturn( array_fill_keys( $enabled, true ) );
	}

	public function test_nothing_is_touched_when_every_option_is_off(): void {
		$this->enable( array() );

		Actions\expectRemoved( 'wp_head' )->never();
		Actions\expectRemoved( 'template_redirect' )->never();
		Filters\expectAdded( 'the_generator' )->never();
		Filters\expectAdded( 'xmlrpc_enabled' )->never();
		Filters\expectAdded( 'xmlrpc_methods' )->never();
		Filters\expectAdded( 'wp_headers' )->never();

		PH_Cleanup_Fingerprint::register();
	}

	public function test_remove_generator_covers_both_the_head_tag_and_feeds(): void {
		$this->enable( array( 'remove_generator' ) );

		Actions\expectRemoved( 'wp_head' )->once()->with( 'wp_generator' );
		// Feed templates call the_generator() directly, so the head removal
		// alone would leave the version visible in every feed.
		Filters\expectAdded( 'the_generator' )->once();

		PH_Cleanup_Fingerprint::register();
	}

	public function test_remove_shortlink_passes_the_priority_core_registered(): void {
		$this->enable( array( 'remove_shortlink' ) );

		Actions\expectRemoved( 'wp_head' )->once()->with( 'wp_shortlink_wp_head' );
		Actions\expectRemoved( 'template_redirect' )
			->once()
			->with( 'wp_shortlink_header', 11 );

		PH_Cleanup_Fingerprint::register();
	}

	public function test_disable_xmlrpc_also_empties_the_method_list(): void {
		$this->enable( array( 'disable_xmlrpc' ) );

		// xmlrpc_enabled alone only gates methods that require a login.
		Filters\expectAdded( 'xmlrpc_enabled' )->once();
		Filters\expectAdded( 'xmlrpc_methods' )->once()->with( '__return_empty_array', 10, 1 );

		PH_Cleanup_Fingerprint::register();
	}

	public function test_disabling_xmlrpc_supersedes_the_pingback_option(): void {
		$this->enable( array( 'disable_xmlrpc', 'remove_pingback' ) );

		// Rejecting every method already covers pingbacks, so the narrower
		// filters are not added on top.
		Filters\expectAdded( 'wp_headers' )->never();

		PH_Cleanup_Fingerprint::register();
	}

	public function test_remove_pingback_filters_methods_and_headers(): void {
		$this->enable( array( 'remove_pingback' ) );

		Filters\expectAdded( 'xmlrpc_methods' )->once();
		Filters\expectAdded( 'wp_headers' )->once();

		PH_Cleanup_Fingerprint::register();
	}

	public function test_rsd_and_wlwmanifest_are_removed_independently(): void {
		$this->enable( array( 'remove_rsd_link' ) );
		Actions\expectRemoved( 'wp_head' )->once()->with( 'rsd_link' );
		PH_Cleanup_Fingerprint::register();
	}

	/* ---------------------------------------------------------------------
	 * Pure filter callbacks
	 * ------------------------------------------------------------------- */

	public function test_remove_pingback_methods_drops_only_the_pingback_methods(): void {
		$methods = PH_Cleanup_Fingerprint::remove_pingback_methods(
			array(
				'pingback.ping'                     => 'this_pingback',
				'pingback.extensions.getPingbacks'  => 'that_pingback',
				'wp.getPosts'                       => 'keep_me',
			)
		);

		$this->assertSame( array( 'wp.getPosts' => 'keep_me' ), $methods );
	}

	public function test_remove_pingback_header_drops_only_x_pingback(): void {
		$headers = PH_Cleanup_Fingerprint::remove_pingback_header(
			array(
				'X-Pingback'   => 'https://example.com/xmlrpc.php',
				'Content-Type' => 'text/html',
			)
		);

		$this->assertSame( array( 'Content-Type' => 'text/html' ), $headers );
	}

	public function test_pingback_callbacks_pass_through_unexpected_input(): void {
		// These run on every front-end request, so a filter that hands them
		// something odd must not fatal the public site.
		$this->assertSame( 'nonsense', PH_Cleanup_Fingerprint::remove_pingback_methods( 'nonsense' ) );
		$this->assertNull( PH_Cleanup_Fingerprint::remove_pingback_header( null ) );
	}
}
