<?php
/**
 * Payment processing class for X402 Solana Paywall
 * Handles Solana blockchain payment verification
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * X402 Payment Class
 */
class X402_Payment {
    
    /**
     * Verify payment transaction on Solana blockchain
     *
     * @param string $signature Transaction signature
     * @param int $post_id Post ID
     * @param string $wallet_address Wallet address
     * @return array Result array with success status and data
     */
    public static function verify_transaction($signature, $post_id, $wallet_address) {
        // Sanitize inputs
        $signature = X402_Security::sanitize_signature($signature);
        $wallet_address = X402_Security::sanitize_wallet_address($wallet_address);
        $post_id = absint($post_id);
        
        // Validate inputs
        if (empty($signature) || empty($wallet_address) || empty($post_id)) {
            return array(
                'success' => false,
                'message' => __('Invalid payment data', 'x402-solana-paywall')
            );
        }
        
        // Check if transaction already exists
        $existing = X402_Database::get_payment_by_signature($signature);
        if ($existing) {
            if ($existing->status === 'verified') {
                return array(
                    'success' => true,
                    'message' => __('Payment already verified', 'x402-solana-paywall'),
                    'session_token' => $existing->session_token
                );
            }
        }
        
        // Get required payment amount
        $required_amount = self::get_post_payment_amount($post_id);
        if ($required_amount <= 0) {
            return array(
                'success' => false,
                'message' => __('Content is not behind a paywall', 'x402-solana-paywall')
            );
        }
        
        // Verify transaction on Solana blockchain
        $verification = self::verify_on_chain($signature, $wallet_address, $required_amount);
        
        if (!$verification['success']) {
            // Log failed verification
            X402_Security::log_security_event('payment_verification_failed', array(
                'signature' => substr($signature, 0, 20) . '...',
                'post_id' => $post_id,
                'reason' => $verification['message']
            ));
            
            return $verification;
        }
        
        // Generate session token
        $session_token = X402_Security::generate_session_token($post_id, $wallet_address);
        
        // Calculate expiration (24 hours from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Record payment in database
        $payment_id = X402_Database::record_payment(array(
            'post_id' => $post_id,
            'wallet_address' => $wallet_address,
            'transaction_signature' => $signature,
            'amount' => $verification['amount'],
            'currency' => 'SOL',
            'status' => 'verified',
            'session_token' => $session_token,
            'expires_at' => $expires_at,
            'encrypted_data' => json_encode($verification['transaction_data'])
        ));
        
        if (!$payment_id) {
            return array(
                'success' => false,
                'message' => __('Failed to record payment', 'x402-solana-paywall')
            );
        }
        
        // Log successful payment
        X402_Security::log_security_event('payment_verified', array(
            'payment_id' => $payment_id,
            'post_id' => $post_id,
            'amount' => $verification['amount']
        ));
        
        return array(
            'success' => true,
            'message' => __('Payment verified successfully', 'x402-solana-paywall'),
            'session_token' => $session_token,
            'expires_at' => $expires_at
        );
    }
    
    /**
     * Verify transaction on Solana blockchain
     * This is a placeholder that would connect to actual Solana RPC in production
     *
     * @param string $signature Transaction signature
     * @param string $wallet_address Wallet address
     * @param float $required_amount Required amount
     * @return array
     */
    private static function verify_on_chain($signature, $wallet_address, $required_amount) {
        // Get Solana network configuration
        $network = get_option('x402_solana_network', 'mainnet-beta');
        $rpc_endpoint = self::get_rpc_endpoint($network);
        
        // In production, this would make an actual RPC call to Solana
        // Example using WordPress HTTP API:
        /*
        $response = wp_remote_post($rpc_endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getTransaction',
                'params' => array(
                    $signature,
                    array('encoding' => 'json', 'commitment' => 'confirmed')
                )
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Failed to connect to Solana network', 'x402-solana-paywall')
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return array(
                'success' => false,
                'message' => __('Transaction not found on blockchain', 'x402-solana-paywall')
            );
        }
        
        // Verify transaction details
        $transaction = $body['result'];
        // ... verify amount, recipient, etc.
        */
        
        // For now, return simulated verification
        // In production, replace this with actual blockchain verification
        return array(
            'success' => true,
            'amount' => $required_amount,
            'transaction_data' => array(
                'signature' => $signature,
                'wallet' => $wallet_address,
                'timestamp' => time(),
                'network' => $network
            )
        );
    }
    
    /**
     * Get RPC endpoint for Solana network
     *
     * @param string $network Network name
     * @return string
     */
    private static function get_rpc_endpoint($network) {
        $endpoints = array(
            'mainnet-beta' => 'https://api.mainnet-beta.solana.com',
            'testnet' => 'https://api.testnet.solana.com',
            'devnet' => 'https://api.devnet.solana.com',
        );
        
        // Allow custom RPC endpoint
        $custom_endpoint = get_option('x402_custom_rpc_endpoint');
        if (!empty($custom_endpoint)) {
            return esc_url_raw($custom_endpoint);
        }
        
        return isset($endpoints[$network]) ? $endpoints[$network] : $endpoints['mainnet-beta'];
    }
    
    /**
     * Get payment amount required for a post
     *
     * @param int $post_id Post ID
     * @return float
     */
    public static function get_post_payment_amount($post_id) {
        $amount = get_post_meta($post_id, '_x402_payment_amount', true);
        return !empty($amount) ? floatval($amount) : 0;
    }
    
    /**
     * Check if post requires payment
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function is_post_protected($post_id) {
        $enabled = get_post_meta($post_id, '_x402_paywall_enabled', true);
        $amount = self::get_post_payment_amount($post_id);
        
        return $enabled === '1' && $amount > 0;
    }
    
    /**
     * Validate user access to content
     *
     * @param int $post_id Post ID
     * @param string $session_token Session token from cookie or request
     * @return bool
     */
    public static function validate_access($post_id, $session_token = null) {
        // Admins and post authors always have access
        if (current_user_can('edit_post', $post_id)) {
            return true;
        }
        
        // Check if content is protected
        if (!self::is_post_protected($post_id)) {
            return true;
        }
        
        // Get session token from cookie if not provided
        if (empty($session_token)) {
            $cookie_name = 'x402_session_' . $post_id;
            $session_token = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : '';
        }
        
        if (empty($session_token)) {
            return false;
        }
        
        // Verify session token in database
        $payment = X402_Database::get_payment_by_token($session_token);
        
        if (!$payment || $payment->post_id != $post_id) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Set access cookie for user
     *
     * @param int $post_id Post ID
     * @param string $session_token Session token
     * @param int $expires Expiration timestamp
     */
    public static function set_access_cookie($post_id, $session_token, $expires) {
        $cookie_name = 'x402_session_' . $post_id;
        $expire_time = strtotime($expires);
        
        setcookie(
            $cookie_name,
            $session_token,
            $expire_time,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // HttpOnly flag for security
        );
    }
}
