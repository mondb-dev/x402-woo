<?php
/**
 * Payment Failed Template
 * 
 * This template can be overridden by copying it to yourtheme/x402/payment-failed.php
 *
 * @package X402_Solana_Paywall
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$error_message = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : __('Payment verification failed.', 'x402-solana-paywall');
$post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

$error_data = array(
    'message' => $error_message,
    'post_id' => $post_id,
);

// Allow themes to wrap content
do_action('x402_before_error_content');
?>

<div class="x402-error-container">
    <?php
    /**
     * Hook: x402_before_payment_error
     */
    do_action('x402_before_payment_error', $error_data);
    ?>

    <div class="x402-error-wrapper">
        
        <?php
        /**
         * Hook: x402_payment_error_header
         * 
         * @hooked x402_template_error_icon - 10
         * @hooked x402_template_error_title - 20
         */
        do_action('x402_payment_error_header', $error_data);
        ?>

        <div class="x402-error-content">
            
            <?php
            /**
             * Hook: x402_payment_error_content
             * 
             * @hooked x402_template_error_message - 10
             * @hooked x402_template_error_details - 20
             * @hooked x402_template_retry_button - 30
             */
            do_action('x402_payment_error_content', $error_data);
            ?>

        </div>

        <?php
        /**
         * Hook: x402_payment_error_footer
         * 
         * @hooked x402_template_error_support - 10
         * @hooked x402_template_error_troubleshooting - 20
         */
        do_action('x402_payment_error_footer', $error_data);
        ?>

    </div>

    <?php
    /**
     * Hook: x402_after_payment_error
     */
    do_action('x402_after_payment_error', $error_data);
    ?>
</div>

<?php
do_action('x402_after_error_content');

get_footer();
