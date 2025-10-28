<?php
/**
 * Admin interface class for X402 Solana Paywall
 * Handles admin settings and post meta boxes
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * X402 Admin Class
 */
class X402_Admin {
    
    /**
     * Initialize admin interface
     */
    public static function init() {
        // Add settings page
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        
        // Register settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
        // Add meta boxes
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        
        // Save meta box data
        add_action('save_post', array(__CLASS__, 'save_meta_box_data'));
        
        // Add custom columns to posts list
        add_filter('manage_posts_columns', array(__CLASS__, 'add_custom_columns'));
        add_filter('manage_pages_columns', array(__CLASS__, 'add_custom_columns'));
        add_action('manage_posts_custom_column', array(__CLASS__, 'render_custom_column'), 10, 2);
        add_action('manage_pages_custom_column', array(__CLASS__, 'render_custom_column'), 10, 2);
    }
    
    /**
     * Add settings page to WordPress admin
     */
    public static function add_settings_page() {
        add_options_page(
            __('X402 Paywall Settings', 'x402-solana-paywall'),
            __('X402 Paywall', 'x402-solana-paywall'),
            'manage_options',
            'x402-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public static function register_settings() {
        // General settings
        register_setting('x402_settings', 'x402_merchant_wallet', array(
            'type' => 'string',
            'sanitize_callback' => array('X402_Security', 'sanitize_wallet_address'),
            'default' => ''
        ));
        
        register_setting('x402_settings', 'x402_solana_network', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'testnet'
        ));
        
        register_setting('x402_settings', 'x402_custom_rpc_endpoint', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('x402_settings', 'x402_default_currency', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'SOL'
        ));
        
        register_setting('x402_settings', 'x402_session_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3600
        ));
        
