<?php
/**
 * Plugin Name:       Dashboard Cleanup
 * Plugin URI:        https://github.com/philhoyt/wp-dashboard-cleanup
 * Description:       Removes noise from the WordPress admin dashboard. No configuration required.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Phil Hoyt
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-dashboard-cleanup
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/widgets/class-ph-cleanup-plugin-updates-widget.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/widgets/class-ph-cleanup-server-info-widget.php';

$wp_dashboard_cleanup_puc = plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $wp_dashboard_cleanup_puc ) ) {
	require_once $wp_dashboard_cleanup_puc;

	$wp_dashboard_cleanup_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/philhoyt/wp-dashboard-cleanup/',
		__FILE__,
		'wp-dashboard-cleanup'
	);
	$wp_dashboard_cleanup_updater->getVcsApi()->enableReleaseAssets();
}

/**
 * Returns the list of dashboard widget IDs to remove.
 *
 * Developers can modify this list via the filter:
 *
 *   add_filter( 'wp_dashboard_cleanup_removed_widgets', function( $widgets ) {
 *       // $widgets is an array of [ 'id' => string, 'context' => string ]
 *       return $widgets;
 *   } );
 *
 * @return array<int, array{id: string, context: string}>
 */
function wp_dashboard_cleanup_get_widgets(): array {
	$widgets = array(
		array(
			'id'      => 'dashboard_right_now',
			'context' => 'normal',
		),
		array(
			'id'      => 'dashboard_primary',
			'context' => 'side',
		),
		array(
			'id'      => 'dashboard_site_health',
			'context' => 'normal',
		),
		array(
			'id'      => 'dashboard_activity',
			'context' => 'normal',
		),
		array(
			'id'      => 'dashboard_quick_press',
			'context' => 'side',
		),
	);

	return (array) apply_filters( 'wp_dashboard_cleanup_removed_widgets', $widgets );
}

/**
 * Removes registered dashboard widgets.
 */
function wp_dashboard_cleanup_remove_widgets(): void {
	foreach ( wp_dashboard_cleanup_get_widgets() as $widget ) {
		if ( ! empty( $widget['id'] ) && ! empty( $widget['context'] ) ) {
			remove_meta_box( $widget['id'], 'dashboard', $widget['context'] );
		}
	}
}
add_action( 'wp_dashboard_setup', 'wp_dashboard_cleanup_remove_widgets' );

/**
 * Removes the Welcome to WordPress panel.
 */
function wp_dashboard_cleanup_remove_welcome_panel(): void {
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}
add_action( 'wp_dashboard_setup', 'wp_dashboard_cleanup_remove_welcome_panel' );

/**
 * Returns the list of checklist items for the Site Setup Checklist widget.
 *
 * Each item is an array with:
 *   - label    (string)   Human-readable label displayed in the widget.
 *   - callback (callable) Returns true when the item is complete, false otherwise.
 *   - link     (string)   Admin URL to the screen where the issue can be fixed.
 *
 * Developers can modify this list via the filter:
 *
 *   add_filter( 'wp_dashboard_cleanup_checklist_items', function( $items ) {
 *       // $items is an array of [ 'label' => string, 'callback' => callable, 'link' => string ]
 *       return $items;
 *   } );
 *
 * @return array<int, array{label: string, callback: callable, link: string}>
 */
