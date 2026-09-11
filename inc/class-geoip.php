<?php
/**
 * DBSA_GeoIP — Geolocalizzazione paese self-contained.
 *
 * Usa il database gratuito DB-IP Country Lite (formato MMDB, licenza CC BY 4.0,
 * nessuna registrazione richiesta), scaricato localmente in wp-content/uploads.
 * Il lookup avviene interamente sul server: l'IP non viene mai salvato
 * né inviato a servizi esterni.
 *
 * Attribuzione richiesta dalla licenza: "IP Geolocation by DB-IP"
 * (mostrata nella dashboard admin).
 *
 * @package DB_Site_Analytics
 * @since 3.2.0
 */

if (!defined('ABSPATH')) exit;

class DBSA_GeoIP {

    private static $instance = null;

    /** URL download: %s = anno-mese (es. 2026-07) */
    const DOWNLOAD_URL = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    /** Aggiorna il database se più vecchio di N giorni (DB-IP pubblica mensilmente) */
    const MAX_AGE_DAYS = 35;

    /** @var DBSA_MMDB_Reader|null */
    private $reader = null;

    /** @var bool Reader già inizializzato (anche se fallito) */
    private $reader_loaded = false;

    /** @var array<string,string> Cache lookup per richiesta */
    private $cache = array();

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Aggiornamento automatico agganciato alla manutenzione giornaliera
        add_action('dbsa_daily_cron', array($this, 'maybe_update'), 20);
    }

    // -------------------------------------------------------------------------
    // Lookup
    // -------------------------------------------------------------------------

    /**
     * Codice paese ISO 3166-1 alpha-2 per un IP, o '' se non determinabile.
     * L'IP viene usato solo in memoria, mai persistito.
     */
    public function country_code(string $ip): string {
        if (!$this->is_enabled() || $ip === '' || $ip === '0.0.0.0') {
            return '';
        }

        if (isset($this->cache[$ip])) {
            return $this->cache[$ip];
        }

        $reader = $this->get_reader();
        if (!$reader) {
            return '';
        }

        try {
            $code = $reader->country_code($ip);
        } catch (Exception $e) {
            $code = '';
        }

        $this->cache[$ip] = $code;
        return $code;
    }

    /**
     * Nome localizzato del paese da codice ISO (fallback: il codice stesso).
     */
    public static function country_name(string $code): string {
        if ($code === '') {
            return __('Sconosciuto', 'db-site-analytics');
        }
        if (class_exists('Locale')) {
            $name = Locale::getDisplayRegion('-' . $code, get_locale());
            if ($name && $name !== $code) {
                return $name;
            }
        }
        return $code;
    }

    // -------------------------------------------------------------------------
    // Stato
    // -------------------------------------------------------------------------

    public function is_enabled(): bool {
        $settings = get_option('dbsa_settings', array());
        return !empty($settings['enable_geoip']);
    }

    public function database_path(): string {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . 'dbsa-geoip/dbip-country-lite.mmdb';
    }

    public function database_exists(): bool {
        return is_readable($this->database_path());
    }

    /**
     * Info per la UI impostazioni: presenza, data build, dimensione.
     */
    public function database_info(): array {
        if (!$this->database_exists()) {
            return array('exists' => false);
        }

        $path = $this->database_path();
        $info = array(
            'exists' => true,
            'size'   => (int) filesize($path),
            'mtime'  => (int) filemtime($path),
            'build'  => 0,
        );

        $reader = $this->get_reader();
        if ($reader) {
            $meta          = $reader->get_metadata();
            $info['build'] = $meta['build_epoch'];
            $info['type']  = $meta['database_type'];
        }

        return $info;
    }

    // -------------------------------------------------------------------------
    // Download / aggiornamento
    // -------------------------------------------------------------------------

    /**
     * Cron: aggiorna se abilitato e database assente o più vecchio di MAX_AGE_DAYS.
     */
    public function maybe_update(): void {
        if (!$this->is_enabled()) {
            return;
        }
        if ($this->database_exists()) {
            $age_days = (time() - (int) filemtime($this->database_path())) / DAY_IN_SECONDS;
            if ($age_days < self::MAX_AGE_DAYS) {
                return;
            }
        }
        $this->download();
    }

    /**
     * Scarica il database del mese corrente (fallback: mese precedente,
     * la release mensile può non essere ancora pubblicata).
     *
     * @return true|WP_Error
     */
    public function download() {
        $months = array(
            gmdate('Y-m'),
            gmdate('Y-m', strtotime('first day of last month')),
        );

        $last_error = new WP_Error('dbsa_geoip', __('Download non riuscito.', 'db-site-analytics'));

        foreach ($months as $month) {
            $result = $this->download_month($month);
            if ($result === true) {
                update_option('dbsa_geoip_updated', time(), false);
                delete_option('dbsa_geoip_last_error');
                return true;
            }
            $last_error = $result;
        }

        update_option('dbsa_geoip_last_error', $last_error->get_error_message(), false);
        return $last_error;
    }

    /**
     * @return true|WP_Error
     */
    private function download_month(string $month) {
        $url = sprintf(self::DOWNLOAD_URL, $month);

        // wp_tempnam crea già il file: va eliminato anche se la richiesta fallisce
        $tmp_gz   = wp_tempnam('dbsa-geoip');
        $response = wp_remote_get($url, array(
            'timeout'  => 120,
            'stream'   => true,
            'filename' => $tmp_gz,
        ));

        if (is_wp_error($response)) {
            @unlink($tmp_gz);
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || !is_readable($tmp_gz)) {
            @unlink($tmp_gz);
            return new WP_Error('dbsa_geoip', sprintf(
                /* translators: 1: mese richiesto (YYYY-MM), 2: codice HTTP */
                __('Download %1$s fallito (HTTP %2$d).', 'db-site-analytics'), $month, $code
            ));
        }

        // Decomprimi in streaming (il file scompattato è ~10 MB)
        $dir = dirname($this->database_path());
        if (!wp_mkdir_p($dir)) {
            @unlink($tmp_gz);
            return new WP_Error('dbsa_geoip', __('Impossibile creare la cartella dbsa-geoip.', 'db-site-analytics'));
        }

        $tmp_mmdb = $this->database_path() . '.tmp';
        $gz  = gzopen($tmp_gz, 'rb');
        $out = fopen($tmp_mmdb, 'wb');

        if (!$gz || !$out) {
            if ($gz)  gzclose($gz);
            if ($out) fclose($out);
            @unlink($tmp_gz);
            @unlink($tmp_mmdb);
            return new WP_Error('dbsa_geoip', __('Errore di decompressione.', 'db-site-analytics'));
        }

        while (!gzeof($gz)) {
            $chunk = gzread($gz, 1048576);
            if ($chunk === false) break;
            fwrite($out, $chunk);
        }
        gzclose($gz);
        fclose($out);
        @unlink($tmp_gz);

        // Valida il file prima di renderlo attivo
        try {
            $test = new DBSA_MMDB_Reader($tmp_mmdb);
            $meta = $test->get_metadata();
            unset($test);
            if (empty($meta['node_count'])) {
                throw new RuntimeException('DB vuoto.');
            }
        } catch (Exception $e) {
            @unlink($tmp_mmdb);
            return new WP_Error('dbsa_geoip', sprintf(
                /* translators: %s: messaggio di errore */
                __('File scaricato non valido: %s', 'db-site-analytics'), $e->getMessage()
            ));
        }

        // Sostituzione atomica
        if (!@rename($tmp_mmdb, $this->database_path())) {
            @unlink($tmp_mmdb);
            return new WP_Error('dbsa_geoip', __('Impossibile sostituire il database.', 'db-site-analytics'));
        }

        // Invalida il reader in-memory
        $this->reader        = null;
        $this->reader_loaded = false;
        $this->cache         = array();

        return true;
    }

    /**
     * Rimuove il database dal filesystem (disattivazione feature).
     */
    public function delete_database(): void {
        if ($this->database_exists()) {
            @unlink($this->database_path());
        }
        $this->reader        = null;
        $this->reader_loaded = false;
        delete_option('dbsa_geoip_updated');
        delete_option('dbsa_geoip_last_error');
    }

    // -------------------------------------------------------------------------
    // Reader
    // -------------------------------------------------------------------------

    private function get_reader(): ?DBSA_MMDB_Reader {
        if ($this->reader_loaded) {
            return $this->reader;
        }
        $this->reader_loaded = true;

        if (!$this->database_exists()) {
            return null;
        }

        try {
            $this->reader = new DBSA_MMDB_Reader($this->database_path());
        } catch (Exception $e) {
            $this->reader = null;
        }

        return $this->reader;
    }
}
