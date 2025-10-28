# X402 Solana Paywall - Usage Guide

## Quick Start Guide

### Step 1: Installation

1. Upload the plugin to `/wp-content/plugins/x402-solana-paywall/`
2. Activate via WordPress admin panel
3. Go to Settings > X402 Paywall

### Step 2: Configuration

```
Settings > X402 Paywall
├── Merchant Wallet Address: [Your Solana wallet]
├── Solana Network: Mainnet Beta (for production)
├── Custom RPC Endpoint: (optional)
├── Default Currency: SOL
├── Session Timeout: 3600 seconds
└── Security Logging: ✓ Enabled
```

### Step 3: Protect Content

1. Edit any post or page
2. Find "X402 Paywall Settings" meta box
3. ✓ Enable paywall for this content
4. Set payment amount: 0.1 SOL
5. Publish

## User Flow Example

### Reader Experience

1. **Discovers Protected Content**
   - Sees preview (first 200 characters)
   - Lock icon and payment notice

2. **Initiates Payment**
   - Opens Solana wallet (Phantom, Solflare, etc.)
   - Sends exact amount to merchant wallet
   - Copies transaction signature

3. **Verifies Payment**
   - Enters wallet address
   - Pastes transaction signature
   - Clicks "Verify Payment"

4. **Accesses Content**
   - Page reloads with full content
   - Access valid for 24 hours
   - Can return anytime within period

## Code Examples

### Check if Content is Protected

```php
$post_id = 123;
if (X402_Payment::is_post_protected($post_id)) {
    echo "This content requires payment";
}
```

### Get Payment Amount

```php
$amount = X402_Payment::get_post_payment_amount($post_id);
echo "Price: {$amount} SOL";
```

### Check User Access

```php
if (X402_Payment::validate_access($post_id)) {
    // User has paid, show content
} else {
    // Show paywall
}
```

### Get Payment Statistics

```php
$stats = X402_Database::get_post_payment_stats($post_id);
echo "Total Payments: " . $stats['total_payments'];
echo "Total Amount: " . $stats['total_amount'] . " SOL";
echo "Unique Wallets: " . $stats['unique_wallets'];
```

## Hooks and Filters

### Custom Paywall Content

```php
add_filter('x402_paywall_content', function($content, $post_id) {
    // Customize paywall UI
    return $content . '<p>Custom message here</p>';
}, 10, 2);
```

### Modify Payment Amount

```php
add_filter('x402_payment_amount', function($amount, $post_id) {
    // Dynamic pricing based on post type or category
    if (has_category('premium', $post_id)) {
        return $amount * 2;
    }
    return $amount;
}, 10, 2);
```

### After Payment Verified

```php
add_action('x402_payment_verified', function($payment_id, $post_id) {
    // Send notification, update analytics, etc.
    error_log("Payment verified for post {$post_id}");
}, 10, 2);
```

## Database Queries

### Get Recent Payments

```php
global $wpdb;
$table = $wpdb->prefix . 'x402_payments';

$payments = $wpdb->get_results(
    "SELECT * FROM {$table} 
    WHERE status = 'verified' 
    ORDER BY created_at DESC 
    LIMIT 10"
);
```

### Get Revenue by Post

```php
$revenue = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT SUM(amount) FROM {$table} 
        WHERE post_id = %d AND status = 'verified'",
        $post_id
    )
);
```

## Security Features

### Encrypted Data Storage

All sensitive payment data is encrypted using AES-256-CBC:

```php
// Encrypt
$encrypted = X402_Security::encrypt($sensitive_data);

// Decrypt
$decrypted = X402_Security::decrypt($encrypted);
```

### Session Token Generation

```php
$token = X402_Security::generate_session_token($post_id, $wallet_address);
// Returns HMAC-SHA256 hash of post_id + wallet + timestamp + random
```

### Rate Limiting

Automatic rate limiting (10 requests/minute per IP):
- Applied to all payment verification endpoints
- Returns 429 status when exceeded
- 60-second cooldown period

### Audit Logging

```php
X402_Security::log_security_event('custom_event', array(
    'user_action' => 'something',
    'result' => 'success'
));
```

## REST API Protection

Protected content is automatically filtered in WordPress REST API:

```json
GET /wp-json/wp/v2/posts/123
{
  "content": {
    "rendered": "This content requires payment...",
    "protected": true
  }
}
```

## Testing

### Test on Devnet

1. Set network to "Devnet" in settings
2. Use devnet SOL (free from faucet)
3. Test merchant wallet on devnet
4. Verify transactions complete

### Test Payment Flow

```php
// Simulate payment verification (for testing only)
$result = X402_Payment::verify_transaction(
    'test_signature_here',
    $post_id,
    'test_wallet_address'
);

if ($result['success']) {
    echo "Payment verified!";
}
```

## Troubleshooting

### Enable Debug Mode

Add to wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at: `/wp-content/debug.log`

### Common Issues

**"Security check failed"**
- Cookie/JavaScript disabled
- Nonce expired (page loaded >24h ago)
- Solution: Refresh page

**"Rate limit exceeded"**
- Too many verification attempts
- Solution: Wait 60 seconds

**"Transaction not found"**
- Transaction not yet confirmed
- Wrong network (mainnet vs testnet)
- Solution: Wait for confirmation, check network

## Performance Optimization

### Database Indexes

The plugin creates indexes on:
- `post_id` - Fast lookup by content
- `wallet_address` - Check user payments
- `transaction_signature` - Verify uniqueness
- `session_token` - Validate access
- `expires_at` - Cleanup queries

### Caching

Payment verification results are not cached for security.
Content protection decisions respect WordPress caching plugins.

### Cleanup Cron Jobs

Add to theme functions.php or separate plugin:

```php
// Daily cleanup of expired payments
add_action('daily_cleanup', function() {
    X402_Database::cleanup_expired_payments();
    X402_Security::cleanup_audit_logs();
});

if (!wp_next_scheduled('daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'daily_cleanup');
}
```

## Production Deployment

### Pre-Launch Checklist

- [ ] Test on testnet/devnet first
- [ ] Configure merchant wallet address
- [ ] Enable HTTPS on site
- [ ] Set up custom RPC endpoint
- [ ] Test payment flow end-to-end
- [ ] Enable audit logging
- [ ] Set appropriate session timeout
- [ ] Backup database before launch
- [ ] Monitor first transactions closely

### Recommended RPC Providers

For production, use dedicated RPC endpoints:
- **QuickNode**: https://www.quicknode.com/
- **Alchemy**: https://www.alchemy.com/
- **Helius**: https://www.helius.dev/

Benefits:
- Higher rate limits
- Better reliability
- Advanced features
- Analytics dashboard

## Support and Resources

- Plugin Repository: https://github.com/mondb-dev/x402-wp
- X402 Protocol: https://github.com/payAINetwork/x402-solana
- Solana Docs: https://docs.solana.com/
- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/

## License

GPL v2 or later - Free to use and modify
