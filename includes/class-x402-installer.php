<?php
/**
 * Database installer for X402 payment gateway.
 *
 * @package X402_Solana_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database table creation and updates for X402.
 */
class X402_Installer {

    /**
     * Plugin version option name.
     */
    const VERSION_OPTION = 'x402_db_version';

    /**
     * Current database version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Install database tables and default options.
     */
    public static function install() {
        global $wpdb;

        $installed_version = get_option(self::VERSION_OPTION);
        
        // Only run if not installed or version changed
        if ($installed_version !== self::DB_VERSION) {
            self::create_tables();
            self::set_default_options();
            update_option(self::VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'x402_transactions';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            tx_hash varchar(100) NOT NULL,
            wallet_address varchar(100) NOT NULL,
            amount varchar(50) NOT NULL,
            asset varchar(100) NOT NULL,
            network varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            facilitator_url varchar(255) DEFAULT NULL,
            verification_method varchar(50) DEFAULT NULL,
            settled tinyint(1) DEFAULT 0,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tx_hash (tx_hash),
            KEY order_id (order_id),
            KEY status (status),
            KEY wallet_address (wallet_address),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Create payment requirements table for tracking pending payments
        $requirements_table = $wpdb->prefix . 'x402_payment_requirements';

        $requirements_sql = "CREATE TABLE IF NOT EXISTS $requirements_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            pay_to varchar(100) NOT NULL,
            amount varchar(50) NOT NULL,
            asset varchar(100) NOT NULL,
            network varchar(50) NOT NULL,
            timeout int(11) NOT NULL,
            expires_at datetime NOT NULL,
            requirements_data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        dbDelta($requirements_sql);
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        // Set default facilitator URL if not exists
        if (!get_option('wc_x402_facilitator_url')) {
            add_option('wc_x402_facilitator_url', 'https://facilitator.base.org');
        }

        // Set default facilitator mode
        if (!get_option('wc_x402_facilitator_mode')) {
            add_option('wc_x402_facilitator_mode', 'base');
        }

        // Set default facilitator timeout
        if (!get_option('wc_x402_facilitator_timeout')) {
            add_option('wc_x402_facilitator_timeout', 30);
        }

        // Generate webhook secret
        if (!get_option('x402_webhook_secret')) {
            X402_Security_Handler::generate_webhook_secret();
        }

        // Set plugin version
        if (!get_option('x402_version')) {
            add_option('x402_version', X402_VERSION);
        }
    }

    /**
     * Uninstall - remove tables and options.
     */
    public static function uninstall() {
        global $wpdb;

        // Only remove if explicitly requested
        if (!defined('X402_REMOVE_ALL_DATA') || !X402_REMOVE_ALL_DATA) {
            return;
        }

        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}x402_transactions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}x402_payment_requirements");

        // Remove options
        delete_option(self::VERSION_OPTION);
        delete_option('wc_x402_facilitator_url');
        delete_option('wc_x402_facilitator_mode');
        delete_option('wc_x402_facilitator_timeout');
        delete_option('x402_webhook_secret');
        delete_option('x402_version');

        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_x402_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_x402_%'");
    }

    /**
     * Get transaction record.
     *
     * @param string $tx_hash Transaction hash.
     * @return object|null
     */
    public static function get_transaction($tx_hash) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_transactions';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE tx_hash = %s",
                $tx_hash
            )
        );
    }

    /**
     * Insert transaction record.
     *
     * @param array $data Transaction data.
     * @return int|false Insert ID or false on failure.
     */
    public static function insert_transaction($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_transactions';
        
        $defaults = array(
            'status' => 'pending',
            'settled' => 0,
            'created_at' => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        // Serialize metadata if array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        $result = $wpdb->insert($table, $data);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update transaction status.
     *
     * @param string $tx_hash Transaction hash.
     * @param string $status  New status.
     * @return bool
     */
    public static function update_transaction_status($tx_hash, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_transactions';
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ),
            array('tx_hash' => $tx_hash),
            array('%s', '%s'),
            array('%s')
        );

        return false !== $result;
    }

    /**
     * Get transactions for order.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public static function get_order_transactions($order_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_transactions';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE order_id = %d ORDER BY created_at DESC",
                $order_id
            )
        );
    }

    /**
     * Save payment requirements.
     *
     * @param int   $order_id     Order ID.
     * @param array $requirements Requirements data.
     * @return int|false
     */
    public static function save_payment_requirements($order_id, $requirements) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_payment_requirements';
        
        $data = array(
            'order_id' => $order_id,
            'pay_to' => $requirements['payTo'] ?? '',
            'amount' => $requirements['amount'] ?? '',
            'asset' => $requirements['asset'] ?? '',
            'network' => $requirements['network'] ?? '',
            'timeout' => $requirements['timeout'] ?? 600,
            'expires_at' => date('Y-m-d H:i:s', time() + ($requirements['timeout'] ?? 600)),
            'requirements_data' => wp_json_encode($requirements),
            'status' => 'pending',
        );

        $result = $wpdb->replace($table, $data);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get payment requirements for order.
     *
     * @param int $order_id Order ID.
     * @return object|null
     */
    public static function get_payment_requirements($order_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_payment_requirements';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE order_id = %d",
                $order_id
            )
        );
    }

    /**
     * Clean up expired payment requirements.
     *
     * @return int Number of deleted records.
     */
    public static function cleanup_expired_requirements() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_payment_requirements';
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE expires_at < %s AND status = 'pending'",
                current_time('mysql')
            )
        );
    }

    /**
     * Get statistics.
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_transactions';
        
        return array(
            'total_transactions' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'"),
            'total_amount' => $wpdb->get_var("SELECT SUM(CAST(amount AS DECIMAL(20,8))) FROM $table WHERE status = 'completed'"),
        );
    }
}
