=== DB Site Analytics ===
Contributors: dadebertolino
Tags: analytics, statistics, gdpr, privacy, tracking
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 3.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tracciamento visite server-side senza cookie e senza servizi esterni. Privacy by design.

== Description ==

**DB Site Analytics** è un plugin WordPress per il tracciamento delle visite senza dipendenze esterne.

* Nessun cookie
* Nessun JavaScript per contare le visite (script leggeri solo per le funzioni opzionali download ed eventi)
* Zero servizi esterni (niente Google, Matomo, Plausible)
* I dati restano nel tuo database WordPress
* Privacy by design: nessun IP salvato, nessun dato inviato a terzi

= Come funziona =

Il tracciamento avviene lato server tramite l'hook `template_redirect`. Nessuno script viene iniettato nelle pagine per contare le visite e gli ad-blocker non possono bloccarlo. Vengono contate solo le pagine reali: 404, bot, richieste HEAD e prefetch del browser sono esclusi.

Per contare i visitatori unici senza identificarli, viene generato un hash giornaliero: `SHA256(IP + UserAgent + salt_giornaliero)`. Il salt cambia a mezzanotte (fuso orario del sito) e quello precedente viene eliminato: da quel momento l'hash non è più ricollegabile al visitatore. L'indirizzo IP non viene mai salvato.

= Funzionalità =

* Dashboard con grafico visite, KPI, top pagine, top referrer, breakdown dispositivi/browser/OS
* Confronto periodo corrente vs periodo precedente
* Download tracking (click su file PDF, ZIP, DOCX...)
* Tracking eventi: outbound link e scroll depth
* Shortcode `[dbsa_views]` per mostrare il contatore visite
* REST API autenticata `/wp-json/dbsa/v1/stats`
* Export CSV (pageview, download, eventi)
* Ricerche interne e anteprime di condivisione (Facebook, WhatsApp, Telegram…) come report separati
* Bonifica dello storico: esclude il rumore registrato in passato, senza cancellare dati
* Auto-aggiornamento da GitHub Releases

= Privacy =

Il plugin è progettato per ridurre al minimo i dati personali: non salva indirizzi IP, non installa cookie, non memorizza nulla sul dispositivo del visitatore e non comunica con servizi di terze parti (a parte il download mensile del database GeoIP, se attivato). I referrer esterni vengono salvati senza query string.

Il visitor_hash è un dato pseudonimo durante la giornata e diventa non ricollegabile dopo la rotazione del salt. In genere non serve un banner di consenso, ma il trattamento va descritto nell'informativa privacy. Se attivi il tracciamento delle ricerche interne, i termini cercati possono contenere dati personali.

== Installation ==

