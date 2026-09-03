<?php
/**
 * Fingerprint reduction: removes version strings and discovery links.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strips the markers automated scanners use to identify a WordPress install.
 *
 * Every method here is guarded by a setting that defaults to off, so a site
 * that has not visited the settings screen gets no change in output.
 */
class PH_Cleanup_Fingerprint {

	/**
	 * Wires up whichever fingerprint options are switched on.
	 *
	 * Runs on plugins_loaded. Keep this cheap: it executes on every request,
	 * including every front-end one.
	 */
	public static function register(): void {
		if ( PH_Cleanup_Settings::is_enabled( 'remove_generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			// The head tag and the feed element are separate. remove_action
			// handles the first; every feed template calls the_generator()
			// directly, so only the filter covers feeds.
			add_filter( 'the_generator', '__return_empty_string' );
		}

		// Both of these are no-ops on WordPress 6.7 and later, where core
		// stopped registering them. remove_action on an unregistered callback
		// does nothing, so they stay for 5.5 to 6.6 support.
		if ( PH_Cleanup_Settings::is_enabled( 'remove_rsd_link' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( PH_Cleanup_Settings::is_enabled( 'remove_wlwmanifest_link' ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( PH_Cleanup_Settings::is_enabled( 'remove_shortlink' ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			// Core registers wp_shortlink_header at priority 11. Omitting the
			// priority here makes remove_action a silent no-op and the Link
			// header keeps shipping.
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( PH_Cleanup_Settings::is_enabled( 'disable_xmlrpc' ) ) {
			// xmlrpc_enabled alone is not enough: core only consults it in
			// wp_xmlrpc_server::login(), so methods that need no login still
			// run. Emptying the method list rejects everything.
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
		} elseif ( PH_Cleanup_Settings::is_enabled( 'remove_pingback' ) ) {
			add_filter( 'xmlrpc_methods', array( __CLASS__, 'remove_pingback_methods' ) );
			add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
		}
	}

	/**
	 * Drops the pingback methods from the XML-RPC method list.
	 *
	 * @param mixed $methods Registered XML-RPC methods.
	 * @return mixed
	 */
	public static function remove_pingback_methods( $methods ) {
		if ( ! is_array( $methods ) ) {
			return $methods;
		}

		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );

		return $methods;
	}

	/**
	 * Drops the X-Pingback response header.
	 *
	 * @param mixed $headers Response headers.
	 * @return mixed
	 */
	public static function remove_pingback_header( $headers ) {
		if ( ! is_array( $headers ) ) {
			return $headers;
		}

		unset( $headers['X-Pingback'] );

		return $headers;
	}
}
