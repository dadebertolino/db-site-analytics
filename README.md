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

### Country geolocation (GeoIP) *(v3.2.0)*
- Self-contained: free **DB-IP Country Lite** database (MMDB) downloaded locally into `wp-content/uploads/dbsa-geoip/` — no registration, no API key, no external service at runtime
- Pure-PHP MMDB reader, zero Composer dependencies (24/28/32-bit records, IPv4/IPv6)
- Automatic monthly refresh via cron, manual update button in settings
- The visitor IP is used in memory only for the lookup and never stored; only the ISO country code is written to the database
- Attribution: [IP Geolocation by DB-IP](https://db-ip.com) (CC BY 4.0)

### REST API *(authenticated, requires `manage_options`)*
```
GET /wp-json/dbsa/v1/stats
GET /wp-json/dbsa/v1/stats/pages
GET /wp-json/dbsa/v1/stats/referrers
GET /wp-json/dbsa/v1/stats/devices
GET /wp-json/dbsa/v1/stats/daily
GET /wp-json/dbsa/v1/stats/downloads
GET /wp-json/dbsa/v1/stats/events
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
│   ├── class-mmdb-reader.php
│   ├── class-geoip.php
│   ├── class-visitor.php
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

### 3.2.0
- New: self-contained country geolocation (GeoIP) — free DB-IP Country Lite database downloaded locally; no registration, no external service at runtime
- New: pure-PHP MMDB reader (`DBSA_MMDB_Reader`), zero Composer dependencies, 24/28/32-bit records, IPv4/IPv6 (validated against official MaxMind test databases)
- New: "Countries" dashboard card with per-nation breakdown
- New: `countries` field in the `/stats/devices` REST endpoint
- New: `/stats/downloads` and `/stats/events` REST endpoints — the REST API now covers every metric (7 endpoints)
- New: GeoIP settings section — enable toggle, database status (size/build date), manual update button; automatic download on activation and monthly refresh via cron
- Privacy: the IP is used in memory only for the lookup and never stored; only the ISO country code is written to the database
- Attribution: "IP Geolocation by DB-IP" (CC BY 4.0) shown in dashboard and settings
- Quality: GitHub Actions CI (PHPCS with WPCS ruleset, PHP 7.4/8.3 lint) and release workflow attaching the plugin ZIP; PHPCS audit fixes — escaping on `wp_die` and exceptions, `wp_safe_redirect`, `wp_parse_url`, translators comments, renamed template variables shadowing WordPress globals

### 3.1.0
- Critical fix: removed nonce from download/event tracking endpoints — with page caching the cached nonce expired and tracking silently failed
- Security: per-IP rate limiting on public endpoints (20 downloads/min, 30 events/min)
- Security: strict event validation (scroll_depth limited to 25/50/75/100%, outbound_click must be a valid external URL, download extension must match configured list)
- Security: new "behind proxy/CDN" setting — by default the IP is read only from `REMOTE_ADDR` (not spoofable); `X-Forwarded-For`/`CF-Connecting-IP` headers are used only when enabled
- Performance: transient caching for `[dbsa_views]` shortcode (10 min), admin dashboard, widget and AJAX (2–5 min)
- Performance: removed `SHOW TABLES` on every pageview — schema verified via versioned option (`dbsa_schema_version`)
- Refactor: new `DBSA_Visitor` class — visitor hash, IP and salt logic centralized (previously duplicated in 3 classes)
- Fix: atomic daily salt rotation via `add_option` (day-change race condition)
- Fix: retention uses `UTC_TIMESTAMP()` instead of `NOW()` (`created_at` is stored in UTC) and deletes in 5000-row batches
- Fix: cron scheduled at `tomorrow midnight` (previously fired immediately)
- Fix: `(string)` cast on `parse_url` before `fnmatch` (PHP 8.1+ deprecation)

### 3.0.3
- Fix: "Unsupported operand types" nella dashboard — `$wpdb->get_results()` restituisce stringhe, aggiunti cast `(int)` e `array_map('intval', ...)` prima di operazioni aritmetiche in `dashboard.php` e `events.php`

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
