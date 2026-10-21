<?php
/**
 * Provider Interface
 *
 * @package Mail_SMTP_By_Oi
 */

defined('ABSPATH') || exit;

interface OI_Provider_Interface {

    /**
     * Get Provider Identifier
     *
     * @return string
     */
    public function get_id();

    /**
     * Get Provider Display Name
     *
     * @return string
     */
    public function get_name();

    /**
     * Send email via this provider
     *
     * @param array $mail_data Normalized email data array
     * @param array $settings Plugin settings
     * @return array ['success' => bool, 'message' => string, 'debug' => string]
     */
    public function send(array $mail_data, array $settings);
}
