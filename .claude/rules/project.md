---
paths:
  - "**/*.php"
  - "**/composer.json"
---
# Project: Dashboard Cleanup

- Slug: `wp-dashboard-cleanup`
- Text domain: `wp-dashboard-cleanup`
- PHP minimum: 7.4
- WP minimum: 5.5
- Distribution: GitHub (PUC)
- Main file: `dashboard-cleanup.php`
- Version constant: `PH_CLEANUP_VERSION`
- Global prefix: `ph_cleanup_` / `PH_Cleanup_` / `PH_CLEANUP_` (enforced by phpcs)
- Option keys keep the historical `wp_dashboard_cleanup_` prefix on purpose
