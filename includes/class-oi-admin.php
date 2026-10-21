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

        <!-- Email Details Modal -->
        <div id="oi-email-modal" class="oi-modal-backdrop" style="display:none;">
            <div class="oi-modal-container">
                <div class="oi-modal-header">
                    <h3 class="oi-modal-title" id="oi-modal-subject"><?php esc_html_e('Email Details', 'mail-smtp-by-oi'); ?></h3>
                    <button type="button" class="oi-modal-close" id="oi-modal-close-btn">&times;</button>
                </div>
                <div class="oi-modal-body" id="oi-modal-content">
                    <div class="oi-modal-loading"><?php esc_html_e('Loading details...', 'mail-smtp-by-oi'); ?></div>
                </div>
            </div>
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

            <!-- Sender Details -->
            <div class="oi-section-title"><?php esc_html_e('Sender Details', 'mail-smtp-by-oi'); ?></div>

            <div class="oi-grid-2">
                <div class="oi-form-group">
                    <label class="oi-label" for="oi_from_email"><?php esc_html_e('From Email', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                    <input type="email" class="oi-input" name="from_email" id="oi_from_email" value="<?php echo esc_attr($settings['from_email']); ?>" required>
                    <span class="oi-help"><?php esc_html_e('The email address that emails will be sent from (e.g. no-reply@yourdomain.com).', 'mail-smtp-by-oi'); ?></span>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="force_from_email" value="1" <?php checked(!empty($settings['force_from_email'])); ?>>
                        <?php esc_html_e('Force From Email (Recommended: prevents other plugins from overriding with invalid addresses)', 'mail-smtp-by-oi'); ?>
                    </label>
                </div>

                <div class="oi-form-group">
                    <label class="oi-label" for="oi_from_name"><?php esc_html_e('From Name', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                    <input type="text" class="oi-input" name="from_name" id="oi_from_name" value="<?php echo esc_attr($settings['from_name']); ?>" required>
                    <span class="oi-help"><?php esc_html_e('The name that will appear as the sender (e.g. your company or site name).', 'mail-smtp-by-oi'); ?></span>
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="force_from_name" value="1" <?php checked(!empty($settings['force_from_name'])); ?>>
                        <?php esc_html_e('Force From Name across all emails', 'mail-smtp-by-oi'); ?>
                    </label>
                </div>
            </div>

            <div class="oi-form-group">
                <label class="oi-checkbox-label">
                    <input type="checkbox" name="set_return_path" value="1" <?php checked(!empty($settings['set_return_path'])); ?>>
                    <?php esc_html_e('Set Return-Path (Delivery bounce notifications will be sent to the From Email)', 'mail-smtp-by-oi'); ?>
                </label>
            </div>

            <!-- Mailer Selection Cards -->
            <div class="oi-section-title"><?php esc_html_e('Choose Mail Service Provider', 'mail-smtp-by-oi'); ?></div>
            <div class="oi-mailers-grid">
                <?php
                $mailers = [
                    'smtp'     => ['name' => 'Custom SMTP', 'desc' => 'Any standard SMTP server (cPanel, Postfix, Zimbra, etc.)', 'icon' => 'dashicons-email-alt'],
                    'gmail'    => ['name' => 'Google / Gmail', 'desc' => 'Send via Gmail / Google Workspace SMTP', 'icon' => 'dashicons-google'],
                    'outlook'  => ['name' => 'Outlook / Office 365', 'desc' => 'Send via Microsoft 365 / Outlook SMTP', 'icon' => 'dashicons-windows'],
                    'sendgrid' => ['name' => 'SendGrid', 'desc' => 'High-volume reliable cloud API mailer', 'icon' => 'dashicons-cloud'],
                    'mailgun'  => ['name' => 'Mailgun', 'desc' => 'Powerful developer API with US / EU regions', 'icon' => 'dashicons-rest-api'],
                    'brevo'    => ['name' => 'Brevo (Sendinblue)', 'desc' => '300 free emails/day via REST API', 'icon' => 'dashicons-paperclip'],
                    'postmark' => ['name' => 'Postmark', 'desc' => 'Lightning fast transactional email delivery', 'icon' => 'dashicons-performance'],
                    'resend'   => ['name' => 'Resend', 'desc' => 'Modern developer-first email platform', 'icon' => 'dashicons-controls-play'],
                    'default'  => ['name' => 'Default (PHP Mail)', 'desc' => 'Native web server mail without SMTP (Not recommended)', 'icon' => 'dashicons-admin-site'],
                ];

                foreach ($mailers as $key => $info) :
                    $checked = ($settings['mailer'] === $key);
                ?>
                    <label class="oi-mailer-card <?php echo $checked ? 'oi-mailer-selected' : ''; ?>">
                        <input type="radio" name="mailer" value="<?php echo esc_attr($key); ?>" <?php checked($checked); ?> class="oi-mailer-radio">
                        <div class="oi-mailer-icon">
                            <span class="dashicons <?php echo esc_attr($info['icon']); ?>"></span>
                        </div>
                        <div class="oi-mailer-content">
                            <div class="oi-mailer-name"><?php echo esc_html($info['name']); ?></div>
                            <div class="oi-mailer-desc"><?php echo esc_html($info['desc']); ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Custom SMTP & Presets Section -->
            <div id="oi-smtp-fields" class="oi-conditional-section" style="<?php echo in_array($settings['mailer'], ['smtp', 'gmail', 'outlook'], true) ? '' : 'display:none;'; ?>">
                <div class="oi-section-title"><?php esc_html_e('SMTP Server Settings', 'mail-smtp-by-oi'); ?></div>

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
                        <label class="oi-label" for="oi_smtp_pass"><?php esc_html_e('SMTP Password / App Password', 'mail-smtp-by-oi'); ?></label>
                        <input type="password" class="oi-input" name="smtp_pass" id="oi_smtp_pass" value="<?php echo !empty($settings['smtp_pass']) ? '********' : ''; ?>" placeholder="<?php esc_attr_e('Leave blank to keep existing password', 'mail-smtp-by-oi'); ?>" autocomplete="new-password">
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
                <div class="oi-section-title"><?php esc_html_e('API Credentials', 'mail-smtp-by-oi'); ?></div>

                <div class="oi-form-group">
                    <label class="oi-label" for="oi_api_key"><?php esc_html_e('API Key / Server Token', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                    <input type="password" class="oi-input" name="api_key" id="oi_api_key" value="<?php echo !empty($settings['api_key']) ? '********' : ''; ?>" placeholder="<?php esc_attr_e('Paste your API Key here', 'mail-smtp-by-oi'); ?>" autocomplete="new-password">
                    <span class="oi-help"><?php esc_html_e('Stored with AES-256-CBC encryption. Leave blank to keep existing key.', 'mail-smtp-by-oi'); ?></span>
                </div>
            </div>

            <!-- Mailgun Specific Fields -->
            <div id="oi-mailgun-fields" class="oi-conditional-section" style="<?php echo ($settings['mailer'] === 'mailgun') ? '' : 'display:none;'; ?>">
                <div class="oi-section-title"><?php esc_html_e('Mailgun API Settings', 'mail-smtp-by-oi'); ?></div>

                <div class="oi-grid-3">
                    <div class="oi-form-group">
                        <label class="oi-label" for="oi_mailgun_api_key"><?php esc_html_e('Mailgun Private API Key', 'mail-smtp-by-oi'); ?> <span class="oi-required">*</span></label>
                        <input type="password" class="oi-input" name="mailgun_api_key" id="oi_mailgun_api_key" value="<?php echo !empty($settings['mailgun_api_key']) ? '********' : ''; ?>" placeholder="key-xxxx" autocomplete="new-password">
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

            <!-- Logging & Maintenance -->
            <div class="oi-section-title"><?php esc_html_e('Email Logging & Auto-Cleanup', 'mail-smtp-by-oi'); ?></div>

            <div class="oi-grid-3">
                <div class="oi-form-group">
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="enable_logging" value="1" <?php checked(!empty($settings['enable_logging'])); ?>>
                        <?php esc_html_e('Enable Email Logging', 'mail-smtp-by-oi'); ?>
                    </label>
                    <span class="oi-help"><?php esc_html_e('Tracks sent and failed emails with full debug details', 'mail-smtp-by-oi'); ?></span>
                </div>

                <div class="oi-form-group">
                    <label class="oi-checkbox-label">
                        <input type="checkbox" name="log_body" value="1" <?php checked(!empty($settings['log_body'])); ?>>
                        <?php esc_html_e('Log Email Content / Body', 'mail-smtp-by-oi'); ?>
                    </label>
                    <span class="oi-help"><?php esc_html_e('Disable if you do not want to store message content (GDPR privacy)', 'mail-smtp-by-oi'); ?></span>
                </div>

                <div class="oi-form-group">
                    <label class="oi-label" for="oi_log_retention_days"><?php esc_html_e('Log Retention Period', 'mail-smtp-by-oi'); ?></label>
                    <select class="oi-select" name="log_retention_days" id="oi_log_retention_days">
                        <option value="7" <?php selected($settings['log_retention_days'], 7); ?>><?php esc_html_e('7 Days', 'mail-smtp-by-oi'); ?></option>
                        <option value="14" <?php selected($settings['log_retention_days'], 14); ?>><?php esc_html_e('14 Days', 'mail-smtp-by-oi'); ?></option>
                        <option value="30" <?php selected($settings['log_retention_days'], 30); ?>><?php esc_html_e('30 Days (Recommended)', 'mail-smtp-by-oi'); ?></option>
                        <option value="60" <?php selected($settings['log_retention_days'], 60); ?>><?php esc_html_e('60 Days', 'mail-smtp-by-oi'); ?></option>
                        <option value="90" <?php selected($settings['log_retention_days'], 90); ?>><?php esc_html_e('90 Days', 'mail-smtp-by-oi'); ?></option>
                        <option value="0" <?php selected($settings['log_retention_days'], 0); ?>><?php esc_html_e('Keep Forever (No auto delete)', 'mail-smtp-by-oi'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Fallback Mechanism -->
            <div class="oi-section-title"><?php esc_html_e('Failover & Fallback Connection', 'mail-smtp-by-oi'); ?></div>
            <div class="oi-form-group">
                <label class="oi-checkbox-label">
                    <input type="checkbox" name="enable_fallback" value="1" <?php checked(!empty($settings['enable_fallback'])); ?>>
                    <?php esc_html_e('Enable automatic fallback to default PHP mail() if primary mailer fails', 'mail-smtp-by-oi'); ?>
                </label>
            </div>

            <!-- Save Button Bar -->
            <div class="oi-form-actions">
                <button type="submit" class="button button-primary button-hero oi-btn" id="oi-save-btn">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save Settings', 'mail-smtp-by-oi'); ?>
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
                <div class="oi-actions-group">
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
                <table class="wp-list-table widefat fixed striped oi-table">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-cb check-column"><input type="checkbox" id="oi-select-all"></th>
                            <th scope="col" style="width: 100px;"><?php esc_html_e('Status', 'mail-smtp-by-oi'); ?></th>
                            <th scope="col"><?php esc_html_e('To', 'mail-smtp-by-oi'); ?></th>
                            <th scope="col"><?php esc_html_e('Subject', 'mail-smtp-by-oi'); ?></th>
                            <th scope="col" style="width: 110px;"><?php esc_html_e('Mailer', 'mail-smtp-by-oi'); ?></th>
                            <th scope="col" style="width: 150px;"><?php esc_html_e('Date / Time', 'mail-smtp-by-oi'); ?></th>
                            <th scope="col" style="width: 170px; text-align:right;"><?php esc_html_e('Actions', 'mail-smtp-by-oi'); ?></th>
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
                                        <span class="oi-badge oi-badge-sent"><?php esc_html_e('Sent', 'mail-smtp-by-oi'); ?></span>
                                    <?php else : ?>
                                        <span class="oi-badge oi-badge-failed" title="<?php echo esc_attr($row['error_message']); ?>"><?php esc_html_e('Failed', 'mail-smtp-by-oi'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html($row['to_email']); ?></strong></td>
                                <td><?php echo esc_html($row['subject']); ?></td>
                                <td><code><?php echo esc_html(strtoupper($row['mailer'])); ?></code></td>
                                <td><span class="oi-date-text"><?php echo esc_html(get_date_from_gmt($row['created_at'], 'Y-m-d H:i:s')); ?></span></td>
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

            <div class="oi-section-title"><?php esc_html_e('Data Cleanup on Uninstall', 'mail-smtp-by-oi'); ?></div>
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

        wp_send_json_success([
            'log' => [
                'id'            => $log['id'],
                'to'            => esc_html($log['to_email']),
                'subject'       => esc_html($log['subject']),
                'message'       => $log['message'],
                'headers'       => esc_html($log['headers']),
                'attachments'   => esc_html($log['attachments']),
                'mailer'        => esc_html(strtoupper($log['mailer'])),
                'status'        => esc_html($log['status']),
                'error_message' => esc_html($log['error_message'] ?? ''),
                'debug_log'     => esc_html($log['debug_log'] ?? ''),
                'date'          => esc_html(get_date_from_gmt($log['created_at'], 'Y-m-d H:i:s')),
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
        $res = OI_Logger::resend($id);

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
}
