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
));

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

    <?php if (!empty($_GET['geoip_ok'])) : ?>
        <div class="db-ui-alert db-ui-alert-success">
            <span class="db-ui-alert-icon">✅</span>
            <span><?php esc_html_e('Database GeoIP aggiornato.', 'db-site-analytics'); ?></span>
        </div>
    <?php elseif (!empty($_GET['geoip_err'])) : ?>
        <div class="db-ui-alert db-ui-alert-error">
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
                        <?php esc_html_e('Escludi gli amministratori', 'db-site-analytics'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Le visite degli utenti con ruolo Administrator non vengono registrate.', 'db-site-analytics'); ?></p>
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
        <button type="submit" name="dbsa_geoip_update" value="1" class="db-ui-btn db-ui-btn-secondary">
            <?php esc_html_e('Aggiorna ora il database GeoIP', 'db-site-analytics'); ?>
        </button>
    </form>
    <?php endif; ?>
</div>
