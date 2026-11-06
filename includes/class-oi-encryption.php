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
            $iv = function_exists('random_bytes') ? random_bytes($iv_length) : openssl_random_pseudo_bytes($iv_length);
            $encrypted = openssl_encrypt($value, self::$cipher, self::get_key(), OPENSSL_RAW_DATA, $iv);

            if ($encrypted !== false) {
                // v2 format: Store IV directly prefixed to ciphertext in base64 (immune to delimiter splitting)
                return self::$prefix . 'v2::' . base64_encode($iv . $encrypted);
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

        // Modern v2 format
        if (strpos($raw, 'v2::') === 0) {
            if (function_exists('openssl_decrypt')) {
                $decoded = base64_decode(substr($raw, 4));
                $iv_length = openssl_cipher_iv_length(self::$cipher);
                if (strlen($decoded) > $iv_length) {
                    $iv = substr($decoded, 0, $iv_length);
                    $cipher_text = substr($decoded, $iv_length);
                    $decrypted = openssl_decrypt($cipher_text, self::$cipher, self::get_key(), OPENSSL_RAW_DATA, $iv);
                    if ($decrypted !== false) {
                        return $decrypted;
                    }
                }
            }
        }

        // Legacy v1 format compatibility (backward compatibility)
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
