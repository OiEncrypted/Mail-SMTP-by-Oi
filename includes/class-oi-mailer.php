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
        require_once OI_MAIL_SMTP_PATH . 'includes/providers/class-oi-provider-default.php';

        self::$providers = [
            'smtp'     => new OI_Provider_SMTP('smtp', 'Custom SMTP'),
            'gmail'    => new OI_Provider_SMTP('gmail', 'Gmail / Google Workspace'),
            'outlook'  => new OI_Provider_SMTP('outlook', 'Microsoft 365 / Outlook'),
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

        $mailer = $settings['mailer'] ?? 'smtp';

        // Custom SMTP, Gmail, Outlook, Default are processed via PHPMailer
        if (in_array($mailer, ['smtp', 'gmail', 'outlook', 'default'], true)) {
            self::$current_mail_data = $mail_args;
            self::$phpmailer_debug = [];
            return null; // Continue with normal PHPMailer flow
        }

        // For API Providers (SendGrid, Mailgun, Brevo, Postmark, Resend)
        $provider = self::get_provider($mailer);
        if (!$provider) {
            return null;
        }

        $mail_data = [
            'to'          => $mail_args['to'] ?? '',
            'subject'     => $mail_args['subject'] ?? '',
            'message'     => $mail_args['message'] ?? '',
            'headers'     => $mail_args['headers'] ?? [],
            'attachments' => $mail_args['attachments'] ?? [],
            'from_email'  => self::filter_from_email($settings['from_email'] ?? ''),
            'from_name'   => self::filter_from_name($settings['from_name'] ?? ''),
        ];

        $result = $provider->send($mail_data, $settings);

        if ($result['success']) {
            OI_Logger::log([
                'to'            => $mail_data['to'],
                'subject'       => $mail_data['subject'],
                'message'       => $mail_data['message'],
                'headers'       => $mail_data['headers'],
                'attachments'   => $mail_data['attachments'],
                'mailer'        => $mailer,
                'status'        => 'sent',
                'debug_log'     => $result['debug'] ?? '',
            ]);

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
            'error_message' => $result['message'] ?? 'Unknown API Error',
            'debug_log'     => $result['debug'] ?? '',
        ]);

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

        OI_Logger::log([
            'to'            => $mail_data['to'] ?? '',
            'subject'       => $mail_data['subject'] ?? '',
            'message'       => $mail_data['message'] ?? '',
            'headers'       => $mail_data['headers'] ?? [],
            'attachments'   => $mail_data['attachments'] ?? [],
            'mailer'        => $settings['mailer'] ?? 'smtp',
            'status'        => 'sent',
            'debug_log'     => implode("\n", self::$phpmailer_debug),
        ]);
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
