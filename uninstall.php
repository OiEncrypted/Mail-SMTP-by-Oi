<?php
/**
 * Uninstall Handler
 *
 * @package Mail_SMTP_By_Oi
 */

// If uninstall not called from WordPress, exit.
defined('WP_UNINSTALL_PLUGIN') || exit;

$settings = get_option('oi_mail_smtp_settings', []);

// Only delete data if explicitly requested in settings
if (!empty($settings['delete_on_uninstall'])) {
    global $wpdb;

    // Drop logs table
    $table_name = $wpdb->prefix . 'oi_mail_smtp_logs';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    // Delete options
    delete_option('oi_mail_smtp_settings');

    // Clear cron
    $timestamp = wp_next_scheduled('oi_mail_smtp_cleanup_logs');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'oi_mail_smtp_cleanup_logs');
    }
}
