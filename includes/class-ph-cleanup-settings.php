<?php
/**
 * Hardening settings: option schema, getters, and the settings screen.
 *
 * @package WpDashboardCleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and renders the optional hardening settings.
 *
 * Every option defaults to false, so an existing install picks up no new
 * behaviour when it updates. Nothing here runs on the front end beyond
 * get_settings(), which reads a single autoloaded option.
 */
class PH_Cleanup_Settings {

	// Database key, deliberately left on its historical name. The 2.0.0 prefix
	// rename covered PHP symbols and hook names, where a wp_ prefix risks a
	// collision with core; an option key carries no such risk, and renaming it
	// would orphan the stored settings on every existing install.
	const OPTION_NAME = 'wp_dashboard_cleanup_hardening';

	const OPTION_GROUP = 'ph_cleanup';
	const PAGE_SLUG    = 'wp-dashboard-cleanup';

	/**
	 * Registers the settings screen hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		if ( defined( 'PH_CLEANUP_FILE' ) ) {
			add_filter(
				'plugin_action_links_' . plugin_basename( PH_CLEANUP_FILE ),
				array( __CLASS__, 'add_action_link' )
			);
		}
	}

	// Option schema.

	/**
	 * Returns every recognised option key with its default value.
	 *
	 * This array is the single source of truth for which keys exist. Both the
	 * getter and the sanitize callback iterate it, so an unrecognised key can
	 * never be read from or written to the option.
	 *
	 * @return array<string, bool>
	 */
	public static function get_defaults(): array {
		return array(
			// Author privacy.
			'disable_author_archives'  => false,
			'block_author_enumeration' => false,
			'restrict_rest_users'      => false,
			'remove_users_sitemap'     => false,
			'strip_oembed_author'      => false,
			// Fingerprint reduction.
			'remove_generator'         => false,
			'remove_rsd_link'          => false,
			'remove_wlwmanifest_link'  => false,
			'remove_shortlink'         => false,
			'disable_xmlrpc'           => false,
			'remove_pingback'          => false,
		);
	}

