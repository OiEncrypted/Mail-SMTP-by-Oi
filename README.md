<a name="top"></a>
<h1 align="center">
  Mail SMTP by Oi 🚀
</h1>

<p align="center">
  <strong>The Ultimate High-Performance SMTP & Transactional Email Plugin for WordPress</strong><br>
  <em>Fix WordPress email deliverability forever. Connect Custom SMTP, Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, and Resend with comprehensive email logging, 1-click resend, and live diagnostics.</em>
</p>

<p align="center">
  <a href="https://wordpress.org/"><img src="https://img.shields.io/badge/WordPress-5.6%20to%207.0+-21759B?logo=wordpress&logoColor=white" alt="WordPress 5.6 - 7.0+"></a>
  <a href="https://woocommerce.com/"><img src="https://img.shields.io/badge/WooCommerce-Compatible-96588A?logo=woocommerce&logoColor=white" alt="WooCommerce Compatible"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-7.4%20to%208.3+-777BB4?logo=php&logoColor=white" alt="PHP 7.4 - 8.3+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-GPL%20v3-green.svg?logo=gnu&logoColor=white" alt="GPL v3 License"></a>
  <a href="#"><img src="https://img.shields.io/badge/Security-AES--256--CBC%20Encrypted-blue.svg" alt="Security AES-256-CBC"></a>
  <a href="https://oitech.com.bd/"><img src="https://img.shields.io/badge/Author-Oi%20Technologies-4f46e5.svg" alt="Oi Technologies"></a>
</p>

<p align="center">
  <a href="#-why-mail-smtp-by-oi">Why Mail SMTP?</a> •
  <a href="#-key-features">Key Features</a> •
  <a href="#-how-it-works">How It Works</a> •
  <a href="#-supported-mail-providers">Supported Providers</a> •
  <a href="#%EF%B8%8F-installation--quick-start">Installation</a> •
  <a href="#-provider-configuration-guides">Provider Guides</a> •
  <a href="#-email-logger--resend">Email Logger</a> •
  <a href="#-diagnostics--port-scanner">Diagnostics</a> •
  <a href="#-security--encryption">Security</a> •
  <a href="#-frequently-asked-questions">FAQ</a> •
  <a href="#-about-oi-technologies">About Oi</a>
</p>

<div align="center">

⭐ **If this plugin solves your WordPress email deliverability problems, please consider starring the repository — it helps other developers and store owners discover it!**

</div>

---

## 📖 Why Mail SMTP by Oi?

By default, WordPress attempts to send emails using the web server's native PHP `mail()` function. This causes critical business issues:
- ❌ **No Authentication**: Emails lack SPF, DKIM, and DMARC alignment, causing Gmail, Yahoo, and Outlook to flag them as Spam or reject them completely.
- ❌ **Silent Failures**: Web hosts often disable or throttle `mail()`, leaving you unaware that order notifications, password resets, or form submissions never reached their destination.
- ❌ **Bloated Alternatives**: Most existing SMTP plugins are bloated with slow tracking scripts, heavy vendor SDKs, aggressive upsells, and locked features.

**Mail SMTP by Oi** was built from the ground up by **Oi Technologies** to provide an ultra-clean, lightweight, high-performance, and feature-complete solution that respects your server resources and privacy.

### 📊 Comparison Table

