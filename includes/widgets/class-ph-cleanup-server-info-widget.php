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
	 * Enqueues the refresh script on the dashboard page for users who see this widget.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) ) {
			return;
		}

		wp_register_script( 'ph-cleanup-server-info', false, array(), PH_CLEANUP_VERSION, true );
		wp_enqueue_script( 'ph-cleanup-server-info' );
		wp_add_inline_script(
			'ph-cleanup-server-info',
			sprintf(
				'( function() {
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
				} )();',
				esc_js( self::AJAX_ACTION )
			)
		);
	}

	/**
	 * Renders the full widget: PHP section + DB content wrapper.
	 */
	public static function render(): void {
		self::render_php_section();

		echo '<hr style="margin: 12px 0; border: none; border-top: 1px solid #f0f0f0;">';

		echo '<div id="ph-cleanup-db-size-content">';
		self::render_db_section();
		echo '</div>';
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

		// Declared array return: anything else in the transient — a truncated
		// object cache entry, a key collision — would be a TypeError and take
		// the whole dashboard down. Re-query instead.
		if ( is_array( $cached ) && isset( $cached['total'], $cached['autoloaded_size'], $cached['tables'] ) ) {
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
			"SELECT SUM( LENGTH( option_value ) ) FROM {$wpdb->options} WHERE autoload IN ( 'yes', 'on', 'auto' )"
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
	 * Adds the Server Info toggle to the shared profile section.
	 *
	 * Rendering and saving are handled by PH_Cleanup_Profile_Fields so the
	 * "Dashboard Cleanup" heading is printed once regardless of how many
	 * per-user fields the plugin adds.
	 *
	 * @param mixed $fields Registered profile fields.
	 * @return mixed
	 */
	public static function register_profile_field( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$fields[] = array(
			'key'         => self::USER_META_KEY,
			'title'       => __( 'Server Info Widget', 'wp-dashboard-cleanup' ),
			'label'       => __( 'Show the Server Info widget on the dashboard', 'wp-dashboard-cleanup' ),
			'description' => '',
			'visible'     => array( __CLASS__, 'profile_field_visible' ),
		);

		return $fields;
	}

	/**
	 * Shows the Server Info toggle only to users who could see the widget.
	 *
	 * @param WP_User $user The user whose profile is being viewed.
	 * @return bool
	 */
	public static function profile_field_visible( WP_User $user ): bool {
		return user_can( $user, 'manage_options' );
	}

	/**
	 * Handles the AJAX refresh request for the database section.
	 *
	 * Clears the transient and returns fresh rendered content.
	 */
	public static function handle_ajax(): void {
		check_ajax_referer( 'ph_cleanup_db_size_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '-1' );
		}

		delete_transient( self::TRANSIENT_KEY );
		self::render_db_section();
		wp_die();
	}
}

add_action( 'admin_enqueue_scripts', array( 'PH_Cleanup_Server_Info_Widget', 'enqueue_assets' ) );
add_action( 'wp_ajax_' . PH_Cleanup_Server_Info_Widget::AJAX_ACTION, array( 'PH_Cleanup_Server_Info_Widget', 'handle_ajax' ) );
add_filter( 'ph_cleanup_profile_fields', array( 'PH_Cleanup_Server_Info_Widget', 'register_profile_field' ) );
