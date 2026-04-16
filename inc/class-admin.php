<?php
/**
 * DBSA_Admin — Dashboard analytics e pagina impostazioni.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Admin {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu',            array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init',            array($this, 'handle_settings_save'));
        add_action('wp_ajax_dbsa_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_dashboard_setup',    array($this, 'register_dashboard_widget'));
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public function register_menu(): void {
        add_menu_page(
            __('DB Site Analytics', 'db-site-analytics'),
            __('Analytics', 'db-site-analytics'),
            'manage_options',
            'dbsa-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-chart-line',
            80
        );

        add_submenu_page(
            'dbsa-dashboard',
            __('Dashboard', 'db-site-analytics'),
            __('Dashboard', 'db-site-analytics'),
            'manage_options',
            'dbsa-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'dbsa-dashboard',
            __('Download', 'db-site-analytics'),
            __('Download', 'db-site-analytics'),
            'manage_options',
            'dbsa-downloads',
            array($this, 'render_downloads')
        );

        add_submenu_page(
            'dbsa-dashboard',
            __('Eventi', 'db-site-analytics'),
            __('Eventi', 'db-site-analytics'),
            'manage_options',
            'dbsa-events',
            array($this, 'render_events')
        );

        add_submenu_page(
            'dbsa-dashboard',
            __('Impostazioni', 'db-site-analytics'),
            __('Impostazioni', 'db-site-analytics'),
            'manage_options',
            'dbsa-settings',
            array($this, 'render_settings')
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets(string $hook): void {
        // Hook WP per le pagine del plugin:
        // toplevel_page_dbsa-dashboard, dbsa_page_dbsa-downloads,
        // dbsa_page_dbsa-events, dbsa_page_dbsa-settings, index.php (widget)
        $is_dbsa_page = (
            $hook === 'toplevel_page_dbsa-dashboard' ||
            strpos($hook, 'dbsa_page_dbsa-') !== false ||
            $hook === 'index.php'
        );

        if (!$is_dbsa_page) {
            return;
        }

        wp_enqueue_style(
            'db-admin-ui',
            DBSA_PLUGIN_URL . 'assets/css/db-admin-ui.css',
            array(),
            '1.0.0'
        );
        wp_enqueue_style(
            'dbsa-admin',
            DBSA_PLUGIN_URL . 'assets/css/admin.css',
            array('db-admin-ui'),
            DBSA_VERSION
        );

        // Chart.js solo nella dashboard principale
        if ($hook === 'toplevel_page_dbsa-dashboard') {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
            wp_enqueue_script(
                'dbsa-admin',
                DBSA_PLUGIN_URL . 'assets/js/admin.js',
                array('chartjs'),
                DBSA_VERSION,
                true
            );
            wp_localize_script('dbsa-admin', 'dbsa', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('dbsa_nonce'),
                'i18n'     => array(
                    'pageviews' => __('Pageview', 'db-site-analytics'),
                    'visitors'  => __('Visitatori unici', 'db-site-analytics'),
                ),
            ));
        }
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function render_dashboard(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permesso negato.', 'db-site-analytics'));
        }

        // Intervallo date (default: ultimi 30gg)
        $to   = gmdate('Y-m-d');
        $from = gmdate('Y-m-d', strtotime('-29 days'));

        if (!empty($_GET['from']) && !empty($_GET['to'])) {
            $from = sanitize_text_field(wp_unslash($_GET['from']));
            $to   = sanitize_text_field(wp_unslash($_GET['to']));
        }

        $db      = DBSA_DB::instance();
        $today   = gmdate('Y-m-d');
        $week_from = gmdate('Y-m-d', strtotime('-6 days'));

        $stats_today  = $db->get_stats($today, $today);
        $stats_7d     = $db->get_stats($week_from, $today);
        $stats_30d    = $db->get_stats($from, $to);
        $top_pages    = $db->get_top_pages($from, $to, 10);
        $top_referrers = $db->get_top_referrers($from, $to, 10);
        $daily_views  = $db->get_daily_views($from, $to);
        $devices      = $db->get_device_breakdown($from, $to);

        // Fase 2
        $browsers    = $db->get_browser_breakdown($from, $to);
        $os_list     = $db->get_os_breakdown($from, $to);
        $comparison  = $db->get_period_comparison($from, $to);
        $dl_total    = $db->get_downloads_total($from, $to);

        include DBSA_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    // -------------------------------------------------------------------------
    // Downloads page
    // -------------------------------------------------------------------------

    public function render_downloads(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permesso negato.', 'db-site-analytics'));
        }

        $to   = gmdate('Y-m-d');
        $from = gmdate('Y-m-d', strtotime('-29 days'));

        if (!empty($_GET['from']) && !empty($_GET['to'])) {
            $from = sanitize_text_field(wp_unslash($_GET['from']));
            $to   = sanitize_text_field(wp_unslash($_GET['to']));
        }

        $db           = DBSA_DB::instance();
        $top_downloads = $db->get_top_downloads($from, $to, 20);
        $dl_total      = $db->get_downloads_total($from, $to);

        include DBSA_PLUGIN_DIR . 'templates/admin/downloads.php';
    }

    // -------------------------------------------------------------------------
    // Events page
    // -------------------------------------------------------------------------

    public function render_events(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permesso negato.', 'db-site-analytics'));
        }

        $to   = gmdate('Y-m-d');
        $from = gmdate('Y-m-d', strtotime('-29 days'));

        if (!empty($_GET['from']) && !empty($_GET['to'])) {
            $from = sanitize_text_field(wp_unslash($_GET['from']));
            $to   = sanitize_text_field(wp_unslash($_GET['to']));
        }

        $db            = DBSA_DB::instance();
        $outbound      = $db->get_outbound_links($from, $to, 20);
        $scroll_depth  = $db->get_scroll_depth_summary($from, $to);
        $events_total  = $db->get_events_total($from, $to);

        include DBSA_PLUGIN_DIR . 'templates/admin/events.php';
    }

    // -------------------------------------------------------------------------
    // AJAX: dati per chart JS (ricarica con filtro date)
    // -------------------------------------------------------------------------

    public function ajax_get_stats(): void {
        check_ajax_referer('dbsa_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permesso negato.', 403);
        }

        $from = sanitize_text_field(wp_unslash($_POST['from'] ?? gmdate('Y-m-d', strtotime('-29 days'))));
        $to   = sanitize_text_field(wp_unslash($_POST['to']   ?? gmdate('Y-m-d')));

        $db = DBSA_DB::instance();

        wp_send_json_success(array(
            'daily_views' => $db->get_daily_views($from, $to),
            'stats'       => $db->get_stats($from, $to),
            'top_pages'   => $db->get_top_pages($from, $to, 10),
            'referrers'   => $db->get_top_referrers($from, $to, 10),
            'devices'     => $db->get_device_breakdown($from, $to),
            'browsers'    => $db->get_browser_breakdown($from, $to),
            'os_list'     => $db->get_os_breakdown($from, $to),
            'comparison'  => $db->get_period_comparison($from, $to),
            'dl_total'    => $db->get_downloads_total($from, $to),
            'ev_total'    => $db->get_events_total($from, $to),
        ));
    }

    // -------------------------------------------------------------------------
    // Impostazioni
    // -------------------------------------------------------------------------

    public function handle_settings_save(): void {
        if (!isset($_POST['dbsa_save_settings'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('dbsa_settings_nonce');

        $settings = array(
            'exclude_admins'       => isset($_POST['exclude_admins']) ? 1 : 0,
            'track_logged_in'      => isset($_POST['track_logged_in']) ? 1 : 0,
            'exclude_paths'        => sanitize_textarea_field(wp_unslash($_POST['exclude_paths'] ?? '')),
            'exclude_roles'        => array_map('sanitize_key', (array) ($_POST['exclude_roles'] ?? array())),
            'retention_days'       => absint($_POST['retention_days'] ?? 90),
            'track_downloads'      => isset($_POST['track_downloads']) ? 1 : 0,
            'download_extensions'  => sanitize_text_field(wp_unslash($_POST['download_extensions'] ?? DBSA_Downloader::DEFAULT_EXTENSIONS)),
            'track_outbound'       => isset($_POST['track_outbound']) ? 1 : 0,
            'track_scroll'         => isset($_POST['track_scroll']) ? 1 : 0,
        );

        update_option('dbsa_settings', $settings);

        wp_redirect(admin_url('admin.php?page=dbsa-settings&saved=1'));
        exit;
    }

    public function render_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permesso negato.', 'db-site-analytics'));
        }

        $settings = get_option('dbsa_settings', array());
        $saved    = !empty($_GET['saved']);

        include DBSA_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    // -------------------------------------------------------------------------
    // Widget dashboard WP principale
    // -------------------------------------------------------------------------

    public function register_dashboard_widget(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_add_dashboard_widget(
            'dbsa_widget',
            __('DB Site Analytics', 'db-site-analytics'),
            array($this, 'render_dashboard_widget')
        );
    }

    public function render_dashboard_widget(): void {
        $db    = DBSA_DB::instance();
        $today = gmdate('Y-m-d');

        $stats_today     = $db->get_stats($today, $today);
        $stats_yesterday = $db->get_stats(
            gmdate('Y-m-d', strtotime('-1 day')),
            gmdate('Y-m-d', strtotime('-1 day'))
        );
        $stats_7d = $db->get_stats(gmdate('Y-m-d', strtotime('-6 days')), $today);

        $top = $db->get_top_pages($today, $today, 1);
        include DBSA_PLUGIN_DIR . 'templates/admin/widget.php';
    }
}
