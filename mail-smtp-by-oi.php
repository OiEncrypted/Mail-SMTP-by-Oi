<?php
/**
 * Plugin Name: Mail SMTP by Oi
 * Plugin URI: https://oitech.com.bd/open-source/
 * Description: High-performance, secure SMTP and transactional email plugin for WordPress. Connect Custom SMTP, Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, and Resend with comprehensive email logging, 1-click resend, and live diagnostics.
 * Version: 1.0.0
 * Author: Oi Technologies
 * Author URI: https://oitech.com.bd/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: mail-smtp-by-oi
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

// Plugin version and constants
define('OI_MAIL_SMTP_VERSION', '1.0.0');
define('OI_MAIL_SMTP_FILE', __FILE__);
define('OI_MAIL_SMTP_PATH', plugin_dir_path(__FILE__));
define('OI_MAIL_SMTP_URL', plugin_dir_url(__FILE__));
define('OI_MAIL_SMTP_BASENAME', plugin_basename(__FILE__));

// Require the core bootstrap class
require_once OI_MAIL_SMTP_PATH . 'includes/class-oi-mail-smtp.php';

/**
 * Main instance of Mail SMTP by Oi.
 *
 * @return OI_Mail_SMTP
 */
function oi_mail_smtp() {
    return OI_Mail_SMTP::instance();
}

// Activation hook
register_activation_hook(__FILE__, ['OI_Mail_SMTP', 'activate']);

// Deactivation hook
register_deactivation_hook(__FILE__, ['OI_Mail_SMTP', 'deactivate']);

// Initialize the plugin
add_action('plugins_loaded', 'oi_mail_smtp');
