<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * Loads the WordPress test suite for integration tests when a test library is
 * available. Otherwise loads only the Composer autoloader so the Brain Monkey
 * unit suite (tests/phpunit/unit/) can run without a WordPress install.
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	// No WordPress test suite available — Brain Monkey unit tests only.
	// The plugin source files guard on ABSPATH and exit() without it.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	}
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
