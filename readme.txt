=== DB Site Analytics ===
Contributors: dadebertolino
Tags: analytics, statistics, gdpr, privacy, tracking
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 3.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tracciamento visite server-side senza cookie, senza servizi esterni, GDPR compliant by design.

== Description ==

**DB Site Analytics** è un plugin WordPress per il tracciamento delle visite senza dipendenze esterne.

* Zero cookie di profilazione
* Zero JavaScript di tracking nel frontend
* Zero servizi esterni (niente Google, Matomo, Plausible)
* I dati restano nel tuo database WordPress
* GDPR compliant by design — non serve consenso

= Come funziona =

Il tracciamento avviene lato server tramite l'hook `template_redirect`. Nessuno script viene iniettato nelle pagine, le performance non vengono impattate e gli ad-blocker non possono bloccarlo.

Per contare i visitatori unici senza identificarli, viene generato un hash giornaliero anonimo: `SHA256(IP + UserAgent + salt_giornaliero)`. Il salt cambia ogni notte — dopo 24h l'hash è irrecuperabile. L'indirizzo IP non viene mai salvato.

= Funzionalità =

* Dashboard con grafico visite, KPI, top pagine, top referrer, breakdown dispositivi/browser/OS
* Confronto periodo corrente vs periodo precedente
* Download tracking (click su file PDF, ZIP, DOCX...)
* Tracking eventi: outbound link e scroll depth
* Shortcode `[dbsa_views]` per mostrare il contatore visite
* REST API autenticata `/wp-json/dbsa/v1/stats`
* Export CSV (pageview, download, eventi)
* Auto-aggiornamento da GitHub Releases

= Privacy =

Questo plugin non raccoglie dati personali. Non installa cookie. Non comunica con servizi di terze parti. Non richiede consenso ai sensi del GDPR e del Regolamento ePrivacy.

== Installation ==

1. Scarica lo ZIP dalla pagina [GitHub Releases](https://github.com/dadebertolino/db-site-analytics/releases)
2. Vai su Plugin → Aggiungi nuovo → Carica plugin
3. Carica lo ZIP e attiva il plugin
4. Accedi alla dashboard da **Analytics** nel menu laterale

== Frequently Asked Questions ==

= Devo mettere il banner cookie per questo plugin? =

No. Il plugin non installa cookie e non raccoglie dati personali. Non è necessario il consenso ai sensi del GDPR e del Regolamento ePrivacy.

= Il plugin rallenta il sito? =

No. Il tracciamento avviene completamente lato server, senza JavaScript aggiunto alle pagine. L'impatto sulle performance è trascurabile (una query INSERT per pageview).

= Posso usarlo in multisite? =

Il plugin è progettato per installazioni singole. Il supporto multisite non è stato testato.

= Come si aggiorna? =

Il plugin include un auto-updater che controlla GitHub Releases ogni 12 ore. Gli aggiornamenti appaiono nella schermata Plugin come qualsiasi altro plugin.

= Cosa succede se disinstallo il plugin? =

Il file `uninstall.php` rimuove tutte le tabelle del database e tutte le opzioni create dal plugin. Nessun dato residuo.

== Screenshots ==

1. Dashboard principale con grafico visite e KPI
2. Confronto periodi con variazione percentuale
3. Pagina Download tracking
4. Pagina Tracking Eventi
5. Impostazioni

== Changelog ==

= 3.0.3 =
* Fix: errore "Unsupported operand types" nella dashboard — aggiunti cast espliciti sui valori restituiti da wpdb prima di operazioni aritmetiche

= 3.0.2 =
* Fix: rimossi i return type union (int|false) incompatibili con PHP 7.4

= 3.0.1 =
* Fix: hook enqueue_assets esplicitati per tutte le sottopagine admin
* Fix: defaults impostazioni completi all'attivazione (Fase 2 e 3)
* Fix: ajax_get_stats restituisce ora tutti i dati dashboard
* Fix: backslash superfluo in settings template
* Aggiunto: frontend.css per shortcode [dbsa_views]
* Aggiunto: LICENSE e readme.txt

= 3.0.0 =
* Shortcode [dbsa_views] con parametri page_id, period, type, format
* Tracking eventi: outbound link click e scroll depth
* Pagina admin "Eventi"
* Export CSV eventi
* Tabella DB {prefix}dbsa_events

= 2.0.0 =
* Confronto periodo corrente vs precedente
* Breakdown browser e OS
* Download tracking con JS frontend ~1KB
* Pagina dedicata Download
* Export CSV pageview e download (BOM UTF-8)

= 1.0.0 =
* Prima release: tracking pageview server-side, dashboard, filtro bot, widget WP, GitHub auto-updater

== Upgrade Notice ==

= 3.0.3 =
Fix critico: la dashboard non si apriva su alcuni server. Aggiornamento raccomandato.

= 3.0.2 =
Fix compatibilità PHP 7.4. Aggiornamento raccomandato per tutti.

= 3.0.0 =
Aggiunta tabella dbsa_events. La tabella viene creata automaticamente al primo utilizzo. Nessuna azione richiesta.
