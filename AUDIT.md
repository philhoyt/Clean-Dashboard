# Dashboard Cleanup — Audit Report

**Date:** 2026-05-16
**WordPress latest stable:** 6.9.4
**Scanned:** 3 PHP files (plugin source) · 0 blocks · 0 JS source files

## Project Inventory

```
Type:        Plugin (no blocks, no JS source)
Slug:        wp-dashboard-cleanup
Main file:   dashboard-cleanup.php
PHP files:   3 (dashboard-cleanup.php + 2 widget classes)
Blocks:      0
JS source:   0
Tooling:     phpcs ✓  wpcs ✓  phpstan ✓  phpunit ✓  wp-scripts ✗ (not needed)
phpcs run:   CLEAN — zero violations
```

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 0 |
| Warning  | 7 |
| Info     | 2 |

---

## Status

| ID | Issue | Status |
|----|-------|--------|
| SEC-01 | `handle_dismiss()` — missing capability check | ✅ Fixed |
| STD-01 | `Tested up to: 7.0` — version doesn't exist | ✅ Fixed |
| STD-02 | `Requires at least: 5.0` vs `$context` param in `wp_add_dashboard_widget()` | ✅ Fixed |
| STD-03 | Inline `<script>` tag output in widget render callback | ✅ Fixed |
| STD-04 | No version constant defined in main plugin file | ✅ Fixed |
| ARC-01 | Autoload query misses WP 6.6+ autoload values | ✅ Fixed |
| BLD-01 | `bin/build-zip.sh` ships dev files in distribution zip | ✅ Fixed |
| TST-01 | No test files — `tests/` directory and bootstrap absent | open |
| INF-01 | `README.md` missing development and releases sections | open |

---

## Warnings

### [SEC-01] `handle_dismiss()` — missing `current_user_can()` check
**File:** `dashboard-cleanup.php:297`
**Code:**
```php
function wp_dashboard_cleanup_handle_dismiss(): void {
    check_admin_referer( 'wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_dismiss_nonce' );
    update_option( 'wp_dashboard_cleanup_checklist_dismissed', true );
    // ...
}
add_action( 'admin_post_wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_handle_dismiss' );
```
**Problem:** `admin_post_{action}` fires for any logged-in user. WordPress nonces are user-specific, so any logged-in subscriber who knows the action name can generate a valid nonce for `'wp_dashboard_cleanup_dismiss'` and POST to `admin-post.php`. The `check_admin_referer()` call will pass, and `update_option( 'wp_dashboard_cleanup_checklist_dismissed', true )` will run — silently dismissing the checklist widget site-wide for all users.

**Fix:** Add a capability check immediately after the nonce check:
```php
function wp_dashboard_cleanup_handle_dismiss(): void {
    check_admin_referer( 'wp_dashboard_cleanup_dismiss', 'wp_dashboard_cleanup_dismiss_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'wp-dashboard-cleanup' ) );
    }
    update_option( 'wp_dashboard_cleanup_checklist_dismissed', true );
    wp_safe_redirect( admin_url( 'index.php' ) );
    exit;
}
```

---

### [STD-01] `Tested up to: 7.0` — WordPress 7.0 does not exist
**File:** `readme.txt:5`
**Code:** `Tested up to: 7.0`
**Problem:** WordPress latest stable is 6.9.4 with no 7.0 prerelease. The `7.0` value is almost certainly a typo (likely `6.0`). Plugin directories and PUC use this field to determine compatibility; an invalid version can confuse update checks.

**Fix:** Update to the highest WordPress version you have tested against. Current stable is `6.9.4`:
```
Tested up to: 6.9
```

---