| Feature | Default WordPress `mail()` | Generic SMTP Plugins | **Mail SMTP by Oi** |
| :--- | :---: | :---: | :---: |
| **Delivery Reliability** | 🔴 Low (often Spam) | 🟡 Medium | 🟢 **100% In-Box Delivery** |
| **Custom SMTP (cPanel/Postfix)** | ❌ No | 🟢 Yes | 🟢 **Yes (SSL/TLS/AutoTLS)** |
| **Direct Cloud APIs (No Bloat)** | ❌ No | 🟡 Locked in Pro | 🟢 **Amazon SES (SigV4), SendGrid, Resend, Brevo, Mailgun, Postmark** |
| **Multi-Channel Alerts** | ❌ No | 🟡 Paid Add-on | 🟢 **Telegram, Slack, Discord, Email** |
| **Full Email Logger** | ❌ No | 🟡 Paid Add-on | 🟢 **Built-in Free** |
| **1-Click Email Resend** | ❌ No | 🟡 Paid Add-on | 🟢 **Built-in Free** |
| **Async Background Queue (Zero Latency)** | ❌ No | 🔴 Pro / Locked | 🟢 **Built-in Free (Auto Retry)** |
| **Smart Conditional Email Routing** | ❌ No | 🔴 Pro / Locked | 🟢 **Built-in Free (Subject/To/From Rules)** |
| **Weekly Executive Summary Digest** | ❌ No | 🔴 Pro / Locked | 🟢 **Built-in Free (Scorecard & Metrics)** |
| **Secure Attachment Vault** | ❌ No | 🔴 Pro / Locked | 🟢 **Built-in Free (.htaccess Protected)** |
| **WordPress Core Email Controls** | ❌ No | 🔴 Pro / Locked | 🟢 **Built-in Free (Selective Suppression)** |
| **Developer Staging & Simulation Mode** | ❌ No | 🔴 Pro / None | 🟢 **Built-in Free (Capture Without Sending)** |
| **Admin Bar Live Health Node** | ❌ No | 🟡 Basic / Paid | 🟢 **Built-in Free (Top Bar Status)** |
| **Open & Click Tracking** | ❌ No | 🔴 Pro / Locked | 🟢 **Built-in Free** |
| **Live SMTP Handshake Terminal** | ❌ No | ❌ No | 🟢 **Real-time Live Debugger** |
| **Dashboard Status Widget** | ❌ No | 🟡 Paid / Basic | 🟢 **Built-in Live Widget** |
| **Outbound Port Diagnostic Scanner**| ❌ No | 🟡 Basic | 🟢 **Ports 587, 465, 2525, 25** |
| **SPF & DMARC DNS Analyzer** | ❌ No | ❌ No | 🟢 **Built-in Free** |
| **Credential Storage Security** | N/A | 🔴 Plain text in DB | 🟢 **OpenSSL AES-256-CBC Encrypted** |
| **Performance Impact** | N/A | 🔴 Heavy SDKs | 🟢 **Ultra-Lightweight & Fast** |

<div align="right"><a href="#top">↑ back to top</a></div>

---

## ✨ Key Features

### 1. 🌐 Universal Mailer Support
- **Custom SMTP**: Any standard SMTP host with port flexibility (587, 465, 2525, 25), SSL / TLS / STARTTLS, and SMTP authentication.
- **Amazon SES (Simple Email Service)**: Direct AWS Signature Version 4 implementation via REST API. Ultra-fast, lowest cost ($0.10 per 1,000 emails), with zero external SDK bloat.
- **Gmail / Google Workspace**: Instant preset for Gmail SMTP with App Passwords.
- **Microsoft 365 / Outlook**: Instant preset for Outlook enterprise servers.
- **SendGrid Web API v3**: Direct HTTPS API (bypasses hosting port blocks completely).
- **Mailgun API**: Supports both US (`api.mailgun.net`) and EU (`api.eu.mailgun.net`) regions.
- **Brevo (Sendinblue) API**: Free tier friendly REST API.
- **Postmark API**: Ultra-fast transactional email API with server token.
- **Resend API**: Modern developer-first transactional email delivery.
- **Default PHP Mail**: Automatic failover / fallback option.

### 2. 📋 Comprehensive Email Logger & 1-Click Resend
- Keeps track of all outgoing emails with recipient address, subject, status (`Sent` / `Failed`), mailer used, date, headers, and attachments.
- **Modal Preview**: Inspect the full HTML or Plain-text body of any past email.
- **1-Click Resend**: Re-deliver failed or previously sent emails directly from the dashboard.
- **Bulk Operations**: Bulk delete and "Clear All Logs" options.
- **Auto-Cleanup Cron**: Automatically prunes logs older than 7, 14, 30, 60, or 90 days to keep your database lean.
- **GDPR Privacy Mode**: Option to turn off body logging for sensitive user data.

### 3. 📊 7-Day Delivery Activity Trend & CSV Export
- **Daily Visual Bar Chart**: Clean CSS/SVG delivery trend in the dashboard showing Sent (Green) vs Failed (Red) emails for each of the last 7 days.
- **Export to CSV**: Download filtered email delivery logs as CSV for auditing and client reporting.
- **Sandboxed `<iframe>` Modal**: Inspect emails in an isolated iframe so custom email styling doesn't conflict with WordPress admin CSS.

