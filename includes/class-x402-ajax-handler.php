<?php
/**
 * AJAX handlers for X402 payment gateway.
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles AJAX requests for X402 payments.
 */
class X402_AJAX_Handler {

    /**
     * Initialize AJAX hooks.
     */
    public static function init() {
        // Logged in users
        add_action('wp_ajax_x402_verify_payment', array(__CLASS__, 'verify_payment'));
        add_action('wp_ajax_x402_check_payment_status', array(__CLASS__, 'check_payment_status'));
        
        // Non-logged in users (for checkout)
        add_action('wp_ajax_nopriv_x402_verify_payment', array(__CLASS__, 'verify_payment'));
        add_action('wp_ajax_nopriv_x402_check_payment_status', array(__CLASS__, 'check_payment_status'));
        
        // Webhook handler
        add_action('wp_ajax_nopriv_x402_webhook', array(__CLASS__, 'handle_webhook'));
        add_action('wp_ajax_x402_webhook', array(__CLASS__, 'handle_webhook'));
        
        // Token price handlers
        add_action('wp_ajax_x402_get_token_price', array(__CLASS__, 'get_token_price'));
        add_action('wp_ajax_nopriv_x402_get_token_price', array(__CLASS__, 'get_token_price'));
        add_action('wp_ajax_x402_get_token_amount', array(__CLASS__, 'get_token_amount'));
        add_action('wp_ajax_nopriv_x402_get_token_amount', array(__CLASS__, 'get_token_amount'));
    }

