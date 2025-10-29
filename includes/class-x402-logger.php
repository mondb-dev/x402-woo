<?php
/**
 * Logging utilities for X402 payment gateway.
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles logging for X402 payments using WooCommerce logger.
 */
class X402_Logger {

    /**
     * Logger instance.
     *
     * @var WC_Logger
     */
    private static $logger = null;

    /**
     * Log source identifier.
     */
    const SOURCE = 'x402-payments';

    /**
     * Get logger instance.
     *
     * @return WC_Logger|null
     */
    private static function get_logger() {
        if (null === self::$logger && function_exists('wc_get_logger')) {
            self::$logger = wc_get_logger();
        }

        return self::$logger;
    }

    /**
     * Log a debug message.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function debug($message, $context = array()) {
        $logger = self::get_logger();
        
        if ($logger) {
            $logger->debug($message, self::build_context($context));
        }
    }

    /**
     * Log an info message.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function info($message, $context = array()) {
        $logger = self::get_logger();
        
        if ($logger) {
            $logger->info($message, self::build_context($context));
        }
    }

    /**
     * Log a notice message.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function notice($message, $context = array()) {
        $logger = self::get_logger();
        
        if ($logger) {
            $logger->notice($message, self::build_context($context));
        }
    }

    /**
     * Log a warning message.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function warning($message, $context = array()) {
        $logger = self::get_logger();
        
        if ($logger) {
            $logger->warning($message, self::build_context($context));
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function error($message, $context = array()) {
        $logger = self::get_logger();
        
        if ($logger) {
            $logger->error($message, self::build_context($context));
        }
    }

    /**
     * Log a critical error message.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function critical($message, $context = array()) {
        $logger = self::get_logger();
        
        if ($logger) {
            $logger->critical($message, self::build_context($context));
        }
    }

    /**
     * Log payment verification attempt.
     *
     * @param int    $order_id The order ID.
     * @param bool   $success  Whether verification succeeded.
     * @param string $message  Optional message.
     */
    public static function log_payment_verification($order_id, $success, $message = '') {
        $context = array(
            'order_id' => $order_id,
            'success' => $success,
        );

        if (!empty($message)) {
            $context['message'] = $message;
        }

        if ($success) {
            self::info('Payment verification successful', $context);
        } else {
            self::warning('Payment verification failed', $context);
        }
    }

    /**
     * Log facilitator request.
     *
     * @param string $endpoint The facilitator endpoint.
     * @param array  $data     Request data.
     */
    public static function log_facilitator_request($endpoint, $data = array()) {
        self::debug('Facilitator request', array(
            'endpoint' => $endpoint,
            'data' => self::sanitize_log_data($data),
        ));
    }

    /**
     * Log facilitator response.
     *
     * @param int   $status_code HTTP status code.
     * @param array $response    Response data.
     */
    public static function log_facilitator_response($status_code, $response = array()) {
        $level = $status_code >= 200 && $status_code < 300 ? 'info' : 'error';
        
        self::$level('Facilitator response', array(
            'status_code' => $status_code,
            'response' => self::sanitize_log_data($response),
        ));
    }

    /**
     * Log webhook received.
     *
     * @param string $event     The webhook event type.
     * @param array  $payload   The webhook payload.
     * @param bool   $verified  Whether signature was verified.
     */
    public static function log_webhook($event, $payload, $verified) {
        self::info('Webhook received', array(
            'event' => $event,
            'verified' => $verified,
            'payload' => self::sanitize_log_data($payload),
        ));
    }

    /**
     * Log transaction details.
     *
     * @param int    $order_id Order ID.
     * @param string $tx_hash  Transaction hash.
     * @param array  $details  Additional transaction details.
     */
    public static function log_transaction($order_id, $tx_hash, $details = array()) {
        self::info('Transaction recorded', array(
            'order_id' => $order_id,
            'tx_hash' => $tx_hash,
            'details' => self::sanitize_log_data($details),
        ));
    }

    /**
     * Log API error.
     *
     * @param string    $endpoint  The API endpoint.
     * @param Exception $exception The exception that occurred.
     */
    public static function log_api_error($endpoint, Exception $exception) {
        self::error('API error', array(
            'endpoint' => $endpoint,
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
        ));
    }

    /**
     * Build context array for logging.
     *
     * @param array $context User-provided context.
     * @return array
     */
    private static function build_context($context) {
        $default_context = array(
            'source' => self::SOURCE,
            'timestamp' => current_time('mysql'),
        );

        // Add user info if available
        if (is_user_logged_in()) {
            $default_context['user_id'] = get_current_user_id();
        }

        return array_merge($default_context, $context);
    }

    /**
     * Sanitize sensitive data from logs.
     *
     * @param mixed $data The data to sanitize.
     * @return mixed
     */
    private static function sanitize_log_data($data) {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive_keys = array(
            'api_key',
            'secret',
            'password',
            'private_key',
            'token',
            'authorization',
        );

        foreach ($data as $key => $value) {
            $lower_key = strtolower($key);
            
            foreach ($sensitive_keys as $sensitive) {
                if (strpos($lower_key, $sensitive) !== false) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }

            if (is_array($value)) {
                $data[$key] = self::sanitize_log_data($value);
            }
        }

        return $data;
    }

    /**
     * Get recent log entries.
     *
     * @param int $limit Maximum number of entries to return.
     * @return array
     */
    public static function get_recent_logs($limit = 50) {
        if (!function_exists('wc_get_logger')) {
            return array();
        }

        $log_files = self::get_log_files();
        
        if (empty($log_files)) {
            return array();
        }

        $logs = array();
        $log_file = reset($log_files);
        $file_path = WC_LOG_DIR . $log_file;

        if (file_exists($file_path)) {
            $lines = file($file_path);
            $lines = array_slice($lines, -$limit);
            
            foreach ($lines as $line) {
                $logs[] = trim($line);
            }
        }

        return $logs;
    }

    /**
     * Get X402 log files.
     *
     * @return array
     */
    private static function get_log_files() {
        if (!defined('WC_LOG_DIR')) {
            return array();
        }

        $files = @scandir(WC_LOG_DIR);
        
        if (false === $files) {
            return array();
        }

        $log_files = array();
        
        foreach ($files as $file) {
            if (strpos($file, self::SOURCE) !== false && strpos($file, '.log') !== false) {
                $log_files[] = $file;
            }
        }

        rsort($log_files);
        
        return $log_files;
    }

    /**
     * Clear old log files.
     *
     * @param int $days Days to keep logs.
     */
    public static function clear_old_logs($days = 30) {
        $files = self::get_log_files();
        $cutoff = time() - ($days * DAY_IN_SECONDS);

        foreach ($files as $file) {
            $file_path = WC_LOG_DIR . $file;
            
            if (file_exists($file_path) && filemtime($file_path) < $cutoff) {
                @unlink($file_path);
            }
        }
    }
}
