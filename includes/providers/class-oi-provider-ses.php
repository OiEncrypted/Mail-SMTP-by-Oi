<?php
/**
 * Amazon SES (Simple Email Service) API v2 Provider
 *
 * Direct AWS Signature Version 4 implementation without heavy SDKs.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Provider_SES implements OI_Provider_Interface {

    /**
     * Get Provider Identifier
     *
     * @return string
     */
    public function get_id() {
        return 'ses';
    }

    /**
     * Get Provider Name
     *
     * @return string
     */
    public function get_name() {
        return 'Amazon SES';
    }

    /**
     * Send email via Amazon SES v2 REST API
     *
     * @param array $mail_data
     * @param array $settings
     * @return array
     */
    public function send(array $mail_data, array $settings) {
        $access_key = trim($settings['ses_access_key'] ?? '');
        $secret_key = trim($settings['ses_secret_key'] ?? '');
        $region     = sanitize_text_field($settings['ses_region'] ?? 'us-east-1');

        if (empty($access_key) || empty($secret_key)) {
            return [
                'success' => false,
                'message' => __('Amazon SES Access Key or Secret Key is missing in plugin settings.', 'mail-smtp-by-oi'),
                'debug'   => 'Amazon SES error: Missing AWS credentials.',
            ];
        }

        $from_email = !empty($mail_data['from_email']) ? $mail_data['from_email'] : ($settings['from_email'] ?? get_option('admin_email'));
        $from_name  = !empty($mail_data['from_name']) ? $mail_data['from_name'] : ($settings['from_name'] ?? get_option('blogname'));

        // Recipients
        $to_list = is_array($mail_data['to']) ? $mail_data['to'] : explode(',', (string)$mail_data['to']);
        $to_recipients = [];
        foreach ($to_list as $to_item) {
            $clean = trim($to_item);
            if (is_email($clean)) {
                $to_recipients[] = $clean;
            }
        }

        if (empty($to_recipients)) {
            return [
                'success' => false,
                'message' => __('No valid recipient email address found.', 'mail-smtp-by-oi'),
                'debug'   => 'Amazon SES error: Recipient array is empty.',
            ];
        }

        $cc_recipients = [];
        if (!empty($mail_data['cc'])) {
            foreach ((array)$mail_data['cc'] as $item) {
                if (is_email(trim($item))) {
                    $cc_recipients[] = trim($item);
                }
            }
        }

        $bcc_recipients = [];
        if (!empty($mail_data['bcc'])) {
            foreach ((array)$mail_data['bcc'] as $item) {
                if (is_email(trim($item))) {
                    $bcc_recipients[] = trim($item);
                }
            }
        }

        $message = $mail_data['message'] ?? '';
        $is_html = (isset($mail_data['content_type']) && stripos($mail_data['content_type'], 'text/html') !== false)
            || (stripos($message, '<html') !== false)
            || (stripos($message, '<p') !== false)
            || (stripos($message, '<br') !== false);

        $sender_formatted = !empty($from_name) ? sprintf('"%s" <%s>', str_replace('"', '\"', $from_name), $from_email) : $from_email;

        // Build Payload
        $has_attachments = !empty($mail_data['attachments']);

        if ($has_attachments) {
            // Build raw MIME message if attachments exist
            $raw_mime = $this->build_raw_mime($mail_data, $from_email, $from_name, $to_recipients, $is_html);
            $payload = [
                'Content' => [
                    'Raw' => [
                        'Data' => base64_encode($raw_mime),
                    ],
                ],
                'Destination' => [
                    'ToAddresses' => $to_recipients,
                ],
            ];
            if (!empty($cc_recipients)) {
                $payload['Destination']['CcAddresses'] = $cc_recipients;
            }
            if (!empty($bcc_recipients)) {
                $payload['Destination']['BccAddresses'] = $bcc_recipients;
            }
        } else {
            // Standard Simple Email
            $body_content = [];
            if ($is_html) {
                $body_content['Html'] = [
                    'Data'    => $message,
                    'Charset' => 'UTF-8',
                ];
                $body_content['Text'] = [
                    'Data'    => wp_strip_all_tags($message),
                    'Charset' => 'UTF-8',
                ];
            } else {
                $body_content['Text'] = [
                    'Data'    => $message,
                    'Charset' => 'UTF-8',
                ];
            }

            $payload = [
                'FromEmailAddress' => $sender_formatted,
                'Destination' => [
                    'ToAddresses' => $to_recipients,
                ],
                'Content' => [
                    'Simple' => [
                        'Subject' => [
                            'Data'    => $mail_data['subject'] ?? '',
                            'Charset' => 'UTF-8',
                        ],
                        'Body' => $body_content,
                    ],
                ],
            ];

            if (!empty($cc_recipients)) {
                $payload['Destination']['CcAddresses'] = $cc_recipients;
            }
            if (!empty($bcc_recipients)) {
                $payload['Destination']['BccAddresses'] = $bcc_recipients;
            }
            if (!empty($mail_data['reply_to'])) {
                $reply_to = is_array($mail_data['reply_to']) ? $mail_data['reply_to'] : explode(',', (string)$mail_data['reply_to']);
                $clean_reply = array_values(array_filter(array_map('trim', $reply_to), 'is_email'));
                if (!empty($clean_reply)) {
                    $payload['ReplyToAddresses'] = $clean_reply;
                }
            }
        }

        $payload_json = wp_json_encode($payload);
        $endpoint     = "https://email.{$region}.amazonaws.com/v2/email/outbound-emails";
        $host         = "email.{$region}.amazonaws.com";

        // Generate SigV4 Headers
        $headers = $this->generate_sigv4_headers($payload_json, $access_key, $secret_key, $region, $host);

        $start_time = microtime(true);
        $response = wp_remote_post($endpoint, [
            'timeout'     => 25,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => $headers,
            'body'        => $payload_json,
            'sslverify'   => true,
        ]);
        $duration = round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'debug'   => sprintf("Amazon SES Connection Error (%d ms): %s", $duration, $response->get_error_message()),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $res_data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            $msg_id = $res_data['MessageId'] ?? 'N/A';
            return [
                'success' => true,
                'message' => sprintf(__('Email delivered via Amazon SES (Message ID: %s)', 'mail-smtp-by-oi'), $msg_id),
                'debug'   => sprintf("Amazon SES Success (%d ms, HTTP %d)\nMessageId: %s\nRegion: %s", $duration, $code, $msg_id, $region),
            ];
        }

        $error_type = $res_data['type'] ?? ($res_data['__type'] ?? 'SES_Error');
        $error_msg  = $res_data['message'] ?? ($res_data['Message'] ?? __('Unknown Amazon SES API error', 'mail-smtp-by-oi'));

        return [
            'success' => false,
            'message' => sprintf(__('Amazon SES Error (%s): %s', 'mail-smtp-by-oi'), esc_html($error_type), esc_html($error_msg)),
            'debug'   => sprintf("Amazon SES Failed (%d ms, HTTP %d)\nError Type: %s\nMessage: %s\nPayload: %s", $duration, $code, $error_type, $error_msg, $body),
        ];
    }

    /**
     * Generate AWS Signature Version 4 Headers
     *
     * @param string $payload
     * @param string $access_key
     * @param string $secret_key
     * @param string $region
     * @param string $host
     * @return array
     */
    private function generate_sigv4_headers($payload, $access_key, $secret_key, $region, $host) {
        $timestamp = time();
        $date_stamp = gmdate('Ymd', $timestamp);
        $amz_date   = gmdate('Ymd\THis\Z', $timestamp);

        $service = 'ses';
        $canonical_uri = '/v2/email/outbound-emails';
        $canonical_querystring = '';

        $hashed_payload = hash('sha256', $payload);

        // Canonical headers (must be lowercase and trimmed)
        $canonical_headers = "content-type:application/json\n" .
                             "host:{$host}\n" .
                             "x-amz-date:{$amz_date}\n";
        $signed_headers = 'content-type;host;x-amz-date';

        $canonical_request = "POST\n" .
                             $canonical_uri . "\n" .
                             $canonical_querystring . "\n" .
                             $canonical_headers . "\n" .
                             $signed_headers . "\n" .
                             $hashed_payload;

        $credential_scope = "{$date_stamp}/{$region}/{$service}/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n" .
                          $amz_date . "\n" .
                          $credential_scope . "\n" .
                          hash('sha256', $canonical_request);

        // Derive signing key
        $k_date    = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region  = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', $service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);

        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        $auth_header = "AWS4-HMAC-SHA256 Credential={$access_key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

        return [
            'Content-Type'  => 'application/json',
            'Host'          => $host,
            'X-Amz-Date'    => $amz_date,
            'Authorization' => $auth_header,
        ];
    }

    /**
     * Build RFC 2822 multipart MIME message when attachments are present
     *
     * @param array $mail_data
     * @param string $from_email
     * @param string $from_name
     * @param array $to_recipients
     * @param bool $is_html
     * @return string
     */
    private function build_raw_mime(array $mail_data, $from_email, $from_name, array $to_recipients, $is_html) {
        $boundary = '=_oi_' . md5(microtime(true) . wp_rand());
        $alt_boundary = '=_alt_' . md5(microtime(true) . wp_rand());

        $sender_header = !empty($from_name) ? sprintf('"%s" <%s>', addcslashes($from_name, '"'), $from_email) : $from_email;

        $headers = [];
        $headers[] = "From: {$sender_header}";
        $headers[] = "To: " . implode(', ', $to_recipients);
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($mail_data['subject'] ?? '') . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        $mime = implode("\r\n", $headers) . "\r\n\r\n";
        $mime .= "--{$boundary}\r\n";
        $mime .= "Content-Type: multipart/alternative; boundary=\"{$alt_boundary}\"\r\n\r\n";

        $message = $mail_data['message'] ?? '';

        // Plain text part
        $mime .= "--{$alt_boundary}\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode(wp_strip_all_tags($message))) . "\r\n";

        // HTML part if present
        if ($is_html) {
            $mime .= "--{$alt_boundary}\r\n";
            $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($message)) . "\r\n";
        }
        $mime .= "--{$alt_boundary}--\r\n";

        // Attachments
        if (!empty($mail_data['attachments'])) {
            foreach ((array)$mail_data['attachments'] as $att_path) {
                if (is_string($att_path) && file_exists($att_path)) {
                    $filename = basename($att_path);
                    $file_data = file_get_contents($att_path);
                    $mime_type = function_exists('mime_content_type') ? mime_content_type($att_path) : 'application/octet-stream';

                    $mime .= "--{$boundary}\r\n";
                    $mime .= "Content-Type: {$mime_type}; name=\"{$filename}\"\r\n";
                    $mime .= "Content-Transfer-Encoding: base64\r\n";
                    $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
                    $mime .= chunk_split(base64_encode($file_data)) . "\r\n";
                }
            }
        }

        $mime .= "--{$boundary}--\r\n";
        return $mime;
    }
}
