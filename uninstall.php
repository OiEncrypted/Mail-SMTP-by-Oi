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

    // Drop logs and queue tables
    $logs_table  = $wpdb->prefix . 'oi_mail_smtp_logs';
    $queue_table = $wpdb->prefix . 'oi_mail_smtp_queue';
    $wpdb->query("DROP TABLE IF EXISTS $logs_table");
    $wpdb->query("DROP TABLE IF EXISTS $queue_table");

    // Delete options and transients
    delete_option('oi_mail_smtp_settings');
    delete_transient('oi_email_failure_alert');

    // Clear scheduled crons
    $crons = [
        'oi_mail_smtp_cleanup_logs',
        'oi_mail_smtp_process_queue_cron',
        'oi_mail_smtp_weekly_digest_cron',
    ];

    foreach ($crons as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    // Clean up attachment vault folder
    $upload_dir = wp_upload_dir();
    $vault_dir = trailingslashit($upload_dir['basedir']) . 'oi-mail-smtp';
    if (is_dir($vault_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vault_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                @rmdir($fileinfo->getRealPath());
            } else {
                @unlink($fileinfo->getRealPath());
            }
        }
        @rmdir($vault_dir);
    }
}
