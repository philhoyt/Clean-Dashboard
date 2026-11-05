=== Dashboard Cleanup ===
Contributors: philhoyt
Tags: dashboard, admin, cleanup, widgets
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Removes noise from the WordPress admin dashboard and guides you through cleaning up a fresh WordPress install.

== Description ==

Dashboard Cleanup tidies up the WordPress admin dashboard in two ways.

Removes default dashboard widgets:

* At a Glance
* WordPress Events and News
* Site Health Status
* Activity
* Quick Draft
* Welcome to WordPress panel

Adds a Site Setup Checklist widget that auto-detects common default WordPress states that should be cleaned up on a new site:

* Delete "Hello World!" post
* Delete "Sample Page"
* Set timezone
* Update site tagline
* Set permalink structure
* Delete Hello Dolly plugin
* Delete default comment
* Allow search engine indexing

Each checklist item links directly to the admin screen where it can be fixed. Once all items are complete, a button lets you remove the widget from your dashboard entirely.

= For Developers =

Modify the removed widgets list:

<pre>
add_filter( 'ph_cleanup_removed_widgets', function( $widgets ) {
    // $widgets is an array of [ 'id' => string, 'context' => string ]
    // Remove an item to keep a widget, or add one to remove additional widgets.
    return $widgets;
} );
</pre>

Modify the setup checklist items:

<pre>
add_filter( 'ph_cleanup_checklist_items', function( $items ) {
    // $items is an array of [ 'label' => string, 'callback' => callable, 'link' => string ]
    // Add custom items, remove existing ones, or reorder the list.
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

The `callback` should return `true` when the item is complete and `false` when it still needs attention.

== Installation ==

1. Download the latest `dashboard-cleanup.zip` from the [GitHub releases page](https://github.com/philhoyt/Clean-Dashboard/releases).
2. Go to **Plugins → Add New → Upload Plugin** and upload the zip file.
3. Activate through the Plugins screen.

== Frequently Asked Questions ==

= Can I add my own items to the checklist? =

Yes. Use the `ph_cleanup_checklist_items` filter to add, remove, or reorder items. See the For Developers section above.

= Can I keep some of the default dashboard widgets? =

Yes. Use the `ph_cleanup_removed_widgets` filter to modify the list of widgets that get removed.

== Upgrade Notice ==

= 2.0.0 =
Filter and constant names changed prefix from wp_ to ph_cleanup_. Your settings are kept. If you hook this plugin's filters from your own code, rename them — the old names still work for now but log a deprecation notice.

== Changelog ==

= 2.0.0 =
* Change: Renamed every global function, constant, and filter from the `wp_` / `WP_` prefix to `ph_cleanup_` / `PH_CLEANUP_`. `wp_` belongs to WordPress core, and a future core symbol using one of the old names would have been a fatal error.
* Change: The five public filters were renamed. The old names still work and are honoured, but log a deprecation notice: `wp_dashboard_cleanup_removed_widgets` is now `ph_cleanup_removed_widgets`, `wp_dashboard_cleanup_checklist_items` is now `ph_cleanup_checklist_items`, `wp_dashboard_cleanup_hardening_enabled` is now `ph_cleanup_hardening_enabled`, `wp_dashboard_cleanup_profile_fields` is now `ph_cleanup_profile_fields`, and `wp_dashboard_cleanup_block_author_archive` is now `ph_cleanup_block_author_archive`.
* Fix: The "Remove the default admin username" checklist item could never be marked complete, which also meant the button to dismiss the Site Setup Checklist never appeared.
* Fix: A request that passed an array where an author ID was expected could log a PHP warning.
* Fix: A corrupted cache entry for the Server Info widget could take down the dashboard instead of being re-read.
* Fix: The file editor check now notices when a host disables file modifications through a filter rather than a constant.
* Add: A hardening settings screen under Settings > Dashboard Cleanup. Author privacy options can disable author archives, block author lookups by ID, hide the REST API users endpoint from logged-out visitors, leave authors out of the sitemap, and remove author data from oEmbed responses. Fingerprint reduction options can remove the generator tag, RSD link, Windows Live Writer link, shortlink, XML-RPC, and pingbacks. Every option is off by default, so updating changes nothing until you turn one on.
* Add: Six security items in the Site Setup Checklist: remove the default "admin" username, set the default role for new users below Administrator, serve the site over HTTPS, hide PHP errors from visitors, enable automatic WordPress updates, and disable the plugin and theme file editor.
* Add: A checkbox on each user's profile screen to disable that user's author archive page.
* Add: A button on the settings screen that brings back a dismissed Site Setup Checklist, so checks added in a later release can still be seen.
* Add: An uninstall routine that removes the plugin's options and user settings when the plugin is deleted, multisite included.
* Add: A translation template so the plugin can be translated.
* Change: The Updates & Cleanup widget no longer scans the plugin and theme directories twice on every dashboard load.
* Change: Tested against WordPress 7.1.

Your stored settings are not affected by the rename.

= 1.0.1 =
* Fix: The 1.0.0 download was missing a bundled library file, which caused a fatal error on the Plugins screen after install.

= 1.0.0 =
* Initial release.