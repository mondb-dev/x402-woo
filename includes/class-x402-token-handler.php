<?php
/**
 * X402 Token Handler - SPL and ERC-20 token support
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles SPL tokens and ERC-20 tokens for X402 payments.
 */
class X402_Token_Handler {
    
    /**
     * Popular SPL token addresses (Solana)
     */
    const SPL_TOKENS = array(
        'USDC' => array(
            'mint' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            'decimals' => 6,
            'symbol' => 'USDC',
            'name' => 'USD Coin',
            'network' => 'solana',
            'coingecko_id' => 'usd-coin',
        ),
        'USDT' => array(
            'mint' => 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB',
            'decimals' => 6,
            'symbol' => 'USDT',
            'name' => 'Tether USD',
            'network' => 'solana',
            'coingecko_id' => 'tether',
        ),
        'TROLL' => array(
            'mint' => 'HUBsveNpjo5pWqNkH57QzxjQASdTVXcSK7bVKTSZtcSX',
            'decimals' => 6,
            'symbol' => 'TROLL',
            'name' => 'Troll',
            'network' => 'solana',
            'coingecko_id' => 'troll',
        ),
        'BONK' => array(
            'mint' => 'DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263',
            'decimals' => 5,
            'symbol' => 'BONK',
            'name' => 'Bonk',
            'network' => 'solana',
            'coingecko_id' => 'bonk',
        ),
        'RAY' => array(
            'mint' => '4k3Dyjzvzp8eMZWUXbBCjEvwSkkk59S5iCNLY3QrkX6R',
            'decimals' => 6,
            'symbol' => 'RAY',
            'name' => 'Raydium',
            'network' => 'solana',
            'coingecko_id' => 'raydium',
        ),
        'JUP' => array(
            'mint' => 'JUPyiwrYJFskUPiHa7hkeR8VUtAeFoSYbKedZNsDvCN',
            'decimals' => 6,
            'symbol' => 'JUP',
            'name' => 'Jupiter',
            'network' => 'solana',
            'coingecko_id' => 'jupiter',
        ),
        'WIF' => array(
            'mint' => 'EKpQGSJtjMFqKZ9KQanSqYXRcF8fBopzLHYxdM65zcjm',
            'decimals' => 6,
            'symbol' => 'WIF',
            'name' => 'dogwifhat',
            'network' => 'solana',
            'coingecko_id' => 'dogwifcoin',
        ),
        'SOL' => array(
            'mint' => 'native',
            'decimals' => 9,
            'symbol' => 'SOL',
            'name' => 'Solana',
            'network' => 'solana',
            'coingecko_id' => 'solana',
        ),
    );
    
    /**
     * Popular ERC-20 token addresses by network
     */
    const ERC20_TOKENS = array(
        'ethereum-mainnet' => array(
            'USDC' => array(
                'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
                'decimals' => 6,
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'coingecko_id' => 'usd-coin',
            ),
            'USDT' => array(
                'address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'decimals' => 6,
                'symbol' => 'USDT',
                'name' => 'Tether USD',
                'coingecko_id' => 'tether',
            ),
            'DAI' => array(
                'address' => '0x6B175474E89094C44Da98b954EedeAC495271d0F',
                'decimals' => 18,
                'symbol' => 'DAI',
                'name' => 'Dai Stablecoin',
                'coingecko_id' => 'dai',
            ),
        ),
        'base-mainnet' => array(
            'USDC' => array(
                'address' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
                'decimals' => 6,
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'coingecko_id' => 'usd-coin',
            ),
            'DEGEN' => array(
                'address' => '0x4ed4E862860beD51a9570b96d89aF5E1B0Efefed',
                'decimals' => 18,
                'symbol' => 'DEGEN',
                'name' => 'Degen',
                'coingecko_id' => 'degen-base',
            ),
        ),
        'polygon-mainnet' => array(
            'USDC' => array(
                'address' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174',
                'decimals' => 6,
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'coingecko_id' => 'usd-coin',
            ),
            'USDT' => array(
                'address' => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
                'decimals' => 6,
                'symbol' => 'USDT',
                'name' => 'Tether USD',
                'coingecko_id' => 'tether',
            ),
        ),
    );
    
