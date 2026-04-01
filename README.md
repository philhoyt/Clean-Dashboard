# Dashboard Cleanup

A WordPress plugin that removes noise from the admin dashboard and guides you through cleaning up a fresh WordPress install. No configuration required.

## Features

**Removes default dashboard widgets:**
- At a Glance
- WordPress Events and News
- Site Health Status
- Activity
- Quick Draft
- Welcome to WordPress panel

**Site Setup Checklist widget** — auto-detects common default WordPress states that should be cleaned up on a new site:
- Delete "Hello World!" post
- Delete "Sample Page"
- Set timezone
- Update site tagline
- Set permalink structure
- Delete Hello Dolly plugin
- Delete default comment
- Allow search engine indexing

Each item links directly to the admin screen where it can be fixed. Once all items are complete, a button lets you remove the widget from your dashboard entirely.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## For Developers

### Modify the removed widgets list

```php
add_filter( 'wp_dashboard_cleanup_removed_widgets', function( $widgets ) {
    // $widgets is an array of [ 'id' => string, 'context' => string ]
    // Remove an item to keep a widget, or add one to remove additional widgets.
    return $widgets;
} );
```

### Modify the setup checklist items

```php
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
```

The `callback` should return `true` when the item is complete and `false` when it still needs attention.

## License

GPL-2.0-or-later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
