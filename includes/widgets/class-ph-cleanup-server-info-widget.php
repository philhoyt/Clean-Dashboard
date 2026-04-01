<?php
/**
 * Server Info dashboard widget.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays PHP version status and database size in a single widget.
 */
class PH_Cleanup_Server_Info_Widget {

	// Update PHP thresholds on major WordPress releases.
	const PHP_MINIMUM     = '7.4';
	const PHP_RECOMMENDED = '8.2';

	const TRANSIENT_KEY       = 'ph_cleanup_db_size_cache';
	const AJAX_ACTION         = 'ph_cleanup_refresh_db_size';
	const AUTOLOAD_WARN_BYTES = 819200; // 800KB

	const USER_META_KEY = 'ph_cleanup_show_server_info';

	/**
	 * Registers the dashboard widget.
	 */
	public static function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ph_cleanup_server_info',
			__( 'Server Info', 'wp-dashboard-cleanup' ),
			array( __CLASS__, 'render' ),
			null,
			null,
			'side'
		);
	}

	/**
	 * Renders the full widget: PHP section + DB content wrapper + inline refresh script.
	 */
	public static function render(): void {
		self::render_php_section();

		echo '<hr style="margin: 12px 0; border: none; border-top: 1px solid #f0f0f0;">';

		echo '<div id="ph-cleanup-db-size-content">';
		self::render_db_section();
		echo '</div>';

		printf(
			'<script>
				( function() {
					var wrap = document.getElementById( "ph-cleanup-db-size-content" );
					if ( ! wrap ) { return; }
					wrap.addEventListener( "click", function( e ) {
						if ( ! e.target || e.target.id !== "ph-cleanup-refresh-db" ) { return; }
						var btn = e.target;
						btn.disabled = true;
						fetch( ajaxurl, {
							method: "POST",
							headers: { "Content-Type": "application/x-www-form-urlencoded" },
							body: "action=%s&nonce=" + btn.dataset.nonce
						} )
						.then( function( r ) { return r.text(); } )
						.then( function( html ) {
							wrap.innerHTML = html;
						} );
					} );
				} )();
			</script>',
			esc_js( self::AJAX_ACTION )
		);
	}

	/**
	 * Renders the PHP version section.
	 */
	private static function render_php_section(): void {
		$version = PHP_VERSION;

		if ( version_compare( $version, self::PHP_RECOMMENDED, '>=' ) ) {
			$label = __( 'Current', 'wp-dashboard-cleanup' );
			$color = '#00a32a';
		} elseif ( version_compare( $version, self::PHP_MINIMUM, '>=' ) ) {
			$label = __( 'Acceptable', 'wp-dashboard-cleanup' );
			$color = '#dba617';
		} else {
			$label = __( 'Outdated', 'wp-dashboard-cleanup' );
			$color = '#d63638';
		}

		printf(
			'<p style="margin: 0 0 4px; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; letter-spacing: 0.5px;">%s</p>',
			esc_html__( 'PHP', 'wp-dashboard-cleanup' )
		);

		printf(
			'<p style="margin: 0; font-size: 14px;">
				<span style="color: #50575e;">%s &mdash; </span>
				<strong style="color: %s;">%s</strong>
			</p>',
			esc_html( $version ),
			esc_attr( $color ),
			esc_html( $label )
		);

		if ( '#00a32a' !== $color ) {
			printf(
				'<p style="margin: 4px 0 0; color: #50575e; font-size: 12px;">%s</p>',
				esc_html__( 'Contact your host to upgrade PHP.', 'wp-dashboard-cleanup' )
			);
		}
	}

	/**
	 * Renders the database size section (used by both render() and handle_ajax()).
	 */
	public static function render_db_section(): void {
		$data = self::get_data();

		printf(
			'<p style="margin: 0 0 8px; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; letter-spacing: 0.5px;">%s</p>',
			esc_html__( 'Database', 'wp-dashboard-cleanup' )
		);

		printf(
			'<p style="margin: 0 0 8px; font-size: 14px;"><strong>%s</strong> %s</p>',
			esc_html__( 'Total size:', 'wp-dashboard-cleanup' ),
			esc_html( size_format( $data['total'] ) )
		);

		if ( ! empty( $data['tables'] ) ) {
			echo '<ul style="margin: 0 0 8px; padding: 0; list-style: none;">';

			$top        = array_slice( $data['tables'], 0, 5 );
			$last_index = count( $top ) - 1;

			foreach ( $top as $index => $table ) {
				$border = ( $index === $last_index ) ? '' : 'border-bottom: 1px solid #f0f0f0;';

				printf(
					'<li style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px; %s">
						<span style="color: #1d2327; font-family: monospace;">%s</span>
						<span style="color: #50575e;">%s</span>
					</li>',
					esc_attr( $border ),
					esc_html( $table['name'] ),
					esc_html( size_format( $table['size'] ) )
				);
			}

			echo '</ul>';
		}

		if ( ! empty( $data['autoloaded_size'] ) && $data['autoloaded_size'] > self::AUTOLOAD_WARN_BYTES ) {
			printf(
				'<p style="margin: 0 0 8px; padding: 6px 8px; background: #fcf9e8; border-left: 3px solid #dba617; font-size: 12px; color: #50575e;">%s</p>',
				sprintf(
					/* translators: %s: formatted size of autoloaded data */
					esc_html__( 'Autoloaded data is %s. Consider removing orphaned plugin options or expired transients.', 'wp-dashboard-cleanup' ),
					esc_html( size_format( $data['autoloaded_size'] ) )
				)
			);
		}

		printf(
			'<button id="ph-cleanup-refresh-db" class="button button-secondary" data-nonce="%s" style="font-size: 12px;">%s</button>',
			esc_attr( wp_create_nonce( 'ph_cleanup_db_size_nonce' ) ),
			esc_html__( 'Refresh', 'wp-dashboard-cleanup' )
		);
	}

	/**
	 * Returns cached or freshly queried database size data.
	 *
	 * @return array{total: int, autoloaded_size: int, tables: list<array{name: string, size: int}>}
	 */
	private static function get_data(): array {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}
		return self::query_data();
	}

	/**
	 * Queries information_schema for table sizes and caches the result.
	 *
	 * @return array{total: int, autoloaded_size: int, tables: list<array{name: string, size: int}>}
	 */
	private static function query_data(): array {
		global $wpdb;

		$prefix = $wpdb->esc_like( $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS tbl, ( data_length + index_length ) AS size
				FROM information_schema.TABLES
				WHERE table_schema = DATABASE()
				AND table_name LIKE %s
				ORDER BY size DESC',
				$prefix . '%'
			)
		);

		$total  = 0;
		$tables = array();

		foreach ( (array) $rows as $row ) {
			$size   = (int) $row->size;
			$total += $size;

			$tables[] = array(
				'name' => $row->tbl,
				'size' => $size,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoloaded_size = (int) $wpdb->get_var(
			"SELECT SUM( LENGTH( option_value ) ) FROM {$wpdb->options} WHERE autoload = 'yes'"
		);

		$data = array(
			'total'           => $total,
			'autoloaded_size' => $autoloaded_size,
			'tables'          => $tables,
		);

		set_transient( self::TRANSIENT_KEY, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Renders the Server Info toggle field on a user's profile page.
	 *
	 * Only shown for users with manage_options capability.
	 *
	 * @param WP_User $user The user whose profile is being edited.
	 */
	public static function render_profile_field( WP_User $user ): void {
		if ( ! user_can( $user, 'manage_options' ) ) {
			return;
		}

		$enabled = (bool) get_user_meta( $user->ID, self::USER_META_KEY, true );
		?>
		<h2><?php esc_html_e( 'Dashboard Cleanup', 'wp-dashboard-cleanup' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Server Info Widget', 'wp-dashboard-cleanup' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::USER_META_KEY ); ?>" value="1" <?php checked( $enabled ); ?>>
						<?php esc_html_e( 'Show the Server Info widget on the dashboard', 'wp-dashboard-cleanup' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the Server Info toggle from the user profile form.
	 *
	 * @param int $user_id The ID of the user being saved.
	 */
	public static function save_profile_field( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		check_admin_referer( 'update-user_' . $user_id );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checkbox value is cast to int.
		update_user_meta( $user_id, self::USER_META_KEY, isset( $_POST[ self::USER_META_KEY ] ) ? 1 : 0 );
	}

	/**
	 * Handles the AJAX refresh request for the database section.
	 *
	 * Clears the transient and returns fresh rendered content.
	 */
	public static function handle_ajax(): void {
		check_ajax_referer( 'ph_cleanup_db_size_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		delete_transient( self::TRANSIENT_KEY );
		self::render_db_section();
		wp_die();
	}
}

add_action( 'wp_ajax_' . PH_Cleanup_Server_Info_Widget::AJAX_ACTION, array( 'PH_Cleanup_Server_Info_Widget', 'handle_ajax' ) );
add_action( 'show_user_profile', array( 'PH_Cleanup_Server_Info_Widget', 'render_profile_field' ) );
add_action( 'edit_user_profile', array( 'PH_Cleanup_Server_Info_Widget', 'render_profile_field' ) );
add_action( 'personal_options_update', array( 'PH_Cleanup_Server_Info_Widget', 'save_profile_field' ) );
add_action( 'edit_user_profile_update', array( 'PH_Cleanup_Server_Info_Widget', 'save_profile_field' ) );