    /**
     * Verify payment AJAX handler.
     */
    public static function verify_payment() {
        // Verify nonce
        if (!X402_Security_Handler::verify_nonce(X402_Security_Handler::VERIFY_PAYMENT_ACTION)) {
            X402_Security_Handler::log_security_event('Invalid nonce in verify_payment');
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'x402-solana-paywall'),
            ), 403);
        }

        // Get and validate order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_id = X402_Security_Handler::validate_order_id($order_id);

        if (!$order_id) {
            X402_Logger::error('Invalid order ID in AJAX verify_payment');
            wp_send_json_error(array(
                'message' => __('Invalid order ID.', 'x402-solana-paywall'),
            ), 400);
        }

        // Rate limiting
        if (!X402_Security_Handler::check_rate_limit('verify_payment_' . $order_id, 10, 60)) {
            X402_Security_Handler::log_security_event('Rate limit exceeded in verify_payment', array('order_id' => $order_id));
            wp_send_json_error(array(
                'message' => __('Too many verification attempts. Please wait a moment.', 'x402-solana-paywall'),
            ), 429);
        }

        $order = wc_get_order($order_id);

        // Check permissions
        if (!X402_Security_Handler::can_view_order($order)) {
            X402_Security_Handler::log_security_event('Unauthorized verify_payment attempt', array('order_id' => $order_id));
            wp_send_json_error(array(
                'message' => __('You do not have permission to verify this order.', 'x402-solana-paywall'),
            ), 403);
        }

        // Get and sanitize transaction hash
        $tx_hash = isset($_POST['tx_hash']) ? X402_Crypto_Validator::sanitize_tx_hash($_POST['tx_hash']) : '';

        if (empty($tx_hash)) {
            wp_send_json_error(array(
                'message' => __('Transaction hash is required.', 'x402-solana-paywall'),
            ), 400);
        }

        // Get network for validation
        $gateway = new X402_WooCommerce_Gateway();
        $network = $gateway->get_option('network', 'solana-devnet');

        // Validate transaction hash format
        if (!X402_Crypto_Validator::validate_tx_hash($tx_hash, $network)) {
            X402_Logger::warning('Invalid transaction hash format', array(
                'order_id' => $order_id,
                'tx_hash' => $tx_hash,
                'network' => $network,
            ));
            wp_send_json_error(array(
                'message' => __('Invalid transaction hash format.', 'x402-solana-paywall'),
            ), 400);
        }

        // Check if transaction already exists
        $existing_tx = X402_Installer::get_transaction($tx_hash);
        if ($existing_tx) {
            wp_send_json_success(array(
                'message' => __('Transaction already verified.', 'x402-solana-paywall'),
                'status' => $existing_tx->status,
            ));
            return;
        }

        // Perform verification (this would integrate with your actual verification logic)
        // For now, we'll store the transaction as pending
        $transaction_id = X402_Installer::insert_transaction(array(
            'order_id' => $order_id,
            'tx_hash' => $tx_hash,
            'wallet_address' => isset($_POST['wallet_address']) ? X402_Crypto_Validator::sanitize_address($_POST['wallet_address']) : '',
            'amount' => $order->get_total(),
            'asset' => $gateway->get_option('asset', ''),
            'network' => $network,
            'status' => 'pending',
            'verification_method' => 'manual',
        ));

        if ($transaction_id) {
            X402_Logger::info('Transaction submitted for verification', array(
                'order_id' => $order_id,
                'tx_hash' => $tx_hash,
                'transaction_id' => $transaction_id,
            ));

            wp_send_json_success(array(
                'message' => __('Transaction submitted for verification.', 'x402-solana-paywall'),
                'transaction_id' => $transaction_id,
            ));
        } else {
            X402_Logger::error('Failed to insert transaction', array(
                'order_id' => $order_id,
                'tx_hash' => $tx_hash,
            ));

            wp_send_json_error(array(
                'message' => __('Failed to submit transaction.', 'x402-solana-paywall'),
            ), 500);
        }
    }

    /**
     * Check payment status AJAX handler.
     */
    public static function check_payment_status() {
        // Verify nonce
        if (!X402_Security_Handler::verify_nonce(X402_Security_Handler::CHECK_STATUS_ACTION)) {
            X402_Security_Handler::log_security_event('Invalid nonce in check_payment_status');
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'x402-solana-paywall'),
            ), 403);
        }

        // Get and validate order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_id = X402_Security_Handler::validate_order_id($order_id);

        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('Invalid order ID.', 'x402-solana-paywall'),
            ), 400);
        }

        // Rate limiting
        if (!X402_Security_Handler::check_rate_limit('check_status_' . $order_id, 20, 60)) {
            wp_send_json_error(array(
                'message' => __('Too many status checks. Please wait a moment.', 'x402-solana-paywall'),
            ), 429);
        }

        $order = wc_get_order($order_id);

        // Check permissions
        if (!X402_Security_Handler::can_view_order($order)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to view this order.', 'x402-solana-paywall'),
            ), 403);
        }

        // Get transactions for this order
        $transactions = X402_Installer::get_order_transactions($order_id);

        if (empty($transactions)) {
            wp_send_json_success(array(
                'status' => 'no_transactions',
                'order_status' => $order->get_status(),
                'message' => __('No transactions found for this order.', 'x402-solana-paywall'),
            ));
            return;
        }

        $latest_transaction = $transactions[0];

        wp_send_json_success(array(
            'status' => $latest_transaction->status,
            'order_status' => $order->get_status(),
            'tx_hash' => $latest_transaction->tx_hash,
            'amount' => $latest_transaction->amount,
            'network' => $latest_transaction->network,
            'created_at' => $latest_transaction->created_at,
        ));
    }

    /**
     * Handle webhook from facilitator.
     */
    public static function handle_webhook() {
        // Get raw payload
        $payload = file_get_contents('php://input');

        // Get signature from header
        $signature = isset($_SERVER['HTTP_X_X402_SIGNATURE']) 
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_X402_SIGNATURE'])) 
            : '';

        // Verify signature
        if (!X402_Security_Handler::verify_webhook_signature($payload, $signature)) {
            X402_Security_Handler::log_security_event('Invalid webhook signature');
            X402_Logger::error('Webhook signature verification failed');
            status_header(401);
            die('Invalid signature');
        }

        // Parse and sanitize payload
        $data = X402_Security_Handler::sanitize_webhook_payload($payload);

        if (!$data) {
            X402_Logger::error('Invalid webhook payload');
            status_header(400);
            die('Invalid payload');
        }

        X402_Logger::log_webhook($data['event'] ?? 'unknown', $data, true);

        // Process webhook based on event type
        $event = $data['event'] ?? '';
        
        switch ($event) {
            case 'payment.verified':
                self::handle_payment_verified($data);
                break;
            case 'payment.settled':
                self::handle_payment_settled($data);
                break;
            case 'payment.failed':
                self::handle_payment_failed($data);
                break;
            default:
                X402_Logger::warning('Unknown webhook event', array('event' => $event));
        }

        status_header(200);
        die('OK');
    }

    /**
     * Handle payment verified webhook.
     *
     * @param array $data Webhook data.
     */
    private static function handle_payment_verified($data) {
        $order_id = isset($data['order_id']) ? absint($data['order_id']) : 0;
        $tx_hash = isset($data['tx_hash']) ? X402_Crypto_Validator::sanitize_tx_hash($data['tx_hash']) : '';

        if (!$order_id || !$tx_hash) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Update transaction status
        X402_Installer::update_transaction_status($tx_hash, 'verified');

        // Add order note
        $order->add_order_note(
            sprintf(
                __('Payment verified via webhook. Transaction: %s', 'x402-solana-paywall'),
                $tx_hash
            )
        );

        X402_Logger::info('Payment verified via webhook', array(
            'order_id' => $order_id,
            'tx_hash' => $tx_hash,
        ));
    }

    /**
     * Handle payment settled webhook.
     *
     * @param array $data Webhook data.
     */
    private static function handle_payment_settled($data) {
        $order_id = isset($data['order_id']) ? absint($data['order_id']) : 0;
        $tx_hash = isset($data['tx_hash']) ? X402_Crypto_Validator::sanitize_tx_hash($data['tx_hash']) : '';

        if (!$order_id || !$tx_hash) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Update transaction status
        X402_Installer::update_transaction_status($tx_hash, 'completed');

        // Complete order payment
        if (!$order->is_paid()) {
            $order->payment_complete($tx_hash);
        }

        $order->add_order_note(
            sprintf(
                __('Payment settled via webhook. Transaction: %s', 'x402-solana-paywall'),
                $tx_hash
            )
        );

        X402_Logger::info('Payment settled via webhook', array(
            'order_id' => $order_id,
            'tx_hash' => $tx_hash,
        ));
    }

    /**
     * Handle payment failed webhook.
     *
     * @param array $data Webhook data.
     */
    private static function handle_payment_failed($data) {
        $order_id = isset($data['order_id']) ? absint($data['order_id']) : 0;
        $tx_hash = isset($data['tx_hash']) ? X402_Crypto_Validator::sanitize_tx_hash($data['tx_hash']) : '';

        if (!$order_id || !$tx_hash) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Update transaction status
        X402_Installer::update_transaction_status($tx_hash, 'failed');

        $order->add_order_note(
            sprintf(
                __('Payment failed via webhook. Transaction: %s', 'x402-solana-paywall'),
                $tx_hash
            )
        );

        X402_Logger::warning('Payment failed via webhook', array(
            'order_id' => $order_id,
            'tx_hash' => $tx_hash,
        ));
    }

    /**
     * Get token price via AJAX.
     */
    public static function get_token_price() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'x402_get_price')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'x402-solana-paywall')), 403);
        }

        $token_network = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        
        if (empty($token_network)) {
            wp_send_json_error(array('message' => __('Token not specified.', 'x402-solana-paywall')), 400);
        }

        // Parse token and network
        $parts = explode('_', $token_network);
        if (count($parts) < 2) {
            wp_send_json_error(array('message' => __('Invalid token format.', 'x402-solana-paywall')), 400);
        }

        $symbol = $parts[0];
        $network = implode('_', array_slice($parts, 1));

        // Get token price
        $price = X402_Token_Handler::get_token_price($symbol, $network);

        if ($price === false) {
            wp_send_json_error(array('message' => __('Unable to fetch token price.', 'x402-solana-paywall')), 500);
        }

        $token_info = X402_Token_Handler::get_token_info($symbol, $network);

        wp_send_json_success(array(
            'price' => $price,
            'formatted_price' => '$' . number_format($price, 2),
            'symbol' => $symbol,
            'network' => $network,
            'decimals' => $token_info['decimals'] ?? 6,
        ));
    }

    /**
     * Get token amount for fiat value via AJAX.
     */
    public static function get_token_amount() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'x402_get_price')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'x402-solana-paywall')), 403);
        }

        $token_network = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $fiat_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

        if (empty($token_network) || $fiat_amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'x402-solana-paywall')), 400);
        }

        // Parse token and network
        $parts = explode('_', $token_network);
        if (count($parts) < 2) {
            wp_send_json_error(array('message' => __('Invalid token format.', 'x402-solana-paywall')), 400);
        }

        $symbol = $parts[0];
        $network = implode('_', array_slice($parts, 1));

        // Check if it's a custom token request
        if ($token_network === 'custom') {
            // Get custom token from gateway settings
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (isset($gateways['x402'])) {
                $gateway = $gateways['x402'];
                $token_info = $gateway->get_active_token();
                
                if (!$token_info) {
                    wp_send_json_error(array('message' => __('Custom token not configured.', 'x402-solana-paywall')), 400);
                }
                
                // Get price
                $price = false;
                if (isset($token_info['manual_price']) && $token_info['manual_price'] !== false) {
                    $price = $token_info['manual_price'];
                } elseif (isset($token_info['coingecko_id'])) {
                    $price = X402_Token_Handler::get_token_price($token_info['symbol'], $token_info['network']);
                }
                
                if (!$price) {
                    wp_send_json_error(array('message' => __('Token price not available.', 'x402-solana-paywall')), 500);
                }
                
                $token_amount = number_format($fiat_amount / $price, $token_info['decimals'], '.', '');
                $symbol = $token_info['symbol'];
                $network = $token_info['network'];
            } else {
                wp_send_json_error(array('message' => __('Payment gateway not available.', 'x402-solana-paywall')), 500);
            }
        } else {
            // Convert fiat to token amount for standard tokens
            $token_amount = X402_Token_Handler::convert_fiat_to_token($fiat_amount, $symbol, $network);

            if ($token_amount === false) {
                wp_send_json_error(array('message' => __('Unable to calculate token amount.', 'x402-solana-paywall')), 500);
            }

            $token_info = X402_Token_Handler::get_token_info($symbol, $network);
        }

        wp_send_json_success(array(
            'token_amount' => $token_amount,
            'formatted_amount' => $token_amount . ' ' . $symbol,
            'symbol' => $symbol,
            'network' => $network,
            'fiat_amount' => $fiat_amount,
            'token_info' => $token_info,
        ));
    }
}

// Initialize AJAX handlers
add_action('init', array('X402_AJAX_Handler', 'init'));
