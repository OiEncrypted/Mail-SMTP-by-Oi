<?php
/**
 * Smart Conditional Email Routing Engine
 *
 * Dynamically routes emails to different mailers based on subject, sender,
 * recipient, or source headers (e.g. WooCommerce, Contact Forms, Newsletters).
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

class OI_Routing {

    /**
     * Evaluate routing rules for an email and return the matched mailer key
     *
     * @param array $mail_data
     * @param string $default_mailer
     * @return string Matched mailer key
     */
    public static function resolve_mailer(array $mail_data, $default_mailer) {
        $rules = OI_Options::get('routing_rules', []);
        if (empty($rules) || !is_array($rules)) {
            return $default_mailer;
        }

        foreach ($rules as $rule) {
            if (empty($rule['mailer']) || empty($rule['field']) || !isset($rule['value'])) {
                continue;
            }

            if (self::matches_rule($rule, $mail_data)) {
                return sanitize_key($rule['mailer']);
            }
        }

        return $default_mailer;
    }

    /**
     * Check if a specific rule matches the email data
     *
     * @param array $rule
     * @param array $mail_data
     * @return bool
     */
    private static function matches_rule(array $rule, array $mail_data) {
        $field     = sanitize_key($rule['field'] ?? 'subject');
        $operator  = sanitize_key($rule['operator'] ?? 'contains');
        $match_val = strtolower(trim((string)$rule['value']));

        if ($match_val === '') {
            return false;
        }

        $subject = strtolower((string)($mail_data['subject'] ?? ''));
        $from    = strtolower((string)($mail_data['from_email'] ?? ''));
        $to      = strtolower(is_array($mail_data['to'] ?? '') ? implode(',', $mail_data['to']) : (string)($mail_data['to'] ?? ''));
        $headers = strtolower(is_array($mail_data['headers'] ?? '') ? implode("\n", $mail_data['headers']) : (string)($mail_data['headers'] ?? ''));

        $target_val = '';
        switch ($field) {
            case 'subject':
                $target_val = $subject;
                break;
            case 'from':
            case 'from_email':
                $target_val = $from;
                break;
            case 'to':
            case 'to_email':
                $target_val = $to;
                break;
            case 'headers':
                $target_val = $headers;
                break;
            default:
                return false;
        }

        switch ($operator) {
            case 'equals':
                return $target_val === $match_val;
            case 'starts_with':
                return strpos($target_val, $match_val) === 0;
            case 'ends_with':
                return substr($target_val, -strlen($match_val)) === $match_val;
            case 'contains':
            default:
                return strpos($target_val, $match_val) !== false;
        }
    }
}
