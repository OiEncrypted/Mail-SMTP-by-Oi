<?php
/**
 * Admin Controller, Menus & AJAX Endpoints
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Admin {

    /**
     * Page hook suffix
     */
    private static $page_hook = '';

    /**
     * Initialize Admin hooks
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('plugin_action_links_' . OI_MAIL_SMTP_BASENAME, [__CLASS__, 'add_action_links']);

        // Admin notices & CSV export & Dashboard Widget & Admin Bar
        add_action('admin_notices', [__CLASS__, 'display_failure_notice']);
        add_action('admin_post_oi_export_logs_csv', [__CLASS__, 'handle_export_csv']);
        add_action('wp_dashboard_setup', [__CLASS__, 'register_dashboard_widget']);
        add_action('admin_bar_menu', [__CLASS__, 'register_admin_bar'], 99);

        // AJAX handlers
        add_action('wp_ajax_oi_save_settings', [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_oi_send_test_email', [__CLASS__, 'ajax_send_test_email']);
        add_action('wp_ajax_oi_test_port', [__CLASS__, 'ajax_test_port']);
        add_action('wp_ajax_oi_get_log_details', [__CLASS__, 'ajax_get_log_details']);
        add_action('wp_ajax_oi_resend_email', [__CLASS__, 'ajax_resend_email']);
        add_action('wp_ajax_oi_delete_log', [__CLASS__, 'ajax_delete_log']);
        add_action('wp_ajax_oi_delete_bulk_logs', [__CLASS__, 'ajax_delete_bulk_logs']);
        add_action('wp_ajax_oi_clear_all_logs', [__CLASS__, 'ajax_clear_all_logs']);
        add_action('wp_ajax_oi_check_dns', [__CLASS__, 'ajax_check_dns']);
        add_action('wp_ajax_oi_dismiss_failure_alert', [__CLASS__, 'ajax_dismiss_failure_alert']);
        add_action('wp_ajax_oi_import_settings', [__CLASS__, 'ajax_import_settings']);
        add_action('wp_ajax_oi_test_alert_notification', [__CLASS__, 'ajax_test_alert_notification']);
        add_action('wp_ajax_oi_send_test_digest', [__CLASS__, 'ajax_send_test_digest']);
    }

    /**
     * Register Admin Menus
     */
    public static function register_admin_menu() {
        $stats = OI_Logger::get_stats();
        $badge = '';
        if ($stats['failed'] > 0) {
            $badge = sprintf(' <span class="update-plugins count-%d"><span class="plugin-count">%d</span></span>', $stats['failed'], $stats['failed']);
        }

        self::$page_hook = add_menu_page(
            __('Mail SMTP by Oi', 'mail-smtp-by-oi'),
            __('Mail SMTP', 'mail-smtp-by-oi') . $badge,
            'manage_options',
            'mail-smtp-by-oi',
            [__CLASS__, 'render_admin_page'],
            'dashicons-email-alt2',
            65
        );

        add_submenu_page(
            'mail-smtp-by-oi',
            __('Settings - Mail SMTP by Oi', 'mail-smtp-by-oi'),
            __('Settings', 'mail-smtp-by-oi'),
            'manage_options',
            'mail-smtp-by-oi',
            [__CLASS__, 'render_admin_page']
        );

        add_submenu_page(
            'mail-smtp-by-oi',
            __('Email Logs - Mail SMTP by Oi', 'mail-smtp-by-oi'),
            __('Email Logs', 'mail-smtp-by-oi'),
            'manage_options',
            'mail-smtp-by-oi-logs',
            [__CLASS__, 'render_logs_page']
        );

        add_submenu_page(
            'mail-smtp-by-oi',
            __('Test Email - Mail SMTP by Oi', 'mail-smtp-by-oi'),
            __('Test Email', 'mail-smtp-by-oi'),
            'manage_options',
            'mail-smtp-by-oi-test',
            [__CLASS__, 'render_test_page']
        );

        add_submenu_page(
            'mail-smtp-by-oi',
            __('Diagnostics - Mail SMTP by Oi', 'mail-smtp-by-oi'),
            __('Diagnostics', 'mail-smtp-by-oi'),
            'manage_options',
            'mail-smtp-by-oi-diagnostics',
            [__CLASS__, 'render_diagnostics_page']
        );
    }

    /**
     * Add settings link to plugins list page
     *
     * @param array $links
     * @return array
     */
    public static function add_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=mail-smtp-by-oi')) . '">' . esc_html__('Settings', 'mail-smtp-by-oi') . '</a>';
        $logs_link = '<a href="' . esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs')) . '">' . esc_html__('Logs', 'mail-smtp-by-oi') . '</a>';
        array_unshift($links, $logs_link);
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Enqueue Admin Assets
     *
     * @param string $hook
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'mail-smtp-by-oi') === false) {
            return;
        }

        wp_enqueue_style(
            'oi-mail-smtp-admin',
            OI_MAIL_SMTP_URL . 'assets/css/admin.css',
            [],
            OI_MAIL_SMTP_VERSION
        );

        wp_enqueue_script(
            'oi-mail-smtp-admin',
            OI_MAIL_SMTP_URL . 'assets/js/admin.js',
            ['jquery'],
            OI_MAIL_SMTP_VERSION,
            true
        );

        wp_localize_script('oi-mail-smtp-admin', 'oiMailSmtp', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('oi_mail_smtp_nonce'),
            'i18n'     => [
                'saving'             => __('Saving settings...', 'mail-smtp-by-oi'),
                'saved'              => __('Settings saved successfully!', 'mail-smtp-by-oi'),
                'sending'            => __('Sending test email...', 'mail-smtp-by-oi'),
                'sent'               => __('Test email sent successfully!', 'mail-smtp-by-oi'),
                'failed'             => __('Failed to send email. Check debug output.', 'mail-smtp-by-oi'),
                'confirm_delete'     => __('Are you sure you want to delete this log entry?', 'mail-smtp-by-oi'),
                'confirm_bulk'       => __('Are you sure you want to delete the selected log entries?', 'mail-smtp-by-oi'),
                'confirm_clear_all'  => __('Are you sure you want to delete ALL email logs? This cannot be undone.', 'mail-smtp-by-oi'),
                'testing_port'       => __('Testing connection...', 'mail-smtp-by-oi'),
                'copy_success'       => __('Copied to clipboard!', 'mail-smtp-by-oi'),
            ],
        ]);
    }

    /**
     * Determine active tab based on query param or current page
     *
     * @return string
     */
    private static function get_active_tab() {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'mail-smtp-by-oi';
        if ($page === 'mail-smtp-by-oi-logs') return 'logs';
        if ($page === 'mail-smtp-by-oi-test') return 'test';
        if ($page === 'mail-smtp-by-oi-diagnostics') return 'diagnostics';

        return isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    }

    /**
     * Get authentic brand SVG icon for mailers
     *
     * @param string $key
     * @return string
     */
    public static function get_mailer_icon($key) {
        switch ($key) {
            case 'gmail':
                return '<svg width="28" height="28" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>';
            case 'outlook':
                return '<svg width="26" height="26" viewBox="0 0 24 24"><path fill="#F25022" d="M1 1h10v10H1z"/><path fill="#7FBA00" d="M13 1h10v10H13z"/><path fill="#00A4EF" d="M1 13h10v10H1z"/><path fill="#FFB900" d="M13 13h10v10H13z"/></svg>';
            case 'sendgrid':
                return '<svg width="26" height="26" viewBox="0 0 24 24" fill="#009DD9"><path d="M11.6 0H0v11.6h11.6V0zm12.4 0H12.4v11.6H24V0zM11.6 12.4H0V24h11.6V12.4zm12.4 0H12.4V24H24V12.4z"/></svg>';
            case 'mailgun':
                return '<svg width="26" height="26" viewBox="0 0 24 24" fill="#E2373F"><path d="M12 13.5l11-8.5H1l11 8.5zm11-6.5L12 15 1 7v12h22V7z"/></svg>';
            case 'brevo':
                return '<svg width="26" height="26" viewBox="0 0 24 24" fill="#0B996F"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14.5h-2v-2h2v2zm0-4h-2V7h2v5.5z"/></svg>';
            case 'postmark':
                return '<svg width="26" height="26" viewBox="0 0 24 24"><rect width="24" height="24" rx="4" fill="#1A1F2C"/><path d="M4 6h16v12H4z" fill="#FFDE00"/><path d="M4 6l8 6 8-6" stroke="#1A1F2C" stroke-width="2" fill="none"/></svg>';
            case 'ses':
                return '<svg width="28" height="28" viewBox="0 0 24 24" fill="#FF9900"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>';
            case 'resend':
                return '<svg width="26" height="26" viewBox="0 0 24 24" fill="#000000"><path d="M2 4h20v16H2V4zm2 2v1.5l8 5 8-5V6H4zm16 12V9.5l-8 5-8-5V18h16z"/></svg>';
            case 'default':
                return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
            case 'smtp':
            default:
                return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>';
        }
    }

    /**
     * Render Main Admin Container with Navigation Tabs
     */
    public static function render_admin_page() {
        $active_tab = self::get_active_tab();
        $stats      = OI_Logger::get_stats();
        $settings   = OI_Options::get_all(true);
        ?>
        <div class="wrap oi-smtp-wrap">
            <!-- Header Banner -->
            <div class="oi-header-card">
                <div class="oi-header-brand">
                    <div class="oi-brand-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>
                    <div>
                        <h1 class="oi-title"><?php esc_html_e('Mail SMTP by Oi', 'mail-smtp-by-oi'); ?></h1>
                        <p class="oi-subtitle"><?php esc_html_e('Reliable, secure & ultra-fast email deliverability for WordPress', 'mail-smtp-by-oi'); ?> <span class="oi-version-pill">v<?php echo esc_html(OI_MAIL_SMTP_VERSION); ?></span></p>
                    </div>
                </div>
                <div class="oi-header-status">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-test')); ?>" class="oi-header-action-btn">
                        <span class="dashicons dashicons-email"></span> <?php esc_html_e('Quick Test', 'mail-smtp-by-oi'); ?>
                    </a>
                    <?php if (!empty($settings['enabled'])) : ?>
                        <span class="oi-status-pill oi-status-active">
                            <span class="oi-dot"></span> <?php esc_html_e('SMTP Active', 'mail-smtp-by-oi'); ?> (<?php echo esc_html(strtoupper($settings['mailer'])); ?>)
                        </span>
                    <?php else : ?>
                        <span class="oi-status-pill oi-status-inactive">
                            <span class="oi-dot"></span> <?php esc_html_e('SMTP Disabled', 'mail-smtp-by-oi'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="oi-stats-grid">
                <div class="oi-stat-card">
                    <div class="oi-stat-label"><?php esc_html_e('Total Emails Sent', 'mail-smtp-by-oi'); ?></div>
                    <div class="oi-stat-value oi-text-success"><?php echo number_format_i18n($stats['sent']); ?></div>
                    <div class="oi-stat-hint"><?php esc_html_e('Successfully delivered', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div class="oi-stat-card">
                    <div class="oi-stat-label"><?php esc_html_e('Failed Emails', 'mail-smtp-by-oi'); ?></div>
                    <div class="oi-stat-value oi-text-danger"><?php echo number_format_i18n($stats['failed']); ?></div>
                    <div class="oi-stat-hint"><?php esc_html_e('Errors captured in logs', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div class="oi-stat-card">
                    <div class="oi-stat-label"><?php esc_html_e('Delivery Success Rate', 'mail-smtp-by-oi'); ?></div>
                    <div class="oi-stat-value oi-text-primary"><?php echo esc_html($stats['rate']); ?>%</div>
                    <div class="oi-stat-hint"><?php esc_html_e('Overall reliability', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div class="oi-stat-card">
                    <div class="oi-stat-label"><?php esc_html_e('Active Mailer', 'mail-smtp-by-oi'); ?></div>
                    <div class="oi-stat-value oi-text-mailer"><?php echo esc_html(ucfirst($settings['mailer'])); ?></div>
                    <div class="oi-stat-hint"><?php echo !empty($settings['smtp_host']) ? esc_html($settings['smtp_host']) : esc_html__('Cloud API / Default', 'mail-smtp-by-oi'); ?></div>
                </div>
            </div>

            <!-- 7-Day Email Activity Trend -->
            <?php
            $daily = OI_Logger::get_daily_stats(7);
            $max_count = max(1, $daily['max_count']);
            ?>
            <div class="oi-trend-card">
                <div class="oi-trend-header">
                    <div>
                        <div class="oi-trend-title"><?php esc_html_e('7-Day Email Delivery Activity', 'mail-smtp-by-oi'); ?></div>
                        <div class="oi-trend-subtitle"><?php esc_html_e('Sent (Green) vs Failed (Red) distribution over the last 7 days', 'mail-smtp-by-oi'); ?></div>
                    </div>
                    <div class="oi-trend-legend">
                        <span class="oi-legend-item"><span class="oi-legend-dot oi-dot-sent"></span> <?php esc_html_e('Sent', 'mail-smtp-by-oi'); ?></span>
                        <span class="oi-legend-item"><span class="oi-legend-dot oi-dot-failed"></span> <?php esc_html_e('Failed', 'mail-smtp-by-oi'); ?></span>
                    </div>
                </div>
                <div class="oi-bars-container">
                    <?php foreach ($daily['days'] as $day) :
                        $total_h = min(100, max(8, (($day['total'] / $max_count) * 100)));
                    ?>
                        <div class="oi-bar-col" title="<?php echo esc_attr(sprintf('%s: %d Sent, %d Failed', $day['label'], $day['sent'], $day['failed'])); ?>">
                            <div class="oi-bar-track">
                                <div class="oi-bar-stack" style="height: <?php echo esc_attr($total_h); ?>%;">
                                    <?php if ($day['sent'] > 0) : ?>
                                        <div class="oi-bar-sent" style="flex-grow: <?php echo esc_attr($day['sent']); ?>;"></div>
                                    <?php endif; ?>
                                    <?php if ($day['failed'] > 0) : ?>
                                        <div class="oi-bar-failed" style="flex-grow: <?php echo esc_attr($day['failed']); ?>;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="oi-bar-label"><?php echo esc_html($day['label']); ?></span>
                            <span class="oi-bar-count"><?php echo esc_html($day['total']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <nav class="nav-tab-wrapper oi-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi&tab=settings')); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Settings', 'mail-smtp-by-oi'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs')); ?>" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Email Logs', 'mail-smtp-by-oi'); ?>
                    <?php if ($stats['failed'] > 0) : ?>
                        <span class="oi-tab-badge"><?php echo esc_html($stats['failed']); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-test')); ?>" class="nav-tab <?php echo $active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-testimonial"></span> <?php esc_html_e('Send Test Email', 'mail-smtp-by-oi'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-diagnostics')); ?>" class="nav-tab <?php echo $active_tab === 'diagnostics' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-heart"></span> <?php esc_html_e('Diagnostics & Ports', 'mail-smtp-by-oi'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi&tab=tools')); ?>" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Tools & About', 'mail-smtp-by-oi'); ?>
                </a>
            </nav>

            <!-- Tab Content -->
            <div class="oi-tab-content">
                <?php
                switch ($active_tab) {
                    case 'logs':
                        self::render_logs_tab();
                        break;
                    case 'test':
                        self::render_test_tab();
                        break;
                    case 'diagnostics':
                        self::render_diagnostics_tab();
                        break;
                    case 'tools':
                        self::render_tools_tab();
                        break;
                    case 'settings':
                    default:
                        self::render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <!-- Email Details Modal with Sandboxed Iframe & Tabs -->
        <div id="oi-email-modal" class="oi-modal-backdrop" style="display:none;">
            <div class="oi-modal-container">
                <div class="oi-modal-header">
                    <h3 class="oi-modal-title" id="oi-modal-subject"><?php esc_html_e('Email Details', 'mail-smtp-by-oi'); ?></h3>
                    <button type="button" class="oi-modal-close" id="oi-modal-close-btn">&times;</button>
                </div>
                <div class="oi-modal-nav">
                    <button type="button" class="oi-modal-tab active" data-tab="preview"><?php esc_html_e('HTML Preview', 'mail-smtp-by-oi'); ?></button>
                    <button type="button" class="oi-modal-tab" data-tab="raw"><?php esc_html_e('Raw / Text', 'mail-smtp-by-oi'); ?></button>
                    <button type="button" class="oi-modal-tab" data-tab="meta"><?php esc_html_e('Headers & Info', 'mail-smtp-by-oi'); ?></button>
                    <button type="button" class="oi-modal-tab" data-tab="attachments"><?php esc_html_e('Attachments', 'mail-smtp-by-oi'); ?> <span id="oi-modal-attachments-badge" class="oi-badge" style="display:none; padding:1px 6px; font-size:10px; margin-left:4px;"></span></button>
                    <button type="button" class="oi-modal-tab" data-tab="debug"><?php esc_html_e('Debug Trace', 'mail-smtp-by-oi'); ?></button>
                    <button type="button" class="oi-modal-tab" data-tab="forward"><?php esc_html_e('Resend / Forward', 'mail-smtp-by-oi'); ?></button>
                </div>
                <div class="oi-modal-body" id="oi-modal-content">
                    <div id="oi-tab-preview" class="oi-modal-pane active">
                        <iframe id="oi-modal-iframe" class="oi-modal-iframe" sandbox="allow-same-origin"></iframe>
                    </div>
                    <div id="oi-tab-raw" class="oi-modal-pane">
                        <pre id="oi-modal-raw-body" class="oi-modal-raw"></pre>
                    </div>
                    <div id="oi-tab-meta" class="oi-modal-pane">
                        <div id="oi-modal-meta-box" class="oi-modal-meta"></div>
                    </div>
                    <div id="oi-tab-attachments" class="oi-modal-pane">
                        <div id="oi-modal-attachments-list"></div>
                    </div>
                    <div id="oi-tab-debug" class="oi-modal-pane">
                        <pre id="oi-modal-debug-log" class="oi-terminal-body" style="border-radius:8px; max-height:300px;"></pre>
                    </div>
                    <div id="oi-tab-forward" class="oi-modal-pane">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                            <h4 style="margin:0 0 10px 0; font-size:14px; color:#0f172a;"><?php esc_html_e('Resend or Forward Email', 'mail-smtp-by-oi'); ?></h4>
                            <p class="oi-help" style="margin-bottom:14px;"><?php esc_html_e('You can resend this email to the original recipient, or specify a new recipient email address to forward it.', 'mail-smtp-by-oi'); ?></p>
                            <div class="oi-form-group">
                                <label class="oi-label" for="oi-modal-forward-to"><?php esc_html_e('Recipient Email Address', 'mail-smtp-by-oi'); ?></label>
                                <input type="email" class="oi-input" id="oi-modal-forward-to" placeholder="recipient@example.com">
                            </div>
                            <div style="margin-top:14px; display:flex; align-items:center; gap:12px;">
                                <button type="button" class="button button-primary" id="oi-modal-forward-btn">
                                    <span class="dashicons dashicons-email-alt" style="vertical-align:middle; margin-right:2px;"></span>
                                    <?php esc_html_e('Send Now', 'mail-smtp-by-oi'); ?>
                                </button>
                                <span id="oi-modal-forward-status" style="font-size:12px; font-weight:600;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Toast Notification System -->
        <div id="oi-toast" class="oi-toast" style="display:none;">
            <div class="oi-toast-icon"></div>
            <div class="oi-toast-msg"></div>
        </div>
        <?php
    }

    /**
     * Submenu wrappers
     */
    public static function render_logs_page() {
        $_GET['tab'] = 'logs';
        self::render_admin_page();
    }

    public static function render_test_page() {
        $_GET['tab'] = 'test';
        self::render_admin_page();
    }

    public static function render_diagnostics_page() {
        $_GET['tab'] = 'diagnostics';
        self::render_admin_page();
    }

    /**
     * Settings Tab
     */
    private static function render_settings_tab() {
        $settings = OI_Options::get_all(true);
        ?>
        <form id="oi-settings-form" class="oi-card" method="post">
            <div class="oi-card-header">
                <h2><?php esc_html_e('General Email Configuration', 'mail-smtp-by-oi'); ?></h2>
                <p><?php esc_html_e('Configure how WordPress and all plugins (WooCommerce, forms, newsletters) dispatch emails.', 'mail-smtp-by-oi'); ?></p>
            </div>

            <!-- Master Enable Switch -->
            <div class="oi-form-group oi-toggle-row">
                <div class="oi-toggle-text">
                    <label class="oi-label" for="oi_enabled"><?php esc_html_e('Enable Mail SMTP', 'mail-smtp-by-oi'); ?></label>
                    <span class="oi-help"><?php esc_html_e('Turn ON to route all outgoing WordPress emails through your configured mailer.', 'mail-smtp-by-oi'); ?></span>
                </div>
                <label class="oi-switch">
                    <input type="checkbox" name="enabled" id="oi_enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                    <span class="oi-slider round"></span>
                </label>
            </div>

            <!-- Developer Staging & Simulation Mode Card (FluentSMTP & Post-SMTP Killer Feature) -->
            <div class="oi-helper-card" style="margin-top:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px 22px; display:block;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span class="dashicons dashicons-admin-tools" style="font-size:22px; width:22px; height:22px; color:#4f46e5;"></span>
                        <div>
                            <strong style="font-size:14px; color:#0f172a; display:block;"><?php esc_html_e('Developer Staging & Sandbox Mode', 'mail-smtp-by-oi'); ?></strong>
                            <span class="oi-help" style="margin:2px 0 0 0;"><?php esc_html_e('Simulate email delivery without sending to real users, or reroute all outgoing emails to a single test inbox.', 'mail-smtp-by-oi'); ?></span>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_simulation_mode" id="oi_enable_simulation_mode" value="1" <?php checked(!empty($settings['enable_simulation_mode'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>
                <div id="oi-simulation-options" style="<?php echo !empty($settings['enable_simulation_mode']) ? '' : 'display:none;'; ?> margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">
                    <div class="oi-form-group" style="margin-bottom:0; max-width:440px;">
                        <label class="oi-label" for="oi_reroute_all_to"><?php esc_html_e('Optional: Reroute All Outgoing Emails To', 'mail-smtp-by-oi'); ?></label>
                        <input type="email" class="oi-input" name="reroute_all_to" id="oi_reroute_all_to" value="<?php echo esc_attr($settings['reroute_all_to'] ?? ''); ?>" placeholder="developer@example.com">
                        <span class="oi-help"><?php esc_html_e('Leave blank to only log emails in DB without sending. If an email is entered, all site emails will be forwarded exclusively to this inbox.', 'mail-smtp-by-oi'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Step 01: Sender Identity -->
            <div class="oi-step-header">
                <div class="oi-step-badge">01</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('Sender Identity', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Define the From Email and Name that appears on all outgoing messages.', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <div class="oi-grid-2">
                <div class="oi-form-group">
                    <label class="oi-label" for="oi_from_email"><?php esc_html_e('From Email', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                    <input type="email" class="oi-input" name="from_email" id="oi_from_email" value="<?php echo esc_attr($settings['from_email']); ?>" required placeholder="no-reply@yourdomain.com">
                    <span class="oi-help"><?php esc_html_e('The email address that emails will be sent from.', 'mail-smtp-by-oi'); ?></span>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="force_from_email" value="1" <?php checked(!empty($settings['force_from_email'])); ?>>
                        <?php esc_html_e('Force From Email (Prevents other plugins from overriding with invalid addresses)', 'mail-smtp-by-oi'); ?>
                    </label>
                </div>

                <div class="oi-form-group">
                    <label class="oi-label" for="oi_from_name"><?php esc_html_e('From Name', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                    <input type="text" class="oi-input" name="from_name" id="oi_from_name" value="<?php echo esc_attr($settings['from_name']); ?>" required placeholder="My Store / Website">
                    <span class="oi-help"><?php esc_html_e('The name that will appear as the sender in recipient inboxes.', 'mail-smtp-by-oi'); ?></span>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="force_from_name" value="1" <?php checked(!empty($settings['force_from_name'])); ?>>
                        <?php esc_html_e('Force From Name across all website notifications', 'mail-smtp-by-oi'); ?>
                    </label>
                </div>
            </div>

            <div class="oi-form-group">
                <label class="oi-checkbox-label">
                    <input type="checkbox" name="set_return_path" value="1" <?php checked(!empty($settings['set_return_path'])); ?>>
                    <?php esc_html_e('Set Return-Path to match From Email (Delivery bounce notifications will be routed properly)', 'mail-smtp-by-oi'); ?>
                </label>
            </div>

            <!-- Step 02: Choose Mail Service Provider -->
            <div class="oi-step-header" style="margin-top:36px;">
                <div class="oi-step-badge">02</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('Choose Mail Service Provider', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Select between direct SMTP connection or modern high-volume cloud APIs.', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <div class="oi-mailers-grid">
                <?php
                $mailers = [
                    'ses'      => ['name' => 'Amazon SES', 'desc' => 'AWS cloud email ($0.10/1k), SigV4 REST API', 'badge' => 'Recommended', 'badge_type' => 'recommended'],
                    'smtp'     => ['name' => 'Custom SMTP', 'desc' => 'Any standard SMTP server (cPanel, Postfix, Zimbra)', 'badge' => 'Universal', 'badge_type' => 'popular'],
                    'gmail'    => ['name' => 'Google / Gmail', 'desc' => 'Send via Gmail / Google Workspace SMTP (587 TLS)', 'badge' => 'Popular', 'badge_type' => 'popular'],
                    'outlook'  => ['name' => 'Outlook / 365', 'desc' => 'Send via Microsoft 365 / Outlook SMTP', 'badge' => 'Enterprise', 'badge_type' => 'enterprise'],
                    'resend'   => ['name' => 'Resend', 'desc' => 'Modern developer-first transactional email API', 'badge' => 'Modern API', 'badge_type' => 'developer'],
                    'sendgrid' => ['name' => 'SendGrid', 'desc' => 'High-volume reliable cloud API mailer', 'badge' => 'High Volume', 'badge_type' => 'popular'],
                    'postmark' => ['name' => 'Postmark', 'desc' => 'Lightning fast transactional email delivery', 'badge' => 'Fastest', 'badge_type' => 'developer'],
                    'brevo'    => ['name' => 'Brevo (Sendinblue)', 'desc' => '300 free emails/day via direct REST API', 'badge' => 'Free Tier', 'badge_type' => 'developer'],
                    'mailgun'  => ['name' => 'Mailgun', 'desc' => 'Powerful developer API with US / EU regions', 'badge' => 'Developer', 'badge_type' => 'developer'],
                    'default'  => ['name' => 'Default (PHP Mail)', 'desc' => 'Native web server mail fallback (Not recommended)', 'badge' => 'Fallback', 'badge_type' => 'fallback'],
                ];

                foreach ($mailers as $key => $info) :
                    $checked = ($settings['mailer'] === $key);
                ?>
                    <label class="oi-mailer-card <?php echo $checked ? 'oi-mailer-selected' : ''; ?>" data-mailer="<?php echo esc_attr($key); ?>">
                        <?php if (!empty($info['badge'])) : ?>
                            <span class="oi-mailer-badge oi-badge-<?php echo esc_attr($info['badge_type']); ?>"><?php echo esc_html($info['badge']); ?></span>
                        <?php endif; ?>
                        <input type="radio" name="mailer" value="<?php echo esc_attr($key); ?>" <?php checked($checked); ?> class="oi-mailer-radio">
                        <div class="oi-mailer-icon">
                            <?php echo self::get_mailer_icon($key); ?>
                        </div>
                        <div class="oi-mailer-content">
                            <div class="oi-mailer-name"><?php echo esc_html($info['name']); ?></div>
                            <div class="oi-mailer-desc"><?php echo esc_html($info['desc']); ?></div>
                        </div>
                        <div class="oi-mailer-check">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Step 03: Service Credentials & Authentication -->
            <div class="oi-step-header" style="margin-top:36px;">
                <div class="oi-step-badge">03</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('Service Credentials & Authentication', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Configure server details or API tokens (Encrypted using AES-256-CBC).', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <!-- Custom SMTP & Presets Section -->
            <div id="oi-smtp-fields" class="oi-conditional-section" style="<?php echo in_array($settings['mailer'], ['smtp', 'gmail', 'outlook'], true) ? '' : 'display:none;'; ?>">
                <!-- Gmail Quick Help -->
                <div class="oi-helper-card oi-helper-gmail" style="<?php echo ($settings['mailer'] === 'gmail') ? '' : 'display:none;'; ?>">
                    <div class="oi-helper-icon"><span class="dashicons dashicons-info"></span></div>
                    <div class="oi-helper-body">
                        <strong><?php esc_html_e('How to set up Google / Gmail SMTP:', 'mail-smtp-by-oi'); ?></strong>
                        <ol>
                            <li><?php printf(esc_html__('Log in to your %s and ensure 2-Step Verification is active.', 'mail-smtp-by-oi'), '<a href="https://myaccount.google.com/security" target="_blank" rel="noopener">Google Account Security ↗</a>'); ?></li>
                            <li><?php printf(esc_html__('Generate a 16-character password in %s (choose App: "Other" and name it "WordPress").', 'mail-smtp-by-oi'), '<a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Google App Passwords ↗</a>'); ?></li>
                            <li><?php esc_html_e('Paste that 16-character code into the "SMTP Password" field below.', 'mail-smtp-by-oi'); ?></li>
                        </ol>
                    </div>
                </div>

                <div class="oi-grid-3">
                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_smtp_host"><?php esc_html_e('SMTP Host', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <input type="text" class="oi-input" name="smtp_host" id="oi_smtp_host" value="<?php echo esc_attr($settings['smtp_host']); ?>" placeholder="smtp.yourdomain.com">
                        <span class="oi-help"><?php esc_html_e('Outgoing mail server address', 'mail-smtp-by-oi'); ?></span>
                    </div>

                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_smtp_port"><?php esc_html_e('SMTP Port', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <input type="number" class="oi-input" name="smtp_port" id="oi_smtp_port" value="<?php echo esc_attr($settings['smtp_port']); ?>" placeholder="587">
                        <span class="oi-help"><?php esc_html_e('Usually 587 (TLS), 465 (SSL), or 2525', 'mail-smtp-by-oi'); ?></span>
                    </div>

                    <div class="oi-form-group">
                        <label class="oi-label"><?php esc_html_e('Encryption', 'mail-smtp-by-oi'); ?></label>
                        <div class="oi-radio-group">
                            <label><input type="radio" name="smtp_encryption" value="none" <?php checked($settings['smtp_encryption'], 'none'); ?>> <?php esc_html_e('None', 'mail-smtp-by-oi'); ?></label>
                            <label><input type="radio" name="smtp_encryption" value="ssl" <?php checked($settings['smtp_encryption'], 'ssl'); ?>> <?php esc_html_e('SSL', 'mail-smtp-by-oi'); ?></label>
                            <label><input type="radio" name="smtp_encryption" value="tls" <?php checked($settings['smtp_encryption'], 'tls'); ?>> <?php esc_html_e('TLS', 'mail-smtp-by-oi'); ?></label>
                        </div>
                    </div>
                </div>

                <div class="oi-grid-2">
                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_smtp_user"><?php esc_html_e('SMTP Username', 'mail-smtp-by-oi'); ?></label>
                        <input type="text" class="oi-input" name="smtp_user" id="oi_smtp_user" value="<?php echo esc_attr($settings['smtp_user']); ?>" placeholder="you@yourdomain.com" autocomplete="off">
                    </div>

                    <div class="oi-form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label class="oi-label" for="oi_smtp_pass"><?php esc_html_e('SMTP Password / App Password', 'mail-smtp-by-oi'); ?></label>
                            <?php if (!empty($settings['smtp_pass'])) : ?>
                                <span class="oi-key-badge-saved"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Saved & Encrypted', 'mail-smtp-by-oi'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="oi-password-wrapper">
                            <input type="password" class="oi-input" name="smtp_pass" id="oi_smtp_pass" value="" placeholder="<?php echo !empty($settings['smtp_pass']) ? esc_attr__('•••••••• (Saved - leave blank to keep)', 'mail-smtp-by-oi') : esc_attr__('Enter SMTP Password', 'mail-smtp-by-oi'); ?>" autocomplete="new-password">
                            <button type="button" class="oi-toggle-password-btn" aria-label="Toggle password visibility"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                        <span class="oi-help"><?php esc_html_e('Encrypted securely in database using AES-256-CBC.', 'mail-smtp-by-oi'); ?></span>
                    </div>
                </div>

                <div class="oi-grid-2">
                    <div class="oi-form-group">
                        <label class="oi-checkbox-label">
                            <input type="checkbox" name="smtp_auth" value="1" <?php checked(!empty($settings['smtp_auth'])); ?>>
                            <?php esc_html_e('Enable SMTP Authentication (Recommended)', 'mail-smtp-by-oi'); ?>
                        </label>
                    </div>
                    <div class="oi-form-group">
                        <label class="oi-checkbox-label">
                            <input type="checkbox" name="smtp_autotls" value="1" <?php checked(!empty($settings['smtp_autotls'])); ?>>
                            <?php esc_html_e('Enable AutoTLS (Automatically upgrades unencrypted socket to TLS)', 'mail-smtp-by-oi'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Generic API Key Section (SendGrid, Brevo, Postmark, Resend) -->
            <div id="oi-api-fields" class="oi-conditional-section" style="<?php echo in_array($settings['mailer'], ['sendgrid', 'brevo', 'postmark', 'resend'], true) ? '' : 'display:none;'; ?>">
                <div class="oi-helper-card">
                    <div class="oi-helper-icon"><span class="dashicons dashicons-admin-network"></span></div>
                    <div class="oi-helper-body">
                        <strong><?php esc_html_e('Direct REST API Advantage:', 'mail-smtp-by-oi'); ?></strong>
                        <p><?php esc_html_e('Cloud APIs send over standard HTTPS (Port 443). They are completely unaffected by web hosting SMTP port blocks or firewall limits.', 'mail-smtp-by-oi'); ?></p>
                    </div>
                </div>

                <div class="oi-form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label class="oi-label" for="oi_api_key"><?php esc_html_e('API Key / Server Token', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <?php if (!empty($settings['api_key'])) : ?>
                            <span class="oi-key-badge-saved"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Saved & Encrypted', 'mail-smtp-by-oi'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="oi-password-wrapper">
                        <input type="password" class="oi-input" name="api_key" id="oi_api_key" value="" placeholder="<?php echo !empty($settings['api_key']) ? esc_attr__('•••••••• (Saved - leave blank to keep)', 'mail-smtp-by-oi') : esc_attr__('Paste your API Key or Token here', 'mail-smtp-by-oi'); ?>" autocomplete="new-password">
                        <button type="button" class="oi-toggle-password-btn" aria-label="Toggle visibility"><span class="dashicons dashicons-visibility"></span></button>
                    </div>
                    <span class="oi-help"><?php esc_html_e('Stored with AES-256-CBC encryption. Leave blank to keep existing key.', 'mail-smtp-by-oi'); ?></span>
                </div>
            </div>

            <!-- Mailgun Specific Fields -->
            <div id="oi-mailgun-fields" class="oi-conditional-section" style="<?php echo ($settings['mailer'] === 'mailgun') ? '' : 'display:none;'; ?>">
                <div class="oi-grid-3">
                    <div class="oi-form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label class="oi-label" for="oi_mailgun_api_key"><?php esc_html_e('Mailgun Private API Key', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                            <?php if (!empty($settings['mailgun_api_key'])) : ?>
                                <span class="oi-key-badge-saved"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Saved', 'mail-smtp-by-oi'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="oi-password-wrapper">
                            <input type="password" class="oi-input" name="mailgun_api_key" id="oi_mailgun_api_key" value="" placeholder="<?php echo !empty($settings['mailgun_api_key']) ? esc_attr__('•••••••• (Saved - leave blank to keep)', 'mail-smtp-by-oi') : 'key-xxxx'; ?>" autocomplete="new-password">
                            <button type="button" class="oi-toggle-password-btn" aria-label="Toggle visibility"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                    </div>

                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_mailgun_domain"><?php esc_html_e('Domain Name', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <input type="text" class="oi-input" name="mailgun_domain" id="oi_mailgun_domain" value="<?php echo esc_attr($settings['mailgun_domain']); ?>" placeholder="mg.yourdomain.com">
                    </div>

                    <div class="oi-form-group">
                        <label class="oi-label"><?php esc_html_e('Mailgun Region', 'mail-smtp-by-oi'); ?></label>
                        <select class="oi-select" name="mailgun_region">
                            <option value="us" <?php selected($settings['mailgun_region'], 'us'); ?>><?php esc_html_e('US (United States)', 'mail-smtp-by-oi'); ?></option>
                            <option value="eu" <?php selected($settings['mailgun_region'], 'eu'); ?>><?php esc_html_e('EU (Europe)', 'mail-smtp-by-oi'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Amazon SES Specific Fields -->
            <div id="oi-ses-fields" class="oi-conditional-section" style="<?php echo ($settings['mailer'] === 'ses') ? '' : 'display:none;'; ?>">
                <div class="oi-helper-card">
                    <div class="oi-helper-icon"><span class="dashicons dashicons-cloud"></span></div>
                    <div class="oi-helper-body">
                        <strong><?php esc_html_e('Amazon SES Direct REST API (Signature Version 4):', 'mail-smtp-by-oi'); ?></strong>
                        <p><?php esc_html_e('Ultra-fast, lowest cost ($0.10 / 1,000 emails), enterprise-grade email delivery via AWS SES v2 API. No heavy AWS SDK needed. Ensure your IAM credentials have ses:SendRawEmail and ses:SendEmail permissions, and your sender domain or email is verified in the AWS SES Console.', 'mail-smtp-by-oi'); ?></p>
                    </div>
                </div>

                <div class="oi-grid-3">
                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_ses_access_key"><?php esc_html_e('AWS Access Key ID', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <input type="text" class="oi-input" name="ses_access_key" id="oi_ses_access_key" value="<?php echo !empty($settings['ses_access_key']) ? esc_attr($settings['ses_access_key']) : ''; ?>" placeholder="AKIAIOSFODNN7EXAMPLE" autocomplete="off">
                        <span class="oi-help"><?php esc_html_e('Your 20-character AWS Access Key ID', 'mail-smtp-by-oi'); ?></span>
                    </div>

                    <div class="oi-form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label class="oi-label" for="oi_ses_secret_key"><?php esc_html_e('AWS Secret Access Key', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                            <?php if (!empty($settings['ses_secret_key'])) : ?>
                                <span class="oi-key-badge-saved"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Saved', 'mail-smtp-by-oi'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="oi-password-wrapper">
                            <input type="password" class="oi-input" name="ses_secret_key" id="oi_ses_secret_key" value="" placeholder="<?php echo !empty($settings['ses_secret_key']) ? esc_attr__('•••••••• (Saved - leave blank to keep)', 'mail-smtp-by-oi') : 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'; ?>" autocomplete="new-password">
                            <button type="button" class="oi-toggle-password-btn" aria-label="Toggle visibility"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                        <span class="oi-help"><?php esc_html_e('Stored securely with AES-256-CBC encryption', 'mail-smtp-by-oi'); ?></span>
                    </div>

                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_ses_region"><?php esc_html_e('AWS Region', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <select class="oi-select" name="ses_region" id="oi_ses_region">
                            <option value="us-east-1" <?php selected($settings['ses_region'], 'us-east-1'); ?>>US East (N. Virginia) us-east-1</option>
                            <option value="us-east-2" <?php selected($settings['ses_region'], 'us-east-2'); ?>>US East (Ohio) us-east-2</option>
                            <option value="us-west-1" <?php selected($settings['ses_region'], 'us-west-1'); ?>>US West (N. California) us-west-1</option>
                            <option value="us-west-2" <?php selected($settings['ses_region'], 'us-west-2'); ?>>US West (Oregon) us-west-2</option>
                            <option value="eu-west-1" <?php selected($settings['ses_region'], 'eu-west-1'); ?>>Europe (Ireland) eu-west-1</option>
                            <option value="eu-west-2" <?php selected($settings['ses_region'], 'eu-west-2'); ?>>Europe (London) eu-west-2</option>
                            <option value="eu-central-1" <?php selected($settings['ses_region'], 'eu-central-1'); ?>>Europe (Frankfurt) eu-central-1</option>
                            <option value="ap-south-1" <?php selected($settings['ses_region'], 'ap-south-1'); ?>>Asia Pacific (Mumbai) ap-south-1</option>
                            <option value="ap-southeast-1" <?php selected($settings['ses_region'], 'ap-southeast-1'); ?>>Asia Pacific (Singapore) ap-southeast-1</option>
                            <option value="ap-southeast-2" <?php selected($settings['ses_region'], 'ap-southeast-2'); ?>>Asia Pacific (Sydney) ap-southeast-2</option>
                            <option value="ap-northeast-1" <?php selected($settings['ses_region'], 'ap-northeast-1'); ?>>Asia Pacific (Tokyo) ap-northeast-1</option>
                            <option value="ca-central-1" <?php selected($settings['ses_region'], 'ca-central-1'); ?>>Canada (Central) ca-central-1</option>
                            <option value="sa-east-1" <?php selected($settings['ses_region'], 'sa-east-1'); ?>>South America (São Paulo) sa-east-1</option>
                        </select>
                        <span class="oi-help"><?php esc_html_e('Select the region where your verified SES domain/email resides', 'mail-smtp-by-oi'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Step 04: Reliability, Logging & Multi-Channel Alerts -->
            <div class="oi-step-header" style="margin-top:36px;">
                <div class="oi-step-badge">04</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('Logging, Fallback & Multi-Channel Instant Alerts', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Set retention limits, automatic cleanup, and instant alerts to Telegram, Slack, Discord, or Email.', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <div class="oi-grid-2" style="margin-bottom:20px;">
                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-list-view"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Email Delivery Logging', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Track all outgoing emails with status, headers, and technical debug logs.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_logging" value="1" <?php checked(!empty($settings['enable_logging'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-media-text"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Log Email Content / Body', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Store complete HTML content (Disable for strict GDPR privacy compliance).', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="log_body" value="1" <?php checked(!empty($settings['log_body'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-shield-alt"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Automatic Fallback to PHP mail()', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Emergency safety net: sends via PHP mail if your primary SMTP mailer fails.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_fallback" value="1" <?php checked(!empty($settings['enable_fallback'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-bell"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Instant Failure Alerts', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Get notified via Telegram, Slack, Discord, or Email if delivery fails.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_failure_alerts" id="oi_enable_failure_alerts" value="1" <?php checked(!empty($settings['enable_failure_alerts'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>
            </div>

            <div class="oi-form-group" style="max-width:380px;">
                <label class="oi-label" for="oi_log_retention_days"><?php esc_html_e('Automatic Log Retention & Purge', 'mail-smtp-by-oi'); ?></label>
                <select class="oi-select" name="log_retention_days" id="oi_log_retention_days">
                    <option value="7" <?php selected($settings['log_retention_days'], 7); ?>><?php esc_html_e('7 Days', 'mail-smtp-by-oi'); ?></option>
                    <option value="14" <?php selected($settings['log_retention_days'], 14); ?>><?php esc_html_e('14 Days', 'mail-smtp-by-oi'); ?></option>
                    <option value="30" <?php selected($settings['log_retention_days'], 30); ?>><?php esc_html_e('30 Days (Recommended)', 'mail-smtp-by-oi'); ?></option>
                    <option value="60" <?php selected($settings['log_retention_days'], 60); ?>><?php esc_html_e('60 Days', 'mail-smtp-by-oi'); ?></option>
                    <option value="90" <?php selected($settings['log_retention_days'], 90); ?>><?php esc_html_e('90 Days', 'mail-smtp-by-oi'); ?></option>
                    <option value="0" <?php selected($settings['log_retention_days'], 0); ?>><?php esc_html_e('Keep Forever (No auto delete)', 'mail-smtp-by-oi'); ?></option>
                </select>
                <span class="oi-help"><?php esc_html_e('Logs older than this threshold are purged automatically by daily cron.', 'mail-smtp-by-oi'); ?></span>
            </div>

            <!-- Multi-Channel Notification Configuration Box -->
            <div id="oi-alert-channels-box" class="oi-helper-card" style="margin-top:16px; background:#fafafa; border:1px solid #e2e8f0; <?php echo !empty($settings['enable_failure_alerts']) ? '' : 'display:none;'; ?>">
                <div style="font-weight:700; font-size:14px; margin-bottom:12px; color:#0f172a;">
                    <span class="dashicons dashicons-bell" style="vertical-align:middle; color:#4f46e5; margin-right:4px;"></span>
                    <?php esc_html_e('Notification Alert Channels', 'mail-smtp-by-oi'); ?>
                </div>

                <?php
                $active_channels = !empty($settings['alert_channels']) ? (array)$settings['alert_channels'] : ['email'];
                ?>
                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="alert_channels[]" value="email" <?php checked(in_array('email', $active_channels, true)); ?>>
                        <strong><?php esc_html_e('Email Alert', 'mail-smtp-by-oi'); ?></strong>
                    </label>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="alert_channels[]" value="telegram" <?php checked(in_array('telegram', $active_channels, true)); ?>>
                        <strong><?php esc_html_e('Telegram Bot', 'mail-smtp-by-oi'); ?></strong>
                    </label>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="alert_channels[]" value="slack" <?php checked(in_array('slack', $active_channels, true)); ?>>
                        <strong><?php esc_html_e('Slack Webhook', 'mail-smtp-by-oi'); ?></strong>
                    </label>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="alert_channels[]" value="discord" <?php checked(in_array('discord', $active_channels, true)); ?>>
                        <strong><?php esc_html_e('Discord Webhook', 'mail-smtp-by-oi'); ?></strong>
                    </label>
                </div>

                <!-- Email Channel Details -->
                <div class="oi-form-group" style="margin-bottom:14px;">
                    <label class="oi-label" for="oi_failure_alert_email"><?php esc_html_e('Alert Recipient Email', 'mail-smtp-by-oi'); ?></label>
                    <input type="email" class="oi-input" name="failure_alert_email" id="oi_failure_alert_email" value="<?php echo esc_attr($settings['failure_alert_email']); ?>" placeholder="admin@yourdomain.com">
                </div>

                <!-- Telegram Channel Details -->
                <div class="oi-grid-2" style="margin-bottom:14px;">
                    <div class="oi-form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label class="oi-label" for="oi_telegram_bot_token"><?php esc_html_e('Telegram Bot Token', 'mail-smtp-by-oi'); ?></label>
                            <?php if (!empty($settings['telegram_bot_token'])) : ?>
                                <span class="oi-key-badge-saved"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Saved', 'mail-smtp-by-oi'); ?></span>
                            <?php endif; ?>
                        </div>
                        <input type="password" class="oi-input" name="telegram_bot_token" id="oi_telegram_bot_token" value="" placeholder="<?php echo !empty($settings['telegram_bot_token']) ? esc_attr__('•••••••• (Saved - leave blank to keep)', 'mail-smtp-by-oi') : '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ'; ?>" autocomplete="new-password">
                        <span class="oi-help"><?php esc_html_e('Create a bot via @BotFather in Telegram to get your token.', 'mail-smtp-by-oi'); ?></span>
                    </div>
                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_telegram_chat_id"><?php esc_html_e('Telegram Chat ID', 'mail-smtp-by-oi'); ?></label>
                        <input type="text" class="oi-input" name="telegram_chat_id" id="oi_telegram_chat_id" value="<?php echo esc_attr($settings['telegram_chat_id']); ?>" placeholder="e.g. 123456789 or @channelusername">
                        <span class="oi-help"><?php esc_html_e('Your personal Telegram chat ID or channel ID.', 'mail-smtp-by-oi'); ?></span>
                    </div>
                </div>

                <!-- Slack Channel Details -->
                <div class="oi-form-group" style="margin-bottom:14px;">
                    <label class="oi-label" for="oi_slack_webhook_url"><?php esc_html_e('Slack Incoming Webhook URL', 'mail-smtp-by-oi'); ?></label>
                    <input type="url" class="oi-input" name="slack_webhook_url" id="oi_slack_webhook_url" value="<?php echo esc_attr($settings['slack_webhook_url']); ?>" placeholder="https://hooks.slack.com/services/T00/B00/XXXX">
                </div>

                <!-- Discord Channel Details -->
                <div class="oi-form-group" style="margin-bottom:14px;">
                    <label class="oi-label" for="oi_discord_webhook_url"><?php esc_html_e('Discord Incoming Webhook URL', 'mail-smtp-by-oi'); ?></label>
                    <input type="url" class="oi-input" name="discord_webhook_url" id="oi_discord_webhook_url" value="<?php echo esc_attr($settings['discord_webhook_url']); ?>" placeholder="https://discord.com/api/webhooks/123/XXXX">
                </div>

                <!-- Live Test Alert Button -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:12px; margin-top:14px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <span style="font-weight:600; font-size:13px; color:#334155;"><?php esc_html_e('Test Notification Channel:', 'mail-smtp-by-oi'); ?></span>
                    <select id="oi_test_channel_select" class="oi-select" style="width:auto; min-width:140px;">
                        <option value="email"><?php esc_html_e('Email', 'mail-smtp-by-oi'); ?></option>
                        <option value="telegram"><?php esc_html_e('Telegram', 'mail-smtp-by-oi'); ?></option>
                        <option value="slack"><?php esc_html_e('Slack', 'mail-smtp-by-oi'); ?></option>
                        <option value="discord"><?php esc_html_e('Discord', 'mail-smtp-by-oi'); ?></option>
                    </select>
                    <button type="button" class="button button-secondary" id="oi-test-alert-btn">
                        <span class="dashicons dashicons-megaphone" style="vertical-align:middle; margin-right:2px;"></span>
                        <?php esc_html_e('Send Test Alert', 'mail-smtp-by-oi'); ?>
                    </button>
                    <span id="oi-test-alert-status" style="font-size:12px; font-weight:600;"></span>
                </div>
            </div>

            <!-- Step 05: Performance & Engagement Tracking -->
            <div class="oi-step-header" style="margin-top:36px;">
                <div class="oi-step-badge">05</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('Background Queue & Email Tracking (Pro Features)', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Speed up WooCommerce checkouts with async background queue, and track recipient opens & clicks.', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <div class="oi-grid-3">
                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-performance"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Background Queue', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Async dispatch so WooCommerce checkouts load with zero delay.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_queue" value="1" <?php checked(!empty($settings['enable_queue'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-visibility"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Email Open Tracking', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Detects recipient email opens via an invisible 1x1 tracking pixel.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_open_tracking" value="1" <?php checked(!empty($settings['enable_open_tracking'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-admin-links"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Link Click Tracking', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Monitors recipient link clicks with instant transparent redirect.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="enable_click_tracking" value="1" <?php checked(!empty($settings['enable_click_tracking'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>
            </div>

            <!-- Step 06: Enterprise Suite: Smart Routing, Weekly Digest & Attachment Vault -->
            <div class="oi-step-header" style="margin-top:36px;">
                <div class="oi-step-badge">06</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('Enterprise Suite: Smart Routing, Weekly Digest & Attachment Vault', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Route critical transactional emails via dedicated mailers, receive automated weekly executive scorecards, and safely archive PDF invoices/attachments.', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <!-- Smart Routing Rules Builder -->
            <div class="oi-helper-card" style="margin-bottom:20px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:22px; display:block;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div>
                        <strong style="font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-randomize" style="color:#4f46e5;"></span>
                            <?php esc_html_e('Smart Conditional Email Routing', 'mail-smtp-by-oi'); ?>
                        </strong>
                        <span class="oi-help" style="margin:4px 0 0 0;"><?php esc_html_e('Route specific emails through different SMTP/API services based on Subject, Sender, or Recipient (e.g. WooCommerce orders via Amazon SES, and newsletters via SendGrid).', 'mail-smtp-by-oi'); ?></span>
                    </div>
                    <button type="button" class="button button-secondary" id="oi-add-route-rule-btn">
                        <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle; margin-right:2px;"></span>
                        <?php esc_html_e('Add Routing Rule', 'mail-smtp-by-oi'); ?>
                    </button>
                </div>

                <div id="oi-routing-rules-container">
                    <?php
                    $routing_rules = !empty($settings['routing_rules']) && is_array($settings['routing_rules']) ? $settings['routing_rules'] : [];
                    $mailers_available = [
                        'ses'      => 'Amazon SES',
                        'smtp'     => 'Custom SMTP',
                        'sendgrid' => 'SendGrid',
                        'mailgun'  => 'Mailgun',
                        'brevo'    => 'Brevo',
                        'postmark' => 'Postmark',
                        'resend'   => 'Resend',
                        'gmail'    => 'Gmail / Workspace',
                        'outlook'  => 'Microsoft 365',
                        'default'  => 'Default PHP Mail',
                    ];
                    ?>
                    <div class="oi-table-responsive" id="oi-routing-rules-table" style="<?php echo empty($routing_rules) ? 'display:none;' : ''; ?>">
                        <table class="widefat fixed striped oi-table">
                            <thead>
                                <tr>
                                    <th style="width:160px;"><span class="oi-condition-chip oi-chip-when">WHEN</span> <?php esc_html_e('Field', 'mail-smtp-by-oi'); ?></th>
                                    <th style="width:140px;"><?php esc_html_e('Operator', 'mail-smtp-by-oi'); ?></th>
                                    <th><?php esc_html_e('Matching Value', 'mail-smtp-by-oi'); ?></th>
                                    <th style="width:180px;"><span class="oi-condition-chip oi-chip-then">ROUTE VIA</span> <?php esc_html_e('Mailer', 'mail-smtp-by-oi'); ?></th>
                                    <th style="width:50px; text-align:center;"><?php esc_html_e('Action', 'mail-smtp-by-oi'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="oi-routing-rules-body">
                                <?php foreach ($routing_rules as $idx => $rule) : ?>
                                    <tr class="oi-route-rule-row">
                                        <td>
                                            <select class="oi-select" name="routing_rules[<?php echo esc_attr($idx); ?>][field]">
                                                <option value="subject" <?php selected($rule['field'], 'subject'); ?>><?php esc_html_e('Subject', 'mail-smtp-by-oi'); ?></option>
                                                <option value="to_email" <?php selected($rule['field'] === 'to' || $rule['field'] === 'to_email'); ?>><?php esc_html_e('To Email', 'mail-smtp-by-oi'); ?></option>
                                                <option value="from_email" <?php selected($rule['field'] === 'from' || $rule['field'] === 'from_email'); ?>><?php esc_html_e('From Email', 'mail-smtp-by-oi'); ?></option>
                                                <option value="headers" <?php selected($rule['field'], 'headers'); ?>><?php esc_html_e('Headers', 'mail-smtp-by-oi'); ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="oi-select" name="routing_rules[<?php echo esc_attr($idx); ?>][operator]">
                                                <option value="contains" <?php selected($rule['operator'], 'contains'); ?>><?php esc_html_e('Contains', 'mail-smtp-by-oi'); ?></option>
                                                <option value="equals" <?php selected($rule['operator'], 'equals'); ?>><?php esc_html_e('Equals', 'mail-smtp-by-oi'); ?></option>
                                                <option value="starts_with" <?php selected($rule['operator'], 'starts_with'); ?>><?php esc_html_e('Starts With', 'mail-smtp-by-oi'); ?></option>
                                                <option value="ends_with" <?php selected($rule['operator'], 'ends_with'); ?>><?php esc_html_e('Ends With', 'mail-smtp-by-oi'); ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="oi-input" name="routing_rules[<?php echo esc_attr($idx); ?>][value]" value="<?php echo esc_attr($rule['value']); ?>" placeholder="e.g. [Order #] or @vipcustomer.com">
                                        </td>
                                        <td>
                                            <select class="oi-select" name="routing_rules[<?php echo esc_attr($idx); ?>][mailer]">
                                                <?php foreach ($mailers_available as $m_key => $m_label) : ?>
                                                    <option value="<?php echo esc_attr($m_key); ?>" <?php selected($rule['mailer'], $m_key); ?>><?php echo esc_html($m_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="button oi-btn-delete oi-remove-route-rule-btn" title="<?php esc_attr_e('Remove Rule', 'mail-smtp-by-oi'); ?>">&times;</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="oi-no-routing-rules" class="oi-help" style="padding:16px; text-align:center; background:#f8fafc; border-radius:8px; border:1px dashed #cbd5e1; <?php echo !empty($routing_rules) ? 'display:none;' : ''; ?>">
                        <span class="dashicons dashicons-randomize" style="font-size:24px; width:24px; height:24px; color:#94a3b8; display:block; margin:0 auto 6px auto;"></span>
                        <?php esc_html_e('No conditional routing rules configured yet. All emails will use your default mailer.', 'mail-smtp-by-oi'); ?>
                    </div>
                </div>
            </div>

            <!-- Weekly Executive Digest & Attachment Vault Grid -->
            <div class="oi-grid-2">
                <!-- Weekly Digest Card -->
                <div class="oi-helper-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:22px; display:block;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="oi-feature-icon"><span class="dashicons dashicons-chart-area"></span></div>
                            <div>
                                <strong style="font-size:14px; color:#0f172a; display:block;"><?php esc_html_e('Weekly Executive Email Summary Digest', 'mail-smtp-by-oi'); ?></strong>
                                <span class="oi-help" style="margin:2px 0 0 0;"><?php esc_html_e('Automated email scorecard sent every Monday with volume and delivery rate.', 'mail-smtp-by-oi'); ?></span>
                            </div>
                        </div>
                        <label class="oi-switch">
                            <input type="checkbox" name="enable_weekly_digest" id="oi_enable_weekly_digest" value="1" <?php checked(!empty($settings['enable_weekly_digest'])); ?>>
                            <span class="oi-slider round"></span>
                        </label>
                    </div>

                    <div id="oi-weekly-digest-options" style="<?php echo !empty($settings['enable_weekly_digest']) ? '' : 'display:none;'; ?> margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9;">
                        <div class="oi-form-group">
                            <label class="oi-label" for="oi_digest_recipient_email"><?php esc_html_e('Digest Recipient Email', 'mail-smtp-by-oi'); ?></label>
                            <input type="email" class="oi-input" name="digest_recipient_email" id="oi_digest_recipient_email" value="<?php echo esc_attr($settings['digest_recipient_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        </div>
                        <div style="margin-top:12px; display:flex; align-items:center; gap:10px;">
                            <button type="button" class="button button-secondary" id="oi-test-digest-btn">
                                <span class="dashicons dashicons-email-alt" style="vertical-align:middle; margin-right:2px;"></span>
                                <?php esc_html_e('Send Test Weekly Digest', 'mail-smtp-by-oi'); ?>
                            </button>
                            <span id="oi-test-digest-status" style="font-size:12px; font-weight:600;"></span>
                        </div>
                    </div>
                </div>

                <!-- Attachment Vault Card -->
                <div class="oi-helper-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:22px; display:block;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="oi-feature-icon"><span class="dashicons dashicons-paperclip"></span></div>
                            <div>
                                <strong style="font-size:14px; color:#0f172a; display:block;"><?php esc_html_e('Secure Email Attachment Vault', 'mail-smtp-by-oi'); ?></strong>
                                <span class="oi-help" style="margin:2px 0 0 0;"><?php esc_html_e('Archive sent PDF invoices and attachments into protected storage.', 'mail-smtp-by-oi'); ?></span>
                            </div>
                        </div>
                        <label class="oi-switch">
                            <input type="checkbox" name="save_attachments" value="1" <?php checked(!empty($settings['save_attachments'])); ?>>
                            <span class="oi-slider round"></span>
                        </label>
                    </div>
                    <div class="oi-help" style="margin-top:12px; line-height:1.5;">
                        <?php esc_html_e('Archived files can be viewed and safely downloaded directly from the Email Delivery Logs. Attachments are cleaned up automatically when corresponding logs are purged.', 'mail-smtp-by-oi'); ?>
                    </div>
                </div>
            </div>

            <!-- Step 07: WordPress Automated Email Controls (Pro Feature - Reference from WP Mail SMTP Pro) -->
            <div class="oi-step-header" style="margin-top:36px;">
                <div class="oi-step-badge">07</div>
                <div>
                    <h3 class="oi-step-title"><?php esc_html_e('WordPress Core Automated Email Controls', 'mail-smtp-by-oi'); ?></h3>
                    <p class="oi-step-desc"><?php esc_html_e('Selectively suppress annoying automated notifications to keep user and administrator inboxes clean.', 'mail-smtp-by-oi'); ?></p>
                </div>
            </div>

            <?php
            $controls = !empty($settings['email_controls']) && is_array($settings['email_controls']) ? $settings['email_controls'] : [];
            ?>
            <div class="oi-grid-2">
                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-admin-comments"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Disable Comment Moderation Alert', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Stops sending emails to admin whenever a comment is awaiting moderation.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="email_controls[dis_comment_moderation]" value="1" <?php checked(!empty($controls['dis_comment_moderation'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-format-chat"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Disable Comment Published Notification', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Stops notifying post authors when a new comment is approved/published.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="email_controls[dis_comment_published]" value="1" <?php checked(!empty($controls['dis_comment_published'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-lock"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Disable Password Changed Alert', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Stops sending confirmation emails to users after their password is changed.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="email_controls[dis_password_change_user]" value="1" <?php checked(!empty($controls['dis_password_change_user'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-admin-users"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Disable New User Admin Notification', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Stops sending an email to site admin every time a new user registers.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="email_controls[dis_new_user_admin]" value="1" <?php checked(!empty($controls['dis_new_user_admin'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-email-alt2"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Disable Admin Email Changed Alert', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Stops sending notification to the old email when site admin email changes.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="email_controls[dis_email_change]" value="1" <?php checked(!empty($controls['dis_email_change'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>

                <div class="oi-feature-card">
                    <div class="oi-feature-card-info">
                        <div class="oi-feature-icon"><span class="dashicons dashicons-update"></span></div>
                        <div>
                            <div class="oi-feature-title"><?php esc_html_e('Disable Automatic Core Update Notice', 'mail-smtp-by-oi'); ?></div>
                            <div class="oi-feature-desc"><?php esc_html_e('Stops sending emails when WordPress completes automatic background updates.', 'mail-smtp-by-oi'); ?></div>
                        </div>
                    </div>
                    <label class="oi-switch">
                        <input type="checkbox" name="email_controls[dis_auto_core_update]" value="1" <?php checked(!empty($controls['dis_auto_core_update'])); ?>>
                        <span class="oi-slider round"></span>
                    </label>
                </div>
            </div>

            <!-- Save Button Bar -->
            <div class="oi-form-actions">
                <button type="submit" class="button button-primary button-hero oi-btn" id="oi-save-btn">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save Configuration', 'mail-smtp-by-oi'); ?>
                </button>
                <span class="oi-save-status" id="oi-save-status"></span>
            </div>
        </form>
        <?php
    }

    /**
     * Email Logs Tab
     */
    private static function render_logs_tab() {
        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status   = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $page_num = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

        $logs_data = OI_Logger::get_logs([
            'search'   => $search,
            'status'   => $status,
            'page'     => $page_num,
            'per_page' => 15,
        ]);

        $items = $logs_data['items'];
        $total = $logs_data['total'];
        $pages = $logs_data['pages'];
        ?>
        <div class="oi-card">
            <div class="oi-card-header oi-flex-between">
                <div>
                    <h2><?php esc_html_e('Email Delivery Logs', 'mail-smtp-by-oi'); ?></h2>
                    <p><?php esc_html_e('Review all outgoing emails, inspect raw errors, and resend failed messages.', 'mail-smtp-by-oi'); ?></p>
                </div>
                <div class="oi-actions-group" style="display:flex; gap:8px;">
                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=oi_export_logs_csv' . (!empty($status) ? '&status=' . urlencode($status) : '') . (!empty($search) ? '&s=' . urlencode($search) : ''))); ?>" class="button oi-btn-secondary" title="<?php esc_attr_e('Export current filtered logs to CSV', 'mail-smtp-by-oi'); ?>">
                        <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'mail-smtp-by-oi'); ?>
                    </a>
                    <button type="button" class="button oi-btn-danger" id="oi-clear-logs-btn">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear All Logs', 'mail-smtp-by-oi'); ?>
                    </button>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <form method="get" class="oi-toolbar-row">
                <input type="hidden" name="page" value="mail-smtp-by-oi-logs">
                <div class="oi-filter-group">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs')); ?>" class="oi-pill-btn <?php echo empty($status) ? 'active' : ''; ?>">
                        <?php esc_html_e('All', 'mail-smtp-by-oi'); ?> (<?php echo esc_html($total); ?>)
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs&status=sent')); ?>" class="oi-pill-btn <?php echo ($status === 'sent') ? 'active' : ''; ?>">
                        <?php esc_html_e('Sent', 'mail-smtp-by-oi'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs&status=failed')); ?>" class="oi-pill-btn <?php echo ($status === 'failed') ? 'active' : ''; ?>">
                        <?php esc_html_e('Failed', 'mail-smtp-by-oi'); ?>
                    </a>
                </div>

                <div class="oi-search-group">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search recipient or subject...', 'mail-smtp-by-oi'); ?>" class="oi-input-sm">
                    <button type="submit" class="button oi-btn-sm"><?php esc_html_e('Search', 'mail-smtp-by-oi'); ?></button>
                </div>
            </form>

            <!-- Logs Table -->
            <?php if (empty($items)) : ?>
                <div class="oi-empty-state">
                    <span class="dashicons dashicons-email-alt oi-empty-icon"></span>
                    <h3><?php esc_html_e('No email logs found', 'mail-smtp-by-oi'); ?></h3>
                    <p><?php esc_html_e('Outgoing emails will automatically appear here once dispatched.', 'mail-smtp-by-oi'); ?></p>
                </div>
            <?php else : ?>
                <div class="oi-table-responsive">
                    <table class="wp-list-table widefat fixed striped oi-table">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column column-cb check-column"><input type="checkbox" id="oi-select-all"></th>
                                <th scope="col" style="width: 90px;"><?php esc_html_e('Status', 'mail-smtp-by-oi'); ?></th>
                                <th scope="col"><?php esc_html_e('To', 'mail-smtp-by-oi'); ?></th>
                                <th scope="col"><?php esc_html_e('Subject', 'mail-smtp-by-oi'); ?></th>
                                <th scope="col" style="width: 140px;"><?php esc_html_e('Tracking', 'mail-smtp-by-oi'); ?></th>
                                <th scope="col" style="width: 100px;"><?php esc_html_e('Mailer', 'mail-smtp-by-oi'); ?></th>
                                <th scope="col" style="width: 140px;"><?php esc_html_e('Date / Time', 'mail-smtp-by-oi'); ?></th>
                                <th scope="col" style="width: 160px; text-align:right;"><?php esc_html_e('Actions', 'mail-smtp-by-oi'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $row) : ?>
                                <tr id="oi-log-row-<?php echo esc_attr($row['id']); ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" class="oi-log-cb" value="<?php echo esc_attr($row['id']); ?>">
                                    </th>
                                    <td>
                                        <?php if ($row['status'] === 'sent') : ?>
                                            <span class="oi-badge oi-badge-sent"><span class="dashicons dashicons-yes" style="font-size:12px; width:12px; height:12px; line-height:1; vertical-align:middle;"></span> <?php esc_html_e('Sent', 'mail-smtp-by-oi'); ?></span>
                                        <?php else : ?>
                                            <span class="oi-badge oi-badge-failed" title="<?php echo esc_attr($row['error_message']); ?>"><span class="dashicons dashicons-warning" style="font-size:12px; width:12px; height:12px; line-height:1; vertical-align:middle;"></span> <?php esc_html_e('Failed', 'mail-smtp-by-oi'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($row['to_email']); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($row['subject']); ?>
                                        <?php if (!empty($row['attachments'])) : ?>
                                            <span class="dashicons dashicons-paperclip" title="<?php esc_attr_e('Has attachments', 'mail-smtp-by-oi'); ?>" style="font-size:14px; width:14px; height:14px; color:#4f46e5; vertical-align:middle; margin-left:6px;"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['open_count'])) : ?>
                                            <span class="oi-badge oi-badge-sent" title="<?php printf(esc_attr__('Opened %d times. Last: %s', 'mail-smtp-by-oi'), (int)$row['open_count'], esc_attr($row['last_opened_at'] ?? '')); ?>" style="cursor:help;">
                                                <span class="dashicons dashicons-visibility" style="font-size:12px; line-height:1; vertical-align:middle; margin-right:2px;"></span><?php printf(esc_html__('%d Open%s', 'mail-smtp-by-oi'), (int)$row['open_count'], (int)$row['open_count'] > 1 ? 's' : ''); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color:#94a3b8; font-size:12px;"><?php esc_html_e('Unopened', 'mail-smtp-by-oi'); ?></span>
                                        <?php endif; ?>

                                        <?php if (!empty($row['click_count'])) : ?>
                                            <span class="oi-badge" style="background:#e0e7ff; color:#3730a3; margin-left:4px; cursor:help;" title="<?php printf(esc_attr__('Clicked %d times. Last: %s', 'mail-smtp-by-oi'), (int)$row['click_count'], esc_attr($row['last_clicked_at'] ?? '')); ?>">
                                                <span class="dashicons dashicons-external" style="font-size:12px; line-height:1; vertical-align:middle; margin-right:2px;"></span><?php printf(esc_html__('%d Click%s', 'mail-smtp-by-oi'), (int)$row['click_count'], (int)$row['click_count'] > 1 ? 's' : ''); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo esc_html(strtoupper($row['mailer'])); ?></code></td>
                                    <td><span class="oi-date-text"><?php echo esc_html($row['created_at']); ?></span></td>
                                    <td style="text-align:right;">
                                        <button type="button" class="button oi-btn-sm oi-view-log-btn" data-id="<?php echo esc_attr($row['id']); ?>" title="<?php esc_attr_e('View email preview and details', 'mail-smtp-by-oi'); ?>">
                                            <?php esc_html_e('View', 'mail-smtp-by-oi'); ?>
                                        </button>
                                        <button type="button" class="button oi-btn-sm oi-resend-btn" data-id="<?php echo esc_attr($row['id']); ?>" title="<?php esc_attr_e('Resend email', 'mail-smtp-by-oi'); ?>">
                                            <?php esc_html_e('Resend', 'mail-smtp-by-oi'); ?>
                                        </button>
                                        <button type="button" class="button oi-btn-sm oi-btn-delete oi-delete-log-btn" data-id="<?php echo esc_attr($row['id']); ?>" title="<?php esc_attr_e('Delete log', 'mail-smtp-by-oi'); ?>">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bulk Actions & Pagination -->
                <div class="oi-table-footer">
                    <div class="oi-bulk-actions">
                        <button type="button" class="button oi-btn-sm" id="oi-bulk-delete-btn"><?php esc_html_e('Delete Selected', 'mail-smtp-by-oi'); ?></button>
                    </div>

                    <?php if ($pages > 1) : ?>
                        <div class="oi-pagination">
                            <?php
                            echo paginate_links([
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $pages,
                                'current'   => $page_num,
                            ]);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Send Test Email Tab
     */
    private static function render_test_tab() {
        $settings = OI_Options::get_all(false);
        $current_user = wp_get_current_user();
        $default_to = $current_user->user_email ?: get_option('admin_email');
        ?>
        <div class="oi-card">
            <div class="oi-card-header">
                <h2><?php esc_html_e('Send a Test Email', 'mail-smtp-by-oi'); ?></h2>
                <p><?php esc_html_e('Verify that your mail server, credentials, and DNS records are configured properly with real-time debug output.', 'mail-smtp-by-oi'); ?></p>
            </div>

            <form id="oi-test-email-form" method="post">
                <div class="oi-grid-2">
                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_test_to"><?php esc_html_e('Send To Email', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <input type="email" class="oi-input" id="oi_test_to" name="test_to" value="<?php echo esc_attr($default_to); ?>" required>
                        <span class="oi-help"><?php esc_html_e('Enter an inbox you have access to right now to confirm delivery.', 'mail-smtp-by-oi'); ?></span>
                    </div>

                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_test_subject"><?php esc_html_e('Email Subject', 'mail-smtp-by-oi'); ?></label>
                        <input type="text" class="oi-input" id="oi_test_subject" name="test_subject" value="<?php esc_attr_e('Mail SMTP by Oi - Test Verification', 'mail-smtp-by-oi'); ?>">
                    </div>
                </div>

                <div class="oi-form-group">
                    <label class="oi-label" for="oi_test_message"><?php esc_html_e('Custom Message (Optional HTML)', 'mail-smtp-by-oi'); ?></label>
                    <textarea class="oi-textarea" id="oi_test_message" name="test_message" rows="4"><?php esc_html_e('Congratulations! Your WordPress website is now successfully sending emails via Mail SMTP by Oi.', 'mail-smtp-by-oi'); ?></textarea>
                </div>

                <div class="oi-form-actions">
                    <button type="submit" class="button button-primary button-hero oi-btn" id="oi-send-test-btn">
                        <span class="dashicons dashicons-email"></span> <?php esc_html_e('Send Test Email', 'mail-smtp-by-oi'); ?>
                    </button>
                    <span class="oi-test-spinner" id="oi-test-spinner" style="display:none;">
                        <span class="spinner is-active" style="float:none; margin:0 8px;"></span> <?php esc_html_e('Sending email...', 'mail-smtp-by-oi'); ?>
                    </span>
                </div>
            </form>

            <!-- Test Result Banner & Terminal Debug Box -->
            <div id="oi-test-result" style="display:none; margin-top:24px;">
                <div id="oi-test-alert" class="oi-alert"></div>

                <div class="oi-terminal-card">
                    <div class="oi-terminal-header">
                        <span class="oi-terminal-dot oi-dot-red"></span>
                        <span class="oi-terminal-dot oi-dot-yellow"></span>
                        <span class="oi-terminal-dot oi-dot-green"></span>
                        <span class="oi-terminal-title"><?php esc_html_e('SMTP Handshake & Live Debug Log', 'mail-smtp-by-oi'); ?></span>
                        <button type="button" class="button oi-btn-xs" id="oi-copy-debug-btn"><?php esc_html_e('Copy Log', 'mail-smtp-by-oi'); ?></button>
                    </div>
                    <pre class="oi-terminal-body" id="oi-terminal-body"></pre>
                </div>
            </div>

            <?php
            $from_domain_dns = OI_Diagnostics::check_dns();
            ?>
            <div class="oi-card" style="margin-top:24px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-shield" style="font-size:22px; width:22px; height:22px; color:#4f46e5;"></span>
                        <div>
                            <strong style="font-size:15px; color:#0f172a; display:block;"><?php esc_html_e('Domain Deliverability & Spam Protection Check', 'mail-smtp-by-oi'); ?></strong>
                            <span class="oi-help" style="margin:0;"><?php printf(esc_html__('Checking DNS records for: %s', 'mail-smtp-by-oi'), '<strong>' . esc_html($from_domain_dns['domain']) . '</strong>'); ?></span>
                        </div>
                    </div>
                    <div>
                        <?php if ($from_domain_dns['spf']['found'] && $from_domain_dns['dmarc']['found']) : ?>
                            <span class="oi-badge oi-badge-sent"><span class="dashicons dashicons-yes" style="font-size:12px; vertical-align:middle;"></span> <?php esc_html_e('Deliverability Health: Excellent', 'mail-smtp-by-oi'); ?></span>
                        <?php else : ?>
                            <span class="oi-badge oi-badge-failed"><span class="dashicons dashicons-warning" style="font-size:12px; vertical-align:middle;"></span> <?php esc_html_e('Action Recommended', 'mail-smtp-by-oi'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="oi-grid-2" style="margin-top:12px;">
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong><?php esc_html_e('SPF Record', 'mail-smtp-by-oi'); ?></strong>
                            <span class="oi-badge <?php echo $from_domain_dns['spf']['found'] ? 'oi-badge-sent' : 'oi-badge-failed'; ?>">
                                <?php echo $from_domain_dns['spf']['found'] ? '✔ PASS' : '✖ MISSING'; ?>
                            </span>
                        </div>
                        <p style="font-size:12px; color:#64748b; margin:0;"><?php echo esc_html($from_domain_dns['spf']['tip']); ?></p>
                        <?php if ($from_domain_dns['spf']['found']) : ?>
                            <code style="font-size:11px; display:block; margin-top:6px; word-break:break-all;"><?php echo esc_html($from_domain_dns['spf']['record']); ?></code>
                        <?php endif; ?>
                    </div>

                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong><?php esc_html_e('DMARC Record', 'mail-smtp-by-oi'); ?></strong>
                            <span class="oi-badge <?php echo $from_domain_dns['dmarc']['found'] ? 'oi-badge-sent' : 'oi-badge-failed'; ?>">
                                <?php echo $from_domain_dns['dmarc']['found'] ? '✔ PASS' : '✖ MISSING'; ?>
                            </span>
                        </div>
                        <p style="font-size:12px; color:#64748b; margin:0;"><?php echo esc_html($from_domain_dns['dmarc']['tip']); ?></p>
                        <?php if ($from_domain_dns['dmarc']['found']) : ?>
                            <code style="font-size:11px; display:block; margin-top:6px; word-break:break-all;"><?php echo esc_html($from_domain_dns['dmarc']['record']); ?></code>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Diagnostics & Ports Tab
     */
    private static function render_diagnostics_tab() {
        $settings = OI_Options::get_all(false);
        $host     = !empty($settings['smtp_host']) ? $settings['smtp_host'] : 'smtp.gmail.com';
        $env      = OI_Diagnostics::get_environment_info();
        $dns      = OI_Diagnostics::check_dns();
        ?>
        <div class="oi-card">
            <div class="oi-card-header">
                <h2><?php esc_html_e('Server Diagnostics & Connectivity', 'mail-smtp-by-oi'); ?></h2>
                <p><?php esc_html_e('Check whether your web hosting firewall permits outbound SMTP traffic, and verify domain authentication (SPF & DMARC).', 'mail-smtp-by-oi'); ?></p>
            </div>

            <!-- Port Scanner Box -->
            <div class="oi-section-title"><?php esc_html_e('Live Outbound Port Connectivity Test', 'mail-smtp-by-oi'); ?></div>
            <p class="oi-help"><?php esc_html_e('Many hosting providers block port 25, 465, or 587. Click "Run Port Test" to test connectivity to your SMTP host in real time.', 'mail-smtp-by-oi'); ?></p>

            <div class="oi-port-tester-bar">
                <input type="text" class="oi-input" id="oi_diag_host" value="<?php echo esc_attr($host); ?>" placeholder="smtp.gmail.com" style="max-width:300px;">
                <button type="button" class="button button-primary oi-btn" id="oi-run-port-test-btn">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Run Port Test', 'mail-smtp-by-oi'); ?>
                </button>
            </div>

            <div id="oi-port-results" class="oi-port-results-grid" style="margin-top:16px;">
                <!-- Filled via AJAX on page load or on click -->
                <div class="oi-port-card" data-port="587">
                    <div class="oi-port-number">Port 587</div>
                    <div class="oi-port-name">TLS / STARTTLS</div>
                    <div class="oi-port-badge oi-badge-idle"><?php esc_html_e('Ready to test', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div class="oi-port-card" data-port="465">
                    <div class="oi-port-number">Port 465</div>
                    <div class="oi-port-name">SSL</div>
                    <div class="oi-port-badge oi-badge-idle"><?php esc_html_e('Ready to test', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div class="oi-port-card" data-port="2525">
                    <div class="oi-port-number">Port 2525</div>
                    <div class="oi-port-name">Alternative Port</div>
                    <div class="oi-port-badge oi-badge-idle"><?php esc_html_e('Ready to test', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div class="oi-port-card" data-port="25">
                    <div class="oi-port-number">Port 25</div>
                    <div class="oi-port-name">Unencrypted</div>
                    <div class="oi-port-badge oi-badge-idle"><?php esc_html_e('Ready to test', 'mail-smtp-by-oi'); ?></div>
                </div>
            </div>

            <!-- DNS Records Checker -->
            <div class="oi-section-title" style="margin-top:32px;"><?php esc_html_e('Domain Email Authentication (SPF & DMARC)', 'mail-smtp-by-oi'); ?></div>
            <div class="oi-dns-card">
                <div class="oi-dns-row">
                    <div class="oi-dns-badge <?php echo $dns['spf']['found'] ? 'oi-dns-pass' : 'oi-dns-warn'; ?>">
                        SPF
                    </div>
                    <div class="oi-dns-info">
                        <strong><?php esc_html_e('SPF Record for', 'mail-smtp-by-oi'); ?> <?php echo esc_html($dns['domain']); ?>:</strong>
                        <div class="oi-dns-val"><?php echo $dns['spf']['found'] ? esc_html($dns['spf']['record']) : esc_html__('No record found.', 'mail-smtp-by-oi'); ?></div>
                        <p class="oi-help"><?php echo esc_html($dns['spf']['tip']); ?></p>
                    </div>
                </div>

                <div class="oi-dns-row" style="margin-top:16px;">
                    <div class="oi-dns-badge <?php echo $dns['dmarc']['found'] ? 'oi-dns-pass' : 'oi-dns-warn'; ?>">
                        DMARC
                    </div>
                    <div class="oi-dns-info">
                        <strong><?php esc_html_e('DMARC Record for', 'mail-smtp-by-oi'); ?> <?php echo esc_html($dns['domain']); ?>:</strong>
                        <div class="oi-dns-val"><?php echo $dns['dmarc']['found'] ? esc_html($dns['dmarc']['record']) : esc_html__('No record found.', 'mail-smtp-by-oi'); ?></div>
                        <p class="oi-help"><?php echo esc_html($dns['dmarc']['tip']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Server Environment -->
            <div class="oi-section-title" style="margin-top:32px;"><?php esc_html_e('Server & PHP Environment', 'mail-smtp-by-oi'); ?></div>
            <table class="widefat striped oi-env-table">
                <tbody>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo esc_html($env['php_version']); ?> <?php echo $env['php_compatible'] ? '&#9989; (Compatible)' : '&#10060; (Requires PHP 7.4+)'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress Version</strong></td>
                        <td><?php echo esc_html($env['wp_version']); ?> &#9989;</td>
                    </tr>
                    <tr>
                        <td><strong>OpenSSL Extension</strong></td>
                        <td><?php echo $env['openssl_installed'] ? '&#9989; Installed (Required for SSL/TLS & AES encryption)' : '&#10060; Missing'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>cURL Extension</strong></td>
                        <td><?php echo $env['curl_installed'] ? '&#9989; Installed (Required for API Mailers)' : '&#10060; Missing'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Socket Functions (fsockopen)</strong></td>
                        <td><?php echo $env['sockets_enabled'] ? '&#9989; Enabled' : '&#10060; Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Memory Limit / Max Execution</strong></td>
                        <td><?php echo esc_html($env['memory_limit']); ?> / <?php echo esc_html($env['max_execution_time']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Tools & About Tab
     */
    private static function render_tools_tab() {
        $settings = OI_Options::get_all(false);
        ?>
        <div class="oi-card">
            <div class="oi-card-header">
                <h2><?php esc_html_e('Tools & Plugin Information', 'mail-smtp-by-oi'); ?></h2>
                <p><?php esc_html_e('Data management, uninstall cleanup settings, and Oi Technologies information.', 'mail-smtp-by-oi'); ?></p>
            </div>

            <!-- Migration / Importer -->
            <div class="oi-section-title"><?php esc_html_e('1-Click Import from Other SMTP Plugins', 'mail-smtp-by-oi'); ?></div>
            <?php
            $detected = OI_Migration::detect_plugins();
            if (!empty($detected)) :
            ?>
                <p><?php esc_html_e('We found existing settings from other SMTP plugins on your WordPress site. You can import their configuration into Mail SMTP by Oi with a single click:', 'mail-smtp-by-oi'); ?></p>
                <div class="oi-migration-grid">
                    <?php foreach ($detected as $key => $info) : ?>
                        <div class="oi-migration-card">
                            <div class="oi-migration-info">
                                <div class="oi-migration-title"><?php echo esc_html($info['name']); ?></div>
                                <div class="oi-migration-desc"><?php echo esc_html($info['description']); ?> (Detected Mailer: <code><?php echo esc_html($info['mailer']); ?></code>)</div>
                            </div>
                            <button type="button" class="button button-primary oi-btn-import" data-plugin="<?php echo esc_attr($key); ?>">
                                <span class="dashicons dashicons-upload"></span> <?php printf(esc_html__('Import from %s', 'mail-smtp-by-oi'), esc_html($info['name'])); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="oi-help"><?php esc_html_e('No active configurations from WP Mail SMTP, FluentSMTP, or Easy WP SMTP were detected.', 'mail-smtp-by-oi'); ?></p>
            <?php endif; ?>

            <div class="oi-section-title" style="margin-top:32px;"><?php esc_html_e('Data Cleanup on Uninstall', 'mail-smtp-by-oi'); ?></div>
            <div class="oi-form-group">
                <label class="oi-checkbox-label">
                    <input type="checkbox" name="delete_on_uninstall" id="oi_delete_on_uninstall" value="1" <?php checked(!empty($settings['delete_on_uninstall'])); ?>>
                    <?php esc_html_e('Completely delete all Mail SMTP by Oi settings and email logs when the plugin is uninstalled.', 'mail-smtp-by-oi'); ?>
                </label>
                <span class="oi-help"><?php esc_html_e('Enable this if you wish to leave no trace in the database if you remove this plugin.', 'mail-smtp-by-oi'); ?></span>
            </div>

            <div class="oi-section-title" style="margin-top:32px;"><?php esc_html_e('About Oi Technologies', 'mail-smtp-by-oi'); ?></div>
            <div class="oi-about-box">
                <p><strong>Mail SMTP by Oi</strong> <?php esc_html_e('is proudly developed and maintained by', 'mail-smtp-by-oi'); ?> <a href="https://oitech.com.bd/" target="_blank" rel="noopener"><strong>Oi Technologies</strong></a>.</p>
                <p><?php esc_html_e('Our goal is to build world-class, ultra-clean, high-performance WordPress solutions for businesses, developers, and creators.', 'mail-smtp-by-oi'); ?></p>
                <div class="oi-about-links">
                    <a href="https://oitech.com.bd/open-source/" target="_blank" class="button button-secondary"><?php esc_html_e('Explore More Plugins', 'mail-smtp-by-oi'); ?></a>
                    <a href="https://oitech.com.bd/" target="_blank" class="button button-secondary"><?php esc_html_e('Visit Oi Technologies', 'mail-smtp-by-oi'); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save Settings
     */
    public static function ajax_save_settings() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $form_data = [];
        if (isset($_POST['data'])) {
            parse_str(wp_unslash($_POST['data']), $form_data);
        }

        $saved = OI_Options::save($form_data);

        if ($saved !== false) {
            wp_send_json_success(['message' => __('Settings saved successfully!', 'mail-smtp-by-oi')]);
        }

        wp_send_json_error(['message' => __('Failed to save settings. Please try again.', 'mail-smtp-by-oi')]);
    }

    /**
     * AJAX: Send Test Email
     */
    public static function ajax_send_test_email() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $to      = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : __('Mail SMTP by Oi - Test', 'mail-smtp-by-oi');
        $message = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

        if (empty($to) || !is_email($to)) {
            wp_send_json_error(['message' => __('Please provide a valid recipient email address.', 'mail-smtp-by-oi')]);
        }

        if (empty($message)) {
            $message = '<p>' . __('This is a test email sent from WordPress using <strong>Mail SMTP by Oi</strong>.', 'mail-smtp-by-oi') . '</p>';
        }

        $result = OI_Mailer::send_test($to, $subject, $message);

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'debug'   => $result['debug'],
                'time_ms' => $result['elapsed_ms'] ?? 0,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'debug'   => $result['debug'],
                'time_ms' => $result['elapsed_ms'] ?? 0,
            ]);
        }
    }

    /**
     * AJAX: Test Socket Port
     */
    public static function ajax_test_port() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $host = isset($_POST['host']) ? sanitize_text_field(wp_unslash($_POST['host'])) : '';
        $port = isset($_POST['port']) ? absint($_POST['port']) : 587;

        $res = OI_Diagnostics::test_port($host, $port);
        wp_send_json_success($res);
    }

    /**
     * AJAX: Get Log Details for Modal
     */
    public static function ajax_get_log_details() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $log = OI_Logger::get_log($id);

        if (!$log) {
            wp_send_json_error(['message' => __('Log not found.', 'mail-smtp-by-oi')]);
        }

        $vault_attachments = OI_Attachments::get_log_attachments($id);

        wp_send_json_success([
            'log' => [
                'id'                => $log['id'],
                'to'                => esc_html($log['to_email']),
                'raw_to'            => $log['to_email'],
                'subject'           => esc_html($log['subject']),
                'message'           => $log['message'],
                'headers'           => esc_html($log['headers']),
                'attachments'       => esc_html($log['attachments']),
                'vault_attachments' => $vault_attachments,
                'mailer'            => esc_html(strtoupper($log['mailer'])),
                'status'            => esc_html($log['status']),
                'error_message'     => esc_html($log['error_message'] ?? ''),
                'debug_log'         => esc_html($log['debug_log'] ?? ''),
                'open_count'        => (int)($log['open_count'] ?? 0),
                'click_count'       => (int)($log['click_count'] ?? 0),
                'last_opened_at'    => !empty($log['last_opened_at']) ? esc_html($log['last_opened_at']) : '',
                'last_clicked_at'   => !empty($log['last_clicked_at']) ? esc_html($log['last_clicked_at']) : '',
                'date'              => esc_html($log['created_at']),
            ],
        ]);
    }

    /**
     * AJAX: Resend Email
     */
    public static function ajax_resend_email() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $override_to = !empty($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
        $res = OI_Logger::resend($id, $override_to);

        if ($res['success']) {
            wp_send_json_success($res);
        }

        wp_send_json_error($res);
    }

    /**
     * AJAX: Delete Single Log
     */
    public static function ajax_delete_log() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $deleted = OI_Logger::delete($id);

        if ($deleted) {
            wp_send_json_success(['message' => __('Log entry deleted.', 'mail-smtp-by-oi')]);
        }

        wp_send_json_error(['message' => __('Failed to delete log entry.', 'mail-smtp-by-oi')]);
    }

    /**
     * AJAX: Delete Bulk Logs
     */
    public static function ajax_delete_bulk_logs() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : [];
        $deleted = OI_Logger::delete_bulk($ids);

        wp_send_json_success(['count' => $deleted, 'message' => sprintf(__('%d log entries deleted.', 'mail-smtp-by-oi'), $deleted)]);
    }

    /**
     * AJAX: Clear All Logs
     */
    public static function ajax_clear_all_logs() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        OI_Logger::clear_all();
        wp_send_json_success(['message' => __('All email logs cleared.', 'mail-smtp-by-oi')]);
    }

    /**
     * AJAX: Check DNS
     */
    public static function ajax_check_dns() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        $res = OI_Diagnostics::check_dns($domain);
        wp_send_json_success($res);
    }

    /**
     * Display Email Failure Notice in Admin
     */
    public static function display_failure_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $alert = get_transient('oi_email_failure_alert');
        if (!$alert) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'mail-smtp-by-oi-logs') !== false) {
            delete_transient('oi_email_failure_alert');
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible oi-failure-notice" data-nonce="<?php echo esc_attr(wp_create_nonce('oi_mail_smtp_nonce')); ?>">
            <p>
                <strong><?php esc_html_e('Mail SMTP by Oi Alert:', 'mail-smtp-by-oi'); ?></strong>
                <?php printf(esc_html__('%d email(s) recently failed to send from your website! Latest error: %s', 'mail-smtp-by-oi'), esc_html($alert['count']), '<code>' . esc_html($alert['error']) . '</code>'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs&status=failed')); ?>" class="button button-small" style="margin-left:8px; font-weight:600;"><?php esc_html_e('Inspect Failure Logs', 'mail-smtp-by-oi'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle CSV Export Action
     */
    public static function handle_export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'mail-smtp-by-oi'));
        }

        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        OI_Logger::export_csv([
            'status' => $status,
            'search' => $search,
        ]);
    }

    /**
     * AJAX: Dismiss Failure Alert Notice
     */
    public static function ajax_dismiss_failure_alert() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');
        delete_transient('oi_email_failure_alert');
        wp_send_json_success();
    }

    /**
     * AJAX: 1-Click Import Settings from Other Plugins
     */
    public static function ajax_import_settings() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $plugin_key = isset($_POST['plugin']) ? sanitize_key($_POST['plugin']) : '';
        $result = OI_Migration::import_from($plugin_key);

        if ($result['success']) {
            wp_send_json_success($result);
        }

        wp_send_json_error($result);
    }

    /**
     * AJAX: Test Failure Alert Notification (Telegram, Slack, Discord, Email)
     */
    public static function ajax_test_alert_notification() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $channel   = isset($_POST['channel']) ? sanitize_key($_POST['channel']) : 'email';
        $settings  = OI_Options::get_all(true);
        $site_name = get_option('blogname', 'WordPress Site');
        $test_msg  = sprintf(__('This is a live test notification from %s. Your alert channel is configured properly!', 'mail-smtp-by-oi'), $site_name);

        $result = OI_Mailer::dispatch_alert_to_channel($channel, $test_msg, $site_name, $settings, true);

        if ($result['success']) {
            wp_send_json_success($result);
        }

        wp_send_json_error($result);
    }

    /**
     * AJAX: Send Test Weekly Digest Email
     */
    public static function ajax_send_test_digest() {
        check_ajax_referer('oi_mail_smtp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'mail-smtp-by-oi')]);
        }

        $recipient = isset($_POST['recipient']) ? sanitize_email(wp_unslash($_POST['recipient'])) : '';
        $result = OI_Digest::send_digest(true, $recipient);

        if ($result['success']) {
            wp_send_json_success($result);
        }

        wp_send_json_error($result);
    }

    /**
     * Register WordPress Admin Dashboard Widget
     */
    public static function register_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'oi_mail_smtp_dashboard_widget',
            __('Mail SMTP by Oi - Delivery Status', 'mail-smtp-by-oi'),
            [__CLASS__, 'render_dashboard_widget']
        );
    }

    /**
     * Render Dashboard Widget HTML
     */
    public static function render_dashboard_widget() {
        $stats = OI_Logger::get_stats();
        $today = OI_Logger::get_today_stats();
        $settings = OI_Options::get_all(false);
        $mailer = $settings['mailer'] ?? 'smtp';
        $mailer_names = [
            'smtp'     => 'Custom SMTP',
            'gmail'    => 'Gmail / Workspace',
            'outlook'  => 'Microsoft 365',
            'ses'      => 'Amazon SES',
            'sendgrid' => 'SendGrid',
            'mailgun'  => 'Mailgun',
            'brevo'    => 'Brevo',
            'postmark' => 'Postmark',
            'resend'   => 'Resend',
            'default'  => 'Default PHP Mail',
        ];
        $mailer_label = $mailer_names[$mailer] ?? strtoupper($mailer);
        $status_color = empty($settings['enabled']) ? '#94a3b8' : '#10b981';
        $status_text  = empty($settings['enabled']) ? __('Disabled', 'mail-smtp-by-oi') : __('Active', 'mail-smtp-by-oi');
        ?>
        <div class="oi-dashboard-widget-content" style="padding:4px 0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?php echo esc_attr($status_color); ?>;"></span>
                    <strong><?php echo esc_html($mailer_label); ?></strong>
                    <span style="font-size:12px; color:#64748b; background:#f1f5f9; padding:2px 8px; border-radius:12px; font-weight:600;"><?php echo esc_html($status_text); ?></span>
                </div>
                <div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-test')); ?>" class="button button-small" style="font-size:11px;"><?php esc_html_e('Send Test', 'mail-smtp-by-oi'); ?></a>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; text-align:center; margin-bottom:14px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 4px;">
                    <div style="font-size:18px; font-weight:700; color:#10b981;"><?php echo esc_html(number_format_i18n($stats['sent'])); ?></div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px; font-weight:600; text-transform:uppercase;"><?php esc_html_e('Sent', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 4px;">
                    <div style="font-size:18px; font-weight:700; color:<?php echo $stats['failed'] > 0 ? '#ef4444' : '#64748b'; ?>;"><?php echo esc_html(number_format_i18n($stats['failed'])); ?></div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px; font-weight:600; text-transform:uppercase;"><?php esc_html_e('Failed', 'mail-smtp-by-oi'); ?></div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 4px;">
                    <div style="font-size:18px; font-weight:700; color:#4f46e5;"><?php echo esc_html($stats['rate']); ?>%</div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px; font-weight:600; text-transform:uppercase;"><?php esc_html_e('Success Rate', 'mail-smtp-by-oi'); ?></div>
                </div>
            </div>

            <div style="font-size:12px; color:#64748b; margin-bottom:12px; display:flex; justify-content:space-between;">
                <span><?php printf(esc_html__('Today: %d sent, %d failed', 'mail-smtp-by-oi'), $today['sent'], $today['failed']); ?></span>
                <span style="font-weight:600; color:#0f172a;"><?php printf(esc_html__('Total: %d emails', 'mail-smtp-by-oi'), $stats['total']); ?></span>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs')); ?>" style="font-size:12px; text-decoration:none; font-weight:600; color:#4f46e5;">
                    <span class="dashicons dashicons-list-view" style="font-size:14px; vertical-align:middle; margin-right:2px;"></span>
                    <?php esc_html_e('View Detailed Logs →', 'mail-smtp-by-oi'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mail-smtp-by-oi')); ?>" style="font-size:12px; text-decoration:none; color:#64748b;">
                    <?php esc_html_e('Settings', 'mail-smtp-by-oi'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Register Admin Bar Quick Status Node (Reference from WP Mail SMTP & Post-SMTP)
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public static function register_admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options') || !is_admin_bar_showing()) {
            return;
        }

        $stats = OI_Logger::get_stats();
        $has_failed = $stats['failed'] > 0;
        $title_badge = $has_failed
            ? sprintf(' <span style="background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px; margin-left:4px;">%d Failed</span>', $stats['failed'])
            : ' <span style="background:#10b981; color:#fff; font-size:9px; font-weight:700; padding:1px 5px; border-radius:10px; margin-left:4px;">Active</span>';

        $wp_admin_bar->add_node([
            'id'    => 'oi-mail-smtp-bar',
            'title' => '<span class="ab-icon dashicons dashicons-email" style="top:2px;"></span> Mail SMTP' . $title_badge,
            'href'  => admin_url('admin.php?page=mail-smtp-by-oi'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'oi-mail-smtp-bar-logs',
            'parent' => 'oi-mail-smtp-bar',
            'title'  => __('Email Delivery Logs', 'mail-smtp-by-oi') . ($has_failed ? ' (' . $stats['failed'] . ' ' . __('Failed', 'mail-smtp-by-oi') . ')' : ''),
            'href'   => admin_url('admin.php?page=mail-smtp-by-oi-logs'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'oi-mail-smtp-bar-test',
            'parent' => 'oi-mail-smtp-bar',
            'title'  => __('Send Test Email', 'mail-smtp-by-oi'),
            'href'   => admin_url('admin.php?page=mail-smtp-by-oi-test'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'oi-mail-smtp-bar-settings',
            'parent' => 'oi-mail-smtp-bar',
            'title'  => __('Settings', 'mail-smtp-by-oi'),
            'href'   => admin_url('admin.php?page=mail-smtp-by-oi'),
        ]);
    }
}
