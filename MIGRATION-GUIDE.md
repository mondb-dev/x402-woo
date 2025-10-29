# X402 WooCommerce Gateway - Migration Guide

## 📋 Overview

This guide helps you migrate from the previous version to the enhanced security-compliant version with Base facilitator support.

---

## 🔄 Migration Steps

### Step 1: Backup Your Data
```bash
# Backup WordPress database
wp db export backup-$(date +%Y%m%d).sql

# Backup plugin files
tar -czf x402-backup-$(date +%Y%m%d).tar.gz wp-content/plugins/x402-solana-paywall/
```

### Step 2: Update Plugin Files
```bash
# Navigate to plugins directory
cd wp-content/plugins/x402-solana-paywall/

# Pull latest changes
git pull origin main

# Or upload new files via FTP/SFTP
```

### Step 3: Deactivate Plugin
1. Go to WordPress Admin → Plugins
2. Deactivate "X402 Solana Paywall"
3. Wait for deactivation to complete

### Step 4: Reactivate Plugin
1. Click "Activate" on "X402 Solana Paywall"
2. New database tables will be created automatically
3. Default settings will be configured

### Step 5: Verify Settings
1. Go to WooCommerce → Settings → Payments → X402
2. Check facilitator settings:
   - **Facilitator Mode**: Should default to "Base Facilitator"
   - **Facilitator Endpoint**: Should show `https://facilitator.base.org`
   - **Facilitator Timeout**: Should be 30 seconds
3. Verify all other settings are intact

### Step 6: Test Payment Flow
1. Create a test order
2. Select X402 as payment method
3. Complete a test transaction
4. Verify transaction appears in database

---

## 🆕 What's New

### New Database Tables
Two new tables are automatically created:
- `wp_x402_transactions` - Transaction records
- `wp_x402_payment_requirements` - Pending payment tracking

### New Settings
- **Facilitator Mode** - Select your facilitator (Base is default)
- **Facilitator Endpoint** - Configure facilitator URL
- **Facilitator Timeout** - Set request timeout (5-120 seconds)

### New Security Features
- Nonce verification on all AJAX requests
- Rate limiting on payment operations
- Webhook signature verification
- Enhanced input validation

### New Logging
- Comprehensive payment logging
- Security event tracking
- Facilitator request/response logging
- Automatic log cleanup

---

## 🔍 Verifying Migration

### Check Database Tables
```sql
-- Verify tables exist
SHOW TABLES LIKE 'wp_x402_%';

-- Expected output:
-- wp_x402_transactions
-- wp_x402_payment_requirements

-- Check table structure
DESCRIBE wp_x402_transactions;
```

### Check Default Options
```php
// Via WP-CLI
wp option get wc_x402_facilitator_url
wp option get wc_x402_facilitator_mode
wp option get x402_webhook_secret

// Via PHP
echo get_option('wc_x402_facilitator_url');
// Should output: https://facilitator.base.org

echo get_option('wc_x402_facilitator_mode');
// Should output: base
```

### Check Scheduled Tasks
```php
// Via WP-CLI
wp cron event list

// Look for:
// x402_cleanup_expired_requirements (daily)
// x402_cleanup_old_logs (weekly)
```

### Check Logs
1. Go to WooCommerce → Status → Logs
2. Look for log file: `x402-payments-{date}.log`
3. Should see activation message

---

## 🔧 Configuration Changes

### Old Configuration
```php
// Old settings (still supported)
'facilitator_mode' => 'none'
'facilitator_endpoint' => ''
```

### New Configuration (Recommended)
```php
// New default settings
'facilitator_mode' => 'base'
'facilitator_endpoint' => 'https://facilitator.base.org'
'facilitator_timeout' => 30
```

### Migration of Facilitator Settings

**If you had "No Facilitator":**
- Mode changes to: `base` (recommended)
- Endpoint set to: `https://facilitator.base.org`
- You can change back to `none` if needed

**If you had "Coinbase Facilitator":**
- Mode remains: `coinbase`
- Your API key is preserved
- Endpoint not changed

**If you had "Custom Facilitator":**
- Mode remains: `custom`
- Your custom endpoint is preserved
- Timeout now configurable (default: 30s)

---

## 🔐 Security Enhancements

### Webhook Secret
A new webhook secret is automatically generated on activation.

**To view/regenerate:**
```php
// Get current secret
$secret = X402_Security_Handler::get_webhook_secret();

// Regenerate (use with caution)
$new_secret = X402_Security_Handler::generate_webhook_secret();
```

**Update facilitator with new secret:**
1. Copy webhook secret
2. Update in your facilitator dashboard
3. Test webhook delivery

### Rate Limiting
Rate limiting is enabled by default:
- 5 payment attempts per minute
- 10 verification attempts per minute
- 20 status checks per minute

**To customize:**
```php
// In your theme's functions.php or custom plugin
add_filter('x402_rate_limit', function($limit, $action) {
    if ($action === 'verify_payment') {
        return 20; // Increase to 20 attempts
    }
    return $limit;
}, 10, 2);
```

---

## 📊 Data Migration

### Existing Orders
- All existing order data is preserved
- Order meta data remains intact
- No changes to completed orders

### New Transaction Records
- New transactions automatically recorded in `wp_x402_transactions`
- Historical transactions can be imported (see below)

