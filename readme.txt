=== Dashboard Cleanup ===
Contributors: philhoyt
Tags: dashboard, admin, cleanup, widgets
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
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
add_filter( 'wp_dashboard_cleanup_removed_widgets', function( $widgets ) {
    // $widgets is an array of [ 'id' => string, 'context' => string ]
    // Remove an item to keep a widget, or add one to remove additional widgets.
    return $widgets;
} );
</pre>

Modify the setup checklist items:

<pre>
add_filter( 'wp_dashboard_cleanup_checklist_items', function( $items ) {
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

Yes. Use the `wp_dashboard_cleanup_checklist_items` filter to add, remove, or reorder items. See the For Developers section above.

= Can I keep some of the default dashboard widgets? =

Yes. Use the `wp_dashboard_cleanup_removed_widgets` filter to modify the list of widgets that get removed.

== Changelog ==

= 1.0.0 =
* Initial release.