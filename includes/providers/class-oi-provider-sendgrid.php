<?php
/**
 * SendGrid Web API v3 Provider
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_SendGrid implements OI_Provider_Interface {

    public function get_id() {
        return 'sendgrid';
    }

    public function get_name() {
        return 'SendGrid';
    }

    public function send(array $mail_data, array $settings) {
        $api_key = !empty($settings['sendgrid_api_key']) ? $settings['sendgrid_api_key'] : ($settings['api_key'] ?? '');
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('SendGrid API Key is missing. Please check plugin settings.', 'mail-smtp-by-oi'),
                'debug'   => 'SendGrid error: Missing API Key.',
            ];
        }

        $from_email = !empty($mail_data['from_email']) ? $mail_data['from_email'] : ($settings['from_email'] ?? get_option('admin_email'));
        $from_name  = !empty($mail_data['from_name']) ? $mail_data['from_name'] : ($settings['from_name'] ?? get_option('blogname'));

        // Prepare To recipients
        $to_list = is_array($mail_data['to']) ? $mail_data['to'] : explode(',', (string)$mail_data['to']);
        $personalizations = [];
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
                'debug'   => 'SendGrid error: Recipient list is empty.',
            ];
        }

        $personalization_item = ['to' => $to_recipients];

        if (!empty($mail_data['cc'])) {
            $cc_recipients = [];
            foreach ((array)$mail_data['cc'] as $cc_item) {
                if (is_email(trim($cc_item))) {
                    $cc_recipients[] = ['email' => trim($cc_item)];
                }
            }
            if (!empty($cc_recipients)) {
                $personalization_item['cc'] = $cc_recipients;
            }
        }

        if (!empty($mail_data['bcc'])) {
            $bcc_recipients = [];
            foreach ((array)$mail_data['bcc'] as $bcc_item) {
                if (is_email(trim($bcc_item))) {
                    $bcc_recipients[] = ['email' => trim($bcc_item)];
                }
            }
            if (!empty($bcc_recipients)) {
                $personalization_item['bcc'] = $bcc_recipients;
            }
        }

        $personalizations[] = $personalization_item;

        $message = $mail_data['message'] ?? '';
        $is_html = (isset($mail_data['content_type']) && stripos($mail_data['content_type'], 'text/html') !== false)
            || (stripos($message, '<html') !== false)
            || (stripos($message, '<p') !== false)
            || (stripos($message, '<br') !== false);

        $body = [
            'personalizations' => $personalizations,
            'from' => [
                'email' => $from_email,
                'name'  => $from_name,
            ],
            'subject' => $mail_data['subject'] ?? '',
            'content' => [
                [
                    'type'  => $is_html ? 'text/html' : 'text/plain',
                    'value' => $message,
                ],
            ],
        ];

        // Reply-To
        if (!empty($mail_data['reply_to'])) {
            $reply_first = is_array($mail_data['reply_to']) ? reset($mail_data['reply_to']) : $mail_data['reply_to'];
            if (is_array($reply_first) && !empty($reply_first['email'])) {
                $body['reply_to'] = ['email' => $reply_first['email'], 'name' => $reply_first['name'] ?? ''];
            } elseif (is_string($reply_first) && is_email($reply_first)) {
                $body['reply_to'] = ['email' => $reply_first];
            }
        }

        // Attachments
        if (!empty($mail_data['attachments'])) {
            $sendgrid_attachments = [];
            foreach ((array)$mail_data['attachments'] as $file) {
                if (is_string($file) && file_exists($file)) {
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $sendgrid_attachments[] = [
                            'content'     => base64_encode($content),
                            'type'        => mime_content_type($file) ?: 'application/octet-stream',
                            'filename'    => basename($file),
                            'disposition' => 'attachment',
                        ];
                    }
                }
            }
            if (!empty($sendgrid_attachments)) {
                $body['attachments'] = $sendgrid_attachments;
            }
        }

        $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'debug'   => 'SendGrid WP Error: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $res_body = wp_remote_retrieve_body($response);

        // SendGrid returns 202 Accepted on success
        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'message' => __('Email successfully accepted by SendGrid API.', 'mail-smtp-by-oi'),
                'debug'   => "HTTP Code: $code\nResponse: " . ($res_body ?: 'Accepted'),
            ];
        }

        return [
            'success' => false,
            'message' => __('SendGrid returned an error.', 'mail-smtp-by-oi') . ' Code: ' . $code,
            'debug'   => "HTTP Code: $code\nBody: " . $res_body,
        ];
    }
}
