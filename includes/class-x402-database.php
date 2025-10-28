<?php
/**
 * Database class for X402 Solana Paywall
 * Handles all database operations with encrypted data storage
 *
 * @package X402_Solana_Paywall
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * X402 Database Class
 */
class X402_Database {
    
    /**
     * Create plugin database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Payments table
        $payments_table = $wpdb->prefix . 'x402_payments';
        $payments_sql = "CREATE TABLE IF NOT EXISTS {$payments_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            wallet_address varchar(255) NOT NULL,
            transaction_signature varchar(255) NOT NULL,
            amount decimal(20,9) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'SOL',
            status varchar(20) NOT NULL DEFAULT 'pending',
            encrypted_data text,
            session_token varchar(255),
            expires_at datetime,
            verified_at datetime,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY wallet_address (wallet_address),
            KEY transaction_signature (transaction_signature),
            KEY status (status),
            KEY session_token (session_token),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        
        // Audit log table
        $audit_table = $wpdb->prefix . 'x402_audit_log';
        $audit_sql = "CREATE TABLE IF NOT EXISTS {$audit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data text,
            user_ip varchar(45) NOT NULL,
            user_agent varchar(255),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($payments_sql);
        dbDelta($audit_sql);
        
        // Update database version
        update_option('x402_db_version', X402_VERSION);
    }
    
    /**
     * Record a payment
     *
     * @param array $payment_data Payment data
     * @return int|false Payment ID or false on failure
     */
    public static function record_payment($payment_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        $defaults = array(
            'post_id' => 0,
            'wallet_address' => '',
            'transaction_signature' => '',
            'amount' => 0,
            'currency' => 'SOL',
            'status' => 'pending',
            'encrypted_data' => '',
            'session_token' => '',
            'expires_at' => null,
            'verified_at' => null,
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        );
        
        $payment_data = wp_parse_args($payment_data, $defaults);

        // Encrypt sensitive data
        if (!empty($payment_data['encrypted_data'])) {
            $encrypted = X402_Security::encrypt($payment_data['encrypted_data']);
            $payment_data['encrypted_data'] = $encrypted ? $encrypted : '';
        }

        $data_to_insert = array(
            'post_id' => $payment_data['post_id'],
            'wallet_address' => $payment_data['wallet_address'],
            'transaction_signature' => $payment_data['transaction_signature'],
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'status' => $payment_data['status'],
            'encrypted_data' => $payment_data['encrypted_data'],
            'session_token' => $payment_data['session_token'],
            'expires_at' => $payment_data['expires_at'],
            'verified_at' => $payment_data['verified_at'],
            'created_at' => $payment_data['created_at'],
            'updated_at' => $payment_data['updated_at'],
        );

        $result = $wpdb->insert(
            $table_name,
            $data_to_insert,
            array('%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update payment status
     *
     * @param int $payment_id Payment ID
     * @param string $status New status
     * @param array $additional_data Additional data to update
     * @return bool
     */
    public static function update_payment_status($payment_id, $status, $additional_data = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        $update_data = array(
            'status' => sanitize_text_field($status),
            'updated_at' => current_time('mysql', true)
        );

        if ($status === 'verified') {
            $update_data['verified_at'] = current_time('mysql', true);
        }
        
        // Merge additional data
        $update_data = array_merge($update_data, $additional_data);
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => absint($payment_id)),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get payment by transaction signature
     *
     * @param string $signature Transaction signature
     * @return object|null
     */
    public static function get_payment_by_signature($signature) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE transaction_signature = %s",
                $signature
            )
        );
    }
    
    /**
     * Get payment by session token
     *
     * @param string $token Session token
     * @return object|null
     */
    public static function get_payment_by_token($token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE session_token = %s 
                AND status = 'verified' 
                AND expires_at > NOW()",
                $token
            )
        );
    }
    
    /**
     * Check if wallet has paid for post
     *
     * @param int $post_id Post ID
     * @param string $wallet_address Wallet address
     * @return bool
     */
    public static function has_wallet_paid($post_id, $wallet_address) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE post_id = %d 
                AND wallet_address = %s 
                AND status = 'verified' 
                AND expires_at > NOW()",
                $post_id,
                $wallet_address
            )
        );
        
        return $count > 0;
    }
    
    /**
     * Get payment statistics for a post
     *
     * @param int $post_id Post ID
     * @return array
     */
    public static function get_post_payment_stats($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_payments,
                    SUM(amount) as total_amount,
                    COUNT(DISTINCT wallet_address) as unique_wallets
                FROM {$table_name} 
                WHERE post_id = %d 
                AND status = 'verified'",
                $post_id
            ),
            ARRAY_A
        );
        
        return $stats ? $stats : array(
            'total_payments' => 0,
            'total_amount' => 0,
            'unique_wallets' => 0
        );
    }
    
    /**
     * Clean up expired payments
     */
    public static function cleanup_expired_payments() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payments';
        
        $wpdb->query(
            "DELETE FROM {$table_name} 
            WHERE expires_at < NOW() 
            AND status = 'pending'"
        );
    }
    
    /**
     * Drop plugin tables (used during uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'x402_payments',
            $wpdb->prefix . 'x402_audit_log'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option('x402_db_version');
    }
}
