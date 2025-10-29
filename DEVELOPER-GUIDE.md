# X402 WooCommerce Gateway - Developer Quick Reference

## 🚀 Quick Start

### Activation
The plugin automatically:
1. Creates database tables
2. Sets Base facilitator as default
3. Generates webhook secret
4. Schedules cleanup tasks

### Default Configuration
```php
Facilitator Mode: 'base'
Facilitator URL: 'https://facilitator.base.org'
Facilitator Timeout: 30 seconds
Rate Limits: Enabled
Logging: Enabled
```

---

## 📝 Common Tasks

### 1. Validate Crypto Address
```php
// Ethereum/EVM address
if (X402_Crypto_Validator::validate_eth_address($address)) {
    // Valid Ethereum address
}

// Solana address
if (X402_Crypto_Validator::validate_solana_address($address)) {
    // Valid Solana address
}

// Auto-detect network type
$network_type = X402_Crypto_Validator::get_network_type('base-mainnet');
// Returns: 'evm'
```

### 2. Validate Transaction Hash
```php
$tx_hash = '0x123...';
$network = 'ethereum-mainnet';

if (X402_Crypto_Validator::validate_tx_hash($tx_hash, $network)) {
    // Valid transaction hash
}
```

### 3. Log Events
```php
// Info level
X402_Logger::info('Payment received', array(
    'order_id' => 123,
    'amount' => '10.50'
));

// Error level
X402_Logger::error('Payment failed', array(
    'order_id' => 123,
    'error' => 'Invalid signature'
));

// Log transaction
X402_Logger::log_transaction($order_id, $tx_hash, $metadata);
```

### 4. Check Security Permissions
```php
// Check if user can manage payments
if (X402_Security_Handler::can_manage_payments()) {
    // User has admin permissions
}

// Check if user can view order
if (X402_Security_Handler::can_view_order($order)) {
    // User has access to this order
}
```

### 5. Rate Limiting
```php
// Check rate limit (10 attempts per 60 seconds)
if (!X402_Security_Handler::check_rate_limit('verify_payment_' . $order_id, 10, 60)) {
    // Rate limit exceeded
    wp_send_json_error(array('message' => 'Too many attempts'), 429);
}
```

### 6. Create Nonce for AJAX
```php
// In PHP (server-side)
$nonce = X402_Security_Handler::create_nonce(X402_Security_Handler::VERIFY_PAYMENT_ACTION);

// Verify nonce
if (X402_Security_Handler::verify_nonce(X402_Security_Handler::VERIFY_PAYMENT_ACTION, $nonce)) {
    // Valid nonce
}
```

### 7. Work with Transactions
```php
// Insert transaction
$tx_id = X402_Installer::insert_transaction(array(
    'order_id' => 123,
    'tx_hash' => '0x123...',
    'wallet_address' => '0xabc...',
    'amount' => '10.50',
    'asset' => '0xUSDC...',
    'network' => 'base-mainnet',
    'status' => 'pending',
));

// Get transaction by hash
$tx = X402_Installer::get_transaction('0x123...');

// Update status
X402_Installer::update_transaction_status('0x123...', 'completed');

// Get all transactions for order
$transactions = X402_Installer::get_order_transactions(123);
```

### 8. Handle Webhooks
```php
// Webhook handler automatically:
// 1. Verifies HMAC signature
// 2. Validates payload
// 3. Logs event
// 4. Processes based on event type

// Events handled:
// - payment.verified
// - payment.settled
// - payment.failed
```

---

## 🔒 Security Best Practices

### 1. Always Validate Input
```php
// Bad ❌
$order_id = $_POST['order_id'];

// Good ✅
$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
$order_id = X402_Security_Handler::validate_order_id($order_id);
```

### 2. Always Sanitize Crypto Data
```php
// Bad ❌
$address = $_POST['address'];

// Good ✅
$address = X402_Crypto_Validator::sanitize_address($_POST['address']);
if (!X402_Crypto_Validator::validate_eth_address($address)) {
    // Invalid address
}
```

### 3. Always Use Nonces for AJAX
```php
// In AJAX request (JavaScript)
data: {
    action: 'x402_verify_payment',
    _wpnonce: x402_ajax.verify_nonce,
    order_id: orderId
}

// In AJAX handler (PHP)
if (!X402_Security_Handler::verify_nonce(X402_Security_Handler::VERIFY_PAYMENT_ACTION)) {
    wp_send_json_error(array('message' => 'Invalid nonce'), 403);
}
```

### 4. Always Check Permissions
```php
// Before performing admin actions
if (!X402_Security_Handler::can_manage_payments()) {
    wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
}
```

