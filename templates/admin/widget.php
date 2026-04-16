<?php
/**
 * Template: Widget Dashboard WP
 *
 * Variabili: $stats_today, $stats_yesterday, $stats_7d, $top (array)
 */

if (!defined('ABSPATH')) exit;
?>
<div class="dbsa-widget">
    <div class="dbsa-widget-stats">
        <div class="dbsa-widget-stat">
            <span class="dbsa-widget-value"><?php echo esc_html(number_format_i18n($stats_today['pageviews'])); ?></span>
            <span class="dbsa-widget-label"><?php esc_html_e('Oggi', 'db-site-analytics'); ?></span>
        </div>
        <div class="dbsa-widget-stat">
            <span class="dbsa-widget-value"><?php echo esc_html(number_format_i18n($stats_yesterday['pageviews'])); ?></span>
            <span class="dbsa-widget-label"><?php esc_html_e('Ieri', 'db-site-analytics'); ?></span>
        </div>
        <div class="dbsa-widget-stat">
            <span class="dbsa-widget-value"><?php echo esc_html(number_format_i18n($stats_7d['pageviews'])); ?></span>
            <span class="dbsa-widget-label"><?php esc_html_e('7 giorni', 'db-site-analytics'); ?></span>
        </div>
    </div>

    <?php if (!empty($top[0])) : ?>
        <div class="dbsa-widget-top">
            <span class="dbsa-widget-top-label"><?php esc_html_e('Più vista oggi:', 'db-site-analytics'); ?></span>
            <a href="<?php echo esc_url($top[0]['page_url']); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($top[0]['page_title'] ?: $top[0]['page_url']); ?>
            </a>
        </div>
    <?php endif; ?>

    <a href="<?php echo esc_url(admin_url('admin.php?page=dbsa-dashboard')); ?>" class="db-ui-btn db-ui-btn-primary db-ui-btn-sm dbsa-widget-link">
        <?php esc_html_e('Vedi dashboard →', 'db-site-analytics'); ?>
    </a>
</div>
