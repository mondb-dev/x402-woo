# Security Documentation - X402 Solana Paywall

## Security Architecture

The X402 Solana Paywall plugin implements bank-level security through multiple layers of protection, following WordPress security best practices and industry standards for financial applications.

## Security Layers

### 1. Data Encryption

**Encryption Algorithm**: AES-256-CBC (Advanced Encryption Standard)
- **Key Size**: 256 bits
- **Block Size**: 128 bits
- **Mode**: Cipher Block Chaining (CBC)
- **IV**: Unique 16-byte initialization vector per encryption

**Implementation**:
```php
// Encryption key stored in database, generated on plugin activation
$key = base64_encode(random_bytes(32)); // 256 bits

// Each encryption uses unique IV
$iv = random_bytes(16);
$encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

// IV prepended to ciphertext
$result = base64_encode($iv . $encrypted);
```

**Encrypted Data**:
- Payment transaction details
- Wallet addresses (when stored)
- Audit log event data
- Session metadata

### 2. Authentication & Authorization

**Nonce Verification**:
- WordPress nonces on all AJAX requests
- 24-hour nonce lifetime
- Action-specific nonces
- Prevents CSRF attacks

**Session Tokens**:
- HMAC-SHA256 hashing
- Includes: post_id, wallet_address, timestamp, random salt
- Uses WordPress auth salt as key
- Cannot be forged without secret key

**Cookie Security**:
- HttpOnly flag (prevents JavaScript access)
- Secure flag on HTTPS sites
- SameSite policy support
- Domain and path restrictions

### 3. Input Validation & Sanitization

**All Inputs Sanitized**:
```php
// Wallet addresses - alphanumeric only
$wallet = preg_replace('/[^A-Za-z0-9]/', '', $input);

// Transaction signatures - alphanumeric only
$signature = preg_replace('/[^A-Za-z0-9]/', '', $input);

// Numeric values
$post_id = absint($input);
$amount = floatval($input);

// Text fields
$text = sanitize_text_field($input);

// URLs
$url = esc_url_raw($input);
```

**Output Escaping**:
```php
// HTML output
echo esc_html($data);

// Attributes
echo esc_attr($data);

// URLs
echo esc_url($url);

// JavaScript
echo esc_js($data);
```

### 4. SQL Injection Prevention

**Prepared Statements**:
All database queries use prepared statements with parameter binding:

```php
$wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE post_id = %d AND wallet_address = %s",
        $post_id,
        $wallet_address
    )
);
```

**Parameterized Inserts**:
```php
$wpdb->insert(
    $table_name,
    $data,
    array('%d', '%s', '%f') // Data types
);
```

### 5. Rate Limiting

**Implementation**:
- Uses WordPress transients
- 10 requests per minute per IP
- 60-second cooldown
- Applied to payment verification endpoint

**Detection**:
```php
$transient_key = 'x402_rate_limit_' . md5($ip);
$requests = get_transient($transient_key);

if ($requests >= 10) {
    wp_send_json_error(['message' => 'Rate limit exceeded'], 429);
}
```

### 6. XSS Prevention

**Strategies**:
1. Output escaping on all dynamic content
2. Content Security Policy headers
3. X-XSS-Protection header
4. No inline JavaScript in PHP
5. Separate .js files with nonce localization

**Headers Set**:
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

### 7. Audit Logging

**Logged Events**:
- Payment verification attempts (success/failure)
- Transaction signature checks
- Access grants/denials
- Security violations
- Rate limit triggers

**Log Data**:
- Event type and encrypted details
- IP address
- User agent
- Timestamp

**Retention**:
- 90-day retention period
- Automatic cleanup
- Encrypted storage

### 8. Session Security

**Token Properties**:
- Cryptographically secure random component
- HMAC prevents tampering
- Time-limited (24 hours default)
- Tied to specific post and wallet

**Token Structure**:
```
HMAC-SHA256(
    post_id + 
    wallet_address + 
    timestamp + 
    random(32 bytes),
    wp_auth_salt
)
```

## Threat Model

### Protected Against

✅ **SQL Injection** - Prepared statements, parameterized queries
✅ **XSS Attacks** - Output escaping, CSP headers
✅ **CSRF** - Nonce verification on all forms
✅ **Session Hijacking** - HttpOnly cookies, secure tokens
✅ **Replay Attacks** - Unique transaction signatures, timestamps
✅ **Brute Force** - Rate limiting, cooldown periods
✅ **Data Theft** - Encryption at rest, secure transmission
✅ **Man-in-the-Middle** - HTTPS enforcement, secure cookies
✅ **Clickjacking** - X-Frame-Options header
✅ **Information Disclosure** - Error message sanitization, debug mode controls

### Attack Scenarios & Mitigations

**Scenario 1: Attacker tries to forge payment**
- Mitigation: Transaction signature verified on blockchain
- Signature must match actual on-chain transaction
- Cannot be forged without private key

**Scenario 2: Attacker replays old transaction**
- Mitigation: Each signature can only be used once
- Database uniqueness constraint on signature
- Duplicate detection before verification

**Scenario 3: Attacker steals session token**
- Mitigation: HttpOnly cookies prevent JS access
- Token tied to specific post and wallet
- Time-limited validity (24 hours)
- HTTPS prevents network sniffing

**Scenario 4: Attacker floods verification endpoint**
- Mitigation: Rate limiting (10 req/min)
- IP-based throttling
- 429 response with retry-after

