# Dashboard Cleanup

[![CI](https://github.com/philhoyt/Clean-Dashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/philhoyt/Clean-Dashboard/actions/workflows/ci.yml)

A WordPress plugin that removes the default dashboard widgets, adds a site setup checklist and update widgets for administrators, and offers optional hardening settings.

## Requirements

- WordPress 5.5 or later
- PHP 7.4 or later

## Installation

Download `dashboard-cleanup.zip` from the [releases page](https://github.com/philhoyt/Clean-Dashboard/releases) and install it from Plugins > Add New > Upload Plugin, or copy the plugin folder into `wp-content/plugins/`. Updates are offered from GitHub releases through Plugin Update Checker.

No settings are needed. Activation removes the widgets and adds the checklist; the hardening options are all off until turned on.

## Usage

### Removed widgets

On `wp_dashboard_setup` the plugin removes At a Glance (`dashboard_right_now`), WordPress Events and News (`dashboard_primary`), Site Health Status (`dashboard_site_health`), Activity (`dashboard_activity`), Quick Draft (`dashboard_quick_press`), and the Welcome panel, for every user.

### Widgets added for administrators

All three need `manage_options`.

Site Setup Checklist: eight setup checks (Hello World post, Sample Page, timezone, tagline, permalinks, Hello Dolly, default comment, search engine indexing) and six security checks (default "admin" username, default role below Administrator, HTTPS, PHP errors hidden, automatic core updates, file editor disabled). Each item links to the screen where it is fixed. When all pass, a button dismisses the widget; Settings > Dashboard Cleanup has a button to restore it.

Updates & Cleanup: plugin updates, theme updates, inactive plugins, and unused themes. The plugin and theme directories are scanned once per dashboard load.

Server Info: PHP version against a 7.4 minimum and 8.2 recommendation, database size, and autoloaded option size. The data is cached in a transient for a day; a Refresh button clears it. The widget is off by default and each administrator enables it from their own profile screen (user meta `ph_cleanup_show_server_info`).

### Hardening settings

Settings > Dashboard Cleanup, all off by default:

- Author privacy: disable author archives site-wide (404), return 404 for `/?author=N` lookups (no effect with plain permalinks), hide `/wp-json/wp/v2/users` from logged-out visitors, drop the users provider from `wp-sitemap.xml`, and strip author name and URL from oEmbed responses. Each user's profile screen also has a checkbox that disables only that user's archive (user meta `ph_cleanup_disable_author_archive`).
- Fingerprint reduction: remove the generator tag, RSD link, Windows Live Writer manifest link, shortlink tag and `Link` header, all XML-RPC methods, and the pingback method and `X-Pingback` header. Turning off XML-RPC breaks Jetpack and the WordPress mobile apps; the screen shows a notice when it is enabled.

Options are stored in one array under `wp_dashboard_cleanup_hardening`; the checklist dismissal under `wp_dashboard_cleanup_checklist_dismissed`. Both option names keep their 1.x spelling on purpose so existing installs keep their state.

### Filters

```php
// Change which widgets are removed. Each entry is [ 'id' => string, 'context' => string ].
add_filter( 'ph_cleanup_removed_widgets', function( $widgets ) {
    return $widgets;
} );

// Add, remove, or reorder checklist items. The callback returns true when the item is complete.
add_filter( 'ph_cleanup_checklist_items', function( $items ) {
    $items[] = array(
        'label'    => 'My custom check',
        'callback' => function() {
            return get_option( 'my_option' ) === 'expected_value';
        },
        'link'     => admin_url( 'options-general.php' ),
    );
    return $items;
} );
```

- `ph_cleanup_hardening_enabled( bool $enabled, string $key )`: override a hardening option in code.
- `ph_cleanup_profile_fields( array $fields )`: add per-user checkboxes to the profile screen; each field is `[ 'key', 'title', 'label', 'description', 'visible' ]`.
- `ph_cleanup_block_author_archive( bool $blocked, int $author_id )`: decide per author whether the archive is blocked.

The 1.x names (`wp_dashboard_cleanup_*`) still work through `apply_filters_deprecated()` and log a deprecation notice.

### Uninstall

`uninstall.php` deletes both options, the `ph_cleanup_db_size_cache` transient, and the two user meta keys, on every site when the network is multisite.

## Development

There is no JavaScript build; the plugin is PHP only.

```bash
composer install        # also copies Plugin Update Checker into lib/ via scripts/copy-puc.php
composer run lint       # phpcs, WordPress Coding Standards
composer run lint:fix   # phpcbf
composer run analyse    # PHPStan
composer run test       # PHPUnit with Brain\Monkey stubs, tests/phpunit/unit/
composer run i18n       # regenerates languages/wp-dashboard-cleanup.pot (needs WP-CLI)
bash bin/build-zip.sh   # writes dist/dashboard-cleanup.zip, honouring .distignore
```

The tests stub WordPress with Brain\Monkey; no WordPress install is needed. CI (`.github/workflows/ci.yml`) runs phpcs, PHPStan, and PHPUnit on PHP 8.4 (PHPUnit 13 needs 8.4), and runs `php -l` over every shipped PHP file on 7.4 and 8.4 to hold the declared minimum.

## Releases

Bump the version in `dashboard-cleanup.php` (header and `PH_CLEANUP_VERSION`) and the `Stable tag` in `readme.txt`, add the changelog entry to `readme.txt`, then push a `v` tag:

```bash
git tag v2.0.0 && git push origin v2.0.0
```

The release workflow (`.github/workflows/release.yml`) runs `bin/build-zip.sh`, pulls the matching `= X.Y.Z =` section out of `readme.txt` as the release notes, and attaches `dist/dashboard-cleanup.zip` and `readme.txt` to the GitHub release. `build-zip.sh` fails the build if a bundled Plugin Update Checker file is missing from the staged tree. Use a lowercase `v`: Plugin Update Checker strips the prefix case-sensitively.

## Limitations

- Widgets are removed for every role; there is no per-role setting, only the `ph_cleanup_removed_widgets` filter.
- Blocking `/?author=N` has no effect on sites with plain permalinks, where that is the real archive URL.
- Disabling XML-RPC here is weaker than blocking `xmlrpc.php` at the web server, and it breaks Jetpack and the mobile apps.
- The Server Info database figures are cached for a day; use Refresh after large changes.

## License

GPL-2.0-or-later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
