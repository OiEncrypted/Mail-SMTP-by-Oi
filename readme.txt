=== Mail SMTP by Oi ===
Contributors: oitechnologies
Donate link: https://oitech.com.bd/
Tags: smtp, email, wp mail, sendgrid, mailgun, mail logger, email log, resend
Requires at least: 5.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Fix WordPress email deliverability issues with Mail SMTP by Oi. Connect Custom SMTP, Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, and Resend.

== Description ==

**Mail SMTP by Oi** is an ultra-fast, modern, lightweight, and rock-solid SMTP and transactional email plugin developed by **Oi Technologies**.

If your WordPress website fails to send emails or if notification emails (order confirmations, password resets, contact form messages) are landing in the spam folder, **Mail SMTP by Oi** solves this once and for all.

### Key Features:
* **Custom SMTP Support**: Connect to any SMTP host (cPanel, Postfix, Zimbra, AWS SES SMTP, etc.) with SSL / TLS / STARTTLS encryption.
* **1-Click Popular Presets**: Instant setup for Google Workspace / Gmail and Microsoft 365 / Outlook.
* **Modern Cloud API Mailers**: Direct integration with **SendGrid**, **Mailgun** (US & EU), **Brevo (Sendinblue)**, **Postmark**, and **Resend** without bloated SDKs.
* **Comprehensive Email Logger**: Keep track of every outgoing email with status (Sent / Failed), recipient, subject, headers, and attachments.
* **1-Click Resend**: Re-send failed or previously sent emails directly from the log viewer.
* **Real-Time SMTP Handshake Debugger**: Live interactive terminal view showing the exact SMTP conversation and status codes.
* **Port Connectivity Diagnostic Scanner**: Built-in socket tester for ports 587, 465, 2525, and 25 to detect if your web host blocks outgoing mail.
* **DNS SPF & DMARC Checker**: Automatically analyzes your domain records to help ensure 100% Inbox deliverability.
* **Failover / Fallback Connection**: Automatic fallback to ensure critical emails are never lost.
* **AES-256-CBC Encryption**: SMTP passwords and API tokens are securely encrypted in your database using WordPress security keys.
* **GDPR Privacy Controls**: Option to disable email body logging and automatic log cleanup (retention settings).

== Installation ==

1. Upload the `Mail SMTP by Oi` folder to the `/wp-content/plugins/` directory, or install the ZIP file via WordPress Plugins -> Add New -> Upload.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Mail SMTP** in the WordPress admin menu.
4. Select your preferred mail service provider (Custom SMTP, Gmail, SendGrid, Resend, etc.) and fill in your credentials.
5. Go to the **Send Test Email** tab to send a test message and verify delivery.

== Frequently Asked Questions ==

= Why are my WordPress emails going to spam? =
By default, WordPress uses the server's basic PHP `mail()` function, which lacks proper authentication headers (SPF, DKIM, DMARC) and is heavily flagged by Gmail, Yahoo, and Outlook. Using Mail SMTP by Oi routes emails through an authenticated SMTP server or API, ensuring emails reach the inbox.

= Are my SMTP passwords and API keys stored securely? =
Yes! Mail SMTP by Oi uses industry-standard OpenSSL AES-256-CBC encryption to store sensitive credentials in your database.

= Does this plugin work with WooCommerce and form plugins? =
Yes, 100% compatible with WooCommerce, Elementor Forms, Contact Form 7, WPForms, Gravity Forms, Fluent Forms, and all core WordPress notifications.

== Changelog ==

= 1.0.0 =
* Initial official release.
* Custom SMTP, Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, and Resend integrations.
* Real-time SMTP Handshake Debugger.
* Outbound Port Scanner and DNS SPF/DMARC analyzer.
* Email logger with 1-click resend and auto-cleanup cron.
