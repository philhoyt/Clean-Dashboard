=== Dashboard Cleanup ===
Contributors: philhoyt
Tags: dashboard, admin, cleanup, widgets
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Removes the default dashboard widgets, adds a setup checklist and update widgets, and offers optional hardening settings

== Description ==

Dashboard Cleanup works without settings. On activation it removes these from the dashboard for every user:

* At a Glance
* WordPress Events and News
* Site Health Status
* Activity
* Quick Draft
* The Welcome to WordPress panel

For administrators it adds three widgets.

Site Setup Checklist checks the leftovers of a fresh install and links each item to the screen where it is fixed:

* Delete the "Hello World!" post
* Delete "Sample Page"
* Set the timezone
* Update the site tagline
* Set a permalink structure
* Delete the Hello Dolly plugin
* Delete the default comment
* Allow search engine indexing
* Remove the default "admin" username
* Set the default role for new users below Administrator
* Serve the site over HTTPS
* Hide PHP errors from visitors
* Enable automatic WordPress updates
* Disable the plugin and theme file editor

When every item is complete, a button removes the widget. A button on the settings screen brings it back, so checks added in a later release can still be seen.

Updates & Cleanup lists plugin updates, theme updates, inactive plugins, and unused themes, with a link to the screen that handles each.

Server Info shows the PHP version (flagged as current, acceptable, or outdated), the database size, and the size of autoloaded options. The figures are cached for a day with a Refresh button. This widget is off by default; each administrator turns it on from their own profile screen.

= Hardening settings =

Settings > Dashboard Cleanup has two groups of options. All of them are off by default.

Author privacy: disable author archives site-wide, return a 404 for author lookups by ID (/?author=1), hide the REST API users endpoint from logged-out visitors, leave authors out of the sitemap, and remove author name and URL from oEmbed responses. Each user's profile screen also has a checkbox to disable that one user's author archive.

Fingerprint reduction: remove the generator tag, the RSD link, the Windows Live Writer link, the shortlink tag and header, XML-RPC, and pingbacks. Disabling XML-RPC breaks Jetpack and the WordPress mobile apps; the settings screen says so next to the option.

= Filters =

Change the list of removed widgets:

<pre>
add_filter( 'ph_cleanup_removed_widgets', function( $widgets ) {
    // $widgets is an array of [ 'id' => string, 'context' => string ].
    // Remove an entry to keep that widget, or add one to remove another widget.
    return $widgets;
} );
</pre>

Change the checklist:

<pre>
add_filter( 'ph_cleanup_checklist_items', function( $items ) {
    // $items is an array of [ 'label' => string, 'callback' => callable, 'link' => string ].
    $items[] = array(
        'label'    => 'My custom check',
        'callback' => function() {
            return get_option( 'my_option' ) === 'expected_value';
        },
        'link'     => admin_url( 'options-general.php' ),
    );
    return $items;
} );
</pre>

The callback returns true when the item is complete and false when it still needs attention.

Three more filters: `ph_cleanup_hardening_enabled` ($enabled, $key) overrides a hardening option in code, `ph_cleanup_profile_fields` adds per-user checkboxes to the profile screen, and `ph_cleanup_block_author_archive` ($blocked, $author_id) decides per author whether the archive is blocked.

Deleting the plugin removes its options, the cached server data, and its user settings, on every site of a multisite network.

== Installation ==

1. Download `dashboard-cleanup.zip` from the [GitHub releases page](https://github.com/philhoyt/Clean-Dashboard/releases).
2. Go to Plugins > Add New > Upload Plugin and upload the zip.
3. Activate from the Plugins screen.

Updates are delivered from GitHub releases through Plugin Update Checker.

== Frequently Asked Questions ==

= Can I add my own items to the checklist? =

Yes. Use the `ph_cleanup_checklist_items` filter to add, remove, or reorder items. See the Filters section above.

= Can I keep some of the default dashboard widgets? =

Yes. Use the `ph_cleanup_removed_widgets` filter to change the list of widgets that are removed.

= Does updating to 2.0.0 change anything on my site? =

The hardening options are all off until you turn one on, and your stored settings are kept. If your own code hooks the plugin's filters, rename them from the `wp_dashboard_cleanup_` prefix to `ph_cleanup_`; the old names still work but log a deprecation notice.

== Changelog ==

= 2.0.1 =
* Fixed the update checker so it always installs the release zip, not whichever file GitHub lists first.
* Rewrote the readme files in plain English.

= 2.0.0 =
* Renamed every global function, constant, and filter from the `wp_` / `WP_` prefix to `ph_cleanup_` / `PH_CLEANUP_`. `wp_` belongs to WordPress core, and a future core symbol using one of the old names would have been a fatal error. Stored settings are not affected.
* Renamed the five public filters. The old names still work but log a deprecation notice: `wp_dashboard_cleanup_removed_widgets` is now `ph_cleanup_removed_widgets`, `wp_dashboard_cleanup_checklist_items` is now `ph_cleanup_checklist_items`, `wp_dashboard_cleanup_hardening_enabled` is now `ph_cleanup_hardening_enabled`, `wp_dashboard_cleanup_profile_fields` is now `ph_cleanup_profile_fields`, and `wp_dashboard_cleanup_block_author_archive` is now `ph_cleanup_block_author_archive`.
* Fixed the "Remove the default admin username" checklist item, which could never be marked complete and so kept the button to dismiss the Site Setup Checklist from appearing.
* Fixed a PHP warning when a request passed an array where an author ID was expected.
* Fixed a corrupted cache entry for the Server Info widget taking down the dashboard instead of being re-read.
* Fixed the file editor check to notice when a host disables file modifications through a filter rather than a constant.
* Added a hardening settings screen under Settings > Dashboard Cleanup. Author privacy options: disable author archives, block author lookups by ID, hide the REST API users endpoint from logged-out visitors, leave authors out of the sitemap, and remove author data from oEmbed responses. Fingerprint reduction options: remove the generator tag, RSD link, Windows Live Writer link, shortlink, XML-RPC, and pingbacks. Every option is off by default.
* Added six security items to the Site Setup Checklist: remove the default "admin" username, set the default role for new users below Administrator, serve the site over HTTPS, hide PHP errors from visitors, enable automatic WordPress updates, and disable the plugin and theme file editor.
* Added a checkbox on each user's profile screen to disable that user's author archive page.
* Added a button on the settings screen that brings back a dismissed Site Setup Checklist, so checks added in a later release can still be seen.
* Added an uninstall routine that removes the plugin's options and user settings when the plugin is deleted, multisite included.
* Added a translation template.
* Changed the Updates & Cleanup widget to scan the plugin and theme directories once per dashboard load instead of twice.
* Tested against WordPress 7.1.

= 1.0.1 =
* Fixed a fatal error on the Plugins screen after install: the 1.0.0 download was missing a bundled library file.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Filter and constant names changed prefix from wp_ to ph_cleanup_. Your settings are kept. If you hook this plugin's filters from your own code, rename them; the old names still work for now but log a deprecation notice.
