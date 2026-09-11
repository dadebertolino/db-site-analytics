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

    /**
     * Crawler che generano l'anteprima quando un link viene condiviso.
     * Esclusi dalle visite, contati a parte (v3.3.0). Chiave: pattern UA.
     */
    const SHARE_AGENTS = array(
        'facebookexternalhit' => 'Facebook',
        'whatsapp'            => 'WhatsApp',
        'telegrambot'         => 'Telegram',
        'twitterbot'          => 'X / Twitter',
        'linkedinbot'         => 'LinkedIn',
        'slackbot'            => 'Slack',
        'discordbot'          => 'Discord',
        'pinterest'           => 'Pinterest',
        'skypeuripreview'     => 'Skype',
    );

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
        $settings = get_option('dbsa_settings', array());

        if (!$this->should_track($settings)) {
            return;
        }

        $ua = $this->get_user_agent();

        // Bot: scartati dalle visite; le anteprime di condivisione contate a parte
        if ($this->is_bot($ua)) {
            $this->maybe_track_share_preview($ua, $settings);
            return;
        }

        // Le ricerche interne non sono pagine: tracciate come evento dedicato
        if (is_search()) {
            $this->maybe_track_search($settings);
            return;
        }

        // Raccolta dati
        $parsed_ua     = $this->parse_user_agent($ua);
        $raw_referrer  = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $referrer_host = self::normalize_referrer_host($raw_referrer);

        DBSA_DB::instance()->insert_pageview(array(
            'page_url'      => self::get_current_url(),
            'page_title'    => $this->get_page_title(),
            // URL di provenienza solo per referral esterni, senza query string
            'referrer'      => $referrer_host !== '' ? preg_replace('/[?#].*$/', '', $raw_referrer) : '',
            'referrer_host' => $referrer_host,
            'visitor_hash'  => DBSA_Visitor::generate_hash(),
            'device_type'   => $parsed_ua['device'],
            'browser'       => $parsed_ua['browser'],
            'os'            => $parsed_ua['os'],
            'country'       => DBSA_GeoIP::instance()->country_code(DBSA_Visitor::get_client_ip()),
            'is_bot'        => 0,
        ));
    }

    // -------------------------------------------------------------------------
    // Metodi pubblici di utilità
    // -------------------------------------------------------------------------

    /**
     * Host normalizzato del referrer (minuscolo, senza www).
     * Ritorna '' per traffico diretto e per navigazione interna.
     */
    public static function normalize_referrer_host(string $raw): string {
        $host = wp_parse_url($raw, PHP_URL_HOST);
        if (!$host) {
            return ''; // referrer assente = traffico diretto
        }

        $host = preg_replace('/^www\./', '', strtolower($host));

        // Dominio proprio: non è referral, è navigazione interna
        return in_array($host, self::own_hosts(), true) ? '' : $host;
    }

    /**
     * URL completo della pagina corrente (senza query string con i permalink
     * "belli"; con i permalink semplici mantiene le query var pubbliche,
     * altrimenti ogni pagina risulterebbe la homepage).
     */
    public static function get_current_url(): string {
        global $wp;

        if ('' === (string) get_option('permalink_structure') && !empty($wp->query_vars)) {
            return esc_url_raw(add_query_arg($wp->query_vars, home_url('/')));
        }

        return esc_url_raw(home_url(add_query_arg(array(), $wp->request)));
    }

    // -------------------------------------------------------------------------
    // Metodi privati
    // -------------------------------------------------------------------------

    /**
     * Decide se la richiesta corrente va considerata.
     */
    private function should_track(array $settings): bool {
        if (is_admin() || wp_doing_ajax())               return false;
        if (wp_doing_cron())                             return false;
        if (defined('REST_REQUEST') && REST_REQUEST)     return false;
        if (php_sapi_name() === 'cli')                   return false;

        // 404: scanner che cercano .env, up.php, wp-login... non sono pagine
        if (is_404())                                    return false;

        // Richieste servite da WordPress dopo template_redirect che non sono pagine
        if (is_feed() || is_robots() || is_favicon())    return false;
        if (is_trackback() || is_embed())                return false;
        if (is_preview() || is_customize_preview())      return false;

        // HEAD (link checker, monitor): WP esce solo dopo template_redirect
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD']))) : 'GET';
        if ('HEAD' === $method)                          return false;

        // Prefetch/prerender del browser (speculation rules WP 6.8+): non è una visita
        if ($this->is_prefetch())                        return false;

        if ($this->is_excluded_user($settings))          return false;
        if ($this->is_excluded_path($settings))          return false;

        return true;
    }

    /**
     * Esclusione utenti loggati: staff, tracking loggati disattivato, ruoli esclusi.
     */
    private function is_excluded_user(array $settings): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        // Staff (chi può modificare contenuti): le proprie visite mentre si lavora al sito.
        // Chiave storica 'exclude_admins', mantenuta per compatibilità con le impostazioni salvate.
        if (!empty($settings['exclude_admins'] ?? 1) && current_user_can('edit_posts')) {
            return true;
        }

        // Se track_logged_in è disabilitato, salta tutti i loggati
        if (empty($settings['track_logged_in'])) {
            return true;
        }

        $user          = wp_get_current_user();
        $exclude_roles = (array) ($settings['exclude_roles'] ?? array('administrator'));

        return (bool) array_intersect($exclude_roles, (array) $user->roles);
    }

    /**
     * Esclusione percorsi personalizzati (pattern con wildcard).
     */
    private function is_excluded_path(array $settings): bool {
        $exclude_paths = sanitize_textarea_field($settings['exclude_paths'] ?? '');
        if (empty($exclude_paths)) {
            return false;
        }

        $current_path = (string) wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH);
        foreach (array_filter(array_map('trim', explode("\n", $exclude_paths))) as $pattern) {
            if (fnmatch($pattern, $current_path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Richieste di prefetch/prerender inviate dal browser in anticipo.
     */
    private function is_prefetch(): bool {
        foreach (array('HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ') as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }
            $value = strtolower(sanitize_text_field(wp_unslash($_SERVER[$header])));
            if (strpos($value, 'prefetch') !== false || strpos($value, 'prerender') !== false || strpos($value, 'preview') !== false) {
                return true;
            }
        }
        return false;
    }

    private function get_user_agent(): string {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';
    }

    /**
     * Rilevamento bot/crawler tramite pattern sullo User-Agent.
     * Estendibile con il filtro 'dbsa_bot_patterns'.
     */
    private function is_bot(string $ua): bool {
        if ('' === trim($ua)) {
            return true; // richieste senza UA: quasi sempre scanner
        }

        $patterns = apply_filters('dbsa_bot_patterns', array(
            'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
            'go-http-client', 'java/', 'libwww', 'okhttp', 'headlesschrome',
            'ahrefs', 'semrush', 'mj12', 'dotbot', 'petalbot', 'bytespider',
            'facebookexternalhit', 'whatsapp', 'telegrambot', 'preview',
            // Dalla lista precedente, non coperti dai pattern generici
            'mediapartners', 'archive', 'fetch', 'lighthouse', 'screaming frog',
            'dataprovider', 'httrack', 'nutch', 'pingdom', 'statuspage', 'skypeuripreview',
        ));

        $ua = strtolower($ua);
        foreach ($patterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Conta l'anteprima generata da un social/app di messaggistica (v3.3.0).
     * Indica quante volte un link è stato condiviso (approssimato: alcune
     * piattaforme rigenerano l'anteprima più volte).
     */
    private function maybe_track_share_preview(string $ua, array $settings): void {
        if (empty($settings['track_share_previews'] ?? 1)) {
            return;
        }

        $ua_lower = strtolower($ua);
        foreach (self::SHARE_AGENTS as $pattern => $network) {
            if (strpos($ua_lower, $pattern) === false) {
                continue;
            }
            if (!DBSA_Visitor::check_rate_limit('share', 30, 60)) {
                return;
            }
            DBSA_DB::instance()->insert_event(array(
                'event_type'   => 'share_preview',
                'event_data'   => $network,
                'page_url'     => self::get_current_url(),
                'visitor_hash' => '',
            ));
            return;
        }
    }

    /**
     * Registra una ricerca interna come evento (v3.3.0).
     */
    private function maybe_track_search(array $settings): void {
        if (empty($settings['track_searches'] ?? 1)) {
            return;
        }

        $query = trim(preg_replace('/\s+/', ' ', sanitize_text_field(get_search_query(false))));

        // Vuote o spazzatura: scarta
        if ('' === $query || mb_strlen($query) > 100) {
            return;
        }

        // Un utente vero non fa più di dieci ricerche al minuto
        if (!DBSA_Visitor::check_rate_limit('search', 10, 60)) {
            return;
        }

        DBSA_DB::instance()->insert_event(array(
            'event_type'   => 'search',
            'event_data'   => $query,
            'page_url'     => '',
            'visitor_hash' => DBSA_Visitor::generate_hash(),
        ));
    }

    /**
     * Host del sito (home e site URL), normalizzati come i referrer.
     */
    private static function own_hosts(): array {
        static $hosts = null;

        if (null === $hosts) {
            $hosts = array();
            foreach (array(home_url(), site_url()) as $url) {
                $host = wp_parse_url($url, PHP_URL_HOST);
                if ($host) {
                    $hosts[] = preg_replace('/^www\./', '', strtolower($host));
                }
            }
            $hosts = array_values(array_unique($hosts));
        }

        return $hosts;
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

        // OS — iOS prima di macOS: gli UA di iPhone/iPad contengono "like Mac OS X"
        $os = 'Other';
        if (strpos($ua_lower, 'windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($ua_lower, 'iphone') !== false || strpos($ua_lower, 'ipad') !== false || strpos($ua_lower, 'ipod') !== false) {
            $os = 'iOS';
        } elseif (strpos($ua_lower, 'macintosh') !== false || strpos($ua_lower, 'mac os') !== false) {
            $os = 'macOS';
        } elseif (strpos($ua_lower, 'android') !== false) {
            $os = 'Android';
        } elseif (strpos($ua_lower, 'linux') !== false) {
            $os = 'Linux';
        }

        return compact('device', 'browser', 'os');
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
        if (is_home() || is_front_page()) {
            return get_bloginfo('name');
        }
        return '';
    }
}
