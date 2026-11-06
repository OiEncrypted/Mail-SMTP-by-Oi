<?php
/**
 * Core Mail Dispatcher & Hook Interceptor
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Mailer {

    /**
     * Provider instances
     *
     * @var array
     */
    private static $providers = [];

    /**
     * Currently active mail data being processed
     *
     * @var array|null
     */
    private static $current_mail_data = null;

    /**
     * Debug logs collected during PHPMailer run
     *
     * @var array
     */
    private static $phpmailer_debug = [];

    /**
     * Flag to bypass queue during direct dispatch
     *
     * @var bool
     */
    private static $is_direct_dispatch = false;

    /**
     * Tracking hash for the currently dispatched email
     *
     * @var string|null
     */
    private static $current_tracking_hash = null;

    /**
     * Initialize hooks
     */
    public static function init() {
        self::register_providers();

        // High priority hooks to ensure our settings override defaults
        add_filter('wp_mail_from', [__CLASS__, 'filter_from_email'], 99999);
        add_filter('wp_mail_from_name', [__CLASS__, 'filter_from_name'], 99999);

        // Pre wp_mail short-circuit for API-based providers (WP 5.7+)
        add_filter('pre_wp_mail', [__CLASS__, 'pre_wp_mail_handler'], 10, 2);

        // PHPMailer configuration for SMTP
        add_action('phpmailer_init', [__CLASS__, 'configure_phpmailer'], 99999);

        // Failure capture
        add_action('wp_mail_failed', [__CLASS__, 'on_wp_mail_failed'], 99999);

        // Success capture for standard wp_mail
        add_action('wp_mail_succeeded', [__CLASS__, 'on_wp_mail_succeeded'], 99999);
    }

    /**
     * Register available mail providers
     */
    public static function register_providers() {
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/interface-oi-provider.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-smtp.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-sendgrid.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-mailgun.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-brevo.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-postmark.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-resend.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-ses.php';
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-default.php';

        self::$providers = [
            'smtp'     => new OI_Provider_SMTP('smtp', 'Custom SMTP'),
            'gmail'    => new OI_Provider_SMTP('gmail', 'Gmail / Google Workspace'),
            'outlook'  => new OI_Provider_SMTP('outlook', 'Microsoft 365 / Outlook'),
            'ses'      => new OI_Provider_SES(),
            'sendgrid' => new OI_Provider_SendGrid(),
            'mailgun'  => new OI_Provider_Mailgun(),
            'brevo'    => new OI_Provider_Brevo(),
            'postmark' => new OI_Provider_Postmark(),
            'resend'   => new OI_Provider_Resend(),
            'default'  => new OI_Provider_Default(),
        ];
    }

    /**
     * Get a provider by key
     *
     * @param string $key
     * @return OI_Provider_Interface
     */
    public static function get_provider($key) {
        if (isset(self::$providers[$key])) {
            return self::$providers[$key];
        }
        return self::$providers['smtp'];
    }

    /**
     * Filter From Email
     *
     * @param string $from_email
     * @return string
     */
    public static function filter_from_email($from_email) {
        $settings = OI_Options::get_all(false);
        if (empty($settings['enabled'])) {
            return $from_email;
        }

        $configured_from = $settings['from_email'] ?? '';
        if (!empty($configured_from) && is_email($configured_from)) {
            if (!empty($settings['force_from_email']) || empty($from_email) || $from_email === 'wordpress@' . (isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : 'localhost')) {
                return $configured_from;
            }
        }

        return $from_email;
    }

    /**
     * Filter From Name
     *
     * @param string $from_name
     * @return string
     */
    public static function filter_from_name($from_name) {
        $settings = OI_Options::get_all(false);
        if (empty($settings['enabled'])) {
            return $from_name;
        }

        $configured_name = $settings['from_name'] ?? '';
        if (!empty($configured_name)) {
            if (!empty($settings['force_from_name']) || empty($from_name) || $from_name === 'WordPress') {
                return $configured_name;
            }
        }

        return $from_name;
    }

    /**
     * Intercept wp_mail for API providers using pre_wp_mail
     *
     * @param null|bool $return
     * @param array $mail_args
     * @return null|bool
     */
    public static function pre_wp_mail_handler($return, $mail_args) {
        $settings = OI_Options::get_all(true);
        if (empty($settings['enabled'])) {
            return $return;
        }

        // 0. Offload to background queue if enabled and not already in direct queue dispatch
        if (!self::$is_direct_dispatch && OI_Queue::is_enabled()) {
            $queued = OI_Queue::enqueue($mail_args);
            if ($queued) {
                return true; // Short-circuit wp_mail immediately for zero latency!
            }
        }

        // 1. Reroute All Outgoing Emails (FluentSMTP-inspired Staging feature)
        if (!empty($settings['reroute_all_to']) && is_email($settings['reroute_all_to'])) {
            $original_to = is_array($mail_args['to']) ? implode(', ', $mail_args['to']) : $mail_args['to'];
            $mail_args['to'] = $settings['reroute_all_to'];
            $mail_args['subject'] = '[Rerouted from: ' . $original_to . '] ' . ($mail_args['subject'] ?? '');
        }

        // 2. Email Simulation / Sandbox Staging Mode (FluentSMTP & Post-SMTP feature)
        if (!empty($settings['enable_simulation_mode'])) {
            $tracking_hash = OI_Tracker::generate_hash();
            $log_id = OI_Logger::log([
                'to'            => $mail_args['to'] ?? '',
                'subject'       => ($mail_args['subject'] ?? '') . ' [Simulated]',
                'message'       => $mail_args['message'] ?? '',
                'headers'       => $mail_args['headers'] ?? [],
                'attachments'   => $mail_args['attachments'] ?? [],
                'mailer'        => 'simulation',
                'status'        => 'sent',
                'tracking_hash' => $tracking_hash,
                'debug_log'     => 'Email Simulation / Staging Mode is ACTIVE. Message was logged successfully but not dispatched to external mail servers.',
            ]);

            if ($log_id && !empty($mail_args['attachments'])) {
                OI_Attachments::archive($log_id, $mail_args['attachments']);
            }

            return true; // Short-circuit wp_mail: zero external email sent!
        }

        // Generate tracking hash & process message body
        $tracking_hash = OI_Tracker::generate_hash();
        self::$current_tracking_hash = $tracking_hash;

        $message = $mail_args['message'] ?? '';
        $processed_message = OI_Tracker::process_email_body($message, $tracking_hash);

        $default_mailer = $settings['mailer'] ?? 'smtp';

        // Evaluate Smart Conditional Routing
        $mail_context = [
            'to'          => $mail_args['to'] ?? '',
            'subject'     => $mail_args['subject'] ?? '',
            'from_email'  => self::filter_from_email($settings['from_email'] ?? ''),
            'headers'     => $mail_args['headers'] ?? [],
        ];
        $mailer = OI_Routing::resolve_mailer($mail_context, $default_mailer);

        // Custom SMTP, Gmail, Outlook, Default are processed via PHPMailer
        if (in_array($mailer, ['smtp', 'gmail', 'outlook', 'default'], true)) {
            $mail_args['message'] = $processed_message;
            self::$current_mail_data = $mail_args;
            self::$phpmailer_debug = [];
            return null; // Continue with normal PHPMailer flow
        }

        // For API Providers (SendGrid, Mailgun, Brevo, Postmark, Resend, SES)
        $provider = self::get_provider($mailer);
        if (!$provider) {
            return null;
        }

        $mail_data = [
            'to'            => $mail_args['to'] ?? '',
            'subject'       => $mail_args['subject'] ?? '',
            'message'       => $processed_message,
            'headers'       => $mail_args['headers'] ?? [],
            'attachments'   => $mail_args['attachments'] ?? [],
            'from_email'    => self::filter_from_email($settings['from_email'] ?? ''),
            'from_name'     => self::filter_from_name($settings['from_name'] ?? ''),
            'tracking_hash' => $tracking_hash,
        ];

        $result = $provider->send($mail_data, $settings);

        if ($result['success']) {
            $log_id = OI_Logger::log([
                'to'            => $mail_data['to'],
                'subject'       => $mail_data['subject'],
                'message'       => $mail_data['message'],
                'headers'       => $mail_data['headers'],
                'attachments'   => $mail_data['attachments'],
                'mailer'        => $mailer,
                'status'        => 'sent',
                'tracking_hash' => $tracking_hash,
                'debug_log'     => $result['debug'] ?? '',
            ]);

            if ($log_id && !empty($mail_data['attachments'])) {
                OI_Attachments::archive($log_id, $mail_data['attachments']);
            }

            return true;
        }

        // Check fallback if primary API failed
        if (!empty($settings['enable_fallback'])) {
            $fallback_mailer = $settings['fallback_mailer'] ?? 'default';
            $fallback_provider = self::get_provider($fallback_mailer);
            if ($fallback_provider) {
                $fallback_res = $fallback_provider->send($mail_data, $settings);
                if ($fallback_res['success']) {
                    OI_Logger::log([
                        'to'            => $mail_data['to'],
                        'subject'       => $mail_data['subject'] . ' [Fallback]',
                        'message'       => $mail_data['message'],
                        'headers'       => $mail_data['headers'],
                        'attachments'   => $mail_data['attachments'],
                        'mailer'        => $fallback_mailer . ' (fallback)',
                        'status'        => 'sent',
                        'tracking_hash' => $tracking_hash,
                        'debug_log'     => "Primary ($mailer) failed: " . $result['message'] . "\nSent via fallback: " . ($fallback_res['debug'] ?? ''),
                    ]);
                    return true;
                }
            }
        }

        // Failed
        OI_Logger::log([
            'to'            => $mail_data['to'],
            'subject'       => $mail_data['subject'],
            'message'       => $mail_data['message'],
            'headers'       => $mail_data['headers'],
            'attachments'   => $mail_data['attachments'],
            'mailer'        => $mailer,
            'status'        => 'failed',
            'tracking_hash' => $tracking_hash,
            'error_message' => $result['message'] ?? 'Unknown API Error',
            'debug_log'     => $result['debug'] ?? '',
        ]);

        self::trigger_failure_alert($result['message'] ?? 'API Error');

        return false;
    }

    /**
     * Configure PHPMailer when wp_mail runs for SMTP
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public static function configure_phpmailer($phpmailer) {
        $settings = OI_Options::get_all(true);
        if (empty($settings['enabled'])) {
            return;
        }

        $mailer = $settings['mailer'] ?? 'smtp';

        if ($mailer === 'default') {
            $phpmailer->isMail();
            return;
        }

        // Set up debug log collector
        self::$phpmailer_debug = [];
        $phpmailer->SMTPDebug = 2;
        $phpmailer->Debugoutput = function($str, $level) {
            $trimmed = trim($str);
            if (!empty($trimmed)) {
                self::$phpmailer_debug[] = date('H:i:s') . ' [' . $level . '] ' . $trimmed;
            }
        };

        $phpmailer->isSMTP();

        // Host and Port based on preset or custom
        if ($mailer === 'gmail') {
            $phpmailer->Host = 'smtp.gmail.com';
            $phpmailer->Port = 587;
            $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($mailer === 'outlook') {
            $phpmailer->Host = 'smtp.office365.com';
            $phpmailer->Port = 587;
            $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            // Custom SMTP
            $phpmailer->Host = $settings['smtp_host'] ?? '';
            $phpmailer->Port = (int)($settings['smtp_port'] ?? 587);

            $encryption = $settings['smtp_encryption'] ?? 'tls';
            if ($encryption === 'ssl') {
                $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $phpmailer->SMTPSecure = '';
            }
        }

        $phpmailer->SMTPAutoTLS = !empty($settings['smtp_autotls']);
        $phpmailer->Timeout     = (int)($settings['smtp_timeout'] ?? 20);

        // Authentication
        if (!empty($settings['smtp_auth'])) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $settings['smtp_user'] ?? '';
            $phpmailer->Password = $settings['smtp_pass'] ?? '';
        } else {
            $phpmailer->SMTPAuth = false;
        }

        // From
        $from_email = self::filter_from_email($settings['from_email'] ?? '');
        $from_name  = self::filter_from_name($settings['from_name'] ?? '');
        if (!empty($from_email)) {
            $phpmailer->setFrom($from_email, $from_name, false);
        }

        // Return-Path
        if (!empty($settings['set_return_path']) && !empty($from_email)) {
            $phpmailer->Sender = $from_email;
        }

        // Inject open and click tracking into PHPMailer Body if present
        if (!empty(self::$current_tracking_hash) && !empty($phpmailer->Body)) {
            $phpmailer->Body = OI_Tracker::process_email_body($phpmailer->Body, self::$current_tracking_hash);
        }
    }

    /**
     * Action called on wp_mail failure
     *
     * @param \WP_Error $wp_error
     */
    public static function on_wp_mail_failed($wp_error) {
        $settings = OI_Options::get_all(false);
        if (empty($settings['enabled'])) {
            return;
        }

        $mail_data = is_array(self::$current_mail_data) ? self::$current_mail_data : [];
        $error_msg = is_wp_error($wp_error) ? $wp_error->get_error_message() : 'Unknown error';

        OI_Logger::log([
            'to'            => $mail_data['to'] ?? '',
            'subject'       => $mail_data['subject'] ?? '',
            'message'       => $mail_data['message'] ?? '',
            'headers'       => $mail_data['headers'] ?? [],
            'attachments'   => $mail_data['attachments'] ?? [],
            'mailer'        => $settings['mailer'] ?? 'smtp',
            'status'        => 'failed',
            'error_message' => $error_msg,
            'debug_log'     => implode("\n", self::$phpmailer_debug),
        ]);

        self::trigger_failure_alert($error_msg);
    }

    /**
     * Trigger failure alert transient & multi-channel notifications
     *
     * @param string $error_msg
     */
    public static function trigger_failure_alert($error_msg = '') {
        $existing = get_transient('oi_email_failure_alert');
        $count = ($existing && isset($existing['count'])) ? $existing['count'] + 1 : 1;

        set_transient('oi_email_failure_alert', [
            'count'     => $count,
            'error'     => $error_msg,
            'timestamp' => time(),
        ], DAY_IN_SECONDS);

        $settings = OI_Options::get_all(true);
        if (empty($settings['enable_failure_alerts']) || get_transient('oi_failure_alert_throttled')) {
            return;
        }

        $channels = !empty($settings['alert_channels']) ? (array)$settings['alert_channels'] : ['email'];
        $site_name = get_option('blogname', 'WordPress Site');

        foreach ($channels as $channel) {
            self::dispatch_alert_to_channel($channel, $error_msg, $site_name, $settings, false);
        }

        // Throttle for 15 minutes to prevent alert storms
        set_transient('oi_failure_alert_throttled', true, 15 * MINUTE_IN_SECONDS);
    }

    /**
     * Dispatch alert to a specific channel (Email, Telegram, Slack, Discord)
     *
     * @param string $channel
     * @param string $error_msg
     * @param string $site_name
     * @param array $settings
     * @param bool $is_test
     * @return array ['success' => bool, 'message' => string]
     */
    public static function dispatch_alert_to_channel($channel, $error_msg, $site_name, array $settings, $is_test = false) {
        $logs_url = admin_url('admin.php?page=mail-smtp-by-oi-logs');
        $prefix = $is_test ? __('[TEST ALERT]', 'mail-smtp-by-oi') : __('[URGENT ERROR]', 'mail-smtp-by-oi');

        switch ($channel) {
            case 'telegram':
                $bot_token = trim($settings['telegram_bot_token'] ?? '');
                $chat_id   = trim($settings['telegram_chat_id'] ?? '');
                if (empty($bot_token) || empty($chat_id)) {
                    return ['success' => false, 'message' => __('Telegram Bot Token or Chat ID is missing.', 'mail-smtp-by-oi')];
                }

                $msg  = "🚨 <b>" . esc_html($prefix) . " Mail SMTP by Oi Alert</b>\n\n";
                $msg .= "<b>Website:</b> " . esc_html($site_name) . " (" . esc_url(home_url()) . ")\n";
                $msg .= "<b>Message:</b> " . esc_html($error_msg) . "\n";
                $msg .= "<b>Time:</b> " . esc_html(current_time('mysql')) . "\n\n";
                $msg .= "👉 <a href=\"" . esc_url($logs_url) . "\">View Logs in WordPress Dashboard</a>";

                $response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
                    'timeout' => 10,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body'    => wp_json_encode([
                        'chat_id'                  => $chat_id,
                        'text'                     => $msg,
                        'parse_mode'               => 'HTML',
                        'disable_web_page_preview' => true,
                    ]),
                ]);

                if (is_wp_error($response)) {
                    return ['success' => false, 'message' => $response->get_error_message()];
                }
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) {
                    return ['success' => true, 'message' => __('Telegram notification sent successfully.', 'mail-smtp-by-oi')];
                }
                $body = json_decode(wp_remote_retrieve_body($response), true);
                return ['success' => false, 'message' => $body['description'] ?? sprintf(__('Telegram HTTP error: %d', 'mail-smtp-by-oi'), $code)];

            case 'slack':
                $webhook = trim($settings['slack_webhook_url'] ?? '');
                if (empty($webhook)) {
                    return ['success' => false, 'message' => __('Slack Webhook URL is missing.', 'mail-smtp-by-oi')];
                }

                $slack_payload = [
                    'text' => sprintf("🚨 *%s WordPress Email Alert*\n*Site:* %s (%s)\n*Error:* `%s`\n<%s|Open Mail SMTP Logs>", $prefix, $site_name, home_url(), $error_msg, $logs_url),
                ];

                $response = wp_remote_post($webhook, [
                    'timeout' => 10,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body'    => wp_json_encode($slack_payload),
                ]);

                if (is_wp_error($response)) {
                    return ['success' => false, 'message' => $response->get_error_message()];
                }
                $code = wp_remote_retrieve_response_code($response);
                return ($code >= 200 && $code < 300)
                    ? ['success' => true, 'message' => __('Slack notification sent successfully.', 'mail-smtp-by-oi')]
                    : ['success' => false, 'message' => sprintf(__('Slack HTTP error: %d', 'mail-smtp-by-oi'), $code)];

            case 'discord':
                $webhook = trim($settings['discord_webhook_url'] ?? '');
                if (empty($webhook)) {
                    return ['success' => false, 'message' => __('Discord Webhook URL is missing.', 'mail-smtp-by-oi')];
                }

                $discord_payload = [
                    'content' => sprintf("🚨 **%s WordPress Email Alert**\n**Site:** %s (<%s>)\n**Error:** `%s`\n[Open Mail SMTP Logs](<%s>)", $prefix, $site_name, home_url(), $error_msg, $logs_url),
                ];

                $response = wp_remote_post($webhook, [
                    'timeout' => 10,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body'    => wp_json_encode($discord_payload),
                ]);

                if (is_wp_error($response)) {
                    return ['success' => false, 'message' => $response->get_error_message()];
                }
                $code = wp_remote_retrieve_response_code($response);
                return ($code >= 200 && $code < 300)
                    ? ['success' => true, 'message' => __('Discord notification sent successfully.', 'mail-smtp-by-oi')]
                    : ['success' => false, 'message' => sprintf(__('Discord HTTP error: %d', 'mail-smtp-by-oi'), $code)];

            case 'email':
            default:
                $alert_email = !empty($settings['failure_alert_email']) ? $settings['failure_alert_email'] : get_option('admin_email');
                if (!is_email($alert_email)) {
                    return ['success' => false, 'message' => __('Invalid alert recipient email address.', 'mail-smtp-by-oi')];
                }

                $sub = sprintf(__('%s Email Sending Alert on %s', 'mail-smtp-by-oi'), $prefix, $site_name);
                $body = sprintf(
                    __("Hello,\n\n%s from your WordPress website (%s):\n\nError Details:\n%s\n\nCheck logs in your WordPress dashboard:\n%s\n\nBest regards,\nMail SMTP by Oi", 'mail-smtp-by-oi'),
                    $is_test ? 'This is a test notification' : 'An outgoing email failed to send',
                    home_url(),
                    $error_msg,
                    $logs_url
                );

                $sent = @mail($alert_email, $sub, $body, 'From: no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                return $sent
                    ? ['success' => true, 'message' => sprintf(__('Alert email dispatched to %s.', 'mail-smtp-by-oi'), $alert_email)]
                    : ['success' => false, 'message' => __('PHP mail() failed to dispatch alert.', 'mail-smtp-by-oi')];
        }
    }

    /**
     * Action called on wp_mail success (WordPress 5.9+)
     *
     * @param array $mail_data
     */
    public static function on_wp_mail_succeeded($mail_data) {
        $settings = OI_Options::get_all(false);
        if (empty($settings['enabled'])) {
            return;
        }

        $log_id = OI_Logger::log([
            'to'            => $mail_data['to'] ?? '',
            'subject'       => $mail_data['subject'] ?? '',
            'message'       => $mail_data['message'] ?? '',
            'headers'       => $mail_data['headers'] ?? [],
            'attachments'   => $mail_data['attachments'] ?? [],
            'mailer'        => $settings['mailer'] ?? 'smtp',
            'status'        => 'sent',
            'tracking_hash' => self::$current_tracking_hash,
            'debug_log'     => implode("\n", self::$phpmailer_debug),
        ]);

        if ($log_id && !empty($mail_data['attachments'])) {
            OI_Attachments::archive($log_id, $mail_data['attachments']);
        }

        self::$current_tracking_hash = null;
    }

    /**
     * Dispatch email directly bypassing background queue
     *
     * @param array $mail_args
     * @return bool
     */
    public static function dispatch_direct(array $mail_args) {
        self::$is_direct_dispatch = true;

        $result = wp_mail(
            $mail_args['to'] ?? '',
            $mail_args['subject'] ?? '',
            $mail_args['message'] ?? '',
            $mail_args['headers'] ?? [],
            $mail_args['attachments'] ?? []
        );

        self::$is_direct_dispatch = false;
        return (bool)$result;
    }

    /**
     * Direct test email sender with live debug buffer
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @return array
     */
    public static function send_test($to, $subject, $message) {
        $settings = OI_Options::get_all(true);
        $mailer = $settings['mailer'] ?? 'smtp';

        $provider = self::get_provider($mailer);

        $mail_data = [
            'to'           => $to,
            'subject'      => $subject,
            'message'      => $message,
            'headers'      => ['Content-Type: text/html; charset=UTF-8'],
            'attachments'  => [],
            'from_email'   => self::filter_from_email($settings['from_email'] ?? ''),
            'from_name'    => self::filter_from_name($settings['from_name'] ?? ''),
            'content_type' => 'text/html',
        ];

        $start_time = microtime(true);
        $result = $provider->send($mail_data, $settings);
        $elapsed = round((microtime(true) - $start_time) * 1000, 2);

        // Also log this test email
        OI_Logger::log([
            'to'            => $to,
            'subject'       => '[TEST EMAIL] ' . $subject,
            'message'       => $message,
            'headers'       => $mail_data['headers'],
            'attachments'   => [],
            'mailer'        => $mailer,
            'status'        => $result['success'] ? 'sent' : 'failed',
            'error_message' => $result['success'] ? null : ($result['message'] ?? ''),
            'debug_log'     => ($result['debug'] ?? '') . "\nTotal time taken: {$elapsed}ms",
        ]);

        $result['elapsed_ms'] = $elapsed;
        return $result;
    }
}
