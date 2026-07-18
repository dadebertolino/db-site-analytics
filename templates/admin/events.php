<?php
/**
 * Template: Pagina Eventi Custom
 *
 * Variabili: $from, $to, $outbound, $scroll_depth, $events_total
 */

if (!defined('ABSPATH')) exit;

$export_nonce   = wp_create_nonce('dbsa_export');
$export_ev_url  = add_query_arg(array(
    'dbsa_export' => 'events',
    'from'        => $from,
    'to'          => $to,
    '_wpnonce'    => $export_nonce,
), admin_url('admin.php'));

$settings = get_option('dbsa_settings', array());
$events_enabled = !empty($settings['track_outbound']) || !empty($settings['track_scroll']);
?>
<div class="wrap">

    <div class="db-ui-page-header">
        <h1><?php esc_html_e('Tracking Eventi', 'db-site-analytics'); ?></h1>
        <div class="db-ui-actions">
            <a href="<?php echo esc_url($export_ev_url); ?>" class="db-ui-btn db-ui-btn-sm">⬇️ <?php esc_html_e('Esporta CSV', 'db-site-analytics'); ?></a>
        </div>
    </div>

    <!-- Filtro date -->
    <div class="db-ui-card dbsa-filter-bar">
        <div class="db-ui-card-body">
            <form method="get" action="">
                <input type="hidden" name="page" value="dbsa-events">
                <label for="dbsa_from_ev"><?php esc_html_e('Dal', 'db-site-analytics'); ?></label>
                <input type="date" id="dbsa_from_ev" name="from" value="<?php echo esc_attr($from); ?>">
                <label for="dbsa_to_ev"><?php esc_html_e('al', 'db-site-analytics'); ?></label>
                <input type="date" id="dbsa_to_ev" name="to" value="<?php echo esc_attr($to); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                <button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e('Filtra', 'db-site-analytics'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-events')); ?>" class="db-ui-btn"><?php esc_html_e('Reset', 'db-site-analytics'); ?></a>
            </form>
        </div>
    </div>

    <?php if (!$events_enabled) : ?>
    <div class="db-ui-alert db-ui-alert-warning">
        <span class="db-ui-alert-icon">⚠️</span>
        <span>
            <?php esc_html_e('Nessun tipo di evento è abilitato.', 'db-site-analytics'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-settings')); ?>"><?php esc_html_e('Configura nelle impostazioni →', 'db-site-analytics'); ?></a>
        </span>
    </div>
    <?php endif; ?>

    <!-- KPI -->
    <div class="db-ui-card dbsa-kpi-card" style="max-width:240px; margin-bottom:16px;">
        <div class="db-ui-card-body" style="text-align:center;">
            <div class="dbsa-kpi-icon">⚡</div>
            <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($events_total)); ?></div>
            <div class="dbsa-kpi-label"><?php esc_html_e('eventi nel periodo', 'db-site-analytics'); ?></div>
        </div>
    </div>

    <div class="dbsa-grid-2">

        <!-- Outbound Links -->
        <div class="db-ui-card">
            <div class="db-ui-card-header">
                <h3>🔗 <?php esc_html_e('Link esterni cliccati', 'db-site-analytics'); ?></h3>
                <?php if (empty($settings['track_outbound'])) : ?>
                    <span class="db-ui-badge db-ui-badge-muted"><?php esc_html_e('Disabilitato', 'db-site-analytics'); ?></span>
                <?php endif; ?>
            </div>
            <div class="db-ui-card-body dbsa-table-wrap">
                <?php if (empty($outbound)) : ?>
                    <div class="db-ui-empty">
                        <span class="db-ui-empty-icon">🔗</span>
                        <span class="db-ui-empty-text"><?php esc_html_e('Nessun click su link esterno nel periodo.', 'db-site-analytics'); ?></span>
                    </div>
                <?php else : ?>
                    <table class="db-ui-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('URL', 'db-site-analytics'); ?></th>
                                <th><?php esc_html_e('Click', 'db-site-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($outbound as $lnk) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($lnk['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html(parse_url($lnk['url'], PHP_URL_HOST) . (parse_url($lnk['url'], PHP_URL_PATH) ?: '')); ?>
                                        <span class="screen-reader-text"><?php esc_html_e('(si apre in una nuova finestra)', 'db-site-analytics'); ?></span>
                                    </a>
                                </td>
                                <td><strong><?php echo esc_html(number_format_i18n($lnk['clicks'])); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scroll Depth -->
        <div class="db-ui-card">
            <div class="db-ui-card-header">
                <h3>📜 <?php esc_html_e('Profondità di scroll', 'db-site-analytics'); ?></h3>
                <?php if (empty($settings['track_scroll'])) : ?>
                    <span class="db-ui-badge db-ui-badge-muted"><?php esc_html_e('Disabilitato', 'db-site-analytics'); ?></span>
                <?php endif; ?>
            </div>
            <div class="db-ui-card-body">
                <?php if (empty($scroll_depth)) : ?>
                    <div class="db-ui-empty">
                        <span class="db-ui-empty-icon">📜</span>
                        <span class="db-ui-empty-text"><?php esc_html_e('Nessun dato di scroll nel periodo.', 'db-site-analytics'); ?></span>
                    </div>
                <?php else :
                    // Trova il massimo per calcolare le proporzioni
                    $max_scroll = max(array_map('intval', array_column($scroll_depth, 'users')));
                    $max_scroll = max($max_scroll, 1);
                    foreach ($scroll_depth as $sd) :
                        $pct_bar = round(((int) $sd['users'] / $max_scroll) * 100);
                ?>
                    <div class="dbsa-scroll-row">
                        <div class="dbsa-scroll-label">
                            <span class="dbsa-scroll-pct"><?php echo esc_html($sd['depth']); ?></span>
                        </div>
                        <div class="db-ui-progress dbsa-scroll-bar">
                            <div class="db-ui-progress-fill db-ui-progress-success"
                                 style="width:<?php echo esc_attr($pct_bar); ?>%"></div>
                        </div>
                        <span class="dbsa-scroll-users">
                            <?php
                            printf(
                                /* translators: %s: numero di utenti */
                                esc_html(_n('%s utente', '%s utenti', $sd['users'], 'db-site-analytics')),
                                esc_html(number_format_i18n($sd['users']))
                            );
                            ?>
                        </span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div><!-- /.dbsa-grid-2 -->

</div>
