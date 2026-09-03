<?php
/**
 * Shared user profile section for the plugin's per-user settings.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves every per-user checkbox this plugin adds.
 *
 * Features register their field through the ph_cleanup_profile_fields
 * filter rather than hooking the profile screen themselves, so the "Dashboard
 * Cleanup" heading is printed exactly once no matter how many fields exist.
 */
class PH_Cleanup_Profile_Fields {

	/**
	 * Hooks the profile screen render and save.
	 */
	public static function register(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
	}

	/**
	 * Returns every registered field.
	 *
	 * Each field is an array with:
	 *   - key         (string)   Meta key, also the form field name.
	 *   - title       (string)   Row heading.
	 *   - label       (string)   Text beside the checkbox.
	 *   - description (string)   Optional help text below the checkbox.
	 *   - visible     (callable) Receives WP_User, returns whether to show the row.
	 *
	 * Developers can add their own:
	 *
	 *   add_filter( 'ph_cleanup_profile_fields', function( $fields ) {
	 *       $fields[] = array( ... );
	 *       return $fields;
	 *   } );
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fields(): array {
		$fields = (array) apply_filters( 'ph_cleanup_profile_fields', array() );

		return (array) apply_filters_deprecated(
			'wp_dashboard_cleanup_profile_fields',
			array( $fields ),
			'2.0.0',
			'ph_cleanup_profile_fields'
		);
	}

	/**
	 * Returns the fields that should be shown for a given user.
	 *
	 * Used by both render() and save(), so a field the current user cannot see
	 * is never written. Without that, a hidden field would be reset to 0 every
	 * time the profile is saved.
	 *
	 * @param WP_User $user The user whose profile is being viewed or saved.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_visible_fields( WP_User $user ): array {
		$visible = array();

		foreach ( self::get_fields() as $field ) {
			if ( empty( $field['key'] ) ) {
				continue;
			}

			if ( isset( $field['visible'] ) && is_callable( $field['visible'] )
				&& ! call_user_func( $field['visible'], $user ) ) {
				continue;
			}

			$visible[] = $field;
		}

		return $visible;
	}

	/**
	 * Renders the plugin's section on the profile screen.
	 *
	 * @param WP_User $user The user whose profile is being edited.
	 */
	public static function render( WP_User $user ): void {
		$fields = self::get_visible_fields( $user );
		if ( empty( $fields ) ) {
			return;
		}

		printf( '<h2>%s</h2>', esc_html__( 'Dashboard Cleanup', 'wp-dashboard-cleanup' ) );
		echo '<table class="form-table" role="presentation">';

		foreach ( $fields as $field ) {
			self::render_row( $user, $field );
		}

		echo '</table>';
	}

	/**
	 * Renders a single checkbox row.
	 *
	 * @param WP_User              $user  The user whose profile is being edited.
	 * @param array<string, mixed> $field Field definition from get_fields().
	 */
	private static function render_row( WP_User $user, array $field ): void {
		$key     = (string) $field['key'];
		$enabled = (bool) get_user_meta( $user->ID, $key, true );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( (string) $field['title'] ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $enabled ); ?>>
					<?php echo esc_html( (string) $field['label'] ); ?>
				</label>
				<?php if ( ! empty( $field['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( (string) $field['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves every visible checkbox from the profile form.
	 *
	 * @param int $user_id The ID of the user being saved.
	 */
	public static function save( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		check_admin_referer( 'update-user_' . $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		foreach ( self::get_visible_fields( $user ) as $field ) {
			$key = (string) $field['key'];
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- presence of the key is the whole value; stored as 1 or 0.
			update_user_meta( $user_id, $key, isset( $_POST[ $key ] ) ? 1 : 0 );
		}
	}
}
