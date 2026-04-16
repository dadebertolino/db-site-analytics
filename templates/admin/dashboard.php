<?php
/**
 * Template: Dashboard Analytics
 *
 * Variabili disponibili (impostate in DBSA_Admin::render_dashboard):
 * $from, $to, $stats_today, $stats_7d, $stats_30d,
 * $top_pages, $top_referrers, $daily_views, $devices,
 * $browsers, $os_list, $comparison, $dl_total
 */

if (!defined('ABSPATH')) exit;

// Defaults per variabili Fase 2 (retrocompatibilità)
$browsers   = $browsers   ?? array();
$os_list    = $os_list    ?? array();
$comparison = $comparison ?? array('current' => array('pageviews' => 0, 'visitors' => 0), 'previous' => array('pageviews' => 0, 'visitors' => 0));
$dl_total   = $dl_total   ?? 0;

// Helper: calcola variazione percentuale
function dbsa_pct_change(int $current, int $prev): string {
    if ($prev === 0) return $current > 0 ? '+100%' : '—';
    $pct = round((($current - $prev) / $prev) * 100, 1);
    return ($pct >= 0 ? '+' : '') . $pct . '%';
}
function dbsa_pct_class(int $current, int $prev): string {
    if ($prev === 0) return '';
    return $current >= $prev ? 'dbsa-trend-up' : 'dbsa-trend-down';
}

// URL export
$export_nonce  = wp_create_nonce('dbsa_export');
$export_pv_url = add_query_arg(array('dbsa_export' => 'pageviews', 'from' => $from, 'to' => $to, '_wpnonce' => $export_nonce), admin_url('admin.php'));
$export_dl_url = add_query_arg(array('dbsa_export' => 'downloads', 'from' => $from, 'to' => $to, '_wpnonce' => $export_nonce), admin_url('admin.php'));

// Prepara dati per Chart.js
$chart_labels  = array();
$chart_pv      = array();
$chart_uv      = array();

// Riempi i giorni mancanti con 0
$date_range = array();
$current    = strtotime($from);
$end        = strtotime($to);
while ($current <= $end) {
    $date_range[gmdate('Y-m-d', $current)] = array('pageviews' => 0, 'visitors' => 0);
    $current = strtotime('+1 day', $current);
}
foreach ($daily_views as $row) {
    if (isset($date_range[$row['day']])) {
        $date_range[$row['day']] = $row;
    }
}
foreach ($date_range as $day => $row) {
    $chart_labels[] = gmdate('d/m', strtotime($day));
    $chart_pv[]     = (int) $row['pageviews'];
    $chart_uv[]     = (int) $row['visitors'];
}

