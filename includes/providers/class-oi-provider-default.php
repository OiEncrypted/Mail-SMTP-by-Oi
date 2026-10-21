<?php
/**
 * Default PHP / WordPress Mail Provider
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_Default implements OI_Provider_Interface {

    public function get_id() {
        return 'default';
    }

    public function get_name() {
        return 'Default PHP Mail';
    }

    public function send(array $mail_data, array $settings) {
        $to          = $mail_data['to'] ?? '';
        $subject     = $mail_data['subject'] ?? '';
        $message     = $mail_data['message'] ?? '';
        $headers     = $mail_data['headers'] ?? [];
        $attachments = $mail_data['attachments'] ?? [];

        // Temporarily unhook our own processor to avoid recursive loop
        remove_action('phpmailer_init', ['OI_Mailer', 'configure_phpmailer'], 99999);

        $sent = mail(
            is_array($to) ? implode(', ', $to) : (string)$to,
            $subject,
            $message,
            is_array($headers) ? implode("\r\n", $headers) : (string)$headers
        );

        // Re-hook
        add_action('phpmailer_init', ['OI_Mailer', 'configure_phpmailer'], 99999);

        return [
            'success' => $sent,
            'message' => $sent ? __('Email sent via default PHP mail().', 'mail-smtp-by-oi') : __('Failed to send via PHP mail().', 'mail-smtp-by-oi'),
            'debug'   => $sent ? 'Native mail() returned true.' : 'Native mail() returned false.',
        ];
    }
}
