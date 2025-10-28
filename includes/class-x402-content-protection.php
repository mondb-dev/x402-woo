<?php
/**
 * Content protection class for X402 Solana Paywall
 * Handles content filtering and protection
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * X402 Content Protection Class
 */
class X402_Content_Protection {
    
    /**
     * Initialize content protection
     */
    public static function init() {
        // Filter content
        add_filter('the_content', array(__CLASS__, 'filter_content'), 999);
        
        // Filter excerpt
        add_filter('the_excerpt', array(__CLASS__, 'filter_excerpt'), 999);
        
        // Prevent REST API access to protected content
        add_filter('rest_prepare_post', array(__CLASS__, 'filter_rest_content'), 10, 2);
        add_filter('rest_prepare_page', array(__CLASS__, 'filter_rest_content'), 10, 2);
    }
    
    /**
     * Filter post content
     *
     * @param string $content Post content
     * @return string
     */
    public static function filter_content($content) {
        if (!is_singular() || is_admin()) {
            return $content;
        }
        
        $post_id = get_the_ID();
        
        // Check if post requires payment
        if (!X402_Payment::is_post_protected($post_id)) {
            return $content;
        }
        
        // Check if user has access
        if (X402_Payment::validate_access($post_id)) {
            return $content;
        }
        
        // Return paywall UI
        return self::render_paywall($post_id, $content);
    }
    
    /**
     * Filter excerpt
     *
     * @param string $excerpt Post excerpt
     * @return string
     */
    public static function filter_excerpt($excerpt) {
        $post_id = get_the_ID();
        
        if (X402_Payment::is_post_protected($post_id)) {
            $excerpt .= ' <span class="x402-locked-indicator">' . __('[Locked Content]', 'x402-solana-paywall') . '</span>';
        }
        
        return $excerpt;
    }
    
    /**
     * Filter REST API content
     *
     * @param WP_REST_Response $response Response object
     * @param WP_Post $post Post object
     * @return WP_REST_Response
     */
    public static function filter_rest_content($response, $post) {
        if (!X402_Payment::is_post_protected($post->ID)) {
            return $response;
        }
        
        if (!X402_Payment::validate_access($post->ID)) {
            $data = $response->get_data();
            $data['content']['rendered'] = self::get_paywall_message($post->ID);
            $data['content']['protected'] = true;
            $response->set_data($data);
        }
        
        return $response;
    }
    
    /**
     * Render paywall UI
     *
     * @param int $post_id Post ID
     * @param string $content Original content
     * @return string
     */
    private static function render_paywall($post_id, $content) {
        $amount = X402_Payment::get_post_payment_amount($post_id);
        $currency = get_option('x402_default_currency', 'SOL');
        $excerpt = self::get_content_preview($content);
        
        ob_start();
        ?>
        <div class="x402-paywall-container" data-post-id="<?php echo esc_attr($post_id); ?>">
            <div class="x402-preview-content">
                <?php echo wp_kses_post($excerpt); ?>
            </div>
            
            <div class="x402-paywall-notice">
                <div class="x402-lock-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                    </svg>
                </div>
                
                <h3 class="x402-paywall-title">
                    <?php echo esc_html__('Premium Content', 'x402-solana-paywall'); ?>
                </h3>
                
                <p class="x402-paywall-description">
                    <?php 
                    printf(
                        esc_html__('This content requires a payment of %s %s to unlock.', 'x402-solana-paywall'),
                        '<strong>' . esc_html($amount) . '</strong>',
                        '<strong>' . esc_html($currency) . '</strong>'
                    ); 
                    ?>
                </p>
                
                <div class="x402-payment-section">
                    <div class="x402-payment-form">
                        <label for="x402-wallet-address">
                            <?php echo esc_html__('Your Solana Wallet Address:', 'x402-solana-paywall'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="x402-wallet-address" 
                            class="x402-wallet-input" 
                            placeholder="<?php echo esc_attr__('Enter your wallet address', 'x402-solana-paywall'); ?>"
                        />
                        
                        <label for="x402-transaction-signature">
                            <?php echo esc_html__('Transaction Signature:', 'x402-solana-paywall'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="x402-transaction-signature" 
                            class="x402-signature-input" 
                            placeholder="<?php echo esc_attr__('Paste transaction signature after payment', 'x402-solana-paywall'); ?>"
                        />
                        
                        <div class="x402-payment-instructions">
                            <h4><?php echo esc_html__('How to Pay:', 'x402-solana-paywall'); ?></h4>
                            <ol>
                                <li><?php echo esc_html__('Send exactly the required amount of SOL to the merchant wallet', 'x402-solana-paywall'); ?></li>
                                <li><?php echo esc_html__('Copy the transaction signature from your wallet', 'x402-solana-paywall'); ?></li>
                                <li><?php echo esc_html__('Paste it above and click "Verify Payment"', 'x402-solana-paywall'); ?></li>
                            </ol>
                            
                            <div class="x402-merchant-info">
                                <strong><?php echo esc_html__('Merchant Wallet:', 'x402-solana-paywall'); ?></strong>
                                <code class="x402-merchant-wallet"><?php echo esc_html(get_option('x402_merchant_wallet', 'Not configured')); ?></code>
                            </div>
                        </div>
                        
                        <button type="button" class="x402-verify-button" id="x402-verify-payment">
                            <?php echo esc_html__('Verify Payment', 'x402-solana-paywall'); ?>
                        </button>
                        
                        <div class="x402-status-message" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get content preview
     *
     * @param string $content Full content
     * @return string
     */
    private static function get_content_preview($content) {
        // Strip shortcodes and tags
        $preview = wp_strip_all_tags(strip_shortcodes($content));
        
        // Get first 200 characters
        $preview = substr($preview, 0, 200);
        
        // Find last space to avoid cutting words
        $last_space = strrpos($preview, ' ');
        if ($last_space !== false) {
            $preview = substr($preview, 0, $last_space);
        }
        
        return '<p>' . esc_html($preview) . '...</p>';
    }
    
    /**
     * Get paywall message for REST API
     *
     * @param int $post_id Post ID
     * @return string
     */
    private static function get_paywall_message($post_id) {
        $amount = X402_Payment::get_post_payment_amount($post_id);
        $currency = get_option('x402_default_currency', 'SOL');
        
        return sprintf(
            '<p>%s</p>',
            sprintf(
                __('This content requires a payment of %s %s to access.', 'x402-solana-paywall'),
                $amount,
                $currency
            )
        );
    }
}
