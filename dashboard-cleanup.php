<?php
/**
 * Plugin Name:       Dashboard Cleanup
 * Plugin URI:        https://github.com/philhoyt/Clean-Dashboard
 * Description:       Removes noise from the WordPress admin dashboard, and adds optional hardening settings. No configuration required.
 * Version:           2.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Phil Hoyt
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-dashboard-cleanup
 * Domain Path:       /languages
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PH_CLEANUP_VERSION', '2.0.0' );
define( 'PH_CLEANUP_FILE', __FILE__ );

// Database key, deliberately left on its historical name — see the note on
// PH_Cleanup_Settings::OPTION_NAME. Renaming it would lose the dismissal on
// every install that already made one.
define( 'PH_CLEANUP_DISMISSED_OPTION', 'wp_dashboard_cleanup_checklist_dismissed' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ph-cleanup-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ph-cleanup-profile-fields.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ph-cleanup-author-privacy.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ph-cleanup-fingerprint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/widgets/class-ph-cleanup-plugin-updates-widget.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/widgets/class-ph-cleanup-server-info-widget.php';

$ph_cleanup_puc = plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $ph_cleanup_puc ) ) {
	require_once $ph_cleanup_puc;

	$ph_cleanup_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/philhoyt/Clean-Dashboard/',
		__FILE__,
		'wp-dashboard-cleanup'
	);
	$ph_cleanup_api     = $ph_cleanup_updater->getVcsApi();
	if ( method_exists( $ph_cleanup_api, 'enableReleaseAssets' ) ) {
		$ph_cleanup_api->enableReleaseAssets();
	}
}

/**
 * Returns the list of dashboard widget IDs to remove.
 *
 * Developers can modify this list via the filter:
 *
 *   add_filter( 'ph_cleanup_removed_widgets', function( $widgets ) {
 *       // $widgets is an array of [ 'id' => string, 'context' => string ]
 *       return $widgets;
 *   } );
 *
 * @return array<int, array{id: string, context: string}>
 */
function ph_cleanup_get_widgets(): array {
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

	$widgets = (array) apply_filters( 'ph_cleanup_removed_widgets', $widgets );

	return (array) apply_filters_deprecated(
		'wp_dashboard_cleanup_removed_widgets',
		array( $widgets ),
		'2.0.0',
		'ph_cleanup_removed_widgets'
	);
}

/**
 * Removes registered dashboard widgets.
 */
function ph_cleanup_remove_widgets(): void {
	foreach ( ph_cleanup_get_widgets() as $widget ) {
		if ( ! empty( $widget['id'] ) && ! empty( $widget['context'] ) ) {
			remove_meta_box( $widget['id'], 'dashboard', $widget['context'] );
		}
	}
}
add_action( 'wp_dashboard_setup', 'ph_cleanup_remove_widgets' );

/**
 * Removes the Welcome to WordPress panel.
 */
function ph_cleanup_remove_welcome_panel(): void {
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}
add_action( 'wp_dashboard_setup', 'ph_cleanup_remove_welcome_panel' );

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
 *   add_filter( 'ph_cleanup_checklist_items', function( $items ) {
 *       // $items is an array of [ 'label' => string, 'callback' => callable, 'link' => string ]
 *       return $items;
 *   } );
 *
 * @return array<int, array{label: string, callback: callable, link: string}>
 */
function ph_cleanup_get_checklist_items(): array {
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

	$items = array_merge( $items, ph_cleanup_get_security_checklist_items() );

	$items = (array) apply_filters( 'ph_cleanup_checklist_items', $items );

	return (array) apply_filters_deprecated(
		'wp_dashboard_cleanup_checklist_items',
		array( $items ),
		'2.0.0',
		'ph_cleanup_checklist_items'
	);
}

/**
 * Returns whether PHP errors are safely hidden from visitors.
 *
 * Pure function so the logic can be tested without redefining constants, which
 * PHP does not allow once they are set.
 *
 * @param bool|null $wp_debug         Value of WP_DEBUG, or null if undefined.
 * @param bool|null $wp_debug_display Value of WP_DEBUG_DISPLAY, or null if undefined.
 * @return bool
 */
