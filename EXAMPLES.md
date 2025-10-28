# Configuration Examples

## Basic Configuration

### Development/Testing Setup
```php
// Settings → X402 Paywall
Merchant Wallet Address: 5rJ8hPZfZvVb9yVqJ9wLPGMvXPQkxVHYcFJKrXXXXXXX
Solana Network: Devnet
Custom RPC Endpoint: (leave empty for default)
Default Currency: SOL
Session Timeout: 3600
Security Logging: ✓ Enabled
```

### Production Setup
```php
// Settings → X402 Paywall
Merchant Wallet Address: [Your real Solana wallet]
Solana Network: Mainnet Beta
Custom RPC Endpoint: https://your-project.solana-mainnet.quiknode.pro/xxxx/
Default Currency: SOL
Session Timeout: 86400 (24 hours)
Security Logging: ✓ Enabled
```

## Post Configuration Examples

### Premium Article
```
Post: "Advanced Trading Strategies"
✓ Enable paywall
Amount: 0.05 SOL
```

### Exclusive Tutorial Series
```
Post: "Complete Web3 Development Course - Part 1"
✓ Enable paywall
Amount: 0.1 SOL
```

### High-Value Content
```
Page: "2024 Market Analysis Report"
✓ Enable paywall
Amount: 0.5 SOL
```

### Micro-payment Content
```
Post: "Daily Crypto News Summary"
✓ Enable paywall
Amount: 0.001 SOL
```

## Advanced Configurations

### Custom Pricing by Category

Add to your theme's functions.php:
```php
// Adjust pricing based on post category
add_filter('x402_payment_amount', function($amount, $post_id) {
    if (has_category('premium', $post_id)) {
        return $amount * 2; // Double price for premium category
    }
    if (has_category('basic', $post_id)) {
        return $amount * 0.5; // Half price for basic category
    }
    return $amount;
}, 10, 2);
```

### Time-Based Pricing
```php
// Increase price for older content (aged content is more valuable)
add_filter('x402_payment_amount', function($amount, $post_id) {
    $post_date = get_the_date('U', $post_id);
    $days_old = (time() - $post_date) / DAY_IN_SECONDS;
    
    if ($days_old > 365) {
        return $amount * 1.5; // 50% premium for content over 1 year old
    }
    return $amount;
}, 10, 2);
```

### Author-Based Pricing
```php
// Different authors can have different base prices
add_filter('x402_payment_amount', function($amount, $post_id) {
    $author_id = get_post_field('post_author', $post_id);
    $author_multiplier = get_user_meta($author_id, 'x402_price_multiplier', true);
    
    if ($author_multiplier && $author_multiplier > 0) {
        return $amount * floatval($author_multiplier);
    }
    return $amount;
}, 10, 2);
```

### Custom Session Duration
```php
// Extend session for premium users
add_filter('x402_session_duration', function($duration, $post_id, $wallet_address) {
    // Check if wallet is premium (you'd maintain this list)
    $premium_wallets = get_option('x402_premium_wallets', array());
    
    if (in_array($wallet_address, $premium_wallets)) {
        return $duration * 7; // 7x longer session for premium wallets
    }
    return $duration;
}, 10, 3);
```

### Email Notification After Payment
```php
// Send email when payment is verified
add_action('x402_payment_verified', function($payment_id, $post_id) {
    $payment = X402_Database::get_payment_by_id($payment_id);
    $post_title = get_the_title($post_id);
    
    $to = get_option('admin_email');
    $subject = "New Payment Received - {$post_title}";
    $message = "A payment of {$payment->amount} SOL was received for: {$post_title}";
    
    wp_mail($to, $subject, $message);
}, 10, 2);
```

### Analytics Integration
```php
// Track payments in Google Analytics
add_action('x402_payment_verified', function($payment_id, $post_id) {
    $payment = X402_Database::get_payment_by_id($payment_id);
    
    // Add GA tracking code
    ?>
    <script>
        gtag('event', 'purchase', {
            'transaction_id': '<?php echo esc_js($payment->transaction_signature); ?>',
            'value': <?php echo floatval($payment->amount); ?>,
            'currency': 'SOL',
            'items': [{
                'item_id': '<?php echo esc_js($post_id); ?>',
                'item_name': '<?php echo esc_js(get_the_title($post_id)); ?>'
            }]
        });
    </script>
    <?php
}, 10, 2);
```

## wp-config.php Additions

### Enhanced Security (Production)
```php
// Force HTTPS
define('FORCE_SSL_ADMIN', true);

// Disable file editing
define('DISALLOW_FILE_EDIT', true);

// Production mode
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
```

### Development Mode
```php
// Enable debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
```

## Server Configuration

### Apache .htaccess
```apache
# Protect plugin files
<FilesMatch "\.(php|md)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# Allow plugin loading
<FilesMatch "^(x402-solana-paywall\.php)$">
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Allow from all
    </IfModule>
</FilesMatch>
```

### Nginx Configuration
```nginx
# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;

# Rate limiting
limit_req_zone $binary_remote_addr zone=x402_limit:10m rate=10r/m;

location ~ /wp-content/plugins/x402-solana-paywall/.*\.php$ {
    deny all;
}
```

## Environment Variables

### .env File (if using)
```bash
X402_MERCHANT_WALLET=YourSolanaWalletAddressHere
X402_NETWORK=mainnet-beta
X402_RPC_ENDPOINT=https://your-rpc-endpoint.com
X402_DEBUG=false
```

## Monitoring & Alerts

### Database Size Monitoring
```php
// Check database size weekly
add_action('weekly_maintenance', function() {
    global $wpdb;
    
    $payments_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}x402_payments");
    $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}x402_audit_log");
    
    if ($payments_count > 100000 || $logs_count > 100000) {
        // Send alert email
        wp_mail(
            get_option('admin_email'),
            'X402: Database size warning',
            "Large table sizes detected. Consider cleanup."
        );
    }
});
```

## Backup Configuration

### Daily Backup Script
```bash
#!/bin/bash
# Backup X402 payment data

DATE=$(date +%Y%m%d)
BACKUP_DIR="/backups/x402"

# Backup database tables
mysqldump -u username -p database_name \
    wp_x402_payments \
    wp_x402_audit_log \
    > "$BACKUP_DIR/x402_backup_$DATE.sql"

# Compress
gzip "$BACKUP_DIR/x402_backup_$DATE.sql"

# Keep only last 30 days
find "$BACKUP_DIR" -name "x402_backup_*.sql.gz" -mtime +30 -delete
```

## Testing Configurations

### Automated Test Script
```php
// Test basic functionality
function test_x402_functionality() {
    $post_id = wp_insert_post(array(
        'post_title' => 'Test Protected Post',
        'post_content' => 'Test content',
        'post_status' => 'publish'
    ));
    
    update_post_meta($post_id, '_x402_paywall_enabled', '1');
    update_post_meta($post_id, '_x402_payment_amount', '0.1');
    
    $is_protected = X402_Payment::is_post_protected($post_id);
    $amount = X402_Payment::get_post_payment_amount($post_id);
    
    echo "Protected: " . ($is_protected ? 'Yes' : 'No') . "\n";
    echo "Amount: " . $amount . " SOL\n";
    
    wp_delete_post($post_id, true);
}
```