**Scenario 5: SQL injection attempt**
- Mitigation: All queries use prepared statements
- Input validation removes dangerous characters
- WordPress $wpdb escaping

**Scenario 6: XSS injection in payment form**
- Mitigation: All outputs escaped
- No eval() or innerHTML usage
- CSP headers restrict script sources

## Compliance Features

### Data Protection (GDPR)

- **Minimal Data Collection**: Only transaction essentials stored
- **Encryption**: All personal data encrypted at rest
- **Right to Erasure**: Uninstall removes all data
- **Data Retention**: 90-day audit log retention
- **Access Controls**: Admin-only access to payment data

### Financial Security Standards

- **PCI-DSS Alignment**: 
  - No credit card data stored
  - Encryption of sensitive data
  - Access logging and monitoring
  - Regular security updates

- **SOC 2 Type II Principles**:
  - Security: Multi-layer protection
  - Availability: Rate limiting prevents DoS
  - Confidentiality: Encryption and access controls
  - Processing Integrity: Transaction verification
  - Privacy: Minimal data collection

## Security Testing

### Automated Tests

**WordPress Coding Standards**:
```bash
phpcs --standard=WordPress x402-solana-paywall.php
```

**Security Scanner**:
```bash
# WPScan security checks
wpscan --url http://localhost --plugins
```

### Manual Security Review

**Checklist**:
- [ ] All user inputs sanitized
- [ ] All outputs escaped
- [ ] All queries use prepared statements
- [ ] Nonces on all forms
- [ ] Capability checks on admin functions
- [ ] Rate limiting on public endpoints
- [ ] Encryption keys secure
- [ ] No hardcoded secrets
- [ ] HTTPS enforced
- [ ] Audit logging functional

### Penetration Testing

**Test Cases**:
1. SQL injection in all form fields
2. XSS in payment signature field
3. CSRF token bypass attempts
4. Session fixation/hijacking
5. Rate limit evasion
6. Transaction replay attacks
7. Privilege escalation
8. Information disclosure

## Security Updates

### Update Policy

- **Critical**: Immediate patch release
- **High**: Within 48 hours
- **Medium**: Next scheduled release
- **Low**: Quarterly updates

### Vulnerability Disclosure

Report security issues to:
- GitHub Security Advisories (preferred)
- Email: security@example.com
- Do not disclose publicly until patched

## Best Practices for Users

### For Site Administrators

1. **Keep WordPress Updated**: Always run latest version
2. **Use HTTPS**: SSL/TLS certificate required
3. **Strong Admin Passwords**: Use password manager
4. **Two-Factor Authentication**: Enable on admin accounts
5. **Regular Backups**: Database and files
6. **Monitor Logs**: Check audit logs regularly
7. **Limit Admin Access**: Principle of least privilege
8. **Use Custom RPC**: Avoid public RPC rate limits
9. **Test on Staging**: Test updates before production
10. **Security Plugins**: Consider WordPress security plugins

### For Content Creators

1. **Reasonable Pricing**: Set appropriate payment amounts
2. **Monitor Statistics**: Check for anomalies
3. **Review Payments**: Verify transaction on blockchain
4. **Secure Wallet**: Use hardware wallet for merchant address
5. **Backup Keys**: Secure backup of wallet recovery phrase

### For Readers

1. **Verify Site Security**: Check for HTTPS lock icon
2. **Use Reputable Wallets**: Phantom, Solflare, etc.
3. **Verify Transaction**: Check amount and recipient
4. **Keep Receipts**: Save transaction signatures
5. **Report Issues**: Contact site admin if problems

## Secure Configuration

### Recommended Settings

```php
// wp-config.php additions
define('FORCE_SSL_ADMIN', true);
define('DISALLOW_FILE_EDIT', true);
define('WP_DEBUG', false); // In production
```

### Plugin Settings

- **Network**: Mainnet Beta (production)
- **Logging**: Enabled
- **Session Timeout**: 3600 seconds (1 hour)
- **Custom RPC**: Use dedicated provider

### Server Configuration

**PHP Settings**:
```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /path/to/error.log
```

**Apache (.htaccess)**:
```apache
# Disable directory browsing
Options -Indexes

# Protect wp-config.php
<files wp-config.php>
order allow,deny
deny from all
</files>
```

**Nginx**:
```nginx
# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
```

## Incident Response

### In Case of Security Breach

1. **Isolate**: Disable plugin immediately
2. **Investigate**: Check audit logs for compromise
3. **Notify**: Inform affected users if needed
4. **Patch**: Apply security updates
5. **Monitor**: Increase logging and monitoring
6. **Report**: Disclose to appropriate parties

### Recovery Steps

1. Restore from clean backup
2. Change all passwords and keys
3. Regenerate encryption key
4. Review all payment records
5. Update WordPress and all plugins
6. Perform security scan
7. Re-enable plugin with enhanced monitoring

## Security Roadmap

### Future Enhancements

- [ ] Multi-signature wallet support
- [ ] Hardware security module (HSM) integration
- [ ] Advanced fraud detection
- [ ] Machine learning anomaly detection
- [ ] Real-time security monitoring dashboard
- [ ] Integration with security services (Cloudflare, etc.)
- [ ] Automated vulnerability scanning
- [ ] Bug bounty program

## Conclusion

The X402 Solana Paywall plugin implements comprehensive security measures suitable for financial applications. Regular updates, proper configuration, and adherence to best practices ensure bank-level security for cryptocurrency payments in WordPress.

For questions or security concerns, please refer to our security policy or contact the development team.
