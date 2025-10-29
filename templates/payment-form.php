<?php
/**
 * Payment Form Template Part
 * 
 * This template can be overridden by copying it to yourtheme/x402/payment-form.php
 *
 * @package X402_Solana_Paywall
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($args['post_id']) ? $args['post_id'] : get_the_ID();
$paywall_data = X402_Template_Handler::get_paywall_data($post_id);

/**
 * Hook: x402_before_payment_form
 */
do_action('x402_before_payment_form', $paywall_data);
?>

<div class="x402-payment-form">
    
    <div class="x402-payment-amount">
        <?php
        /**
         * Hook: x402_payment_amount_display
         * 
         * @hooked x402_template_amount_label - 10
         * @hooked x402_template_amount_value - 20
         * @hooked x402_template_token_selector - 30
         */
        do_action('x402_payment_amount_display', $paywall_data);
        ?>
    </div>

    <div class="x402-payment-wallet">
        <?php
        /**
         * Hook: x402_payment_wallet_display
         * 
         * @hooked x402_template_wallet_label - 10
         * @hooked x402_template_wallet_address - 20
         * @hooked x402_template_wallet_qr - 30
         */
        do_action('x402_payment_wallet_display', $paywall_data);
        ?>
    </div>

    <div class="x402-payment-actions">
        <?php
        /**
         * Hook: x402_payment_actions
         * 
         * @hooked x402_template_connect_wallet_button - 10
         * @hooked x402_template_manual_verify_button - 20
         * @hooked x402_template_payment_instructions - 30
         */
        do_action('x402_payment_actions', $paywall_data);
        ?>
    </div>

    <div class="x402-payment-status" style="display:none;">
        <?php
        /**
         * Hook: x402_payment_status_display
         * 
         * @hooked x402_template_status_spinner - 10
         * @hooked x402_template_status_message - 20
         */
        do_action('x402_payment_status_display', $paywall_data);
        ?>
    </div>

</div>

<?php
/**
 * Hook: x402_after_payment_form
 */
do_action('x402_after_payment_form', $paywall_data);
