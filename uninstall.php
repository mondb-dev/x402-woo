<?php
/**
 * Uninstall script for X402 Solana Paywall
 * Removes all plugin data from database
 *
 * @package X402_Solana_Paywall
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load database class
require_once plugin_dir_path(__FILE__) . 'includes/class-x402-database.php';

// Delete all plugin options
$options = array(
    'x402_solana_network',
    'x402_merchant_wallet',
    'x402_custom_rpc_endpoint',
    'x402_default_currency',
    'x402_enable_logging',
    'x402_session_timeout',
    'x402_encryption_key',
    'x402_db_version',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete all post meta
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_x402_%'");

// Drop database tables
X402_Database::drop_tables();

// Clear any cached data
wp_cache_flush();
