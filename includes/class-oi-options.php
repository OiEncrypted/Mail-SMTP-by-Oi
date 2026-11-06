<?php
/**
 * Settings and Options Handler
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Options {

    /**
     * Option key name in wp_options
     */
    const OPTION_KEY = 'oi_mail_smtp_settings';

    /**
     * Cached settings
     *
     * @var array
     */
    private static $settings = null;

    /**
     * Default options
     *
     * @return array
     */
    public static function get_defaults() {
        $admin_email = get_option('admin_email', 'admin@' . (isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : 'example.com'));
        $site_name   = get_option('blogname', 'WordPress');

        return [
            'enabled'            => true,
            'mailer'             => 'smtp', // 'smtp', 'gmail', 'outlook', 'sendgrid', 'mailgun', 'brevo', 'postmark', 'resend', 'default'
            'from_email'         => $admin_email,
            'force_from_email'   => false,
            'from_name'          => $site_name,
            'force_from_name'    => false,
            'set_return_path'    => true,

            // Custom SMTP
            'smtp_host'          => '',
            'smtp_port'          => 587,
            'smtp_encryption'    => 'tls', // 'none', 'ssl', 'tls'
            'smtp_auth'          => true,
            'smtp_autotls'       => true,
            'smtp_user'          => '',
            'smtp_pass'          => '',
            'smtp_timeout'       => 20,

            // API Providers
            'api_key'            => '',
            'mailgun_api_key'    => '',
            'mailgun_domain'     => '',
            'mailgun_region'     => 'us', // 'us', 'eu'

            // Logging & Privacy
            'enable_logging'     => true,
            'log_body'           => true,
            'log_retention_days' => 30, // 0 = keep forever, 7, 14, 30, 60, 90
            'delete_on_uninstall'=> false,

            // Fallback & Alerts
            'enable_fallback'        => false,
            'fallback_mailer'        => 'default',
            'enable_failure_alerts'  => false,
            'failure_alert_email'    => $admin_email,
            'alert_channels'         => ['email'], // 'email', 'telegram', 'slack', 'discord'
            'telegram_bot_token'     => '',
            'telegram_chat_id'       => '',
            'slack_webhook_url'      => '',
            'discord_webhook_url'    => '',

            // Amazon SES
            'ses_access_key'         => '',
            'ses_secret_key'         => '',
            'ses_region'             => 'us-east-1',

            // Tracking & Background Queue
            'enable_open_tracking'   => true,
            'enable_click_tracking'  => true,
            'enable_queue'           => false,

            // Enterprise Features: Smart Routing, Weekly Digest & Attachment Vault
            'routing_rules'          => [],
            'enable_weekly_digest'   => true,
            'digest_recipient_email' => $admin_email,
            'save_attachments'       => true,

            // Developer Staging & Email Controls (Reference from FluentSMTP & WP Mail SMTP Pro)
            'enable_simulation_mode' => false,
            'reroute_all_to'         => '',
            'email_controls'         => [],
        ];
    }

    /**
     * Get all settings merged with defaults
     *
     * @param bool $decrypt Whether to decrypt passwords/keys
     * @return array
     */
    public static function get_all($decrypt = true) {
        if (self::$settings === null) {
            $saved = get_option(self::OPTION_KEY, []);
            if (!is_array($saved)) {
                $saved = [];
            }
            self::$settings = wp_parse_args($saved, self::get_defaults());
        }

        $settings = self::$settings;

        if ($decrypt) {
            $sensitive_keys = ['smtp_pass', 'api_key', 'mailgun_api_key', 'ses_access_key', 'ses_secret_key', 'telegram_bot_token'];
            foreach ($sensitive_keys as $key) {
                if (!empty($settings[$key])) {
                    $settings[$key] = OI_Encryption::decrypt($settings[$key]);
                }
            }
        }

        return $settings;
    }

    /**
     * Get a specific setting
     *
     * @param string $key
     * @param mixed $default
     * @param bool $decrypt
     * @return mixed
     */
    public static function get($key, $default = null, $decrypt = true) {
        $settings = self::get_all($decrypt);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Save settings with validation, sanitization, and encryption
     *
     * @param array $raw_data
     * @return bool
     */
    public static function save(array $raw_data) {
        $current = self::get_all(true);
        $defaults = self::get_defaults();
        $cleaned = [];

        // Boolean flags
        $cleaned['enabled']           = !empty($raw_data['enabled']);
        $cleaned['force_from_email']  = !empty($raw_data['force_from_email']);
        $cleaned['force_from_name']   = !empty($raw_data['force_from_name']);
        $cleaned['set_return_path']   = !empty($raw_data['set_return_path']);
        $cleaned['smtp_auth']         = !empty($raw_data['smtp_auth']);
        $cleaned['smtp_autotls']      = !empty($raw_data['smtp_autotls']);
        $cleaned['enable_logging']    = !empty($raw_data['enable_logging']);
        $cleaned['log_body']          = !empty($raw_data['log_body']);
        $cleaned['delete_on_uninstall']  = !empty($raw_data['delete_on_uninstall']);
        $cleaned['enable_fallback']      = !empty($raw_data['enable_fallback']);
        $cleaned['enable_failure_alerts'] = !empty($raw_data['enable_failure_alerts']);
        $cleaned['failure_alert_email']  = !empty($raw_data['failure_alert_email']) ? sanitize_email($raw_data['failure_alert_email']) : $defaults['failure_alert_email'];
        $cleaned['enable_open_tracking']  = !empty($raw_data['enable_open_tracking']);
        $cleaned['enable_click_tracking'] = !empty($raw_data['enable_click_tracking']);
        $cleaned['enable_queue']          = !empty($raw_data['enable_queue']);

        // Text & Select fields
        $cleaned['mailer'] = in_array($raw_data['mailer'] ?? '', ['smtp', 'gmail', 'outlook', 'sendgrid', 'mailgun', 'brevo', 'postmark', 'resend', 'ses', 'default'], true)
            ? $raw_data['mailer'] : 'smtp';

        $cleaned['from_email'] = !empty($raw_data['from_email']) ? sanitize_email($raw_data['from_email']) : $defaults['from_email'];
        $cleaned['from_name']  = !empty($raw_data['from_name']) ? sanitize_text_field($raw_data['from_name']) : $defaults['from_name'];

        $cleaned['smtp_host']       = !empty($raw_data['smtp_host']) ? sanitize_text_field($raw_data['smtp_host']) : '';
        $cleaned['smtp_port']       = !empty($raw_data['smtp_port']) ? absint($raw_data['smtp_port']) : 587;
        $cleaned['smtp_encryption'] = in_array($raw_data['smtp_encryption'] ?? '', ['none', 'ssl', 'tls'], true) ? $raw_data['smtp_encryption'] : 'tls';
        $cleaned['smtp_user']       = !empty($raw_data['smtp_user']) ? sanitize_text_field($raw_data['smtp_user']) : '';
        $cleaned['smtp_timeout']    = !empty($raw_data['smtp_timeout']) ? max(5, min(120, absint($raw_data['smtp_timeout']))) : 20;

        // Password handling: if left blank, keep existing encrypted password
        if (isset($raw_data['smtp_pass']) && $raw_data['smtp_pass'] !== '') {
            $cleaned['smtp_pass'] = OI_Encryption::encrypt($raw_data['smtp_pass']);
        } else {
            $cleaned['smtp_pass'] = !empty($current['smtp_pass']) ? OI_Encryption::encrypt($current['smtp_pass']) : '';
        }

        // API Key handling
        if (isset($raw_data['api_key']) && $raw_data['api_key'] !== '') {
            $cleaned['api_key'] = OI_Encryption::encrypt(trim($raw_data['api_key']));
        } else {
            $cleaned['api_key'] = !empty($current['api_key']) ? OI_Encryption::encrypt($current['api_key']) : '';
        }

        // Mailgun specifics
        if (isset($raw_data['mailgun_api_key']) && $raw_data['mailgun_api_key'] !== '') {
            $cleaned['mailgun_api_key'] = OI_Encryption::encrypt(trim($raw_data['mailgun_api_key']));
        } else {
            $cleaned['mailgun_api_key'] = !empty($current['mailgun_api_key']) ? OI_Encryption::encrypt($current['mailgun_api_key']) : '';
        }
        $cleaned['mailgun_domain'] = !empty($raw_data['mailgun_domain']) ? sanitize_text_field(trim($raw_data['mailgun_domain'])) : '';
        $cleaned['mailgun_region'] = ($raw_data['mailgun_region'] ?? 'us') === 'eu' ? 'eu' : 'us';

        // Amazon SES specifics
        if (isset($raw_data['ses_access_key']) && $raw_data['ses_access_key'] !== '') {
            $cleaned['ses_access_key'] = OI_Encryption::encrypt(trim($raw_data['ses_access_key']));
        } else {
            $cleaned['ses_access_key'] = !empty($current['ses_access_key']) ? OI_Encryption::encrypt($current['ses_access_key']) : '';
        }
        if (isset($raw_data['ses_secret_key']) && $raw_data['ses_secret_key'] !== '') {
            $cleaned['ses_secret_key'] = OI_Encryption::encrypt(trim($raw_data['ses_secret_key']));
        } else {
            $cleaned['ses_secret_key'] = !empty($current['ses_secret_key']) ? OI_Encryption::encrypt($current['ses_secret_key']) : '';
        }
        $cleaned['ses_region'] = !empty($raw_data['ses_region']) ? sanitize_text_field(trim($raw_data['ses_region'])) : 'us-east-1';

        // Multi-channel failure alerts
        $allowed_channels = ['email', 'telegram', 'slack', 'discord'];
        $raw_channels = isset($raw_data['alert_channels']) ? (array)$raw_data['alert_channels'] : ['email'];
        $cleaned['alert_channels'] = array_values(array_intersect($allowed_channels, $raw_channels));
        if (empty($cleaned['alert_channels'])) {
            $cleaned['alert_channels'] = ['email'];
        }

        if (isset($raw_data['telegram_bot_token']) && $raw_data['telegram_bot_token'] !== '') {
            $cleaned['telegram_bot_token'] = OI_Encryption::encrypt(trim($raw_data['telegram_bot_token']));
        } else {
            $cleaned['telegram_bot_token'] = !empty($current['telegram_bot_token']) ? OI_Encryption::encrypt($current['telegram_bot_token']) : '';
        }
        $cleaned['telegram_chat_id']    = !empty($raw_data['telegram_chat_id']) ? sanitize_text_field(trim($raw_data['telegram_chat_id'])) : '';
        $cleaned['slack_webhook_url']   = !empty($raw_data['slack_webhook_url']) ? esc_url_raw(trim($raw_data['slack_webhook_url'])) : '';
        $cleaned['discord_webhook_url'] = !empty($raw_data['discord_webhook_url']) ? esc_url_raw(trim($raw_data['discord_webhook_url'])) : '';

        $cleaned['log_retention_days'] = isset($raw_data['log_retention_days']) ? absint($raw_data['log_retention_days']) : 30;
        $cleaned['fallback_mailer']    = sanitize_text_field($raw_data['fallback_mailer'] ?? 'default');

        // Enterprise Features
        $cleaned['enable_weekly_digest']   = !empty($raw_data['enable_weekly_digest']);
        $cleaned['digest_recipient_email'] = !empty($raw_data['digest_recipient_email']) ? sanitize_email($raw_data['digest_recipient_email']) : $defaults['digest_recipient_email'];
        $cleaned['save_attachments']       = !empty($raw_data['save_attachments']);

        $cleaned['routing_rules'] = [];
        if (!empty($raw_data['routing_rules']) && is_array($raw_data['routing_rules'])) {
            foreach ($raw_data['routing_rules'] as $rule) {
                if (!empty($rule['mailer']) && !empty($rule['field']) && isset($rule['value']) && trim($rule['value']) !== '') {
                    $cleaned['routing_rules'][] = [
                        'field'    => sanitize_key($rule['field']),
                        'operator' => sanitize_key($rule['operator'] ?? 'contains'),
                        'value'    => sanitize_text_field(trim($rule['value'])),
                        'mailer'   => sanitize_key($rule['mailer']),
                    ];
                }
            }
        }

        // Staging Simulation Mode & Reroute All (FluentSMTP Feature)
        $cleaned['enable_simulation_mode'] = !empty($raw_data['enable_simulation_mode']);
        $cleaned['reroute_all_to']         = !empty($raw_data['reroute_all_to']) ? sanitize_email($raw_data['reroute_all_to']) : '';

        // Email Controls (WP Mail SMTP Pro Feature)
        $cleaned['email_controls'] = [];
        if (!empty($raw_data['email_controls']) && is_array($raw_data['email_controls'])) {
            foreach ($raw_data['email_controls'] as $control_key => $val) {
                if (!empty($val)) {
                    $cleaned['email_controls'][sanitize_key($control_key)] = 1;
                }
            }
        }

        self::$settings = null; // flush cache
        return update_option(self::OPTION_KEY, $cleaned);
    }
}