	/**
	 * Returns the stored settings merged over the defaults.
	 *
	 * Unfiltered, so the settings form always shows what is actually stored.
	 * Behaviour should call is_enabled() instead.
	 *
	 * @return array<string, bool>
	 */
	public static function get_settings(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = array();
		foreach ( self::get_defaults() as $key => $default ) {
			$merged[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : $default;
		}

		return $merged;
	}

	/**
	 * Returns whether a single hardening option is switched on.
	 *
	 * Developers can force an option on or off without changing what the
	 * settings screen displays:
	 *
	 *   add_filter( 'ph_cleanup_hardening_enabled', function( $enabled, $key ) {
	 *       return 'disable_xmlrpc' === $key ? true : $enabled;
	 *   }, 10, 2 );
	 *
	 * @param string $key Option key from get_defaults().
	 * @return bool
	 */
	public static function is_enabled( string $key ): bool {
		$settings = self::get_settings();
		$enabled  = isset( $settings[ $key ] ) ? $settings[ $key ] : false;

		$enabled = (bool) apply_filters( 'ph_cleanup_hardening_enabled', $enabled, $key );

		return (bool) apply_filters_deprecated(
			'wp_dashboard_cleanup_hardening_enabled',
			array( $enabled, $key ),
			'2.0.0',
			'ph_cleanup_hardening_enabled'
		);
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * Iterates the defaults rather than the input, so unknown keys are dropped
	 * and an unchecked box (absent from the POST body) is stored as false.
	 *
	 * @param mixed $input Raw value from the settings form.
	 * @return array<string, bool>
	 */
	public static function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$previous = self::get_settings();
		$clean    = array();

		foreach ( array_keys( self::get_defaults() ) as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		if ( $clean['disable_xmlrpc'] && ! $previous['disable_xmlrpc'] ) {
			add_settings_error(
				self::OPTION_NAME,
				'xmlrpc_disabled',
				__( 'XML-RPC is now disabled. Jetpack and the WordPress mobile apps will not be able to reach this site until you turn it back off.', 'wp-dashboard-cleanup' ),
				'warning'
			);
		}

		return $clean;
	}

	// Settings screen.

	/**
	 * Returns the settings sections and their fields.
	 *
	 * Calls translation functions, so it must not run before init.
	 *
	 * @return array<int, array{id: string, title: string, description: string, fields: array<int, array{key: string, title: string, label: string, description: string}>}>
	 */
	public static function get_sections(): array {
		return array(
			array(
				'id'          => 'ph_cleanup_author',
				'title'       => __( 'Author Privacy', 'wp-dashboard-cleanup' ),
				'description' => __( 'These options reduce what your site publishes about the people who write on it. They change public URLs and API responses, so review your site and your search console after turning any of them on.', 'wp-dashboard-cleanup' ),
				'fields'      => array(
					array(
						'key'         => 'disable_author_archives',
						'title'       => __( 'Author archives', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Disable author archive pages site-wide', 'wp-dashboard-cleanup' ),
						'description' => __( 'Visitors requesting an author archive get your theme\'s 404 page. You can also disable a single author\'s archive from their profile screen.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'block_author_enumeration',
						'title'       => __( 'Author enumeration', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Block requests that look up an author by ID', 'wp-dashboard-cleanup' ),
						'description' => __( 'A request like /?author=1 normally redirects to the author\'s archive URL, which reveals their username. This returns a 404 instead. It has no effect on sites using plain permalinks, where /?author=1 is the real author archive URL.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'restrict_rest_users',
						'title'       => __( 'REST API users', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Hide the users endpoint from logged-out visitors', 'wp-dashboard-cleanup' ),
						'description' => __( 'Removes /wp-json/wp/v2/users for anyone who is not logged in. Logged-in users are unaffected, so the block editor keeps working. Author details will stop appearing in embedded REST responses for anonymous requests.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'remove_users_sitemap',
						'title'       => __( 'Author sitemap', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Leave authors out of the WordPress sitemap', 'wp-dashboard-cleanup' ),
						'description' => __( 'Removes the users section from /wp-sitemap.xml. This also happens automatically when author archives are disabled site-wide.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'strip_oembed_author',
						'title'       => __( 'oEmbed author data', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Remove author name and URL from oEmbed responses', 'wp-dashboard-cleanup' ),
						'description' => __( 'Applies to both the JSON and XML oEmbed output other sites request when they embed your posts.', 'wp-dashboard-cleanup' ),
					),
				),
			),
			array(
				'id'          => 'ph_cleanup_fingerprint',
				'title'       => __( 'Fingerprint Reduction', 'wp-dashboard-cleanup' ),
				'description' => __( 'These options remove version strings and discovery links that identify your WordPress install to automated scanners. They do not change how your site looks.', 'wp-dashboard-cleanup' ),
				'fields'      => array(
					array(
						'key'         => 'remove_generator',
						'title'       => __( 'Generator tag', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Remove the WordPress version from pages and feeds', 'wp-dashboard-cleanup' ),
						'description' => __( 'Removes the generator meta tag from the page head and the generator element from every feed.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'remove_rsd_link',
						'title'       => __( 'RSD link', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Remove the Really Simple Discovery link', 'wp-dashboard-cleanup' ),
						'description' => __( 'Used only by remote publishing clients over XML-RPC. WordPress 6.7 and later no longer output it.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'remove_wlwmanifest_link',
						'title'       => __( 'Windows Live Writer link', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Remove the Windows Live Writer manifest link', 'wp-dashboard-cleanup' ),
						'description' => __( 'Windows Live Writer was retired in 2017. WordPress 6.7 and later no longer output this link.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'remove_shortlink',
						'title'       => __( 'Shortlink', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Remove the shortlink tag and HTTP header', 'wp-dashboard-cleanup' ),
						'description' => __( 'Removes both the shortlink element from the page head and the matching Link header from the response.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'disable_xmlrpc',
						'title'       => __( 'XML-RPC', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Reject all XML-RPC method calls', 'wp-dashboard-cleanup' ),
						'description' => __( 'Check what uses this first. Disabling XML-RPC breaks the Jetpack connection, the official WordPress mobile apps, and any plugin or service that posts to your site remotely. If Jetpack is active, leave this off. Blocking xmlrpc.php at your web server is stronger than blocking it here.', 'wp-dashboard-cleanup' ),
					),
					array(
						'key'         => 'remove_pingback',
						'title'       => __( 'Pingbacks', 'wp-dashboard-cleanup' ),
						'label'       => __( 'Remove the pingback method and X-Pingback header', 'wp-dashboard-cleanup' ),
						'description' => __( 'Pingbacks can be used to bounce traffic off your site at someone else. This leaves the rest of XML-RPC working. Disabling XML-RPC above already covers this.', 'wp-dashboard-cleanup' ),
					),
				),
			),
		);
	}

	/**
	 * Adds the settings page under the Settings menu.
	 */
	public static function add_menu(): void {
		add_options_page(
			__( 'Dashboard Cleanup', 'wp-dashboard-cleanup' ),
			__( 'Dashboard Cleanup', 'wp-dashboard-cleanup' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Adds a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public static function add_action_link( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'wp-dashboard-cleanup' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Registers the option, its sections, and its fields.
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);

		foreach ( self::get_sections() as $section ) {
			add_settings_section(
				$section['id'],
				$section['title'],
				array( __CLASS__, 'render_section' ),
				self::PAGE_SLUG
			);

			foreach ( $section['fields'] as $field ) {
				add_settings_field(
					self::OPTION_NAME . '_' . $field['key'],
					$field['title'],
					array( __CLASS__, 'render_checkbox_field' ),
					self::PAGE_SLUG,
					$section['id'],
					$field
				);
			}
		}
	}

	/**
	 * Renders a section's introductory paragraph.
	 *
	 * @param array<string, mixed> $args Section arguments; 'id' identifies the section.
	 */
	public static function render_section( array $args ): void {
		$id = isset( $args['id'] ) ? (string) $args['id'] : '';

		foreach ( self::get_sections() as $section ) {
			if ( $section['id'] === $id ) {
				printf( '<p>%s</p>', esc_html( $section['description'] ) );
				return;
			}
		}
	}

	/**
	 * Renders one checkbox row.
	 *
	 * Uses the fieldset and screen-reader legend pattern from the core Reading
	 * and Discussion screens rather than label_for, so the checkbox keeps its
	 * own inline label alongside the row heading.
	 *
	 * @param array<string, mixed> $args Field definition from get_sections().
	 */
	public static function render_checkbox_field( array $args ): void {
		$key = isset( $args['key'] ) ? (string) $args['key'] : '';
		if ( '' === $key ) {
			return;
		}

		$settings = self::get_settings();
		$checked  = isset( $settings[ $key ] ) ? $settings[ $key ] : false;
		$id       = 'wpdc-' . $key;
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( (string) $args['title'] ); ?></span></legend>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
					value="1"
					<?php checked( $checked ); ?>
				>
				<?php echo esc_html( (string) $args['label'] ); ?>
			</label>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $args['description'] ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * Renders the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'Everything on this page is off by default. Turn on only what your site needs.', 'wp-dashboard-cleanup' )
		);

		printf( '<form action="%s" method="post">', esc_url( admin_url( 'options.php' ) ) );
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';

		self::render_checklist_restore();

		echo '</div>';
	}

	/**
	 * Renders the button that brings back a dismissed Site Setup Checklist.
	 */
	public static function render_checklist_restore(): void {
		if ( ! get_option( PH_CLEANUP_DISMISSED_OPTION ) ) {
			return;
		}

		printf( '<hr><h2>%s</h2>', esc_html__( 'Site Setup Checklist', 'wp-dashboard-cleanup' ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'The Site Setup Checklist widget was removed from the dashboard. Bring it back to see the current checks, including any added since you dismissed it.', 'wp-dashboard-cleanup' )
		);

		printf(
			'<form method="post" action="%s"><input type="hidden" name="action" value="ph_cleanup_restore">',
			esc_url( admin_url( 'admin-post.php' ) )
		);
		wp_nonce_field( 'ph_cleanup_restore', 'ph_cleanup_restore_nonce' );
		printf(
			'<button type="submit" class="button button-secondary">%s</button></form>',
			esc_html__( 'Restore the checklist widget', 'wp-dashboard-cleanup' )
		);
	}
}
