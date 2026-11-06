<?php
/**
 * WordPress Core Privacy & GDPR Integration
 *
 * Implements WordPress Personal Data Exporter and Personal Data Eraser
 * for compliance with GDPR, CCPA, and international data privacy regulations.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Privacy {

    /**
     * Initialize privacy hooks
     */
    public static function init() {
        add_filter('wp_privacy_personal_data_exporters', [__CLASS__, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [__CLASS__, 'register_eraser']);
    }

    /**
     * Register Personal Data Exporter
     *
     * @param array $exporters
     * @return array
     */
    public static function register_exporter($exporters) {
        $exporters['oi_mail_smtp_logs'] = [
            'exporter_friendly_name' => __('Email Logs (Mail SMTP by Oi)', 'mail-smtp-by-oi'),
            'callback'               => [__CLASS__, 'export_personal_data'],
        ];
        return $exporters;
    }

    /**
     * Export personal email logs for a given email address
     *
     * @param string $email_address
     * @param int $page
     * @return array
     */
    public static function export_personal_data($email_address, $page = 1) {
        global $wpdb;
        $table = OI_Logger::get_table_name();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [
                'data' => [],
                'done' => true,
            ];
        }

        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE to_email LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
                '%' . $wpdb->esc_like($email_address) . '%',
                $limit,
                $offset
            ),
            ARRAY_A
        );

        $export_items = [];

        if (!empty($results)) {
            foreach ($results as $row) {
                $item_id = "oi-mail-log-{$row['id']}";

                $data = [
                    ['name' => __('Log ID', 'mail-smtp-by-oi'), 'value' => $row['id']],
                    ['name' => __('Sent Date', 'mail-smtp-by-oi'), 'value' => $row['created_at']],
                    ['name' => __('Recipient', 'mail-smtp-by-oi'), 'value' => $row['to_email']],
                    ['name' => __('Subject', 'mail-smtp-by-oi'), 'value' => $row['subject']],
                    ['name' => __('Mailer Service', 'mail-smtp-by-oi'), 'value' => strtoupper($row['mailer'])],
                    ['name' => __('Delivery Status', 'mail-smtp-by-oi'), 'value' => ucfirst($row['status'])],
                ];

                $export_items[] = [
                    'group_id'    => 'oi_mail_smtp_logs',
                    'group_label' => __('Email Logs (Mail SMTP by Oi)', 'mail-smtp-by-oi'),
                    'item_id'     => $item_id,
                    'data'        => $data,
                ];
            }
        }

        $done = count($results) < $limit;

        return [
            'data' => $export_items,
            'done' => $done,
        ];
    }

    /**
     * Register Personal Data Eraser
     *
     * @param array $erasers
     * @return array
     */
    public static function register_eraser($erasers) {
        $erasers['oi_mail_smtp_logs'] = [
            'eraser_friendly_name' => __('Email Logs (Mail SMTP by Oi)', 'mail-smtp-by-oi'),
            'callback'             => [__CLASS__, 'erase_personal_data'],
        ];
        return $erasers;
    }

    /**
     * Erase personal email logs for a given email address
     *
     * @param string $email_address
     * @param int $page
     * @return array
     */
    public static function erase_personal_data($email_address, $page = 1) {
        global $wpdb;
        $table = OI_Logger::get_table_name();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [
                'items_removed'  => 0,
                'items_retained' => 0,
                'messages'       => [],
                'done'           => true,
            ];
        }

        $limit = 50;

        // Fetch IDs to remove
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE to_email LIKE %s LIMIT %d",
                '%' . $wpdb->esc_like($email_address) . '%',
                $limit
            )
        );

        $items_removed = 0;

        if (!empty($ids)) {
            $id_placeholders = implode(',', array_map('intval', $ids));
            $deleted = $wpdb->query("DELETE FROM $table WHERE id IN ($id_placeholders)");
            if ($deleted) {
                $items_removed = (int)$deleted;
            }
        }

        $done = count($ids) < $limit;

        return [
            'items_removed'  => $items_removed,
            'items_retained' => 0,
            'messages'       => $items_removed > 0 ? [sprintf(__('Deleted %d email log entries matching %s.', 'mail-smtp-by-oi'), $items_removed, $email_address)] : [],
            'done'           => $done,
        ];
    }
}

