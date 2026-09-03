<?php
/**
 * Author privacy: archive blocking and username enumeration defences.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides the people who write on a site from anonymous visitors.
 *
 * Author archives can be switched off site-wide or for one user at a time. The
 * related options cover the other places WordPress publishes author data: the
 * REST API, the sitemap, and oEmbed responses.
 *
 * This is the plugin's only front-end code, so every callback here guards its
 * arguments before touching them.
 */
class PH_Cleanup_Author_Privacy {

	const USER_META_KEY = 'ph_cleanup_disable_author_archive';

	/**
	 * Wires up whichever author privacy options are switched on.
	 *
	 * Runs on plugins_loaded, which is early enough for the sitemap provider
	 * filter to beat wp_sitemaps_get_server() on init.
	 */
	public static function register(): void {
		// The per-author checkbox is always available, so an author can opt out
		// even when the site-wide toggle is off.
		add_filter( 'ph_cleanup_profile_fields', array( __CLASS__, 'register_profile_field' ) );

		// Priority 0 puts this ahead of redirect_canonical at priority 10, so a
		// blocked ?author=N request 404s before core can redirect to a URL
		// containing the username.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_block_author_archive' ), 0 );

		add_filter( 'author_link', array( __CLASS__, 'filter_author_link' ), 10, 2 );

		if ( PH_Cleanup_Settings::is_enabled( 'restrict_rest_users' ) ) {
			add_filter( 'rest_endpoints', array( __CLASS__, 'filter_rest_endpoints' ) );
		}

		// A disabled archive makes get_author_posts_url() return the home page,
		// so leaving the users sitemap registered would fill it with duplicate
		// home URLs. Remove it whenever archives are off site-wide.
		if ( PH_Cleanup_Settings::is_enabled( 'remove_users_sitemap' )
			|| PH_Cleanup_Settings::is_enabled( 'disable_author_archives' ) ) {
			add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'filter_sitemaps_provider' ), 10, 2 );
		} else {
			// Otherwise keep the sitemap but leave out individually opted-out authors.
			add_filter( 'wp_sitemaps_users_query_args', array( __CLASS__, 'filter_sitemaps_users_query_args' ) );
		}

		if ( PH_Cleanup_Settings::is_enabled( 'strip_oembed_author' ) ) {
			add_filter( 'oembed_response_data', array( __CLASS__, 'filter_oembed_response_data' ) );
		}
	}

	// Archive blocking.

	/**
	 * Decides whether a request for an author archive should be blocked.
	 *
	 * Pure function: no WordPress calls, so the decision can be tested against
	 * a truth table without a running site.
	 *
	 * @param bool $is_author_query   Whether this request is an author archive.
	 * @param bool $global_disabled   Whether archives are off site-wide.
	 * @param bool $author_disabled   Whether this author opted out individually.
	 * @param bool $is_enumeration    Whether the request identified the author by ID.
	 * @param bool $block_enumeration Whether ID lookups should be blocked.
	 * @return bool
	 */
	public static function should_block(
		bool $is_author_query,
		bool $global_disabled,
		bool $author_disabled,
		bool $is_enumeration,
		bool $block_enumeration
	): bool {
		if ( ! $is_author_query ) {
			return false;
		}

		if ( $global_disabled || $author_disabled ) {
			return true;
		}

		return $is_enumeration && $block_enumeration;
	}

	/**
	 * Returns whether the request looked the author up by numeric ID.
	 *
	 * On a site using plain permalinks, /?author=1 is the real author archive
	 * URL, so blocking it there would break archives the owner never asked to
	 * disable. Reading query vars rather than $_GET catches every shape of the
	 * request, including feed variants.
	 *
	 * @return bool
	 */
	public static function is_enumeration_request(): bool {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return false;
		}

		$author      = get_query_var( 'author' );
		$author_name = get_query_var( 'author_name' );

		// WP::parse_request() keeps array values for public query vars, so a
		// request like /?author[]=1 lands here as an array. Casting one to
		// string would emit an Array to string conversion warning.
		if ( ! is_scalar( $author ) || ! is_scalar( $author_name ) ) {
			return false;
		}

		return '' !== (string) $author && '' === (string) $author_name;
	}

	/**
	 * Returns whether a specific author opted out of having an archive.
	 *
	 * @param int $author_id User ID.
	 * @return bool
	 */
	public static function is_author_archive_disabled( int $author_id ): bool {
		if ( $author_id <= 0 ) {
			return false;
		}

		return (bool) get_user_meta( $author_id, self::USER_META_KEY, true );
	}

	/**
	 * Turns a blocked author archive request into a 404.
	 *
	 * Runs on template_redirect, which fires before template-loader.php picks a
	 * template. Setting the 404 flag here means the theme's 404 template is
	 * chosen on the next lines, so the response is a real 404 page rather than
	 * an empty archive with a 200 status.
	 */
	public static function maybe_block_author_archive(): void {
		if ( ! is_author() ) {
			return;
		}

		$author_id = (int) get_query_var( 'author' );
		if ( $author_id <= 0 ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				$author_id = (int) $author->ID;
			}
		}

		$blocked = self::should_block(
			true,
			PH_Cleanup_Settings::is_enabled( 'disable_author_archives' ),
			self::is_author_archive_disabled( $author_id ),
			self::is_enumeration_request(),
			PH_Cleanup_Settings::is_enabled( 'block_author_enumeration' )
		);

		/**
		 * Filters whether an author archive request is blocked.
		 *
		 * @param bool $blocked   Whether to return a 404.
		 * @param int  $author_id The author being requested, or 0 if unknown.
		 */
		$blocked = (bool) apply_filters( 'ph_cleanup_block_author_archive', $blocked, $author_id );
		$blocked = (bool) apply_filters_deprecated(
			'wp_dashboard_cleanup_block_author_archive',
			array( $blocked, $author_id ),
			'2.0.0',
			'ph_cleanup_block_author_archive'
		);

		if ( ! $blocked ) {
			return;
		}

		self::send_404();
	}

	/**
	 * Flags the current request as a 404 without ending it.
	 *
	 * No exit call: template-loader.php still needs to render the 404 template.
	 */
	public static function send_404(): void {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	// Author data elsewhere.

	/**
	 * Points author links at the home page when the archive is unavailable.
	 *
	 * Themes call the_author_posts_link(), which builds an anchor whatever this
	 * returns, so an empty string would leave a dead link in the markup.
	 *
	 * @param mixed $link      The author archive URL.
	 * @param mixed $author_id The author's user ID.
	 * @return mixed
	 */
	public static function filter_author_link( $link, $author_id = 0 ) {
		if ( PH_Cleanup_Settings::is_enabled( 'disable_author_archives' )
			|| self::is_author_archive_disabled( (int) $author_id ) ) {
			return home_url( '/' );
		}

		return $link;
	}

	/**
	 * Removes the users endpoints for logged-out visitors.
	 *
	 * Unregistering the routes also drops them from the /wp/v2 index, which
	 * rest_pre_dispatch would not do.
	 *
	 * @param mixed $endpoints Registered REST routes.
	 * @return mixed
	 */
	public static function filter_rest_endpoints( $endpoints ) {
		if ( ! is_array( $endpoints ) ) {
			return $endpoints;
		}

		// Any logged-in user keeps core behaviour, which is what stops this
		// from breaking the block editor's author controls.
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		// rest_do_request() dispatches through the same server with no current
		// user, so internal callers need an exemption.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			$route = (string) $route;

			// Already returns 401 for anonymous requests, and clients use it to
			// detect whether they are logged in.
			if ( '/wp/v2/users/me' === $route ) {
				continue;
			}

			// Matching by prefix rather than by core's exact route regex, which
			// could change and turn this into a silent no-op.
			if ( '/wp/v2/users' === $route || 0 === strpos( $route, '/wp/v2/users/' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Stops the users sitemap from being registered.
	 *
	 * WP_Sitemaps_Registry::add_provider() rejects anything that is not a
	 * provider object, so returning false removes the section entirely.
	 *
	 * @param mixed  $provider The sitemap provider.
	 * @param string $name     The provider name.
	 * @return mixed
	 */
	public static function filter_sitemaps_provider( $provider, $name = '' ) {
		return ( 'users' === $name ) ? false : $provider;
	}

	/**
	 * Leaves individually opted-out authors out of the users sitemap.
	 *
	 * @param mixed $args WP_User_Query arguments.
	 * @return mixed
	 */
	public static function filter_sitemaps_users_query_args( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		// The NOT EXISTS branch compiles to a LEFT JOIN ... IS NULL against
		// wp_usermeta. Accepted: this runs only when /wp-sitemap-users-1.xml is
		// requested, and only while per-author opt-outs are in play.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => self::USER_META_KEY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::USER_META_KEY,
				'value'   => '1',
				'compare' => '!=',
			),
		);

		return $args;
	}

	/**
	 * Removes author details from oEmbed responses.
	 *
	 * Core applies this filter from get_oembed_response_data() for both the
	 * JSON and XML output, so one filter covers both.
	 *
	 * @param mixed $data The oEmbed response.
	 * @return mixed
	 */
	public static function filter_oembed_response_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}

	// Profile field.

	/**
	 * Adds the per-author checkbox to the shared profile section.
	 *
	 * @param mixed $fields Registered profile fields.
	 * @return mixed
	 */
	public static function register_profile_field( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$description = __( 'Requests for this author\'s archive return a 404.', 'wp-dashboard-cleanup' );
		if ( PH_Cleanup_Settings::is_enabled( 'disable_author_archives' ) ) {
			$description .= ' ' . __( 'Author archives are currently disabled site-wide in Settings, Dashboard Cleanup.', 'wp-dashboard-cleanup' );
		}

		$fields[] = array(
			'key'         => self::USER_META_KEY,
			'title'       => __( 'Author Archive', 'wp-dashboard-cleanup' ),
			'label'       => __( 'Disable this user\'s author archive page', 'wp-dashboard-cleanup' ),
			'description' => $description,
			'visible'     => array( __CLASS__, 'profile_field_visible' ),
		);

		return $fields;
	}

	/**
	 * Shows the checkbox for anyone who could have an author archive.
	 *
	 * Looser than the Server Info widget's admin-only gate, because any user
	 * with published content has an archive. The short-circuit keeps the
	 * uncached count_user_posts() query off the common path.
	 *
	 * @param WP_User $user The user whose profile is being viewed.
	 * @return bool
	 */
	public static function profile_field_visible( WP_User $user ): bool {
		return user_can( $user, 'edit_posts' ) || 0 < (int) count_user_posts( $user->ID );
	}
}
