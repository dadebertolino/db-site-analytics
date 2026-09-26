<?php
/**
 * DBSA_Privacy — Dichiarazione dei trattamenti (v3.4.0).
 *
 * - Registro trattamenti dell'ecosistema DB: filtro `dbph_processing_register`
 *   di DB Privacy Hub (e il legacy `dbseo_processing_register`). Se l'Hub non
 *   è installato il filtro non scatta e la classe è inerte.
 * - Testo suggerito per l'informativa in Impostazioni → Privacy di WordPress.
 *
 * Le voci sono dinamiche: si dichiara solo ciò che le impostazioni attivano.
 * Nessun esportatore/eliminatore DSAR: l'hash giornaliero non è ricollegabile
 * a un utente o a un indirizzo email, quindi non c'è nulla da estrarre.
 *
 * @package DB_Site_Analytics
 */

if (!defined('ABSPATH')) exit;

class DBSA_Privacy {

    private static $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('dbph_processing_register',  array($this, 'declare_processing'));
        add_filter('dbseo_processing_register', array($this, 'declare_processing'));
        add_action('admin_init',                array($this, 'add_policy_content'));
    }

    /**
     * Voci per il registro trattamenti di DB Privacy Hub.
     */
    public function declare_processing($register) {
        if (!is_array($register)) {
            $register = array();
        }

        $settings = get_option('dbsa_settings', array());

        $register[] = array(
            'id'             => 'dbsa_statistics',
            'label'          => __('Statistiche di visita (DB Site Analytics)', 'db-site-analytics'),
            'status'         => 'active',
            'purpose'        => __('Misurare in forma aggregata le visite al sito: pagine più viste, provenienza, tipo di dispositivo, andamento nel tempo.', 'db-site-analytics'),
            'legal_basis'    => __('Legittimo interesse del titolare (art. 6.1.f GDPR) a conoscere l\'uso del proprio sito. Nessun cookie né memorizzazione sul dispositivo (art. 122 Codice Privacy non applicabile).', 'db-site-analytics'),
            'data_collected' => $this->data_collected($settings),
            'retention'      => $this->retention($settings),
            'transfers'      => __('Nessuno. I dati restano nel database WordPress del sito; nessun servizio esterno riceve dati dei visitatori.', 'db-site-analytics'),
        );

        if (!empty($settings['track_searches'] ?? 1)) {
            $register[] = array(
                'id'             => 'dbsa_searches',
                'label'          => __('Ricerche interne (DB Site Analytics)', 'db-site-analytics'),
                'status'         => 'active',
                'purpose'        => __('Conoscere cosa cercano i visitatori per migliorare i contenuti del sito.', 'db-site-analytics'),
                'legal_basis'    => __('Legittimo interesse del titolare (art. 6.1.f GDPR).', 'db-site-analytics'),
                'data_collected' => __('Termine cercato e hash giornaliero del visitatore. I termini che sembrano email, numeri di telefono o codici fiscali vengono scartati senza essere salvati.', 'db-site-analytics'),
                'retention'      => $this->retention($settings),
                'transfers'      => __('Nessuno.', 'db-site-analytics'),
            );
        }

        return $register;
    }

    /**
     * Testo suggerito per la pagina privacy (Impostazioni → Privacy → Guida).
     */
    public function add_policy_content(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $settings = get_option('dbsa_settings', array());

        $content  = '<p>' . esc_html__('Questo sito raccoglie statistiche di visita con DB Site Analytics, direttamente sul proprio server. Non vengono usati cookie né altri strumenti che memorizzano informazioni sul tuo dispositivo, e nessun dato viene inviato a terzi.', 'db-site-analytics') . '</p>';
        $content .= '<p>' . esc_html($this->data_collected($settings)) . '</p>';
        $content .= '<p>' . esc_html($this->retention($settings)) . '</p>';
        if (!empty($settings['track_searches'] ?? 1)) {
            $content .= '<p>' . esc_html__('Vengono registrati anche i termini cercati nel sito, per migliorarne i contenuti; i termini che sembrano email, numeri di telefono o codici fiscali vengono scartati.', 'db-site-analytics') . '</p>';
        }

        wp_add_privacy_policy_content(__('DB Site Analytics', 'db-site-analytics'), wp_kses_post($content));
    }

    private function data_collected(array $settings): string {
        $items = array(
            __('pagina visitata', 'db-site-analytics'),
            __('sito di provenienza (senza parametri)', 'db-site-analytics'),
            __('tipo di dispositivo, browser e sistema operativo', 'db-site-analytics'),
        );
        if (!empty($settings['enable_geoip'])) {
            $items[] = __('paese, ricavato dall\'indirizzo IP con un database locale', 'db-site-analytics');
        }
        if (!empty($settings['track_downloads'])) {
            $items[] = __('file scaricati', 'db-site-analytics');
        }
        if (!empty($settings['track_outbound'])) {
            $items[] = __('link esterni cliccati', 'db-site-analytics');
        }
        if (!empty($settings['track_scroll'])) {
            $items[] = __('profondità di lettura della pagina', 'db-site-analytics');
        }

        return sprintf(
            /* translators: %s: elenco dei dati raccolti */
            __('Per ogni visita vengono registrati: %s. L\'indirizzo IP non viene salvato: viene combinato con lo user agent e con un valore casuale che cambia ogni giorno per calcolare un codice (hash) che permette di contare i visitatori unici della giornata. Durante la giornata il codice è un dato pseudonimo; dal giorno dopo il valore casuale viene eliminato e il codice non è più ricollegabile al visitatore.', 'db-site-analytics'),
            implode(', ', $items)
        );
    }

    private function retention(array $settings): string {
        $days = absint($settings['retention_days'] ?? 90);

        return $days > 0
            ? sprintf(
                /* translators: %d: giorni di conservazione */
                __('I dati vengono cancellati automaticamente dopo %d giorni.', 'db-site-analytics'),
                $days
            )
            : __('I dati vengono conservati fino alla cancellazione manuale da parte del titolare.', 'db-site-analytics');
    }
}
