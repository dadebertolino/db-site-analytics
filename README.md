# DB Site Analytics

**Plugin WordPress per il tracciamento delle visite senza dipendenze esterne.**

Zero cookie. Zero JavaScript di tracking. Zero servizi esterni. I dati restano nel tuo database. GDPR compliant by design.

---

## Caratteristiche

### Tracciamento
- **Server-side** via hook `template_redirect` — nessun JS iniettato nel frontend, nessun impatto sulle performance, invisibile agli ad-blocker
- **Nessun IP salvato** — visitor hash giornaliero anonimo non reversibile (SHA-256 + salt rotante ogni 24h)
- **Nessun cookie** di profilazione o sessione
- **Filtro bot/crawler** automatico (lista di 30+ pattern UA)
- Parsing User-Agent leggero per device, browser e OS
- Esclusione configurabile per ruoli, percorsi e utenti loggati

### Dashboard
- Grafico visite (pageview + visitatori unici) con filtro date
- KPI: oggi, 7 giorni, periodo selezionato
- **Confronto periodi** con variazione percentuale e mini-bar
- Top 10 pagine e Top 10 referrer
- Breakdown dispositivi (donut), browser e OS (bar inline)
- Widget nella dashboard principale di WordPress
- Export CSV pageview, download ed eventi (BOM UTF-8, compatibile Excel)

### Download tracking *(opzionale)*
- Intercetta click su link a file con estensioni configurabili
- JS frontend ~1KB (sendBeacon + fallback XHR)
- Pagina dedicata con top file scaricati, visitatori unici, pagina di provenienza

### Tracking eventi *(opzionale)*
- **Outbound link** — click su link che portano fuori dal sito
- **Scroll depth** — soglie 25%, 50%, 75%, 100%
- Pagina dedicata con tabella outbound links e barre scroll depth
- Export CSV eventi

### REST API *(autenticata, richiede `manage_options`)*
```
GET /wp-json/dbsa/v1/stats
GET /wp-json/dbsa/v1/stats/pages
GET /wp-json/dbsa/v1/stats/referrers
GET /wp-json/dbsa/v1/stats/devices
GET /wp-json/dbsa/v1/stats/daily
```

### Shortcode contatore visite
```
[dbsa_views]
[dbsa_views period="7"]
[dbsa_views page_id="123"]
[dbsa_views type="visitors"]
[dbsa_views format="compact"]
[dbsa_views format="number"]
```

---

## Privacy & GDPR

Non richiede consenso: non raccoglie IP, non installa cookie, non invia dati a terzi.
Il `visitor_hash` è un contatore anonimo giornaliero: `SHA256(IP + UA + salt_giornaliero)` — irrecuperabile dopo 24h.

---

## Installazione

1. Scarica lo ZIP dalla pagina [Releases](https://github.com/dadebertolino/db-site-analytics/releases)
2. WordPress → Plugin → Aggiungi nuovo → Carica plugin → Attiva
3. **Analytics** nel menu laterale

**Requisiti:** WordPress 5.8+ · PHP 7.4+ · MySQL 5.6+

---

## Struttura

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
│   └── admin.css
├── assets/js/
│   ├── downloader.js
│   └── events.js
└── uninstall.php
```

---

## Changelog

### 3.0.2
- Fix: rimossi return type union (`int|false`) incompatibili con PHP 7.4

### 3.0.1
- Fix: hook `enqueue_assets` esplicitati per tutte le sottopagine admin
- Fix: defaults impostazioni completi all'attivazione (Fase 2 e 3)
- Fix: `ajax_get_stats` restituisce ora tutti i dati dashboard
- Fix: backslash superfluo in settings template
- Aggiunto: `assets/css/frontend.css` per shortcode `[dbsa_views]`
- Aggiunto: `LICENSE` e `readme.txt`

### 3.0.0
- REST API: 5 endpoint autenticati (`/stats`, `/stats/pages`, `/stats/referrers`, `/stats/devices`, `/stats/daily`)
- Shortcode `[dbsa_views]` con parametri `page_id`, `period`, `type`, `format`
- Tracking eventi: outbound link click e scroll depth (25/50/75/100%)
- Pagina admin "Eventi" con tabella outbound e barre scroll depth
- Export CSV eventi
- Tabella DB `{prefix}dbsa_events`

### 2.0.0
- Dashboard: confronto periodo corrente vs precedente con variazione %
- Breakdown browser e OS (bar inline)
- Download tracking con JS ~1KB e pagina dedicata
- Export CSV pageview e download (BOM UTF-8)
- Classi `DBSA_Exporter` e `DBSA_Downloader`

### 1.0.0
- MVP: tracking pageview server-side, dashboard base, filtro bot, widget WP, GitHub auto-updater

---

## Autore

**Davide Bertolino** — [davidebertolino.it](https://www.davidebertolino.it)

## Licenza

GPL v2 or later
