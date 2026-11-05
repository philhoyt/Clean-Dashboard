<?php
/**
 * Removes everything the plugin stored when it is deleted.
 *
 * @package WpDashboardCleanup
 */

// Only ever run from the WordPress uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes this plugin's options, transients, and user meta for one site.
 */
function ph_cleanup_uninstall_site(): void {
	delete_option( 'wp_dashboard_cleanup_hardening' );
	// Literal rather than PH_CLEANUP_DISMISSED_OPTION: uninstall.php runs
	// standalone, without the main plugin file loaded.
	delete_option( 'wp_dashboard_cleanup_checklist_dismissed' );
	delete_transient( 'ph_cleanup_db_size_cache' );

	// The final true deletes the key for every user in one pass rather than
	// paging through the user table.
	delete_metadata( 'user', 0, 'ph_cleanup_show_server_info', '', true );
	delete_metadata( 'user', 0, 'ph_cleanup_disable_author_archive', '', true );
}

if ( is_multisite() ) {
	// Options are per site, so a network uninstall has to visit each one.
	$ph_cleanup_sites = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 0,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $ph_cleanup_sites as $ph_cleanup_site_id ) {
		switch_to_blog( (int) $ph_cleanup_site_id );
		ph_cleanup_uninstall_site();
		restore_current_blog();
	}
} else {
	ph_cleanup_uninstall_site();
}
