<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PH_Cleanup_Server_Info_Widget;

/**
 * Thrown by the wp_die() stub so we can assert that a handler halted.
 */
class ServerInfoWpDieException extends \RuntimeException {}

/**
 * Unit tests for the Server Info widget's security gates and save logic.
 *
 * These mock WordPress functions with Brain Monkey, so they verify that the
 * nonce and capability checks fire (and that the handlers bail out) without a
 * running WordPress install.
 */
final class ServerInfoWidgetTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// The widget class registers hooks at file scope; stub add_action so
		// loading the file does not fatal, then load it once.
		Functions\when( 'add_action' )->justReturn( true );
		require_once dirname( __DIR__, 3 ) . '/includes/widgets/class-ph-cleanup-server-info-widget.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		unset( $_POST );
		parent::tearDown();
	}

	/* ---------------------------------------------------------------------
	 * handle_ajax() — nonce + capability gate
	 * ------------------------------------------------------------------- */

	public function test_handle_ajax_verifies_nonce_before_doing_work(): void {
		// An invalid nonce dies inside check_ajax_referer; simulate by throwing.
		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'ph_cleanup_db_size_nonce', 'nonce' )
			->andThrow( new ServerInfoWpDieException() );

		// Nothing past the nonce check should run.
		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'delete_transient' )->never();

		$this->expectException( ServerInfoWpDieException::class );
		PH_Cleanup_Server_Info_Widget::handle_ajax();
	}

	public function test_handle_ajax_requires_manage_options(): void {
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		// Capability failure must die with -1 and skip the cache refresh.
		Functions\expect( 'wp_die' )
			->once()
			->with( '-1' )
			->andThrow( new ServerInfoWpDieException() );
		Functions\expect( 'delete_transient' )->never();

		$this->expectException( ServerInfoWpDieException::class );
		PH_Cleanup_Server_Info_Widget::handle_ajax();
	}

	public function test_handle_ajax_refreshes_cache_on_success(): void {
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// Capability passed, so the transient is cleared and content re-rendered.
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'ph_cleanup_db_size_cache' );

		// render_db_section() reads a cached value; return one so no DB is hit.
		Functions\when( 'get_transient' )->justReturn(
			array(
				'total'           => 0,
				'autoloaded_size' => 0,
				'tables'          => array(),
			)
		);
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'size_format' )->justReturn( '0 B' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce-value' );

		Functions\expect( 'wp_die' )->once()->andReturnNull();

		ob_start();
		PH_Cleanup_Server_Info_Widget::handle_ajax();
		ob_end_clean();
	}

	/* ---------------------------------------------------------------------
	 * save_profile_field() — capability + nonce gate and save logic
	 * ------------------------------------------------------------------- */

	public function test_save_profile_field_bails_without_edit_user_cap(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_user', 42 )
			->andReturn( false );

		// No nonce check and no write when the capability is missing.
		Functions\expect( 'check_admin_referer' )->never();
		Functions\expect( 'update_user_meta' )->never();

		PH_Cleanup_Server_Info_Widget::save_profile_field( 42 );
	}

	public function test_save_profile_field_stores_one_when_box_checked(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'update-user_42' );

		$_POST['ph_cleanup_show_server_info'] = '1';

		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, 'ph_cleanup_show_server_info', 1 );

		PH_Cleanup_Server_Info_Widget::save_profile_field( 42 );
	}

	public function test_save_profile_field_stores_zero_when_box_unchecked(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( 'update-user_42' );

		// No $_POST key set — the checkbox was unchecked.
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, 'ph_cleanup_show_server_info', 0 );

		PH_Cleanup_Server_Info_Widget::save_profile_field( 42 );
	}

	/* ---------------------------------------------------------------------
	 * register() — capability + opt-in gate
	 * ------------------------------------------------------------------- */

	public function test_register_skips_widget_without_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'get_user_meta' )->never();
		Functions\expect( 'wp_add_dashboard_widget' )->never();

		PH_Cleanup_Server_Info_Widget::register();
	}

	public function test_register_skips_widget_when_opt_in_disabled(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		Functions\expect( 'wp_add_dashboard_widget' )->never();

		PH_Cleanup_Server_Info_Widget::register();
	}

	public function test_register_adds_widget_when_opted_in(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_user_meta' )->justReturn( '1' );
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'wp_add_dashboard_widget' )
			->once()
			->with(
				'ph_cleanup_server_info',
				\Mockery::any(),
				\Mockery::type( 'array' ),
				\Mockery::any(),
				\Mockery::any(),
				'side'
			);

		PH_Cleanup_Server_Info_Widget::register();
	}
}
