# X402 Template Customization Guide

## Overview

The X402 Solana Paywall plugin provides a fully theme-agnostic template system with extensive customization options. Templates can be overridden, hooks can be used to inject custom content, and CSS can be customized using CSS custom properties (CSS variables).

## Table of Contents

1. [Template Override System](#template-override-system)
2. [Available Templates](#available-templates)
3. [Hook Reference](#hook-reference)
4. [CSS Customization](#css-customization)
5. [JavaScript Integration](#javascript-integration)
6. [Examples](#examples)

---

## Template Override System

### How to Override Templates

To customize any X402 template, copy it from the plugin directory to your theme:

**Plugin Location:**
```
/wp-content/plugins/x402-wp/templates/[template-name].php
```

**Theme Location (copy to):**
```
/wp-content/themes/your-theme/x402/[template-name].php
```

The plugin automatically checks your theme directory first before using its default templates.

### Template Hierarchy

Templates are loaded in this order:
1. `your-theme/x402/[template-name].php`
2. `your-theme/[template-name].php`
3. `x402-wp/templates/[template-name].php` (fallback)

---

## Available Templates

### Main Page Templates

#### 1. `payment-required.php`
Full page template for content that requires payment.

**Usage:** Displayed when a user tries to access paid content without payment.

**Available Data:** `$paywall_data` array with:
- `enabled` (bool) - Whether paywall is enabled
- `amount` (string) - Payment amount
- `currency` (string) - Currency code (USD, EUR, etc.)
- `wallet_address` (string) - Payment wallet address
- `network` (string) - Blockchain network
- `description` (string) - Payment description
- `post_id` (int) - Post ID
- `token_info` (array) - Token information

**Hooks in this template:**
- `x402_before_paywall_content`
- `x402_before_paywall`
- `x402_paywall_header`
- `x402_paywall_content`
- `x402_paywall_footer`
- `x402_after_paywall`
- `x402_after_paywall_content`

#### 2. `payment-success.php`
Full page template for successful payment confirmation.

**Usage:** Displayed when payment is successfully verified (URL: `?x402_payment=success`).

**Available Data:** `$success_data` array with:
- `transaction_id` (string) - Blockchain transaction ID
- `post_id` (int) - Post ID

**Hooks in this template:**
- `x402_before_success_content`
- `x402_before_payment_success`
- `x402_payment_success_header`
- `x402_payment_success_content`
- `x402_payment_success_footer`
- `x402_after_payment_success`
- `x402_after_success_content`

#### 3. `payment-failed.php`
Full page template for failed payment.

**Usage:** Displayed when payment verification fails (URL: `?x402_payment=failed`).

**Available Data:** `$error_data` array with:
- `message` (string) - Error message
- `post_id` (int) - Post ID

**Hooks in this template:**
- `x402_before_error_content`
- `x402_before_payment_error`
- `x402_payment_error_header`
- `x402_payment_error_content`
- `x402_payment_error_footer`
- `x402_after_payment_error`
- `x402_after_error_content`

### Template Parts

#### 4. `payment-form.php`
Payment form component (included in paywall page).

**Hooks:**
- `x402_before_payment_form`
- `x402_payment_amount_display`
- `x402_payment_wallet_display`
- `x402_payment_actions`
- `x402_payment_status_display`
- `x402_after_payment_form`

#### 5. `payment-error.php`
Error message component.

**Hooks:**
- `x402_before_error_message`
- `x402_error_icon`
- `x402_error_help_content`
- `x402_after_error_message`

---

## Hook Reference

### Filter Hooks

#### Template Loading
```php
// Modify template path
apply_filters('x402_get_template', $located, $template_name, $args);

// Modify template part
apply_filters('x402_get_template_part', $templates, $slug, $name, $args);
```

#### Content Filters
```php
// Modify paywall data
apply_filters('x402_paywall_data', $data, $post_id);

// Modify text strings
apply_filters('x402_paywall_title', $title, $paywall_data);
apply_filters('x402_paywall_description', $description, $paywall_data);
apply_filters('x402_success_title', $title, $success_data);
apply_filters('x402_success_message', $message, $success_data);
apply_filters('x402_error_title', $title, $error_data);
apply_filters('x402_error_message', $message, $error_data);

// Modify button text
apply_filters('x402_access_button_text', $text, $args);
apply_filters('x402_retry_button_text', $text, $args);
apply_filters('x402_home_button_text', $text, $args);

// Control default styles
apply_filters('x402_load_default_styles', true);
```

### Action Hooks

#### Page Structure Hooks
```php
// Paywall page
do_action('x402_before_paywall_content');
do_action('x402_before_paywall', $paywall_data);
do_action('x402_paywall_header', $paywall_data);
do_action('x402_paywall_content', $paywall_data);
do_action('x402_paywall_footer', $paywall_data);
do_action('x402_after_paywall', $paywall_data);
do_action('x402_after_paywall_content');

// Success page
do_action('x402_before_success_content');
do_action('x402_before_payment_success', $success_data);
do_action('x402_payment_success_header', $success_data);
do_action('x402_payment_success_content', $success_data);
do_action('x402_payment_success_footer', $success_data);
do_action('x402_after_payment_success', $success_data);
do_action('x402_after_success_content');

// Error page
do_action('x402_before_error_content');
do_action('x402_before_payment_error', $error_data);
do_action('x402_payment_error_header', $error_data);
do_action('x402_payment_error_content', $error_data);
do_action('x402_payment_error_footer', $error_data);
do_action('x402_after_payment_error', $error_data);
do_action('x402_after_error_content');
```

#### Component Hooks
```php
// Payment form
do_action('x402_before_payment_form', $paywall_data);
do_action('x402_payment_amount_display', $paywall_data);
do_action('x402_payment_wallet_display', $paywall_data);
do_action('x402_payment_actions', $paywall_data);
do_action('x402_payment_status_display', $paywall_data);
do_action('x402_after_payment_form', $paywall_data);

// Template part hooks
do_action('x402_before_template_part', $template, $slug, $name, $args);
do_action('x402_after_template_part', $template, $slug, $name, $args);
do_action('x402_before_template', $template_name, $located, $args);
do_action('x402_after_template', $template_name, $located, $args);
```

#### Default Hook Implementations

These functions are hooked by default (you can remove them if needed):

**Paywall Hooks:**
- `x402_template_paywall_title` (priority 10)
- `x402_template_paywall_description` (priority 20)
- `x402_template_payment_preview` (priority 10)
- `x402_template_payment_form` (priority 20)
- `x402_template_payment_info` (priority 30)
- `x402_template_payment_security_notice` (priority 10)

**Success Hooks:**
- `x402_template_success_icon` (priority 10)
- `x402_template_success_title` (priority 20)
- `x402_template_success_message` (priority 10)
- `x402_template_transaction_details` (priority 20)
- `x402_template_access_button` (priority 30)

**Error Hooks:**
- `x402_template_error_icon` (priority 10)
- `x402_template_error_title` (priority 20)
- `x402_template_error_message` (priority 10)
- `x402_template_retry_button` (priority 30)
- `x402_template_error_support` (priority 10)

---

## CSS Customization

### Using CSS Custom Properties

The plugin uses CSS custom properties (variables) for easy customization without overriding entire stylesheets.

#### Available CSS Variables

```css
/* Colors */
--x402-bg-color: #ffffff;
--x402-border-color: #e0e0e0;
--x402-heading-color: #1a1a1a;
--x402-text-color: #4a4a4a;
--x402-text-muted: #6b7280;
--x402-primary-color: #4f46e5;
--x402-primary-hover: #4338ca;
--x402-secondary-color: #6b7280;
--x402-secondary-hover: #4b5563;
--x402-success-color: #10b981;
--x402-error-color: #ef4444;
--x402-warning-color: #f59e0b;
--x402-info-color: #3b82f6;

/* Backgrounds */
--x402-highlight-bg: #f8f9fa;
--x402-code-bg: #000000;
--x402-code-color: #ffffff;
--x402-info-bg: #eff6ff;
--x402-badge-bg: #e0e7ff;
--x402-badge-color: #4f46e5;

/* Sizing */
--x402-padding: 2rem;
--x402-border-radius: 8px;
--x402-h1-size: 2rem;
--x402-h2-size: 1.5rem;
--x402-text-size: 1rem;
--x402-amount-size: 2.5rem;
--x402-button-size: 1rem;
--x402-button-padding: 0.75rem 1.5rem;
--x402-button-radius: 6px;

/* Effects */
--x402-box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
--x402-button-text: #ffffff;
```

### Customization Methods

#### Method 1: In your theme's CSS file

```css
/* In your theme's style.css or custom CSS */
:root {
    --x402-primary-color: #ff6b6b;
    --x402-primary-hover: #ff5252;
    --x402-border-radius: 16px;
    --x402-button-radius: 24px;
}
```

#### Method 2: Using WordPress Customizer

Add custom CSS through: **Appearance > Customize > Additional CSS**

```css
.x402-paywall-wrapper {
    --x402-bg-color: #f0f4f8;
    --x402-padding: 3rem;
    max-width: 600px;
}

.x402-button-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

#### Method 3: Programmatically via PHP

```php
// In your theme's functions.php
add_action('wp_enqueue_scripts', function() {
    $custom_css = "
        :root {
            --x402-primary-color: " . get_theme_mod('primary_color', '#4f46e5') . ";
            --x402-border-radius: 12px;
        }
    ";
    wp_add_inline_style('x402-templates', $custom_css);
}, 20);
```

#### Method 4: Disable Default Styles Completely

```php
// In your theme's functions.php
add_filter('x402_load_default_styles', '__return_false');
```

Then enqueue your own stylesheet:

```php
add_action('wp_enqueue_scripts', function() {
    if (/* on X402 page */) {
        wp_enqueue_style(
            'my-x402-styles',
            get_stylesheet_directory_uri() . '/x402-custom.css',
            array(),
            '1.0.0'
        );
    }
});
```

### Dark Mode Support

The plugin includes automatic dark mode support. You can customize dark mode colors:

```css
@media (prefers-color-scheme: dark) {
    :root {
        --x402-bg-color: #1a1a2e;
        --x402-border-color: #16213e;
        --x402-heading-color: #eaeaea;
        --x402-text-color: #b8b8b8;
    }
}
```

---

## JavaScript Integration

### Available Events

The plugin dispatches custom events you can listen to:

```javascript
// Payment initiated
document.addEventListener('x402:payment:initiated', function(e) {
    console.log('Payment started', e.detail);
});

// Payment successful
document.addEventListener('x402:payment:success', function(e) {
    console.log('Payment verified', e.detail.transactionId);
});

// Payment failed
document.addEventListener('x402:payment:failed', function(e) {
    console.log('Payment failed', e.detail.error);
});

// Wallet connected
document.addEventListener('x402:wallet:connected', function(e) {
    console.log('Wallet connected', e.detail.address);
});
```

### Extending Functionality

Add custom JavaScript:

```php
// In your theme's functions.php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'my-x402-customizations',
        get_stylesheet_directory_uri() . '/js/x402-custom.js',
        array('x402-frontend'),
        '1.0.0',
        true
    );
});
```

---

## Examples

### Example 1: Adding a Custom Header to Paywall

```php
// In your theme's functions.php
add_action('x402_before_paywall', function($paywall_data) {
    echo '<div class="my-custom-banner">';
    echo '<h2>Premium Content</h2>';
    echo '<p>Support independent journalism with cryptocurrency</p>';
    echo '</div>';
}, 5); // Priority 5 to run before default content
```

### Example 2: Customizing Success Message

```php
add_filter('x402_success_message', function($message, $data) {
    return '<p>🎉 Awesome! Your payment of ' . $data['transaction_id'] . ' was confirmed. Thank you for supporting quality content!</p>';
}, 10, 2);
```

### Example 3: Adding Trust Badges

```php
add_action('x402_paywall_footer', function($paywall_data) {
    ?>
    <div class="trust-badges">
        <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/secure-badge.png" alt="Secure">
        <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/blockchain-badge.png" alt="Blockchain">
    </div>
    <?php
}, 30);
```

### Example 4: Full Template Override

Create `/wp-content/themes/your-theme/x402/payment-required.php`:

```php
<?php
get_header();

$paywall_data = X402_Template_Handler::get_paywall_data();
?>

<div class="my-custom-paywall">
    <div class="paywall-hero">
        <h1><?php echo esc_html(get_the_title()); ?></h1>
        <p class="tagline">Premium content worth paying for</p>
    </div>
    
    <div class="paywall-grid">
        <div class="payment-section">
            <?php do_action('x402_paywall_content', $paywall_data); ?>
        </div>
        
        <div class="benefits-section">
            <h3>Why Subscribe?</h3>
            <ul>
                <li>Ad-free experience</li>
                <li>Support independent creators</li>
                <li>Instant access</li>
            </ul>
        </div>
    </div>
</div>

<?php get_footer(); ?>
```

### Example 5: Customizing Button Styles

```css
/* Gradient buttons */
.x402-button-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
}

.x402-button-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

/* Rounded, colorful buttons */
.x402-button {
    border-radius: 50px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-size: 0.875rem;
}
```

### Example 6: Adding Analytics Tracking

```php
add_action('x402_payment_success_content', function($success_data) {
    ?>
    <script>
    // Track successful payment
    if (typeof gtag !== 'undefined') {
        gtag('event', 'purchase', {
            'transaction_id': '<?php echo esc_js($success_data['transaction_id']); ?>',
            'value': <?php echo esc_js($success_data['amount'] ?? 0); ?>,
            'currency': '<?php echo esc_js($success_data['currency'] ?? 'USD'); ?>'
        });
    }
    </script>
    <?php
}, 40);
```

### Example 7: Mobile-Optimized Layout

```css
@media (max-width: 768px) {
    .x402-paywall-wrapper {
        --x402-padding: 1rem;
        --x402-h1-size: 1.5rem;
        --x402-amount-size: 2rem;
    }
    
    .x402-payment-amount {
        padding: 1rem;
    }
    
    .x402-wallet-address {
        font-size: 0.75rem;
        padding: 0.5rem;
    }
}
```

---

## Best Practices

1. **Always test after customization** - Ensure payment flow still works
2. **Use hooks over template overrides** when possible - Easier to maintain
3. **Respect accessibility** - Maintain ARIA labels, focus states, keyboard navigation
4. **Test responsive design** - Mobile users are common
5. **Keep security in mind** - Don't remove security-related elements
6. **Use child themes** - Protect customizations from theme updates
7. **Document your changes** - Help future developers

---

## Support

For more information:
- **Documentation:** See USAGE.md and DEVELOPER-GUIDE.md
- **Issues:** https://github.com/mondb-dev/x402-wp/issues
- **Community:** Join our Discord for theme customization help