### Import Historical Transactions (Optional)
```php
// Create a custom script to import old transactions
function x402_import_historical_transactions() {
    global $wpdb;
    
    // Get all orders with X402 payment
    $orders = wc_get_orders(array(
        'payment_method' => 'x402',
        'limit' => -1,
    ));
    
    foreach ($orders as $order) {
        // Get transaction data from order meta
        $tx_hash = $order->get_meta('_x402_transaction_hash');
        $wallet = $order->get_meta('_x402_wallet_address');
        
        if (!empty($tx_hash)) {
            // Check if already exists
            $exists = X402_Installer::get_transaction($tx_hash);
            
            if (!$exists) {
                // Insert historical transaction
                X402_Installer::insert_transaction(array(
                    'order_id' => $order->get_id(),
                    'tx_hash' => $tx_hash,
                    'wallet_address' => $wallet,
                    'amount' => $order->get_total(),
                    'asset' => get_option('wc_x402_asset'),
                    'network' => get_option('wc_x402_network'),
                    'status' => 'completed',
                    'verification_method' => 'historical_import',
                ));
            }
        }
    }
    
    X402_Logger::info('Historical transaction import completed', array(
        'count' => count($orders)
    ));
}

// Run once via WP-CLI or admin page
// wp eval 'x402_import_historical_transactions();'
```

---

## 🧪 Testing After Migration

### 1. Test Payment Processing
```bash
# Create test order
wp wc shop_order create --user=admin --billing_email=test@example.com

# Process test payment
# Use your wallet to complete a test transaction
```

### 2. Test AJAX Endpoints
```javascript
// In browser console on checkout page
jQuery.ajax({
    url: x402_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'x402_check_payment_status',
        _wpnonce: x402_ajax.status_nonce,
        order_id: 123
    },
    success: function(r) { console.log('Success:', r); },
    error: function(e) { console.error('Error:', e); }
});
```

### 3. Test Webhook
```bash
# Get webhook secret
wp option get x402_webhook_secret

# Test webhook (replace with your secret and domain)
curl -X POST https://yoursite.com/wp-admin/admin-ajax.php?action=x402_webhook \
  -H "Content-Type: application/json" \
  -H "X-X402-Signature: $(echo -n '{"event":"payment.verified"}' | openssl dgst -sha256 -hmac 'YOUR_SECRET')" \
  -d '{"event":"payment.verified","order_id":123,"tx_hash":"0x123"}'
```

### 4. Check Logs
```bash
# Via WP-CLI
wp wc log list --source=x402-payments

# View latest log
wp wc log list --source=x402-payments --fields=timestamp,level,message
```

---

## ⚠️ Troubleshooting

### Issue: "Database table not created"
**Solution:**
```sql
-- Manually run installer
-- In WP-CLI:
wp eval 'X402_Installer::install();'

-- Or in WordPress admin (Tools → Site Health → Debug)
```

### Issue: "Webhook secret not generated"
**Solution:**
```php
// Generate manually via WP-CLI
wp eval 'X402_Security_Handler::generate_webhook_secret();'
```

### Issue: "Scheduled tasks not running"
**Solution:**
```bash
# Check if cron is working
wp cron test

# Manually schedule tasks
wp eval '
    wp_schedule_event(time(), "daily", "x402_cleanup_expired_requirements");
    wp_schedule_event(time(), "weekly", "x402_cleanup_old_logs");
'

# List scheduled events
wp cron event list --fields=hook,next_run,recurrence
```

### Issue: "Rate limiting too strict"
**Solution:**
```php
// Add to functions.php to disable temporarily
add_filter('x402_rate_limit', function($limit, $action) {
    return 999; // Very high limit for testing
}, 10, 2);
```

### Issue: "Old settings not migrated"
**Solution:**
```php
// Manually copy old settings if needed
update_option('wc_x402_facilitator_mode', get_option('wc_x402_old_mode'));
update_option('wc_x402_facilitator_endpoint', get_option('wc_x402_old_endpoint'));
```

---

## 🔄 Rollback Procedure

If you need to rollback to the previous version:

### Step 1: Backup Current State
```bash
wp db export rollback-backup-$(date +%Y%m%d).sql
```

### Step 2: Deactivate Plugin
```bash
wp plugin deactivate x402-solana-paywall
```

### Step 3: Restore Previous Version
```bash
# Restore from backup
tar -xzf x402-backup-{date}.tar.gz

# Or checkout previous commit
git checkout {previous-commit-hash}
```

### Step 4: Reactivate
```bash
wp plugin activate x402-solana-paywall
```

### Step 5: Clean Up (Optional)
```sql
-- Remove new tables if needed
DROP TABLE IF EXISTS wp_x402_transactions;
DROP TABLE IF EXISTS wp_x402_payment_requirements;

-- Remove new options
DELETE FROM wp_options WHERE option_name LIKE 'wc_x402_%';
DELETE FROM wp_options WHERE option_name LIKE 'x402_%';
```

---

## 📞 Support

### Getting Help
- Check logs: WooCommerce → Status → Logs
- Review documentation: `AUDIT-IMPLEMENTATION.md`
- Developer guide: `DEVELOPER-GUIDE.md`
- Create issue: https://github.com/mondb-dev/x402-wp/issues

### Before Contacting Support
1. Check error logs
2. Review migration steps
3. Verify database tables exist
4. Test in staging environment first

---

## ✅ Migration Checklist

- [ ] Backup database
- [ ] Backup plugin files
- [ ] Deactivate plugin
- [ ] Update plugin files
- [ ] Reactivate plugin
- [ ] Verify database tables created
- [ ] Check default settings
- [ ] Configure facilitator settings
- [ ] Test payment flow
- [ ] Verify webhook works
- [ ] Check scheduled tasks
- [ ] Review logs
- [ ] Import historical data (optional)
- [ ] Test in production

---

**Migration Version**: 1.0 → 1.1  
**Date**: October 29, 2025  
**Estimated Time**: 15-30 minutes
