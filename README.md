# X402 Solana Paywall - WordPress Plugin

A bank-level secure cryptocurrency paywall plugin for WordPress that enables content creators to monetize their posts and pages using Solana blockchain payments. Built on the x402-solana protocol.

## Features

### Core Functionality
- **Content Paywall**: Place posts and pages behind a paywall
- **Solana Integration**: Accept SOL payments on Solana blockchain
- **Flexible Pricing**: Set custom payment amounts per post/page
- **Session Management**: 24-hour access sessions after payment
- **Admin Dashboard**: Easy configuration and payment statistics

### Bank-Level Security
- **AES-256-CBC Encryption**: All sensitive data encrypted at rest
- **HMAC-SHA256 Tokens**: Secure session token generation
- **Rate Limiting**: 10 requests per minute per IP
- **Nonce Verification**: WordPress nonces on all AJAX requests
- **Audit Logging**: Complete security event logging (90-day retention)
- **HttpOnly Cookies**: Secure cookie implementation
- **Security Headers**: X-Frame-Options, X-XSS-Protection, etc.
- **Input Sanitization**: All user inputs properly sanitized and validated
- **SQL Injection Protection**: Prepared statements for all database queries
- **XSS Prevention**: Output escaping on all dynamic content

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **OpenSSL**: For encryption functionality

## Installation

1. Download the plugin files
2. Upload the `x402-solana-paywall` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Settings > X402 Paywall to configure

## Configuration

### Initial Setup

1. **Set Merchant Wallet**
   - Go to Settings > X402 Paywall
   - Enter your Solana wallet address in "Merchant Wallet Address"
   - This is where payments will be received

2. **Choose Network**
   - Select Mainnet Beta for production
   - Use Testnet or Devnet for testing

3. **Optional Settings**
   - Custom RPC Endpoint (e.g., QuickNode, Alchemy)
   - Session timeout duration
   - Enable/disable audit logging

### Protecting Content

1. Edit any post or page
2. Look for "X402 Paywall Settings" in the sidebar
3. Check "Enable paywall for this content"
4. Set the payment amount in SOL
5. Publish or update the post

## Usage

### For Administrators/Authors

- Protected content shows a lock icon (🔒) in the posts list
- View payment statistics in the meta box on each post
- Access detailed payment records in the database
- Clean up old logs and expired payments from Settings page

### For Readers

1. Visit a protected post/page
2. See preview content and payment information
3. Send required SOL amount to merchant wallet
4. Enter wallet address and transaction signature
5. Click "Verify Payment"
6. Access unlocked for 24 hours

## Security Features

### Encryption
- **Algorithm**: AES-256-CBC
- **Key Management**: Randomly generated 256-bit key stored in database
- **IV**: Unique initialization vector per encryption
- **Data Protection**: Payment details, transaction data encrypted at rest

### Authentication
- **Nonce Verification**: All AJAX requests verified with WordPress nonces
- **Session Tokens**: HMAC-SHA256 tokens with post ID, wallet, and timestamp
- **Cookie Security**: HttpOnly and Secure flags enabled
- **Rate Limiting**: Prevents brute force and DDoS attacks

### Audit Trail
- All payment verifications logged
- Security events tracked with IP and user agent
- 90-day retention policy
- Encrypted event data storage

## Database Schema

### x402_payments
Stores payment records with encrypted data:
- Transaction signatures
- Wallet addresses
- Payment amounts and currency
- Session tokens and expiration
- Verification status and timestamps

### x402_audit_log
Security event logging:
- Event types and encrypted data
- IP addresses and user agents
- Timestamps

## API Endpoints

### AJAX Endpoints

**Verify Payment**
- Endpoint: `wp-ajax-x402_verify_payment`
- Method: POST
- Parameters: `nonce`, `post_id`, `signature`, `wallet_address`
- Returns: Success/error with session token

## Development

### File Structure
```
x402-solana-paywall/
├── x402-solana-paywall.php    # Main plugin file
├── uninstall.php               # Uninstall cleanup
├── includes/
│   ├── class-x402-database.php        # Database operations
│   ├── class-x402-security.php        # Security functions
│   ├── class-x402-payment.php         # Payment processing
│   ├── class-x402-content-protection.php  # Content filtering
│   ├── class-x402-admin.php           # Admin interface
│   └── class-x402-api.php             # AJAX handlers
├── assets/
│   ├── css/
│   │   ├── frontend.css       # Frontend styles
│   │   └── admin.css          # Admin styles
│   └── js/
│       ├── frontend.js        # Frontend JavaScript
│       └── admin.js           # Admin JavaScript
└── languages/                  # Translation files
```

### Hooks and Filters

**Actions**
- `x402_payment_verified`: Fired when payment is verified
- `x402_access_granted`: Fired when access is granted

**Filters**
- `x402_payment_amount`: Filter payment amount before processing
- `x402_session_duration`: Filter session duration
- `x402_paywall_content`: Filter paywall UI HTML

## Blockchain Integration

The plugin is designed to work with the x402-solana protocol (https://github.com/payAINetwork/x402-solana).

### Current Implementation
- Placeholder transaction verification
- Signature and wallet validation
- Network RPC endpoint configuration

### Production Integration
For production use, integrate actual Solana blockchain verification:
- Install Solana Web3.js or use WordPress HTTP API
- Implement `verify_on_chain()` method in `class-x402-payment.php`
- Verify transaction amount, recipient, and confirmation status
- Handle transaction errors and edge cases

## Security Best Practices

1. **Always use HTTPS** in production
2. **Keep WordPress and plugins updated**
3. **Use strong merchant wallet security**
4. **Regular backup of payment database**
5. **Monitor audit logs for suspicious activity**
6. **Use custom RPC endpoint for production** (avoid rate limits)
7. **Test on testnet/devnet before mainnet**
8. **Regular security audits recommended**

## Troubleshooting

### Common Issues

**Payment verification fails**
- Check Solana network status
- Verify RPC endpoint is accessible
- Ensure transaction has confirmed
- Check wallet address format

**Content not protected**
- Verify paywall is enabled in post settings
- Check payment amount is set and > 0
- Clear WordPress cache

**Rate limit errors**
- Wait 60 seconds between requests
- Check for multiple users behind same IP

## Compliance

### Data Protection
- Encrypted storage of payment data
- Minimal data collection
- Automatic cleanup of old logs
- GDPR considerations for EU users

### Financial Security
- No storage of private keys
- No payment processing (blockchain-based)
- Audit trail for all transactions
- Bank-level encryption standards

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/mondb-dev/x402-wp
- X402 Protocol: https://github.com/payAINetwork/x402-solana

## License

GPL v2 or later

## Credits

Built on the x402-solana protocol by payAINetwork
WordPress integration by X402 Network

## Changelog

### 1.0.0
- Initial release
- Core paywall functionality
- Solana blockchain integration
- Bank-level security features
- Admin interface and settings
- Audit logging and compliance features
