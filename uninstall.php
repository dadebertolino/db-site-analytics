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

// Rimuovi transient updater
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dbgu_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_dbgu_%'");

// Rimuovi cron
wp_clear_scheduled_hook('dbsa_daily_cron');
