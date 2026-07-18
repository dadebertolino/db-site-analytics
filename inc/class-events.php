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
        // v3.1.0 — Niente nonce (vedi DBSA_Downloader): rate limit + validazione stretta.
        if (!DBSA_Visitor::check_rate_limit('event', 30, 60)) {
            wp_send_json_error('Rate limit exceeded', 429);
        }

        $event_type = sanitize_key($_POST['event_type'] ?? '');
        $event_data = sanitize_text_field(wp_unslash($_POST['event_data'] ?? ''));
        $page_url   = esc_url_raw(wp_unslash($_POST['page_url'] ?? ''));

        // Validazione per tipo evento
        if ($event_type === 'scroll_depth') {
            if (!in_array($event_data, array('25%', '50%', '75%', '100%'), true)) {
                wp_send_json_error('Invalid scroll depth', 400);
            }
        } elseif ($event_type === 'outbound_click') {
            $event_data = esc_url_raw($event_data);
            $host       = (string) wp_parse_url($event_data, PHP_URL_HOST);
            $home_host  = (string) wp_parse_url(home_url(), PHP_URL_HOST);
            if (!filter_var($event_data, FILTER_VALIDATE_URL) || $host === '' ||
                $host === $home_host || $host === 'www.' . $home_host) {
                wp_send_json_error('Invalid outbound URL', 400);
            }
        } else {
            wp_send_json_error('Invalid event type', 400);
        }

        DBSA_DB::instance()->insert_event(array(
            'event_type'   => $event_type,
            'event_data'   => substr($event_data, 0, 2083),
            'page_url'     => $page_url,
            'visitor_hash' => DBSA_Visitor::generate_hash(),
        ));

        wp_send_json_success();
    }

}