### 5. Always Rate Limit
```php
// Before processing sensitive operations
if (!X402_Security_Handler::check_rate_limit('action_' . $id, 5, 60)) {
    wp_send_json_error(array('message' => 'Too many attempts'), 429);
}
```

---

## 📊 Database Queries

### Get Transaction Stats
```php
$stats = X402_Installer::get_stats();
/*
Returns:
array(
    'total_transactions' => 100,
    'pending' => 5,
    'completed' => 90,
    'failed' => 5,
    'total_amount' => '1250.50'
)
*/
```

### Custom Queries
```php
global $wpdb;
$table = $wpdb->prefix . 'x402_transactions';

// Get recent transactions
$recent = $wpdb->get_results(
    "SELECT * FROM $table 
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
     ORDER BY created_at DESC
     LIMIT 10"
);

// Get transactions by status
$pending = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table WHERE status = %s",
        'pending'
    )
);
```

---

## 🎨 Frontend Integration

### JavaScript (AJAX)
```javascript
jQuery(document).ready(function($) {
    // Verify payment
    $.ajax({
        url: x402_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'x402_verify_payment',
            _wpnonce: x402_ajax.verify_nonce,
            order_id: orderId,
            tx_hash: txHash,
            wallet_address: walletAddress
        },
        success: function(response) {
            if (response.success) {
                console.log('Transaction verified:', response.data);
            }
        },
        error: function(xhr) {
            console.error('Verification failed:', xhr.responseJSON);
        }
    });
    
    // Check payment status
    $.ajax({
        url: x402_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'x402_check_payment_status',
            _wpnonce: x402_ajax.status_nonce,
            order_id: orderId
        },
        success: function(response) {
            if (response.success) {
                console.log('Status:', response.data.status);
            }
        }
    });
});
```

---

## 🔌 Hooks & Filters

### Available Actions
```php
// After plugin initialization
do_action('x402_init');

// Before payment processing
do_action('x402_before_process_payment', $order_id);

// After payment success
do_action('x402_payment_complete', $order_id, $transaction_id);

// After transaction recorded
do_action('x402_transaction_recorded', $transaction_id, $data);
```

### Available Filters
```php
// Modify facilitator URL
$url = apply_filters('x402_facilitator_url', $url, $network);

// Modify rate limit
$limit = apply_filters('x402_rate_limit', 10, 'verify_payment');

// Modify log retention
$days = apply_filters('x402_log_retention_days', 30);

// Modify payment requirements
$requirements = apply_filters('x402_payment_requirements', $requirements, $order);
```

---

## 🐛 Debugging

### Enable Debug Logging
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WC_LOG_THRESHOLD', 'debug');
```

### View Logs
1. WooCommerce → Status → Logs
2. Select log file: `x402-payments-{date}.log`

### Check Database Tables
```sql
-- View transactions
SELECT * FROM wp_x402_transactions ORDER BY created_at DESC LIMIT 10;

-- Check pending payments
SELECT * FROM wp_x402_payment_requirements WHERE status = 'pending';

-- Transaction statistics
SELECT 
    status,
    COUNT(*) as count,
    SUM(CAST(amount AS DECIMAL(20,8))) as total
FROM wp_x402_transactions
GROUP BY status;
```

### Common Issues

**Issue**: "Invalid nonce"
- **Solution**: Refresh page to get new nonce

**Issue**: "Rate limit exceeded"
- **Solution**: Wait 60 seconds or adjust rate limit

**Issue**: "Invalid facilitator URL"
- **Solution**: Ensure HTTPS is used (except localhost)

**Issue**: "Transaction not found"
- **Solution**: Check transaction hash format and network

---

## 🧪 Testing

### Test Payment Flow
```php
// 1. Create test order
$order = wc_create_order();
$order->add_product($product, 1);
$order->calculate_totals();

// 2. Set payment method
$order->set_payment_method('x402');

// 3. Process payment
$gateway = new X402_WooCommerce_Gateway();
$result = $gateway->process_payment($order->get_id());

// 4. Verify result
if ($result['result'] === 'success') {
    // Payment successful
}
```

### Test Webhook
```bash
# Send test webhook
curl -X POST https://yoursite.com/wp-admin/admin-ajax.php?action=x402_webhook \
  -H "Content-Type: application/json" \
  -H "X-X402-Signature: your-hmac-signature" \
  -d '{
    "event": "payment.verified",
    "order_id": 123,
    "tx_hash": "0x123..."
  }'
```

---

## 📚 Additional Resources

- [WordPress Nonce Documentation](https://developer.wordpress.org/apis/security/nonces/)
- [WooCommerce Payment Gateway API](https://woocommerce.com/document/payment-gateway-api/)
- [X402 Protocol Documentation](https://github.com/payAINetwork/x402-solana)

---

**Last Updated**: October 29, 2025  
**Version**: 1.1.0