        register_setting('x402_settings', 'x402_enable_logging', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle cleanup actions
        if (isset($_POST['x402_cleanup_logs']) && check_admin_referer('x402_cleanup_action')) {
            X402_Security::cleanup_audit_logs();
            echo '<div class="notice notice-success"><p>' . esc_html__('Audit logs cleaned up successfully.', 'x402-solana-paywall') . '</p></div>';
        }
        
        if (isset($_POST['x402_cleanup_payments']) && check_admin_referer('x402_cleanup_action')) {
            X402_Database::cleanup_expired_payments();
            echo '<div class="notice notice-success"><p>' . esc_html__('Expired payments cleaned up successfully.', 'x402-solana-paywall') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('x402_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="x402_merchant_wallet">
                                <?php echo esc_html__('Merchant Wallet Address', 'x402-solana-paywall'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="x402_merchant_wallet" 
                                name="x402_merchant_wallet" 
                                value="<?php echo esc_attr(get_option('x402_merchant_wallet', '')); ?>" 
                                class="regular-text"
                            />
                            <p class="description">
                                <?php echo esc_html__('Your Solana wallet address where payments will be received.', 'x402-solana-paywall'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="x402_solana_network">
                                <?php echo esc_html__('Solana Network', 'x402-solana-paywall'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $current_network = get_option('x402_solana_network', 'testnet'); ?>
                            <select id="x402_solana_network" name="x402_solana_network">
                                <option value="mainnet-beta" <?php selected($current_network, 'mainnet-beta'); ?>>
                                    <?php echo esc_html__('Mainnet Beta', 'x402-solana-paywall'); ?>
                                </option>
                                <option value="testnet" <?php selected($current_network, 'testnet'); ?>>
                                    <?php echo esc_html__('Testnet', 'x402-solana-paywall'); ?>
                                </option>
                                <option value="devnet" <?php selected($current_network, 'devnet'); ?>>
                                    <?php echo esc_html__('Devnet', 'x402-solana-paywall'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Select the Solana network to use. Use Testnet or Devnet for testing.', 'x402-solana-paywall'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="x402_custom_rpc_endpoint">
                                <?php echo esc_html__('Custom RPC Endpoint', 'x402-solana-paywall'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="x402_custom_rpc_endpoint" 
                                name="x402_custom_rpc_endpoint" 
                                value="<?php echo esc_attr(get_option('x402_custom_rpc_endpoint', '')); ?>" 
                                class="regular-text"
                                placeholder="https://your-custom-rpc.com"
                            />
                            <p class="description">
                                <?php echo esc_html__('Optional: Use a custom Solana RPC endpoint (e.g., from QuickNode or Alchemy).', 'x402-solana-paywall'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="x402_default_currency">
                                <?php echo esc_html__('Default Currency', 'x402-solana-paywall'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="x402_default_currency" 
                                name="x402_default_currency" 
                                value="<?php echo esc_attr(get_option('x402_default_currency', 'SOL')); ?>" 
                                class="small-text"
                            />
                            <p class="description">
                                <?php echo esc_html__('Currency symbol to display (e.g., SOL, USDC).', 'x402-solana-paywall'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="x402_session_timeout">
                                <?php echo esc_html__('Session Timeout', 'x402-solana-paywall'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="number" 
                                id="x402_session_timeout" 
                                name="x402_session_timeout" 
                                value="<?php echo esc_attr(get_option('x402_session_timeout', 3600)); ?>" 
                                class="small-text"
                                min="60"
                                step="60"
                            />
                            <p class="description">
                                <?php echo esc_html__('How long (in seconds) a payment session remains valid. Default: 3600 (1 hour).', 'x402-solana-paywall'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Security Logging', 'x402-solana-paywall'); ?>
                        </th>
                        <td>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="x402_enable_logging" 
                                    value="1" 
                                    <?php checked(get_option('x402_enable_logging', true), true); ?>
                                />
                                <?php echo esc_html__('Enable audit logging for security events', 'x402-solana-paywall'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Recommended for bank-level security compliance. Logs are kept for 90 days.', 'x402-solana-paywall'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr />
            
            <h2><?php echo esc_html__('Maintenance', 'x402-solana-paywall'); ?></h2>
            
            <form method="post">
                <?php wp_nonce_field('x402_cleanup_action'); ?>
                <p>
                    <button type="submit" name="x402_cleanup_logs" class="button">
                        <?php echo esc_html__('Clean Up Old Audit Logs', 'x402-solana-paywall'); ?>
                    </button>
                    <span class="description">
                        <?php echo esc_html__('Remove audit logs older than 90 days.', 'x402-solana-paywall'); ?>
                    </span>
                </p>
                <p>
                    <button type="submit" name="x402_cleanup_payments" class="button">
                        <?php echo esc_html__('Clean Up Expired Payments', 'x402-solana-paywall'); ?>
                    </button>
                    <span class="description">
                        <?php echo esc_html__('Remove expired pending payment records.', 'x402-solana-paywall'); ?>
                    </span>
                </p>
            </form>
            
            <hr />
            
            <h2><?php echo esc_html__('Security Information', 'x402-solana-paywall'); ?></h2>
            <div class="x402-security-info">
                <p><strong><?php echo esc_html__('Encryption:', 'x402-solana-paywall'); ?></strong> AES-256-CBC</p>
                <p><strong><?php echo esc_html__('Nonce Verification:', 'x402-solana-paywall'); ?></strong> Enabled</p>
                <p><strong><?php echo esc_html__('Rate Limiting:', 'x402-solana-paywall'); ?></strong> 10 requests/minute</p>
                <p><strong><?php echo esc_html__('Session Security:', 'x402-solana-paywall'); ?></strong> HMAC-SHA256 tokens</p>
                <p><strong><?php echo esc_html__('Data Protection:', 'x402-solana-paywall'); ?></strong> Encrypted storage with HttpOnly cookies</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add meta boxes to posts and pages
     */
    public static function add_meta_boxes() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'x402_paywall_settings',
                __('X402 Paywall Settings', 'x402-solana-paywall'),
                array(__CLASS__, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render meta box
     *
     * @param WP_Post $post Post object
     */
    public static function render_meta_box($post) {
        wp_nonce_field('x402_meta_box', 'x402_meta_box_nonce');
        
        $enabled = get_post_meta($post->ID, '_x402_paywall_enabled', true);
        $amount = get_post_meta($post->ID, '_x402_payment_amount', true);
        $stats = X402_Database::get_post_payment_stats($post->ID);
        
        ?>
        <div class="x402-meta-box">
            <p>
                <label>
                    <input 
                        type="checkbox" 
                        name="x402_paywall_enabled" 
                        value="1" 
                        <?php checked($enabled, '1'); ?>
                    />
                    <?php echo esc_html__('Enable paywall for this content', 'x402-solana-paywall'); ?>
                </label>
            </p>
            
            <p>
                <label for="x402_payment_amount">
                    <?php echo esc_html__('Payment Amount:', 'x402-solana-paywall'); ?>
                </label>
                <input 
                    type="number" 
                    id="x402_payment_amount" 
                    name="x402_payment_amount" 
                    value="<?php echo esc_attr($amount); ?>" 
                    step="0.000000001" 
                    min="0" 
                    class="small-text"
                />
                <span><?php echo esc_html(get_option('x402_default_currency', 'SOL')); ?></span>
            </p>
            
            <?php if ($stats['total_payments'] > 0): ?>
            <hr />
            <div class="x402-stats">
                <h4><?php echo esc_html__('Payment Statistics', 'x402-solana-paywall'); ?></h4>
                <p><strong><?php echo esc_html__('Total Payments:', 'x402-solana-paywall'); ?></strong> <?php echo esc_html($stats['total_payments']); ?></p>
                <p><strong><?php echo esc_html__('Total Amount:', 'x402-solana-paywall'); ?></strong> <?php echo esc_html($stats['total_amount']); ?> <?php echo esc_html(get_option('x402_default_currency', 'SOL')); ?></p>
                <p><strong><?php echo esc_html__('Unique Wallets:', 'x402-solana-paywall'); ?></strong> <?php echo esc_html($stats['unique_wallets']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     *
     * @param int $post_id Post ID
     */
    public static function save_meta_box_data($post_id) {
        // Verify nonce
        if (!isset($_POST['x402_meta_box_nonce']) || !wp_verify_nonce($_POST['x402_meta_box_nonce'], 'x402_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save paywall enabled setting
        $enabled = isset($_POST['x402_paywall_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_x402_paywall_enabled', $enabled);
        
        // Save payment amount
        if (isset($_POST['x402_payment_amount'])) {
            $amount = floatval($_POST['x402_payment_amount']);
            update_post_meta($post_id, '_x402_payment_amount', $amount);
        }
    }
    
    /**
     * Add custom columns to posts list
     *
     * @param array $columns Columns array
     * @return array
     */
    public static function add_custom_columns($columns) {
        $columns['x402_paywall'] = __('Paywall', 'x402-solana-paywall');
        return $columns;
    }
    
    /**
     * Render custom column content
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public static function render_custom_column($column, $post_id) {
        if ($column === 'x402_paywall') {
            $enabled = get_post_meta($post_id, '_x402_paywall_enabled', true);
            $amount = get_post_meta($post_id, '_x402_payment_amount', true);
            
            if ($enabled === '1' && $amount > 0) {
                echo '🔒 ' . esc_html($amount) . ' ' . esc_html(get_option('x402_default_currency', 'SOL'));
            } else {
                echo '—';
            }
        }
    }
}
