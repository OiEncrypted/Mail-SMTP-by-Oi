<?php
/**
 * Server Diagnostics, Port Connectivity & DNS Checks
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Diagnostics {

    /**
     * Test socket connection to host and port
     *
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @return array
     */
    public static function test_port($host = '', $port = 587, $timeout = 5) {
        if (empty($host)) {
            $host = 'smtp.gmail.com';
        }

        $clean_host = sanitize_text_field($host);
        $clean_port = absint($port);

        $start = microtime(true);
        $errno = 0;
        $errstr = '';

        if (!function_exists('fsockopen')) {
            return [
                'status'  => 'error',
                'message' => __('fsockopen() function is disabled on this server. Contact hosting provider.', 'mail-smtp-by-oi'),
                'time_ms' => 0,
            ];
        }

        $connection = @fsockopen($clean_host, $clean_port, $errno, $errstr, $timeout);
        $elapsed = round((microtime(true) - $start) * 1000, 2);

        if (is_resource($connection)) {
            fclose($connection);
            return [
                'status'  => 'open',
                'message' => sprintf(__('Port %d to %s is OPEN and reachable (%d ms).', 'mail-smtp-by-oi'), $clean_port, esc_html($clean_host), $elapsed),
                'time_ms' => $elapsed,
            ];
        }

        return [
            'status'  => 'closed',
            'message' => sprintf(__('Port %d to %s is BLOCKED or unreachable (%s). Error #%d', 'mail-smtp-by-oi'), $clean_port, esc_html($clean_host), esc_html($errstr), $errno),
            'time_ms' => $elapsed,
        ];
    }

    /**
     * Check common SMTP ports
     *
     * @param string $host
     * @return array
     */
    public static function test_all_ports($host = 'smtp.gmail.com') {
        $ports = [
            587  => 'TLS / STARTTLS (Recommended)',
            465  => 'SSL',
            2525 => 'Alternative Port',
            25   => 'Unencrypted / Often Blocked by Hosts',
        ];

        $results = [];
        foreach ($ports as $port => $desc) {
            $test = self::test_port($host, $port, 3);
            $results[] = [
                'port'        => $port,
                'description' => $desc,
                'status'      => $test['status'],
                'message'     => $test['message'],
                'time_ms'     => $test['time_ms'],
            ];
        }

        return $results;
    }

    /**
     * Check DNS records (SPF, DMARC) for a domain
     *
     * @param string $domain
     * @return array
     */
    public static function check_dns($domain = '') {
        if (empty($domain)) {
            $from_email = OI_Options::get('from_email', get_option('admin_email'));
            $parts = explode('@', $from_email);
            $domain = end($parts);
        }

        $domain = sanitize_text_field(trim($domain));
        if (empty($domain) || !strpos($domain, '.')) {
            return [
                'domain' => $domain,
                'spf'    => ['found' => false, 'record' => '', 'status' => 'warning', 'tip' => __('Invalid domain format.', 'mail-smtp-by-oi')],
                'dmarc'  => ['found' => false, 'record' => '', 'status' => 'warning', 'tip' => __('Invalid domain format.', 'mail-smtp-by-oi')],
            ];
        }

        $spf_result = ['found' => false, 'record' => '', 'status' => 'warning', 'tip' => ''];
        $dkim_result = ['found' => false, 'record' => '', 'selector' => '', 'status' => 'warning', 'tip' => ''];
        $dmarc_result = ['found' => false, 'record' => '', 'status' => 'warning', 'tip' => ''];

        if (function_exists('dns_get_record')) {
            // Check SPF
            $txt_records = @dns_get_record($domain, DNS_TXT);
            if (is_array($txt_records)) {
                foreach ($txt_records as $rec) {
                    $txt = $rec['txt'] ?? ($rec['entries'][0] ?? '');
                    if (stripos($txt, 'v=spf1') !== false) {
                        $spf_result['found'] = true;
                        $spf_result['record'] = $txt;
                        $spf_result['status'] = 'pass';
                        $spf_result['tip'] = __('Valid SPF record detected.', 'mail-smtp-by-oi');
                        break;
                    }
                }
            }

            if (!$spf_result['found']) {
                $spf_result['tip'] = sprintf(__('No SPF record found for %s. Adding an SPF TXT record improves email deliverability and prevents spoofing.', 'mail-smtp-by-oi'), esc_html($domain));
            }

            // Check DKIM across common selectors
            $common_selectors = ['default', 'google', 'k1', 'mail', 's1', 'smtp', 'mandrill', 'resend', 'postmark', 'brevo', 'ses'];
            foreach ($common_selectors as $sel) {
                $dkim_host = $sel . '._domainkey.' . $domain;
                $dkim_records = @dns_get_record($dkim_host, DNS_TXT);
                if (is_array($dkim_records)) {
                    foreach ($dkim_records as $rec) {
                        $txt = $rec['txt'] ?? ($rec['entries'][0] ?? '');
                        if (stripos($txt, 'v=DKIM1') !== false || stripos($txt, 'k=rsa') !== false || stripos($txt, 'p=') !== false) {
                            $dkim_result['found'] = true;
                            $dkim_result['record'] = $txt;
                            $dkim_result['selector'] = $sel;
                            $dkim_result['status'] = 'pass';
                            $dkim_result['tip'] = sprintf(__('Valid DKIM record detected on selector "%s".', 'mail-smtp-by-oi'), esc_html($sel));
                            break 2;
                        }
                    }
                }
            }

            if (!$dkim_result['found']) {
                $dkim_result['tip'] = sprintf(__('No common DKIM selector detected for %s. If your mail provider uses a custom selector, verify it in their dashboard.', 'mail-smtp-by-oi'), esc_html($domain));
            }

            // Check DMARC
            $dmarc_records = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
            if (is_array($dmarc_records)) {
                foreach ($dmarc_records as $rec) {
                    $txt = $rec['txt'] ?? ($rec['entries'][0] ?? '');
                    if (stripos($txt, 'v=DMARC1') !== false) {
                        $dmarc_result['found'] = true;
                        $dmarc_result['record'] = $txt;
                        $dmarc_result['status'] = 'pass';
                        $dmarc_result['tip'] = __('Valid DMARC record detected.', 'mail-smtp-by-oi');
                        break;
                    }
                }
            }

            if (!$dmarc_result['found']) {
                $dmarc_result['tip'] = sprintf(__('No DMARC record found at _dmarc.%s. Major providers like Google & Yahoo require DMARC.', 'mail-smtp-by-oi'), esc_html($domain));
            }
        } else {
            $spf_result['tip'] = __('dns_get_record() is disabled on this server.', 'mail-smtp-by-oi');
            $dkim_result['tip'] = __('dns_get_record() is disabled on this server.', 'mail-smtp-by-oi');
            $dmarc_result['tip'] = __('dns_get_record() is disabled on this server.', 'mail-smtp-by-oi');
        }

        return [
            'domain' => $domain,
            'spf'    => $spf_result,
            'dkim'   => $dkim_result,
            'dmarc'  => $dmarc_result,
        ];
    }

    /**
     * Get server environment details
     *
     * @return array
     */
    public static function get_environment_info() {
        global $wp_version;

        return [
            'php_version'       => PHP_VERSION,
            'php_compatible'    => version_compare(PHP_VERSION, '7.4', '>='),
            'wp_version'        => $wp_version,
            'server_software'   => sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'),
            'openssl_installed' => extension_loaded('openssl'),
            'curl_installed'    => extension_loaded('curl'),
            'sockets_enabled'   => function_exists('fsockopen'),
            'memory_limit'      => ini_get('memory_limit'),
            'max_execution_time'=> ini_get('max_execution_time') . 's',
        ];
    }
}
