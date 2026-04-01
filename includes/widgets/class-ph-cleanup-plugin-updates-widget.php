<?php
/**
 * Updates & Cleanup dashboard widget.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays plugin/theme updates and flags inactive plugins and unused themes.
 */
class PH_Cleanup_Plugin_Updates_Widget {

	/**
	 * Registers the dashboard widget.
	 *
	 * Includes the total action count in the widget title when items need attention.
	 */
	public static function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count = count( get_plugin_updates() )
			+ count( get_theme_updates() )
			+ count( self::get_inactive_plugins() )
			+ count( self::get_unused_themes() );

		$title = $count > 0
			/* translators: %d: number of items needing attention */
			? sprintf( __( 'Updates & Cleanup (%d)', 'wp-dashboard-cleanup' ), $count )
			: __( 'Updates & Cleanup', 'wp-dashboard-cleanup' );

		wp_add_dashboard_widget(
			'ph_cleanup_plugin_updates',
			$title,
			array( __CLASS__, 'render' ),
			null,
			null,
			'side'
		);
	}

	/**
	 * Returns inactive plugins, excluding network-activated plugins on multisite.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_inactive_plugins(): array {
		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active_plugins  = array_merge( $active_plugins, $network_plugins );
		}

		$inactive = array();
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			if ( ! in_array( $plugin_file, $active_plugins, true ) ) {
				$inactive[ $plugin_file ] = $plugin_data;
			}
		}
		return $inactive;
	}

	/**
	 * Returns themes that are not the active theme or its parent theme.
	 *
	 * @return WP_Theme[]
	 */
	private static function get_unused_themes(): array {
		$active = (string) get_option( 'stylesheet' );
		$parent = (string) get_option( 'template' );
		$unused = array();

		foreach ( wp_get_themes() as $slug => $theme ) {
			if ( $slug !== $active && $slug !== $parent ) {
				$unused[ $slug ] = $theme;
			}
		}
		return $unused;
	}

	/**
	 * Renders all four sections of the widget.
	 */
	public static function render(): void {
		self::render_plugin_updates();
		echo '<hr style="margin: 10px 0; border: none; border-top: 1px solid #f0f0f0;">';
		self::render_theme_updates();
		echo '<hr style="margin: 10px 0; border: none; border-top: 1px solid #f0f0f0;">';
		self::render_inactive_plugins();
		echo '<hr style="margin: 10px 0; border: none; border-top: 1px solid #f0f0f0;">';
		self::render_unused_themes();
	}

	/**
	 * Renders the plugin updates section.
	 */
	private static function render_plugin_updates(): void {
		$updates = get_plugin_updates();

		printf(
			'<p style="margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; letter-spacing: 0.5px;">%s</p>',
			esc_html__( 'Plugin Updates', 'wp-dashboard-cleanup' )
		);

		if ( empty( $updates ) ) {
			self::render_all_clear( __( 'All plugins are up to date.', 'wp-dashboard-cleanup' ) );
			return;
		}

		self::render_update_list( array_values( $updates ), 'plugin' );

		printf(
			'<a href="%s" class="button button-small" style="margin-top: 6px;">%s</a>',
			esc_url( admin_url( 'update-core.php' ) ),
			esc_html__( 'Update Plugins', 'wp-dashboard-cleanup' )
		);
	}

	/**
	 * Renders the theme updates section.
	 */
	private static function render_theme_updates(): void {
		$updates = get_theme_updates();

		printf(
			'<p style="margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; letter-spacing: 0.5px;">%s</p>',
			esc_html__( 'Theme Updates', 'wp-dashboard-cleanup' )
		);

		if ( empty( $updates ) ) {
			self::render_all_clear( __( 'All themes are up to date.', 'wp-dashboard-cleanup' ) );
			return;
		}

		self::render_update_list( array_values( $updates ), 'theme' );

		printf(
			'<a href="%s" class="button button-small" style="margin-top: 6px;">%s</a>',
			esc_url( admin_url( 'update-core.php' ) ),
			esc_html__( 'Update Themes', 'wp-dashboard-cleanup' )
		);
	}

	/**
	 * Renders the inactive plugins section.
	 */
	private static function render_inactive_plugins(): void {
		$inactive = self::get_inactive_plugins();

		printf(
			'<p style="margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; letter-spacing: 0.5px;">%s</p>',
			esc_html__( 'Inactive Plugins', 'wp-dashboard-cleanup' )
		);

		if ( empty( $inactive ) ) {
			self::render_all_clear( __( 'No inactive plugins.', 'wp-dashboard-cleanup' ) );
			return;
		}

		echo '<ul style="margin: 0; padding: 0; list-style: none;">';

		$items      = array_values( $inactive );
		$last_index = count( $items ) - 1;

		foreach ( $items as $index => $plugin_data ) {
			$border = ( $index === $last_index ) ? '' : 'border-bottom: 1px solid #f0f0f0;';
			printf(
				'<li style="padding: 5px 0; %s">
					<strong style="font-size: 12px;">%s</strong>
					<span style="color: #50575e; font-size: 11px; margin-left: 4px;">v%s</span>
				</li>',
				esc_attr( $border ),
				esc_html( $plugin_data['Name'] ),
				esc_html( $plugin_data['Version'] )
			);
		}

		echo '</ul>';

		printf(
			'<a href="%s" class="button button-small" style="margin-top: 6px;">%s</a>',
			esc_url( admin_url( 'plugins.php?plugin_status=inactive' ) ),
			esc_html__( 'Manage Inactive Plugins', 'wp-dashboard-cleanup' )
		);
	}

	/**
	 * Renders the unused themes section.
	 */
	private static function render_unused_themes(): void {
		$unused = self::get_unused_themes();

		printf(
			'<p style="margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; letter-spacing: 0.5px;">%s</p>',
			esc_html__( 'Unused Themes', 'wp-dashboard-cleanup' )
		);

		if ( empty( $unused ) ) {
			self::render_all_clear( __( 'No unused themes.', 'wp-dashboard-cleanup' ) );
			return;
		}

		echo '<ul style="margin: 0; padding: 0; list-style: none;">';

		$items      = array_values( $unused );
		$last_index = count( $items ) - 1;

		foreach ( $items as $index => $theme ) {
			$border = ( $index === $last_index ) ? '' : 'border-bottom: 1px solid #f0f0f0;';
			printf(
				'<li style="padding: 5px 0; %s">
					<strong style="font-size: 12px;">%s</strong>
					<span style="color: #50575e; font-size: 11px; margin-left: 4px;">v%s</span>
				</li>',
				esc_attr( $border ),
				esc_html( $theme->display( 'Name' ) ),
				esc_html( $theme->display( 'Version' ) )
			);
		}

		echo '</ul>';

		printf(
			'<a href="%s" class="button button-small" style="margin-top: 6px;">%s</a>',
			esc_url( admin_url( 'themes.php' ) ),
			esc_html__( 'Manage Themes', 'wp-dashboard-cleanup' )
		);
	}

	/**
	 * Renders a shared "all clear" status line.
	 *
	 * @param string $message The translated message to display.
	 */
	private static function render_all_clear( string $message ): void {
		printf(
			'<p style="display: flex; align-items: center; gap: 6px; margin: 0; color: #00a32a;">
				<span style="font-size: 16px; line-height: 1;">&#10003;</span>
				%s
			</p>',
			esc_html( $message )
		);
	}

	/**
	 * Renders a list of update items for plugins or themes.
	 *
	 * @param array  $items Array of plugin data objects or WP_Theme objects with update info.
	 * @param string $type  'plugin' or 'theme'.
	 */
	private static function render_update_list( array $items, string $type ): void {
		echo '<ul style="margin: 0; padding: 0; list-style: none;">';

		$last_index = count( $items ) - 1;

		foreach ( $items as $index => $item ) {
			$border = ( $index === $last_index ) ? '' : 'border-bottom: 1px solid #f0f0f0;';

			if ( 'theme' === $type ) {
				$name        = $item->display( 'Name' );
				$version     = $item->display( 'Version' );
				$new_version = $item->update['new_version'] ?? '';
			} else {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$name = $item->Name;
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$version     = $item->Version;
				$new_version = $item->update->new_version ?? '';
			}

			printf(
				'<li style="padding: 5px 0; %s">
					<strong style="font-size: 12px;">%s</strong><br>
					<span style="color: #50575e; font-size: 11px;">%s &rarr; %s</span>
				</li>',
				esc_attr( $border ),
				esc_html( $name ),
				esc_html( $version ),
				esc_html( $new_version )
			);
		}

		echo '</ul>';
	}
}