### 4. 📥 1-Click Migration from Other SMTP Plugins
- Automatically detects settings from **WP Mail SMTP**, **FluentSMTP**, or **Easy WP SMTP** if installed on your site.
- Migrates host, port, credentials, and from-email with a single click.

### 5. 🔔 Multi-Channel Failure Alert System (Telegram, Slack, Discord, Email)
- Get alerted immediately via **Telegram Bot**, **Slack Webhook**, **Discord Webhook**, or **Admin Email** if email delivery fails.
- Catch expired passwords, blocked ports, or API quota limits before your customers report missed receipts or checkout errors.
- Built-in 1-click test button to verify webhook notifications right from the settings screen.
- Smart throttling (15 minutes) prevents notification floods during high-volume spikes.

### 6. 📊 Native WordPress Dashboard Status Widget
- Displays live delivery statistics right on the main WordPress admin dashboard.
- Highlights 24-hour / all-time sent emails, failed emails, and overall delivery success rate (%).
- Shows the currently active mailer (e.g. Amazon SES, SendGrid, Custom SMTP) and quick links to send test emails or view detailed logs.

### 7. ⚡ Asynchronous Background Queue (Zero Latency)
- Offloads email delivery to an asynchronous background worker and database queue table (`wp_oi_mail_smtp_queue`).
- **Instant Checkout & Submissions**: WooCommerce orders and form submissions finish in milliseconds instead of hanging for 2-5 seconds waiting for SMTP handshakes.
- **Smart Exponential Retry**: Automatically retries failed messages up to 3 times (2 min, 4 min intervals) before declaring permanent failure.

### 8. 👁️ Email Open & Link Click Tracking
- **Open Tracking**: Automatically injects a 1x1 transparent tracking pixel into outgoing HTML emails to measure recipient engagement without external services.
- **Click Tracking**: Tracks recipient clicks on links within emails and seamlessly redirects them to their destinations.
- **Log Table Badges**: Displays live "Opens" and "Clicks" badges in the email logs table and modal details view.

### 9. 🔀 Smart Conditional Email Routing (Enterprise)
- Directs specific emails to designated mailers based on dynamic conditions (Subject, From Email, To Email, or Headers).
- **Match Operators**: `Contains`, `Equals`, `Starts With`, and `Ends With`.
- **Use Cases**: Route mission-critical WooCommerce order receipts through Amazon SES, password resets through Postmark, and newsletters/marketing notifications through SendGrid or Brevo.

### 10. 📈 Weekly Executive Email Summary Digest (Enterprise)
- Automated executive email scorecard dispatched every Monday morning at 09:00 AM.
- Provides site administrators with total sent emails, failure counts, delivery success rate (%), and recipient open/click rates.
- Includes a 1-click "Send Test Weekly Digest" button to review the report layout at any time.

### 11. 📎 Secure Email Attachment Vault & Resend / Forward (Enterprise)
- **Protected Archival**: Safely archives copies of sent attachments (such as WooCommerce PDF receipts, licenses, or quotes) into an `.htaccess`-protected private directory.
- **Direct Admin Downloads**: Administrators can inspect and re-download archived attachments directly from the Email Delivery Logs modal.
- **Edit & Forward / Resend**: Forward logged emails to any custom recipient email address directly from the inspection modal with attachments preserved.
- **Automatic Lifecycle Cleanup**: Attachments are automatically purged whenever corresponding email logs are cleared or pruned.

### 12. 🛑 WordPress Core Automated Email Controls (Enterprise)
- Selectively suppress noisy or unnecessary default WordPress automated notifications.
- **Comment Controls**: Disable "Comment Awaiting Moderation" admin emails and "Comment Published" author emails.
- **User & Admin Controls**: Disable "Password Changed" user alerts, "New User Registration" admin alerts, and "Site Admin Email Change" alerts.
- **Core Update Controls**: Disable automatic core background update success notices.

