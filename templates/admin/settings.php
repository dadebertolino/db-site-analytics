<?php
/**
 * Template: Pagina Impostazioni
 *
 * Variabili: $settings (array), $saved (bool)
 */

if (!defined('ABSPATH')) exit;

$settings = wp_parse_args($settings, array(
    'exclude_admins'  => 1,
    'track_logged_in' => 0,
    'exclude_paths'   => '',
    'exclude_roles'   => array('administrator'),
    'retention_days'  => 90,
    'track_searches'       => 1,
    'track_share_previews' => 1,
));

$noise_report = $noise_report ?? array();
$noise_marked = (int) ($noise_marked ?? 0);

$all_roles      = wp_roles()->get_names();
$retention_opts = array(30, 60, 90, 180, 365);
?>
<div class="wrap">

    <div class="db-ui-page-header">
        <h1><?php esc_html_e('Impostazioni — DB Site Analytics', 'db-site-analytics'); ?></h1>
    </div>

    <?php if ($saved) : ?>
        <div class="db-ui-alert db-ui-alert-success">
            <span class="db-ui-alert-icon">✅</span>
            <span><?php esc_html_e('Impostazioni salvate.', 'db-site-analytics'); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cleanup_marked'])) : ?>
        <?php $dbsa_cleanup_n = absint($_GET['cleanup_marked']); ?>
        <div class="db-ui-alert db-ui-alert-success">
            <span class="db-ui-alert-icon">✅</span>
            <span>
                <?php
                printf(
                    /* translators: %s: numero di righe */
                    esc_html(_n('%s riga esclusa dalle statistiche.', '%s righe escluse dalle statistiche.', $dbsa_cleanup_n, 'db-site-analytics')),
                    esc_html(number_format_i18n($dbsa_cleanup_n))
                );
                ?>
            </span>
        </div>
    <?php elseif (isset($_GET['cleanup_restored'])) : ?>
        <?php $dbsa_cleanup_n = absint($_GET['cleanup_restored']); ?>
        <div class="db-ui-alert db-ui-alert-success">
            <span class="db-ui-alert-icon">↩️</span>
            <span>
                <?php
                printf(
                    /* translators: %s: numero di righe */
                    esc_html(_n('%s riga ripristinata nelle statistiche.', '%s righe ripristinate nelle statistiche.', $dbsa_cleanup_n, 'db-site-analytics')),
                    esc_html(number_format_i18n($dbsa_cleanup_n))
                );
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['geoip_ok'])) : ?>
        <div class="db-ui-alert db-ui-alert-success">
            <span class="db-ui-alert-icon">✅</span>
            <span><?php esc_html_e('Database GeoIP aggiornato.', 'db-site-analytics'); ?></span>
        </div>
    <?php elseif (!empty($_GET['geoip_err'])) : ?>
        <div class="db-ui-alert db-ui-alert-danger">
            <span class="db-ui-alert-icon">❌</span>
            <span><?php echo esc_html(get_option('dbsa_geoip_last_error', __('Aggiornamento GeoIP fallito.', 'db-site-analytics'))); ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('dbsa_settings_nonce'); ?>

        <!-- Tracciamento -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3><?php esc_html_e('Tracciamento', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body dbsa-settings-body">

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="exclude_admins" value="1" <?php checked(1, $settings['exclude_admins']); ?>>
                        <?php esc_html_e('Escludi lo staff del sito', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Le visite di chi può modificare contenuti (amministratori, editor, autori, collaboratori) non vengono mai registrate, anche se il tracciamento degli utenti loggati è attivo. Evita di contare le proprie visite mentre si lavora al sito.', 'db-site-analytics'); ?></p>
                </div>

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="track_logged_in" value="1" <?php checked(1, $settings['track_logged_in']); ?>>
                        <?php esc_html_e('Traccia anche gli utenti loggati', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Se disabilitato, nessun utente loggato viene tracciato (indipendentemente dal ruolo).', 'db-site-analytics'); ?></p>
                </div>

                <hr class="db-ui-sep">

                <div class="dbsa-field-row">
                    <label for="exclude_roles"><strong><?php esc_html_e('Escludi ruoli specifici', 'db-site-analytics'); ?></strong></label>
                    <p class="description"><?php esc_html_e('Seleziona i ruoli le cui visite non devono essere registrate.', 'db-site-analytics'); ?></p>
                    <div class="dbsa-roles-grid">
                        <?php foreach ($all_roles as $slug => $name) : ?>
                            <label>
                                <input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr($slug); ?>"
                                    <?php checked(in_array($slug, (array) $settings['exclude_roles'], true)); ?>>
                                <?php echo esc_html(translate_user_role($name)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="db-ui-sep">

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="trust_proxy" value="1" <?php checked(1, $settings['trust_proxy'] ?? 0); ?>>
                        <?php esc_html_e('Il sito è dietro proxy/CDN (Cloudflare, reverse proxy)', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Attiva SOLO se il sito passa da Cloudflare o un reverse proxy: usa gli header X-Forwarded-For / CF-Connecting-IP per identificare i visitatori. Se disattivato viene usato solo REMOTE_ADDR (non falsificabile).', 'db-site-analytics'); ?></p>
                </div>

                <hr class="db-ui-sep">

                <div class="dbsa-field-row">
                    <label for="exclude_paths"><strong><?php esc_html_e('Percorsi esclusi', 'db-site-analytics'); ?></strong></label>
                    <p class="description"><?php esc_html_e('Un pattern per riga. Supporta wildcard (*). Esempio: /wp-login.php  oppure  /area-riservata/*', 'db-site-analytics'); ?></p>
                    <textarea id="exclude_paths" name="exclude_paths" rows="5" class="large-text code"><?php echo esc_textarea($settings['exclude_paths']); ?></textarea>
                </div>

            </div>
        </div>

        <!-- Download Tracking -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3>📥 <?php esc_html_e('Tracking Download', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body dbsa-settings-body">

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="track_downloads" value="1" <?php checked(1, $settings['track_downloads'] ?? 0); ?>>
                        <?php esc_html_e('Abilita tracking download', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Traccia i click su link a file scaricabili. Richiede un piccolo JS (~1KB) nel frontend.', 'db-site-analytics'); ?></p>
                </div>

                <div class="dbsa-field-row">
                    <label for="download_extensions"><strong><?php esc_html_e('Estensioni tracciate', 'db-site-analytics'); ?></strong></label>
                    <p class="description"><?php esc_html_e('Lista separata da virgole. Esempio: pdf,zip,docx,xlsx', 'db-site-analytics'); ?></p>
                    <input type="text" id="download_extensions" name="download_extensions"
                           value="<?php echo esc_attr($settings['download_extensions'] ?? DBSA_Downloader::DEFAULT_EXTENSIONS); ?>"
                           class="regular-text code">
                </div>

            </div>
        </div>

        <!-- Tracking eventi -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3>⚡ <?php esc_html_e('Tracking Eventi', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body dbsa-settings-body">

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="track_outbound" value="1" <?php checked(1, $settings['track_outbound'] ?? 0); ?>>
                        <?php esc_html_e('Traccia click su link esterni (outbound)', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Registra i click su link che portano fuori dal sito. Richiede un piccolo JS nel frontend.', 'db-site-analytics'); ?></p>
                </div>

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="track_scroll" value="1" <?php checked(1, $settings['track_scroll'] ?? 0); ?>>
                        <?php esc_html_e('Traccia profondità di scroll (25%, 50%, 75%, 100%)', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Misura fino a che punto i visitatori leggono le pagine. Richiede un piccolo JS nel frontend.', 'db-site-analytics'); ?></p>
                </div>

                <hr class="db-ui-sep">

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="track_searches" value="1" <?php checked(1, $settings['track_searches']); ?>>
                        <?php esc_html_e('Traccia le ricerche interne', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Le ricerche vengono registrate come evento separato, non come pagine visitate. Scartate le ricerche vuote o più lunghe di 100 caratteri; massimo 10 ricerche al minuto per IP. Lato server, nessun JS.', 'db-site-analytics'); ?></p>
                </div>

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="track_share_previews" value="1" <?php checked(1, $settings['track_share_previews']); ?>>
                        <?php esc_html_e('Conta le anteprime di condivisione (Facebook, WhatsApp, Telegram…)', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Quando un link viene condiviso, la piattaforma scarica la pagina per generare l\'anteprima. Queste richieste sono escluse dalle visite ma contate a parte: indicano, in modo approssimato, quante volte una pagina è stata condivisa. Lato server, nessun JS.', 'db-site-analytics'); ?></p>
                </div>

            </div>
        </div>

        <!-- GeoIP -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3>🌍 <?php esc_html_e('Geolocalizzazione (GeoIP)', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body dbsa-settings-body">

                <div class="dbsa-field-row">
                    <label>
                        <input type="checkbox" name="enable_geoip" value="1" <?php checked(1, $settings['enable_geoip'] ?? 0); ?>>
                        <?php esc_html_e('Abilita rilevamento del paese', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Usa il database gratuito DB-IP Country Lite (~10 MB), scaricato localmente in wp-content/uploads e aggiornato automaticamente ogni mese. Il lookup avviene interamente sul tuo server: nessun IP viene salvato né inviato a servizi esterni. All\'attivazione il database viene scaricato subito.', 'db-site-analytics'); ?></p>
                </div>

                <?php
                $geoip_info = DBSA_GeoIP::instance()->database_info();
                if ($geoip_info['exists']) :
                ?>
                <div class="dbsa-field-row">
                    <p class="description">
                        <strong><?php esc_html_e('Database presente.', 'db-site-analytics'); ?></strong>
                        <?php
                        printf(
                            /* translators: 1: dimensione, 2: data build */
                            esc_html__('Dimensione: %1$s — Build: %2$s', 'db-site-analytics'),
                            esc_html(size_format($geoip_info['size'])),
                            $geoip_info['build']
                                ? esc_html(gmdate('d/m/Y', $geoip_info['build']))
                                : esc_html(gmdate('d/m/Y', $geoip_info['mtime']))
                        );
                        ?>
                    </p>
                </div>
                <?php elseif (!empty($settings['enable_geoip'])) : ?>
                <div class="dbsa-field-row">
                    <p class="description"><strong><?php esc_html_e('Database non ancora scaricato.', 'db-site-analytics'); ?></strong></p>
                </div>
                <?php endif; ?>

                <p class="description" style="margin-top:8px;">
                    <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a>
                    <?php esc_html_e('(licenza CC BY 4.0)', 'db-site-analytics'); ?>
                </p>

            </div>
        </div>

        <!-- Retention -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3><?php esc_html_e('Conservazione dati', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body dbsa-settings-body">
                <div class="dbsa-field-row">
                    <label for="retention_days"><strong><?php esc_html_e('Mantieni i dati per', 'db-site-analytics'); ?></strong></label>
                    <p class="description"><?php esc_html_e('I dati più vecchi vengono eliminati automaticamente ogni notte.', 'db-site-analytics'); ?></p>
                    <select id="retention_days" name="retention_days">
                        <?php foreach ($retention_opts as $days) : ?>
                            <option value="<?php echo esc_attr($days); ?>" <?php selected($days, $settings['retention_days']); ?>>
                                <?php
                                /* translators: %d: numero di giorni di conservazione */
                                printf(esc_html(_n('%d giorno', '%d giorni', $days, 'db-site-analytics')), absint($days));
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Info GDPR -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3>📋 <?php esc_html_e('Privacy & GDPR', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body">
                <div class="db-ui-alert db-ui-alert-info">
                    <span class="db-ui-alert-icon">ℹ️</span>
                    <div>
                        <strong><?php esc_html_e('Questo plugin non raccoglie dati personali.', 'db-site-analytics'); ?></strong><br>
                        <?php esc_html_e('Non vengono salvati indirizzi IP, cookie di tracciamento o identificatori persistenti. Il visitor_hash è un contatore giornaliero anonimo non reversibile. Nessun consenso è richiesto ai sensi del GDPR/Regolamento ePrivacy.', 'db-site-analytics'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dbsa-submit-row">
            <button type="submit" name="dbsa_save_settings" class="db-ui-btn db-ui-btn-primary db-ui-btn-lg">
                <?php esc_html_e('Salva impostazioni', 'db-site-analytics'); ?>
            </button>
        </div>

    </form>

    <?php if (!empty($settings['enable_geoip'])) : ?>
    <form method="post" action="" style="margin-top:12px;">
        <?php wp_nonce_field('dbsa_geoip_update_nonce'); ?>
        <button type="submit" name="dbsa_geoip_update" value="1" class="db-ui-btn">
            <?php esc_html_e('Aggiorna ora il database GeoIP', 'db-site-analytics'); ?>
        </button>
    </form>
    <?php endif; ?>

    <!-- Bonifica storico (v3.3.0) -->
    <div class="db-ui-card" style="margin-top:24px;">
        <div class="db-ui-card-header">
            <h3>🧹 <?php esc_html_e('Bonifica storico', 'db-site-analytics'); ?></h3>
            <?php if ($noise_marked > 0) : ?>
                <span class="db-ui-badge db-ui-badge-muted">
                    <?php
                    printf(
                        /* translators: %s: numero di righe */
                        esc_html(_n('%s riga già esclusa', '%s righe già escluse', $noise_marked, 'db-site-analytics')),
                        esc_html(number_format_i18n($noise_marked))
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="db-ui-card-body dbsa-settings-body">
            <div class="db-ui-alert db-ui-alert-warning">
                <span class="db-ui-alert-icon">⚠️</span>
                <span><?php esc_html_e('I filtri su 404, bot e ricerche valgono da ora in avanti. Qui puoi escludere dalle statistiche il rumore registrato in precedenza. Le righe non vengono cancellate: sono marcate e nascoste, e puoi ripristinarle in qualsiasi momento. Fai comunque un backup del database prima di procedere.', 'db-site-analytics'); ?></span>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('dbsa_cleanup_nonce'); ?>
                <div class="dbsa-table-wrap">
                    <table class="db-ui-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th><?php esc_html_e('Categoria', 'db-site-analytics'); ?></th>
                                <th><?php esc_html_e('Righe', 'db-site-analytics'); ?></th>
                                <th><?php esc_html_e('Valori più frequenti', 'db-site-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($noise_report as $rule_key => $rule) : ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="cleanup_rules[]" id="dbsa_cleanup_<?php echo esc_attr($rule_key); ?>"
                                           value="<?php echo esc_attr($rule_key); ?>" <?php disabled(0, $rule['count']); ?>>
                                </td>
                                <td><label for="dbsa_cleanup_<?php echo esc_attr($rule_key); ?>"><?php echo esc_html($rule['label']); ?></label></td>
                                <td><strong><?php echo esc_html(number_format_i18n($rule['count'])); ?></strong></td>
                                <td>
                                    <?php foreach ($rule['samples'] as $sample) : ?>
                                        <div class="dbsa-url-muted">
                                            <?php echo esc_html(wp_make_link_relative($sample['label']) ?: '/'); ?>
                                            (<?php echo esc_html(number_format_i18n($sample['total'])); ?>)
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="description"><?php esc_html_e('Verifica che i numeri corrispondano a quanto vedi in dashboard prima di procedere. Le categorie possono sovrapporsi: ogni riga viene esclusa una sola volta.', 'db-site-analytics'); ?></p>

                <div class="dbsa-submit-row">
                    <button type="submit" name="dbsa_cleanup_apply" value="1" class="db-ui-btn db-ui-btn-danger">
                        <?php esc_html_e('Escludi le categorie selezionate', 'db-site-analytics'); ?>
                    </button>
                    <?php if ($noise_marked > 0) : ?>
                        <button type="submit" name="dbsa_cleanup_restore" value="1" class="db-ui-btn">
                            <?php esc_html_e('Ripristina tutte le righe escluse', 'db-site-analytics'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
