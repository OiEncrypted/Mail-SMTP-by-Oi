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
        require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-mailer.php';

        if (is_admin()) {
            require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-admin.php';
        }
    }

    /**
     * Initialize core hooks
     */
    private function init_hooks() {
        // Load textdomain
        add_action('init', [$this, 'load_textdomain']);

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

        // Schedule daily cleanup cron if not scheduled
        if (!wp_next_scheduled('oi_mail_smtp_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'oi_mail_smtp_cleanup_logs');
        }
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('oi_mail_smtp_cleanup_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'oi_mail_smtp_cleanup_logs');
        }
    }
}
