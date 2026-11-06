<?php
/**
 * Asynchronous Background Email Queue Engine
 *
 * Prevents checkout/form latency by offloading email dispatch to background.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Queue {

    /**
     * Get queue table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'oi_mail_smtp_queue';
    }

    /**
     * Create or upgrade queue table
     */
    public static function create_table() {
        global $wpdb;
        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mail_data longtext NOT NULL,
            attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            last_error text NULL,
            scheduled_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status_scheduled (status, scheduled_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Initialize queue hooks & cron
     */
    public static function init() {
        // Cron action for scheduled queue processing
        add_action('oi_mail_smtp_process_queue_cron', [__CLASS__, 'process_queue']);

        // Non-blocking async endpoints
        add_action('wp_ajax_oi_process_queue', [__CLASS__, 'ajax_process_queue']);
        add_action('wp_ajax_nopriv_oi_process_queue', [__CLASS__, 'ajax_process_queue']);

        // Ensure cron event is scheduled every 2 minutes
        add_filter('cron_schedules', [__CLASS__, 'add_cron_interval']);
        if (!wp_next_scheduled('oi_mail_smtp_process_queue_cron')) {
            wp_schedule_event(time(), 'oi_every_two_minutes', 'oi_mail_smtp_process_queue_cron');
        }
    }

    /**
     * Add 2-minute cron interval
     *
     * @param array $schedules
     * @return array
     */
    public static function add_cron_interval($schedules) {
        $schedules['oi_every_two_minutes'] = [
            'interval' => 120,
            'display'  => __('Every Two Minutes', 'mail-smtp-by-oi'),
        ];
        return $schedules;
    }

    /**
     * Check if queue is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool)OI_Options::get('enable_queue', false);
    }

    /**
     * Enqueue an email for background dispatch
     *
     * @param array $mail_args
     * @return int|false
     */
    public static function enqueue(array $mail_args) {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'mail_data'    => wp_json_encode($mail_args),
            'attempts'     => 0,
            'status'       => 'pending',
            'scheduled_at' => current_time('mysql'),
            'created_at'   => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $data);
        if (!$inserted) {
            return false;
        }

        $queue_id = $wpdb->insert_id;

        // Spawn async non-blocking worker
        self::spawn_worker();

        return $queue_id;
    }

    /**
     * Spawn non-blocking background HTTP worker
     */
    public static function spawn_worker() {
        $url = admin_url('admin-ajax.php?action=oi_process_queue&_oi_nonce=' . wp_create_nonce('oi_queue_worker'));

        wp_remote_post($url, [
            'timeout'     => 0.01,
            'blocking'    => false,
            'sslverify'   => false,
            'httpversion' => '1.0',
        ]);
    }

    /**
     * AJAX handler for async background worker
     */
    public static function ajax_process_queue() {
        $nonce = isset($_REQUEST['_oi_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_oi_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'oi_queue_worker') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized queue runner access.', 'mail-smtp-by-oi'), 403);
        }

        // Fast processing in background
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        self::process_queue();
        exit;
    }

    /**
     * Process pending queue items
     */
    public static function process_queue() {
        // Concurrency lock (max 90 seconds)
        $lock = get_transient('oi_queue_worker_lock');
        if ($lock) {
            return;
        }
        set_transient('oi_queue_worker_lock', time(), 90);

        global $wpdb;
        $table = self::get_table_name();

        // Check table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            delete_transient('oi_queue_worker_lock');
            return;
        }

        $now = current_time('mysql');
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = 'pending' AND scheduled_at <= %s ORDER BY id ASC LIMIT 10",
                $now
            ),
            ARRAY_A
        );

        if (empty($items)) {
            delete_transient('oi_queue_worker_lock');
            return;
        }

        foreach ($items as $item) {
            $queue_id  = (int)$item['id'];
            $attempts  = (int)$item['attempts'] + 1;
            $mail_args = json_decode($item['mail_data'], true);

            // Mark as processing
            $wpdb->update($table, ['status' => 'processing', 'attempts' => $attempts], ['id' => $queue_id]);

            // Dispatch direct via OI_Mailer
            $success = OI_Mailer::dispatch_direct($mail_args);

            if ($success) {
                // Delete completed from queue
                $wpdb->delete($table, ['id' => $queue_id]);
            } else {
                $error_msg = __('Failed to send from background queue.', 'mail-smtp-by-oi');

                if ($attempts < 3) {
                    // Retry with exponential delay (2 min, 4 min) in local site timezone
                    $next_retry = date_i18n('Y-m-d H:i:s', current_time('timestamp') + ($attempts * 120));
                    $wpdb->update($table, [
                        'status'       => 'pending',
                        'scheduled_at' => $next_retry,
                        'last_error'   => $error_msg,
                    ], ['id' => $queue_id]);
                } else {
                    // Permanently failed after 3 attempts
                    $wpdb->update($table, [
                        'status'     => 'failed',
                        'last_error' => sprintf(__('Permanently failed after %d attempts.', 'mail-smtp-by-oi'), $attempts),
                    ], ['id' => $queue_id]);

                    OI_Mailer::trigger_failure_alert(sprintf(__('Queue delivery failed permanently for: %s', 'mail-smtp-by-oi'), $mail_args['subject'] ?? ''));
                }
            }
        }

        delete_transient('oi_queue_worker_lock');
    }

    /**
     * Get queue stats
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::get_table_name();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['pending' => 0, 'processing' => 0, 'failed' => 0];
        }

        $pending    = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        $processing = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'processing'");
        $failed     = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");

        return [
            'pending'    => $pending,
            'processing' => $processing,
            'failed'     => $failed,
        ];
    }
}
