<?php
/**
 * Crypto validation utilities for X402 payment gateway.
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates cryptocurrency-related data for security and correctness.
 */
class X402_Crypto_Validator {

    /**
     * Validate Ethereum address format.
     *
     * @param string $address The address to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_eth_address($address) {
        if (!is_string($address)) {
            return false;
        }

        // Check format: 0x followed by 40 hex characters
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return false;
        }

        return true;
    }

    /**
     * Validate Solana address format (base58 encoded).
     *
     * @param string $address The address to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_solana_address($address) {
        if (!is_string($address)) {
            return false;
        }

        // Solana addresses are 32-44 characters base58 encoded
        if (strlen($address) < 32 || strlen($address) > 44) {
            return false;
        }

        // Base58 characters (no 0, O, I, l)
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $address)) {
            return false;
        }

        return true;
    }

    /**
     * Validate transaction hash format.
     *
     * @param string $hash The transaction hash to validate.
     * @param string $network The blockchain network (for format detection).
     * @return bool True if valid, false otherwise.
     */
    public static function validate_tx_hash($hash, $network = 'ethereum') {
        if (!is_string($hash)) {
            return false;
        }

        // Ethereum/EVM transaction hash: 0x followed by 64 hex characters
        if (strpos($network, 'ethereum') !== false || strpos($network, 'base') !== false || strpos($network, 'polygon') !== false) {
            return preg_match('/^0x[a-fA-F0-9]{64}$/', $hash) === 1;
        }

        // Solana transaction signature: base58 encoded, 87-88 characters
        if (strpos($network, 'solana') !== false) {
            $length = strlen($hash);
            if ($length < 87 || $length > 88) {
                return false;
            }
            return preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $hash) === 1;
        }

        return false;
    }

    /**
     * Validate crypto amount (no negative values, proper decimals).
     *
     * @param mixed $amount   The amount to validate.
     * @param int   $decimals Maximum decimal places allowed.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_amount($amount, $decimals = 18) {
        if (!is_numeric($amount)) {
            return false;
        }

        $numeric_amount = floatval($amount);

        // No negative amounts
        if ($numeric_amount < 0) {
            return false;
        }

        // Check decimal places
        $parts = explode('.', (string) $amount);
        if (isset($parts[1]) && strlen($parts[1]) > $decimals) {
            return false;
        }

        return true;
    }

    /**
     * Validate URL format for facilitator endpoints.
     *
     * @param string $url The URL to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_facilitator_url($url) {
        if (!is_string($url) || empty($url)) {
            return false;
        }

        // Must be HTTPS in production
        $parsed = wp_parse_url($url);
        
        if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }

        // Allow http only for localhost/development
        if ($parsed['scheme'] === 'http') {
            $allowed_hosts = array('localhost', '127.0.0.1', '::1');
            if (!in_array($parsed['host'], $allowed_hosts, true)) {
                return false;
            }
        } elseif ($parsed['scheme'] !== 'https') {
            return false;
        }

        return true;
    }

    /**
     * Sanitize blockchain address.
     *
     * @param string $address The address to sanitize.
     * @return string Sanitized address.
     */
    public static function sanitize_address($address) {
        return sanitize_text_field(trim($address));
    }

    /**
     * Sanitize transaction hash.
     *
     * @param string $hash The hash to sanitize.
     * @return string Sanitized hash.
     */
    public static function sanitize_tx_hash($hash) {
        return sanitize_text_field(trim($hash));
    }

    /**
     * Validate and sanitize payment header data.
     *
     * @param array $headers The headers array from request.
     * @return array Sanitized headers.
     */
    public static function sanitize_payment_headers($headers) {
        if (!is_array($headers)) {
            return array();
        }

        $sanitized = array();
        
        foreach ($headers as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized[$sanitized_key] = array_map('sanitize_text_field', $value);
            }
        }

        return $sanitized;
    }

    /**
     * Validate payment metadata before storing.
     *
     * @param array $metadata The metadata to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_payment_metadata($metadata) {
        if (!is_array($metadata)) {
            return false;
        }

        // Required fields for payment metadata
        $required_fields = array('amount', 'asset', 'network');
        
        foreach ($required_fields as $field) {
            if (!isset($metadata[$field]) || empty($metadata[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a network identifier is valid.
     *
     * @param string $network The network identifier.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_network($network) {
        $supported_networks = array(
            'ethereum-mainnet',
            'ethereum-sepolia',
            'ethereum-goerli',
            'base-mainnet',
            'base-sepolia',
            'polygon-mainnet',
            'polygon-amoy',
            'solana-mainnet',
            'solana-devnet',
            'solana-testnet',
        );

        return in_array($network, $supported_networks, true);
    }

    /**
     * Detect network type from network identifier.
     *
     * @param string $network The network identifier.
     * @return string 'evm' or 'solana' or 'unknown'.
     */
    public static function get_network_type($network) {
        if (strpos($network, 'solana') !== false) {
            return 'solana';
        }
        
        if (strpos($network, 'ethereum') !== false || 
            strpos($network, 'base') !== false || 
            strpos($network, 'polygon') !== false) {
            return 'evm';
        }

        return 'unknown';
    }
}
