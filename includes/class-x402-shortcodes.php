<?php
/**
 * X402 Shortcodes - Easy content integration
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode handler class
 */
class X402_Shortcodes {

    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode('x402_paywall', array(__CLASS__, 'paywall_shortcode'));
        add_shortcode('x402_payment_button', array(__CLASS__, 'payment_button_shortcode'));
        add_shortcode('x402_payment_status', array(__CLASS__, 'payment_status_shortcode'));
        add_shortcode('x402_wallet_address', array(__CLASS__, 'wallet_address_shortcode'));
        add_shortcode('x402_payment_amount', array(__CLASS__, 'payment_amount_shortcode'));
        add_shortcode('x402_protected_content', array(__CLASS__, 'protected_content_shortcode'));
    }

    /**
     * Full paywall shortcode
     * 
     * Usage: [x402_paywall amount="10" currency="USD" wallet="YOUR_WALLET"]
     * 
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function paywall_shortcode($atts) {
        $atts = shortcode_atts(array(
            'amount' => '10',
            'currency' => 'USD',
            'wallet' => '',
            'network' => 'solana-mainnet',
            'description' => '',
            'token' => '',
            'title' => __('Payment Required', 'x402-solana-paywall'),
            'button_text' => __('Pay Now', 'x402-solana-paywall'),
        ), $atts, 'x402_paywall');

        $post_id = get_the_ID();
        
        // Check if user has access
        if (X402_Content_Protection::user_has_access($post_id)) {
            return '';
        }

        ob_start();
        
        $paywall_data = array(
            'amount' => $atts['amount'],
            'currency' => $atts['currency'],
            'wallet_address' => $atts['wallet'],
            'network' => $atts['network'],
            'description' => $atts['description'],
            'post_id' => $post_id,
        );

        if (!empty($atts['token'])) {
            $token_handler = new X402_Token_Handler();
            $paywall_data['token_info'] = $token_handler->get_token_info($atts['token']);
        }

        ?>
        <div class="x402-shortcode-paywall">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <?php if (!empty($atts['description'])): ?>
                <p class="x402-paywall-description"><?php echo wp_kses_post($atts['description']); ?></p>
            <?php endif; ?>

            <?php X402_Template_Handler::get_template_part('payment', 'form', $paywall_data); ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Payment button shortcode
     * 
     * Usage: [x402_payment_button text="Pay with Crypto" class="custom-class"]
     * 
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function payment_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => __('Pay Now', 'x402-solana-paywall'),
            'class' => '',
            'style' => 'primary',
        ), $atts, 'x402_payment_button');

        $post_id = get_the_ID();
        
        if (X402_Content_Protection::user_has_access($post_id)) {
            return '';
        }

        $classes = array('x402-button', 'x402-button-' . $atts['style'], 'x402-connect-wallet');
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }

        return sprintf(
            '<button type="button" class="%s" data-post-id="%d">%s</button>',
            esc_attr(implode(' ', $classes)),
            absint($post_id),
            esc_html($atts['text'])
        );
    }

    /**
     * Payment status shortcode
     * 
     * Usage: [x402_payment_status]
     * 
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function payment_status_shortcode($atts) {
        $post_id = get_the_ID();
        
        if (X402_Content_Protection::user_has_access($post_id)) {
            return '<div class="x402-status-badge x402-status-paid">' . 
                   esc_html__('✓ Paid', 'x402-solana-paywall') . 
                   '</div>';
        }

        return '<div class="x402-status-badge x402-status-unpaid">' . 
               esc_html__('Payment Required', 'x402-solana-paywall') . 
               '</div>';
    }

    /**
     * Wallet address display shortcode
     * 
     * Usage: [x402_wallet_address wallet="YOUR_WALLET" show_qr="true"]
     * 
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function wallet_address_shortcode($atts) {
        $atts = shortcode_atts(array(
            'wallet' => '',
            'show_qr' => 'false',
            'label' => __('Payment Address', 'x402-solana-paywall'),
        ), $atts, 'x402_wallet_address');

        if (empty($atts['wallet'])) {
            return '';
        }

        ob_start();
        ?>
        <div class="x402-wallet-display">
            <?php if (!empty($atts['label'])): ?>
                <p class="wallet-label"><?php echo esc_html($atts['label']); ?></p>
            <?php endif; ?>
            
            <div class="x402-wallet-address-wrapper">
                <code class="x402-wallet-address"><?php echo esc_html($atts['wallet']); ?></code>
                <button type="button" class="x402-copy-button" data-copy-text="<?php echo esc_attr($atts['wallet']); ?>">
                    <?php esc_html_e('Copy', 'x402-solana-paywall'); ?>
                </button>
            </div>

            <?php if ($atts['show_qr'] === 'true'): ?>
                <div class="x402-wallet-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($atts['wallet']); ?>" 
                         alt="<?php esc_attr_e('QR Code', 'x402-solana-paywall'); ?>">
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Payment amount display shortcode
     * 
     * Usage: [x402_payment_amount amount="10" currency="USD" token="SOL"]
     * 
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function payment_amount_shortcode($atts) {
        $atts = shortcode_atts(array(
            'amount' => '10',
            'currency' => 'USD',
            'token' => '',
            'show_conversion' => 'true',
        ), $atts, 'x402_payment_amount');

        ob_start();
        ?>
        <div class="x402-amount-display">
            <span class="amount-value"><?php echo esc_html($atts['amount'] . ' ' . $atts['currency']); ?></span>
            
            <?php if (!empty($atts['token']) && $atts['show_conversion'] === 'true'): ?>
                <span class="amount-conversion" 
                      data-amount="<?php echo esc_attr($atts['amount']); ?>" 
                      data-currency="<?php echo esc_attr($atts['currency']); ?>"
                      data-token="<?php echo esc_attr($atts['token']); ?>">
                    <?php esc_html_e('Calculating...', 'x402-solana-paywall'); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Protected content shortcode
     * 
     * Usage: [x402_protected_content]Content here[/x402_protected_content]
     * 
     * @param array  $atts    Shortcode attributes.
     * @param string $content Enclosed content.
     * @return string
     */
    public static function protected_content_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'message' => __('This content is protected. Payment required to access.', 'x402-solana-paywall'),
            'show_excerpt' => 'false',
        ), $atts, 'x402_protected_content');

        $post_id = get_the_ID();
        
        // Show content if user has access
        if (X402_Content_Protection::user_has_access($post_id)) {
            return do_shortcode($content);
        }

        // Show paywall message
        ob_start();
        ?>
        <div class="x402-protected-content">
            <?php if ($atts['show_excerpt'] === 'true' && !empty($content)): ?>
                <div class="x402-content-excerpt">
                    <?php 
                    $excerpt = wp_trim_words(strip_tags($content), 50, '...');
                    echo wp_kses_post($excerpt);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="x402-protected-message">
                <p><?php echo wp_kses_post($atts['message']); ?></p>
                <?php echo do_shortcode('[x402_payment_button]'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize shortcodes
add_action('init', array('X402_Shortcodes', 'init'));
