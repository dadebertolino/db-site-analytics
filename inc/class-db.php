<?php
/**
 * DBSA_DB — Creazione e gestione tabelle database.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_DB {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Cron handler
        add_action('dbsa_daily_cron', array($this, 'daily_maintenance'));
    }

    /**
     * Nome tabella pageviews.
     */
    public static function table_pageviews(): string {
        global $wpdb;
        return $wpdb->prefix . 'dbsa_pageviews';
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

        // Just-in-time: crea tabella se non esiste
        if (!$this->table_exists()) {
            $this->create_tables();
        }

        $inserted = $wpdb->insert(
            self::table_pageviews(),
            array(
                'page_url'     => substr(sanitize_url($data['page_url'] ?? ''), 0, 2083),
                'page_title'   => substr(sanitize_text_field($data['page_title'] ?? ''), 0, 255),
                'referrer'     => substr(sanitize_url($data['referrer'] ?? ''), 0, 2083),
                'visitor_hash' => sanitize_text_field($data['visitor_hash'] ?? ''),
                'device_type'  => sanitize_key($data['device_type'] ?? 'desktop'),
                'browser'      => substr(sanitize_text_field($data['browser'] ?? ''), 0, 50),
                'os'           => substr(sanitize_text_field($data['os'] ?? ''), 0, 50),
                'country'      => substr(sanitize_key($data['country'] ?? ''), 0, 2),
                'is_bot'       => absint($data['is_bot'] ?? 0),
                'created_at'   => current_time('mysql', true), // UTC
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ), ARRAY_A);

        return $totals ?? array('pageviews' => 0, 'visitors' => 0);
    }

    /**
     * Visite giornaliere in un intervallo (per grafico).
     */
    public function get_daily_views(string $from, string $to): array {
        global $wpdb;
        $table = self::table_pageviews();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) AS day,
                COUNT(*) AS pageviews,
                COUNT(DISTINCT visitor_hash) AS visitors
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59',
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
                referrer,
                COUNT(*) AS pageviews
             FROM {$table}
             WHERE is_bot = 0
               AND referrer != ''
               AND created_at BETWEEN %s AND %s
             GROUP BY referrer
             ORDER BY pageviews DESC
             LIMIT %d",
            $from . ' 00:00:00',
            $to . ' 23:59:59',
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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

        if (!$this->downloads_table_exists()) {
            $this->create_downloads_table();
        }

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
            $from . ' 00:00:00',
            $to . ' 23:59:59',
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
                referrer, device_type, browser, os
             FROM {$table}
             WHERE is_bot = 0
               AND created_at BETWEEN %s AND %s
             ORDER BY created_at DESC
             LIMIT 50000",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ), ARRAY_A);
    }

    /**
     * Manutenzione giornaliera: pulisce anche i download.
     */
    public function daily_maintenance(): void {
        global $wpdb;

        update_option('dbsa_daily_salt', wp_generate_password(32, true, true));
        update_option('dbsa_salt_date',  gmdate('Y-m-d'));

        $settings       = get_option('dbsa_settings', array());
        $retention_days = absint($settings['retention_days'] ?? 90);

        if ($retention_days > 0) {
            $interval = intval($retention_days);
            $wpdb->query(
                "DELETE FROM " . self::table_pageviews() . " WHERE created_at < DATE_SUB(NOW(), INTERVAL {$interval} DAY)"
            );
            if ($this->downloads_table_exists()) {
                $wpdb->query(
                    "DELETE FROM " . self::table_downloads() . " WHERE created_at < DATE_SUB(NOW(), INTERVAL {$interval} DAY)"
                );
            }
            if ($this->events_table_exists()) {
                $wpdb->query(
                    "DELETE FROM " . self::table_events() . " WHERE created_at < DATE_SUB(NOW(), INTERVAL {$interval} DAY)"
                );
            }
        }
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

        if (!$this->events_table_exists()) {
            $this->create_events_table();
        }

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
                $from . ' 00:00:00',
                $to . ' 23:59:59'
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE created_at BETWEEN %s AND %s",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59',
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
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
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ), ARRAY_A);
    }

    private function events_table_exists(): bool {
        global $wpdb;
        $table = self::table_events();
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
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
