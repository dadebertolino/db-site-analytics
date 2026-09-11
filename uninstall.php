<?php
/**
 * Uninstall — DB Site Analytics
 * Eseguito da WordPress alla disinstallazione del plugin.
 * Rimuove tabelle, opzioni e transient.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Rimuovi tabelle
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dbsa_pageviews");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dbsa_downloads");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dbsa_events");

// Rimuovi opzioni
delete_option('dbsa_settings');
delete_option('dbsa_daily_salt');
delete_option('dbsa_salt_date');
delete_option('dbsa_schema_version');
delete_option('dbsa_geoip_updated');
delete_option('dbsa_geoip_last_error');
delete_option('dbsa_cache_gen');
delete_option('dbsa_backfill_cursor');

// Rimuovi database GeoIP
$upload = wp_upload_dir();
$geoip_dir = trailingslashit($upload['basedir']) . 'dbsa-geoip';
if (is_dir($geoip_dir)) {
    @unlink($geoip_dir . '/dbip-country-lite.mmdb');
    @rmdir($geoip_dir);
}
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dbsa_salt_lock_%'");

// Rimuovi transient rate-limit e cache
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dbsa_rl_%' OR option_name LIKE '_transient_timeout_dbsa_rl_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dbsa_c_%'  OR option_name LIKE '_transient_timeout_dbsa_c_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dbsa_sc_%' OR option_name LIKE '_transient_timeout_dbsa_sc_%'");

// Rimuovi transient updater
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dbgu_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_dbgu_%'");

// Rimuovi cron
wp_clear_scheduled_hook('dbsa_daily_cron');
wp_clear_scheduled_hook('dbsa_backfill_referrer_host');
