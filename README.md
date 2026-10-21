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

## 📖 Why Mail SMTP by Oi? (কেন এই প্লাগইন?)

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
| **Direct Cloud APIs (No Bloat)** | ❌ No | 🟡 Locked in Pro | 🟢 **SendGrid, Resend, Brevo, Mailgun, Postmark** |
| **Full Email Logger** | ❌ No | 🟡 Paid Add-on | 🟢 **Built-in Free** |
| **1-Click Email Resend** | ❌ No | 🟡 Paid Add-on | 🟢 **Built-in Free** |
| **Live SMTP Handshake Terminal** | ❌ No | ❌ No | 🟢 **Real-time Live Debugger** |
| **Outbound Port Diagnostic Scanner**| ❌ No | 🟡 Basic | 🟢 **Ports 587, 465, 2525, 25** |
| **SPF & DMARC DNS Analyzer** | ❌ No | ❌ No | 🟢 **Built-in Free** |
| **Credential Storage Security** | N/A | 🔴 Plain text in DB | 🟢 **OpenSSL AES-256-CBC Encrypted** |
| **Performance Impact** | N/A | 🔴 Heavy SDKs | 🟢 **Ultra-Lightweight & Fast** |

<div align="right"><a href="#top">↑ back to top</a></div>

---

## ✨ Key Features (মূল সুবিধাসমূহ)

### 1. 🌐 Universal Mailer Support
- **Custom SMTP**: Any standard SMTP host with port flexibility (587, 465, 2525, 25), SSL / TLS / STARTTLS, and SMTP authentication.
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

### 3. 🖥️ Live SMTP Handshake Terminal Debugger
- Real-time terminal box showing the exact socket connection and SMTP dialogue:
  - `EHLO` identification
  - `STARTTLS` negotiation and cipher selection
  - `AUTH LOGIN` / verification
  - `MAIL FROM` and `RCPT TO` status
  - Server completion codes (`250 2.0.0 OK`)
- Instant 1-click "Copy Log" button for easy support and debugging.

### 4. 🩺 Outbound Port Scanner & DNS Health Check
- **Port Scanner**: Tests outgoing connections to ports `587` (TLS), `465` (SSL), `2525`, and `25` to diagnose if your hosting firewall (e.g. GoDaddy, DigitalOcean, Hetzner, AWS) is blocking SMTP.
- **DNS Inspector**: Checks if valid **SPF** and **DMARC** TXT records exist on your domain to prevent spoofing and ensure optimal deliverability.

### 5. 🛡️ Enterprise-Grade Security
- All passwords and API keys are encrypted at rest using **AES-256-CBC** with keys derived from WordPress security salts (`AUTH_KEY` & `SECURE_AUTH_KEY`).
- Complete nonce verification on all AJAX actions.
- Strict capability checks (`manage_options`).

<div align="right"><a href="#top">↑ back to top</a></div>

---

## 🔄 How It Works (আর্কিটেকচার)

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

## 🛠️ Installation & Quick Start (ইনস্টলেশন ও ব্যবহার)

### ১. ইনস্টলেশন:
1. `Mail SMTP by Oi` ফোল্ডারটি আপনার ওয়ার্ডপ্রেসের `wp-content/plugins/` ডিরেক্টরিতে আপলোড করুন অথবা অ্যাডমিন প্যানেল থেকে **Plugins -> Add New -> Upload Plugin** দিয়ে জিপ ফাইল আপলোড করুন।
2. ওয়ার্ডপ্রেস ড্যাশবোর্ড থেকে **Plugins** মেনুতে যান এবং **Mail SMTP by Oi** প্লাগইনটি **Activate** করুন।

### ২. কনফিগারেশন:
1. বাম পাশের অ্যাডমিন মেনু থেকে **Mail SMTP**-তে ক্লিক করুন।
2. **From Email** এবং **From Name** ফিল্ড পূরণ করুন। *(অন্যান্য প্লাগইন যেন ভুল ইমেইল দিয়ে না পাঠায় সেজন্য "Force From Email" চালু রাখা সুবিধাজনক)*।
3. আপনার পছন্দের মেইলার (Custom SMTP, Gmail, SendGrid, Resend ইত্যাদি) সিলেক্ট করুন।
4. প্রয়োজনীয় ক্রেডেনশিয়াল প্রদান করে **Save Settings** বাটনে ক্লিক করুন।

### ৩. টেস্ট ইমেইল ভেরিফিকেশন:
1. **Send Test Email** ট্যাবে যান।
2. আপনার নিজস্ব একটি ইমেইল অ্যাড্রেস দিয়ে **Send Test Email** বাটনে ক্লিক করুন।
3. লাইভ টার্মিনাল উইন্ডোতে হ্যান্ডশেক ডিবাগ লগ দেখে ডেলিভারি কনফার্ম করুন।

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
<summary><strong>ইমেইল কি WooCommerce এবং Contact Form 7 এর সাথে কাজ করবে?</strong></summary>
হ্যাঁ, 100% কাজ করবে। প্লাগইনটি ওয়ার্ডপ্রেসের কোর `wp_mail()` ফাংশনের সাথে ইন্টিগ্রেট করে। ফলে WooCommerce, Elementor Forms, Gravity Forms, WPForms, Fluent Forms কিংবা যেকোনো প্লাগইনের ইমেইল স্বয়ংক্রিয়ভাবে Mail SMTP by Oi দিয়ে যাবে।
</details>

<details>
<summary><strong>হোস্টিং যদি SMTP পোর্ট ব্লক করে রাখে তাহলে কী করব?</strong></summary>
আপনার হোস্টিং প্রোভাইডার যদি পোর্ট 587/465 ব্লক করে রাখে, আপনি **SendGrid**, **Brevo**, **Postmark**, বা **Resend** এর মতো **HTTP REST API** মেইলার ব্যবহার করতে পারেন। API মেইলারগুলো সাধারণ HTTPS (Port 443) দিয়ে কাজ করে, যা কোনো হোস্টিংয়েই ব্লক থাকে না।
</details>

<details>
<summary><strong>ইমেইল লগ কতদিন পর ডিলিট হবে?</strong></summary>
সেটিংসের **Log Retention Period** থেকে আপনি ৭, ১৪, ৩০, ৬০, ৯০ দিন নির্বাচন করতে পারেন অথবা আজীবন সংরক্ষণ করতে পারেন। ডিফল্টভাবে ৩০ দিন পর পুরোনো লগ স্বয়ংক্রিয়ভাবে মুছে যায়।
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
