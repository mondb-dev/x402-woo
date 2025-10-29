<?php
/**
 * Payment Required Template
 * 
 * This template can be overridden by copying it to yourtheme/x402/payment-required.php
 *
 * @package X402_Solana_Paywall
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$paywall_data = X402_Template_Handler::get_paywall_data();

// Allow themes to wrap content
do_action('x402_before_paywall_content');
?>

<div class="x402-paywall-container">
    <?php
    /**
     * Hook: x402_before_paywall
     * 
     * @hooked x402_output_breadcrumbs - 10
     */
    do_action('x402_before_paywall', $paywall_data);
    ?>

    <div class="x402-paywall-wrapper">
        
        <?php
        /**
         * Hook: x402_paywall_header
         * 
         * @hooked x402_template_paywall_title - 10
         * @hooked x402_template_paywall_description - 20
         */
        do_action('x402_paywall_header', $paywall_data);
        ?>

        <div class="x402-paywall-content">
            
            <?php
            /**
             * Hook: x402_paywall_content
             * 
             * @hooked x402_template_payment_preview - 10
             * @hooked x402_template_payment_form - 20
             * @hooked x402_template_payment_info - 30
             */
            do_action('x402_paywall_content', $paywall_data);
            ?>

        </div>

        <?php
        /**
         * Hook: x402_paywall_footer
         * 
         * @hooked x402_template_payment_security_notice - 10
         * @hooked x402_template_payment_support - 20
         */
        do_action('x402_paywall_footer', $paywall_data);
        ?>

    </div>

    <?php
    /**
     * Hook: x402_after_paywall
     * 
     * @hooked x402_output_related_content - 10
     */
    do_action('x402_after_paywall', $paywall_data);
    ?>
</div>

<?php
do_action('x402_after_paywall_content');

get_footer();
