<?php
/**
 * Plugin Name: X402 Solana Paywall
 * Plugin URI: https://github.com/mondb-dev/x402-wp
 * Description: Bank-level secure cryptocurrency paywall for WordPress content using Solana blockchain. Based on x402-solana protocol.
 * Version: 1.0.0
 * Author: X402 Network
 * Author URI: https://github.com/payAINetwork/x402-solana
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: x402-solana-paywall
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('X402_VERSION', '1.0.0');
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
        require_once X402_PLUGIN_DIR . 'includes/class-x402-database.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-security.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-payment.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-content-protection.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-admin.php';
        require_once X402_PLUGIN_DIR . 'includes/class-x402-api.php';
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
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        X402_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'x402_solana_network' => 'mainnet-beta',
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
        wp_localize_script('x402-frontend', 'x402_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('x402_payment_nonce'),
            'post_id' => get_the_ID(),
        ));
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
