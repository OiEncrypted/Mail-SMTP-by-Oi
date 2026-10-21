<?php
/**
 * Encryption Helper for Credentials & Passwords
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Encryption {

    /**
     * Cipher method
     *
     * @var string
     */
    private static $cipher = 'aes-256-cbc';

    /**
     * Prefix to identify encrypted strings
     *
     * @var string
     */
    private static $prefix = 'oi_enc_::';

    /**
     * Get encryption secret key derived from WordPress salts
     *
     * @return string
     */
    private static function get_key() {
        if (defined('AUTH_KEY') && AUTH_KEY) {
            $raw_key = AUTH_KEY . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'oi_fallback_salt');
        } elseif (function_exists('wp_salt')) {
            $raw_key = wp_salt('auth') . wp_salt('secure_auth');
        } else {
            $raw_key = 'oi_smtp_default_secure_key_hash_512';
        }
        return hash('sha256', $raw_key, true);
    }

    /**
     * Encrypt a string
     *
     * @param string $value
     * @return string
     */
    public static function encrypt($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        // Do not double encrypt
        if (strpos($value, self::$prefix) === 0) {
            return $value;
        }

        if (function_exists('openssl_encrypt')) {
            $iv_length = openssl_cipher_iv_length(self::$cipher);
            $iv = openssl_random_pseudo_bytes($iv_length);
            $encrypted = openssl_encrypt($value, self::$cipher, self::get_key(), 0, $iv);

            if ($encrypted !== false) {
                return self::$prefix . base64_encode($iv . '::' . $encrypted);
            }
        }

        // Fallback obfuscation if openssl_encrypt is disabled
        return self::$prefix . 'b64::' . base64_encode(str_rot13($value));
    }

    /**
     * Decrypt a string
     *
     * @param string $value
     * @return string
     */
    public static function decrypt($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        if (strpos($value, self::$prefix) !== 0) {
            // Not encrypted with our prefix
            return $value;
        }

        $raw = substr($value, strlen(self::$prefix));

        if (strpos($raw, 'b64::') === 0) {
            return str_rot13(base64_decode(substr($raw, 5)));
        }

        if (function_exists('openssl_decrypt')) {
            $decoded = base64_decode($raw);
            $parts = explode('::', $decoded, 2);

            if (count($parts) === 2) {
                list($iv, $cipher_text) = $parts;
                $decrypted = openssl_decrypt($cipher_text, self::$cipher, self::get_key(), 0, $iv);
                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
        }

        return $value;
    }
}
