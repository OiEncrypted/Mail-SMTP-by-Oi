<?php
/**
 * 1-Click Migration & Settings Importer from Other SMTP Plugins
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Migration {

    /**
     * Detect installed or configured third-party SMTP plugins
     *
     * @return array
     */
    public static function detect_plugins() {
        $detected = [];

        // 1. WP Mail SMTP
        $wp_mail_smtp = get_option('wp_mail_smtp', false);
        if (!empty($wp_mail_smtp) && is_array($wp_mail_smtp)) {
            $detected['wp_mail_smtp'] = [
                'name'        => 'WP Mail SMTP',
                'description' => __('Found existing WP Mail SMTP configuration.', 'mail-smtp-by-oi'),
                'mailer'      => $wp_mail_smtp['mail']['mailer'] ?? 'smtp',
            ];
        }

        // 2. FluentSMTP
        $fluent_smtp = get_option('fluentmail-settings', false);
        if (!empty($fluent_smtp) && is_array($fluent_smtp) && !empty($fluent_smtp['connections'])) {
            $detected['fluent_smtp'] = [
                'name'        => 'FluentSMTP',
                'description' => __('Found existing FluentSMTP connections.', 'mail-smtp-by-oi'),
                'mailer'      => reset($fluent_smtp['connections'])['provider'] ?? 'smtp',
            ];
        }

        // 3. Easy WP SMTP
        $easy_smtp = get_option('swpsmtp_options', false);
        if (!empty($easy_smtp) && is_array($easy_smtp)) {
            $detected['easy_wp_smtp'] = [
                'name'        => 'Easy WP SMTP',
                'description' => __('Found existing Easy WP SMTP configuration.', 'mail-smtp-by-oi'),
                'mailer'      => 'smtp',
            ];
        }

        return $detected;
    }

    /**
     * Import settings from selected plugin
     *
     * @param string $plugin_key
     * @return array ['success' => bool, 'message' => string]
     */
    public static function import_from($plugin_key) {
        if (!current_user_can('manage_options')) {
            return ['success' => false, 'message' => __('Unauthorized.', 'mail-smtp-by-oi')];
        }

        $imported = [];

        switch ($plugin_key) {
            case 'wp_mail_smtp':
                $opts = get_option('wp_mail_smtp', []);
                if (empty($opts)) {
                    return ['success' => false, 'message' => __('No settings found for WP Mail SMTP.', 'mail-smtp-by-oi')];
                }

                $imported['from_email'] = $opts['mail']['from_email'] ?? '';
                $imported['from_name']  = $opts['mail']['from_name'] ?? '';
                $imported['force_from_email'] = !empty($opts['mail']['from_email_force']);
                $imported['force_from_name']  = !empty($opts['mail']['from_name_force']);
                $imported['set_return_path']  = !empty($opts['mail']['return_path']);

                $raw_mailer = $opts['mail']['mailer'] ?? 'smtp';
                $imported['mailer'] = self::map_mailer_key($raw_mailer);

                if (isset($opts['smtp'])) {
                    $imported['smtp_host']       = $opts['smtp']['host'] ?? '';
                    $imported['smtp_port']       = $opts['smtp']['port'] ?? 587;
                    $imported['smtp_encryption'] = $opts['smtp']['encryption'] ?? 'tls';
                    $imported['smtp_user']       = $opts['smtp']['user'] ?? '';
                    $imported['smtp_pass']       = $opts['smtp']['pass'] ?? '';
                    $imported['smtp_auth']       = !empty($opts['smtp']['auth']);
                    $imported['smtp_autotls']    = !empty($opts['smtp']['autotls']);
                }

                if (isset($opts['sendgrid']['api_key'])) {
                    $imported['api_key'] = $opts['sendgrid']['api_key'];
                }
                if (isset($opts['mailgun'])) {
                    $imported['mailgun_api_key'] = $opts['mailgun']['api_key'] ?? '';
                    $imported['mailgun_domain']  = $opts['mailgun']['domain'] ?? '';
                    $imported['mailgun_region']  = ($opts['mailgun']['region'] ?? 'US') === 'EU' ? 'eu' : 'us';
                }
                break;

            case 'fluent_smtp':
                $opts = get_option('fluentmail-settings', []);
                if (empty($opts['connections'])) {
                    return ['success' => false, 'message' => __('No connections found in FluentSMTP.', 'mail-smtp-by-oi')];
                }

                $conn = reset($opts['connections']);
                $provider = $conn['provider'] ?? 'smtp';
                $imported['mailer'] = self::map_mailer_key($provider);

                $imported['from_email'] = $conn['sender_email'] ?? '';
                $imported['from_name']  = $conn['sender_name'] ?? '';
                $imported['force_from_email'] = !empty($conn['force_from_email_id']);

                if (isset($conn['key_store'])) {
                    $ks = $conn['key_store'];
                    $imported['smtp_host']       = $ks['host'] ?? '';
                    $imported['smtp_port']       = $ks['port'] ?? 587;
                    $imported['smtp_encryption'] = $ks['encryption'] ?? 'tls';
                    $imported['smtp_user']       = $ks['username'] ?? '';
                    $imported['smtp_pass']       = $ks['password'] ?? '';
                    $imported['smtp_auth']       = !empty($ks['auth']);
                    $imported['api_key']         = $ks['api_key'] ?? '';
                }
                break;

            case 'easy_wp_smtp':
                $opts = get_option('swpsmtp_options', []);
                if (empty($opts)) {
                    return ['success' => false, 'message' => __('No settings found for Easy WP SMTP.', 'mail-smtp-by-oi')];
                }

                $imported['mailer']     = 'smtp';
                $imported['from_email'] = $opts['from_email_address'] ?? '';
                $imported['from_name']  = $opts['from_name'] ?? '';

                if (isset($opts['smtp_settings'])) {
                    $s = $opts['smtp_settings'];
                    $imported['smtp_host']       = $s['host'] ?? '';
                    $imported['smtp_port']       = $s['port'] ?? 587;
                    $imported['smtp_encryption'] = $s['type_encryption'] ?? 'tls';
                    $imported['smtp_user']       = $s['smtp_user'] ?? '';
                    $imported['smtp_pass']       = $s['smtp_password'] ?? '';
                    $imported['smtp_auth']       = !empty($s['autentication']);
                }
                break;

            default:
                return ['success' => false, 'message' => __('Unknown plugin source.', 'mail-smtp-by-oi')];
        }

        if (empty($imported)) {
            return ['success' => false, 'message' => __('No settings could be extracted.', 'mail-smtp-by-oi')];
        }

        $saved = OI_Options::save($imported);

        if ($saved !== false) {
            return [
                'success' => true,
                'message' => sprintf(__('Settings successfully imported from %s!', 'mail-smtp-by-oi'), esc_html(str_replace('_', ' ', strtoupper($plugin_key)))),
            ];
        }

        return ['success' => false, 'message' => __('Failed to save imported settings.', 'mail-smtp-by-oi')];
    }

    /**
     * Map third-party mailer keys to our keys
     *
     * @param string $key
     * @return string
     */
    private static function map_mailer_key($key) {
        $key = strtolower(trim($key));
        $map = [
            'smtp'        => 'smtp',
            'gmail'       => 'gmail',
            'google'      => 'gmail',
            'outlook'     => 'outlook',
            'office365'   => 'outlook',
            'sendgrid'    => 'sendgrid',
            'mailgun'     => 'mailgun',
            'sendinblue'  => 'brevo',
            'brevo'       => 'brevo',
            'postmark'    => 'postmark',
            'resend'      => 'resend',
            'mail'        => 'default',
        ];

        return $map[$key] ?? 'smtp';
    }
}
