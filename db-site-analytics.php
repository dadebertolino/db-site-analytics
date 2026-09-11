<?php
/**
 * Plugin Name:       DB Site Analytics
 * Plugin URI:        https://www.davidebertolino.it/progetti/db-site-analytics/
 * Description:       Tracciamento visite server-side senza cookie, senza JavaScript di tracking, senza servizi esterni. GDPR compliant by design.
 * Version:           3.2.0
 * Author:            Davide Bertolino
 * Author URI:        https://www.davidebertolino.it
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       db-site-analytics
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) exit;

// Costanti
define('DBSA_VERSION',    '3.2.0');
define('DBSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DBSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DBSA_PLUGIN_FILE', __FILE__);

// Autoload classi
require_once DBSA_PLUGIN_DIR . 'inc/class-mmdb-reader.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-geoip.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-visitor.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-db.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-tracker.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-downloader.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-events.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-shortcodes.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-rest-api.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-exporter.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-admin.php';
require_once DBSA_PLUGIN_DIR . 'inc/class-updater.php';

/**
 * Classe principale — Singleton
 */
final class DB_Site_Analytics {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // GitHub auto-updater
        new DB_GitHub_Updater(DBSA_PLUGIN_FILE, 'dadebertolino', 'db-site-analytics');

        // Attivazione / disattivazione
        register_activation_hook(DBSA_PLUGIN_FILE,   array($this, 'activate'));
        register_deactivation_hook(DBSA_PLUGIN_FILE, array($this, 'deactivate'));

        // Init componenti
        add_action('init', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'init_components'));
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('db-site-analytics', false, dirname(plugin_basename(DBSA_PLUGIN_FILE)) . '/languages/');
    }

    public function init_components(): void {
        DBSA_DB::instance();
        DBSA_GeoIP::instance();
        DBSA_Tracker::instance();
        DBSA_Downloader::instance();
        DBSA_Events::instance();
        DBSA_Shortcodes::instance();
        DBSA_REST_API::instance();

        if (is_admin()) {
            DBSA_Admin::instance();
            DBSA_Exporter::instance();
        }
    }

    public function activate(): void {
        DBSA_DB::instance()->upgrade();
        $this->schedule_cron();

        // Genera salt giornaliero iniziale
        if (!get_option('dbsa_daily_salt')) {
            update_option('dbsa_daily_salt', wp_generate_password(32, true, true));
            update_option('dbsa_salt_date',  gmdate('Y-m-d'));
        }

        // Impostazioni di default
        if (!get_option('dbsa_settings')) {
            update_option('dbsa_settings', array(
                'exclude_admins'      => 1,
                'exclude_paths'       => '',
                'exclude_roles'       => array('administrator'),
                'retention_days'      => 90,
                'track_logged_in'     => 0,
                'track_downloads'     => 0,
                'download_extensions' => DBSA_Downloader::DEFAULT_EXTENSIONS,
                'track_outbound'      => 0,
                'track_scroll'        => 0,
                'trust_proxy'         => 0,
                'enable_geoip'        => 0,
                'track_searches'      => 1,
                'track_share_previews' => 1,
            ));
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook('dbsa_daily_cron');
        wp_clear_scheduled_hook(DBSA_DB::BACKFILL_HOOK);
    }

    private function schedule_cron(): void {
        if (!wp_next_scheduled('dbsa_daily_cron')) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'dbsa_daily_cron');
        }
    }
}

// Boot
DB_Site_Analytics::instance();
