<?php
declare(strict_types=1);

namespace WpDashboardCleanup\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PH_Cleanup_Profile_Fields;

/**
 * Unit tests for the shared user profile section.
 *
 * The capability and nonce coverage here used to live in ServerInfoWidgetTest,
 * against that widget's own profile handlers. Those handlers were folded into
 * this class so a second per-user field would not print a second heading, and
 * the gates moved with them.
 */
final class ProfileFieldsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__, 3 ) . '/includes/class-ph-cleanup-profile-fields.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		unset( $_POST );
		parent::tearDown();
	}

	/**
	 * Returns a field definition for the tests.
	 *
	 * @param string $key     Meta key.
	 * @param bool   $visible Whether the field should be shown.
	 * @return array<string, mixed>
	 */
	private function field( string $key, bool $visible = true ): array {
		return array(
			'key'         => $key,
			'title'       => 'Title for ' . $key,
			'label'       => 'Label for ' . $key,
			'description' => '',
			'visible'     => static function () use ( $visible ) {
				return $visible;
			},
		);
	}

	/* ---------------------------------------------------------------------
	 * save() — capability + nonce gate
	 * ------------------------------------------------------------------- */

	public function test_save_bails_without_edit_user_cap(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_user', 42 )
			->andReturn( false );

		// No nonce check and no write when the capability is missing.
		Functions\expect( 'check_admin_referer' )->never();
		Functions\expect( 'get_userdata' )->never();
		Functions\expect( 'update_user_meta' )->never();

		PH_Cleanup_Profile_Fields::save( 42 );
	}

	public function test_save_verifies_the_nonce_before_writing(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'update-user_42' )
			->andThrow( new \RuntimeException( 'bad nonce' ) );

		Functions\expect( 'update_user_meta' )->never();

		$this->expectException( \RuntimeException::class );
		PH_Cleanup_Profile_Fields::save( 42 );
	}

	public function test_save_stores_one_when_box_checked(): void {
		$user = \Mockery::mock( 'WP_User' );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( 'update-user_42' );
		Functions\when( 'get_userdata' )->justReturn( $user );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn( array( $this->field( 'ph_cleanup_show_server_info' ) ) );

		$_POST['ph_cleanup_show_server_info'] = '1';

		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, 'ph_cleanup_show_server_info', 1 );

		PH_Cleanup_Profile_Fields::save( 42 );
	}

	public function test_save_stores_zero_when_box_unchecked(): void {
		$user = \Mockery::mock( 'WP_User' );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( 'update-user_42' );
		Functions\when( 'get_userdata' )->justReturn( $user );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn( array( $this->field( 'ph_cleanup_show_server_info' ) ) );

		// No $_POST key set — the checkbox was unchecked.
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, 'ph_cleanup_show_server_info', 0 );

		PH_Cleanup_Profile_Fields::save( 42 );
	}

	/**
	 * The regression test for the refactor's worst failure mode: a field the
	 * editor cannot see must not be silently reset to 0 on every save.
	 */
	public function test_save_skips_fields_the_user_cannot_see(): void {
		$user = \Mockery::mock( 'WP_User' );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'get_userdata' )->justReturn( $user );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn(
				array(
					$this->field( 'visible_field', true ),
					$this->field( 'hidden_field', false ),
				)
			);

		Functions\expect( 'update_user_meta' )->once()->with( 42, 'visible_field', 0 );
		Functions\expect( 'update_user_meta' )->never()->with( 42, 'hidden_field', \Mockery::any() );

		PH_Cleanup_Profile_Fields::save( 42 );
	}

	public function test_save_bails_when_the_user_cannot_be_loaded(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'get_userdata' )->justReturn( false );

		Functions\expect( 'update_user_meta' )->never();

		PH_Cleanup_Profile_Fields::save( 42 );
	}

	/* ---------------------------------------------------------------------
	 * render() — one heading, or none at all
	 * ------------------------------------------------------------------- */

	public function test_render_prints_exactly_one_heading_for_multiple_fields(): void {
		$user = \Mockery::mock( 'WP_User' );
		$user->ID = 42;

		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'checked' )->justReturn( '' );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn(
				array(
					$this->field( 'first_field' ),
					$this->field( 'second_field' ),
				)
			);

		ob_start();
		PH_Cleanup_Profile_Fields::render( $user );
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<h2>' ) );
		$this->assertSame( 2, substr_count( $output, '<tr>' ) );
	}

	public function test_render_prints_nothing_when_no_field_is_visible(): void {
		$user = \Mockery::mock( 'WP_User' );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn( array( $this->field( 'hidden_field', false ) ) );

		ob_start();
		PH_Cleanup_Profile_Fields::render( $user );
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output, 'An empty section heading would be worse than no section.' );
	}

	public function test_render_prints_nothing_when_no_field_is_registered(): void {
		$user = \Mockery::mock( 'WP_User' );

		ob_start();
		PH_Cleanup_Profile_Fields::render( $user );
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	/* ---------------------------------------------------------------------
	 * get_visible_fields() — registry filtering
	 * ------------------------------------------------------------------- */

	public function test_fields_without_a_key_are_ignored(): void {
		$user = \Mockery::mock( 'WP_User' );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn( array( array( 'title' => 'No key here' ) ) );

		$this->assertSame( array(), PH_Cleanup_Profile_Fields::get_visible_fields( $user ) );
	}

	public function test_a_field_without_a_visible_callback_is_shown(): void {
		$user = \Mockery::mock( 'WP_User' );

		Filters\expectApplied( 'ph_cleanup_profile_fields' )
			->andReturn( array( array( 'key' => 'always_on' ) ) );

		$this->assertCount( 1, PH_Cleanup_Profile_Fields::get_visible_fields( $user ) );
	}
}
