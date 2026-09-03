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
	 * register_profile_field() — hands the toggle to the shared section
	 *
	 * Rendering and saving now live in PH_Cleanup_Profile_Fields; the
	 * capability and nonce coverage for them is in ProfileFieldsTest.
	 * ------------------------------------------------------------------- */

	public function test_register_profile_field_appends_the_server_info_toggle(): void {
		Functions\when( '__' )->returnArg( 1 );

		$fields = PH_Cleanup_Server_Info_Widget::register_profile_field( array() );

		$this->assertCount( 1, $fields );
		$this->assertSame( 'ph_cleanup_show_server_info', $fields[0]['key'] );
		$this->assertIsCallable( $fields[0]['visible'] );
	}

	public function test_register_profile_field_preserves_existing_fields(): void {
		Functions\when( '__' )->returnArg( 1 );

		$fields = PH_Cleanup_Server_Info_Widget::register_profile_field(
			array( array( 'key' => 'someone_elses_field' ) )
		);

		$this->assertCount( 2, $fields );
		$this->assertSame( 'someone_elses_field', $fields[0]['key'] );
	}

	public function test_profile_field_visible_only_for_admins(): void {
		$user = \Mockery::mock( 'WP_User' );

		Functions\expect( 'user_can' )->once()->with( $user, 'manage_options' )->andReturn( false );
		$this->assertFalse( PH_Cleanup_Server_Info_Widget::profile_field_visible( $user ) );

		Functions\expect( 'user_can' )->once()->with( $user, 'manage_options' )->andReturn( true );
		$this->assertTrue( PH_Cleanup_Server_Info_Widget::profile_field_visible( $user ) );
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
