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
                    'default'     => 'none',
                    'options'     => array(
                        'none'     => __('No facilitator (manual verification)', 'x402-solana-paywall'),
                        'coinbase' => __('Coinbase Facilitator', 'x402-solana-paywall'),
                        'custom'   => __('Custom Facilitator', 'x402-solana-paywall'),
                    ),
                ),
                'facilitator_endpoint' => array(
                    'title'       => __('Custom Facilitator Endpoint', 'x402-solana-paywall'),
                    'type'        => 'text',
                    'description' => __('Base URL of your self-hosted facilitator (required when using Custom Facilitator).', 'x402-solana-paywall'),
                    'default'     => '',
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
            );
        }

        /**
         * Process payment for the given order ID.
         *
         * @param int $order_id Order identifier.
         * @return array
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            if (!$order instanceof WC_Order) {
                wc_add_notice(__('Unable to locate the order for x402 payment processing.', 'x402-solana-paywall'), 'error');
                return array('result' => 'failure');
            }

            try {
                $handler      = $this->build_payment_handler();
                $requirements = $this->create_payment_requirements($order, $handler);
                $headers      = $this->collect_request_headers();

                $result = $handler->processPayment($headers, $requirements);

                if (!$result['verified']) {
                    $this->store_payment_requirements($order, $requirements, $handler);
                    return array('result' => 'failure');
                }

                if (!empty($result['settlement'])) {
                    $settlement_header = $handler->createPaymentResponseHeader($result['settlement']);
                    $order->update_meta_data('_x402_payment_response', $settlement_header);
                }

                $payload = $result['payload'];
                if ($payload !== null) {
                    $order->update_meta_data('_x402_payment_payload', wp_json_encode($payload->toArray()));
                }

                $order->payment_complete();
                $order->add_order_note(__('x402 payment verified successfully.', 'x402-solana-paywall'));
                $order->save();

                if (function_exists('WC') && isset(WC()->cart)) {
                    WC()->cart->empty_cart();
                }

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } catch (PaymentRequiredException $exception) {
                $this->log_error($exception->getMessage());
                wc_add_notice(
                    sprintf(
                        __('Payment verification failed: %s', 'x402-solana-paywall'),
                        esc_html($exception->getMessage())
                    ),
                    'error'
                );
            } catch (ValidationException $exception) {
                $this->log_error($exception->getMessage());
                wc_add_notice(
                    sprintf(
                        __('Invalid payment configuration: %s', 'x402-solana-paywall'),
                        esc_html($exception->getMessage())
                    ),
                    'error'
                );
            } catch (Exception $exception) {
                $this->log_error($exception->getMessage());
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
            $mode = $this->get_option('facilitator_mode', 'none');

            if ('coinbase' === $mode) {
                $api_key = $this->get_option('facilitator_api_key');
                return FacilitatorClient::coinbase($api_key ? (string) $api_key : null);
            }

            if ('custom' === $mode) {
                $endpoint = trim((string) $this->get_option('facilitator_endpoint'));

                if ('' === $endpoint) {
                    throw new ValidationException(__('Custom facilitator endpoint is required when using the Custom Facilitator option.', 'x402-solana-paywall'));
                }

                $api_key = $this->get_option('facilitator_api_key');
                return FacilitatorClient::selfHosted($endpoint, $api_key ? (string) $api_key : null);
            }

            return null;
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
