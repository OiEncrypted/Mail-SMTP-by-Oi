<?php
/**
 * Email Open & Link Click Tracking Engine
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Tracker {

    /**
     * 1x1 Transparent GIF Binary
     */
    const PIXEL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Initialize tracker endpoints
     */
    public static function init() {
        add_action('init', [__CLASS__, 'handle_tracking_request'], 1);
    }

    /**
     * Intercept tracking requests on frontend
     */
    public static function handle_tracking_request() {
        // 1. Handle Email Open Pixel
        if (isset($_GET['oi_track_open'])) {
            $hash = sanitize_text_field(wp_unslash($_GET['oi_track_open']));
            self::record_open($hash);
            self::render_pixel_and_exit();
        }

        // 2. Handle Link Click Redirect
        if (isset($_GET['oi_track_click']) && isset($_GET['url'])) {
            $hash = sanitize_text_field(wp_unslash($_GET['oi_track_click']));
            $target = esc_url_raw(wp_unslash($_GET['url']));
            self::record_click($hash);

            if (!empty($target)) {
                $scheme = wp_parse_url($target, PHP_URL_SCHEME);
                if (in_array($scheme, ['http', 'https'], true)) {
                    wp_redirect($target, 302);
                    exit;
                }
            }

            wp_safe_redirect(home_url());
            exit;
        }
    }

    /**
     * Record an email open event in database
     *
     * @param string $hash
     */
    public static function record_open($hash) {
        if (empty($hash) || strlen($hash) < 16) {
            return;
        }

        global $wpdb;
        $table = OI_Logger::get_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET open_count = open_count + 1, last_opened_at = %s WHERE tracking_hash = %s",
                current_time('mysql'),
                $hash
            )
        );
    }

    /**
     * Record a link click event in database
     *
     * @param string $hash
     */
    public static function record_click($hash) {
        if (empty($hash) || strlen($hash) < 16) {
            return;
        }

        global $wpdb;
        $table = OI_Logger::get_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET click_count = click_count + 1, last_clicked_at = %s WHERE tracking_hash = %s",
                current_time('mysql'),
                $hash
            )
        );
    }

    /**
     * Render the 1x1 transparent GIF with no-cache headers and exit
     */
    private static function render_pixel_and_exit() {
        while (ob_get_level()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: image/gif');
        header('Content-Length: 43');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

        echo base64_decode(self::PIXEL_GIF_BASE64);
        exit;
    }

    /**
     * Process email HTML message body to inject tracking pixel and link wrappers
     *
     * @param string $message
     * @param string $tracking_hash
     * @return string
     */
    public static function process_email_body($message, $tracking_hash) {
        if (empty($message) || empty($tracking_hash)) {
            return $message;
        }

        $track_opens  = (bool)OI_Options::get('enable_open_tracking', false);
        $track_clicks = (bool)OI_Options::get('enable_click_tracking', false);

        if (!$track_opens && !$track_clicks) {
            return $message;
        }

        // Click tracking: rewrite links
        if ($track_clicks) {
            $site_url = home_url('/');
            $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(https?:\/\/[^"\']+)["\']/i';
            $message = preg_replace_callback($pattern, function($matches) use ($tracking_hash, $site_url) {
                $original_url = $matches[2];

                // Do not track internal tracking URLs or anchors
                if (strpos($original_url, 'oi_track_') !== false || strpos($original_url, '#') === 0) {
                    return $matches[0];
                }

                $tracking_url = add_query_arg([
                    'oi_track_click' => $tracking_hash,
                    'url'            => $original_url,
                ], $site_url);

                return str_replace($matches[2], esc_url($tracking_url), $matches[0]);
            }, $message);
        }

        // Open tracking: inject 1x1 tracking pixel
        if ($track_opens) {
            $pixel_url = add_query_arg([
                'oi_track_open' => $tracking_hash,
            ], home_url('/'));

            $pixel_tag = sprintf(
                '<img src="%s" width="1" height="1" style="display:none!important;width:1px!important;height:1px!important;max-height:0!important;max-width:0!important;opacity:0!important;overflow:hidden!important;" alt="" />',
                esc_url($pixel_url)
            );

            // Inject before closing </body> if present, otherwise append
            if (stripos($message, '</body>') !== false) {
                $message = str_ireplace('</body>', $pixel_tag . '</body>', $message);
            } else {
                $message .= "\n" . $pixel_tag;
            }
        }

        return $message;
    }

    /**
     * Generate unique tracking hash
     *
     * @return string
     */
    public static function generate_hash() {
        return wp_generate_password(32, false, false);
    }
}
