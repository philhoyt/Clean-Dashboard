<?php
/**
 * Plugin Updates dashboard widget.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays a list of plugins with available updates.
 */
class PH_Cleanup_Plugin_Updates_Widget {

	/**
	 * Registers the dashboard widget.
	 *
	 * Includes the pending update count in the widget title when updates exist.
	 */
	public static function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$updates = get_plugin_updates();
		$count   = count( $updates );

		$title = $count > 0
			/* translators: %d: number of plugins with available updates */
			? sprintf( __( 'Plugin Updates (%d)', 'wp-dashboard-cleanup' ), $count )
			: __( 'Plugin Updates', 'wp-dashboard-cleanup' );

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
	 * Renders the widget content.
	 */
	public static function render(): void {
		$updates = get_plugin_updates();

		if ( empty( $updates ) ) {
			printf(
				'<p style="display: flex; align-items: center; gap: 6px; margin: 0; color: #00a32a;">
					<span style="font-size: 16px; line-height: 1;">&#10003;</span>
					%s
				</p>',
				esc_html__( 'All plugins are up to date.', 'wp-dashboard-cleanup' )
			);
			return;
		}

		echo '<ul style="margin: 0 0 12px; padding: 0; list-style: none;">';

		$updates    = array_values( $updates );
		$last_index = count( $updates ) - 1;

		foreach ( $updates as $index => $plugin_data ) {
			$border = ( $index === $last_index ) ? '' : 'border-bottom: 1px solid #f0f0f0;';

			printf(
				'<li style="padding: 6px 0; %s">
					<strong>%s</strong><br>
					<span style="color: #50575e; font-size: 12px;">%s &rarr; %s</span>
				</li>',
				esc_attr( $border ),
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				esc_html( $plugin_data->Name ),
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				esc_html( $plugin_data->Version ),
				esc_html( $plugin_data->update->new_version )
			);
		}

		echo '</ul>';

		printf(
			'<a href="%s" class="button button-primary">%s</a>',
			esc_url( admin_url( 'update-core.php' ) ),
			esc_html__( 'Go to Updates', 'wp-dashboard-cleanup' )
		);
	}
}
