<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * Loads the WordPress test suite environment when running integration tests.
 * When no WordPress test library is present (e.g. plain unit tests), it returns
 * early so the suite can still run without a full WordPress install.
 */

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	// No WordPress test suite available — unit tests only.
	return;
}

require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually loads the plugin into the test environment.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__, 2 ) . '/dashboard-cleanup.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $wp_tests_dir . '/includes/bootstrap.php';
