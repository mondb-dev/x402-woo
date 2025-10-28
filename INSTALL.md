# Quick Installation Guide

## 5-Minute Setup

### Step 1: Upload Plugin
```bash
# Option A: Upload via WordPress Admin
1. Download the plugin ZIP
2. Go to Plugins > Add New
3. Click "Upload Plugin"
4. Select ZIP file
5. Click "Install Now"
6. Activate the plugin

# Option B: Manual Installation
1. Upload x402-solana-paywall folder to /wp-content/plugins/
2. Activate via Plugins menu
```

### Step 2: Configure Settings
```
WordPress Admin → Settings → X402 Paywall

Required Settings:
- Merchant Wallet Address: [Your Solana wallet address]
- Solana Network: Devnet (testing) or Mainnet Beta (production)

Optional Settings:
- Custom RPC Endpoint (recommended for production)
- Session Timeout (default: 1 hour)
- Enable Logging ✓ (recommended)
```

### Step 3: Protect Your First Post
```
1. Edit any post or page
2. Find "X402 Paywall Settings" box (right sidebar)
3. ✓ Check "Enable paywall for this content"
4. Set amount: 0.1 SOL
5. Click "Update" or "Publish"
```

### Step 4: Test Payment Flow
```
1. Log out or open incognito window
2. Visit the protected post
3. You'll see:
   - Content preview
   - Payment instructions
   - Wallet and signature input fields
4. Send SOL to merchant wallet
5. Enter transaction details
6. Click "Verify Payment"
7. Access granted for 24 hours
```

## Quick Checklist

Before Going Live:
- [ ] HTTPS enabled on your site
- [ ] Merchant wallet address configured
- [ ] Network set to Mainnet Beta
- [ ] Custom RPC endpoint configured (optional but recommended)
- [ ] Test payment flow on Devnet first
- [ ] Backup your database
- [ ] Enable security logging
- [ ] Review SECURITY.md

## First Time Testing

Use Solana Devnet:
1. Set "Solana Network" to "Devnet"
2. Get free devnet SOL: https://solfaucet.com/
3. Create test wallet: https://phantom.app/
4. Test complete payment flow
5. Verify access granted after payment

## Common Issues

**"Merchant wallet not configured"**
→ Set merchant wallet address in Settings

**"Payment verification failed"**
→ Check network (mainnet vs devnet)
→ Verify transaction signature is correct
→ Wait for transaction to confirm on blockchain

**Content not protected**
→ Ensure paywall is enabled in post settings
→ Check amount is greater than 0

## Need Help?

- Documentation: See README.md
- Code Examples: See USAGE.md
- Security Info: See SECURITY.md
- Issues: https://github.com/mondb-dev/x402-wp/issues

## Next Steps

1. Read USAGE.md for advanced features
2. Review SECURITY.md for security best practices
3. Check CHANGELOG.md for latest updates
4. See CONTRIBUTING.md to contribute

---

**Ready to monetize your content with crypto payments!** 🚀
