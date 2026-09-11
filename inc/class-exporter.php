<?php
/**
 * DBSA_Exporter — Export CSV di pageview e download.
 *
 * Agganciato ad admin_init per gestire il download prima dell'output HTML.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Exporter {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'handle_export'));
    }

    /**
     * Gestisce la richiesta di export prima di qualsiasi output HTML.
     */
    public function handle_export(): void {
        if (!isset($_GET['dbsa_export'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permesso negato.', 'db-site-analytics'));
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'dbsa_export')) {
            wp_die(esc_html__('Nonce non valido.', 'db-site-analytics'));
        }

        $type = sanitize_key($_GET['dbsa_export']); // 'pageviews' | 'downloads' | 'events'

        // v3.3.0 — Date validate: finiscono anche nel nome del file
        list($from, $to) = DBSA_Admin::get_date_range(
            sanitize_text_field(wp_unslash($_GET['from'] ?? '')),
            sanitize_text_field(wp_unslash($_GET['to'] ?? ''))
        );

        if ($type === 'pageviews') {
            $this->export_pageviews($from, $to);
        } elseif ($type === 'downloads') {
            $this->export_downloads($from, $to);
        } elseif ($type === 'events') {
            $this->export_events($from, $to);
        }
    }

    // -------------------------------------------------------------------------
    // Export pageviews
    // -------------------------------------------------------------------------

    private function export_pageviews(string $from, string $to): void {
        $rows    = DBSA_DB::instance()->get_pageviews_for_export($from, $to);
        $filename = 'dbsa-pageviews-' . $from . '-' . $to . '.csv';

        $this->send_csv_headers($filename);

        $out = fopen('php://output', 'w');

        // BOM UTF-8 per compatibilità Excel
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array(
            'Data/Ora (fuso del sito)', 'URL Pagina', 'Titolo Pagina', 'Referrer', 'Host Referrer', 'Dispositivo', 'Browser', 'OS'
        ), ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, array(
                get_date_from_gmt($row['created_at']),
                $row['page_url'],
                $row['page_title'],
                $row['referrer'],
                $row['referrer_host'],
                $row['device_type'],
                $row['browser'],
                $row['os'],
            ), ';', '"', '');
        }

        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // Export downloads
    // -------------------------------------------------------------------------

    private function export_downloads(string $from, string $to): void {
        $rows     = DBSA_DB::instance()->get_downloads_for_export($from, $to);
        $filename = 'dbsa-downloads-' . $from . '-' . $to . '.csv';

        $this->send_csv_headers($filename);

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array(
            'Data/Ora (fuso del sito)', 'Nome File', 'URL File', 'Pagina di Provenienza'
        ), ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, array(
                get_date_from_gmt($row['created_at']),
                $row['file_name'],
                $row['file_url'],
                $row['page_url'],
            ), ';', '"', '');
        }

        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function export_events(string $from, string $to): void {
        $rows     = DBSA_DB::instance()->get_events_for_export($from, $to);
        $filename = 'dbsa-events-' . $from . '-' . $to . '.csv';

        $this->send_csv_headers($filename);

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array(
            'Data/Ora (fuso del sito)', 'Tipo Evento', 'Dato Evento', 'Pagina'
        ), ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, array(
                get_date_from_gmt($row['created_at']),
                $row['event_type'],
                $row['event_data'],
                $row['page_url'],
            ), ';', '"', '');
        }

        fclose($out);
        exit;
    }

    private function send_csv_headers(string $filename): void {
        // Pulisce qualsiasi output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
