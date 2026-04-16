<?php
/**
 * DBSA_Events — Tracking eventi custom (outbound links, scroll depth).
 *
 * Tabella: {prefix}dbsa_events
 * Tipi evento: outbound_click, scroll_depth
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Events {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts',          array($this, 'enqueue_frontend_script'));
        add_action('wp_ajax_dbsa_track_event',        array($this, 'ajax_track_event'));
        add_action('wp_ajax_nopriv_dbsa_track_event', array($this, 'ajax_track_event'));
    }

    // -------------------------------------------------------------------------
    // Frontend script
    // -------------------------------------------------------------------------

    public function enqueue_frontend_script(): void {
        $settings = get_option('dbsa_settings', array());

        $track_outbound = !empty($settings['track_outbound']);
        $track_scroll   = !empty($settings['track_scroll']);

        if (!$track_outbound && !$track_scroll) {
            return;
        }

        wp_enqueue_script(
            'dbsa-events',
            DBSA_PLUGIN_URL . 'assets/js/events.js',
            array(),
            DBSA_VERSION,
            true
        );

        wp_localize_script('dbsa-events', 'dbsaEv', array(
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('dbsa_event_nonce'),
            'home_url'        => home_url(),
            'track_outbound'  => $track_outbound,
            'track_scroll'    => $track_scroll,
            'scroll_thresholds' => array(25, 50, 75, 100),
        ));
    }

    // -------------------------------------------------------------------------
    // AJAX handler
    // -------------------------------------------------------------------------

    public function ajax_track_event(): void {
        check_ajax_referer('dbsa_event_nonce', 'nonce');

        $event_type = sanitize_key($_POST['event_type'] ?? '');
        $event_data = sanitize_text_field(wp_unslash($_POST['event_data'] ?? ''));
        $page_url   = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));

        $allowed_types = array('outbound_click', 'scroll_depth');
        if (!in_array($event_type, $allowed_types, true)) {
            wp_send_json_error('Invalid event type', 400);
        }

        $visitor_hash = $this->generate_visitor_hash();

        DBSA_DB::instance()->insert_event(array(
            'event_type'   => $event_type,
            'event_data'   => substr($event_data, 0, 2083),
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
        $salt      = ($salt_date === $today)
            ? get_option('dbsa_daily_salt', wp_generate_password(32, true, true))
            : wp_generate_password(32, true, true);

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
