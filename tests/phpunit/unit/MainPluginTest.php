<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Thrown by stubbed wp_die() / wp_safe_redirect() so we can assert a handler
 * halted without actually terminating the test process.
 */
class MainPluginHaltException extends \RuntimeException {}

/**
 * Unit tests for the main plugin file: the checklist dismiss security gate
 * and the developer-facing filter hooks.
 */
final class MainPluginTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Build a throwaway plugin root containing only the widget includes the
		// main file requires — but no lib/ — so the bundled Plugin Update
		// Checker block (guarded by file_exists) is skipped and we avoid pulling
		// in its WordPress dependencies. The widget classes themselves are not
		// needed here, so empty stub files satisfy the require_once calls.
		$fixture     = sys_get_temp_dir() . '/wp-dashboard-cleanup-fixture';
		$widgets_dir = $fixture . '/includes/widgets';
		if ( ! is_dir( $widgets_dir ) ) {
			mkdir( $widgets_dir, 0777, true );
		}
		$stubs = array(
			'includes/class-ph-cleanup-settings.php',
			'includes/class-ph-cleanup-profile-fields.php',
			'includes/class-ph-cleanup-author-privacy.php',
			'includes/class-ph-cleanup-fingerprint.php',
			'includes/widgets/class-ph-cleanup-plugin-updates-widget.php',
			'includes/widgets/class-ph-cleanup-server-info-widget.php',
		);
		foreach ( $stubs as $stub ) {
			if ( ! file_exists( "$fixture/$stub" ) ) {
				file_put_contents( "$fixture/$stub", "<?php\n" );
			}
		}

		Functions\when( 'plugin_dir_path' )->justReturn( $fixture . '/' );
		Functions\when( 'add_action' )->justReturn( true );

		require_once dirname( __DIR__, 3 ) . '/dashboard-cleanup.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ---------------------------------------------------------------------
	 * ph_cleanup_handle_dismiss() — nonce + capability gate
	 * ------------------------------------------------------------------- */

	public function test_dismiss_verifies_nonce_before_anything_else(): void {
		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'ph_cleanup_dismiss', 'ph_cleanup_dismiss_nonce' )
			->andThrow( new MainPluginHaltException() );

		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'update_option' )->never();

		$this->expectException( MainPluginHaltException::class );
		ph_cleanup_handle_dismiss();
	}

	public function test_dismiss_requires_manage_options(): void {
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg( 1 );

		Functions\expect( 'wp_die' )->once()->andThrow( new MainPluginHaltException() );
		Functions\expect( 'update_option' )->never();

		$this->expectException( MainPluginHaltException::class );
		ph_cleanup_handle_dismiss();
	}

	public function test_dismiss_sets_option_and_redirects_when_authorized(): void {
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn( 'http://example.test/wp-admin/index.php' );

		Functions\expect( 'update_option' )
			->once()
			->with( 'wp_dashboard_cleanup_checklist_dismissed', true );

		// wp_safe_redirect() precedes exit; throw so we halt before exit runs.
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->andThrow( new MainPluginHaltException() );

		$this->expectException( MainPluginHaltException::class );
		ph_cleanup_handle_dismiss();
	}

	/* ---------------------------------------------------------------------
	 * Filter hooks and helpers
	 * ------------------------------------------------------------------- */

	public function test_the_pre_2_0_widget_filter_name_still_applies(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		// The 2.0.0 rename kept the old hook alive through
		// apply_filters_deprecated(), so third-party code hooked to the wp_
		// name before the rename must still be able to change the list.
		Filters\expectApplied( 'wp_dashboard_cleanup_removed_widgets' )
			->once()
			->andReturn( array( array( 'id' => 'only_mine', 'context' => 'normal' ) ) );

		$this->assertSame(
			array( array( 'id' => 'only_mine', 'context' => 'normal' ) ),
			ph_cleanup_get_widgets()
		);
	}

	public function test_removed_widgets_list_is_filterable(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );

		// apply_filters returns its passed value here; assert the default set.
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'ph_cleanup_removed_widgets', \Mockery::type( 'array' ) )
			->andReturnUsing(
				static function ( $hook, $widgets ) {
					return $widgets;
				}
			);

		$widgets = ph_cleanup_get_widgets();

		$this->assertContains(
			array(
				'id'      => 'dashboard_activity',
				'context' => 'normal',
			),
			$widgets
		);
	}

	public function test_check_item_invokes_the_item_callback(): void {
		$called = false;
		$item   = array(
			'callback' => static function () use ( &$called ) {
				$called = true;
				return true;
			},
		);

		$this->assertTrue( ph_cleanup_check_item( $item ) );
		$this->assertTrue( $called );
	}

	/* ---------------------------------------------------------------------
	 * Security checklist items
	 * ------------------------------------------------------------------- */

	public function test_security_checklist_items_have_the_expected_shape(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );

		$items = ph_cleanup_get_security_checklist_items();

		$this->assertCount( 6, $items );
		foreach ( $items as $item ) {
			$this->assertArrayHasKey( 'label', $item );
			$this->assertIsCallable( $item['callback'] );
			$this->assertNotSame( '', $item['link'] );
		}
	}

	public function test_admin_username_check_passes_when_the_account_is_gone(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );

		$items = ph_cleanup_get_security_checklist_items();

		Functions\expect( 'username_exists' )->once()->with( 'admin' )->andReturn( false );
		$this->assertTrue( ph_cleanup_check_item( $items[0] ) );

		Functions\expect( 'username_exists' )->once()->with( 'admin' )->andReturn( 5 );
		$this->assertFalse( ph_cleanup_check_item( $items[0] ) );
	}

	public function test_default_role_check_fails_only_for_administrator(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );

		$items = ph_cleanup_get_security_checklist_items();

		Functions\when( 'get_option' )->justReturn( 'administrator' );
		$this->assertFalse( ph_cleanup_check_item( $items[1] ) );

		Functions\when( 'get_option' )->justReturn( 'subscriber' );
		$this->assertTrue( ph_cleanup_check_item( $items[1] ) );
	}

	public function test_https_check_requires_both_urls(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component ) {
				return parse_url( (string) $url, $component );
			}
		);

		$items = ph_cleanup_get_security_checklist_items();

		Functions\when( 'get_option' )->justReturn( 'https://example.com' );
		$this->assertTrue( ph_cleanup_check_item( $items[2] ) );

		Functions\when( 'get_option' )->justReturn( 'http://example.com' );
		$this->assertFalse( ph_cleanup_check_item( $items[2] ) );
	}

	/**
	 * @param bool|null $wp_debug         Value of WP_DEBUG.
	 * @param bool|null $wp_debug_display Value of WP_DEBUG_DISPLAY.
	 * @param bool      $expected         Whether errors are safely hidden.
	 */
	#[DataProvider( 'provide_debug_display_states' )]
	public function test_debug_display_helper( ?bool $wp_debug, ?bool $wp_debug_display, bool $expected ): void {
		$this->assertSame(
			$expected,
			ph_cleanup_is_debug_display_safe( $wp_debug, $wp_debug_display )
		);
	}

	/**
	 * @return array<string, array{0: bool|null, 1: bool|null, 2: bool}>
	 */
	public static function provide_debug_display_states(): array {
		return array(
			'debug off entirely'      => array( false, null, true ),
			'debug undefined'         => array( null, null, true ),
			'debug on, display off'   => array( true, false, true ),
			'debug on, display on'    => array( true, true, false ),
			'debug on, display unset' => array( true, null, false ),
		);
	}

	/**
	 * @param bool|null $updater_disabled Value of AUTOMATIC_UPDATER_DISABLED.
	 * @param mixed     $auto_update_core Value of WP_AUTO_UPDATE_CORE.
	 * @param bool      $expected         Whether core auto-updates look enabled.
	 */
	#[DataProvider( 'provide_autoupdate_states' )]
	public function test_core_autoupdate_helper( ?bool $updater_disabled, $auto_update_core, bool $expected ): void {
		$this->assertSame(
			$expected,
			ph_cleanup_is_core_autoupdate_enabled( $updater_disabled, $auto_update_core )
		);
	}

	/**
	 * @return array<string, array{0: bool|null, 1: mixed, 2: bool}>
	 */
	public static function provide_autoupdate_states(): array {
		return array(
			'nothing configured' => array( null, null, true ),
			'updater disabled'   => array( true, null, false ),
			'core updates off'   => array( null, false, false ),
			'minor updates only' => array( null, 'minor', true ),
			'all updates'        => array( null, true, true ),
		);
	}
}
