<?php
/**
 * Payment Success Template
 * 
 * This template can be overridden by copying it to yourtheme/x402/payment-success.php
 *
 * @package X402_Solana_Paywall
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$transaction_id = isset($_GET['tx']) ? sanitize_text_field($_GET['tx']) : '';
$post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

$success_data = array(
    'transaction_id' => $transaction_id,
    'post_id' => $post_id,
);

// Allow themes to wrap content
do_action('x402_before_success_content');
?>

<div class="x402-success-container">
    <?php
    /**
     * Hook: x402_before_payment_success
     */
    do_action('x402_before_payment_success', $success_data);
    ?>

    <div class="x402-success-wrapper">
        
        <?php
        /**
         * Hook: x402_payment_success_header
         * 
         * @hooked x402_template_success_icon - 10
         * @hooked x402_template_success_title - 20
         */
        do_action('x402_payment_success_header', $success_data);
        ?>

        <div class="x402-success-content">
            
            <?php
            /**
             * Hook: x402_payment_success_content
             * 
             * @hooked x402_template_success_message - 10
             * @hooked x402_template_transaction_details - 20
             * @hooked x402_template_access_button - 30
             */
            do_action('x402_payment_success_content', $success_data);
            ?>

        </div>

        <?php
        /**
         * Hook: x402_payment_success_footer
         * 
         * @hooked x402_template_success_next_steps - 10
         */
        do_action('x402_payment_success_footer', $success_data);
        ?>

    </div>

    <?php
    /**
     * Hook: x402_after_payment_success
     */
    do_action('x402_after_payment_success', $success_data);
    ?>
</div>

<?php
do_action('x402_after_success_content');

get_footer();