function ph_cleanup_is_debug_display_safe( ?bool $wp_debug, ?bool $wp_debug_display ): bool {
	if ( ! $wp_debug ) {
		return true;
	}

	return false === $wp_debug_display;
}

/**
 * Returns whether automatic core updates look enabled.
 *
 * A heuristic, and deliberately so. There is no core helper for this:
 * wp_is_auto_update_enabled_for_type() only handles plugins and themes, and
 * WP_Automatic_Updater lives in a wp-admin file that is not loaded on the
 * dashboard. This targets minor releases, which carry the security fixes.
 *
 * @param bool|null $updater_disabled  Value of AUTOMATIC_UPDATER_DISABLED, or null if undefined.
 * @param mixed     $auto_update_core  Value of WP_AUTO_UPDATE_CORE, or null if undefined.
 * @return bool
 */
function ph_cleanup_is_core_autoupdate_enabled( ?bool $updater_disabled, $auto_update_core ): bool {
	if ( true === $updater_disabled ) {
		return false;
	}

	return false !== $auto_update_core;
}

/**
 * Returns the security checks appended to the Site Setup Checklist.
 *
 * Same shape as ph_cleanup_get_checklist_items(): label, callback,
 * and a link to the screen where the item can be dealt with.
 *
 * @return array<int, array{label: string, callback: callable, link: string}>
 */
function ph_cleanup_get_security_checklist_items(): array {
	return array(
		array(
			'label'    => __( 'Remove the default "admin" username', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				// Returns the user ID when the login exists and false when it
				// does not — never null. It looks up a unique indexed column and
				// hits the object cache first, so this does not scan the users table.
				return false === username_exists( 'admin' );
			},
			'link'     => admin_url( 'users.php' ),
		),
		array(
			'label'    => __( 'Set the default role for new users below Administrator', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				return 'administrator' !== (string) get_option( 'default_role' );
			},
			'link'     => admin_url( 'options-general.php' ),
		),
		array(
			'label'    => __( 'Serve the site over HTTPS', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				$home    = wp_parse_url( (string) get_option( 'home' ), PHP_URL_SCHEME );
				$siteurl = wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_SCHEME );
				return 'https' === $home && 'https' === $siteurl;
			},
			'link'     => admin_url( 'options-general.php' ),
		),
		array(
			'label'    => __( 'Hide PHP errors from visitors', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				return ph_cleanup_is_debug_display_safe(
					defined( 'WP_DEBUG' ) ? (bool) WP_DEBUG : null,
					defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : null
				);
			},
			'link'     => admin_url( 'site-health.php?tab=debug' ),
		),
		array(
			'label'    => __( 'Enable automatic WordPress updates', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				$enabled = ph_cleanup_is_core_autoupdate_enabled(
					defined( 'AUTOMATIC_UPDATER_DISABLED' ) ? (bool) AUTOMATIC_UPDATER_DISABLED : null,
					defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : null
				);

				// Catch host- or plugin-level disabling too. Both of these are
				// core's own filters being read for their effective value, not
				// hooks this plugin defines, so the prefix rule does not apply.
				// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				return $enabled
					&& ! (bool) apply_filters( 'automatic_updater_disabled', false )
					&& (bool) apply_filters( 'allow_minor_auto_core_updates', true );
				// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			},
			'link'     => admin_url( 'update-core.php' ),
		),
		array(
			'label'    => __( 'Disable the plugin and theme file editor', 'wp-dashboard-cleanup' ),
			'callback' => function () {
				// wp_is_file_mod_allowed() covers DISALLOW_FILE_MODS and the
				// file_mod_allowed filter, so host-level disabling counts too.
				// DISALLOW_FILE_EDIT is narrower and has no core helper.
				return ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
					|| ! wp_is_file_mod_allowed( 'capability_edit_themes' );
			},
			// Site Health lists the current value of both constants. The file
			// editor screen itself 404s precisely when this check passes.
			'link'     => admin_url( 'site-health.php?tab=debug' ),
		),
	);
}

