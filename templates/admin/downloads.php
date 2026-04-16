<?php
/**
 * Template: Pagina Download Tracking
 *
 * Variabili: $from, $to, $top_downloads, $dl_total
 */

if (!defined('ABSPATH')) exit;

$export_nonce  = wp_create_nonce('dbsa_export');
$export_dl_url = add_query_arg(array(
    'dbsa_export' => 'downloads',
    'from'        => $from,
    'to'          => $to,
    '_wpnonce'    => $export_nonce,
), admin_url('admin.php'));
?>
<div class="wrap">

    <div class="db-ui-page-header">
        <h1><?php esc_html_e('Download Tracking', 'db-site-analytics'); ?></h1>
        <div class="db-ui-actions">
            <a href="<?php echo esc_url($export_dl_url); ?>" class="db-ui-btn db-ui-btn-sm">⬇️ <?php esc_html_e('Esporta CSV', 'db-site-analytics'); ?></a>
        </div>
    </div>

    <!-- Filtro date -->
    <div class="db-ui-card dbsa-filter-bar">
        <div class="db-ui-card-body">
            <form method="get" action="">
                <input type="hidden" name="page" value="dbsa-downloads">
                <label for="dbsa_from_dl"><?php esc_html_e('Dal', 'db-site-analytics'); ?></label>
                <input type="date" id="dbsa_from_dl" name="from" value="<?php echo esc_attr($from); ?>">
                <label for="dbsa_to_dl"><?php esc_html_e('al', 'db-site-analytics'); ?></label>
                <input type="date" id="dbsa_to_dl" name="to" value="<?php echo esc_attr($to); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                <button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e('Filtra', 'db-site-analytics'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-downloads')); ?>" class="db-ui-btn"><?php esc_html_e('Reset', 'db-site-analytics'); ?></a>
            </form>
        </div>
    </div>

    <!-- KPI -->
    <div class="db-ui-card dbsa-kpi-card" style="max-width:240px;">
        <div class="db-ui-card-body" style="text-align:center;">
            <div class="dbsa-kpi-icon">📥</div>
            <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($dl_total)); ?></div>
            <div class="dbsa-kpi-label"><?php esc_html_e('Download totali nel periodo', 'db-site-analytics'); ?></div>
        </div>
    </div>

    <?php
    $settings = get_option('dbsa_settings', array());
    if (empty($settings['track_downloads'])) :
    ?>
    <div class="db-ui-alert db-ui-alert-warning">
        <span class="db-ui-alert-icon">⚠️</span>
        <span>
            <?php esc_html_e('Il tracking dei download è disabilitato.', 'db-site-analytics'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-settings')); ?>"><?php esc_html_e('Abilitalo nelle impostazioni →', 'db-site-analytics'); ?></a>
        </span>
    </div>
    <?php endif; ?>

    <!-- Tabella top file -->
    <div class="db-ui-card">
        <div class="db-ui-card-header">
            <h3><?php esc_html_e('File più scaricati', 'db-site-analytics'); ?></h3>
        </div>
        <div class="db-ui-card-body dbsa-table-wrap">
            <?php if (empty($top_downloads)) : ?>
                <div class="db-ui-empty">
                    <span class="db-ui-empty-icon">📥</span>
                    <span class="db-ui-empty-text"><?php esc_html_e('Nessun download registrato nel periodo selezionato.', 'db-site-analytics'); ?></span>
                </div>
            <?php else : ?>
                <table class="db-ui-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e('File', 'db-site-analytics'); ?></th>
                            <th><?php esc_html_e('Download', 'db-site-analytics'); ?></th>
                            <th><?php esc_html_e('Visitatori unici', 'db-site-analytics'); ?></th>
                            <th><?php esc_html_e('Pagina principale', 'db-site-analytics'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_downloads as $i => $dl) : ?>
                        <tr>
                            <td><?php echo esc_html($i + 1); ?></td>
                            <td>
                                <a href="<?php echo esc_url($dl['file_url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($dl['file_name'] ?: basename($dl['file_url'])); ?>
                                    <span class="screen-reader-text"><?php esc_html_e('(si apre in una nuova finestra)', 'db-site-analytics'); ?></span>
                                </a>
                                <div class="dbsa-url-muted"><?php echo esc_html(parse_url($dl['file_url'], PHP_URL_HOST) ?: ''); ?></div>
                            </td>
                            <td><strong><?php echo esc_html(number_format_i18n($dl['downloads'])); ?></strong></td>
                            <td><?php echo esc_html(number_format_i18n($dl['visitors'])); ?></td>
                            <td>
                                <?php if (!empty($dl['from_page'])) : ?>
                                    <a href="<?php echo esc_url($dl['from_page']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html(parse_url($dl['from_page'], PHP_URL_PATH) ?: $dl['from_page']); ?>
                                        <span class="screen-reader-text"><?php esc_html_e('(si apre in una nuova finestra)', 'db-site-analytics'); ?></span>
                                    </a>
                                <?php else : ?>
                                    <span class="dbsa-url-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>
