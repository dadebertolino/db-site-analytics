<?php
/**
 * DBSA_Shortcodes — Shortcode contatore visite.
 *
 * [dbsa_views]                  — visite alla pagina corrente (ultimi 30gg)
 * [dbsa_views page_id="123"]    — visite a una pagina specifica
 * [dbsa_views period="7"]       — ultimi N giorni
 * [dbsa_views type="visitors"]  — visitatori unici invece di pageview
 * [dbsa_views format="compact"] — numero senza testo aggiuntivo
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Shortcodes {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('dbsa_views', array($this, 'render_views'));
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_style'));
    }

    /**
     * Registrato su tutte le pagine, caricato solo dove lo shortcode è usato.
     */
    public function register_frontend_style(): void {
        wp_register_style(
            'dbsa-frontend',
            DBSA_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            DBSA_VERSION
        );
    }

    /**
     * [dbsa_views page_id="" period="30" type="pageviews" format="full"]
     */
    public function render_views($atts): string {
        // Niente type hint: WP ≤ 6.4 passa '' (stringa) se lo shortcode non ha attributi
        wp_enqueue_style('dbsa-frontend');

        $atts = shortcode_atts(array(
            'page_id' => '',
            'period'  => 30,
            'type'    => 'pageviews',   // pageviews | visitors
            'format'  => 'full',        // full | compact | number
        ), $atts, 'dbsa_views');

        $period = absint($atts['period']);
        if ($period < 1)   $period = 30;
        if ($period > 365) $period = 365;

        $type   = in_array($atts['type'], array('pageviews', 'visitors'), true) ? $atts['type'] : 'pageviews';
        $format = sanitize_key($atts['format']);

        // Determina URL pagina da cercare
        $page_url = '';
        if (!empty($atts['page_id'])) {
            $page_url = get_permalink(absint($atts['page_id']));
            if (!$page_url) return '';
        } else {
            // Pagina corrente, calcolata come fa il tracker
            $page_url = DBSA_Tracker::get_current_url();
        }

        // v3.1.0 — Cache 10 min: evita una query COUNT a ogni render
        $cache_key = DBSA_DB::cache_key('dbsa_sc_', $page_url . '|' . $period . '|' . $type);
        $count     = get_transient($cache_key);
        if (false === $count) {
            $count = $this->get_view_count($page_url, $period, $type);
            set_transient($cache_key, $count, 10 * MINUTE_IN_SECONDS);
        }
        $count = (int) $count;

        return $this->render_output($count, $type, $period, $format);
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    private function get_view_count(string $page_url, int $period, string $type): int {
        global $wpdb;
        $table = DBSA_DB::table_pageviews();

        // Oggi incluso: period=30 → 30 giorni locali, come la dashboard
        $today = current_time('Y-m-d');
        $from  = DBSA_DB::utc_start(gmdate('Y-m-d', strtotime($today . ' -' . ($period - 1) . ' days')));
        $to    = DBSA_DB::utc_end($today);

        // Normalizza URL: cerca con e senza trailing slash
        $url_no_slash   = rtrim($page_url, '/');
        $url_with_slash = $url_no_slash . '/';

        if ($type === 'visitors') {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT visitor_hash)
                 FROM {$table}
                 WHERE is_bot = 0
                   AND (page_url = %s OR page_url = %s)
                   AND created_at BETWEEN %s AND %s",
                $url_no_slash,
                $url_with_slash,
                $from,
                $to
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE is_bot = 0
                   AND (page_url = %s OR page_url = %s)
                   AND created_at BETWEEN %s AND %s",
                $url_no_slash,
                $url_with_slash,
                $from,
                $to
            ));
        }

        return (int) $count;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    private function render_output(int $count, string $type, int $period, string $format): string {
        $count_formatted = number_format_i18n($count);

        if ($format === 'number') {
            return '<span class="dbsa-views-count">' . esc_html($count_formatted) . '</span>';
        }

        if ($format === 'compact') {
            $label = $type === 'visitors'
                ? _n('visitatore', 'visitatori', $count, 'db-site-analytics')
                : _n('visita', 'visite', $count, 'db-site-analytics');

            return sprintf(
                '<span class="dbsa-views-compact"><span class="dbsa-views-count">%s</span> %s</span>',
                esc_html($count_formatted),
                esc_html($label)
            );
        }

        // format = full (default)
        if ($type === 'visitors') {
            $text = sprintf(
                /* translators: 1: numero formattato, 2: giorni del periodo */
                _n(
                    '<strong>%1$s</strong> visitatore unico negli ultimi %2$d giorni',
                    '<strong>%1$s</strong> visitatori unici negli ultimi %2$d giorni',
                    $count,
                    'db-site-analytics'
                ),
                esc_html($count_formatted),
                $period
            );
        } else {
            $text = sprintf(
                /* translators: 1: numero formattato, 2: giorni del periodo */
                _n(
                    '<strong>%1$s</strong> visita negli ultimi %2$d giorni',
                    '<strong>%1$s</strong> visite negli ultimi %2$d giorni',
                    $count,
                    'db-site-analytics'
                ),
                esc_html($count_formatted),
                $period
            );
        }

        return '<span class="dbsa-views">' . $text . '</span>';
    }
}
