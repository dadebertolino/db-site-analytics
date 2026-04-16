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
            'nonce'      => wp_create_nonce('dbsa_download_nonce'),
            'extensions' => array_values($ext_array),
        ));
    }

    // -------------------------------------------------------------------------
    // AJAX handler
    // -------------------------------------------------------------------------

    public function ajax_track_download(): void {
        check_ajax_referer('dbsa_download_nonce', 'nonce');

        $file_url = esc_url_raw(wp_unslash($_POST['file_url'] ?? ''));
        $page_url = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));

        if (empty($file_url)) {
            wp_send_json_error('Missing file_url', 400);
        }

        // Deriva nome file dall'URL
        $file_name = basename(parse_url($file_url, PHP_URL_PATH) ?? '');

        // Genera hash visitatore (stesso meccanismo del tracker)
        $visitor_hash = $this->generate_visitor_hash();

        DBSA_DB::instance()->insert_download(array(
            'file_url'     => $file_url,
            'file_name'    => $file_name,
            'page_url'     => $page_url,
            'visitor_hash' => $visitor_hash,
        ));

        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function generate_visitor_hash(): string {
        $ip   = $this->get_client_ip();
        $ua   = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $today     = gmdate('Y-m-d');
        $salt_date = get_option('dbsa_salt_date', '');
        if ($salt_date !== $today) {
            $salt = wp_generate_password(32, true, true);
            update_option('dbsa_daily_salt', $salt);
            update_option('dbsa_salt_date',  $today);
        } else {
            $salt = get_option('dbsa_daily_salt', wp_generate_password(32, true, true));
        }

        return hash('sha256', $ip . $ua . $salt);
    }

    private function get_client_ip(): string {
        $headers = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', sanitize_text_field(wp_unslash($_SERVER[$h])))[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}
