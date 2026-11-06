<?php
/**
 * WordPress Core Site Health Integration
 *
 * Adds diagnostic tests to the WordPress Site Health screen (Tools -> Site Health)
 * to monitor SMTP connectivity, port availability, DNS records, and queue health.
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Site_Health {

    /**
     * Initialize Site Health hooks
     */
    public static function init() {
        add_filter('site_status_tests', [__CLASS__, 'register_tests']);
    }

    /**
     * Register custom Site Health tests
     *
     * @param array $tests
     * @return array
     */
    public static function register_tests($tests) {
        $tests['direct']['oi_mail_smtp_mailer_status'] = [
            'label' => __('Mail SMTP by Oi: Mailer Configuration', 'mail-smtp-by-oi'),
            'test'  => [__CLASS__, 'test_mailer_status'],
        ];

        $tests['async']['oi_mail_smtp_ports'] = [
            'label' => __('Mail SMTP by Oi: Outbound Port Connectivity', 'mail-smtp-by-oi'),
            'test'  => 'oi_test_site_health_ports',
        ];

        $tests['direct']['oi_mail_smtp_queue_health'] = [
            'label' => __('Mail SMTP by Oi: Background Queue Health', 'mail-smtp-by-oi'),
            'test'  => [__CLASS__, 'test_queue_health'],
        ];

        return $tests;
    }

    /**
     * Test 1: Check mailer configuration status
     *
     * @return array
     */
    public static function test_mailer_status() {
        $settings = OI_Options::get_all(false);
        $mailer   = $settings['mailer'] ?? 'smtp';
        $enabled  = !empty($settings['enabled']);

        if (!$enabled) {
            return [
                'label'       => __('Mail SMTP by Oi is currently disabled', 'mail-smtp-by-oi'),
                'status'      => 'critical',
                'badge'       => [
                    'label' => __('Email Delivery', 'mail-smtp-by-oi'),
                    'color' => 'red',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('Email routing through Mail SMTP by Oi is toggled off. Your site is falling back to standard PHP mail(), which is prone to landing in spam.', 'mail-smtp-by-oi')
                ),
                'actions'     => sprintf(
                    '<p><a class="button button-primary" href="%s">%s</a></p>',
                    esc_url(admin_url('admin.php?page=mail-smtp-by-oi')),
                    __('Enable Mail SMTP', 'mail-smtp-by-oi')
                ),
            ];
        }

        if ($mailer === 'default') {
            return [
                'label'       => __('Mail SMTP by Oi is set to default PHP mail()', 'mail-smtp-by-oi'),
                'status'      => 'recommended',
                'badge'       => [
                    'label' => __('Email Delivery', 'mail-smtp-by-oi'),
                    'color' => 'orange',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('You are using the default web server mailer. To ensure reliable inbox delivery and avoid spam folders, configure a dedicated SMTP or Cloud API provider (SendGrid, Mailgun, Brevo, Resend, or SES).', 'mail-smtp-by-oi')
                ),
                'actions'     => sprintf(
                    '<p><a class="button button-secondary" href="%s">%s</a></p>',
                    esc_url(admin_url('admin.php?page=mail-smtp-by-oi')),
                    __('Configure Mailer', 'mail-smtp-by-oi')
                ),
            ];
        }

        $provider = OI_Mailer::get_provider($mailer);
        $provider_name = $provider ? $provider->get_name() : strtoupper($mailer);

        return [
            'label'       => sprintf(__('Mail SMTP is actively routing via %s', 'mail-smtp-by-oi'), $provider_name),
            'status'      => 'good',
            'badge'       => [
                'label' => __('Email Delivery', 'mail-smtp-by-oi'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                sprintf(__('Your WordPress emails are configured to route through <strong>%s</strong> with encrypted credentials.', 'mail-smtp-by-oi'), esc_html($provider_name))
            ),
            'actions'     => sprintf(
                '<p><a class="button button-secondary" href="%s">%s</a></p>',
                esc_url(admin_url('admin.php?page=mail-smtp-by-oi-test')),
                __('Send Test Email', 'mail-smtp-by-oi')
            ),
        ];
    }

    /**
     * Test 2: Check background queue health
     *
     * @return array
     */
    public static function test_queue_health() {
        if (!OI_Queue::is_enabled()) {
            return [
                'label'       => __('Background Email Queue is disabled (Synchronous mode)', 'mail-smtp-by-oi'),
                'status'      => 'good',
                'badge'       => [
                    'label' => __('Email Queue', 'mail-smtp-by-oi'),
                    'color' => 'gray',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('Emails are dispatched synchronously when triggered. For high-traffic checkout or WooCommerce sites, enabling the background queue can reduce page latency.', 'mail-smtp-by-oi')
                ),
                'actions'     => sprintf(
                    '<p><a class="button button-secondary" href="%s">%s</a></p>',
                    esc_url(admin_url('admin.php?page=mail-smtp-by-oi#oi-tab-performance')),
                    __('Queue Settings', 'mail-smtp-by-oi')
                ),
            ];
        }

        $stats = OI_Queue::get_stats();

        if ($stats['failed'] > 0) {
            return [
                'label'       => sprintf(__('%d email(s) failed in the background queue', 'mail-smtp-by-oi'), $stats['failed']),
                'status'      => 'recommended',
                'badge'       => [
                    'label' => __('Email Queue', 'mail-smtp-by-oi'),
                    'color' => 'orange',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('There are failed emails in the background queue that could not be delivered after maximum retry attempts. Check your SMTP settings and log viewer.', 'mail-smtp-by-oi')
                ),
                'actions'     => sprintf(
                    '<p><a class="button button-primary" href="%s">%s</a></p>',
                    esc_url(admin_url('admin.php?page=mail-smtp-by-oi-logs')),
                    __('View Email Logs', 'mail-smtp-by-oi')
                ),
            ];
        }

        return [
            'label'       => __('Background Email Queue is operating smoothly', 'mail-smtp-by-oi'),
            'status'      => 'good',
            'badge'       => [
                'label' => __('Email Queue', 'mail-smtp-by-oi'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                sprintf(__('Background queue has %d pending items and 0 permanent failures.', 'mail-smtp-by-oi'), $stats['pending'])
            ),
        ];
    }
}

