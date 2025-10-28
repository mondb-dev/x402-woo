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

    /** Lamports in one SOL */
    const LAMPORTS_PER_SOL = 1000000000;
    
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
        $signature      = X402_Security::sanitize_signature($signature);
        $wallet_address = X402_Security::sanitize_wallet_address($wallet_address);
        $post_id        = absint($post_id);
        
        // Validate inputs
        if (empty($signature) || empty($wallet_address) || empty($post_id)) {
            return array(
                'success' => false,
                'message' => __('Invalid payment data', 'x402-solana-paywall')
            );
        }
        
        // Ensure merchant wallet is configured
        $merchant_wallet = X402_Security::sanitize_wallet_address(get_option('x402_merchant_wallet', ''));

        if (empty($merchant_wallet)) {
            return array(
                'success' => false,
                'message' => __('Merchant wallet is not configured. Please contact the site administrator.', 'x402-solana-paywall')
            );
        }

        // Check if transaction already exists
        $existing = X402_Database::get_payment_by_signature($signature);
        if ($existing) {
            if ((int) $existing->post_id !== $post_id) {
                return array(
                    'success' => false,
                    'message' => __('This transaction has already been used for different content.', 'x402-solana-paywall')
                );
            }

            if ($existing->status === 'verified') {
                return array(
                    'success' => true,
                    'message' => __('Payment already verified', 'x402-solana-paywall'),
                    'session_token' => $existing->session_token
                );
            }
        }
        
        // Get required payment amount
        $required_amount = max(0, round(self::get_post_payment_amount($post_id), 9));
        if ($required_amount <= 0) {
            return array(
                'success' => false,
                'message' => __('Content is not behind a paywall', 'x402-solana-paywall')
            );
        }
        
        // Verify transaction on Solana blockchain
        $verification = self::verify_on_chain($signature, $wallet_address, $merchant_wallet, $required_amount);

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

        // Calculate expiration respecting settings
        $session_timeout = absint(get_option('x402_session_timeout', 3600));

        if ($session_timeout < MINUTE_IN_SECONDS) {
            $session_timeout = MINUTE_IN_SECONDS;
        }

        $expires_timestamp = current_time('timestamp', true) + $session_timeout;
        $expires_at        = gmdate('Y-m-d H:i:s', $expires_timestamp);

        $verified_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true));

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
            'verified_at' => $verified_at,
            'encrypted_data' => wp_json_encode($verification['transaction_data'])
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
     * Verify transaction on the Solana blockchain against the configured merchant wallet.
     *
     * @param string $signature        Transaction signature
     * @param string $wallet_address   Wallet address supplied by the customer
     * @param string $merchant_wallet  Merchant wallet configured in settings
     * @param float  $required_amount  Required amount for the post in SOL
     * @return array
     */
    private static function verify_on_chain($signature, $wallet_address, $merchant_wallet, $required_amount) {
        // Get Solana network configuration
        $network      = get_option('x402_solana_network', 'mainnet-beta');
        $rpc_endpoint = self::get_rpc_endpoint($network);

        if (empty($rpc_endpoint)) {
            return array(
                'success' => false,
                'message' => __('Invalid Solana RPC endpoint configuration.', 'x402-solana-paywall')
            );
        }

        $payload = array(
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'getTransaction',
            'params'  => array(
                $signature,
                array(
                    'encoding'   => 'json',
                    'commitment' => 'confirmed',
                    'maxSupportedTransactionVersion' => 0,
                ),
            ),
        );

        $response = wp_safe_remote_post(
            $rpc_endpoint,
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode($payload),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Failed to connect to the Solana network. Please try again shortly.', 'x402-solana-paywall')
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return array(
                'success' => false,
                'message' => __('Unexpected response from the Solana RPC endpoint.', 'x402-solana-paywall')
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || isset($body['error'])) {
            return array(
                'success' => false,
                'message' => __('Transaction not found on the blockchain.', 'x402-solana-paywall')
            );
        }

        if (empty($body['result']) || !is_array($body['result'])) {
            return array(
                'success' => false,
                'message' => __('Invalid transaction payload received from Solana.', 'x402-solana-paywall')
            );
        }

        $result = $body['result'];

        if (!empty($result['meta']['err'])) {
            return array(
                'success' => false,
                'message' => __('The transaction failed on-chain.', 'x402-solana-paywall')
            );
        }

        if (!empty($result['meta']['confirmationStatus']) && !in_array($result['meta']['confirmationStatus'], array('confirmed', 'finalized'), true)) {
            return array(
                'success' => false,
                'message' => __('The transaction is not yet confirmed on Solana.', 'x402-solana-paywall')
            );
        }

        $block_time = isset($result['blockTime']) ? absint($result['blockTime']) : 0;
        $current_utc = current_time('timestamp', true);

        if ($block_time && ($current_utc - $block_time) > DAY_IN_SECONDS) {
            return array(
                'success' => false,
                'message' => __('The transaction is too old to be used for this purchase.', 'x402-solana-paywall')
            );
        }

        $account_keys = self::extract_account_keys($result);

        if (empty($account_keys)) {
            return array(
                'success' => false,
                'message' => __('Unable to read transaction accounts from Solana.', 'x402-solana-paywall')
            );
        }

        $merchant_index = array_search($merchant_wallet, $account_keys, true);
        $wallet_index   = array_search($wallet_address, $account_keys, true);

        if ($merchant_index === false) {
            return array(
                'success' => false,
                'message' => __('The merchant wallet was not part of the transaction.', 'x402-solana-paywall')
            );
        }

        if ($wallet_index === false) {
            return array(
                'success' => false,
                'message' => __('The paying wallet was not detected in the transaction.', 'x402-solana-paywall')
            );
        }

        // Ensure the payer actually signed the transaction when data available
        if (!self::wallet_signed_transaction($result, $wallet_address)) {
            return array(
                'success' => false,
                'message' => __('The provided wallet did not sign this transaction.', 'x402-solana-paywall')
            );
        }

        $pre_balances  = isset($result['meta']['preBalances']) ? $result['meta']['preBalances'] : array();
        $post_balances = isset($result['meta']['postBalances']) ? $result['meta']['postBalances'] : array();

        if (!isset($pre_balances[$merchant_index], $post_balances[$merchant_index], $pre_balances[$wallet_index], $post_balances[$wallet_index])) {
            return array(
                'success' => false,
                'message' => __('Unable to validate Solana account balances for this transaction.', 'x402-solana-paywall')
            );
        }

        $lamports_received = (int) $post_balances[$merchant_index] - (int) $pre_balances[$merchant_index];
        $lamports_spent    = (int) $pre_balances[$wallet_index] - (int) $post_balances[$wallet_index];

        $required_lamports = (int) round($required_amount * self::LAMPORTS_PER_SOL);

        if ($lamports_received <= 0 || $lamports_received < $required_lamports) {
            return array(
                'success' => false,
                'message' => __('The merchant wallet did not receive the required amount of SOL.', 'x402-solana-paywall')
            );
        }

        if ($lamports_spent < $required_lamports) {
            return array(
                'success' => false,
                'message' => __('The paying wallet did not send the required amount of SOL.', 'x402-solana-paywall')
            );
        }

        $amount_received = $lamports_received / self::LAMPORTS_PER_SOL;

        return array(
            'success' => true,
            'amount' => $amount_received,
            'transaction_data' => array(
                'signature'        => $signature,
                'wallet'           => $wallet_address,
                'merchant_wallet'  => $merchant_wallet,
                'network'          => $network,
                'block_time'       => $block_time,
                'lamports_received'=> $lamports_received,
                'lamports_spent'   => $lamports_spent,
            ),
        );
    }

    /**
     * Extract account keys from a Solana transaction result.
     *
     * @param array $result Transaction result payload.
     * @return array
     */
    private static function extract_account_keys($result) {
        if (empty($result['transaction']) || !is_array($result['transaction'])) {
            return array();
        }

        $message = isset($result['transaction']['message']) ? $result['transaction']['message'] : array();
        $raw_keys = isset($message['accountKeys']) ? $message['accountKeys'] : array();

        $account_keys = array();

        foreach ($raw_keys as $entry) {
            if (is_array($entry) && isset($entry['pubkey'])) {
                $account_keys[] = $entry['pubkey'];
            } elseif (is_string($entry)) {
                $account_keys[] = $entry;
            }
        }

        return $account_keys;
    }

    /**
     * Determine whether a wallet signed the transaction.
     *
     * @param array  $result         Transaction result payload.
     * @param string $wallet_address Wallet address to validate.
     * @return bool
     */
    private static function wallet_signed_transaction($result, $wallet_address) {
        if (empty($result['transaction']['message']['accountKeys'])) {
            return true; // Fallback to true if structure is unexpected.
        }

        foreach ($result['transaction']['message']['accountKeys'] as $entry) {
            if (is_array($entry)) {
                if (!empty($entry['pubkey']) && $entry['pubkey'] === $wallet_address) {
                    return !empty($entry['signer']);
                }
            } elseif ($entry === $wallet_address) {
                // Without signer metadata, assume it signed as first signature matches payer.
                return true;
            }
        }

        return false;
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
     * @param int         $post_id       Post ID
     * @param string      $session_token Session token
     * @param int|string  $expires       Expiration timestamp or datetime string (UTC)
     */
    public static function set_access_cookie($post_id, $session_token, $expires) {
        $cookie_name = 'x402_session_' . $post_id;
        $expire_time = is_numeric($expires) ? (int) $expires : strtotime($expires . ' UTC');

        if (!$expire_time) {
            $session_timeout = absint(get_option('x402_session_timeout', 3600));

            if ($session_timeout < MINUTE_IN_SECONDS) {
                $session_timeout = MINUTE_IN_SECONDS;
            }

            $expire_time = time() + $session_timeout;
        }

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
