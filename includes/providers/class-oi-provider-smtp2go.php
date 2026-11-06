<?php
/**
 * SMTP2GO REST API Provider
 *
 * Ultra-reliable, lightning-fast cloud delivery via SMTP2GO API v3.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_SMTP2GO implements OI_Provider_Interface {

    public function get_id() {
        return 'smtp2go';
    }

    public function get_name() {
        return 'SMTP2GO';
    }

    public function send(array $mail_data, array $settings) {
        $api_key = !empty($settings['smtp2go_api_key']) ? $settings['smtp2go_api_key'] : ($settings['api_key'] ?? '');

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('SMTP2GO API Key is missing. Please check plugin settings.', 'mail-smtp-by-oi'),
                'debug'   => 'SMTP2GO error: Missing API Key.',
            ];
        }

        $from_email = !empty($mail_data['from_email']) ? $mail_data['from_email'] : ($settings['from_email'] ?? get_option('admin_email'));
        $from_name  = !empty($mail_data['from_name']) ? $mail_data['from_name'] : ($settings['from_name'] ?? get_option('blogname'));
        $sender     = !empty($from_name) ? "{$from_name} <{$from_email}>" : $from_email;

        // Prepare To recipients
        $to_list = is_array($mail_data['to']) ? $mail_data['to'] : explode(',', (string)$mail_data['to']);
        $to_recipients = [];

        foreach ($to_list as $to_item) {
            $clean_to = trim($to_item);
            if (!empty($clean_to)) {
                $to_recipients[] = $clean_to;
            }
        }

        if (empty($to_recipients)) {
            return [
                'success' => false,
                'message' => __('No valid recipient email address provided.', 'mail-smtp-by-oi'),
                'debug'   => 'SMTP2GO error: Recipient list is empty.',
            ];
        }

        $payload = [
            'api_key' => $api_key,
            'sender'  => $sender,
            'to'      => $to_recipients,
            'subject' => $mail_data['subject'] ?? '',
        ];

        // Body format
        $body = $mail_data['message'] ?? '';
        $is_html = true;
        if (!empty($mail_data['headers'])) {
            $headers_str = is_array($mail_data['headers']) ? implode("\n", $mail_data['headers']) : $mail_data['headers'];
            if (stripos($headers_str, 'text/plain') !== false && stripos($headers_str, 'text/html') === false) {
                $is_html = false;
            }
        }

        if ($is_html) {
            $payload['html_body'] = $body;
            $payload['text_body'] = wp_strip_all_tags($body);
        } else {
            $payload['text_body'] = $body;
        }

        // Custom headers & CC/BCC
        $custom_headers = [];
        if (!empty($mail_data['headers'])) {
            $headers_array = is_array($mail_data['headers']) ? $mail_data['headers'] : explode("\n", (string)$mail_data['headers']);
            foreach ($headers_array as $header_line) {
                if (empty(trim($header_line))) continue;
                $parts = explode(':', $header_line, 2);
                if (count($parts) === 2) {
                    $header_name = trim($parts[0]);
                    $header_val  = trim($parts[1]);
                    if (strcasecmp($header_name, 'cc') === 0) {
                        $payload['cc'] = array_map('trim', explode(',', $header_val));
                    } elseif (strcasecmp($header_name, 'bcc') === 0) {
                        $payload['bcc'] = array_map('trim', explode(',', $header_val));
                    } elseif (strcasecmp($header_name, 'reply-to') === 0) {
                        $custom_headers[] = ['header' => 'Reply-To', 'value' => $header_val];
                    }
                }
            }
        }

        if (!empty($custom_headers)) {
            $payload['custom_headers'] = $custom_headers;
        }

        // Attachments
        if (!empty($mail_data['attachments'])) {
            $attachments_payload = [];
            $attachments = is_array($mail_data['attachments']) ? $mail_data['attachments'] : [$mail_data['attachments']];
            foreach ($attachments as $file_path) {
                if (is_string($file_path) && file_exists($file_path) && is_readable($file_path)) {
                    $attachments_payload[] = [
                        'filename' => basename($file_path),
                        'fileblob' => base64_encode(file_get_contents($file_path)),
                        'mimetype' => mime_content_type($file_path) ?: 'application/octet-stream',
                    ];
                }
            }
            if (!empty($attachments_payload)) {
                $payload['attachments'] = $attachments_payload;
            }
        }

        $response = wp_remote_post('https://api.smtp2go.com/v3/email/send', [
            'timeout'     => 20,
            'httpversion' => '1.1',
            'headers'     => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'WordPress/Mail-SMTP-by-Oi',
            ],
            'body'        => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'debug'   => 'HTTP Connection failed: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $res_data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && !empty($res_data['data']['succeeded'])) {
            return [
                'success' => true,
                'message' => __('Email successfully dispatched via SMTP2GO API.', 'mail-smtp-by-oi'),
                'debug'   => "HTTP $code - SMTP2GO email accepted. Message ID: " . ($res_data['data']['email_id'] ?? 'unknown'),
            ];
        }

        $error_desc = !empty($res_data['data']['failures'][0]) ? $res_data['data']['failures'][0] : ($res_data['data']['error'] ?? 'Unknown SMTP2GO error');

        return [
            'success' => false,
            'message' => sprintf(__('SMTP2GO delivery failed: %s', 'mail-smtp-by-oi'), $error_desc),
            'debug'   => "HTTP $code - Response: $body",
        ];
    }
}
