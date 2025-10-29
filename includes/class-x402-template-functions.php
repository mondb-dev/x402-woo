<?php
/**
 * X402 Template Functions - Default hook implementations
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default template hooks
 */

// Paywall Header Hooks
add_action('x402_paywall_header', 'x402_template_paywall_title', 10);
add_action('x402_paywall_header', 'x402_template_paywall_description', 20);

// Paywall Content Hooks
add_action('x402_paywall_content', 'x402_template_payment_preview', 10);
add_action('x402_paywall_content', 'x402_template_payment_form', 20);
add_action('x402_paywall_content', 'x402_template_payment_info', 30);

// Paywall Footer Hooks
add_action('x402_paywall_footer', 'x402_template_payment_security_notice', 10);

// Success Page Hooks
add_action('x402_payment_success_header', 'x402_template_success_icon', 10);
add_action('x402_payment_success_header', 'x402_template_success_title', 20);
add_action('x402_payment_success_content', 'x402_template_success_message', 10);
add_action('x402_payment_success_content', 'x402_template_transaction_details', 20);
add_action('x402_payment_success_content', 'x402_template_access_button', 30);

// Error Page Hooks
add_action('x402_payment_error_header', 'x402_template_error_icon', 10);
add_action('x402_payment_error_header', 'x402_template_error_title', 20);
add_action('x402_payment_error_content', 'x402_template_error_message', 10);
add_action('x402_payment_error_content', 'x402_template_retry_button', 30);
add_action('x402_payment_error_footer', 'x402_template_error_support', 10);

// Payment Display Hooks
add_action('x402_payment_amount_display', 'x402_template_amount_label', 10);
add_action('x402_payment_amount_display', 'x402_template_amount_value', 20);
add_action('x402_payment_amount_display', 'x402_template_token_selector', 30);

add_action('x402_payment_wallet_display', 'x402_template_wallet_label', 10);
add_action('x402_payment_wallet_display', 'x402_template_wallet_address', 20);

add_action('x402_payment_actions', 'x402_template_connect_wallet_button', 10);
add_action('x402_payment_actions', 'x402_template_manual_verify_button', 20);

add_action('x402_payment_status_display', 'x402_template_status_spinner', 10);
add_action('x402_payment_status_display', 'x402_template_status_message', 20);

// Icon Hooks
add_action('x402_success_icon', 'x402_template_default_success_icon', 10);
add_action('x402_error_icon', 'x402_template_default_error_icon', 10);

/**
 * Template Functions
 */

/**
 * Output paywall title
 */
function x402_template_paywall_title($paywall_data) {
    $title = apply_filters('x402_paywall_title', get_the_title($paywall_data['post_id']), $paywall_data);
    echo '<h1 class="x402-paywall-title">' . esc_html($title) . '</h1>';
}

/**
 * Output paywall description
 */
function x402_template_paywall_description($paywall_data) {
    $description = !empty($paywall_data['description']) 
        ? $paywall_data['description'] 
        : __('This content requires payment to access.', 'x402-solana-paywall');
    
    $description = apply_filters('x402_paywall_description', $description, $paywall_data);
    echo '<div class="x402-paywall-description">' . wp_kses_post($description) . '</div>';
}

/**
 * Output payment preview
 */
function x402_template_payment_preview($paywall_data) {
    $excerpt = get_the_excerpt($paywall_data['post_id']);
    if (empty($excerpt)) {
        return;
    }
    
    echo '<div class="x402-payment-preview">';
    echo '<h3>' . esc_html__('Content Preview', 'x402-solana-paywall') . '</h3>';
    echo '<div class="x402-preview-content">' . wp_kses_post($excerpt) . '</div>';
    echo '</div>';
}

/**
 * Output payment form
 */
function x402_template_payment_form($paywall_data) {
    X402_Template_Handler::get_template_part('payment', 'form', array('post_id' => $paywall_data['post_id']));
}

/**
 * Output payment info
 */
