<?php
/**
 * DBSA_Downloader — Tracciamento click su link a file scaricabili.
 *
 * Inietta un piccolo JS frontend (~1KB) che intercetta i click su link
 * con estensioni configurabili e li invia via AJAX al server.
 * L'IP non viene mai salvato.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Downloader {

    private static $instance = null;

    /** Estensioni tracciate di default. */
    const DEFAULT_EXTENSIONS = 'pdf,zip,docx,xlsx,pptx,mp3,mp4,rar,7z,tar,gz,exe,dmg';

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts',          array($this, 'enqueue_frontend_script'));
        add_action('wp_ajax_dbsa_track_download',        array($this, 'ajax_track_download'));
        add_action('wp_ajax_nopriv_dbsa_track_download', array($this, 'ajax_track_download'));
    }

    // -------------------------------------------------------------------------
    // Frontend: script leggero
    // -------------------------------------------------------------------------

    public function enqueue_frontend_script(): void {
        $settings = get_option('dbsa_settings', array());

        // Se il tracking download è disabilitato, non caricare nulla
        if (empty($settings['track_downloads'])) {
            return;
        }

        $extensions = sanitize_text_field($settings['download_extensions'] ?? self::DEFAULT_EXTENSIONS);
        $ext_array  = array_filter(array_map('trim', explode(',', strtolower($extensions))));

        wp_enqueue_script(
            'dbsa-downloader',
            DBSA_PLUGIN_URL . 'assets/js/downloader.js',
            array(),
            DBSA_VERSION,
            true
        );

        wp_localize_script('dbsa-downloader', 'dbsaDl', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'extensions' => array_values($ext_array),
        ));
    }

    // -------------------------------------------------------------------------
    // AJAX handler
    // -------------------------------------------------------------------------

    public function ajax_track_download(): void {
        // v3.1.0 — Niente nonce: con il page caching il nonce cachato scade
        // e il tracking fallisce silenziosamente. L'endpoint e' anonimo e
        // write-only: protezione via rate limiting per IP + validazione.
        if (!DBSA_Visitor::check_rate_limit('download', 20, 60)) {
            wp_send_json_error('Rate limit exceeded', 429);
        }

        $file_url = esc_url_raw(wp_unslash($_POST['file_url'] ?? ''));
        $page_url = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));

        if (empty($file_url) || !filter_var($file_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid file_url', 400);
        }

        // L'estensione deve essere tra quelle configurate
        $settings   = get_option('dbsa_settings', array());
        $extensions = strtolower(sanitize_text_field($settings['download_extensions'] ?? self::DEFAULT_EXTENSIONS));
        $allowed    = array_filter(array_map('trim', explode(',', $extensions)));
        $path       = (string) wp_parse_url($file_url, PHP_URL_PATH);
        $ext        = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            wp_send_json_error('Extension not tracked', 400);
        }

        $file_name = basename($path);

        DBSA_DB::instance()->insert_download(array(
            'file_url'     => $file_url,
            'file_name'    => $file_name,
            'page_url'     => $page_url,
            'visitor_hash' => DBSA_Visitor::generate_hash(),
        ));

        wp_send_json_success();
    }

}
