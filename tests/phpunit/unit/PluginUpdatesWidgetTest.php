<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PH_Cleanup_Plugin_Updates_Widget;

/**
 * Unit tests for the Updates & Cleanup widget registration gate.
 */
final class PluginUpdatesWidgetTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once dirname( __DIR__, 3 ) . '/includes/widgets/class-ph-cleanup-plugin-updates-widget.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_skips_widget_without_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'wp_add_dashboard_widget' )->never();

		PH_Cleanup_Plugin_Updates_Widget::register();
	}

	public function test_register_adds_widget_when_capable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugin_updates' )->justReturn( array() );
		Functions\when( 'get_theme_updates' )->justReturn( array() );
		Functions\when( 'get_plugins' )->justReturn( array() );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_get_themes' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'wp_add_dashboard_widget' )
			->once()
			->with(
				'ph_cleanup_plugin_updates',
				\Mockery::any(),
				\Mockery::type( 'array' ),
				\Mockery::any(),
				\Mockery::any(),
				'side'
			);

		PH_Cleanup_Plugin_Updates_Widget::register();
	}
}