// Dati device per donut
$device_labels = array();
$device_data   = array();
foreach ($devices as $d) {
    $device_labels[] = ucfirst(esc_html($d['device_type']));
    $device_data[]   = (int) $d['total'];
}
?>
<div class="wrap dbsa-dashboard">

    <!-- Header -->
    <div class="db-ui-page-header">
        <h1><?php esc_html_e('DB Site Analytics', 'db-site-analytics'); ?></h1>
        <div class="db-ui-actions">
            <a href="<?php echo esc_url($export_pv_url); ?>" class="db-ui-btn db-ui-btn-sm">⬇️ <?php esc_html_e('Esporta CSV', 'db-site-analytics'); ?></a>
            <span class="db-ui-badge db-ui-badge-success">v<?php echo esc_html(DBSA_VERSION); ?></span>
        </div>
    </div>

    <!-- Filtro date -->
    <div class="db-ui-card dbsa-filter-bar">
        <div class="db-ui-card-body">
            <form method="get" action="">
                <input type="hidden" name="page" value="dbsa-dashboard">
                <label for="dbsa_from"><?php esc_html_e('Dal', 'db-site-analytics'); ?></label>
                <input type="date" id="dbsa_from" name="from" value="<?php echo esc_attr($from); ?>" max="<?php echo esc_attr($to); ?>">
                <label for="dbsa_to"><?php esc_html_e('al', 'db-site-analytics'); ?></label>
                <input type="date" id="dbsa_to" name="to" value="<?php echo esc_attr($to); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                <button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e('Filtra', 'db-site-analytics'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-dashboard')); ?>" class="db-ui-btn"><?php esc_html_e('Reset', 'db-site-analytics'); ?></a>
            </form>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="dbsa-kpi-grid">

        <div class="db-ui-card dbsa-kpi-card">
            <div class="db-ui-card-body">
                <div class="dbsa-kpi-icon">📄</div>
                <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($stats_today['pageviews'])); ?></div>
                <div class="dbsa-kpi-label"><?php esc_html_e('Pageview oggi', 'db-site-analytics'); ?></div>
            </div>
        </div>

        <div class="db-ui-card dbsa-kpi-card">
            <div class="db-ui-card-body">
                <div class="dbsa-kpi-icon">👤</div>
                <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($stats_today['visitors'])); ?></div>
                <div class="dbsa-kpi-label"><?php esc_html_e('Visitatori oggi', 'db-site-analytics'); ?></div>
            </div>
        </div>

        <div class="db-ui-card dbsa-kpi-card">
            <div class="db-ui-card-body">
                <div class="dbsa-kpi-icon">📈</div>
                <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($stats_7d['pageviews'])); ?></div>
                <div class="dbsa-kpi-label"><?php esc_html_e('Pageview 7 giorni', 'db-site-analytics'); ?></div>
            </div>
        </div>

        <div class="db-ui-card dbsa-kpi-card">
            <div class="db-ui-card-body">
                <div class="dbsa-kpi-icon">🗓️</div>
                <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($stats_30d['pageviews'])); ?></div>
                <div class="dbsa-kpi-label">
                    <?php
                    $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
                    printf(
                        esc_html(_n('Pageview %d giorno', 'Pageview %d giorni', $days, 'db-site-analytics')),
                        $days
                    );
                    ?>
                </div>
            </div>
        </div>

    </div><!-- /.dbsa-kpi-grid -->

    <!-- Grafico visite -->
    <div class="db-ui-card">
        <div class="db-ui-card-header">
            <h3><?php esc_html_e('Andamento visite', 'db-site-analytics'); ?></h3>
        </div>
        <div class="db-ui-card-body">
            <canvas id="dbsa-chart-views" height="80"></canvas>
        </div>
    </div>

    <!-- Griglia: Top Pagine + Referrer + Device -->
    <div class="dbsa-grid-2">

        <!-- Top Pagine -->
        <div class="db-ui-card">
            <div class="db-ui-card-header">
                <h3><?php esc_html_e('Top 10 pagine', 'db-site-analytics'); ?></h3>
            </div>
            <div class="db-ui-card-body dbsa-table-wrap">
                <?php if (empty($top_pages)) : ?>
                    <div class="db-ui-empty">
                        <span class="db-ui-empty-icon">📄</span>
                        <span class="db-ui-empty-text"><?php esc_html_e('Nessun dato nel periodo selezionato.', 'db-site-analytics'); ?></span>
                    </div>
                <?php else : ?>
                    <table class="db-ui-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Pagina', 'db-site-analytics'); ?></th>
                                <th><?php esc_html_e('PV', 'db-site-analytics'); ?></th>
                                <th><?php esc_html_e('UV', 'db-site-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($top_pages as $page) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($page['page_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($page['page_title'] ?: $page['page_url']); ?>
                                        <span class="screen-reader-text"><?php esc_html_e('(si apre in una nuova finestra)', 'db-site-analytics'); ?></span>
                                    </a>
                                </td>
                                <td><?php echo esc_html(number_format_i18n($page['pageviews'])); ?></td>
                                <td><?php echo esc_html(number_format_i18n($page['visitors'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonna destra: Referrer + Device -->
        <div class="dbsa-col-right">

            <!-- Top Referrer -->
            <div class="db-ui-card">
                <div class="db-ui-card-header">
                    <h3><?php esc_html_e('Top 10 referrer', 'db-site-analytics'); ?></h3>
                </div>
                <div class="db-ui-card-body dbsa-table-wrap">
                    <?php if (empty($top_referrers)) : ?>
                        <div class="db-ui-empty">
                            <span class="db-ui-empty-icon">🔗</span>
                            <span class="db-ui-empty-text"><?php esc_html_e('Nessun referrer nel periodo.', 'db-site-analytics'); ?></span>
                        </div>
                    <?php else : ?>
                        <table class="db-ui-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Referrer', 'db-site-analytics'); ?></th>
                                    <th><?php esc_html_e('Visite', 'db-site-analytics'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($top_referrers as $ref) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url($ref['referrer']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html(parse_url($ref['referrer'], PHP_URL_HOST) ?: $ref['referrer']); ?>
                                            <span class="screen-reader-text"><?php esc_html_e('(si apre in una nuova finestra)', 'db-site-analytics'); ?></span>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($ref['pageviews'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Device Breakdown -->
            <div class="db-ui-card">
                <div class="db-ui-card-header">
                    <h3><?php esc_html_e('Dispositivi', 'db-site-analytics'); ?></h3>
                </div>
                <div class="db-ui-card-body dbsa-device-wrap">
                    <?php if (empty($devices)) : ?>
                        <div class="db-ui-empty">
                            <span class="db-ui-empty-icon">📱</span>
                            <span class="db-ui-empty-text"><?php esc_html_e('Nessun dato.', 'db-site-analytics'); ?></span>
                        </div>
                    <?php else : ?>
                        <canvas id="dbsa-chart-devices" height="120"></canvas>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.dbsa-col-right -->

    </div><!-- /.dbsa-grid-2 -->

    <!-- Confronto periodi -->
    <div class="db-ui-card">
        <div class="db-ui-card-header">
            <h3><?php esc_html_e('Confronto periodi', 'db-site-analytics'); ?></h3>
            <span class="db-ui-badge db-ui-badge-muted dbsa-period-label">
                <?php
                printf(
                    esc_html__('Periodo precedente: %s → %s', 'db-site-analytics'),
                    esc_html($comparison['prev_from']),
                    esc_html($comparison['prev_to'])
                );
                ?>
            </span>
        </div>
        <div class="db-ui-card-body">
            <div class="dbsa-comparison-grid">

                <?php
                $metrics = array(
                    array(
                        'label'   => __('Pageview', 'db-site-analytics'),
                        'icon'    => '📄',
                        'current' => (int) $comparison['current']['pageviews'],
                        'prev'    => (int) $comparison['previous']['pageviews'],
                    ),
                    array(
                        'label'   => __('Visitatori unici', 'db-site-analytics'),
                        'icon'    => '👤',
                        'current' => (int) $comparison['current']['visitors'],
                        'prev'    => (int) $comparison['previous']['visitors'],
                    ),
                );
                foreach ($metrics as $m) :
                    $pct   = dbsa_pct_change($m['current'], $m['prev']);
                    $cls   = dbsa_pct_class($m['current'], $m['prev']);
                ?>
                <div class="dbsa-comparison-item">
                    <div class="dbsa-comparison-icon"><?php echo $m['icon']; ?></div>
                    <div class="dbsa-comparison-data">
                        <div class="dbsa-comparison-label"><?php echo esc_html($m['label']); ?></div>
                        <div class="dbsa-comparison-row">
                            <span class="dbsa-comparison-current"><?php echo esc_html(number_format_i18n($m['current'])); ?></span>
                            <span class="dbsa-comparison-prev">vs <?php echo esc_html(number_format_i18n($m['prev'])); ?></span>
                            <span class="dbsa-comparison-pct <?php echo esc_attr($cls); ?>"><?php echo esc_html($pct); ?></span>
                        </div>
                        <?php
                        $max = max($m['current'], $m['prev'], 1);
                        $pct_bar_cur  = round(($m['current'] / $max) * 100);
                        $pct_bar_prev = round(($m['prev'] / $max) * 100);
                        ?>
                        <div class="dbsa-mini-bars">
                            <div class="dbsa-mini-bar dbsa-mini-bar-current" style="width:<?php echo esc_attr($pct_bar_cur); ?>%"
                                 title="<?php echo esc_attr(__('Periodo corrente', 'db-site-analytics')); ?>"></div>
                            <div class="dbsa-mini-bar dbsa-mini-bar-prev" style="width:<?php echo esc_attr($pct_bar_prev); ?>%"
                                 title="<?php echo esc_attr(__('Periodo precedente', 'db-site-analytics')); ?>"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- Browser & OS + Download KPI -->
    <div class="dbsa-grid-3">

        <!-- Browser -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3><?php esc_html_e('Browser', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body">
                <?php if (empty($browsers)) : ?>
                    <div class="db-ui-empty"><span class="db-ui-empty-icon">🌐</span><span class="db-ui-empty-text"><?php esc_html_e('Nessun dato.', 'db-site-analytics'); ?></span></div>
                <?php else :
                    $max_br = max(array_column($browsers, 'total'), 1);
                    foreach ($browsers as $b) :
                        $pct = round(($b['total'] / $max_br) * 100);
                ?>
                    <div class="dbsa-bar-row">
                        <span class="dbsa-bar-label"><?php echo esc_html($b['browser']); ?></span>
                        <div class="db-ui-progress dbsa-inline-bar">
                            <div class="db-ui-progress-fill" style="width:<?php echo esc_attr($pct); ?>%"></div>
                        </div>
                        <span class="dbsa-bar-value"><?php echo esc_html(number_format_i18n($b['total'])); ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- OS -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3><?php esc_html_e('Sistemi operativi', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body">
                <?php if (empty($os_list)) : ?>
                    <div class="db-ui-empty"><span class="db-ui-empty-icon">💻</span><span class="db-ui-empty-text"><?php esc_html_e('Nessun dato.', 'db-site-analytics'); ?></span></div>
                <?php else :
                    $max_os = max(array_column($os_list, 'total'), 1);
                    foreach ($os_list as $o) :
                        $pct = round(($o['total'] / $max_os) * 100);
                ?>
                    <div class="dbsa-bar-row">
                        <span class="dbsa-bar-label"><?php echo esc_html($o['os']); ?></span>
                        <div class="db-ui-progress dbsa-inline-bar">
                            <div class="db-ui-progress-fill db-ui-progress-success" style="width:<?php echo esc_attr($pct); ?>%"></div>
                        </div>
                        <span class="dbsa-bar-value"><?php echo esc_html(number_format_i18n($o['total'])); ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Download KPI -->
        <div class="db-ui-card">
            <div class="db-ui-card-header"><h3><?php esc_html_e('Download', 'db-site-analytics'); ?></h3></div>
            <div class="db-ui-card-body dbsa-dl-kpi">
                <div class="dbsa-kpi-icon">📥</div>
                <div class="dbsa-kpi-value"><?php echo esc_html(number_format_i18n($dl_total)); ?></div>
                <div class="dbsa-kpi-label"><?php esc_html_e('file scaricati nel periodo', 'db-site-analytics'); ?></div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-downloads&from=' . $from . '&to=' . $to)); ?>"
                   class="db-ui-btn db-ui-btn-primary db-ui-btn-sm" style="margin-top:12px;">
                    <?php esc_html_e('Dettaglio →', 'db-site-analytics'); ?>
                </a>
            </div>
        </div>

    </div><!-- /.dbsa-grid-3 -->

</div><!-- /.wrap -->

<script>
(function() {
    var labels    = <?php echo wp_json_encode($chart_labels); ?>;
    var pvData    = <?php echo wp_json_encode($chart_pv); ?>;
    var uvData    = <?php echo wp_json_encode($chart_uv); ?>;
    var devLabels = <?php echo wp_json_encode($device_labels); ?>;
    var devData   = <?php echo wp_json_encode($device_data); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') return;

        // Grafico linee visite
        var ctxLine = document.getElementById('dbsa-chart-views');
        if (ctxLine) {
            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: '<?php echo esc_js(__('Pageview', 'db-site-analytics')); ?>',
                            data: pvData,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34,113,177,0.08)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                        },
                        {
                            label: '<?php echo esc_js(__('Visitatori unici', 'db-site-analytics')); ?>',
                            data: uvData,
                            borderColor: '#1d6e3f',
                            backgroundColor: 'rgba(29,110,63,0.06)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxTicksLimit: 15 } }
                    }
                }
            });
        }

        // Donut device
        var ctxDev = document.getElementById('dbsa-chart-devices');
        if (ctxDev && devData.length) {
            new Chart(ctxDev, {
                type: 'doughnut',
                data: {
                    labels: devLabels,
                    datasets: [{
                        data: devData,
                        backgroundColor: ['#2271b1', '#1d6e3f', '#dba617'],
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
})();
</script>
