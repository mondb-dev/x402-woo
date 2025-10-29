<?php
/**
 * X402 Template Handler - Theme-agnostic template system
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles template loading with theme override support.
 */
class X402_Template_Handler {

    /**
     * Template paths
     */
    const PLUGIN_TEMPLATE_PATH = 'templates/';
    const THEME_TEMPLATE_PATH = 'x402/';

    /**
     * Initialize template hooks
     */
    public static function init() {
        add_filter('template_include', array(__CLASS__, 'template_loader'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_template_styles'));
    }

    /**
     * Load custom templates for X402 pages
     *
     * @param string $template Template path.
     * @return string
     */
    public static function template_loader($template) {
        // Check if this is a payment page
        if (is_singular() && self::is_payment_required()) {
            $custom_template = self::locate_template('payment-required.php');
            if ($custom_template) {
                return $custom_template;
            }
        }

        // Check for payment success page
        if (self::is_payment_success_page()) {
            $custom_template = self::locate_template('payment-success.php');
            if ($custom_template) {
                return $custom_template;
            }
        }

        // Check for payment failed page
        if (self::is_payment_failed_page()) {
            $custom_template = self::locate_template('payment-failed.php');
            if ($custom_template) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Locate template file
     *
     * @param string $template_name Template filename.
     * @param array  $args          Template arguments.
     * @return string|false Template path or false.
     */
    public static function locate_template($template_name, $args = array()) {
        // Check theme directory first
        $theme_template = locate_template(array(
            self::THEME_TEMPLATE_PATH . $template_name,
            $template_name,
        ));

        if ($theme_template) {
            return $theme_template;
        }

        // Fallback to plugin template
        $plugin_template = X402_PLUGIN_DIR . self::PLUGIN_TEMPLATE_PATH . $template_name;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return false;
    }

    /**
     * Get template part
     *
     * @param string $slug Template slug.
     * @param string $name Optional. Template name.
     * @param array  $args Optional. Template arguments.
     */
    public static function get_template_part($slug, $name = '', $args = array()) {
        $templates = array();
        
        if ($name) {
            $templates[] = "{$slug}-{$name}.php";
        }
        $templates[] = "{$slug}.php";

        // Allow filtering of template parts
        $templates = apply_filters('x402_get_template_part', $templates, $slug, $name, $args);

        foreach ($templates as $template) {
            $located = self::locate_template($template);
            if ($located) {
                // Make args available as variables
                if (!empty($args) && is_array($args)) {
                    extract($args, EXTR_SKIP);
                }
                
                do_action('x402_before_template_part', $template, $slug, $name, $args);
                
                include $located;
                
                do_action('x402_after_template_part', $template, $slug, $name, $args);
                
                return;
            }
        }
    }

    /**
     * Include template with args
     *
     * @param string $template_name Template name.
     * @param array  $args          Arguments to pass to template.
     * @param string $template_path Optional. Template path.
     * @param string $default_path  Optional. Default path.
     */
    public static function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if (!empty($args) && is_array($args)) {
            extract($args, EXTR_SKIP);
        }

        $located = self::locate_template($template_name);

        if (!$located) {
            return;
        }

        // Allow themes to override
        $located = apply_filters('x402_get_template', $located, $template_name, $args);

        do_action('x402_before_template', $template_name, $located, $args);

        include $located;

        do_action('x402_after_template', $template_name, $located, $args);
    }

    /**
     * Check if current page requires payment
     *
     * @return bool
     */
    private static function is_payment_required() {
        if (!is_singular()) {
            return false;
        }

        global $post;
        
        if (!$post) {
            return false;
        }

        $paywall_enabled = get_post_meta($post->ID, '_x402_paywall_enabled', true);
        
        if (!$paywall_enabled) {
            return false;
        }

        // Check if user has already paid
        $has_access = X402_Content_Protection::user_has_access($post->ID);

        return !$has_access;
    }

    /**
     * Check if current page is payment success
     *
     * @return bool
     */
    private static function is_payment_success_page() {
        return isset($_GET['x402_payment']) && $_GET['x402_payment'] === 'success';
    }

    /**
     * Check if current page is payment failed
     *
     * @return bool
     */
    private static function is_payment_failed_page() {
        return isset($_GET['x402_payment']) && $_GET['x402_payment'] === 'failed';
    }

    /**
     * Enqueue template-specific styles
     */
    public static function enqueue_template_styles() {
        // Allow themes to dequeue if they want full control
        if (apply_filters('x402_load_default_styles', true)) {
            wp_enqueue_style(
                'x402-templates',
                X402_PLUGIN_URL . 'assets/css/templates.css',
                array(),
                X402_VERSION
            );
        }

        // Enqueue customizer styles if available
        $custom_css = get_option('x402_custom_css', '');
        if (!empty($custom_css)) {
            wp_add_inline_style('x402-templates', $custom_css);
        }
    }

    /**
     * Get paywall data for template
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function get_paywall_data($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $data = array(
            'enabled' => (bool) get_post_meta($post_id, '_x402_paywall_enabled', true),
            'amount' => get_post_meta($post_id, '_x402_payment_amount', true),
            'currency' => get_post_meta($post_id, '_x402_payment_currency', true) ?: 'USD',
            'wallet_address' => get_post_meta($post_id, '_x402_wallet_address', true),
            'network' => get_post_meta($post_id, '_x402_network', true) ?: 'solana-mainnet',
            'description' => get_post_meta($post_id, '_x402_payment_description', true),
            'post_id' => $post_id,
        );

        // Get gateway for token info
        if (class_exists('WC_Payment_Gateways')) {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (isset($gateways['x402'])) {
                $gateway = $gateways['x402'];
                $data['token_info'] = $gateway->get_active_token();
            }
        }

        return apply_filters('x402_paywall_data', $data, $post_id);
    }

    /**
     * Get payment form HTML
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public static function get_payment_form($post_id = null) {
        ob_start();
        self::get_template_part('payment', 'form', array('post_id' => $post_id));
        return ob_get_clean();
    }

    /**
     * Get success message HTML
     *
     * @param array $data Success data.
     * @return string
     */
    public static function get_success_message($data = array()) {
        ob_start();
        self::get_template_part('payment', 'success', $data);
        return ob_get_clean();
    }

    /**
     * Get error message HTML
     *
     * @param string $message Error message.
     * @return string
     */
    public static function get_error_message($message = '') {
        ob_start();
        self::get_template_part('payment', 'error', array('message' => $message));
        return ob_get_clean();
    }
}

// Initialize
add_action('init', array('X402_Template_Handler', 'init'));
