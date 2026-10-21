<?php
/**
 * Email Logger and Analytics Engine
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Logger {

    /**
     * Get logs table name
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'oi_mail_smtp_logs';
    }

    /**
     * Create or upgrade database table
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            to_email text NOT NULL,
            subject text NOT NULL,
            message longtext NULL,
            headers text NULL,
            attachments text NULL,
            mailer varchar(50) NOT NULL DEFAULT 'smtp',
            status varchar(20) NOT NULL DEFAULT 'sent',
            error_message text NULL,
            debug_log longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log an email transaction
     *
     * @param array $data
     * @return int|false
     */
    public static function log(array $data) {
        if (!OI_Options::get('enable_logging', true)) {
            return false;
        }

        global $wpdb;
        $table = self::get_table_name();

        $log_body = OI_Options::get('log_body', true);

        $to_str = is_array($data['to']) ? implode(', ', $data['to']) : (string)($data['to'] ?? '');
        $attachments_str = '';
        if (!empty($data['attachments'])) {
            $att_names = [];
            foreach ((array)$data['attachments'] as $att) {
                $att_names[] = is_string($att) ? basename($att) : 'attachment';
            }
            $attachments_str = implode(', ', $att_names);
        }

        $headers_str = '';
        if (!empty($data['headers'])) {
            $headers_str = is_array($data['headers']) ? implode("\n", $data['headers']) : (string)$data['headers'];
        }

        $insert_data = [
            'to_email'      => $to_str,
            'subject'       => sanitize_text_field($data['subject'] ?? ''),
            'message'       => $log_body ? ($data['message'] ?? '') : __('(Email body logging is disabled in settings)', 'mail-smtp-by-oi'),
            'headers'       => $headers_str,
            'attachments'   => $attachments_str,
            'mailer'        => sanitize_key($data['mailer'] ?? 'smtp'),
            'status'        => ($data['status'] ?? 'sent') === 'sent' ? 'sent' : 'failed',
            'error_message' => !empty($data['error_message']) ? sanitize_text_field($data['error_message']) : null,
            'debug_log'     => !empty($data['debug_log']) ? $data['debug_log'] : null,
            'created_at'    => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $insert_data);

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Get paginated logs
     *
     * @param array $args
     * @return array ['items' => array, 'total' => int, 'pages' => int, 'current_page' => int]
     */
    public static function get_logs(array $args = []) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => '',
            'search'   => '',
            'orderby'  => 'id',
            'order'    => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $page     = max(1, absint($args['page']));
        $per_page = max(5, min(100, absint($args['per_page'])));
        $offset   = ($page - 1) * $per_page;

        $where = ['1=1'];
        $values = [];

        if (!empty($args['status']) && in_array($args['status'], ['sent', 'failed'], true)) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like(trim($args['search'])) . '%';
            $where[] = '(to_email LIKE %s OR subject LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        $order_by = in_array(strtolower($args['orderby']), ['id', 'created_at', 'status'], true) ? strtolower($args['orderby']) : 'id';
        $order_dir = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Count total
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        if (!empty($values)) {
            $total = (int)$wpdb->get_var($wpdb->prepare($count_sql, $values));
        } else {
            $total = (int)$wpdb->get_var($count_sql);
        }

        // Query items
        $query_sql = "SELECT id, to_email, subject, mailer, status, error_message, created_at FROM $table WHERE $where_clause ORDER BY $order_by $order_dir LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($query_sql, $values), ARRAY_A);

        return [
            'items'        => $items ?: [],
            'total'        => $total,
            'pages'        => ceil($total / $per_page),
            'current_page' => $page,
        ];
    }

    /**
     * Get single log by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function get_log($id) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", absint($id)), ARRAY_A);
    }

    /**
     * Get stats: total, sent, failed, success_rate
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::get_table_name();

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total' => 0, 'sent' => 0, 'failed' => 0, 'rate' => 100];
        }

        $sent = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'sent'");
        $failed = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");
        $total = $sent + $failed;
        $rate = $total > 0 ? round(($sent / $total) * 100, 1) : 100;

        return [
            'total'  => $total,
            'sent'   => $sent,
            'failed' => $failed,
            'rate'   => $rate,
        ];
    }

    /**
     * Resend an email from log
     *
     * @param int $id
     * @return array ['success' => bool, 'message' => string]
     */
    public static function resend($id) {
        $log = self::get_log($id);
        if (!$log) {
            return ['success' => false, 'message' => __('Log entry not found.', 'mail-smtp-by-oi')];
        }

        $to      = $log['to_email'];
        $subject = '[Resent] ' . $log['subject'];
        $message = $log['message'];
        $headers = !empty($log['headers']) ? explode("\n", $log['headers']) : [];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            return ['success' => true, 'message' => sprintf(__('Email successfully resent to %s.', 'mail-smtp-by-oi'), esc_html($to))];
        }

        return ['success' => false, 'message' => __('Failed to resend email. Check error logs for details.', 'mail-smtp-by-oi')];
    }

    /**
     * Delete a single log
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();
        return (bool)$wpdb->delete($table, ['id' => absint($id)], ['%d']);
    }

    /**
     * Bulk delete logs
     *
     * @param array $ids
     * @return int Number of deleted rows
     */
    public static function delete_bulk(array $ids) {
        global $wpdb;
        $table = self::get_table_name();
        $clean_ids = array_filter(array_map('absint', $ids));
        if (empty($clean_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($clean_ids), '%d'));
        $sql = "DELETE FROM $table WHERE id IN ($placeholders)";
        return (int)$wpdb->query($wpdb->prepare($sql, $clean_ids));
    }

    /**
     * Clear all logs
     *
     * @return bool
     */
    public static function clear_all() {
        global $wpdb;
        $table = self::get_table_name();
        return (bool)$wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Auto cleanup old logs based on retention days
     */
    public static function cleanup_old_logs() {
        $retention_days = absint(OI_Options::get('log_retention_days', 30));
        if ($retention_days <= 0) {
            return; // 0 = never cleanup
        }

        global $wpdb;
        $table = self::get_table_name();
        $date_threshold = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));

        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", $date_threshold));
    }
}
