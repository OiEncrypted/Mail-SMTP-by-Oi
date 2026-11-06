<?php
/**
 * Main Plugin Orchestrator & Bootstrap
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Mail_SMTP {

    /**
     * Singleton instance
     *
     * @var OI_Mail_SMTP
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return OI_Mail_SMTP
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required classes
     */
    private function load_dependencies() {
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-encryption.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-options.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-logger.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-diagnostics.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-tracker.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-queue.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-routing.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-attachments.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-digest.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-controls.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-privacy.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-site-health.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-mailer.php';

        if (defined('WP_CLI') && WP_CLI) {
            require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-cli.php';
        }

        if (is_admin()) {
            require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-migration.php';
            require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-admin.php';
        }
    }

    /**
     * Initialize core hooks
     */
    private function init_hooks() {
        // Load textdomain
        add_action('init', [$this, 'load_textdomain']);

        // Initialize Tracker, Queue, and Digest
        OI_Tracker::init();
        OI_Queue::init();
        OI_Digest::init();
        OI_Controls::init();
        OI_Privacy::init();
        OI_Site_Health::init();

        // Register WP-CLI commands if running in CLI
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('oi-smtp', 'OI_CLI');
        }

        // Secure attachment download endpoint
        add_action('admin_post_oi_download_attachment', ['OI_Attachments', 'handle_download']);

        // Initialize mailer
        OI_Mailer::init();

        // Initialize admin if in admin area
        if (is_admin()) {
            OI_Admin::init();
        }

        // Daily cleanup cron hook
        add_action('oi_mail_smtp_cleanup_logs', ['OI_Logger', 'cleanup_old_logs']);
    }

    /**
     * Load plugin translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'mail-smtp-by-oi',
            false,
            dirname(OI_MAIL_SMTP_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation handler
     */
    public static function activate() {
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-logger.php';
        OI_Logger::create_table();

        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-queue.php';
        OI_Queue::create_table();

        // Schedule daily cleanup cron if not scheduled
        if (!wp_next_scheduled('oi_mail_smtp_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'oi_mail_smtp_cleanup_logs');
        }

        // Schedule queue runner cron if not scheduled
        if (!wp_next_scheduled('oi_mail_smtp_process_queue_cron')) {
            wp_schedule_event(time(), 'oi_every_two_minutes', 'oi_mail_smtp_process_queue_cron');
        }
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate() {
        // Clear scheduled crons
        $timestamp = wp_next_scheduled('oi_mail_smtp_cleanup_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'oi_mail_smtp_cleanup_logs');
        }

        $queue_timestamp = wp_next_scheduled('oi_mail_smtp_process_queue_cron');
        if ($queue_timestamp) {
            wp_unschedule_event($queue_timestamp, 'oi_mail_smtp_process_queue_cron');
        }

        $digest_timestamp = wp_next_scheduled('oi_mail_smtp_weekly_digest_cron');
        if ($digest_timestamp) {
            wp_unschedule_event($digest_timestamp, 'oi_mail_smtp_weekly_digest_cron');
        }
    }
}
