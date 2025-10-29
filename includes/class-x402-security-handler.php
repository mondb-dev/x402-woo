<?php
/**
 * Security enhancements for X402 payment gateway.
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles security operations for X402 payments.
 */
class X402_Security_Handler {

    /**
     * Webhook secret option name.
     */
    const WEBHOOK_SECRET_OPTION = 'x402_webhook_secret';

    /**
     * Nonce action for AJAX payment verification.
     */
    const VERIFY_PAYMENT_ACTION = 'x402_verify_payment';

    /**
     * Nonce action for AJAX payment status check.
     */
    const CHECK_STATUS_ACTION = 'x402_check_status';

    /**
     * Initialize security hooks.
     */
    public static function init() {
        // Generate webhook secret if not exists
        if (!get_option(self::WEBHOOK_SECRET_OPTION)) {
            self::generate_webhook_secret();
        }
    }

    /**
     * Generate and store webhook secret.
     *
     * @return string The generated secret.
     */
    public static function generate_webhook_secret() {
        $secret = wp_generate_password(64, true, true);
        update_option(self::WEBHOOK_SECRET_OPTION, $secret);
        return $secret;
    }

    /**
     * Get webhook secret.
     *
     * @return string
     */
    public static function get_webhook_secret() {
        $secret = get_option(self::WEBHOOK_SECRET_OPTION);
        
        if (!$secret) {
            $secret = self::generate_webhook_secret();
        }

        return $secret;
    }

    /**
     * Verify webhook signature.
     *
     * @param string $payload   The raw request payload.
     * @param string $signature The signature from the header.
     * @return bool True if valid, false otherwise.
     */
    public static function verify_webhook_signature($payload, $signature) {
        if (empty($signature)) {
            return false;
        }

        $secret = self::get_webhook_secret();
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Verify nonce for AJAX requests.
     *
     * @param string $action The nonce action.
     * @param string $nonce  The nonce value to verify.
     * @return bool True if valid, false otherwise.
     */
    public static function verify_nonce($action, $nonce = '') {
        if (empty($nonce)) {
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        }

        if (empty($nonce)) {
            return false;
        }

        return wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Create nonce for AJAX requests.
     *
     * @param string $action The nonce action.
     * @return string The nonce value.
     */
    public static function create_nonce($action) {
        return wp_create_nonce($action);
    }

    /**
     * Check if user has permission to manage X402 payments.
     *
     * @return bool
     */
    public static function can_manage_payments() {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Check if user can view order payment details.
     *
     * @param WC_Order $order The order to check.
     * @return bool
     */
    public static function can_view_order($order) {
        if (!$order instanceof WC_Order) {
            return false;
        }

        // Allow admin
        if (self::can_manage_payments()) {
            return true;
        }

        // Allow order owner
        $user_id = get_current_user_id();
        if ($user_id > 0 && $order->get_customer_id() === $user_id) {
            return true;
        }

        // Allow with valid order key
        $order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if (!empty($order_key) && $order->get_order_key() === $order_key) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize and validate order ID.
     *
     * @param mixed $order_id The order ID to validate.
     * @return int|false Valid order ID or false.
     */
    public static function validate_order_id($order_id) {
        $order_id = absint($order_id);

        if ($order_id <= 0) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        return $order_id;
    }

    /**
     * Rate limit check for IP address.
     *
     * @param string $key           The rate limit key.
     * @param int    $max_attempts  Maximum attempts allowed.
     * @param int    $time_window   Time window in seconds.
     * @return bool True if allowed, false if rate limited.
     */
    public static function check_rate_limit($key, $max_attempts = 10, $time_window = 60) {
        $ip = self::get_client_ip();
        $transient_key = 'x402_rate_limit_' . md5($key . '_' . $ip);
        
        $attempts = get_transient($transient_key);
        
        if (false === $attempts) {
            set_transient($transient_key, 1, $time_window);
            return true;
        }

        if ($attempts >= $max_attempts) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, $time_window);
        return true;
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    public static function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = explode(',', $ip);
            $ip = trim($ip[0]);
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return $ip;
    }

    /**
     * Log security event.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context.
     */
    public static function log_security_event($message, $context = array()) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context['source'] = 'x402-security';
            $context['ip'] = self::get_client_ip();
            $context['user_id'] = get_current_user_id();
            
            $logger->warning($message, $context);
        }
    }

    /**
     * Validate payment request headers for security.
     *
     * @param array $headers The headers to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_payment_headers($headers) {
        if (!is_array($headers)) {
            return false;
        }

        // Check for required X402 headers
        $required_headers = array('x-payment', 'x-payment-signature');
        
        foreach ($required_headers as $header) {
            $found = false;
            foreach ($headers as $key => $value) {
                if (strtolower($key) === $header) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                self::log_security_event('Missing required payment header: ' . $header);
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize webhook payload.
     *
     * @param string $payload The raw payload.
     * @return array|null Sanitized payload or null on error.
     */
    public static function sanitize_webhook_payload($payload) {
        if (empty($payload)) {
            return null;
        }

        $data = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log_security_event('Invalid JSON in webhook payload');
            return null;
        }

        return $data;
    }

    /**
     * Generate secure token for order verification.
     *
     * @param int $order_id The order ID.
     * @return string
     */
    public static function generate_order_token($order_id) {
        return hash_hmac('sha256', $order_id . time(), wp_salt('nonce'));
    }

    /**
     * Verify order token.
     *
     * @param int    $order_id The order ID.
     * @param string $token    The token to verify.
     * @param int    $max_age  Maximum age in seconds (default 1 hour).
     * @return bool
     */
    public static function verify_order_token($order_id, $token, $max_age = 3600) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }

        $stored_token = $order->get_meta('_x402_verification_token');
        $token_time = $order->get_meta('_x402_verification_token_time');

        if (empty($stored_token) || empty($token_time)) {
            return false;
        }

        // Check if token has expired
        if (time() - $token_time > $max_age) {
            return false;
        }

        return hash_equals($stored_token, $token);
    }
}

// Initialize security on plugin load
add_action('plugins_loaded', array('X402_Security_Handler', 'init'));
