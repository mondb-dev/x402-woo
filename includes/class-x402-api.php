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

        // REST endpoints for x402 payment integrations
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
    }
    
    /**
     * Verify payment AJAX handler
     */
    public static function verify_payment() {
        // Verify nonce
        if (!class_exists('X402_Security_Handler') || !X402_Security_Handler::verify_nonce(X402_Security_Handler::VERIFY_PAYMENT_ACTION)) {
            wp_send_json_error(
                array(
                    'message' => __('Security check failed', 'x402-solana-paywall'),
                ),
                403
            );
        }

        // Get and validate input
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $signature = isset($_POST['signature']) ? sanitize_text_field(wp_unslash($_POST['signature'])) : '';
        $wallet_address = isset($_POST['wallet_address']) ? sanitize_text_field(wp_unslash($_POST['wallet_address'])) : '';
        
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

    /**
     * Register REST routes used by the WooCommerce gateway.
     */
    public static function register_rest_routes() {
        register_rest_route(
            'x402/v1',
            '/orders/(?P<order_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_order_payment_request'),
                'permission_callback' => array(__CLASS__, 'can_access_order_payment_request'),
                'args'                => array(
                    'order_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && (int) $param > 0;
                        },
                    ),
                    'key' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    /**
     * Provide serialized payment requirements for an order if available.
     *
     * @param WP_REST_Request $request Request instance.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_order_payment_request(WP_REST_Request $request) {
        if (!class_exists('WC_Order')) {
            return new WP_Error('woocommerce_missing', __('WooCommerce must be active to use x402 payments.', 'x402-solana-paywall'), array('status' => 500));
        }

        $order_id = (int) $request->get_param('order_id');
        $order    = wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            return new WP_Error('order_not_found', __('Order not found.', 'x402-solana-paywall'), array('status' => 404));
        }

        $provided_key = (string) $request->get_param('key');

        if ($order->get_order_key() !== $provided_key) {
            return new WP_Error('forbidden', __('Invalid order access key.', 'x402-solana-paywall'), array('status' => 403));
        }

        if ($order->get_payment_method() !== 'x402') {
            return new WP_Error('invalid_payment_method', __('Order is not configured for x402 payments.', 'x402-solana-paywall'), array('status' => 400));
        }

        $stored_payload = $order->get_meta('_x402_payment_requirements');

        if (!empty($stored_payload)) {
            $data = json_decode($stored_payload, true);
            if (is_array($data)) {
                return rest_ensure_response(
                    array(
                        'response' => $data,
                        'encoded'  => base64_encode($stored_payload),
                    )
                );
            }
        }

        if (!class_exists('X402_WooCommerce_Gateway')) {
            return new WP_Error('gateway_unavailable', __('x402 payment gateway is unavailable.', 'x402-solana-paywall'), array('status' => 500));
        }

        $gateway = new X402_WooCommerce_Gateway();

        try {
            $payload = $gateway->build_payment_request_payload($order);
        } catch (Exception $exception) {
            return new WP_Error('payment_error', $exception->getMessage(), array('status' => 500));
        }

        $order->update_meta_data('_x402_payment_requirements', wp_json_encode($payload['response']));
        $order->save();

        return rest_ensure_response(
            array(
                'response' => $payload['response'],
                'encoded'  => $payload['encoded'],
            )
        );
    }

    /**
     * Restrict access to order payment requests exposed over REST.
     *
     * @param WP_REST_Request $request Request instance.
     * @return bool
     */
    public static function can_access_order_payment_request(WP_REST_Request $request) {
        if (!class_exists('WC_Order')) {
            return current_user_can('manage_woocommerce') || current_user_can('manage_options');
        }

        $order_id = (int) $request->get_param('order_id');
        $order    = wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            return false;
        }

        if (class_exists('X402_Security_Handler') && X402_Security_Handler::can_manage_payments()) {
            return true;
        }

        $user_id = get_current_user_id();

        if ($user_id > 0 && (int) $order->get_customer_id() === (int) $user_id) {
            return true;
        }

        $provided_key = (string) $request->get_param('key');

        if ('' !== $provided_key && hash_equals($order->get_order_key(), $provided_key)) {
            return true;
        }

        return false;
    }
}
