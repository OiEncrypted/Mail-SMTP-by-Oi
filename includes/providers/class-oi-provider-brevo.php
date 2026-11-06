<?php
/**
 * Brevo (formerly Sendinblue) API Provider
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_Brevo implements OI_Provider_Interface {

    public function get_id() {
        return 'brevo';
    }

    public function get_name() {
        return 'Brevo (Sendinblue)';
    }

    public function send(array $mail_data, array $settings) {
        $api_key = !empty($settings['brevo_api_key']) ? $settings['brevo_api_key'] : ($settings['api_key'] ?? '');
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Brevo API Key is missing. Please check plugin settings.', 'mail-smtp-by-oi'),
                'debug'   => 'Brevo error: Missing API Key.',
            ];
        }

        $from_email = !empty($mail_data['from_email']) ? $mail_data['from_email'] : ($settings['from_email'] ?? get_option('admin_email'));
        $from_name  = !empty($mail_data['from_name']) ? $mail_data['from_name'] : ($settings['from_name'] ?? get_option('blogname'));

        $to_list = is_array($mail_data['to']) ? $mail_data['to'] : explode(',', (string)$mail_data['to']);
        $to_recipients = [];
        foreach ($to_list as $to_item) {
            $clean_to = trim($to_item);
            if (is_email($clean_to)) {
                $to_recipients[] = ['email' => $clean_to];
            }
        }

        if (empty($to_recipients)) {
            return [
                'success' => false,
                'message' => __('No valid recipient email address provided.', 'mail-smtp-by-oi'),
                'debug'   => 'Brevo error: Recipient list is empty.',
            ];
        }

        $message = $mail_data['message'] ?? '';
        $is_html = (isset($mail_data['content_type']) && stripos($mail_data['content_type'], 'text/html') !== false)
            || (stripos($message, '<html') !== false)
            || (stripos($message, '<p') !== false)
            || (stripos($message, '<br') !== false);

        $body = [
            'sender'      => ['email' => $from_email, 'name' => $from_name],
            'to'          => $to_recipients,
            'subject'     => $mail_data['subject'] ?? '',
        ];

        if ($is_html) {
            $body['htmlContent'] = $message;
            $body['textContent'] = wp_strip_all_tags($message);
        } else {
            $body['textContent'] = $message;
        }

        // CC & BCC
        if (!empty($mail_data['cc'])) {
            $cc = [];
            foreach ((array)$mail_data['cc'] as $item) {
                if (is_email(trim($item))) $cc[] = ['email' => trim($item)];
            }
            if ($cc) $body['cc'] = $cc;
        }
        if (!empty($mail_data['bcc'])) {
            $bcc = [];
            foreach ((array)$mail_data['bcc'] as $item) {
                if (is_email(trim($item))) $bcc[] = ['email' => trim($item)];
            }
            if ($bcc) $body['bcc'] = $bcc;
        }

        // Reply-To
        if (!empty($mail_data['reply_to'])) {
            $reply_first = is_array($mail_data['reply_to']) ? reset($mail_data['reply_to']) : $mail_data['reply_to'];
            if (is_array($reply_first) && !empty($reply_first['email'])) {
                $body['replyTo'] = ['email' => $reply_first['email'], 'name' => $reply_first['name'] ?? ''];
            } elseif (is_string($reply_first) && is_email($reply_first)) {
                $body['replyTo'] = ['email' => $reply_first];
            }
        }

        // Attachments
        if (!empty($mail_data['attachments'])) {
            $attachments = [];
            foreach ((array)$mail_data['attachments'] as $file) {
                if (is_string($file) && file_exists($file)) {
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $attachments[] = [
                            'name'    => basename($file),
                            'content' => base64_encode($content),
                        ];
                    }
                }
            }
            if ($attachments) $body['attachment'] = $attachments;
        }

        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
            'timeout' => 20,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'debug'   => 'Brevo WP Error: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $res_body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'message' => __('Email successfully delivered via Brevo API.', 'mail-smtp-by-oi'),
                'debug'   => "HTTP Code: $code\nResponse: " . $res_body,
            ];
        }

        return [
            'success' => false,
            'message' => __('Brevo returned an error.', 'mail-smtp-by-oi') . ' Code: ' . $code,
            'debug'   => "HTTP Code: $code\nBody: " . $res_body,
        ];
    }
}
