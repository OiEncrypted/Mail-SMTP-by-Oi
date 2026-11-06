<?php
/**
 * Secure Email Attachment Vault & Downloader
 *
 * Saves copies of sent attachments (e.g. WooCommerce PDF invoices) in a protected
 * directory and allows authorized administrators to preview and download them from logs.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Attachments {

    /**
     * Get attachments storage directory path
     *
     * @return string
     */
    public static function get_vault_dir() {
        $upload_dir = wp_upload_dir();
        $vault = trailingslashit($upload_dir['basedir']) . 'oi-mail-smtp/attachments';
        if (!file_exists($vault)) {
            wp_mkdir_p($vault);
            // Protect directory from direct browser access
            file_put_contents($vault . '/.htaccess', "Order Deny,Allow\nDeny from all\n");
            file_put_contents($vault . '/index.php', "<?php // Silence is golden.\nexit;\n");
        }
        return $vault;
    }

    /**
     * Archive outgoing attachments for an email log
     *
     * @param int $log_id
     * @param array|string $attachments
     * @return array List of archived relative paths/filenames
     */
    public static function archive($log_id, $attachments) {
        if (!OI_Options::get('save_attachments', true) || empty($attachments) || empty($log_id)) {
            return [];
        }

        $vault = self::get_vault_dir();
        $log_dir = trailingslashit($vault) . absint($log_id);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $archived = [];
        $files = is_array($attachments) ? $attachments : [$attachments];

        foreach ($files as $file_path) {
            if (!is_string($file_path) || !file_exists($file_path)) {
                continue;
            }

            $filename = sanitize_file_name(basename($file_path));
            $destination = trailingslashit($log_dir) . $filename;

            if (@copy($file_path, $destination)) {
                $archived[] = $filename;
            }
        }

        return $archived;
    }

    /**
     * Check if a log has archived attachments in vault
     *
     * @param int $log_id
     * @return array
     */
    public static function get_log_attachments($log_id) {
        $vault = self::get_vault_dir();
        $log_dir = trailingslashit($vault) . absint($log_id);

        if (!is_dir($log_dir)) {
            return [];
        }

        $files = scandir($log_dir);
        $results = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'index.php' || $file === '.htaccess') {
                continue;
            }
            $full_path = trailingslashit($log_dir) . $file;
            if (is_file($full_path)) {
                $results[] = [
                    'filename' => $file,
                    'size'     => size_format(filesize($full_path), 2),
                    'url'      => admin_url('admin-post.php?action=oi_download_attachment&log_id=' . absint($log_id) . '&file=' . urlencode($file) . '&nonce=' . wp_create_nonce('oi_download_' . $log_id . '_' . $file)),
                ];
            }
        }

        return $results;
    }

    /**
     * Delete archived attachments for a deleted log entry
     *
     * @param int $log_id
     */
    public static function delete_log_attachments($log_id) {
        $vault = self::get_vault_dir();
        $log_dir = trailingslashit($vault) . absint($log_id);

        if (!is_dir($log_dir)) {
            return;
        }

        $files = scandir($log_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                @unlink(trailingslashit($log_dir) . $file);
            }
        }
        @rmdir($log_dir);
    }

    /**
     * Clear all attachment files stored in the vault
     */
    public static function clear_vault() {
        $vault = self::get_vault_dir();
        if (!is_dir($vault)) {
            return;
        }

        $items = scandir($vault);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === 'index.php') {
                continue;
            }
            $target = trailingslashit($vault) . $item;
            if (is_dir($target)) {
                $subfiles = scandir($target);
                foreach ($subfiles as $sf) {
                    if ($sf !== '.' && $sf !== '..') {
                        @unlink(trailingslashit($target) . $sf);
                    }
                }
                @rmdir($target);
            }
        }
    }

    /**
     * Handle attachment download request
     */
    public static function handle_download() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'mail-smtp-by-oi'), 403);
        }

        $log_id = isset($_GET['log_id']) ? absint($_GET['log_id']) : 0;
        $file   = isset($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';
        $nonce  = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'oi_download_' . $log_id . '_' . $file)) {
            wp_die(esc_html__('Security verification failed.', 'mail-smtp-by-oi'), 403);
        }

        $vault = self::get_vault_dir();
        $target = trailingslashit($vault) . $log_id . '/' . $file;

        if (!file_exists($target) || !is_file($target)) {
            wp_die(esc_html__('Attachment file not found.', 'mail-smtp-by-oi'), 404);
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($target) : 'application/octet-stream';
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($target));
        readfile($target);
        exit;
    }
}
