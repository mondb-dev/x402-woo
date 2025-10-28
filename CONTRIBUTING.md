# Contributing to X402 Solana Paywall

Thank you for considering contributing to the X402 Solana Paywall plugin! This document provides guidelines for contributing to the project.

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Respect differing viewpoints and experiences
- Accept responsibility and apologize for mistakes

## How to Contribute

### Reporting Bugs

Before creating a bug report:
1. Check existing issues to avoid duplicates
2. Test on the latest version of the plugin
3. Verify it's not a configuration issue

When creating a bug report, include:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior
- Screenshots if applicable
- Error messages from debug.log

### Suggesting Features

Feature requests are welcome! Please:
- Check if the feature already exists
- Explain the use case clearly
- Describe expected behavior
- Consider security implications
- Be open to discussion and alternatives

### Security Vulnerabilities

**DO NOT** open public issues for security vulnerabilities!

Instead:
- Email security@example.com
- Use GitHub Security Advisories
- Allow time for patch before disclosure
- We'll credit you in the security advisory

## Development Setup

### Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.6+
- Git
- Composer (optional)
- Node.js and npm (for asset building)

### Local Development

1. **Clone the repository**
```bash
git clone https://github.com/mondb-dev/x402-wp.git
cd x402-wp
```

2. **Set up WordPress locally**
- Use Local by Flywheel, MAMP, or similar
- Or use wp-env: `npm install -g @wordpress/env && wp-env start`

3. **Install the plugin**
```bash
ln -s /path/to/x402-wp /path/to/wordpress/wp-content/plugins/x402-solana-paywall
```

4. **Activate in WordPress**
```bash
wp plugin activate x402-solana-paywall
```

### Testing Environment

Use Solana Devnet for testing:
1. Set network to "Devnet" in plugin settings
2. Get free devnet SOL from faucet
3. Create test merchant wallet
4. Test payment flow end-to-end

## Coding Standards

### WordPress Standards

Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/):

```bash
# Install PHP_CodeSniffer
composer global require "squizlabs/php_codesniffer=*"

# Install WordPress standards
composer global require "wp-coding-standards/wpcs=*"

# Check code
phpcs --standard=WordPress x402-solana-paywall.php
```

### PHP Standards

- PHP 7.4+ syntax
- Type hints where appropriate
- DocBlocks for all functions
- Meaningful variable names
- Single responsibility principle

### JavaScript Standards

- ES6+ syntax
- jQuery for WordPress compatibility
- No inline JavaScript
- Proper escaping
- JSDoc comments

### CSS Standards

- Mobile-first responsive design
- BEM naming convention preferred
- Vendor prefixes where needed
- Comments for complex selectors

## Code Review Process

### Pull Request Guidelines

1. **Branch naming**
   - `feature/description` for new features
   - `fix/description` for bug fixes
   - `security/description` for security patches

2. **Commit messages**
   - Clear, descriptive messages
   - Reference issue numbers
   - Use conventional commits format

3. **PR description**
   - What changes were made
   - Why the changes were needed
   - How to test the changes
   - Screenshots for UI changes

4. **Before submitting**
   - Test thoroughly
   - Check coding standards
   - Update documentation
   - Add/update tests if applicable
   - Ensure no security issues

### Review Criteria

Code will be reviewed for:
- ✅ Functionality - Does it work as intended?
- ✅ Security - Are there vulnerabilities?
- ✅ Performance - Is it efficient?
- ✅ Code quality - Is it maintainable?
- ✅ Documentation - Is it documented?
- ✅ Standards - Does it follow conventions?

## Security Guidelines

### Critical Security Rules

1. **Always sanitize inputs**
```php
$input = sanitize_text_field($_POST['field']);
```

2. **Always escape outputs**
```php
echo esc_html($output);
echo esc_attr($attribute);
echo esc_url($url);
```

3. **Use prepared statements**
```php
$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id);
```

4. **Verify nonces**
```php
check_admin_referer('action_name');
wp_verify_nonce($_POST['nonce'], 'action');
```

5. **Check capabilities**
```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

6. **Never store sensitive data unencrypted**
```php
$encrypted = X402_Security::encrypt($sensitive_data);
```

### Security Testing

Before submitting:
- [ ] Test for SQL injection
- [ ] Test for XSS vulnerabilities
- [ ] Verify CSRF protection
- [ ] Check authentication/authorization
- [ ] Test rate limiting
- [ ] Verify data encryption
- [ ] Check error messages (no info disclosure)

## Documentation

### Code Comments

```php
/**
 * Short description of function
 *
 * Longer description if needed, explaining
 * the purpose and any important details.
 *
 * @param string $param1 Description of param1
 * @param int    $param2 Description of param2
 * @return bool True on success, false on failure
 */
function example_function($param1, $param2) {
    // Implementation
}
```

### User Documentation

When adding features:
- Update README.md
- Add examples to USAGE.md
- Document security implications in SECURITY.md
- Update CHANGELOG.md

## Testing

### Manual Testing

Test checklist:
- [ ] Plugin activation/deactivation
- [ ] Settings page functionality
- [ ] Post/page paywall settings
- [ ] Payment verification flow
- [ ] Content protection
- [ ] Session management
- [ ] Error handling
- [ ] Mobile responsiveness
- [ ] Different WordPress versions
- [ ] Different PHP versions

### Automated Testing

While we don't currently have automated tests, contributions are welcome:
- PHPUnit for unit tests
- WP-Browser for integration tests
- Jest for JavaScript tests

## Release Process

Releases follow semantic versioning:
- **Major** (1.0.0): Breaking changes
- **Minor** (0.1.0): New features, backward compatible
- **Patch** (0.0.1): Bug fixes, backward compatible

### Checklist for Releases

- [ ] Update version in main plugin file
- [ ] Update version constant
- [ ] Update CHANGELOG.md
- [ ] Test on fresh WordPress install
- [ ] Security review
- [ ] Create git tag
- [ ] Build release package
- [ ] Update documentation

## Getting Help

- **Documentation**: Check README.md and USAGE.md
- **Issues**: Search existing issues
- **Discussions**: Use GitHub Discussions for questions
- **Security**: Email for security concerns

## License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later license.

## Recognition

Contributors will be:
- Listed in CHANGELOG.md
- Credited in release notes
- Mentioned in README.md (for significant contributions)
- Invited to join as maintainers (for ongoing contributions)

Thank you for contributing to X402 Solana Paywall!