/**
 * Executes a checklist item's callback and returns its completion status.
 *
 * @param array $item A checklist item array with a 'callback' key.
 * @return bool True if the item is complete, false otherwise.
 */
function ph_cleanup_check_item( array $item ): bool {
	return (bool) call_user_func( $item['callback'] );
}

/**
 * Renders the Site Setup Checklist dashboard widget.
 */
function ph_cleanup_render_checklist_widget(): void {
	$items = ph_cleanup_get_checklist_items();
	$total = count( $items );

	$statuses  = array();
	$completed = 0;
	foreach ( $items as $item ) {
		$done       = ph_cleanup_check_item( $item );
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
			'<form method="post" action="%s" style="margin-top: 12px; text-align: right;"><input type="hidden" name="action" value="ph_cleanup_dismiss">',
			esc_url( admin_url( 'admin-post.php' ) )
		);
		wp_nonce_field( 'ph_cleanup_dismiss', 'ph_cleanup_dismiss_nonce' );
		printf(
			'<button type="submit" class="button button-secondary">%s</button></form>',
			esc_html__( 'Remove This Widget', 'wp-dashboard-cleanup' )
		);
	}
}

/**
 * Registers the Site Setup Checklist dashboard widget.
 */
function ph_cleanup_register_checklist_widget(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_option( PH_CLEANUP_DISMISSED_OPTION ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'ph_cleanup_checklist',
		__( 'Site Setup Checklist', 'wp-dashboard-cleanup' ),
		'ph_cleanup_render_checklist_widget'
	);
}
add_action( 'wp_dashboard_setup', 'ph_cleanup_register_checklist_widget' );

/**
 * Handles the dismiss action for the Site Setup Checklist widget.
 *
 * Stores a site option so the widget is no longer registered for any user.
 */
function ph_cleanup_handle_dismiss(): void {
	check_admin_referer( 'ph_cleanup_dismiss', 'ph_cleanup_dismiss_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'wp-dashboard-cleanup' ) );
	}

	update_option( PH_CLEANUP_DISMISSED_OPTION, true );
	wp_safe_redirect( admin_url( 'index.php' ) );
	exit;
}
add_action( 'admin_post_ph_cleanup_dismiss', 'ph_cleanup_handle_dismiss' );

/**
 * Handles the restore action from the settings screen.
 *
 * Brings back a Site Setup Checklist widget that was dismissed earlier, so a
 * site that dismissed it can still see checks added in a later release.
 */
function ph_cleanup_handle_restore(): void {
	check_admin_referer( 'ph_cleanup_restore', 'ph_cleanup_restore_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'wp-dashboard-cleanup' ) );
	}

	delete_option( PH_CLEANUP_DISMISSED_OPTION );
	wp_safe_redirect( admin_url( 'index.php' ) );
	exit;
}
add_action( 'admin_post_ph_cleanup_restore', 'ph_cleanup_handle_restore' );

/**
 * Loads the plugin's translations.
 *
 * Runs on init so no translated string is requested before WordPress is ready
 * to serve one.
 */
function ph_cleanup_load_textdomain(): void {
	load_plugin_textdomain(
		'wp-dashboard-cleanup',
		false,
		dirname( plugin_basename( PH_CLEANUP_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'ph_cleanup_load_textdomain' );

add_action( 'wp_dashboard_setup', array( 'PH_Cleanup_Plugin_Updates_Widget', 'register' ) );
add_action( 'wp_dashboard_setup', array( 'PH_Cleanup_Server_Info_Widget', 'register' ) );

add_action( 'plugins_loaded', array( 'PH_Cleanup_Settings', 'register' ) );
add_action( 'plugins_loaded', array( 'PH_Cleanup_Profile_Fields', 'register' ) );
add_action( 'plugins_loaded', array( 'PH_Cleanup_Author_Privacy', 'register' ) );
add_action( 'plugins_loaded', array( 'PH_Cleanup_Fingerprint', 'register' ) );
