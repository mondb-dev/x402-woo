<?php
/**
 * Plugin Name: X402 Solana Paywall
 * Plugin URI: https://github.com/mondb-dev/x402-wp
 * Description: Bank-level secure cryptocurrency paywall for WordPress content using Solana blockchain. Based on x402-solana protocol.
 * Version: 1.1.0
 * Author: X402 Network
 * Author URI: https://github.com/payAINetwork/x402-solana
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: x402-solana-paywall
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.1
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('X402_VERSION', '1.1.0');
define('X402_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('X402_PLUGIN_URL', plugin_dir_url(__FILE__));
define('X402_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class X402_Solana_Paywall {
    
    /**
     * Single instance of the class
     *
     * @var X402_Solana_Paywall
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return X402_Solana_Paywall
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $vendor_autoload = X402_PLUGIN_DIR . 'vendor/autoload.php';

        if (file_exists($vendor_autoload)) {
            require_once $vendor_autoload;
        }

        // Load new utility classes first
        require_once X402_PLUGIN_DIR . 'includes/class-x402-logger.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-crypto-validator.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-security-handler.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-installer.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-token-handler.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-ajax-handler.php';
        
        // Load template system
        require_once X402_PLUGIN_DIR . 'includes/class-x402-template-handler.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-template-functions.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-shortcodes.php';
        
        // Load existing classes
        require_once X402_PLUGIN_DIR . 'includes/class-x402-database.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-security.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-payment.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-content-protection.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-admin.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-api.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-woocommerce-gateway.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation
        register_activation_hook(X402_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(X402_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));

        // WooCommerce integration
        add_filter('woocommerce_payment_gateways', array($this, 'register_woocommerce_gateway'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Scheduled tasks
        add_action('x402_cleanup_expired_requirements', array($this, 'cleanup_expired_requirements'));
        add_action('x402_cleanup_old_logs', array($this, 'cleanup_old_logs'));
    }

    /**
     * Cleanup expired payment requirements (cron job).
     */
    public function cleanup_expired_requirements() {
        $deleted = X402_Installer::cleanup_expired_requirements();
        X402_Logger::debug('Cleaned up expired payment requirements', array('count' => $deleted));
    }

    /**
     * Cleanup old log files (cron job).
     */
    public function cleanup_old_logs() {
        X402_Logger::clear_old_logs(30);
    }

    /**
     * Register the x402 WooCommerce gateway when WooCommerce is active.
     *
     * @param array $gateways Existing gateways.
     * @return array
     */
    public function register_woocommerce_gateway($gateways) {
        if (class_exists('WC_Payment_Gateway') && class_exists('X402_WooCommerce_Gateway')) {
            $gateways[] = 'X402_WooCommerce_Gateway';
        }

        return $gateways;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        X402_Database::create_tables();
        
        // Install X402 tables
        X402_Installer::install();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cleanup tasks
        if (!wp_next_scheduled('x402_cleanup_expired_requirements')) {
            wp_schedule_event(time(), 'daily', 'x402_cleanup_expired_requirements');
        }
        
        if (!wp_next_scheduled('x402_cleanup_old_logs')) {
            wp_schedule_event(time(), 'weekly', 'x402_cleanup_old_logs');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        X402_Logger::info('X402 plugin activated');
    }    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled tasks
        wp_clear_scheduled_hook('x402_cleanup_expired_requirements');
        wp_clear_scheduled_hook('x402_cleanup_old_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        X402_Logger::info('X402 plugin deactivated');
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'x402_solana_network' => 'testnet',
            'x402_default_currency' => 'SOL',
            'x402_enable_logging' => true,
            'x402_session_timeout' => 3600,
            'x402_encryption_key' => X402_Security::generate_encryption_key(),
        );
        
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize security
        X402_Security::init();
        
        // Initialize content protection
        X402_Content_Protection::init();
        
        // Initialize admin interface
        if (is_admin()) {
            X402_Admin::init();
        }
        
        // Initialize API
        X402_API::init();
        
        // Load text domain
        load_plugin_textdomain('x402-solana-paywall', false, dirname(plugin_basename(X402_PLUGIN_FILE)) . '/languages');
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on single posts/pages
        if (!is_singular()) {
            return;
        }
        
        wp_enqueue_style(
            'x402-frontend',
            X402_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            X402_VERSION
        );
        
        wp_enqueue_script(
            'x402-frontend',
            X402_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            X402_VERSION,
            true
        );
        
        // Localize script with nonce and AJAX URL
        wp_localize_script(
            'x402-frontend',
            'x402_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'verify_nonce' => X402_Security_Handler::create_nonce(X402_Security_Handler::VERIFY_PAYMENT_ACTION),
                'status_nonce' => X402_Security_Handler::create_nonce(X402_Security_Handler::CHECK_STATUS_ACTION),
                'price_nonce' => wp_create_nonce('x402_get_price'),
                'post_id'  => get_the_ID(),
                'messages' => array(
                    'wallet_required'    => esc_html__( 'Please enter your wallet address.', 'x402-solana-paywall' ),
                    'signature_required' => esc_html__( 'Please enter the transaction signature.', 'x402-solana-paywall' ),
                    'verifying'          => esc_html__( 'Verifying…', 'x402-solana-paywall' ),
                    'verify'             => esc_html__( 'Verify Payment', 'x402-solana-paywall' ),
                    'generic_error'      => esc_html__( 'An error occurred. Please try again.', 'x402-solana-paywall' ),
                    'rate_limited'       => esc_html__( 'Too many requests. Please wait a moment and try again.', 'x402-solana-paywall' ),
                ),
            )
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only load on post edit pages and plugin settings page
        if (!in_array($hook, array('post.php', 'post-new.php', 'settings_page_x402-settings'))) {
            return;
        }
        
        wp_enqueue_style(
            'x402-admin',
            X402_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            X402_VERSION
        );
        
        wp_enqueue_script(
            'x402-admin',
            X402_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            X402_VERSION,
            true
        );
    }
}

// Initialize the plugin
function x402_solana_paywall() {
    return X402_Solana_Paywall::get_instance();
}

// Start the plugin
x402_solana_paywall();
