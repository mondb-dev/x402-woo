# X402 Template System - Quick Reference

## Template Override Locations

```
Plugin: /plugins/x402-wp/templates/[file].php
Theme:  /themes/your-theme/x402/[file].php  ← Copy here to override
```

## Available Templates

- `payment-required.php` - Main paywall page
- `payment-success.php` - Success confirmation page
- `payment-failed.php` - Error/failed payment page
- `payment-form.php` - Payment form component
- `payment-error.php` - Error message component

## Quick Hooks Reference

### Most Useful Hooks

```php
// Add content before payment form
add_action('x402_before_payment_form', 'my_function');

// Modify paywall title
add_filter('x402_paywall_title', 'my_function');

// Add custom content to paywall
add_action('x402_paywall_content', 'my_function', 25);

// Customize success message
add_filter('x402_success_message', 'my_function');

// Disable default styles
add_filter('x402_load_default_styles', '__return_false');
```

## CSS Variables Quick List

```css
:root {
    --x402-primary-color: #4f46e5;     /* Main brand color */
    --x402-bg-color: #ffffff;          /* Background */
    --x402-text-color: #4a4a4a;        /* Text color */
    --x402-border-radius: 8px;         /* Corner rounding */
    --x402-padding: 2rem;              /* Container padding */
}
```

## Shortcodes Quick Reference

```
[x402_paywall amount="10" currency="USD"]
[x402_payment_button text="Pay Now"]
[x402_payment_status]
[x402_wallet_address wallet="ADDRESS" show_qr="true"]
[x402_payment_amount amount="10" currency="USD" token="SOL"]
[x402_protected_content]Content[/x402_protected_content]
```

## Common Customizations

### Change Button Color
```css
.x402-button-primary {
    background: #your-color;
}
```

### Add Custom Header
```php
add_action('x402_before_paywall', function() {
    echo '<div class="my-header">Custom content</div>';
}, 5);
```

### Customize Amount Display
```php
add_filter('x402_paywall_data', function($data) {
    $data['amount'] = '15.00';
    return $data;
});
```

## Template Functions

```php
// Get paywall data
$data = X402_Template_Handler::get_paywall_data($post_id);

// Include template part
X402_Template_Handler::get_template_part('payment', 'form', $args);

// Get payment form HTML
$html = X402_Template_Handler::get_payment_form($post_id);

// Check if user has access
$has_access = X402_Content_Protection::user_has_access($post_id);
```

## JavaScript Events

```javascript
// Payment started
document.addEventListener('x402:payment:initiated', handler);

// Payment success
document.addEventListener('x402:payment:success', handler);

// Payment failed
document.addEventListener('x402:payment:failed', handler);

// Wallet connected
document.addEventListener('x402:wallet:connected', handler);
```

## File Structure

```
x402-wp/
├── templates/                    ← Template files
│   ├── payment-required.php
│   ├── payment-success.php
│   ├── payment-failed.php
│   ├── payment-form.php
│   └── payment-error.php
├── assets/
│   ├── css/
│   │   └── templates.css        ← Template styles
│   └── js/
│       └── frontend.js          ← Frontend interactions
└── includes/
    ├── class-x402-template-handler.php      ← Template system
    ├── class-x402-template-functions.php    ← Default hooks
    └── class-x402-shortcodes.php            ← Shortcodes
```

## Full Documentation

- **Template Customization:** `TEMPLATE-CUSTOMIZATION.md`
- **Examples:** `CUSTOMIZATION-EXAMPLES.md`
- **Developer Guide:** `DEVELOPER-GUIDE.md`
- **Usage Guide:** `USAGE.md`

## Support

Report issues: https://github.com/mondb-dev/x402-wp/issues
