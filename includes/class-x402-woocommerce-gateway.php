<?php
/**
 * WooCommerce payment gateway implementation for x402 payments.
 *
 * @package X402_Solana_Paywall
 */

use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Types\PaymentRequirements;
use X402\Validation\Validator;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('X402_WooCommerce_Gateway') && class_exists('WC_Payment_Gateway')) {
    /**
     * Main WooCommerce payment gateway class for x402.
     */
    class X402_WooCommerce_Gateway extends WC_Payment_Gateway {

        /**
         * Constructor.
         */
        public function __construct() {
            $this->id                 = 'x402';
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = __('x402 Payments', 'x402-solana-paywall');
            $this->method_description = __('Accept on-chain payments through the x402 protocol using supported wallets and facilitators.', 'x402-solana-paywall');
            $this->supports           = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option('title', __('x402 Payment', 'x402-solana-paywall'));
            $this->description = $this->get_option('description', __('Complete your order by authorizing an x402 payment.', 'x402-solana-paywall'));

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_order_details_after_order_table', array($this, 'render_order_payment_details'), 20, 1);
        }

        /**
         * Process and validate admin options before saving.
         */
        public function process_admin_options() {
            $post_data = $this->get_post_data();
            $settings  = $this->collect_posted_settings($post_data);
            $errors    = $this->validate_settings($settings);

            if (!empty($errors)) {
                foreach ($errors as $message) {
                    WC_Admin_Settings::add_error($message);
                }

                $this->display_errors();
                return false;
            }

            $result = parent::process_admin_options();

            if ($result) {
                X402_Logger::info('Gateway settings validated', array(
                    'network'          => $settings['network'],
                    'facilitator_mode' => $settings['facilitator_mode'],
                    'use_custom_token' => $settings['use_custom_token'],
                ));
            }

            return $result;
        }

        /**
         * Collect posted settings in a sanitized array keyed by option name.
         *
         * @param array $post_data Raw post data from the settings form.
         * @return array
         */
        private function collect_posted_settings(array $post_data) {
            $fields = $this->get_form_fields();
            $get    = function ($key, $default = '') use ($fields, $post_data) {
                if (!isset($fields[$key])) {
                    return $default;
                }

                return $this->get_field_value($key, $fields[$key], $post_data);
            };

            return array(
                'pay_to'                  => trim((string) $get('pay_to')),
                'asset'                   => trim((string) $get('asset')),
                'network'                 => (string) $get('network', 'solana-devnet'),
                'facilitator_endpoint'    => trim((string) $get('facilitator_endpoint')),
                'facilitator_mode'        => (string) $get('facilitator_mode', 'base'),
                'use_custom_token'        => (string) $get('use_custom_token', 'no'),
                'custom_token_ca'         => trim((string) $get('custom_token_ca')),
                'custom_token_symbol'     => trim((string) $get('custom_token_symbol')),
                'custom_token_decimals'   => $get('custom_token_decimals', ''),
            );
        }

        /**
         * Validate submitted settings prior to saving.
         *
         * @param array $settings Sanitized settings array.
         * @return string[] List of validation error messages.
         */
        private function validate_settings(array $settings) {
            $errors = array();

            if (!empty($settings['pay_to']) && !X402_Crypto_Validator::validate_solana_address($settings['pay_to']) && !X402_Crypto_Validator::validate_eth_address($settings['pay_to'])) {
                $errors[] = __('Invalid pay-to address format.', 'x402-solana-paywall');
            }

            if (!empty($settings['asset']) && !X402_Crypto_Validator::validate_solana_address($settings['asset']) && !X402_Crypto_Validator::validate_eth_address($settings['asset'])) {
                $errors[] = __('Invalid asset address format.', 'x402-solana-paywall');
            }

            if (!empty($settings['facilitator_endpoint']) && !X402_Crypto_Validator::validate_facilitator_url($settings['facilitator_endpoint'])) {
                $errors[] = __('Invalid facilitator URL. Must use HTTPS (except for localhost).', 'x402-solana-paywall');
            }

            if ('yes' === $settings['use_custom_token']) {
                if (empty($settings['custom_token_ca'])) {
                    $errors[] = __('Custom token contract address (CA) is required when custom token is enabled.', 'x402-solana-paywall');
                }

                if (empty($settings['custom_token_symbol'])) {
                    $errors[] = __('Custom token symbol is required.', 'x402-solana-paywall');
                }

                $decimals = '' === $settings['custom_token_decimals'] ? null : (int) $settings['custom_token_decimals'];

                if (null === $decimals || $decimals < 0 || $decimals > 18) {
                    $errors[] = __('Custom token decimals must be between 0 and 18.', 'x402-solana-paywall');
                }

                if (!empty($settings['custom_token_ca'])) {
                    $is_valid_ca = X402_Crypto_Validator::validate_eth_address($settings['custom_token_ca']) ||
                                   X402_Crypto_Validator::validate_solana_address($settings['custom_token_ca']);

                    if (!$is_valid_ca) {
                        $errors[] = __('Invalid contract address format. Must be a valid Ethereum (0x...) or Solana (base58) address.', 'x402-solana-paywall');
                    }
                }
            }

            return $errors;
        }

        /**
         * Initialize gateway settings fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'x402-solana-paywall'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable x402 payments', 'x402-solana-paywall'),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __('Title', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Controls the payment method title displayed during checkout.', 'x402-solana-paywall'),
                    'default'     => __('x402 Payment', 'x402-solana-paywall'),
                ),
                'description' => array(
                    'title'       => __('Description', 'x402-solana-paywall'),
                    'type'        => 'textarea',
                    'description' => __('Displayed to the customer during checkout.', 'x402-solana-paywall'),
                    'default'     => __('Pay securely using an x402-compatible wallet. Submit the payment header provided by your wallet to finalize the order.', 'x402-solana-paywall'),
                ),
                'pay_to' => array(
                    'title'       => __('Pay To Address', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Wallet address that will receive the payment.', 'x402-solana-paywall'),
                    'default'     => '',
                ),
                'asset' => array(
                    'title'       => __('Asset Address', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Token contract or mint address for the asset being collected.', 'x402-solana-paywall'),
                    'default'     => '',
                ),
                'asset_decimals' => array(
                    'title'       => __('Asset Decimals', 'x402-solana-paywall'),
                    'type'        => 'number',
                    'description' => __('Number of decimal places used by the asset (e.g., 6 for USDC, 9 for SOL).', 'x402-solana-paywall'),
                    'default'     => 6,
                    'custom_attributes' => array(
                        'min' => 0,
                        'max' => 18,
                    ),
                ),
                'network' => array(
                    'title'       => __('Network', 'x402-solana-paywall'),
                    'type'        => 'select',
                    'description' => __('Blockchain network where the payment will be executed.', 'x402-solana-paywall'),
                    'default'     => 'solana-devnet',
                    'options'     => $this->get_supported_networks(),
                ),
                'timeout' => array(
                    'title'       => __('Payment Timeout (seconds)', 'x402-solana-paywall'),
                    'type'        => 'number',
                    'description' => __('Maximum amount of time the customer has to complete the payment.', 'x402-solana-paywall'),
                    'default'     => 600,
                    'custom_attributes' => array(
                        'min' => 60,
                        'step' => 30,
                    ),
                ),
                'asset_name' => array(
                    'title'       => __('Asset Name (EVM only)', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('ERC-20 token name required for EIP-712 validation. Leave empty for Solana.', 'x402-solana-paywall'),
                    'default'     => 'USD Coin',
                ),
                'asset_version' => array(
                    'title'       => __('Asset Version (EVM only)', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('ERC-20 token version required for EIP-712 validation. Leave empty for Solana.', 'x402-solana-paywall'),
                    'default'     => '2',
                ),
                'fee_payer' => array(
                    'title'       => __('Fee Payer (Solana optional)', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Optional fee payer address for Solana transactions.', 'x402-solana-paywall'),
                    'default'     => '',
                ),
                'facilitator_mode' => array(
                    'title'       => __('Facilitator', 'x402-solana-paywall'),
                    'type'        => 'select',
                    'description' => __('Choose how payments are verified and settled.', 'x402-solana-paywall'),
                    'default'     => 'base',
                    'options'     => array(
                        'base'     => __('Base Facilitator (Recommended)', 'x402-solana-paywall'),
                        'coinbase' => __('Coinbase Facilitator', 'x402-solana-paywall'),
                        'custom'   => __('Custom Facilitator', 'x402-solana-paywall'),
                        'none'     => __('No facilitator (manual verification)', 'x402-solana-paywall'),
                    ),
                ),
                'facilitator_endpoint' => array(
                    'title'       => __('Facilitator Endpoint URL', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Facilitator service URL. Defaults to Base facilitator. Required when using Custom Facilitator.', 'x402-solana-paywall'),
                    'default'     => 'https://facilitator.base.org',
                    'placeholder' => 'https://facilitator.base.org',
                    'custom_attributes' => array(
                        'pattern' => 'https?://.+',
                    ),
                ),
                'facilitator_timeout' => array(
                    'title'       => __('Facilitator Request Timeout', 'x402-solana-paywall'),
                    'type'        => 'number',
                    'description' => __('Maximum time in seconds to wait for facilitator response.', 'x402-solana-paywall'),
                    'default'     => 30,
                    'custom_attributes' => array(
                        'min' => 5,
                        'max' => 120,
                        'step' => 1,
                    ),
                ),
                'facilitator_api_key' => array(
                    'title'       => __('Facilitator API Key', 'x402-solana-paywall'),
                    'type'        => 'password',
                    'description' => __('Optional API key used when communicating with the facilitator.', 'x402-solana-paywall'),
                    'default'     => '',
                ),
                'auto_settle' => array(
                    'title'       => __('Auto Settle Payments', 'x402-solana-paywall'),
                    'type'        => 'checkbox',
                    'label'       => __('Attempt to automatically settle payments after verification.', 'x402-solana-paywall'),
                    'default'     => 'yes',
                ),
                'token_settings_section' => array(
                    'title'       => __('Token Settings', 'x402-solana-paywall'),
                    'type'        => 'title',
                    'description' => __('Configure accepted tokens and currencies for payments.', 'x402-solana-paywall'),
                ),
                'enable_spl_tokens' => array(
                    'title'       => __('Enable SPL Tokens', 'x402-solana-paywall'),
                    'type'        => 'checkbox',
                    'label'       => __('Accept Solana SPL token payments (USDC, TROLL, BONK, etc.)', 'x402-solana-paywall'),
                    'default'     => 'yes',
                ),
                'enable_erc20_tokens' => array(
                    'title'       => __('Enable ERC-20 Tokens', 'x402-solana-paywall'),
                    'type'        => 'checkbox',
                    'label'       => __('Accept ERC-20 token payments (USDC, USDT, DAI, etc.)', 'x402-solana-paywall'),
                    'default'     => 'yes',
                ),
                'accepted_tokens' => array(
                    'title'       => __('Accepted Tokens', 'x402-solana-paywall'),
                    'type'        => 'multiselect',
                    'description' => __('Select which tokens customers can use for payment. Leave empty to accept all.', 'x402-solana-paywall'),
                    'default'     => array(),
                    'options'     => $this->get_token_options(),
                    'desc_tip'    => true,
                    'class'       => 'wc-enhanced-select',
                ),
                'default_token' => array(
                    'title'       => __('Default Token', 'x402-solana-paywall'),
                    'type'        => 'select',
                    'description' => __('Pre-selected token at checkout.', 'x402-solana-paywall'),
                    'default'     => 'USDC_solana-mainnet',
                    'options'     => $this->get_token_options(),
                    'desc_tip'    => true,
                ),
                'enable_price_conversion' => array(
                    'title'       => __('Enable Price Conversion', 'x402-solana-paywall'),
                    'type'        => 'checkbox',
                    'label'       => __('Automatically convert order total to token amount using real-time prices', 'x402-solana-paywall'),
                    'default'     => 'yes',
                ),
                'custom_token_section' => array(
                    'title'       => __('Custom Token (Advanced)', 'x402-solana-paywall'),
                    'type'        => 'title',
                    'description' => __('Configure a custom token by entering its contract address (CA) or mint address. This overrides the selected tokens above.', 'x402-solana-paywall'),
                ),
                'use_custom_token' => array(
                    'title'       => __('Use Custom Token', 'x402-solana-paywall'),
                    'type'        => 'checkbox',
                    'label'       => __('Enable custom token payment (ignores token selection above)', 'x402-solana-paywall'),
                    'default'     => 'no',
                    'description' => __('When enabled, only the custom token below will be accepted for payment.', 'x402-solana-paywall'),
                    'desc_tip'    => true,
                ),
                'custom_token_ca' => array(
                    'title'       => __('Token Contract Address (CA)', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Enter the token contract address (ERC-20) or mint address (SPL token). Examples: Ethereum: 0x..., Solana: base58 address', 'x402-solana-paywall'),
                    'default'     => '',
                    'placeholder' => __('0x... or base58 mint address', 'x402-solana-paywall'),
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'data-depends-on' => 'use_custom_token',
                    ),
                ),
                'custom_token_symbol' => array(
                    'title'       => __('Token Symbol', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Token symbol (e.g., TROLL, BONK, PEPE)', 'x402-solana-paywall'),
                    'default'     => '',
                    'placeholder' => __('e.g., TROLL', 'x402-solana-paywall'),
                    'desc_tip'    => true,
                ),
                'custom_token_name' => array(
                    'title'       => __('Token Name', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Full token name (e.g., Troll Token)', 'x402-solana-paywall'),
                    'default'     => '',
                    'placeholder' => __('e.g., Troll Token', 'x402-solana-paywall'),
                    'desc_tip'    => true,
                ),
                'custom_token_decimals' => array(
                    'title'       => __('Token Decimals', 'x402-solana-paywall'),
                    'type'        => 'number',
                    'description' => __('Number of decimal places (usually 6, 9, or 18)', 'x402-solana-paywall'),
                    'default'     => '6',
                    'placeholder' => '6',
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'min' => 0,
                        'max' => 18,
                        'step' => 1,
                    ),
                ),
                'custom_token_price' => array(
                    'title'       => __('Token Price (USD)', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Current token price in USD. Leave empty to fetch automatically. Update this regularly for accurate pricing.', 'x402-solana-paywall'),
                    'default'     => '',
                    'placeholder' => __('e.g., 0.000123', 'x402-solana-paywall'),
                    'desc_tip'    => true,
                ),
                'custom_token_coingecko_id' => array(
                    'title'       => __('CoinGecko ID (Optional)', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('CoinGecko token ID for automatic price fetching (e.g., "troll", "bonk"). Find it on CoinGecko URL.', 'x402-solana-paywall'),
                    'default'     => '',
                    'placeholder' => __('e.g., troll', 'x402-solana-paywall'),
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Get token options for settings dropdown
         *
         * @return array
         */
        private function get_token_options() {
            $options = array();
            $tokens = X402_Token_Handler::get_supported_tokens('all');
            
            foreach ($tokens as $key => $token) {
                $symbol = $token['token_symbol'] ?? $token['symbol'];
                $network = $token['network'];
                $network_label = $this->get_network_label($network);
                
                $label = sprintf(
                    '%s (%s on %s)',
                    $token['name'],
                    $symbol,
                    $network_label
                );
                
                if (isset($token['custom']) && $token['custom']) {
                    $label .= ' [Custom]';
                }
                
                $options[$key] = $label;
            }
            
            return $options;
        }

        /**
         * Get human-readable network label
         *
         * @param string $network Network identifier.
         * @return string
         */
        private function get_network_label($network) {
            $labels = array(
                'solana' => 'Solana',
                'solana-mainnet' => 'Solana',
                'solana-devnet' => 'Solana Devnet',
                'solana-testnet' => 'Solana Testnet',
                'ethereum-mainnet' => 'Ethereum',
                'ethereum-sepolia' => 'Ethereum Sepolia',
                'base-mainnet' => 'Base',
                'base-sepolia' => 'Base Sepolia',
                'polygon-mainnet' => 'Polygon',
                'polygon-amoy' => 'Polygon Amoy',
            );
            
            return isset($labels[$network]) ? $labels[$network] : ucfirst($network);
        }

        /**
         * Get active token information (custom or selected)
         *
         * @return array|null Token information or null.
         */
        public function get_active_token() {
            // Check if custom token is enabled
            if ($this->get_option('use_custom_token') === 'yes') {
                return $this->get_custom_token_info();
            }
            
            // Return default token from selection
            $default_token = $this->get_option('default_token', 'USDC_solana-mainnet');
            
            if (empty($default_token)) {
                return null;
            }
            
            $parts = explode('_', $default_token);
            if (count($parts) < 2) {
                return null;
            }
            
            $symbol = $parts[0];
            $network = implode('_', array_slice($parts, 1));
            
            return X402_Token_Handler::get_token_info($symbol, $network);
        }

        /**
         * Get custom token information from settings
         *
         * @return array|null
         */
        private function get_custom_token_info() {
            $ca = $this->get_option('custom_token_ca');
            $symbol = $this->get_option('custom_token_symbol');
            $name = $this->get_option('custom_token_name');
            $decimals = $this->get_option('custom_token_decimals', 6);
            $price = $this->get_option('custom_token_price');
            $coingecko_id = $this->get_option('custom_token_coingecko_id');
            
            if (empty($ca) || empty($symbol)) {
                return null;
            }
            
            // Detect network based on address format
            $network = $this->get_option('network', 'solana-mainnet');
            if (X402_Crypto_Validator::validate_eth_address($ca)) {
                // EVM address
                $network_type = X402_Crypto_Validator::get_network_type($network);
                if ($network_type !== 'evm') {
                    $network = 'ethereum-mainnet'; // Default to Ethereum if not set
                }
            } elseif (X402_Crypto_Validator::validate_solana_address($ca)) {
                // Solana address
                if (strpos($network, 'solana') === false) {
                    $network = 'solana-mainnet';
                }
            }
            
            $token_info = array(
                'address' => $ca,
                'mint' => $ca, // For Solana compatibility
                'symbol' => strtoupper($symbol),
                'name' => $name ?: $symbol,
                'decimals' => (int) $decimals,
                'network' => $network,
                'custom' => true,
                'manual_price' => !empty($price) ? (float) $price : false,
            );
            
            if (!empty($coingecko_id)) {
                $token_info['coingecko_id'] = $coingecko_id;
            }
            
            return $token_info;
        }

        /**
         * Get token price (custom or from handler)
         *
         * @param array $token_info Token information.
         * @return float|false
         */
        private function get_token_price_for_payment($token_info) {
            // Check if manual price is set
            if (isset($token_info['manual_price']) && $token_info['manual_price'] !== false) {
                return $token_info['manual_price'];
            }
            
            // Fetch from price oracle
            if (isset($token_info['coingecko_id'])) {
                $price = X402_Token_Handler::get_token_price($token_info['symbol'], $token_info['network']);
                if ($price !== false) {
                    return $price;
                }
            }
            
            // For custom tokens without coingecko_id, require manual price
            if (isset($token_info['custom']) && $token_info['custom']) {
                X402_Logger::warning('Custom token has no price configured', array(
                    'symbol' => $token_info['symbol'],
                    'ca' => $token_info['address'] ?? $token_info['mint'],
                ));
                return false;
            }
            
            return X402_Token_Handler::get_token_price($token_info['symbol'], $token_info['network']);
        }

        /**
         * Process payment for the given order ID.
         *
         * @param int $order_id Order identifier.
         * @return array
         */
        public function process_payment($order_id) {
            // Validate order ID
            $order_id = X402_Security_Handler::validate_order_id($order_id);
            if (!$order_id) {
                X402_Logger::error('Invalid order ID provided', array('order_id' => $order_id));
                wc_add_notice(__('Unable to locate the order for x402 payment processing.', 'x402-solana-paywall'), 'error');
                return array('result' => 'failure');
            }

            $order = wc_get_order($order_id);

            if (!$order instanceof WC_Order) {
                X402_Logger::error('Order not found', array('order_id' => $order_id));
                wc_add_notice(__('Unable to locate the order for x402 payment processing.', 'x402-solana-paywall'), 'error');
                return array('result' => 'failure');
            }

            // Rate limiting
            if (!X402_Security_Handler::check_rate_limit('process_payment_' . $order_id, 5, 60)) {
                X402_Logger::warning('Rate limit exceeded for order', array('order_id' => $order_id));
                wc_add_notice(__('Too many payment attempts. Please try again later.', 'x402-solana-paywall'), 'error');
                return array('result' => 'failure');
            }

            try {
                X402_Logger::info('Processing payment', array('order_id' => $order_id));
                
                $handler      = $this->build_payment_handler();
                $requirements = $this->create_payment_requirements($order, $handler);
                $headers      = $this->collect_request_headers();

                // Validate payment headers
                $headers = X402_Crypto_Validator::sanitize_payment_headers($headers);

                $result = $handler->processPayment($headers, $requirements);

                if (!$result['verified']) {
                    X402_Logger::log_payment_verification($order_id, false, 'Payment not verified by handler');
                    $this->store_payment_requirements($order, $requirements, $handler);
                    return array('result' => 'failure');
                }

                X402_Logger::log_payment_verification($order_id, true);

                // Store transaction data
                if (isset($result['payload']) && $result['payload'] !== null) {
                    $payload = $result['payload'];
                    $payload_array = $payload->toArray();
                    
                    // Extract transaction details
                    $tx_hash = $payload_array['signature'] ?? '';
                    $wallet_address = $payload_array['from'] ?? '';
                    
                    if (!empty($tx_hash)) {
                        // Validate transaction hash
                        $network = $this->get_option('network', 'solana-devnet');
                        if (X402_Crypto_Validator::validate_tx_hash($tx_hash, $network)) {
                            X402_Installer::insert_transaction(array(
                                'order_id' => $order_id,
                                'tx_hash' => $tx_hash,
                                'wallet_address' => $wallet_address,
                                'amount' => $requirements->amount,
                                'asset' => $requirements->asset,
                                'network' => $requirements->network,
                                'status' => 'completed',
                                'facilitator_url' => $this->get_facilitator_endpoint(),
                                'verification_method' => $this->get_option('facilitator_mode', 'base'),
                                'settled' => !empty($result['settlement']) ? 1 : 0,
                                'metadata' => $payload_array,
                            ));
                            
                            X402_Logger::log_transaction($order_id, $tx_hash, $payload_array);
                        }
                    }
                    
                    $order->update_meta_data('_x402_payment_payload', wp_json_encode($payload_array));
                }

                if (!empty($result['settlement'])) {
                    $settlement_header = $handler->createPaymentResponseHeader($result['settlement']);
                    $order->update_meta_data('_x402_payment_response', $settlement_header);
                    $order->update_meta_data('_x402_settlement_complete', true);
                }

                $order->payment_complete();
                $order->add_order_note(__('x402 payment verified successfully.', 'x402-solana-paywall'));
                $order->save();

                if (function_exists('WC') && isset(WC()->cart)) {
                    WC()->cart->empty_cart();
                }

                X402_Logger::info('Payment completed successfully', array('order_id' => $order_id));

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } catch (PaymentRequiredException $exception) {
                X402_Logger::error('Payment required exception', array(
                    'order_id' => $order_id,
                    'message' => $exception->getMessage(),
                ));
                wc_add_notice(
                    sprintf(
                        __('Payment verification failed: %s', 'x402-solana-paywall'),
                        esc_html($exception->getMessage())
                    ),
                    'error'
                );
            } catch (ValidationException $exception) {
                X402_Logger::error('Validation exception', array(
                    'order_id' => $order_id,
                    'message' => $exception->getMessage(),
                ));
                wc_add_notice(
                    sprintf(
                        __('Invalid payment configuration: %s', 'x402-solana-paywall'),
                        esc_html($exception->getMessage())
                    ),
                    'error'
                );
            } catch (Exception $exception) {
                X402_Logger::critical('Unexpected exception', array(
                    'order_id' => $order_id,
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ));
                wc_add_notice(
                    sprintf(
                        __('Unexpected error while processing x402 payment: %s', 'x402-solana-paywall'),
                        esc_html($exception->getMessage())
                    ),
                    'error'
                );
            }

            return array('result' => 'failure');
        }

        /**
         * Build payment handler using facilitator configuration.
         *
         * @return PaymentHandler
         */
        private function build_payment_handler() {
            $auto_settle = 'yes' === $this->get_option('auto_settle', 'yes');
            $facilitator = $this->maybe_create_facilitator();

            return new PaymentHandler($facilitator, $auto_settle);
        }

        /**
         * Create facilitator client when configured.
         *
         * @return FacilitatorClient|null
         */
        private function maybe_create_facilitator() {
            $mode = $this->get_option('facilitator_mode', 'base');

            if ('base' === $mode) {
                $endpoint = $this->get_facilitator_endpoint();
                $api_key = $this->get_option('facilitator_api_key');
                return FacilitatorClient::selfHosted($endpoint, $api_key ? (string) $api_key : null);
            }

            if ('coinbase' === $mode) {
                $api_key = $this->get_option('facilitator_api_key');
                return FacilitatorClient::coinbase($api_key ? (string) $api_key : null);
            }

            if ('custom' === $mode) {
                $endpoint = trim((string) $this->get_option('facilitator_endpoint'));

                if ('' === $endpoint) {
                    throw new ValidationException(__('Custom facilitator endpoint is required when using the Custom Facilitator option.', 'x402-solana-paywall'));
                }

                if (!X402_Crypto_Validator::validate_facilitator_url($endpoint)) {
                    throw new ValidationException(__('Custom facilitator endpoint must be a valid HTTPS URL (localhost allowed).', 'x402-solana-paywall'));
                }

                $sanitized_endpoint = esc_url_raw($endpoint, array('http', 'https'));

                if ('' === $sanitized_endpoint) {
                    throw new ValidationException(__('Custom facilitator endpoint is not a valid URL.', 'x402-solana-paywall'));
                }

                $api_key = $this->get_option('facilitator_api_key');
                return FacilitatorClient::selfHosted($sanitized_endpoint, $api_key ? (string) $api_key : null);
            }

            return null;
        }

        /**
         * Get facilitator endpoint URL with validation.
         *
         * @return string
         */
        private function get_facilitator_endpoint() {
            $endpoint = trim((string) $this->get_option('facilitator_endpoint', 'https://facilitator.base.org'));
            
            if ('' === $endpoint) {
                return 'https://facilitator.base.org';
            }

            return esc_url_raw($endpoint, array('http', 'https'));
        }

        /**
         * Create payment requirements for the WooCommerce order.
         *
         * @param WC_Order $order WooCommerce order instance.
         * @return PaymentRequirements
         */
        private function create_payment_requirements(WC_Order $order, PaymentHandler $handler) {
            $pay_to  = trim((string) $this->get_option('pay_to'));
            $asset   = trim((string) $this->get_option('asset'));
            $network = $this->get_option('network', 'solana-devnet');

            if ('' === $pay_to || '' === $asset) {
                throw new ValidationException(__('Both the pay-to wallet address and asset address must be configured for x402 payments.', 'x402-solana-paywall'));
            }

            if (!Validator::isValidNetwork($network)) {
                throw new ValidationException(__('The configured x402 network is not supported.', 'x402-solana-paywall'));
            }

            // Additional crypto validation
            $network_type = X402_Crypto_Validator::get_network_type($network);
            
            if ($network_type === 'solana' && !X402_Crypto_Validator::validate_solana_address($pay_to)) {
                throw new ValidationException(__('The configured pay-to address is not a valid Solana address.', 'x402-solana-paywall'));
            } elseif ($network_type === 'evm' && !X402_Crypto_Validator::validate_eth_address($pay_to)) {
                throw new ValidationException(__('The configured pay-to address is not a valid EVM address.', 'x402-solana-paywall'));
            }
            
            if ($network_type === 'solana' && !X402_Crypto_Validator::validate_solana_address($asset)) {
                throw new ValidationException(__('The configured asset address is not a valid Solana address.', 'x402-solana-paywall'));
            } elseif ($network_type === 'evm' && !X402_Crypto_Validator::validate_eth_address($asset)) {
                throw new ValidationException(__('The configured asset address is not a valid EVM address.', 'x402-solana-paywall'));
            }

            if (!Validator::isValidAddress($pay_to, $network)) {
                throw new ValidationException(__('The configured pay-to address is not valid for the selected network.', 'x402-solana-paywall'));
            }

            if (!Validator::isValidAddress($asset, $network)) {
                throw new ValidationException(__('The configured asset address is not valid for the selected network.', 'x402-solana-paywall'));
            }

            $decimals      = max(0, (int) $this->get_option('asset_decimals', 6));
            $amount_atomic = $this->convert_to_atomic_amount((string) $order->get_total('edit'), $decimals);

            $description = sprintf(
                /* translators: %s: Order number. */
                __('WooCommerce order %s', 'x402-solana-paywall'),
                $order->get_order_number()
            );

            $resource_url = add_query_arg(
                array(
                    'key' => $order->get_order_key(),
                ),
                rest_url(sprintf('x402/v1/orders/%d', $order->get_id()))
            );

            return $handler->createPaymentRequirements(
                payTo: $pay_to,
                amount: $amount_atomic,
                resource: esc_url_raw($resource_url),
                description: $description,
                asset: $asset,
                network: $network,
                scheme: 'exact',
                timeout: max(60, (int) $this->get_option('timeout', 600)),
                mimeType: 'application/json',
                extra: $this->get_payment_extra_data($network),
                id: 'wc-order-' . $order->get_id()
            );
        }

        /**
         * Generate the serialized payment request for an order.
         *
         * @param WC_Order $order WooCommerce order instance.
         * @return array<string, mixed>
         */
        public function build_payment_request_payload(WC_Order $order) {
            $handler      = $this->build_payment_handler();
            $requirements = $this->create_payment_requirements($order, $handler);
            $response     = $handler->createPaymentRequiredResponse($requirements);

            $response_array = $response->toArray();

            return array(
                'requirements' => $requirements->toArray(),
                'response'     => $response_array,
                'encoded'      => base64_encode(wp_json_encode($response_array)),
            );
        }

        /**
         * Convert display amount into atomic units based on decimals.
         *
         * @param string $amount  Display amount.
         * @param int    $decimals Decimal precision.
         * @return string
         */
        private function convert_to_atomic_amount($amount, $decimals) {
            $normalized = wc_format_decimal($amount, $decimals);
            if (false === $normalized) {
                throw new ValidationException(__('Unable to normalize the order total for x402 payment.', 'x402-solana-paywall'));
            }

            $parts    = explode('.', (string) $normalized);
            $whole    = preg_replace('/[^0-9]/', '', $parts[0]);
            $fraction = isset($parts[1]) ? str_pad($parts[1], $decimals, '0') : str_repeat('0', $decimals);

            $value = ltrim($whole . $fraction, '0');

            return '' === $value ? '0' : $value;
        }

        /**
         * Determine extra payload details based on network.
         *
         * @param string $network Selected network.
         * @return array<string, string>|null
         */
        private function get_payment_extra_data($network) {
            if (Validator::isSvmNetwork($network)) {
                $fee_payer = trim((string) $this->get_option('fee_payer'));
                if ('' !== $fee_payer) {
                    if (!Validator::isValidSolanaAddress($fee_payer)) {
                        throw new ValidationException(__('The configured fee payer is not a valid Solana address.', 'x402-solana-paywall'));
                    }

                    return array('feePayer' => $fee_payer);
                }

                return null;
            }

            $name    = trim((string) $this->get_option('asset_name'));
            $version = trim((string) $this->get_option('asset_version'));

            if ('' === $name || '' === $version) {
                throw new ValidationException(__('Asset name and version are required for EVM networks.', 'x402-solana-paywall'));
            }

            return array(
                'name'    => $name,
                'version' => $version,
            );
        }

        /**
         * Persist payment requirements and add checkout notices.
         *
         * @param WC_Order         $order        WooCommerce order.
         * @param PaymentRequirements $requirements Payment requirements.
         * @param PaymentHandler   $handler      Payment handler instance.
         */
        private function store_payment_requirements(WC_Order $order, PaymentRequirements $requirements, PaymentHandler $handler) {
            $response = $handler->createPaymentRequiredResponse($requirements);
            $encoded  = base64_encode(wp_json_encode($response->toArray()));

            $order->update_meta_data('_x402_payment_requirements', wp_json_encode($response->toArray()));
            $order->save();

            wc_add_notice(__('Payment required using an x402-compatible wallet.', 'x402-solana-paywall'), 'error');
            wc_add_notice(
                sprintf(
                    /* translators: %s: Base64 encoded payment request payload. */
                    __('Present this payment request to your wallet: %s', 'x402-solana-paywall'),
                    '<code>' . esc_html($encoded) . '</code>'
                ),
                'notice'
            );
        }

        /**
         * Display payment form on checkout page.
         */
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            
            // Display custom token info if enabled
            if ($this->get_option('use_custom_token') === 'yes') {
                $token_info = $this->get_custom_token_info();
                if ($token_info) {
                    $this->render_custom_token_payment_form($token_info);
                }
            } else {
                $this->render_standard_payment_form();
            }
        }

        /**
         * Render custom token payment form.
         *
         * @param array $token_info Token information.
         */
        private function render_custom_token_payment_form($token_info) {
            echo '<div class="x402-payment-form x402-custom-token">';
            
            echo '<div class="x402-token-info" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0;">';
            echo '<h4 style="margin-top: 0;">Payment Token: ' . esc_html($token_info['name']) . ' (' . esc_html($token_info['symbol']) . ')</h4>';
            
            if (isset($token_info['address']) || isset($token_info['mint'])) {
                $address = $token_info['address'] ?? $token_info['mint'];
                echo '<p><strong>Contract Address (CA):</strong><br>';
                echo '<code style="background: #fff; padding: 5px 10px; display: inline-block; border-radius: 3px; font-size: 11px; word-break: break-all;">' . esc_html($address) . '</code>';
                echo '</p>';
            }
            
            echo '<p><strong>Network:</strong> ' . esc_html(ucfirst($token_info['network'])) . '</p>';
            echo '<p><strong>Decimals:</strong> ' . esc_html($token_info['decimals']) . '</p>';
            
            if (isset($token_info['manual_price']) && $token_info['manual_price'] !== false) {
                echo '<p><strong>Token Price:</strong> $' . esc_html(number_format($token_info['manual_price'], 6)) . ' USD</p>';
            }
            
            echo '<p class="x402-token-amount" style="font-size: 16px; font-weight: bold; color: #2271b1;"></p>';
            echo '</div>';
            
            // Hidden field to pass token selection
            echo '<input type="hidden" name="x402_token" value="custom" />';
            
            echo '</div>';
        }

        /**
         * Render standard token selection form.
         */
        private function render_standard_payment_form() {
            $accepted_tokens = $this->get_option('accepted_tokens', array());
            $default_token = $this->get_option('default_token', 'USDC_solana-mainnet');
            
            if (empty($accepted_tokens)) {
                // If no specific tokens selected, show all
                $accepted_tokens = array_keys($this->get_token_options());
            }
            
            if (count($accepted_tokens) === 1) {
                // Only one token, no need for selector
                echo '<input type="hidden" name="x402_token" value="' . esc_attr($accepted_tokens[0]) . '" />';
                
                // Show token info
                $parts = explode('_', $accepted_tokens[0]);
                $symbol = $parts[0];
                $network = implode('_', array_slice($parts, 1));
                $token_info = X402_Token_Handler::get_token_info($symbol, $network);
                
                if ($token_info) {
                    echo '<div class="x402-token-info" style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">';
                    echo '<p><strong>Payment Token:</strong> ' . esc_html($token_info['name']) . ' (' . esc_html($token_info['symbol']) . ')</p>';
                    echo '<p class="x402-token-amount"></p>';
                    echo '</div>';
                }
            } else {
                // Multiple tokens, show selector
                echo '<div class="x402-payment-form">';
                echo '<p class="form-row form-row-wide">';
                echo '<label for="x402_token">' . esc_html__('Select Payment Token', 'x402-solana-paywall') . ' <span class="required">*</span></label>';
                echo '<select id="x402_token_select" name="x402_token" class="select" required>';
                
                $token_options = $this->get_token_options();
                foreach ($accepted_tokens as $token_key) {
                    if (isset($token_options[$token_key])) {
                        $selected = ($token_key === $default_token) ? ' selected' : '';
                        echo '<option value="' . esc_attr($token_key) . '"' . $selected . '>' . esc_html($token_options[$token_key]) . '</option>';
                    }
                }
                
                echo '</select>';
                echo '</p>';
                
                echo '<div class="x402-token-info" style="margin: 10px 0;">';
                echo '<p class="x402-token-amount"></p>';
                echo '<p class="x402-token-price" style="font-size: 12px; color: #666;"></p>';
                echo '</div>';
                
                echo '<input type="hidden" name="order_total" id="order_total" value="" />';
                echo '</div>';
            }
        }

        /**
         * Render payment details on the order view page.
         *
         * @param WC_Order $order WooCommerce order instance.
         */
        public function render_order_payment_details($order) {
            if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->id) {
                return;
            }

            $requirements_raw = $order->get_meta('_x402_payment_requirements');

            if (empty($requirements_raw)) {
                return;
            }

            $encoded = base64_encode($requirements_raw);

            echo '<section class="woocommerce-order-x402-details">';
            echo '<h2>' . esc_html__('x402 Payment Details', 'x402-solana-paywall') . '</h2>';
            echo '<p>' . esc_html__('Provide the following payment request to your x402 wallet if you need to retry the payment.', 'x402-solana-paywall') . '</p>';
            echo '<pre class="x402-payment-request" style="white-space: pre-wrap; word-break: break-all;">' . esc_html($encoded) . '</pre>';
            echo '</section>';
        }

        /**
         * Collect request headers for payment verification.
         *
         * @return array<string, string>
         */
        private function collect_request_headers() {
            $headers = array();

            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            }

            if (empty($headers)) {
                foreach ($_SERVER as $name => $value) {
                    if (0 === strpos($name, 'HTTP_')) {
                        $header_name              = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                        $headers[$header_name] = $value;
                    }
                }
            }

            return is_array($headers) ? $headers : array();
        }

        /**
         * Retrieve supported network choices.
         *
         * @return array<string, string>
         */
        private function get_supported_networks() {
            $options = array();

            foreach (Validator::SUPPORTED_NETWORKS as $network) {
                $options[$network] = $network;
            }

            return $options;
        }

        /**
         * Log gateway errors for debugging purposes.
         *
         * @param string $message Error message.
         */
        private function log_error($message) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error($message, array('source' => $this->id));
            }
        }
    }
}
