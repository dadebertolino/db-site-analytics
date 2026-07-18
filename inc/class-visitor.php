<?php
/**
 * DBSA_Visitor — Utility condivise per identificazione anonima del visitatore.
 *
 * Centralizza (v3.1.0): rilevamento IP, hash giornaliero anonimo,
 * gestione salt e rate limiting per IP. Sostituisce il codice duplicato
 * in Tracker, Downloader ed Events.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Visitor {

    /**
     * Hash giornaliero anonimo: SHA256(IP + UA + salt_giornaliero).
     * Non è un cookie. Non è persistente. Non è reversibile dopo 24h.
     */
    public static function generate_hash(): string {
        $ip = self::get_client_ip();
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        return hash('sha256', $ip . $ua . self::get_daily_salt());
    }

    /**
     * Ottiene o rigenera il salt giornaliero.
     * Usa add_option (atomico a livello DB) per evitare che due richieste
     * concorrenti al cambio giorno generino salt diversi.
     */
    public static function get_daily_salt(): string {
        $today     = gmdate('Y-m-d');
        $salt_date = get_option('dbsa_salt_date', '');

        if ($salt_date === $today) {
            return get_option('dbsa_daily_salt', '');
        }

        // Giorno nuovo: prova a "vincere" la rigenerazione in modo atomico
        $lock_key = 'dbsa_salt_lock_' . $today;
        $new_salt = wp_generate_password(32, true, true);

        if (add_option($lock_key, $new_salt, '', 'no')) {
            // Questa richiesta ha vinto: aggiorna il salt ufficiale
            update_option('dbsa_daily_salt', $new_salt);
            update_option('dbsa_salt_date',  $today);
            // Pulisci lock del giorno precedente
            delete_option('dbsa_salt_lock_' . gmdate('Y-m-d', strtotime('-1 day')));
            return $new_salt;
        }

        // Un'altra richiesta ha già rigenerato: usa il suo salt
        return get_option($lock_key, get_option('dbsa_daily_salt', $new_salt));
    }

    /**
     * IP del client.
     * Di default si fida SOLO di REMOTE_ADDR (non spoofabile).
     * Gli header proxy (Cloudflare, X-Forwarded-For) vengono considerati
     * solo se l'impostazione 'trust_proxy' è attiva.
     */
    public static function get_client_ip(): string {
        $settings = get_option('dbsa_settings', array());

        if (!empty($settings['trust_proxy'])) {
            $headers = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');
            foreach ($headers as $h) {
                if (!empty($_SERVER[$h])) {
                    $ip = trim(explode(',', sanitize_text_field(wp_unslash($_SERVER[$h])))[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Rate limiting per IP su finestra scorrevole (transient).
     * Ritorna true se la richiesta è consentita, false se il limite è superato.
     *
     * @param string $context Identificatore endpoint (es. 'event', 'download').
     * @param int    $max     Richieste massime nella finestra.
     * @param int    $window  Durata finestra in secondi.
     */
    public static function check_rate_limit(string $context, int $max = 30, int $window = 60): bool {
        $key   = 'dbsa_rl_' . $context . '_' . md5(self::get_client_ip());
        $count = (int) get_transient($key);

        if ($count >= $max) {
            return false;
        }

        // set_transient sovrascrive la scadenza: accettabile per finestre brevi
        set_transient($key, $count + 1, $window);
        return true;
    }
}