### 13. 🧪 Developer Staging & Email Simulation Mode
- **Zero Accidental Sends**: Perfect for local and staging environments (`staging.domain.com` or `localhost`).
- Captures all outgoing emails directly into the Email Delivery Log for inspection without dispatching them to real users.
- **Catch-All Test Reroute**: Option to reroute 100% of outgoing site emails to a single development inbox.

### 14. 🧭 WordPress Admin Bar Live Delivery Monitor
- Real-time status node in the top WordPress admin bar across all administrative pages.
- Displays active mailer state and highlights failed email counts immediately in red.
- Quick dropdown shortcuts to view delivery logs, trigger test emails, and open settings.

### 15. 👁️ Password Visibility Toggles & UX Polish
- Instant show/hide toggle for SMTP passwords and API keys to avoid typing errors.
- Tabbed email viewer: HTML Preview, Plain Text, Headers & Info, Attachments, Debug Trace, and Resend / Forward.

### 16. 🖥️ Live SMTP Handshake Terminal Debugger
- Real-time terminal box showing the exact socket connection and SMTP dialogue:
  - `EHLO` identification
  - `STARTTLS` negotiation and cipher selection
  - `AUTH LOGIN` / verification
  - `MAIL FROM` and `RCPT TO` status
  - Server completion codes (`250 2.0.0 OK`)
- Instant 1-click "Copy Log" button for easy support and debugging.

### 17. 🩺 Outbound Port Scanner & DNS Health Check
- **Port Scanner**: Tests outgoing connections to ports `587` (TLS), `465` (SSL), `2525`, and `25` to diagnose if your hosting firewall (e.g. GoDaddy, DigitalOcean, Hetzner, AWS) is blocking SMTP.
- **DNS Inspector**: Checks if valid **SPF** and **DMARC** TXT records exist on your domain to prevent spoofing and ensure optimal deliverability.

### 18. 🛡️ Enterprise-Grade Security
- All passwords and API keys are encrypted at rest using **AES-256-CBC** with keys derived from WordPress security salts (`AUTH_KEY` & `SECURE_AUTH_KEY`).
- Complete nonce verification on all AJAX actions.
- Strict capability checks (`manage_options`).

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🔄 How It Works (Architecture)

```mermaid
flowchart TD
    A["🛒 WordPress / WooCommerce / Forms / Plugins"] -->|Calls wp_mail()| B["⚡ Mail SMTP by Oi Interceptor"]
    
    B --> C{"Check Configured Mailer"}
    
    C -->|Custom SMTP / Gmail / Outlook| D["PHPMailer SMTP Handler"]
    C -->|SendGrid / Resend / Brevo / Postmark / Mailgun| E["Direct HTTPS REST API"]
    C -->|Default Fallback| F["Native Mail Dispatcher"]
    
    D -->|Real-time Socket Handshake| G["Outbound Mail Server"]
    E -->|Secure HTTPS Payload| H["Cloud API Gateway"]
    
    G --> I{"Send Success?"}
    H --> I
    
    I -->|Yes| J["🟢 Mark Sent & Write to DB Log"]
    I -->|No| K["🔴 Capture Error & Trigger Fallback"]
    K --> L["Save Error & Full Debug Trace in Log"]
    
    J --> M["📬 Recipient Inbox"]
```

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 📁 Plugin File Structure

```
Mail SMTP by Oi/
├── mail-smtp-by-oi.php            # Main plugin header & bootstrap hook
├── uninstall.php                  # Clean database cleanup on uninstall
├── readme.txt                     # WordPress.org standard metadata
├── README.md                      # Comprehensive documentation
├── .gitignore                     # Git ignore rules
│
├── includes/
│   ├── class-oi-mail-smtp.php    # Singleton plugin bootstrap & lifecycle
│   ├── class-oi-options.php      # Settings management & validation
│   ├── class-oi-encryption.php   # AES-256-CBC credential encryption
│   ├── class-oi-mailer.php       # wp_mail dispatcher & hook interceptor
│   ├── class-oi-logger.php       # Database logger, resend engine & cron pruner
│   ├── class-oi-migration.php    # 1-Click migration from other SMTP plugins
│   ├── class-oi-diagnostics.php  # Live port socket scanner & DNS checks
│   ├── class-oi-admin.php        # Admin UI, tabs, navigation & AJAX endpoints
│   │
│   └── providers/
│       ├── interface-oi-provider.php       # Standard provider interface
│       ├── class-oi-provider-smtp.php      # Custom SMTP, Gmail & Outlook driver
│       ├── class-oi-provider-sendgrid.php  # SendGrid v3 API driver
│       ├── class-oi-provider-mailgun.php   # Mailgun US/EU API driver
│       ├── class-oi-provider-brevo.php     # Brevo (Sendinblue) API driver
│       ├── class-oi-provider-postmark.php  # Postmark API driver
│       ├── class-oi-provider-resend.php    # Resend API driver
│       └── class-oi-provider-default.php   # Native fallback driver
│
└── assets/
    ├── css/
    │   └── admin.css                       # Responsive modern dashboard styling
    └── js/
        └── admin.js                        # AJAX form handler, test runner & modal
```

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🛠️ Installation & Quick Start

