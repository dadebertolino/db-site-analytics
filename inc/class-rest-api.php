<?php
/**
 * DBSA_REST_API — Endpoint REST autenticato per statistiche.
 *
 * GET /wp-json/dbsa/v1/stats
 * Parametri: from (Y-m-d), to (Y-m-d), metric (pageviews|visitors|downloads|events|all)
 * Le date sono giorni nel fuso orario del sito (v3.3.0).
 *
 * GET /wp-json/dbsa/v1/stats/pages
 * GET /wp-json/dbsa/v1/stats/referrers
 * GET /wp-json/dbsa/v1/stats/devices
 * GET /wp-json/dbsa/v1/stats/daily
 * GET /wp-json/dbsa/v1/stats/downloads   (v3.2.0)
 * GET /wp-json/dbsa/v1/stats/events      (v3.2.0)
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

        // Top download (v3.2.0)
        register_rest_route(self::NAMESPACE, '/stats/downloads', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_downloads'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args() + array(
                'limit' => array(
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Eventi (v3.2.0)
        register_rest_route(self::NAMESPACE, '/stats/events', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_events'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => $this->date_args() + array(
                'limit' => array(
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ),
            ),
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
                // v3.3.0: aggregato per host normalizzato; 'referrer' mantenuto per compatibilità
                return array(
                    'referrer'  => $r['referrer'],
                    'host'      => $r['referrer'],
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
            'devices'   => $db->get_device_breakdown($from, $to),
            'browsers'  => $db->get_browser_breakdown($from, $to),
            'os'        => $db->get_os_breakdown($from, $to),
            'countries' => $db->get_country_breakdown($from, $to),
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

    public function get_downloads(WP_REST_Request $request): WP_REST_Response {
        $from  = $request->get_param('from');
        $to    = $request->get_param('to');
        $limit = min(absint($request->get_param('limit')), 100);
        $db    = DBSA_DB::instance();

        $rows = $db->get_top_downloads($from, $to, $limit);

        return new WP_REST_Response(array(
            'period' => array('from' => $from, 'to' => $to),
            'total'  => $db->get_downloads_total($from, $to),
            'data'   => array_map(function($r) {
                return array(
                    'file_url'  => $r['file_url'],
                    'file_name' => $r['file_name'],
                    'downloads' => (int) $r['downloads'],
                    'visitors'  => (int) $r['visitors'],
                    'from_page' => $r['from_page'],
                );
            }, $rows),
        ), 200);
    }

    public function get_events(WP_REST_Request $request): WP_REST_Response {
        $from  = $request->get_param('from');
        $to    = $request->get_param('to');
        $limit = min(absint($request->get_param('limit')), 100);
        $db    = DBSA_DB::instance();

        return new WP_REST_Response(array(
            'period'       => array('from' => $from, 'to' => $to),
            'total'        => $db->get_events_total($from, $to),
            'outbound'     => array_map(function($r) {
                return array(
                    'url'    => $r['url'],
                    'clicks' => (int) $r['clicks'],
                );
            }, $db->get_outbound_links($from, $to, $limit)),
            'scroll_depth' => array_map(function($r) {
                return array(
                    'depth' => $r['depth'],
                    'users' => (int) $r['users'],
                );
            }, $db->get_scroll_depth_summary($from, $to)),
            'searches'     => array_map(function($r) {
                return array(
                    'term'     => $r['term'],
                    'searches' => (int) $r['searches'],
                    'visitors' => (int) $r['visitors'],
                );
            }, $db->get_top_searches($from, $to, $limit)),
            'share_previews' => array(
                'networks' => array_map(function($r) {
                    return array(
                        'network' => $r['network'],
                        'total'   => (int) $r['total'],
                    );
                }, $db->get_share_preview_networks($from, $to)),
                'pages'    => array_map(function($r) {
                    return array(
                        'url'   => $r['page_url'],
                        'total' => (int) $r['total'],
                    );
                }, $db->get_top_shared_pages($from, $to, $limit)),
            ),
        ), 200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function check_permission(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }
        // Schema aggiornato prima delle query (es. subito dopo un update del plugin)
        DBSA_DB::instance()->ensure_tables();
        return true;
    }

    private function date_args(): array {
        return array(
            'from' => array(
                'default'           => gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' -29 days')),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_date'),
            ),
            'to' => array(
                'default'           => current_time('Y-m-d'),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_date'),
            ),
        );
    }

    public function validate_date($value): bool {
        // Niente type hint: ?from[]=x arriva come array e causerebbe un TypeError (500)
        if (!is_string($value)) {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
