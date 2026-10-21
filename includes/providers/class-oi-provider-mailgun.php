<?php
/**
 * Mailgun API Provider
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_Mailgun implements OI_Provider_Interface {

    public function get_id() {
        return 'mailgun';
    }

    public function get_name() {
        return 'Mailgun';
    }

    public function send(array $mail_data, array $settings) {
        $api_key = $settings['mailgun_api_key'] ?? '';
        $domain  = $settings['mailgun_domain'] ?? '';
        $region  = $settings['mailgun_region'] ?? 'us';

        if (empty($api_key) || empty($domain)) {
            return [
                'success' => false,
                'message' => __('Mailgun API Key or Domain is missing. Please check plugin settings.', 'mail-smtp-by-oi'),
                'debug'   => 'Mailgun error: Missing API Key or Domain.',
            ];
        }

        $base_url = ($region === 'eu') ? 'https://api.eu.mailgun.net/v3/' : 'https://api.mailgun.net/v3/';
        $endpoint = $base_url . rawurlencode($domain) . '/messages';

        $from_email = !empty($mail_data['from_email']) ? $mail_data['from_email'] : ($settings['from_email'] ?? get_option('admin_email'));
        $from_name  = !empty($mail_data['from_name']) ? $mail_data['from_name'] : ($settings['from_name'] ?? get_option('blogname'));

        $to_list = is_array($mail_data['to']) ? $mail_data['to'] : explode(',', (string)$mail_data['to']);
        $valid_to = [];
        foreach ($to_list as $to_item) {
            $clean_to = trim($to_item);
            if (is_email($clean_to)) {
                $valid_to[] = $clean_to;
            }
        }

        if (empty($valid_to)) {
            return [
                'success' => false,
                'message' => __('No valid recipient email address provided.', 'mail-smtp-by-oi'),
                'debug'   => 'Mailgun error: Recipient list is empty.',
            ];
        }

        $message = $mail_data['message'] ?? '';
        $is_html = (isset($mail_data['content_type']) && stripos($mail_data['content_type'], 'text/html') !== false)
            || (stripos($message, '<html') !== false)
            || (stripos($message, '<p') !== false)
            || (stripos($message, '<br') !== false);

        $body = [
            'from'    => !empty($from_name) ? "$from_name <$from_email>" : $from_email,
            'to'      => implode(', ', $valid_to),
            'subject' => $mail_data['subject'] ?? '',
        ];

        if ($is_html) {
            $body['html'] = $message;
            $body['text'] = wp_strip_all_tags($message);
        } else {
            $body['text'] = $message;
        }

        if (!empty($mail_data['cc'])) {
            $body['cc'] = implode(', ', (array)$mail_data['cc']);
        }
        if (!empty($mail_data['bcc'])) {
            $body['bcc'] = implode(', ', (array)$mail_data['bcc']);
        }

        if (!empty($mail_data['reply_to'])) {
            $reply_first = is_array($mail_data['reply_to']) ? reset($mail_data['reply_to']) : $mail_data['reply_to'];
            if (is_array($reply_first) && !empty($reply_first['email'])) {
                $body['h:Reply-To'] = $reply_first['email'];
            } elseif (is_string($reply_first) && is_email($reply_first)) {
                $body['h:Reply-To'] = $reply_first;
            }
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('api:' . $api_key),
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'debug'   => 'Mailgun WP Error: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $res_body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'message' => __('Email successfully delivered via Mailgun API.', 'mail-smtp-by-oi'),
                'debug'   => "HTTP Code: $code\nResponse: " . $res_body,
            ];
        }

        return [
            'success' => false,
            'message' => __('Mailgun returned an error.', 'mail-smtp-by-oi') . ' Code: ' . $code,
            'debug'   => "HTTP Code: $code\nBody: " . $res_body,
        ];
    }
}
