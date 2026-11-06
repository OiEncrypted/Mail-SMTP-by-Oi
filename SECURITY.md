# Security Policy

At **Oi Technologies**, security is at the core of **Mail SMTP by Oi**. We take the security of our software, the integrity of transactional emails, and the privacy of your credentials with utmost seriousness.

This document outlines our security procedures, supported versions, responsible disclosure process, and built-in architectural security controls.

---

## Supported Versions

Only the latest active minor release receives official security patches and bug fixes. We strongly recommend always running the latest version available on the WordPress Plugin Directory or via our official repository.

| Version | Supported          | Status                               |
| :------ | :----------------- | :----------------------------------- |
| 1.0.x   | :white_check_mark: | Currently Supported (Active Release) |
| < 1.0.0 | :x:                | Unsupported / Deprecated             |

---

## Reporting a Vulnerability

If you believe you have discovered a security vulnerability in **Mail SMTP by Oi**, please notify us privately. **Do not create public GitHub issues or disclose details on public forums before a fix is released.**

### How to Report

Please submit your security report via either of the following channels:

* **Email**: [security@oitech.com.bd](mailto:security@oitech.com.bd) (Subject: `[Security Report] Mail SMTP by Oi - <Vulnerability Type>`)
* **GitHub**: Submit a report through **GitHub Security Advisories** (Private Vulnerability Reporting).

### What to Include in Your Report

To help us triage and resolve the issue quickly, please provide as much detail as possible:

1. **Vulnerability Type**: (e.g., Cross-Site Scripting (XSS), SQL Injection, Cross-Site Request Forgery (CSRF), Privilege Escalation, Remote Code Execution, SSRF, Information Disclosure).
2. **Affected Version**: Plugin version and environment details (PHP version, WordPress version).
3. **Step-by-Step Reproduction**: Clear, numbered steps to reproduce the vulnerability.
4. **Proof of Concept (PoC)**: Minimal code snippets, screenshots, or HTTP request payloads demonstrating the issue.
5. **Impact Assessment**: What an attacker could achieve by exploiting this flaw.
6. **Suggested Remediation**: Optional, but greatly appreciated if you have recommendations.

---

## Response & Disclosure Timeline

We adhere to a coordinated, responsible disclosure model:

| Phase | Target Timeline |
| :--- | :--- |
| **Initial Acknowledgment** | Within **24 to 48 hours** |
| **Triage & Severity Rating** | Within **72 hours** (rated using CVSS v3.1) |
| **Patch Development & Testing**| Within **5 to 10 business days** (critical vulnerabilities are fast-tracked) |
| **Release & Public Advisory** | Coordinated release on WordPress.org SVN and GitHub |

We kindly ask reporters to maintain confidentiality until the patch is deployed and available to all users.

---

## Security Architecture & Defenses

**Mail SMTP by Oi** is built following WordPress Core Security Standards and OWASP Top 10 guidelines:

### 1. Robust Credential Encryption (AES-256-CBC)
* All sensitive credentials—including custom SMTP passwords, Mailgun, SendGrid, Brevo, Postmark, Resend, and AWS SES API keys/secrets—are encrypted in the WordPress database using OpenSSL **AES-256-CBC**.
* The cryptographic keys are derived from WordPress salts (`AUTH_KEY`, `SECURE_AUTH_KEY`).
* Each encryption call generates a cryptographically secure random Initialization Vector (`random_bytes`), preventing replay and rainbow table attacks.

### 2. Strict Access Control & Authorization
* All administrative menus, AJAX endpoints (`wp_ajax_*`), and configuration saving functions are guarded by explicit capability checks:
  ```php
  if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Unauthorized access.'], 403);
  }
  ```
* No privileged operations are ever exposed to unauthenticated users (`wp_ajax_nopriv_*` is never used for management tasks).

### 3. Anti-CSRF Protection (Nonces)
* Every form submission, AJAX request, log deletion, test email dispatch, and settings import is validated using cryptographic WordPress nonces (`check_ajax_referer` / `wp_verify_nonce`).

### 4. SQL Injection Prevention
* All database interactions targeting custom tables (`wp_oi_email_logs`, `wp_oi_email_queue`) strictly utilize `$wpdb->prepare()` with parameterized placeholders (`%s`, `%d`). Raw SQL concatenation is forbidden.

### 5. Cross-Site Scripting (XSS) Mitigation
* All incoming input parameters are cleansed with `sanitize_text_field()`, `sanitize_email()`, `wp_unslash()`, and `absint()`.
* All output rendered in the WordPress admin panel is escaped using context-aware functions: `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()`.

### 6. Attachment & File Vault Security
* Downloaded and saved email attachments are placed in a dedicated, isolated subfolder under `wp-content/uploads/oi-mail-smtp/`.
* Direct script execution inside the attachment directory is disabled via `.htaccess` (`deny from all`) and `index.php` silencers.
* Attachment downloads require authenticated admin sessions and one-time verification tokens.

### 7. Privacy & GDPR Compliance
* **Data Minimization**: Website administrators can toggle email body logging off completely to comply with GDPR/HIPAA regulations.
* **Automatic Retention Cron**: Configurable retention limits (7, 14, 30, 60, 90 days) automatically purge historical logs and associated attachments.
* **Clean Uninstall**: If configured, all custom tables and options are completely wiped when the plugin is deleted via `uninstall.php`.

---

## Safe Harbor Policy

We consider security research conducted under this policy to be:
* Authorized in view of any applicable anti-hacking laws.
* Exempt from restrictions in our Terms of Service that would interfere with conducting security research.

As long as you act in good faith, avoid privacy violations, destruction of data, and service disruption, we will not pursue legal action against you.

Thank you for helping keep **Mail SMTP by Oi** and our community safe!
