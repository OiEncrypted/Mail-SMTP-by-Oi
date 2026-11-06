<?php
/**
 * SMTP Provider (Custom SMTP, Gmail, Outlook)
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_SMTP implements OI_Provider_Interface {

    private $id = 'smtp';
    private $name = 'Custom SMTP';

    public function __construct($id = 'smtp', $name = 'Custom SMTP') {
        $this->id = $id;
        $this->name = $name;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_name() {
        return $this->name;
    }

    /**
     * Send email via PHPMailer SMTP
     *
     * @param array $mail_data
     * @param array $settings
     * @return array
     */
    public function send(array $mail_data, array $settings) {
        global $phpmailer;

        // Ensure PHPMailer is loaded
        if (!($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        }

        $debug_log = [];

        try {
            // Reset PHPMailer state
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();
            $phpmailer->clearReplyTos();

            // Setup debug logging
            $phpmailer->SMTPDebug = 2; // Client and server messages
            $phpmailer->Debugoutput = function($str, $level) use (&$debug_log) {
                $trimmed = trim($str);
                if (!empty($trimmed)) {
                    $debug_log[] = date('Y-m-d H:i:s') . ' [SMTP ' . $level . '] ' . $trimmed;
                }
            };

            // Mailer configuration
            $phpmailer->isSMTP();

            $host = $settings['smtp_host'] ?? '';
            $port = (int)($settings['smtp_port'] ?? 587);
            $encryption = $settings['smtp_encryption'] ?? 'tls';

            // Automatic presets for Gmail and Outlook
            if ($this->id === 'gmail' || ($settings['mailer'] ?? '') === 'gmail') {
                $host = 'smtp.gmail.com';
                $port = 587;
                $encryption = 'tls';
            } elseif ($this->id === 'outlook' || ($settings['mailer'] ?? '') === 'outlook') {
                $host = 'smtp.office365.com';
                $port = 587;
                $encryption = 'tls';
            }

            $phpmailer->Host = $host;
            $phpmailer->Port = $port;
            $phpmailer->Timeout = (int)($settings['smtp_timeout'] ?? 20);

            // Encryption
            if ($encryption === 'ssl') {
                $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $phpmailer->SMTPSecure = '';
            }

            // AutoTLS
            $phpmailer->SMTPAutoTLS = !empty($settings['smtp_autotls']);

            // Authentication
            if (!empty($settings['smtp_auth'])) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $settings['smtp_user'] ?? '';
                $phpmailer->Password = $settings['smtp_pass'] ?? '';
            } else {
                $phpmailer->SMTPAuth = false;
            }

            // From & From Name
            $from_email = !empty($mail_data['from_email']) ? $mail_data['from_email'] : ($settings['from_email'] ?? get_option('admin_email'));
            $from_name  = !empty($mail_data['from_name']) ? $mail_data['from_name'] : ($settings['from_name'] ?? get_option('blogname'));
            $phpmailer->setFrom($from_email, $from_name, false);

            // Return Path
            if (!empty($settings['set_return_path'])) {
                $phpmailer->Sender = $from_email;
            }

            // Reply-To
            if (!empty($mail_data['reply_to'])) {
                foreach ((array)$mail_data['reply_to'] as $reply) {
                    if (is_array($reply) && isset($reply['email'])) {
                        $phpmailer->addReplyTo($reply['email'], $reply['name'] ?? '');
                    } elseif (is_string($reply) && is_email($reply)) {
                        $phpmailer->addReplyTo($reply);
                    }
                }
            }

            // Recipients
            $to_list = is_array($mail_data['to']) ? $mail_data['to'] : explode(',', (string)$mail_data['to']);
            foreach ($to_list as $to_item) {
                $clean_to = trim($to_item);
                if (is_email($clean_to)) {
                    $phpmailer->addAddress($clean_to);
                }
            }

            // CC / BCC if passed in mail_data
            if (!empty($mail_data['cc'])) {
                foreach ((array)$mail_data['cc'] as $cc_item) {
                    if (is_email(trim($cc_item))) {
                        $phpmailer->addCC(trim($cc_item));
                    }
                }
            }
            if (!empty($mail_data['bcc'])) {
                foreach ((array)$mail_data['bcc'] as $bcc_item) {
                    if (is_email(trim($bcc_item))) {
                        $phpmailer->addBCC(trim($bcc_item));
                    }
                }
            }

            // Subject & Body
            $phpmailer->Subject = $mail_data['subject'] ?? '';
            $message = $mail_data['message'] ?? '';

            // Check if HTML content
            $is_html = (isset($mail_data['content_type']) && stripos($mail_data['content_type'], 'text/html') !== false)
                || (stripos($message, '<html') !== false)
                || (stripos($message, '<p') !== false)
                || (stripos($message, '<br') !== false);

            if ($is_html) {
                $phpmailer->isHTML(true);
                $phpmailer->Body = $message;
                $phpmailer->AltBody = wp_strip_all_tags($message);
            } else {
                $phpmailer->isHTML(false);
                $phpmailer->Body = $message;
            }

            // Custom Headers
            if (!empty($mail_data['headers'])) {
                $headers = is_array($mail_data['headers']) ? $mail_data['headers'] : explode("\n", (string)$mail_data['headers']);
                foreach ($headers as $header_line) {
                    $header_line = trim($header_line);
                    if (!empty($header_line) && strpos($header_line, ':') !== false) {
                        list($h_name, $h_val) = explode(':', $header_line, 2);
                        $h_name = trim($h_name);
                        $h_val  = trim($h_val);
                        if (!in_array(strtolower($h_name), ['from', 'content-type', 'to', 'subject'], true)) {
                            $phpmailer->addCustomHeader($h_name, $h_val);
                        }
                    }
                }
            }

            // Attachments
            if (!empty($mail_data['attachments'])) {
                foreach ((array)$mail_data['attachments'] as $attachment) {
                    if (is_string($attachment) && file_exists($attachment)) {
                        $phpmailer->addAttachment($attachment);
                    }
                }
            }

            // Send!
            $sent = $phpmailer->send();

            return [
                'success' => $sent,
                'message' => $sent ? __('Email sent successfully via SMTP.', 'mail-smtp-by-oi') : $phpmailer->ErrorInfo,
                'debug'   => implode("\n", $debug_log),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'debug'   => implode("\n", $debug_log) . "\nException: " . $e->getMessage(),
            ];
        }
    }
}