1. Scarica lo ZIP dalla pagina [GitHub Releases](https://github.com/dadebertolino/db-site-analytics/releases)
2. Vai su Plugin → Aggiungi nuovo → Carica plugin
3. Carica lo ZIP e attiva il plugin
4. Accedi alla dashboard da **Analytics** nel menu laterale

== Frequently Asked Questions ==

= Devo mettere il banner cookie per questo plugin? =

In genere no: il plugin non installa cookie e non memorizza nulla sul dispositivo del visitatore. Il trattamento dei dati (hash giornaliero, eventuali termini di ricerca) va comunque indicato nell'informativa privacy. Per casi specifici confrontati con il tuo consulente privacy.

= Il plugin rallenta il sito? =

No. Il tracciamento avviene lato server, senza JavaScript aggiunto alle pagine. L'impatto sulle performance è trascurabile (una query INSERT per pageview).

= Uso una cache di pagina: le visite vengono contate? =

Solo quelle che raggiungono PHP. Con una cache di pagina (WP Super Cache, LiteSpeed Cache, Cloudflare APO…) le pagine servite dalla cache non eseguono WordPress e non vengono conteggiate.

= Dopo l'aggiornamento alla 3.3.0 vedo meno visite e referral. È normale? =

Sì. Dalla 3.3.0 non vengono più contati 404, bot, prefetch, ricerche e navigazione interna tra le pagine del sito: quei numeri non misuravano visite reali. Per ripulire anche i dati passati usa "Bonifica storico" nelle impostazioni.

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

= 3.3.0 =
* Accuratezza: esclusi dal conteggio i 404 (scanner che cercano .env, up.php, wp-login…), le richieste HEAD, il prefetch/prerender del browser, favicon, anteprime ed embed
* Accuratezza: filtro bot rivisto (richieste senza User-Agent, HeadlessChrome, okhttp, Bytespider, PetalBot…) ed estendibile con il filtro dbsa_bot_patterns
* Accuratezza: referrer normalizzati per host (senza www); la navigazione interna non conta più come referral. Nuova colonna referrer_host, storico ricalcolato in background
* Accuratezza: giorni, grafico e filtri seguono il fuso orario del sito (prima UTC), con gestione dell'ora legale; il salt dei visitatori ruota a mezzanotte locale e non più due volte al giorno
* Nuovo: "Escludi lo staff del sito" — le visite di chi può modificare contenuti non vengono registrate (l'opzione "Escludi gli amministratori" non era applicata)
* Nuovo: ricerche interne tracciate come evento separato (non più come pagine), con un massimo di 10 ricerche al minuto per IP
* Nuovo: conteggio delle anteprime di condivisione (Facebook, WhatsApp, Telegram, X, LinkedIn…) per stimare quante volte una pagina viene condivisa
* Nuovo: "Bonifica storico" nelle impostazioni — esclude dalle statistiche il rumore registrato in passato, senza cancellare dati e con ripristino
* Nuovo: campi searches e share_previews nell'endpoint REST /stats/events
* Privacy: referrer esterni salvati senza query string; eliminati i lock del salt dei giorni passati, che ne conservavano una copia; testi privacy corretti (l'hash è pseudonimo durante la giornata)
* Performance: Chart.js incluso nel plugin (nessuna richiesta a CDN), CSS dello shortcode caricato solo dove serve, query duplicata rimossa dalla dashboard
* Fix: shortcode [dbsa_views] senza attributi in errore fatale su WordPress ≤ 6.4
* Fix: iPhone e iPad rilevati come macOS
* Fix: download con link relativi e link esterni //dominio non tracciati
* Fix: con i permalink semplici tutte le visite risultavano sulla homepage
* Fix: date non valide accettate nei filtri della dashboard e dell'export
* Fix: l'auto-updater riattivava il plugin anche se era disattivato
* Fix: file temporaneo non eliminato quando il download GeoIP falliva
* Rimosso: endpoint AJAX dbsa_get_stats, non utilizzato
* API: in /stats/referrers il campo referrer contiene ora l'host normalizzato
* Export CSV: date nel fuso orario del sito e nuova colonna Host Referrer

= 3.2.0 =
* Nuovo: geolocalizzazione paese (GeoIP) self-contained — database gratuito DB-IP Country Lite scaricato localmente, nessuna registrazione, nessun servizio esterno a runtime
* Nuovo: reader MMDB in PHP puro (DBSA_MMDB_Reader), zero dipendenze Composer, supporto record 24/28/32 bit e IPv4/IPv6
* Nuovo: card "Paesi" nella dashboard con breakdown per nazione
* Nuovo: campo countries nell'endpoint REST /stats/devices
* Nuovo: endpoint REST /stats/downloads e /stats/events — la REST API copre ora tutte le metriche (7 endpoint)
* Nuovo: sezione GeoIP nelle impostazioni — attivazione, stato database (dimensione/build), aggiornamento manuale; download automatico all'attivazione e aggiornamento mensile via cron
* Nota privacy: l'IP è usato solo in memoria per il lookup e mai salvato; nel database viene registrato esclusivamente il codice paese ISO
* Attribuzione: "IP Geolocation by DB-IP" (CC BY 4.0) mostrata in dashboard e impostazioni
* Qualità: CI GitHub Actions (PHPCS con ruleset WPCS, lint PHP 7.4/8.3) e workflow di release che allega lo ZIP; fix da audit PHPCS — escape su wp_die ed eccezioni, wp_safe_redirect, wp_parse_url, commenti translators, rinominate variabili template che oscuravano globali WordPress

= 3.1.0 =
* Fix critico: rimosso il nonce dagli endpoint di tracking download/eventi — con il page caching il nonce cachato scadeva e il tracking falliva silenziosamente
* Sicurezza: rate limiting per IP sugli endpoint pubblici (20 download/min, 30 eventi/min)
* Sicurezza: validazione stretta degli eventi (scroll_depth solo 25/50/75/100%, outbound_click solo URL esterni validi, estensione download tra quelle configurate)
* Sicurezza: nuova impostazione "dietro proxy/CDN" — di default l'IP è letto solo da REMOTE_ADDR (non falsificabile); gli header X-Forwarded-For/CF-Connecting-IP vengono usati solo se l'opzione è attiva
* Performance: cache transient per shortcode [dbsa_views] (10 min), dashboard admin, widget e AJAX (2-5 min)
* Performance: eliminato SHOW TABLES a ogni pageview — verifica schema con opzione versionata (dbsa_schema_version)
* Refactor: nuova classe DBSA_Visitor — hash visitatore, IP e salt centralizzati (prima duplicati in 3 classi)
* Fix: rotazione salt atomica con add_option (race condition al cambio giorno)
* Fix: retention usa UTC_TIMESTAMP invece di NOW() (created_at è in UTC) e cancella a batch da 5000 righe
* Fix: cron schedulato a "tomorrow midnight" (prima partiva subito)
* Fix: cast (string) su parse_url prima di fnmatch (deprecation PHP 8.1+)

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

= 3.3.0 =
Statistiche più accurate: dopo l'aggiornamento visite e referral caleranno, perché 404, bot e navigazione interna non vengono più contati. Usa "Bonifica storico" nelle impostazioni per ripulire anche i dati passati.

= 3.0.3 =
Fix critico: la dashboard non si apriva su alcuni server. Aggiornamento raccomandato.

= 3.0.2 =
Fix compatibilità PHP 7.4. Aggiornamento raccomandato per tutti.

= 3.0.0 =
Aggiunta tabella dbsa_events. La tabella viene creata automaticamente al primo utilizzo. Nessuna azione richiesta.