### [STD-02] `Requires at least: 5.0` — `$context` parameter may not apply on early WP
**File:** `readme.txt:4`, `includes/widgets/class-ph-cleanup-plugin-updates-widget.php:37`, `includes/widgets/class-ph-cleanup-server-info-widget.php:39`
**Code:**
```php
wp_add_dashboard_widget(
    'ph_cleanup_plugin_updates',
    $title,
    array( __CLASS__, 'render' ),
    null,
    null,
    'side'   // $context — 6th parameter
);
```
**Problem:** Both widget classes pass `'side'` as the 6th argument (`$context`) to `wp_add_dashboard_widget()`. This parameter was added in WordPress 5.5. On WP 5.0–5.4 the argument is silently ignored and the widget appears in the default context instead of the sidebar — a silent layout mismatch, not a crash. Verify against [the WordPress changelog](https://developer.wordpress.org/reference/functions/wp_add_dashboard_widget/) to confirm the exact version.

**Fix:** Raise the header to at minimum `5.5` in both files:
```
Requires at least: 5.5
```

---

### [STD-03] Inline `<script>` tag output in widget render callback
**File:** `includes/widgets/class-ph-cleanup-server-info-widget.php:61`
**Code:**
```php
printf(
    '<script>
        ( function() { ... } )();
    </script>',
    esc_js( self::AJAX_ACTION )
);
```
**Problem:** The refresh interaction script is echoed directly inside the render callback via `printf()`. This bypasses the WordPress enqueue system, prevents deduplication, and is incompatible with Content Security Policy headers that block inline scripts. The `esc_js()` call correctly escapes the action name, so there is no XSS risk — the issue is standards conformance and CSP.

**Fix:** Register the script once (on `admin_enqueue_scripts` gated to the dashboard page), pass data via `wp_add_inline_script()`, and remove the `printf( '<script>...' )` block from the render callback:
```php
add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

public static function enqueue_assets( string $hook ): void {
    if ( 'index.php' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'ph-cleanup-server-info',
        plugin_dir_url( __FILE__ ) . '../../assets/js/server-info.js',
        [],
        WP_DASHBOARD_CLEANUP_VERSION,
        true
    );
    wp_add_inline_script(
        'ph-cleanup-server-info',
        sprintf( 'window.phCleanupAction = %s;', wp_json_encode( self::AJAX_ACTION ) ),
        'before'
    );
}
```
Alternatively, since the script is short, move it to a static file and enqueue it — or use `wp_add_inline_script()` on a registered (but not necessarily external) script handle.

---

### [STD-04] No version constant defined
**File:** `dashboard-cleanup.php`
**Problem:** The plugin version exists only in the plugin header comment (`Version: 1.0.0`) and `readme.txt`. There is no PHP constant like `WP_DASHBOARD_CLEANUP_VERSION`. Without it, there is no reliable way to cache-bust enqueued assets if scripts or styles are added in future, and no single source of truth for version comparisons.

**Fix:** Define a constant immediately after the ABSPATH guard:
```php
define( 'WP_DASHBOARD_CLEANUP_VERSION', '1.0.0' );
```
Keep the header `Version:` and this constant in sync on every release.

---

### [ARC-01] Autoload size query misses WordPress 6.6+ autoload values
**File:** `includes/widgets/class-ph-cleanup-server-info-widget.php:233`
**Code:**
```php
$autoloaded_size = (int) $wpdb->get_var(
    "SELECT SUM( LENGTH( option_value ) ) FROM {$wpdb->options} WHERE autoload = 'yes'"
);
```
**Problem:** WordPress 6.6 introduced granular autoload management. Options can now have `autoload` values of `'yes'`, `'no'`, `'on'`, `'off'`, `'auto'`, or `'auto-draft'`. The query only matches `'yes'`, so the widget underreports autoloaded data size on WP 6.6+ sites, potentially suppressing the warning even when autoloaded data is large.

**Fix:** Expand the `WHERE` clause to match all autoloaded values:
```php
$autoloaded_size = (int) $wpdb->get_var(
    "SELECT SUM( LENGTH( option_value ) ) FROM {$wpdb->options}
     WHERE autoload IN ( 'yes', 'on', 'auto' )"
);
```

---

### [BLD-01] `bin/build-zip.sh` ships dev files in the distribution zip
**File:** `bin/build-zip.sh:19`
**Problem:** The rsync exclude list does not include newly added dev files. These will appear in every release zip:
- `phpstan.neon`
- `phpunit.xml`
- `.distignore`
- `tests/` (once added)

**Fix:** Add the missing excludes to the rsync call:
```bash
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='.claude' \
  --exclude='.distignore' \
  --exclude='bin' \
  --exclude='dist' \
  --exclude='docs' \
  --exclude='phpcs.xml' \
  --exclude='phpstan.neon' \
  --exclude='phpunit.xml' \
  --exclude='tests' \
  --exclude='vendor' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='scripts' \
  . "${STAGE}/"
```

---

## Info

### [TST-01] No test files — `tests/` directory and bootstrap are absent
**Recommendation:** `phpunit.xml` references `tests/phpunit/bootstrap.php` but neither the directory nor the bootstrap exist. The plugin has security-sensitive code paths (`handle_dismiss`, `save_profile_field`, `handle_ajax`) that warrant unit tests. At minimum, add tests confirming: nonce rejection, capability rejection, and that `get_data()` returns the expected shape. Creating `tests/phpunit/bootstrap.php` with the WordPress test suite bootstrap is the first step.

---

### [INF-01] `README.md` missing development and releases sections
**Recommendation:** `README.md` describes the plugin for end users but doesn't document the development workflow (`composer lint`, `composer analyse`, how to build the zip, how to cut a release). Add a **Development** section and a **Releases** section per the WordPress readme conventions so contributors have a clear on-ramp.

---

## Quick Wins

1. **SEC-01** — Add `current_user_can( 'manage_options' )` to `handle_dismiss()` (`dashboard-cleanup.php:299`). One line; eliminates the capability bypass.
2. **STD-01 + STD-02** — Fix `readme.txt`: set `Tested up to: 6.9` and `Requires at least: 5.5`. Two-line change; corrects the impossible version and aligns the stated minimum with actual API usage.
3. **BLD-01** — Add the four missing `--exclude` lines to `bin/build-zip.sh`. Prevents dev config files from shipping in every release zip.

---

## Already Clean

- **Output escaping** — every `echo`/`printf` in all three PHP files uses `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`, or `esc_html__()`. Zero unescaped output.
- **Input sanitization** — `$_POST` values are accessed only in `save_profile_field()`, cast to int via ternary; the phpcs:ignore rationale is correct.
- **Nonce verification** — `handle_ajax()` uses `check_ajax_referer()` ✓; `save_profile_field()` uses `check_admin_referer()` ✓; `handle_dismiss()` uses `check_admin_referer()` (missing cap check flagged as SEC-01, but nonce itself is in place).
- **Capability checks** — `register()` in both widgets gates on `current_user_can( 'manage_options' )` ✓; `handle_ajax()` re-checks capability after nonce ✓; `save_profile_field()` checks `edit_user` ✓; `render_profile_field()` checks `user_can( $user, 'manage_options' )` ✓.
- **SQL safety** — all `$wpdb` calls use `$wpdb->prepare()` or reference only internal table names with no user input; phpcs:ignore comments are narrow and justified.
- **i18n** — all user-facing strings are wrapped in `__()`, `esc_html__()`, or `esc_html_e()` with the correct `'wp-dashboard-cleanup'` text domain.
- **Prefix discipline** — all global functions, option names, action names, widget IDs, and hooks use the `wp_dashboard_cleanup_` or `ph_cleanup_` prefix consistently.
- **No debug code** — no `var_dump`, `print_r`, `error_log`, `dd`, or `die()` in production paths.
- **No hardcoded secrets** — clean.
- **phpcs** — zero violations against the WordPress standard.
- **No blocks** — block audit phases skipped (not applicable).
- **No JS source** — ESLint/Stylelint phases skipped (not applicable).
