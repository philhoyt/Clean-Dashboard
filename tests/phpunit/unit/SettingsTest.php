<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PH_Cleanup_Settings;

/**
 * Unit tests for the hardening option schema.
 *
 * The sanitize callback and the getters are pure enough to test directly, which
 * is the point: they are the gatekeepers for every other hardening feature, so
 * a regression here would silently change behaviour site-wide.
 */
final class SettingsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__, 3 ) . '/includes/class-ph-cleanup-settings.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ---------------------------------------------------------------------
	 * get_defaults() — the "no configuration required" contract
	 * ------------------------------------------------------------------- */

	public function test_every_option_defaults_to_off(): void {
		$defaults = PH_Cleanup_Settings::get_defaults();

		$this->assertNotEmpty( $defaults );
		$this->assertNotContains( true, $defaults, 'Upgrading must not switch on any hardening behaviour.' );
	}

	/* ---------------------------------------------------------------------
	 * sanitize() — whitelist, coercion, and the unchecked-box case
	 * ------------------------------------------------------------------- */

	public function test_sanitize_drops_unknown_keys(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'add_settings_error' )->justReturn( null );
		// Switching disable_xmlrpc on reaches the add_settings_error branch,
		// which builds its message with __().
		Functions\when( '__' )->returnArg( 1 );

		$clean = PH_Cleanup_Settings::sanitize(
			array(
				'disable_xmlrpc' => '1',
				'evil_key'       => '1',
			)
		);

		$this->assertArrayNotHasKey( 'evil_key', $clean );
		$this->assertSame(
			array_keys( PH_Cleanup_Settings::get_defaults() ),
			array_keys( $clean )
		);
	}

	/**
	 * @param mixed $submitted Raw value from the form.
	 * @param bool  $expected  Expected stored value.
	 */
	#[DataProvider( 'provide_checkbox_values' )]
	public function test_sanitize_coerces_submitted_values( $submitted, bool $expected ): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'add_settings_error' )->justReturn( null );

		$clean = PH_Cleanup_Settings::sanitize( array( 'remove_generator' => $submitted ) );

		$this->assertSame( $expected, $clean['remove_generator'] );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function provide_checkbox_values(): array {
		return array(
			'checkbox value'  => array( '1', true ),
			'integer one'     => array( 1, true ),
			'boolean true'    => array( true, true ),
			'string zero'     => array( '0', false ),
			'empty string'    => array( '', false ),
			'boolean false'   => array( false, false ),
		);
	}

	public function test_sanitize_stores_false_for_an_unchecked_box(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'add_settings_error' )->justReturn( null );

		// An unchecked checkbox is simply absent from the POST body.
		$clean = PH_Cleanup_Settings::sanitize( array() );

		$this->assertFalse( $clean['remove_generator'] );
		$this->assertFalse( $clean['disable_author_archives'] );
	}

	public function test_sanitize_survives_non_array_input(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'add_settings_error' )->justReturn( null );

		foreach ( array( 'not-an-array', null, 42 ) as $garbage ) {
			$clean = PH_Cleanup_Settings::sanitize( $garbage );

			$this->assertSame( array_keys( PH_Cleanup_Settings::get_defaults() ), array_keys( $clean ) );
			$this->assertNotContains( true, $clean );
		}
	}

	public function test_sanitize_warns_when_xmlrpc_is_newly_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'disable_xmlrpc' => false ) );
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'add_settings_error' )
			->once()
			->with( 'wp_dashboard_cleanup_hardening', 'xmlrpc_disabled', \Mockery::any(), 'warning' );

		PH_Cleanup_Settings::sanitize( array( 'disable_xmlrpc' => '1' ) );
	}

	public function test_sanitize_does_not_warn_when_xmlrpc_was_already_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'disable_xmlrpc' => true ) );
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'add_settings_error' )->never();

		PH_Cleanup_Settings::sanitize( array( 'disable_xmlrpc' => '1' ) );
	}

	/* ---------------------------------------------------------------------
	 * get_settings() — defaults merge and corrupt-option tolerance
	 * ------------------------------------------------------------------- */

	public function test_get_settings_merges_defaults_over_a_partial_option(): void {
		Functions\when( 'get_option' )->justReturn( array( 'disable_xmlrpc' => true ) );

		$settings = PH_Cleanup_Settings::get_settings();

		$this->assertSame( array_keys( PH_Cleanup_Settings::get_defaults() ), array_keys( $settings ) );
		$this->assertTrue( $settings['disable_xmlrpc'] );
		$this->assertFalse( $settings['remove_generator'] );
	}

	public function test_get_settings_drops_stale_keys(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'disable_xmlrpc'    => true,
				'removed_in_v2'     => true,
			)
		);

		$this->assertArrayNotHasKey( 'removed_in_v2', PH_Cleanup_Settings::get_settings() );
	}

	public function test_get_settings_survives_a_corrupt_option(): void {
		Functions\when( 'get_option' )->justReturn( 'this is not an array' );

		$settings = PH_Cleanup_Settings::get_settings();

		$this->assertSame( array_keys( PH_Cleanup_Settings::get_defaults() ), array_keys( $settings ) );
		$this->assertNotContains( true, $settings );
	}

	public function test_get_settings_always_returns_real_booleans(): void {
		Functions\when( 'get_option' )->justReturn( array( 'disable_xmlrpc' => '1' ) );

		$this->assertTrue( PH_Cleanup_Settings::get_settings()['disable_xmlrpc'] );
	}

	/* ---------------------------------------------------------------------
	 * is_enabled() — the filtered accessor behaviour reads
	 * ------------------------------------------------------------------- */

	public function test_is_enabled_reads_the_stored_value(): void {
		// Brain Monkey's apply_filters() passes the value through untouched
		// when no filter expectation is set.
		Functions\when( 'get_option' )->justReturn( array( 'remove_generator' => true ) );

		$this->assertTrue( PH_Cleanup_Settings::is_enabled( 'remove_generator' ) );
		$this->assertFalse( PH_Cleanup_Settings::is_enabled( 'disable_xmlrpc' ) );
	}

	public function test_is_enabled_returns_false_for_an_unknown_key(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$this->assertFalse( PH_Cleanup_Settings::is_enabled( 'no_such_option' ) );
	}

	public function test_is_enabled_can_be_overridden_by_filter(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		Filters\expectApplied( 'ph_cleanup_hardening_enabled' )
			->once()
			->with( false, 'disable_xmlrpc' )
			->andReturn( true );

		$this->assertTrue( PH_Cleanup_Settings::is_enabled( 'disable_xmlrpc' ) );
	}

	/* ---------------------------------------------------------------------
	 * register_settings() / add_menu() — the wiring that guards the option
	 * ------------------------------------------------------------------- */

	public function test_register_settings_uses_our_sanitize_callback_and_hides_the_option_from_rest(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'add_settings_section' )->justReturn( null );
		Functions\when( 'add_settings_field' )->justReturn( null );

		Functions\expect( 'register_setting' )
			->once()
			->with(
				'ph_cleanup',
				'wp_dashboard_cleanup_hardening',
				\Mockery::on(
					static function ( $args ) {
						return array( 'PH_Cleanup_Settings', 'sanitize' ) === $args['sanitize_callback']
							&& false === $args['show_in_rest'];
					}
				)
			);

		PH_Cleanup_Settings::register_settings();
	}

	public function test_register_settings_registers_a_field_for_every_option_key(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'register_setting' )->justReturn( null );
		Functions\when( 'add_settings_section' )->justReturn( null );

		$registered = array();
		Functions\when( 'add_settings_field' )->alias(
			static function ( $id, $title, $callback, $page, $section, $args ) use ( &$registered ) {
				$registered[] = $args['key'];
			}
		);

		PH_Cleanup_Settings::register_settings();

		$this->assertSame(
			array_keys( PH_Cleanup_Settings::get_defaults() ),
			$registered,
			'Every option key needs a field, or it becomes unreachable from the UI.'
		);
	}

	public function test_settings_page_requires_manage_options(): void {
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'add_options_page' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::any(),
				'manage_options',
				'wp-dashboard-cleanup',
				\Mockery::type( 'array' )
			);

		PH_Cleanup_Settings::add_menu();
	}

	public function test_render_page_bails_without_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'settings_fields' )->never();
		Functions\expect( 'do_settings_sections' )->never();

		ob_start();
		PH_Cleanup_Settings::render_page();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}
}
