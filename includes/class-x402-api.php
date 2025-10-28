<?php
/**
 * API class for X402 Solana Paywall
 * Handles AJAX endpoints for payment verification
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * X402 API Class
 */
class X402_API {
    
    /**
     * Initialize API endpoints
     */
    public static function init() {
        // AJAX endpoints for logged in users
        add_action('wp_ajax_x402_verify_payment', array(__CLASS__, 'verify_payment'));
        
        // AJAX endpoints for non-logged in users
        add_action('wp_ajax_nopriv_x402_verify_payment', array(__CLASS__, 'verify_payment'));
    }
    
    /**
     * Verify payment AJAX handler
     */
    public static function verify_payment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !X402_Security::verify_nonce($_POST['nonce'])) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'x402-solana-paywall')
            ), 403);
        }
        
        // Get and validate input
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $signature = isset($_POST['signature']) ? sanitize_text_field($_POST['signature']) : '';
        $wallet_address = isset($_POST['wallet_address']) ? sanitize_text_field($_POST['wallet_address']) : '';
        
        if (empty($post_id) || empty($signature) || empty($wallet_address)) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters', 'x402-solana-paywall')
            ), 400);
        }
        
        // Verify the payment
        $result = X402_Payment::verify_transaction($signature, $post_id, $wallet_address);
        
        if (!$result['success']) {
            wp_send_json_error(array(
                'message' => $result['message']
            ), 400);
        }
        
        // Set access cookie
        if (isset($result['session_token']) && isset($result['expires_at'])) {
            X402_Payment::set_access_cookie($post_id, $result['session_token'], $result['expires_at']);
        }
        
        // Return success response
        wp_send_json_success(array(
            'message' => $result['message'],
            'reload' => true
        ));
    }
}