### 1. Installation:
1. Upload the `Mail SMTP by Oi` folder to your WordPress `wp-content/plugins/` directory, or upload the zip file from your WordPress admin dashboard (**Plugins -> Add New -> Upload Plugin**).
2. Navigate to the **Plugins** menu in your WordPress dashboard and click **Activate** for **Mail SMTP by Oi**.

### 2. Configuration:
1. Click on **Mail SMTP** in the left admin sidebar menu.
2. Fill in the **From Email** and **From Name** fields. *(Optionally enable "Force From Email" to prevent other plugins from overriding your sender identity)*.
3. Select your preferred mailer (Amazon SES, Custom SMTP, Gmail, SendGrid, Resend, Brevo, Mailgun, or Postmark).
4. Enter the required credentials and click **Save Configuration**.

### 3. Test Email Verification:
1. Switch to the **Test Email** tab.
2. Enter your personal recipient email address and click **Send Test Email**.
3. Inspect the live terminal output to verify socket connection and confirm successful delivery.

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🔑 Provider Configuration Guides

<details>
<summary><strong>1. Google / Gmail & Google Workspace (Click to expand)</strong></summary>

Google no longer allows less secure apps with normal passwords. You must use a **Google App Password**:
1. Log into your Google Account and go to [Security Settings](https://myaccount.google.com/security).
2. Ensure **2-Step Verification** is turned **ON**.
3. Under *2-Step Verification*, scroll down to **App Passwords**.
4. Create a new App Password (name it "WordPress SMTP").
5. Copy the 16-character generated password.
6. In **Mail SMTP by Oi**, select **Google / Gmail**:
   - **SMTP Host**: `smtp.gmail.com`
   - **SMTP Port**: `587`
   - **Encryption**: `TLS`
   - **SMTP Username**: Your full Gmail / Google Workspace address
   - **SMTP Password**: The 16-character App Password (without spaces)
</details>

<details>
<summary><strong>2. Microsoft 365 / Outlook (Click to expand)</strong></summary>

1. In **Mail SMTP by Oi**, select **Outlook / Office 365**:
   - **SMTP Host**: `smtp.office365.com`
   - **SMTP Port**: `587`
   - **Encryption**: `TLS`
   - **SMTP Username**: Your Microsoft 365 / Outlook email address
   - **SMTP Password**: Your account password or App Password
</details>

<details>
<summary><strong>3. SendGrid (Click to expand)</strong></summary>

1. Sign in to your [SendGrid Dashboard](https://app.sendgrid.com/).
2. Navigate to **Settings -> API Keys** and click **Create API Key**.
3. Choose **Full Access** or **Restricted Access** with "Mail Send" permission.
4. Copy the API Key (starts with `SG.`).
5. In **Mail SMTP by Oi**, select **SendGrid** and paste the key into the **API Key** field.
</details>

<details>
<summary><strong>4. Brevo / Sendinblue (Click to expand)</strong></summary>

1. Sign in to [Brevo](https://www.brevo.com/).
2. Click your account name at top right -> **SMTP & API**.
3. Under the **API Keys** tab, click **Generate a new API key**.
4. In **Mail SMTP by Oi**, select **Brevo** and paste the API key.
</details>

<details>
<summary><strong>5. Resend (Click to expand)</strong></summary>

1. Sign in to [Resend.com](https://resend.com/).
2. Go to **API Keys** and click **Create API Key**.
3. In **Mail SMTP by Oi**, select **Resend** and paste the API key (`re_...`).
</details>

<details>
<summary><strong>6. Mailgun (Click to expand)</strong></summary>

1. Sign in to [Mailgun](https://www.mailgun.com/).
2. Go to **Sending -> Domains** and copy your sending domain (e.g. `mg.yourdomain.com`).
3. Go to **Settings -> API Keys** and copy your **Private API Key**.
4. In **Mail SMTP by Oi**, select **Mailgun**, enter your Domain, API Key, and select your Region (**US** or **EU**).
</details>

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🩺 Diagnostics & Outbound Port Scanner

Many budget or cloud web hosts block outgoing traffic on port `25`, `465`, or `587` by default. When this happens, PHPMailer hangs and displays timeout errors.

The **Diagnostics** tab in Mail SMTP by Oi includes an interactive port scanner:
- **Port 587**: Standard TLS / STARTTLS submission port (Recommended).
- **Port 465**: Traditional SSL submission port.
- **Port 2525**: Modern alternative port supported by SendGrid, Mailgun, and major relays.
- **Port 25**: Legacy unencrypted port (usually blocked by all cloud providers).

Clicking **Run Port Test** tests socket connections via `fsockopen` directly from your web server to your mail host, letting you instantly identify if hosting firewalls are the root cause.

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🔒 Security & Data Integrity

- **Encryption at Rest**: Any passwords or API credentials stored in the `wp_options` table are encrypted with `openssl_encrypt()` using `AES-256-CBC`.
- **WordPress Salts Integration**: Encryption keys are dynamically derived from your unique `AUTH_KEY` and `SECURE_AUTH_KEY` in `wp-config.php`.
- **Capability Guard**: All administrative views and AJAX endpoints require the `manage_options` capability.
- **CSRF Protection**: All settings updates, test sends, and log actions are strictly verified with WordPress security nonces.
- **Clean Uninstall**: If the user checks *"Completely delete all Mail SMTP by Oi settings and email logs when uninstalled"*, all database tables, options, and crons are cleanly removed upon plugin deletion.

---

## ❓ Frequently Asked Questions (FAQ)

<details>
<summary><strong>Will this work with WooCommerce, Contact Form 7, and Elementor Forms?</strong></summary>
Yes, 100% compatible. The plugin integrates directly with the WordPress core `wp_mail()` function. Emails sent by WooCommerce, Elementor Forms, Gravity Forms, WPForms, Fluent Forms, Contact Form 7, or any standard WordPress plugin will automatically route through Mail SMTP by Oi.
</details>

<details>
<summary><strong>What should I do if my web hosting blocks outbound SMTP ports?</strong></summary>
If your hosting provider blocks ports 587 and 465, use an HTTP REST API mailer such as **Amazon SES**, **SendGrid**, **Brevo**, **Postmark**, or **Resend**. API mailers communicate over standard HTTPS (Port 443), which is never blocked by hosting firewalls.
</details>

<details>
<summary><strong>How long are email logs retained in the database?</strong></summary>
You can configure the **Log Retention Period** in Settings to automatically purge logs after 7, 14, 30, 60, or 90 days, or choose to keep them forever. By default, logs older than 30 days are automatically cleaned up daily to keep your database lean.
</details>

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!
Feel free to check the [issues page](https://github.com/Oi-Technologies-Official/Mail-SMTP-by-Oi/issues).

---

## 🏢 About Oi Technologies

**Mail SMTP by Oi** is proudly designed, developed, and maintained by **[Oi Technologies](https://oitech.com.bd/)**.

We build clean, robust, high-performance software and open-source tools for merchants, developers, and businesses across Bangladesh and worldwide.

- 🌐 **Website**: [https://oitech.com.bd/](https://oitech.com.bd/)
- 📦 **Open Source Projects**: [https://oitech.com.bd/open-source/](https://oitech.com.bd/open-source/)
- 📧 **Inquiries & Support**: contact@oitech.com.bd

---

<p align="center">
  <strong>Mail SMTP by Oi</strong> • Released under the <a href="LICENSE">GPLv3 License</a>.<br>
  © 2026 Oi Technologies. All rights reserved.
</p>