function x402_template_payment_info($paywall_data) {
    echo '<div class="x402-payment-info">';
    echo '<h3>' . esc_html__('How it works', 'x402-solana-paywall') . '</h3>';
    echo '<ol>';
    echo '<li>' . esc_html__('Connect your wallet or copy the payment address', 'x402-solana-paywall') . '</li>';
    echo '<li>' . esc_html__('Send the exact amount to the provided address', 'x402-solana-paywall') . '</li>';
    echo '<li>' . esc_html__('Wait for confirmation (usually takes a few seconds)', 'x402-solana-paywall') . '</li>';
    echo '<li>' . esc_html__('Access your content immediately', 'x402-solana-paywall') . '</li>';
    echo '</ol>';
    echo '</div>';
}

/**
 * Output security notice
 */
function x402_template_payment_security_notice($paywall_data) {
    echo '<div class="x402-security-notice">';
    echo '<p class="x402-security-text">';
    echo '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 0.5rem;">';
    echo '<path d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.777 11.777 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7.159 7.159 0 0 0 1.048-.625 11.775 11.775 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.541 1.541 0 0 0-1.044-1.263 62.467 62.467 0 0 0-2.887-.87C9.843.266 8.69 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99A1.5 1.5 0 0 1 8 5z"/>';
    echo '</svg>';
    echo esc_html__('Secure payment powered by blockchain technology', 'x402-solana-paywall');
    echo '</p>';
    echo '</div>';
}

/**
 * Output amount label
 */
function x402_template_amount_label($paywall_data) {
    echo '<p class="amount-label">' . esc_html__('Payment Amount', 'x402-solana-paywall') . '</p>';
}

/**
 * Output amount value
 */
function x402_template_amount_value($paywall_data) {
    $amount = $paywall_data['amount'];
    $currency = $paywall_data['currency'];
    
    echo '<div class="amount-value" data-amount="' . esc_attr($amount) . '" data-currency="' . esc_attr($currency) . '">';
    echo esc_html($amount . ' ' . $currency);
    echo '</div>';
}

/**
 * Output token selector
 */
function x402_template_token_selector($paywall_data) {
    if (empty($paywall_data['token_info'])) {
        return;
    }
    
    echo '<div class="x402-token-selector">';
    echo '<label for="x402-token-select">' . esc_html__('Pay with', 'x402-solana-paywall') . '</label>';
    echo '<select id="x402-token-select" class="x402-token-select">';
    // This would be populated dynamically via JS
    echo '<option value="' . esc_attr($paywall_data['token_info']['symbol']) . '">';
    echo esc_html($paywall_data['token_info']['name']);
    echo '</option>';
    echo '</select>';
    echo '</div>';
}

/**
 * Output wallet label
 */
function x402_template_wallet_label($paywall_data) {
    echo '<p class="wallet-label">' . esc_html__('Payment Address', 'x402-solana-paywall') . '</p>';
}

/**
 * Output wallet address
 */
function x402_template_wallet_address($paywall_data) {
    if (empty($paywall_data['wallet_address'])) {
        return;
    }
    
    echo '<div class="x402-wallet-address-wrapper">';
    echo '<code class="x402-wallet-address" id="x402-wallet-address">' . esc_html($paywall_data['wallet_address']) . '</code>';
    echo '<button type="button" class="x402-copy-button" data-copy-target="#x402-wallet-address">';
    echo esc_html__('Copy', 'x402-solana-paywall');
    echo '</button>';
    echo '</div>';
}

/**
 * Output connect wallet button
 */
function x402_template_connect_wallet_button($paywall_data) {
    echo '<button type="button" class="x402-button x402-button-primary x402-connect-wallet" data-post-id="' . esc_attr($paywall_data['post_id']) . '">';
    echo esc_html__('Connect Wallet & Pay', 'x402-solana-paywall');
    echo '</button>';
}

/**
 * Output manual verify button
 */
function x402_template_manual_verify_button($paywall_data) {
    echo '<button type="button" class="x402-button x402-button-outline x402-verify-payment" data-post-id="' . esc_attr($paywall_data['post_id']) . '">';
    echo esc_html__('I\'ve Already Paid - Verify', 'x402-solana-paywall');
    echo '</button>';
}