function wp_dashboard_cleanup_get_checklist_items(): array {
	$items = array(
		array(
			'label'    => __( 'Delete "Hello World!" post', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				$posts = get_posts(
					array(
						'title'          => 'Hello World!',
						'post_status'    => 'publish',
						'post_type'      => 'post',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				);
				return empty( $posts );
			},
			'link'     => admin_url( 'edit.php' ),
		),
		array(
			'label'    => __( 'Delete "Sample Page"', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				$pages = get_posts(
					array(
						'title'          => 'Sample Page',
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				);
				return empty( $pages );
			},
			'link'     => admin_url( 'edit.php?post_type=page' ),
		),
		array(
			'label'    => __( 'Set timezone', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				$tz     = get_option( 'timezone_string' );
				$offset = (string) get_option( 'gmt_offset' );
				return ! ( empty( $tz ) && '0' === $offset );
			},
			'link'     => admin_url( 'options-general.php' ),
		),
		array(
			'label'    => __( 'Update site tagline', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				return 'Just another WordPress site' !== get_option( 'blogdescription' );
			},
			'link'     => admin_url( 'options-general.php' ),
		),
		array(
			'label'    => __( 'Set permalink structure', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				return '' !== get_option( 'permalink_structure' );
			},
			'link'     => admin_url( 'options-permalink.php' ),
		),
		array(
			'label'    => __( 'Delete Hello Dolly plugin', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				return ! file_exists( WP_PLUGIN_DIR . '/hello.php' );
			},
			'link'     => admin_url( 'plugins.php' ),
		),
		array(
			'label'    => __( 'Delete default comment', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				$comments = get_comments(
					array(
						'author_email' => 'wapuu@wordpress.example',
						'number'       => 1,
						'fields'       => 'ids',
						'status'       => 'approve',
					)
				);
				return empty( $comments );
			},
			'link'     => admin_url( 'edit-comments.php' ),
		),
		array(
			'label'    => __( 'Allow search engine indexing', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				return '1' === (string) get_option( 'blog_public' );
			},
			'link'     => admin_url( 'options-reading.php' ),
		),
	);

	return (array) apply_filters( 'wp_dashboard_cleanup_checklist_items', $items );
}

/**
 * Executes a checklist item's callback and returns its completion status.
 *
 * @param array $item A checklist item array with a 'callback' key.
 * @return bool True if the item is complete, false otherwise.
 */
function wp_dashboard_cleanup_check_item( array $item ): bool {
	return (bool) call_user_func( $item['callback'] );
}

/**
 * Renders the Site Setup Checklist dashboard widget.
 */
function wp_dashboard_cleanup_render_checklist_widget(): void {
	$items = wp_dashboard_cleanup_get_checklist_items();
	$total = count( $items );

	$statuses  = array();
	$completed = 0;
	foreach ( $items as $item ) {
		$done       = wp_dashboard_cleanup_check_item( $item );
		$statuses[] = $done;
		$completed += $done ? 1 : 0;
	}

	printf(
		'<p style="margin: 0 0 12px; color: #50575e;">%s</p>',
		/* translators: 1: number of completed items, 2: total number of items */
		esc_html( sprintf( __( '%1$d of %2$d items complete.', 'wp-dashboard-cleanup' ), $completed, $total ) )
	);

	echo '<ul style="margin: 0; padding: 0; list-style: none;">';

	$last_index = $total - 1;
	foreach ( $items as $index => $item ) {
		$done           = $statuses[ $index ];
		$is_last        = ( $index === $last_index );
		$border         = $is_last ? '' : 'border-bottom: 1px solid #f0f0f0;';
		$icon_color     = $done ? '#00a32a' : '#d63638';
		$icon_character = $done ? '&#10003;' : '&#10007;';

		printf(
			'<li style="display: flex; align-items: center; gap: 8px; padding: 6px 0; %s">
				<span style="color: %s; font-size: 16px; line-height: 1; flex-shrink: 0;">%s</span>
				<a href="%s">%s</a>
			</li>',
			esc_attr( $border ),
			esc_attr( $icon_color ),
			wp_kses( $icon_character, array() ),
			esc_url( $item['link'] ),
			esc_html( $item['label'] )
		);
	}

	echo '</ul>';

	if ( $completed === $total ) {
		printf(
			'<form method="post" action="%s" style="margin-top: 12px; text-align: right;"><input type="hidden" name="action" value="wp_dashboard_cleanup_dismiss">',
			esc_url( admin_url( 'admin-post.php' ) )
		);
		wp_nonce_field( 'wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_dismiss_nonce' );
		printf(
			'<button type="submit" class="button button-secondary">%s</button></form>',
			esc_html__( 'Remove This Widget', 'wp-dashboard-cleanup' )
		);
	}
}

/**
 * Registers the Site Setup Checklist dashboard widget.
 */
function wp_dashboard_cleanup_register_checklist_widget(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_option( 'wp_dashboard_cleanup_checklist_dismissed' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'wp_dashboard_cleanup_checklist',
		__( 'Site Setup Checklist', 'wp-dashboard-cleanup' ),
		'wp_dashboard_cleanup_render_checklist_widget'
	);
}
add_action( 'wp_dashboard_setup', 'wp_dashboard_cleanup_register_checklist_widget' );

/**
 * Handles the dismiss action for the Site Setup Checklist widget.
 *
 * Stores a flag in user meta so the widget is no longer registered for this user.
 */
function wp_dashboard_cleanup_handle_dismiss(): void {
	check_admin_referer( 'wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_dismiss_nonce' );
	update_option( 'wp_dashboard_cleanup_checklist_dismissed', true );
	wp_safe_redirect( admin_url( 'index.php' ) );
	exit;
}
add_action( 'admin_post_wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_handle_dismiss' );

add_action( 'wp_dashboard_setup', array( 'PH_Cleanup_Plugin_Updates_Widget', 'register' ) );
add_action( 'wp_dashboard_setup', array( 'PH_Cleanup_Server_Info_Widget', 'register' ) );
