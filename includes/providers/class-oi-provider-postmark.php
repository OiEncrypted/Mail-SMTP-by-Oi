<?php
/**
 * Postmark API Provider
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_Postmark implements OI_Provider_Interface {

    public function get_id() {
        return 'postmark';
    }

    public function get_name() {
        return 'Postmark';
    }

    public function send(array $mail_data, array $settings) {
        $api_key = $settings['api_key'] ?? '';
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Postmark Server API Token is missing.', 'mail-smtp-by-oi'),
                'debug'   => 'Postmark error: Missing API Key.',
            ];
        }

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
                'debug'   => 'Postmark error: Recipient list is empty.',
            ];
        }

        $message = $mail_data['message'] ?? '';
        $is_html = (isset($mail_data['content_type']) && stripos($mail_data['content_type'], 'text/html') !== false)
            || (stripos($message, '<html') !== false)
            || (stripos($message, '<p') !== false)
            || (stripos($message, '<br') !== false);

        $body = [
            'From'     => !empty($from_name) ? "$from_name <$from_email>" : $from_email,
            'To'       => implode(', ', $valid_to),
            'Subject'  => $mail_data['subject'] ?? '',
        ];

        if ($is_html) {
            $body['HtmlBody'] = $message;
            $body['TextBody'] = wp_strip_all_tags($message);
        } else {
            $body['TextBody'] = $message;
        }

        if (!empty($mail_data['cc'])) {
            $body['Cc'] = implode(', ', (array)$mail_data['cc']);
        }
        if (!empty($mail_data['bcc'])) {
            $body['Bcc'] = implode(', ', (array)$mail_data['bcc']);
        }

        if (!empty($mail_data['reply_to'])) {
            $reply_first = is_array($mail_data['reply_to']) ? reset($mail_data['reply_to']) : $mail_data['reply_to'];
            if (is_array($reply_first) && !empty($reply_first['email'])) {
                $body['ReplyTo'] = $reply_first['email'];
            } elseif (is_string($reply_first) && is_email($reply_first)) {
                $body['ReplyTo'] = $reply_first;
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
                            'Name'        => basename($file),
                            'Content'     => base64_encode($content),
                            'ContentType' => mime_content_type($file) ?: 'application/octet-stream',
                        ];
                    }
                }
            }
            if ($attachments) $body['Attachments'] = $attachments;
        }

        $response = wp_remote_post('https://api.postmarkapp.com/email', [
            'timeout' => 20,
            'headers' => [
                'X-Postmark-Server-Token' => $api_key,
                'Content-Type'            => 'application/json',
                'Accept'                  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'debug'   => 'Postmark WP Error: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $res_body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'message' => __('Email successfully delivered via Postmark API.', 'mail-smtp-by-oi'),
                'debug'   => "HTTP Code: $code\nResponse: " . $res_body,
            ];
        }

        return [
            'success' => false,
            'message' => __('Postmark returned an error.', 'mail-smtp-by-oi') . ' Code: ' . $code,
            'debug'   => "HTTP Code: $code\nBody: " . $res_body,
        ];
    }
}