/**
 * Output status spinner
 */
function x402_template_status_spinner($paywall_data) {
    echo '<div class="x402-status-spinner"></div>';
}

/**
 * Output status message
 */
function x402_template_status_message($paywall_data) {
    echo '<p class="x402-status-message">' . esc_html__('Verifying payment...', 'x402-solana-paywall') . '</p>';
}

/**
 * Output success icon
 */
function x402_template_default_success_icon($args) {
    echo '<svg width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #10b981;">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />';
    echo '</svg>';
}

/**
 * Output error icon
 */
function x402_template_default_error_icon($args) {
    echo '<svg width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #ef4444;">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />';
    echo '</svg>';
}

/**
 * Output success title
 */
function x402_template_success_title($success_data) {
    $title = apply_filters('x402_success_title', __('Payment Successful!', 'x402-solana-paywall'), $success_data);
    echo '<h1 class="x402-success-title">' . esc_html($title) . '</h1>';
}

/**
 * Output success message
 */
function x402_template_success_message($success_data) {
    $message = __('Your payment has been verified successfully. You now have access to the content.', 'x402-solana-paywall');
    $message = apply_filters('x402_success_message', $message, $success_data);
    echo '<div class="x402-success-text">' . wp_kses_post($message) . '</div>';
}

/**
 * Output transaction details
 */
function x402_template_transaction_details($success_data) {
    if (empty($success_data['transaction_id'])) {
        return;
    }
    
    echo '<div class="x402-transaction-info">';
    echo '<p class="x402-transaction-label">' . esc_html__('Transaction ID:', 'x402-solana-paywall') . '</p>';
    echo '<p class="x402-transaction-id"><code>' . esc_html($success_data['transaction_id']) . '</code></p>';
    echo '</div>';
}

/**
 * Output access button
 */
function x402_template_access_button($success_data) {
    if (empty($success_data['post_id'])) {
        return;
    }
    
    echo '<div class="x402-access-actions">';
    echo '<a href="' . esc_url(get_permalink($success_data['post_id'])) . '" class="x402-button x402-button-primary">';
    echo esc_html__('View Content', 'x402-solana-paywall');
    echo '</a>';
    echo '</div>';
}

/**
 * Output error title
 */
function x402_template_error_title($error_data) {
    $title = apply_filters('x402_error_title', __('Payment Failed', 'x402-solana-paywall'), $error_data);
    echo '<h1 class="x402-error-title">' . esc_html($title) . '</h1>';
}

/**
 * Output error message
 */
function x402_template_error_message($error_data) {
    $message = !empty($error_data['message']) ? $error_data['message'] : __('Payment verification failed.', 'x402-solana-paywall');
    $message = apply_filters('x402_error_message', $message, $error_data);
    echo '<div class="x402-error-text">' . wp_kses_post($message) . '</div>';
}

/**
 * Output retry button
 */
function x402_template_retry_button($error_data) {
    if (empty($error_data['post_id'])) {
        return;
    }
    
    echo '<div class="x402-error-actions">';
    echo '<a href="' . esc_url(get_permalink($error_data['post_id'])) . '" class="x402-button x402-button-primary">';
    echo esc_html__('Try Again', 'x402-solana-paywall');
    echo '</a>';
    echo '<a href="' . esc_url(home_url('/')) . '" class="x402-button x402-button-secondary">';
    echo esc_html__('Go Home', 'x402-solana-paywall');
    echo '</a>';
    echo '</div>';
}

/**
 * Output error support
 */
function x402_template_error_support($error_data) {
    $support_text = __('Need help? Please contact our support team.', 'x402-solana-paywall');
    $support_text = apply_filters('x402_error_support_text', $support_text, $error_data);
    
    echo '<div class="x402-error-help">';
    echo '<h3>' . esc_html__('Need Help?', 'x402-solana-paywall') . '</h3>';
    echo '<p>' . wp_kses_post($support_text) . '</p>';
    echo '</div>';
}
