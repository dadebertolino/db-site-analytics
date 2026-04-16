<?php
/**
 * DBSA_REST_API — Endpoint REST autenticato per statistiche.
 *
 * GET /wp-json/dbsa/v1/stats
 * Parametri: from (Y-m-d), to (Y-m-d), metric (pageviews|visitors|downloads|events|all)
 *
 * GET /wp-json/dbsa/v1/stats/pages
 * GET /wp-json/dbsa/v1/stats/referrers
 * GET /wp-json/dbsa/v1/stats/devices
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_REST_API {

    private static $instance = null;
    const NAMESPACE = 'dbsa/v1';

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function register_routes(): void {

        // Stats generali
        register_rest_route(self::NAMESPACE, '/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args() + array(
                'metric' => array(
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_key',
                    'enum'              => array('pageviews', 'visitors', 'downloads', 'events', 'all'),
                ),
            ),
        ));

        // Top pagine
        register_rest_route(self::NAMESPACE, '/stats/pages', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_top_pages'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args() + array(
                'limit' => array(
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Top referrer
        register_rest_route(self::NAMESPACE, '/stats/referrers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_top_referrers'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args() + array(
                'limit' => array(
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Breakdown device/browser/OS
        register_rest_route(self::NAMESPACE, '/stats/devices', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_device_stats'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args(),
        ));

        // Serie giornaliera
        register_rest_route(self::NAMESPACE, '/stats/daily', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_daily_series'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args(),
        ));
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    public function get_stats(WP_REST_Request $request): WP_REST_Response {
        $from   = $request->get_param('from');
        $to     = $request->get_param('to');
        $metric = $request->get_param('metric');
        $db     = DBSA_DB::instance();

        $data = array(
            'period' => array('from' => $from, 'to' => $to),
        );

        if ($metric === 'all' || $metric === 'pageviews' || $metric === 'visitors') {
            $stats = $db->get_stats($from, $to);
            if ($metric === 'pageviews') {
                $data['pageviews'] = (int) $stats['pageviews'];
            } elseif ($metric === 'visitors') {
                $data['visitors'] = (int) $stats['visitors'];
            } else {
                $data['pageviews'] = (int) $stats['pageviews'];
                $data['visitors']  = (int) $stats['visitors'];
            }
        }

        if ($metric === 'all' || $metric === 'downloads') {
            $data['downloads'] = $db->get_downloads_total($from, $to);
        }

        if ($metric === 'all' || $metric === 'events') {
            $data['events'] = $db->get_events_total($from, $to);
        }

        return new WP_REST_Response($data, 200);
    }

    public function get_top_pages(WP_REST_Request $request): WP_REST_Response {
        $from  = $request->get_param('from');
        $to    = $request->get_param('to');
        $limit = min(absint($request->get_param('limit')), 100);

        $rows = DBSA_DB::instance()->get_top_pages($from, $to, $limit);

        return new WP_REST_Response(array(
            'period' => array('from' => $from, 'to' => $to),
            'data'   => array_map(function($r) {
                return array(
                    'url'       => $r['page_url'],
                    'title'     => $r['page_title'],
                    'pageviews' => (int) $r['pageviews'],
                    'visitors'  => (int) $r['visitors'],
                );
            }, $rows),
        ), 200);
    }

    public function get_top_referrers(WP_REST_Request $request): WP_REST_Response {
        $from  = $request->get_param('from');
        $to    = $request->get_param('to');
        $limit = min(absint($request->get_param('limit')), 100);

        $rows = DBSA_DB::instance()->get_top_referrers($from, $to, $limit);

        return new WP_REST_Response(array(
            'period' => array('from' => $from, 'to' => $to),
            'data'   => array_map(function($r) {
                return array(
                    'referrer'  => $r['referrer'],
                    'host'      => parse_url($r['referrer'], PHP_URL_HOST) ?: $r['referrer'],
                    'pageviews' => (int) $r['pageviews'],
                );
            }, $rows),
        ), 200);
    }

    public function get_device_stats(WP_REST_Request $request): WP_REST_Response {
        $from = $request->get_param('from');
        $to   = $request->get_param('to');
        $db   = DBSA_DB::instance();

        return new WP_REST_Response(array(
            'period'   => array('from' => $from, 'to' => $to),
            'devices'  => $db->get_device_breakdown($from, $to),
            'browsers' => $db->get_browser_breakdown($from, $to),
            'os'       => $db->get_os_breakdown($from, $to),
        ), 200);
    }

    public function get_daily_series(WP_REST_Request $request): WP_REST_Response {
        $from = $request->get_param('from');
        $to   = $request->get_param('to');

        $rows = DBSA_DB::instance()->get_daily_views($from, $to);

        return new WP_REST_Response(array(
            'period' => array('from' => $from, 'to' => $to),
            'data'   => array_map(function($r) {
                return array(
                    'date'      => $r['day'],
                    'pageviews' => (int) $r['pageviews'],
                    'visitors'  => (int) $r['visitors'],
                );
            }, $rows),
        ), 200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function check_permission(): bool {
        return current_user_can('manage_options');
    }

    private function date_args(): array {
        return array(
            'from' => array(
                'default'           => gmdate('Y-m-d', strtotime('-29 days')),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_date'),
            ),
            'to' => array(
                'default'           => gmdate('Y-m-d'),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_date'),
            ),
        );
    }

    public function validate_date(string $value): bool {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
