<?php
/**
 * DBSA_Tracker — Registra pageview via template_redirect.
 * Nessun JavaScript iniettato. Nessun cookie. Server-side only.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Tracker {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('template_redirect', array($this, 'track_pageview'), 99);
    }

    /**
     * Hook principale: valida e registra la visita.
     */
    public function track_pageview(): void {
        // Esclusioni rapide
        if ($this->should_skip()) {
            return;
        }

        $ua = $this->get_user_agent();

        // Scarta bot
        if ($this->is_bot($ua)) {
            return;
        }

        $settings = get_option('dbsa_settings', array());

        // Esclusione ruoli utente loggati
        if (is_user_logged_in()) {
            $user          = wp_get_current_user();
            $exclude_roles = (array) ($settings['exclude_roles'] ?? array('administrator'));
            foreach ($exclude_roles as $role) {
                if (in_array($role, (array) $user->roles, true)) {
                    return;
                }
            }
            // Se track_logged_in è disabilitato, salta tutti i loggati
            if (empty($settings['track_logged_in'])) {
                return;
            }
        }

        // Esclusione percorsi personalizzati
        $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $exclude_paths = sanitize_textarea_field($settings['exclude_paths'] ?? '');
        if (!empty($exclude_paths)) {
            foreach (array_filter(array_map('trim', explode("\n", $exclude_paths))) as $pattern) {
                if (fnmatch($pattern, $current_path)) {
                    return;
                }
            }
        }

        // Raccolta dati
        $parsed_ua    = $this->parse_user_agent($ua);
        $visitor_hash = $this->generate_visitor_hash();

        DBSA_DB::instance()->insert_pageview(array(
            'page_url'     => $this->get_current_url(),
            'page_title'   => $this->get_page_title(),
            'referrer'     => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
            'visitor_hash' => $visitor_hash,
            'device_type'  => $parsed_ua['device'],
            'browser'      => $parsed_ua['browser'],
            'os'           => $parsed_ua['os'],
            'country'      => '',   // Fase 3: GeoIP
            'is_bot'       => 0,
        ));
    }

    // -------------------------------------------------------------------------
    // Metodi privati
    // -------------------------------------------------------------------------

    /**
     * Esclusioni globali: admin, AJAX, REST, feed, cron.
     */
    private function should_skip(): bool {
        if (is_admin())                return true;
        if (wp_doing_ajax())           return true;
        if (wp_doing_cron())           return true;
        if (is_feed())                 return true;
        if (is_robots())               return true;
        if (defined('REST_REQUEST') && REST_REQUEST) return true;

        // Richieste CLI
        if (php_sapi_name() === 'cli') return true;

        return false;
    }

    private function get_user_agent(): string {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';
    }

    /**
     * Rilevamento bot/crawler tramite lista UA patterns.
     */
    private function is_bot(string $ua): bool {
        if (empty($ua)) return true;

        $bot_patterns = array(
            'bot', 'crawler', 'spider', 'slurp', 'fetch', 'archive',
            'mediapartners', 'facebookexternalhit', 'twitterbot',
            'linkedinbot', 'whatsapp', 'telegrambot', 'applebot',
            'bingbot', 'googlebot', 'yandexbot', 'baiduspider',
            'duckduckbot', 'sogou', 'exabot', 'msnbot', 'semrushbot',
            'ahrefsbot', 'majestic', 'screaming frog', 'dataprovider',
            'python-requests', 'go-http-client', 'java/', 'curl/',
            'wget/', 'libwww', 'httpunit', 'nutch', 'httrack',
            'pcore-http', 'pingdom', 'uptimerobot', 'statuspage',
        );

        $ua_lower = strtolower($ua);
        foreach ($bot_patterns as $pattern) {
            if (strpos($ua_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parsing leggero dello User-Agent (nessuna dipendenza esterna).
     * Ritorna: device (desktop|mobile|tablet), browser, os.
     */
    private function parse_user_agent(string $ua): array {
        $ua_lower = strtolower($ua);

        // Device
        $device = 'desktop';
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop/i', $ua)) {
            $device = 'mobile';
        }

        // Browser
        $browser = 'Other';
        if (strpos($ua_lower, 'edg/') !== false || strpos($ua_lower, 'edge/') !== false) {
            $browser = 'Edge';
        } elseif (strpos($ua_lower, 'opr/') !== false || strpos($ua_lower, 'opera') !== false) {
            $browser = 'Opera';
        } elseif (strpos($ua_lower, 'chrome') !== false && strpos($ua_lower, 'chromium') === false) {
            $browser = 'Chrome';
        } elseif (strpos($ua_lower, 'safari') !== false && strpos($ua_lower, 'chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($ua_lower, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($ua_lower, 'msie') !== false || strpos($ua_lower, 'trident') !== false) {
            $browser = 'IE';
        }

        // OS
        $os = 'Other';
        if (strpos($ua_lower, 'windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($ua_lower, 'macintosh') !== false || strpos($ua_lower, 'mac os') !== false) {
            $os = 'macOS';
        } elseif (strpos($ua_lower, 'iphone') !== false || strpos($ua_lower, 'ipad') !== false) {
            $os = 'iOS';
        } elseif (strpos($ua_lower, 'android') !== false) {
            $os = 'Android';
        } elseif (strpos($ua_lower, 'linux') !== false) {
            $os = 'Linux';
        }

        return compact('device', 'browser', 'os');
    }

    /**
     * Hash giornaliero anonimo: SHA256(IP + UA + salt_giornaliero).
     * Non è un cookie. Non è persistente. Non è reversibile dopo 24h.
     */
    private function generate_visitor_hash(): string {
        $ip   = $this->get_client_ip();
        $ua   = $this->get_user_agent();
        $salt = $this->get_daily_salt();

        return hash('sha256', $ip . $ua . $salt);
    }

    /**
     * Ottiene o rigenera il salt giornaliero.
     */
    private function get_daily_salt(): string {
        $today     = gmdate('Y-m-d');
        $salt_date = get_option('dbsa_salt_date', '');

        if ($salt_date !== $today) {
            $salt = wp_generate_password(32, true, true);
            update_option('dbsa_daily_salt', $salt);
            update_option('dbsa_salt_date',  $today);
            return $salt;
        }

        return get_option('dbsa_daily_salt', wp_generate_password(32, true, true));
    }

    /**
     * Recupera IP reale considerando proxy/CDN comuni.
     * NOTA: l'IP non viene mai salvato nel DB.
     */
    private function get_client_ip(): string {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // Prendi solo il primo IP in liste comma-separated
                $ip = trim(explode(',', $ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * URL completo della pagina corrente.
     */
    private function get_current_url(): string {
        global $wp;
        return esc_url_raw(home_url(add_query_arg(array(), $wp->request)));
    }

    /**
     * Titolo della pagina corrente.
     */
    private function get_page_title(): string {
        if (is_singular()) {
            return get_the_title();
        }
        if (is_category() || is_tag() || is_tax()) {
            return single_term_title('', false);
        }
        if (is_archive()) {
            return get_the_archive_title();
        }
        if (is_search()) {
            return __('Ricerca', 'db-site-analytics') . ': ' . get_search_query();
        }
        if (is_home() || is_front_page()) {
            return get_bloginfo('name');
        }
        return '';
    }
}