    /**
     * Get token information by symbol and network
     *
     * @param string $symbol  Token symbol.
     * @param string $network Network identifier.
     * @return array|null Token info or null if not found.
     */
    public static function get_token_info($symbol, $network = 'solana') {
        $symbol = strtoupper($symbol);
        
        // Check for Solana SPL tokens
        if (strpos($network, 'solana') !== false) {
            if (isset(self::SPL_TOKENS[$symbol])) {
                return self::SPL_TOKENS[$symbol];
            }
            
            // Check custom tokens
            $custom = self::get_custom_token($symbol, $network);
            if ($custom) {
                return $custom;
            }
        }
        
        // Check for ERC-20 tokens
        if (isset(self::ERC20_TOKENS[$network][$symbol])) {
            return array_merge(
                self::ERC20_TOKENS[$network][$symbol],
                array('network' => $network)
            );
        }
        
        // Check custom ERC-20 tokens
        $custom = self::get_custom_token($symbol, $network);
        if ($custom) {
            return $custom;
        }
        
        return null;
    }
    
    /**
     * Validate SPL token mint address
     *
     * @param string $mint_address The mint address to validate.
     * @return bool
     */
    public static function validate_spl_mint($mint_address) {
        if ($mint_address === 'native') {
            return true; // Native SOL
        }
        
        return X402_Crypto_Validator::validate_solana_address($mint_address);
    }
    
    /**
     * Validate ERC-20 token address
     *
     * @param string $token_address The token address to validate.
     * @return bool
     */
    public static function validate_erc20_address($token_address) {
        return X402_Crypto_Validator::validate_eth_address($token_address);
    }
    
    /**
     * Get all supported tokens for a network
     *
     * @param string $network Network identifier or 'all'.
     * @return array
     */
    public static function get_supported_tokens($network = 'all') {
        $tokens = array();
        
        if ($network === 'all' || strpos($network, 'solana') !== false) {
            foreach (self::SPL_TOKENS as $symbol => $token) {
                $tokens[$symbol . '_solana'] = array_merge($token, array('token_symbol' => $symbol));
            }
        }
        
        if ($network === 'all') {
            foreach (self::ERC20_TOKENS as $net => $net_tokens) {
                foreach ($net_tokens as $symbol => $token) {
                    $tokens[$symbol . '_' . $net] = array_merge(
                        $token,
                        array('token_symbol' => $symbol, 'network' => $net)
                    );
                }
            }
        } elseif (isset(self::ERC20_TOKENS[$network])) {
            foreach (self::ERC20_TOKENS[$network] as $symbol => $token) {
                $tokens[$symbol . '_' . $network] = array_merge(
                    $token,
                    array('token_symbol' => $symbol, 'network' => $network)
                );
            }
        }
        
        // Add custom tokens
        $custom_tokens = self::get_custom_tokens($network);
        foreach ($custom_tokens as $key => $token) {
            $tokens[$key] = $token;
        }
        
        return $tokens;
    }
    
    /**
     * Format token amount with proper decimals
     *
     * @param string|int $amount   The amount in smallest units.
     * @param int        $decimals Number of decimal places.
     * @return string Formatted amount.
     */
    public static function format_token_amount($amount, $decimals) {
        if (function_exists('bcpow') && function_exists('bcdiv')) {
            $divisor = bcpow('10', $decimals, 0);
            return bcdiv($amount, $divisor, $decimals);
        }
        
        $divisor = pow(10, $decimals);
        return number_format($amount / $divisor, $decimals, '.', '');
    }
    
    /**
     * Convert token amount to smallest unit
     *
     * @param string|float $amount   Display amount.
     * @param int          $decimals Number of decimal places.
     * @return string Amount in smallest units.
     */
    public static function to_token_units($amount, $decimals) {
        if (function_exists('bcmul') && function_exists('bcpow')) {
            return bcmul($amount, bcpow('10', $decimals, 0), 0);
        }
        
        return (string) ($amount * pow(10, $decimals));
    }
    
    /**
     * Add custom token
     *
     * @param string $network  Network identifier.
     * @param string $symbol   Token symbol.
     * @param string $address  Token address or mint.
     * @param int    $decimals Token decimals.
     * @param string $name     Token name.
     * @return bool
     */
    public static function add_custom_token($network, $symbol, $address, $decimals, $name) {
        $custom_tokens = get_option('x402_custom_tokens', array());
        
        $symbol = strtoupper($symbol);
        $key = $symbol . '_' . $network;
        
        $custom_tokens[$key] = array(
            'address' => $address,
            'mint' => $address, // For Solana compatibility
            'decimals' => (int) $decimals,
            'symbol' => $symbol,
            'name' => $name,
            'network' => $network,
            'custom' => true,
            'token_symbol' => $symbol,
        );
        
        update_option('x402_custom_tokens', $custom_tokens);
        
        X402_Logger::info('Custom token added', array(
            'symbol' => $symbol,
            'network' => $network,
            'address' => $address,
        ));
        
        return true;
    }
    
