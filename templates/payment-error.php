<?php
/**
 * Payment Error Message Template Part
 * 
 * This template can be overridden by copying it to yourtheme/x402/payment-error.php
 *
 * @package X402_Solana_Paywall
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($args['message']) ? $args['message'] : __('Payment verification failed.', 'x402-solana-paywall');
$post_id = isset($args['post_id']) ? $args['post_id'] : 0;

/**
 * Hook: x402_before_error_message
 */
do_action('x402_before_error_message', $args);
?>

<div class="x402-error-message">
    
    <div class="x402-error-icon">
        <?php
        /**
         * Hook: x402_error_icon
         * 
         * @hooked x402_template_default_error_icon - 10
         */
        do_action('x402_error_icon', $args);
        ?>
    </div>

    <h2 class="x402-error-title">
        <?php
        echo esc_html(apply_filters('x402_error_title', __('Payment Failed', 'x402-solana-paywall'), $args));
        ?>
    </h2>

    <div class="x402-error-text">
        <?php
        echo wp_kses_post(apply_filters('x402_error_message', $message, $args));
        ?>
    </div>

    <div class="x402-error-actions">
        <?php if ($post_id): ?>
        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="x402-button x402-button-primary">
            <?php echo esc_html(apply_filters('x402_retry_button_text', __('Try Again', 'x402-solana-paywall'), $args)); ?>
        </a>
        <?php endif; ?>
        
        <a href="<?php echo esc_url(home_url('/')); ?>" class="x402-button x402-button-secondary">
            <?php echo esc_html(apply_filters('x402_home_button_text', __('Go Home', 'x402-solana-paywall'), $args)); ?>
        </a>
    </div>

    <div class="x402-error-help">
        <?php
        /**
         * Hook: x402_error_help_content
         * 
         * @hooked x402_template_error_troubleshooting - 10
         * @hooked x402_template_error_support_link - 20
         */
        do_action('x402_error_help_content', $args);
        ?>
    </div>

</div>

<?php
/**
 * Hook: x402_after_error_message
 */
do_action('x402_after_error_message', $args);
