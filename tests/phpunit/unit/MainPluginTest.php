<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
		foreach (
			array(
				'class-ph-cleanup-plugin-updates-widget.php',
				'class-ph-cleanup-server-info-widget.php',
			) as $stub
		) {
			if ( ! file_exists( "$widgets_dir/$stub" ) ) {
				file_put_contents( "$widgets_dir/$stub", "<?php\n" );
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
	 * wp_dashboard_cleanup_handle_dismiss() — nonce + capability gate
	 * ------------------------------------------------------------------- */

	public function test_dismiss_verifies_nonce_before_anything_else(): void {
		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_dismiss_nonce' )
			->andThrow( new MainPluginHaltException() );

		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'update_option' )->never();

		$this->expectException( MainPluginHaltException::class );
		wp_dashboard_cleanup_handle_dismiss();
	}

	public function test_dismiss_requires_manage_options(): void {
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg( 1 );

		Functions\expect( 'wp_die' )->once()->andThrow( new MainPluginHaltException() );
		Functions\expect( 'update_option' )->never();

		$this->expectException( MainPluginHaltException::class );
		wp_dashboard_cleanup_handle_dismiss();
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
		wp_dashboard_cleanup_handle_dismiss();
	}

	/* ---------------------------------------------------------------------
	 * Filter hooks and helpers
	 * ------------------------------------------------------------------- */

	public function test_removed_widgets_list_is_filterable(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->returnArg( 1 );

		// apply_filters returns its passed value here; assert the default set.
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'wp_dashboard_cleanup_removed_widgets', \Mockery::type( 'array' ) )
			->andReturnUsing(
				static function ( $hook, $widgets ) {
					return $widgets;
				}
			);

		$widgets = wp_dashboard_cleanup_get_widgets();

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

		$this->assertTrue( wp_dashboard_cleanup_check_item( $item ) );
		$this->assertTrue( $called );
	}
}