    /**
     * Get custom token
     *
     * @param string $symbol  Token symbol.
     * @param string $network Network identifier.
     * @return array|null
     */
    public static function get_custom_token($symbol, $network) {
        $custom_tokens = get_option('x402_custom_tokens', array());
        $key = strtoupper($symbol) . '_' . $network;
        
        return isset($custom_tokens[$key]) ? $custom_tokens[$key] : null;
    }
    
    /**
     * Get all custom tokens
     *
     * @param string|null $network Optional network filter.
     * @return array
     */
    public static function get_custom_tokens($network = null) {
        $custom_tokens = get_option('x402_custom_tokens', array());
        
        if ($network && $network !== 'all') {
            return array_filter($custom_tokens, function($token) use ($network) {
                return isset($token['network']) && $token['network'] === $network;
            });
        }
        
        return $custom_tokens;
    }
    
    /**
     * Remove custom token
     *
     * @param string $symbol  Token symbol.
     * @param string $network Network identifier.
     * @return bool
     */
    public static function remove_custom_token($symbol, $network) {
        $custom_tokens = get_option('x402_custom_tokens', array());
        $key = strtoupper($symbol) . '_' . $network;
        
        if (isset($custom_tokens[$key])) {
            unset($custom_tokens[$key]);
            update_option('x402_custom_tokens', $custom_tokens);
            
            X402_Logger::info('Custom token removed', array(
                'symbol' => $symbol,
                'network' => $network,
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get token price in USD
     *
     * @param string $symbol  Token symbol.
     * @param string $network Network identifier.
     * @return float|false Price in USD or false on failure.
     */
    public static function get_token_price($symbol, $network = 'solana') {
        // Cache key
        $cache_key = 'x402_token_price_' . strtolower($symbol) . '_' . $network;
        $cached_price = get_transient($cache_key);
        
        if ($cached_price !== false) {
            return (float) $cached_price;
        }
        
        $token_info = self::get_token_info($symbol, $network);
        
        if (!$token_info) {
            return false;
        }
        
        $price = false;
        
        // Try CoinGecko first
        if (isset($token_info['coingecko_id'])) {
            $price = self::fetch_coingecko_price($token_info['coingecko_id']);
        }
        
        // Fallback to Jupiter for Solana tokens
        if (!$price && strpos($network, 'solana') !== false && isset($token_info['mint'])) {
            $price = self::fetch_jupiter_price($token_info['mint']);
        }
        
        // Cache for 5 minutes
        if ($price) {
            set_transient($cache_key, $price, 300);
        }
        
        return $price;
    }
    
    /**
     * Fetch price from CoinGecko API
     *
     * @param string $coingecko_id CoinGecko token ID.
     * @return float|false
     */
    private static function fetch_coingecko_price($coingecko_id) {
        $url = sprintf(
            'https://api.coingecko.com/api/v3/simple/price?ids=%s&vs_currencies=usd',
            urlencode($coingecko_id)
        );
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            X402_Logger::warning('CoinGecko API error', array(
                'error' => $response->get_error_message(),
                'token_id' => $coingecko_id,
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data[$coingecko_id]['usd'])) {
            return (float) $data[$coingecko_id]['usd'];
        }
        
        return false;
    }
    
    /**
     * Fetch price from Jupiter aggregator
     *
     * @param string $mint_address Token mint address.
     * @return float|false
     */
    private static function fetch_jupiter_price($mint_address) {
        if ($mint_address === 'native') {
            return self::fetch_coingecko_price('solana');
        }
        
        $url = sprintf(
            'https://price.jup.ag/v4/price?ids=%s',
            urlencode($mint_address)
        );
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            X402_Logger::warning('Jupiter API error', array(
                'error' => $response->get_error_message(),
                'mint' => $mint_address,
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['data'][$mint_address]['price'])) {
            return (float) $data['data'][$mint_address]['price'];
        }
        
        return false;
    }
    
    /**
     * Convert fiat amount to token amount
     *
     * @param float  $fiat_amount Fiat amount.
     * @param string $symbol      Token symbol.
     * @param string $network     Network identifier.
     * @return string|false Token amount or false on failure.
     */
    public static function convert_fiat_to_token($fiat_amount, $symbol, $network) {
        $price = self::get_token_price($symbol, $network);
        
        if (!$price) {
            return false;
        }
        
        $token_info = self::get_token_info($symbol, $network);
        
        if (!$token_info) {
            return false;
        }
        
        $token_amount = $fiat_amount / $price;
        
        // Round to token decimals
        return number_format($token_amount, $token_info['decimals'], '.', '');
    }
}
