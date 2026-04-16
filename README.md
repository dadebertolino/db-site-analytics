# DB Site Analytics

**Server-side WordPress visit tracking — no cookies, no external services, no tracking scripts.**

All data stays in your WordPress database. GDPR compliant by design.

---

## Features

### Tracking
- **Server-side** via `template_redirect` hook — no scripts injected in the frontend, no performance impact, invisible to ad-blockers
- **No IP address stored** — daily anonymous non-reversible visitor hash (SHA-256 + daily rotating salt)
- **No cookies** of any kind
- **Bot/crawler filter** — 30+ User-Agent patterns detected automatically
- Lightweight User-Agent parsing for device, browser and OS (no external library)
- Configurable exclusions for user roles, URL paths and logged-in users

### Dashboard
- Visit chart (pageviews + unique visitors) with custom date filter
- KPIs: today, last 7 days, selected period
- **Period comparison** with percentage change and mini-bars
- Top 10 pages and Top 10 referrers
- Device breakdown (donut), browser and OS (inline bars)
- WordPress dashboard widget with quick summary
- CSV export for pageviews, downloads and events (UTF-8 BOM, Excel-compatible)

### Download tracking *(optional)*
- Intercepts clicks on file links with configurable extensions
- ~1KB frontend script (sendBeacon + XHR fallback)
- Dedicated admin page with top downloaded files, unique visitors and source page

### Event tracking *(optional)*
- **Outbound links** — clicks on links leaving the site
- **Scroll depth** — thresholds at 25%, 50%, 75%, 100%
- Dedicated admin page with outbound link table and scroll depth bars
- CSV export for events

### REST API *(authenticated, requires `manage_options`)*
```
GET /wp-json/dbsa/v1/stats
GET /wp-json/dbsa/v1/stats/pages
GET /wp-json/dbsa/v1/stats/referrers
GET /wp-json/dbsa/v1/stats/devices
GET /wp-json/dbsa/v1/stats/daily
```

### Visit counter shortcode
```
[dbsa_views]                         current page views (last 30 days)
[dbsa_views period="7"]              last 7 days
[dbsa_views page_id="123"]           specific page
[dbsa_views type="visitors"]         unique visitors
[dbsa_views format="compact"]        "1,234 visits"
[dbsa_views format="number"]         number only
```

---

## Privacy & GDPR

This plugin **does not require consent** under GDPR/ePrivacy because:

- ❌ No IP addresses collected (never stored, not even anonymised)
- ❌ No cookies of any kind
- ❌ No data sent to third-party services
- ❌ No cross-session user profiling

The `visitor_hash` is an anonymous daily counter: `SHA256(IP + UA + daily_salt)`. The salt rotates every night — after 24h the hash cannot be linked to any visitor.

---

## Installation

1. Download the ZIP from [GitHub Releases](https://github.com/dadebertolino/db-site-analytics/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and activate
4. Find the plugin under **Analytics** in the admin sidebar

**Requirements:** WordPress 5.8+ · PHP 7.4+ · MySQL 5.6+

---

## File Structure

```
db-site-analytics/
├── db-site-analytics.php
├── inc/
│   ├── class-db.php
│   ├── class-tracker.php
│   ├── class-downloader.php
│   ├── class-events.php
│   ├── class-shortcodes.php
│   ├── class-rest-api.php
│   ├── class-exporter.php
│   ├── class-admin.php
│   └── class-updater.php
├── templates/admin/
│   ├── dashboard.php
│   ├── downloads.php
│   ├── events.php
│   ├── settings.php
│   └── widget.php
├── assets/css/
│   ├── db-admin-ui.css
│   ├── admin.css
│   └── frontend.css
├── assets/js/
│   ├── downloader.js
│   └── events.js
└── uninstall.php
```

---

## Changelog

### 3.0.2
- Fix: removed union return types (`int|false`) incompatible with PHP 7.4

### 3.0.1
- Fix: explicit hook checks in `enqueue_assets` for all admin subpages
- Fix: complete settings defaults on activation (Phase 2 and 3)
- Fix: `ajax_get_stats` now returns all dashboard data
- Fix: spurious backslash in settings template
- Added: `assets/css/frontend.css` for `[dbsa_views]` shortcode
- Added: `LICENSE` and `readme.txt`

### 3.0.0
- REST API: 5 authenticated endpoints
- `[dbsa_views]` shortcode with `page_id`, `period`, `type`, `format` parameters
- Event tracking: outbound link clicks and scroll depth (25/50/75/100%)
- New "Events" admin page
- CSV export for events
- `{prefix}dbsa_events` database table

### 2.0.0
- Dashboard: period comparison with percentage change
- Browser and OS breakdown (inline bars)
- Download tracking with ~1KB frontend script
- Dedicated Downloads admin page
- CSV export for pageviews and downloads (UTF-8 BOM)

### 1.0.0
- Initial release: server-side pageview tracking, dashboard, bot filter, WP widget, GitHub auto-updater

---

## Author

**Davide Bertolino** — [davidebertolino.it](https://www.davidebertolino.it)

## License

GPL v2 or later — [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
