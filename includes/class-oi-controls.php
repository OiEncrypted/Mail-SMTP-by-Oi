<?php
/**
 * WordPress Core Automated Email Controls
 *
 * Allows website administrators to suppress or disable specific automated
 * core WordPress notifications (comments, updates, password change alerts).
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Controls {

    /**
     * Initialize email control filters
     */
    public static function init() {
        $settings = OI_Options::get_all(false);
        $controls = !empty($settings['email_controls']) && is_array($settings['email_controls']) ? $settings['email_controls'] : [];

        if (empty($controls)) {
            return;
        }

        // 1. Comments: Awaiting moderation
        if (!empty($controls['dis_comment_moderation'])) {
            add_filter('comment_moderation_recipients', '__return_empty_array', 999);
        }

        // 2. Comments: Published notification to author
        if (!empty($controls['dis_comment_published'])) {
            add_filter('comment_notification_recipients', '__return_empty_array', 999);
        }

        // 3. User details: Password change notification to user
        if (!empty($controls['dis_password_change_user'])) {
            add_filter('send_password_change_email', '__return_false', 999);
        }

        // 4. User details: Email change notification
        if (!empty($controls['dis_email_change'])) {
            add_filter('send_email_change_email', '__return_false', 999);
        }

        // 5. User registration: New user admin alert
        if (!empty($controls['dis_new_user_admin'])) {
            add_filter('wp_send_new_user_notification_to_admin', '__return_false', 999);
        }

        // 6. Automatic WordPress core background update successful
        if (!empty($controls['dis_auto_core_update'])) {
            add_filter('auto_core_update_send_email', '__return_false', 999);
            add_filter('send_core_update_notification_email', '__return_false', 999);
        }
    }
}
