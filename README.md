# DB Site Analytics

**Server-side WordPress visit tracking — no cookies, no external services, no tracking scripts.**

All data stays in your WordPress database. Privacy by design.

---

## Features

### Tracking
- **Server-side** via `template_redirect` hook — no tracking script for pageviews, invisible to ad-blockers
- **Only real pages** — 404s (scanners probing `.env`, `up.php`…), HEAD requests, browser prefetch/prerender, favicon and previews are ignored
- **No IP address stored** — daily visitor hash (SHA-256 + salt rotated at local midnight)
- **No cookies** of any kind
- **Bot/crawler filter** — requests without User-Agent, crawlers, headless browsers and HTTP libraries; extensible via the `dbsa_bot_patterns` filter
- **Normalised referrers** — aggregated by host; internal navigation is not a referral
- **Site timezone** — days, chart and filters follow the WordPress timezone (DST-aware)
- Lightweight User-Agent parsing for device, browser and OS (no external library)
- Configurable exclusions for site staff, user roles, URL paths and logged-in users

> With full-page caching (WP Super Cache, LiteSpeed Cache, Cloudflare APO…) pages served from cache don't run PHP and are not counted.

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

### Internal search & share previews *(server-side, no JS)*
- **Internal searches** recorded as a separate report, not as pages — empty or >100-character queries discarded, max 10 searches/min per IP
- **Share previews** — requests from Facebook, WhatsApp, Telegram, X, LinkedIn, Slack, Discord… generating a link preview are excluded from visits and counted separately, to estimate how often a page is shared

### History cleanup
- Settings card that previews past noise (404/scanner paths, untitled pages, searches recorded as pages) with row counts and top values
- Rows are flagged, not deleted: statistics exclude them and they can be restored at any time

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

The plugin is designed to minimise personal data:

- No IP addresses stored — the IP is only used in memory (visitor hash, optional GeoIP lookup)
- No cookies and nothing stored on the visitor's device
- No data sent to third-party services (except the monthly GeoIP database download, if enabled)
- No cross-day profiling: the salt rotates at local midnight and the previous one is deleted
- External referrers stored without query string

The `visitor_hash` is `SHA256(IP + UA + daily_salt)`. While the day's salt exists the hash is **pseudonymous**, not anonymous; once the salt rotates it can no longer be linked to a visitor. A consent banner is generally not required because no cookies or device storage are used, but the processing should still be described in your privacy policy. If internal search tracking is enabled, search terms may contain personal data.

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
│   ├── admin.js
│   ├── downloader.js
│   ├── events.js
│   └── vendor/chart.umd.min.js   (Chart.js 4.4.0, MIT)
└── uninstall.php
```

---

## Changelog

### 3.3.0
- Accuracy: 404s (scanners probing `.env`, `up.php`, `wp-login`…), HEAD requests, browser prefetch/prerender, favicon, previews and embeds are no longer counted
- Accuracy: revised bot filter (requests without User-Agent, HeadlessChrome, okhttp, Bytespider, PetalBot…), extensible via the `dbsa_bot_patterns` filter
- Accuracy: referrers normalised by host (no `www`); internal navigation is no longer a referral. New `referrer_host` column, history backfilled in the background
- Accuracy: days, chart and filters follow the site timezone (previously UTC), DST-aware; the visitor salt rotates at local midnight and no longer twice a day
- New: "Exclude site staff" — visits from users who can edit content are not recorded (the old "Exclude administrators" option was never applied)
- New: internal searches tracked as a separate event (no longer as pages), capped at 10 searches/min per IP
- New: share-preview counter (Facebook, WhatsApp, Telegram, X, LinkedIn…) to estimate how often a page is shared
- New: history cleanup in settings — hides past noise from statistics without deleting data, with restore
- New: `searches` and `share_previews` in the `/stats/events` REST endpoint
- Privacy: external referrers stored without query string; past days' salt locks (which kept a copy of the salt) are deleted; privacy wording corrected (the hash is pseudonymous during the day)
- Performance: Chart.js bundled (no CDN request), shortcode CSS loaded only where used, duplicate dashboard query removed
- Fix: `[dbsa_views]` without attributes caused a fatal error on WordPress ≤ 6.4
- Fix: iPhone and iPad detected as macOS
- Fix: relative download links and protocol-relative outbound links were not tracked
- Fix: with plain permalinks every visit was recorded as the homepage
- Fix: invalid dates accepted in dashboard and export filters
- Fix: the auto-updater re-activated the plugin even when it was inactive
- Fix: temporary file left behind when the GeoIP download failed
- Removed: unused `dbsa_get_stats` AJAX endpoint
- API: in `/stats/referrers` the `referrer` field now contains the normalised host
- CSV export: dates in the site timezone and new "Host Referrer" column

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
