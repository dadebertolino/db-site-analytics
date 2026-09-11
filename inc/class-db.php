<?php
/**
 * DBSA_DB — Creazione e gestione tabelle database.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_DB {

    private static $instance = null;

    /** Versione schema: incrementare quando cambia la struttura delle tabelle. */
    const SCHEMA_VERSION = '4';

    /** Cron one-off: calcola referrer_host sulle righe precedenti allo schema 4. */
    const BACKFILL_HOOK = 'dbsa_backfill_referrer_host';

    /** is_bot = 1: rumore (404, scanner) marcato dalla bonifica storico. */
    const FLAG_NOISE = 1;

    /** is_bot = 2: ricerca interna registrata come pageview prima della v3.3.0. */
    const FLAG_SEARCH = 2;

    /** Flag in-memory: tabelle verificate in questa richiesta. */
    private static $tables_verified = false;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Cron handler
        add_action('dbsa_daily_cron', array($this, 'daily_maintenance'));
        add_action(self::BACKFILL_HOOK, array($this, 'backfill_referrer_host'));
    }

    /**
     * Nome tabella pageviews.
     */
    public static function table_pageviews(): string {
        global $wpdb;
        return $wpdb->prefix . 'dbsa_pageviews';
    }

    // =========================================================================
    // v3.3.0 — Giorni nel fuso orario del sito
    // created_at è salvato in UTC; le date Y-m-d ricevute da dashboard, REST,
    // export e shortcode sono giorni locali e vengono convertite in confini UTC.
    // =========================================================================

    /**
     * Inizio (00:00:00) del giorno locale, espresso in UTC.
     */
    public static function utc_start(string $date): string {
        return self::local_midnight($date)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Fine (23:59:59) del giorno locale, espressa in UTC. Con l'ora legale
     * un giorno può durare 23 o 25 ore: il calcolo segue il calendario locale.
     */
    public static function utc_end(string $date): string {
        return self::local_midnight($date)
            ->modify('+1 day -1 second')
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private static function local_midnight(string $date): DateTimeImmutable {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        return $day ?: new DateTimeImmutable('today', wp_timezone());
    }

    /**
     * Espressione SQL con la data locale di created_at. Se nell'intervallo
     * cambia l'ora legale, l'offset viene scelto con un CASE sulle transizioni
     * (non richiede le tabelle timezone di MySQL).
     */
    private static function local_date_sql(string $from, string $to): string {
        $tz    = wp_timezone();
        $begin = self::local_midnight($from)->getTimestamp();
        $end   = self::local_midnight($to)->modify('+1 day')->getTimestamp();

        // false per i fusi a offset fisso (es. "UTC+2" nelle impostazioni)
        $transitions = $tz->getTransitions($begin, $end);

        if (empty($transitions) || count($transitions) === 1) {
            $offset = $tz->getOffset(new DateTimeImmutable('@' . $begin));
            return 'DATE(DATE_ADD(created_at, INTERVAL ' . (int) $offset . ' SECOND))';
        }

        $count = count($transitions);
        $case  = 'CASE';
        for ($i = 1; $i < $count; $i++) {
            $case .= " WHEN created_at < '" . gmdate('Y-m-d H:i:s', (int) $transitions[$i]['ts']) . "'"
                . ' THEN ' . (int) $transitions[$i - 1]['offset'];
        }
        $case .= ' ELSE ' . (int) $transitions[$count - 1]['offset'] . ' END';

        return "DATE(DATE_ADD(created_at, INTERVAL ({$case}) SECOND))";
    }

    /**
     * Crea/aggiorna tabella con dbDelta.
     */
    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table           = self::table_pageviews();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url VARCHAR(2083) NOT NULL DEFAULT '',
            page_title VARCHAR(255) NOT NULL DEFAULT '',
            referrer VARCHAR(2083) NOT NULL DEFAULT '',
            referrer_host VARCHAR(255) NOT NULL DEFAULT '',
            visitor_hash VARCHAR(64) NOT NULL DEFAULT '',
            device_type VARCHAR(10) NOT NULL DEFAULT 'desktop',
            browser VARCHAR(50) NOT NULL DEFAULT '',
            os VARCHAR(50) NOT NULL DEFAULT '',
            country VARCHAR(2) NOT NULL DEFAULT '',
            is_bot TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_created (created_at),
            INDEX idx_page (page_url(191)),
            INDEX idx_hash_date (visitor_hash, created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Inserisce un pageview. Ritorna l'ID o false.
     */
    public function insert_pageview(array $data) {
        global $wpdb;

        $this->ensure_tables();

        $inserted = $wpdb->insert(
            self::table_pageviews(),
            array(
                'page_url'     => substr(sanitize_url($data['page_url'] ?? ''), 0, 2083),
                'page_title'   => substr(sanitize_text_field($data['page_title'] ?? ''), 0, 255),
                'referrer'     => substr(sanitize_url($data['referrer'] ?? ''), 0, 2083),
                'referrer_host' => substr(sanitize_text_field($data['referrer_host'] ?? ''), 0, 255),
                'visitor_hash' => sanitize_text_field($data['visitor_hash'] ?? ''),
                'device_type'  => sanitize_key($data['device_type'] ?? 'desktop'),
                'browser'      => substr(sanitize_text_field($data['browser'] ?? ''), 0, 50),
                'os'           => substr(sanitize_text_field($data['os'] ?? ''), 0, 50),
                'country'      => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['country'] ?? ''), 0, 2)),
                'is_bot'       => absint($data['is_bot'] ?? 0),
                'created_at'   => current_time('mysql', true), // UTC
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Statistiche aggregate per la dashboard.
     */
    public function get_stats(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();

        // Totali
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS pageviews,
                COUNT(DISTINCT visitor_hash) AS visitors
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);

        return $totals ?? array('pageviews' => 0, 'visitors' => 0);
    }

    /**
     * Visite giornaliere in un intervallo (per grafico), per giorno locale del sito.
     */
    public function get_daily_views(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();
        $day   = self::local_date_sql($from, $to);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                {$day} AS day,
                COUNT(*) AS pageviews,
                COUNT(DISTINCT visitor_hash) AS visitors
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s
             GROUP BY day
             ORDER BY day ASC",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Top pagine.
     */
    public function get_top_pages(string $from, string $to, int $limit = 10): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                page_url,
                MAX(page_title) AS page_title,
                COUNT(*) AS pageviews,
                COUNT(DISTINCT visitor_hash) AS visitors
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s
             GROUP BY page_url
             ORDER BY pageviews DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    /**
     * Top referrer.
     */
    public function get_top_referrers(string $from, string $to, int $limit = 10): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                referrer_host AS referrer,
                COUNT(*) AS pageviews
             FROM {$table}
             WHERE is_bot = 0
               AND referrer_host != ''
               AND created_at BETWEEN %s AND %s
             GROUP BY referrer_host
             ORDER BY pageviews DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    /**
     * Breakdown device type.
     */
    public function get_device_breakdown(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT device_type, COUNT(*) AS total
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s
             GROUP BY device_type",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Verifica esistenza tabella pageviews.
     */
    private function table_exists(): bool {
        global $wpdb;
        $table = self::table_pageviews();
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    /**
     * v3.1.0 — Garantisce l'esistenza di tutte le tabelle SENZA eseguire
     * SHOW TABLES a ogni insert: usa un flag in-memory + opzione versionata.
     */
    public function ensure_tables(): void {
        if (self::$tables_verified) {
            return;
        }
        if (get_option('dbsa_schema_version') !== self::SCHEMA_VERSION) {
            $this->upgrade();
        }
        self::$tables_verified = true;
    }

    /**
     * Crea/aggiorna tutte le tabelle e avvia le migrazioni dati.
     * Usato all'attivazione e a ogni cambio di SCHEMA_VERSION.
     */
    public function upgrade(): void {
        $this->create_tables();
        $this->create_downloads_table();
        $this->create_events_table();
        update_option('dbsa_schema_version', self::SCHEMA_VERSION, false);

        // v3.3.0 — referrer_host per le righe esistenti, in background a blocchi
        if (!wp_next_scheduled(self::BACKFILL_HOOK)) {
            wp_schedule_single_event(time(), self::BACKFILL_HOOK);
        }
    }

    // =========================================================================
    // FASE 2 — Tabella Downloads
    // =========================================================================

    /**
     * Nome tabella downloads.
     */
    public static function table_downloads(): string {
        global $wpdb;
        return $wpdb->prefix . 'dbsa_downloads';
    }

    /**
     * Crea/aggiorna tabella downloads.
     */
    public function create_downloads_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table           = self::table_downloads();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_url VARCHAR(2083) NOT NULL DEFAULT '',
            file_name VARCHAR(255) NOT NULL DEFAULT '',
            page_url VARCHAR(2083) NOT NULL DEFAULT '',
            visitor_hash VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_created (created_at),
            INDEX idx_file (file_url(191))
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Inserisce un download tracciato.
     */
    public function insert_download(array $data) {
        global $wpdb;

        $this->ensure_tables();

        $inserted = $wpdb->insert(
            self::table_downloads(),
            array(
                'file_url'     => substr(sanitize_url($data['file_url'] ?? ''), 0, 2083),
                'file_name'    => substr(sanitize_text_field($data['file_name'] ?? ''), 0, 255),
                'page_url'     => substr(sanitize_url($data['page_url'] ?? ''), 0, 2083),
                'visitor_hash' => sanitize_text_field($data['visitor_hash'] ?? ''),
                'created_at'   => current_time('mysql', true),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Top file scaricati.
     */
    public function get_top_downloads(string $from, string $to, int $limit = 10): array {
        global $wpdb;
        $table = self::table_downloads();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                file_url,
                MAX(file_name) AS file_name,
                COUNT(*) AS downloads,
                COUNT(DISTINCT visitor_hash) AS visitors,
                MAX(page_url) AS from_page
             FROM {$table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY file_url
             ORDER BY downloads DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    /**
     * Totale download nel periodo.
     */
    public function get_downloads_total(string $from, string $to): int {
        global $wpdb;
        $table = self::table_downloads();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s",
            self::utc_start($from),
            self::utc_end($to)
        ));
    }

    // =========================================================================
    // FASE 2 — Query avanzate dashboard
    // =========================================================================

    /**
     * Top browser.
     */
    public function get_browser_breakdown(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT browser, COUNT(*) AS total
             FROM {$table}
             WHERE is_bot = 0 AND browser != ''
               AND created_at BETWEEN %s AND %s
             GROUP BY browser
             ORDER BY total DESC
             LIMIT 8",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Breakdown paesi (v3.2.0). Richiede GeoIP attivo per avere dati.
     */
    public function get_country_breakdown(string $from, string $to, int $limit = 10): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT country, COUNT(*) AS total
             FROM {$table}
             WHERE is_bot = 0 AND country != ''
               AND created_at BETWEEN %s AND %s
             GROUP BY country
             ORDER BY total DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    /**
     * Top OS.
     */
    public function get_os_breakdown(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT os, COUNT(*) AS total
             FROM {$table}
             WHERE is_bot = 0 AND os != ''
               AND created_at BETWEEN %s AND %s
             GROUP BY os
             ORDER BY total DESC
             LIMIT 8",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Confronto periodo corrente vs precedente.
     * Ritorna stats per entrambi i periodi (stessa durata).
     */
    public function get_period_comparison(string $from, string $to): array {
        $days       = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        $prev_to    = gmdate('Y-m-d', strtotime($from . ' -1 day'));
        $prev_from  = gmdate('Y-m-d', strtotime($prev_to . ' -' . ($days - 1) . ' days'));

        return array(
            'current'  => $this->get_stats($from, $to),
            'previous' => $this->get_stats($prev_from, $prev_to),
            'prev_from' => $prev_from,
            'prev_to'   => $prev_to,
        );
    }

    /**
     * Export CSV pageviews — restituisce array di righe.
     */
    public function get_pageviews_for_export(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                created_at, page_url, page_title,
                referrer, referrer_host, device_type, browser, os
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s
             ORDER BY created_at DESC
             LIMIT 50000",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Export CSV downloads.
     */
    public function get_downloads_for_export(string $from, string $to): array {
        global $wpdb;
        $table = self::table_downloads();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT created_at, file_name, file_url, page_url
             FROM {$table}
             WHERE created_at BETWEEN %s AND %s
             ORDER BY created_at DESC
             LIMIT 50000",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Manutenzione giornaliera: pulisce anche i download.
     */
    public function daily_maintenance(): void {
        // v3.3.0 — Il salt non viene più ruotato qui: lo fa DBSA_Visitor al primo
        // hit del nuovo giorno locale. Ruotarlo anche nel cron lo cambiava a metà
        // giornata e contava due volte gli stessi visitatori.
        DBSA_Visitor::purge_old_salt_locks();

        $settings       = get_option('dbsa_settings', array());
        $retention_days = absint($settings['retention_days'] ?? 90);

        if ($retention_days > 0) {
            // v3.1.0 — UTC_TIMESTAMP (created_at e' salvato in UTC, NOW() usa
            // il timezone del server MySQL) + delete a batch per evitare lock
            // prolungati su tabelle grandi.
            $this->batch_delete_old(self::table_pageviews(), $retention_days);
            if ($this->downloads_table_exists()) {
                $this->batch_delete_old(self::table_downloads(), $retention_days);
            }
            if ($this->events_table_exists()) {
                $this->batch_delete_old(self::table_events(), $retention_days);
            }
        }
    }

    /**
     * Elimina a blocchi da 5000 le righe piu' vecchie di N giorni.
     */
    private function batch_delete_old(string $table, int $days): void {
        global $wpdb;
        $days = intval($days);
        do {
            $deleted = $wpdb->query(
                "DELETE FROM {$table}
                 WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$days} DAY)
                 LIMIT 5000"
            );
        } while ($deleted === 5000);
    }

    private function downloads_table_exists(): bool {
        global $wpdb;
        $table = self::table_downloads();
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    // =========================================================================
    // FASE 3 — Tabella Eventi
    // =========================================================================

    public static function table_events(): string {
        global $wpdb;
        return $wpdb->prefix . 'dbsa_events';
    }

    public function create_events_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table           = self::table_events();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL DEFAULT '',
            event_data VARCHAR(2083) NOT NULL DEFAULT '',
            page_url VARCHAR(2083) NOT NULL DEFAULT '',
            visitor_hash VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_type_date (event_type, created_at),
            INDEX idx_created (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function insert_event(array $data) {
        global $wpdb;

        $this->ensure_tables();

        $inserted = $wpdb->insert(
            self::table_events(),
            array(
                'event_type'   => substr(sanitize_key($data['event_type'] ?? ''), 0, 50),
                'event_data'   => substr(sanitize_text_field($data['event_data'] ?? ''), 0, 2083),
                'page_url'     => substr(sanitize_url($data['page_url'] ?? ''), 0, 2083),
                'visitor_hash' => sanitize_text_field($data['visitor_hash'] ?? ''),
                'created_at'   => current_time('mysql', true),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    public function get_events_total(string $from, string $to, string $event_type = ''): int {
        global $wpdb;
        $table = self::table_events();

        if ($event_type) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE event_type = %s AND created_at BETWEEN %s AND %s",
                $event_type,
                self::utc_start($from),
                self::utc_end($to)
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE created_at BETWEEN %s AND %s",
            self::utc_start($from),
            self::utc_end($to)
        ));
    }

    public function get_events_breakdown(string $from, string $to): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, event_data, COUNT(*) AS total
             FROM {$table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY event_type, event_data
             ORDER BY event_type, total DESC",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    public function get_outbound_links(string $from, string $to, int $limit = 20): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_data AS url, COUNT(*) AS clicks
             FROM {$table}
             WHERE event_type = 'outbound_click'
               AND created_at BETWEEN %s AND %s
             GROUP BY event_data
             ORDER BY clicks DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    public function get_scroll_depth_summary(string $from, string $to): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_data AS depth, COUNT(DISTINCT visitor_hash) AS users
             FROM {$table}
             WHERE event_type = 'scroll_depth'
               AND created_at BETWEEN %s AND %s
             GROUP BY event_data
             ORDER BY CAST(event_data AS UNSIGNED) ASC",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    public function get_events_for_export(string $from, string $to): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT created_at, event_type, event_data, page_url
             FROM {$table}
             WHERE created_at BETWEEN %s AND %s
             ORDER BY created_at DESC
             LIMIT 50000",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Top termini cercati nella ricerca interna (v3.3.0).
     */
    public function get_top_searches(string $from, string $to, int $limit = 20): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_data AS term, COUNT(*) AS searches, COUNT(DISTINCT visitor_hash) AS visitors
             FROM {$table}
             WHERE event_type = 'search'
               AND created_at BETWEEN %s AND %s
             GROUP BY event_data
             ORDER BY searches DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    /**
     * Anteprime di condivisione per piattaforma (v3.3.0).
     */
    public function get_share_preview_networks(string $from, string $to): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_data AS network, COUNT(*) AS total
             FROM {$table}
             WHERE event_type = 'share_preview'
               AND created_at BETWEEN %s AND %s
             GROUP BY event_data
             ORDER BY total DESC",
            self::utc_start($from),
            self::utc_end($to)
        ), ARRAY_A);
    }

    /**
     * Pagine più condivise, stimate dalle anteprime generate (v3.3.0).
     */
    public function get_top_shared_pages(string $from, string $to, int $limit = 10): array {
        global $wpdb;
        $table = self::table_events();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT page_url, COUNT(*) AS total
             FROM {$table}
             WHERE event_type = 'share_preview'
               AND created_at BETWEEN %s AND %s
             GROUP BY page_url
             ORDER BY total DESC
             LIMIT %d",
            self::utc_start($from),
            self::utc_end($to),
            $limit
        ), ARRAY_A);
    }

    private function events_table_exists(): bool {
        global $wpdb;
        $table = self::table_events();
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    // =========================================================================
    // v3.3.0 — Migrazione referrer_host
    // =========================================================================

    /**
     * Calcola referrer_host sulle righe registrate prima dello schema 4.
     * Cron one-off a blocchi con cursore su id: si riprogramma finché serve.
     */
    public function backfill_referrer_host(): void {
        global $wpdb;
        $table  = self::table_pageviews();
        $cursor = (int) get_option('dbsa_backfill_cursor', 0);

        for ($batch = 0; $batch < 20; $batch++) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, referrer FROM {$table}
                 WHERE id > %d AND referrer != '' AND referrer_host = ''
                 ORDER BY id ASC
                 LIMIT 1000",
                $cursor
            ), ARRAY_A);

            if (empty($rows)) {
                delete_option('dbsa_backfill_cursor');
                self::flush_cache();
                return;
            }

            // Un UPDATE per host invece che per riga
            $ids_by_host = array();
            foreach ($rows as $row) {
                $host = DBSA_Tracker::normalize_referrer_host($row['referrer']);
                if ($host !== '') {
                    $ids_by_host[$host][] = (int) $row['id'];
                }
                $cursor = (int) $row['id'];
            }
            foreach ($ids_by_host as $host => $ids) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET referrer_host = %s WHERE id IN (" . implode(',', $ids) . ')',
                    $host
                ));
            }

            update_option('dbsa_backfill_cursor', $cursor, false);
        }

        // Tabella grande: continua al prossimo giro
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::BACKFILL_HOOK);
    }

    // =========================================================================
    // v3.3.0 — Bonifica storico
    // Le righe registrate prima dei filtri (404, scanner, ricerche) vengono
    // marcate in is_bot, non cancellate: le query di lettura filtrano già
    // is_bot = 0, quindi l'operazione è reversibile e il rumore resta misurabile.
    // =========================================================================

    /**
     * Regole di bonifica: etichetta, condizione SQL già preparata, colonna
     * per gli esempi, flag da assegnare.
     */
    private function noise_rules(): array {
        global $wpdb;

        $scanner = array();
        foreach (array('/.env', '/.git', '/wp-admin', '/wp-includes', '/wp-content/', '/cgi-bin', '/phpmyadmin', '/vendor/', '.sql', '.bak', '.asp', '.jsp') as $needle) {
            $scanner[] = $wpdb->prepare('page_url LIKE %s', '%' . $wpdb->esc_like($needle) . '%');
        }
        // .php: nessuna pagina WordPress lo contiene, salvo i permalink PATHINFO (/index.php/...)
        $scanner[] = $wpdb->prepare('(page_url LIKE %s AND page_url NOT LIKE %s)', '%.php%', '%/index.php/%');

        $search = array();
        foreach (array_unique(array('Ricerca: ', __('Ricerca', 'db-site-analytics') . ': ')) as $prefix) {
            $search[] = $wpdb->prepare('page_title LIKE %s', $wpdb->esc_like($prefix) . '%');
        }

        return array(
            'search'   => array(
                'label' => __('Ricerche interne registrate come pagine', 'db-site-analytics'),
                'where' => '(' . implode(' OR ', $search) . ')',
                'group' => 'page_title',
                'flag'  => self::FLAG_SEARCH,
            ),
            'scanner'  => array(
                'label' => __('Percorsi da scanner (.env, .php, wp-admin, .git…)', 'db-site-analytics'),
                'where' => '(' . implode(' OR ', $scanner) . ')',
                'group' => 'page_url',
                'flag'  => self::FLAG_NOISE,
            ),
            'untitled' => array(
                'label' => __('Pagine senza titolo (404, favicon, URL inesistenti)', 'db-site-analytics'),
                'where' => "page_title = ''",
                'group' => 'page_url',
                'flag'  => self::FLAG_NOISE,
            ),
        );
    }

    /**
     * Anteprima bonifica: righe interessate e valori più frequenti per regola.
     */
    public function get_noise_report(): array {
        global $wpdb;
        $table  = self::table_pageviews();
        $report = array();

        foreach ($this->noise_rules() as $key => $rule) {
            $report[$key] = array(
                'label'   => $rule['label'],
                'count'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_bot = 0 AND {$rule['where']}"),
                'samples' => $wpdb->get_results(
                    "SELECT {$rule['group']} AS label, COUNT(*) AS total
                     FROM {$table}
                     WHERE is_bot = 0 AND {$rule['where']}
                     GROUP BY {$rule['group']}
                     ORDER BY total DESC
                     LIMIT 5",
                    ARRAY_A
                ),
            );
        }

        return $report;
    }

    /**
     * Righe già marcate dalla bonifica.
     */
    public function get_marked_count(): int {
        global $wpdb;
        $table = self::table_pageviews();

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE is_bot IN (" . self::FLAG_NOISE . ', ' . self::FLAG_SEARCH . ')'
        );
    }

    /**
     * Marca le righe delle regole scelte. Ritorna il numero di righe marcate.
     */
    public function apply_noise_rules(array $keys): int {
        global $wpdb;
        $table  = self::table_pageviews();
        $marked = 0;

        foreach ($this->noise_rules() as $key => $rule) {
            if (!in_array($key, $keys, true)) {
                continue;
            }
            $flag = (int) $rule['flag'];
            do {
                $updated = (int) $wpdb->query(
                    "UPDATE {$table} SET is_bot = {$flag} WHERE is_bot = 0 AND {$rule['where']} LIMIT 5000"
                );
                $marked += $updated;
            } while ($updated === 5000);
        }

        self::flush_cache();
        return $marked;
    }

    /**
     * Annulla la bonifica: tutte le righe marcate tornano nelle statistiche.
     */
    public function restore_noise(): int {
        global $wpdb;
        $table = self::table_pageviews();

        $restored = (int) $wpdb->query(
            "UPDATE {$table} SET is_bot = 0 WHERE is_bot IN (" . self::FLAG_NOISE . ', ' . self::FLAG_SEARCH . ')'
        );

        self::flush_cache();
        return $restored;
    }

    // =========================================================================
    // Cache statistiche
    // =========================================================================

    /**
     * Chiave transient versionata: flush_cache() invalida tutte le cache
     * statistiche (funziona anche con object cache persistente).
     */
    public static function cache_key(string $prefix, string $key): string {
        return $prefix . md5(get_option('dbsa_cache_gen', '0') . '|' . $key);
    }

    public static function flush_cache(): void {
        update_option('dbsa_cache_gen', (string) microtime(true), false);
    }

    /**
     * Rimuove tutte le tabelle (usato da uninstall.php).
     */
    public static function drop_tables(): void {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "dbsa_pageviews");
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "dbsa_downloads");
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "dbsa_events");
    }
}
