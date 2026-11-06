<?php
/**
 * Weekly Executive Email Summary Digest Engine
 *
 * Sends a weekly performance report to the site administrator summarizing
 * email volume, delivery rate, failures, and engagement metrics.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Digest {

    /**
     * Initialize weekly digest cron and hooks
     */
    public static function init() {
        add_action('oi_mail_smtp_weekly_digest_cron', [__CLASS__, 'send_scheduled_digest']);
        add_filter('cron_schedules', [__CLASS__, 'add_weekly_schedule']);

        if (!wp_next_scheduled('oi_mail_smtp_weekly_digest_cron')) {
            // Schedule for next Monday at 09:00 AM
            $next_monday = strtotime('next monday 09:00:00');
            wp_schedule_event($next_monday, 'weekly', 'oi_mail_smtp_weekly_digest_cron');
        }
    }

    /**
     * Add weekly interval to cron schedules
     *
     * @param array $schedules
     * @return array
     */
    public static function add_weekly_schedule($schedules) {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', 'mail-smtp-by-oi'),
            ];
        }
        return $schedules;
    }

    /**
     * Send scheduled weekly digest
     */
    public static function send_scheduled_digest() {
        if (!OI_Options::get('enable_weekly_digest', true)) {
            return;
        }
        self::send_digest(false);
    }

    /**
     * Compile metrics and dispatch executive email report
     *
     * @param bool $is_test
     * @param string $override_email
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send_digest($is_test = false, $override_email = '') {
        $settings = OI_Options::get_all(false);
        $to = !empty($override_email) ? $override_email : (!empty($settings['digest_recipient_email']) ? $settings['digest_recipient_email'] : get_option('admin_email'));

        if (!is_email($to)) {
            return ['success' => false, 'message' => __('Invalid recipient email address for digest.', 'mail-smtp-by-oi')];
        }

        global $wpdb;
        $table = OI_Logger::get_table_name();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['success' => false, 'message' => __('Log table not found.', 'mail-smtp-by-oi')];
        }

        // Calculate metrics for past 7 days
        $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $sent   = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'sent' AND created_at >= %s", $start_date));
        $failed = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'failed' AND created_at >= %s", $start_date));
        $opens  = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(open_count) FROM $table WHERE created_at >= %s", $start_date));
        $clicks = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(click_count) FROM $table WHERE created_at >= %s", $start_date));

        $total = $sent + $failed;
        $rate  = $total > 0 ? round(($sent / $total) * 100, 1) : 100.0;

        $site_name = get_option('blogname', 'WordPress Site');
        $site_url  = home_url();

        $subject = sprintf(
            __('[%s] Weekly Email Delivery Report: %s%% Success', 'mail-smtp-by-oi'),
            $site_name,
            $rate
        );

        $html = self::build_digest_html([
            'site_name'   => $site_name,
            'site_url'    => $site_url,
            'total'       => $total,
            'sent'        => $sent,
            'failed'      => $failed,
            'rate'        => $rate,
            'opens'       => $opens,
            'clicks'      => $clicks,
            'is_test'     => $is_test,
            'logs_url'    => admin_url('admin.php?page=mail-smtp-by-oi-logs'),
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . (get_option('admin_email')) . '>',
        ];

        $sent_ok = OI_Mailer::dispatch_direct([
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $html,
            'headers'     => $headers,
            'attachments' => [],
        ]);

        if ($sent_ok) {
            return ['success' => true, 'message' => sprintf(__('Weekly Digest successfully dispatched to %s.', 'mail-smtp-by-oi'), $to)];
        }

        return ['success' => false, 'message' => __('Failed to deliver weekly digest email.', 'mail-smtp-by-oi')];
    }

    /**
     * Build responsive HTML email template for Weekly Digest
     *
     * @param array $data
     * @return string
     */
    private static function build_digest_html(array $data) {
        $rate_color = $data['rate'] >= 98 ? '#10b981' : ($data['rate'] >= 90 ? '#f59e0b' : '#ef4444');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Weekly Email Delivery Report</title>
        </head>
        <body style="margin:0; padding:30px 10px; background-color:#f1f5f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1e293b;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                <!-- Header -->
                <tr>
                    <td style="background:#4f46e5; padding:28px 32px; text-align:left;">
                        <span style="display:inline-block; font-size:12px; font-weight:700; color:#c7d2fe; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Mail SMTP by Oi</span>
                        <h1 style="margin:0; font-size:22px; font-weight:800; color:#ffffff;"><?php echo esc_html($data['site_name']); ?></h1>
                        <p style="margin:4px 0 0; font-size:13px; color:#e0e7ff;">7-Day Email Delivery & Performance Summary</p>
                    </td>
                </tr>

                <?php if (!empty($data['is_test'])) : ?>
                <tr>
                    <td style="background:#fef3c7; border-bottom:1px solid #fde68a; padding:10px 32px; font-size:12px; color:#92400e; font-weight:600;">
                        🔔 This is a preview test of your Weekly Executive Email Digest.
                    </td>
                </tr>
                <?php endif; ?>

                <!-- Primary KPI Highlight -->
                <tr>
                    <td style="padding:28px 32px 16px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:20px; text-align:center;">
                            <span style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Delivery Success Rate</span>
                            <div style="font-size:44px; font-weight:800; color:<?php echo esc_attr($rate_color); ?>; margin:4px 0;">
                                <?php echo esc_html($data['rate']); ?>%
                            </div>
                            <span style="font-size:13px; color:#64748b;">
                                <?php echo esc_html(number_format_i18n($data['sent'])); ?> delivered out of <?php echo esc_html(number_format_i18n($data['total'])); ?> total messages
                            </span>
                        </div>
                    </td>
                </tr>

                <!-- Metrics Grid -->
                <tr>
                    <td style="padding:0 32px 28px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td width="48%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; text-align:center;">
                                    <div style="font-size:24px; font-weight:800; color:#10b981;"><?php echo esc_html(number_format_i18n($data['sent'])); ?></div>
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-top:2px;">Delivered</div>
                                </td>
                                <td width="4%"></td>
                                <td width="48%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; text-align:center;">
                                    <div style="font-size:24px; font-weight:800; color:<?php echo $data['failed'] > 0 ? '#ef4444' : '#64748b'; ?>;"><?php echo esc_html(number_format_i18n($data['failed'])); ?></div>
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-top:2px;">Failed</div>
                                </td>
                            </tr>
                            <tr><td height="12"></td></tr>
                            <tr>
                                <td width="48%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; text-align:center;">
                                    <div style="font-size:24px; font-weight:800; color:#4f46e5;"><?php echo esc_html(number_format_i18n($data['opens'])); ?></div>
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-top:2px;">Email Opens</div>
                                </td>
                                <td width="4%"></td>
                                <td width="48%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; text-align:center;">
                                    <div style="font-size:24px; font-weight:800; color:#6366f1;"><?php echo esc_html(number_format_i18n($data['clicks'])); ?></div>
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-top:2px;">Link Clicks</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Action Button -->
                <tr>
                    <td style="padding:0 32px 32px; text-align:center;">
                        <a href="<?php echo esc_url($data['logs_url']); ?>" style="display:inline-block; background:#4f46e5; color:#ffffff; font-weight:700; font-size:14px; text-decoration:none; padding:12px 28px; border-radius:8px;">
                            View Detailed Logs in Dashboard &rarr;
                        </a>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:20px 32px; text-align:center; font-size:12px; color:#94a3b8;">
                        This weekly summary was automatically generated by <strong style="color:#64748b;">Mail SMTP by Oi</strong> for <a href="<?php echo esc_url($data['site_url']); ?>" style="color:#4f46e5; text-decoration:none;"><?php echo esc_html($data['site_name']); ?></a>.
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
