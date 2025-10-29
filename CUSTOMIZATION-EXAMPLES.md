# X402 Customization Examples

This document provides real-world examples of customizing the X402 payment templates.

## Table of Contents

1. [Theme Integration Examples](#theme-integration-examples)
2. [Custom Page Layouts](#custom-page-layouts)
3. [Styling Examples](#styling-examples)
4. [Hook Usage Examples](#hook-usage-examples)
5. [Shortcode Examples](#shortcode-examples)
6. [Advanced Customizations](#advanced-customizations)

---

## Theme Integration Examples

### Example 1: Full Width Layout with Sidebar

```php
// wp-content/themes/your-theme/x402/payment-required.php
<?php
get_header();

$paywall_data = X402_Template_Handler::get_paywall_data();
?>

<div class="site-content full-width">
    <div class="content-area">
        <main class="site-main">
            
            <article class="payment-article">
                <header class="entry-header">
                    <?php do_action('x402_paywall_header', $paywall_data); ?>
                </header>

                <div class="entry-content">
                    <?php do_action('x402_paywall_content', $paywall_data); ?>
                </div>

                <footer class="entry-footer">
                    <?php do_action('x402_paywall_footer', $paywall_data); ?>
                </footer>
            </article>

        </main>
    </div>

    <aside class="sidebar">
        <div class="widget payment-benefits">
            <h3>Why Pay?</h3>
            <ul>
                <li>Support quality journalism</li>
                <li>Ad-free reading experience</li>
                <li>Instant access</li>
                <li>Cancel anytime</li>
            </ul>
        </div>

        <div class="widget payment-guarantee">
            <h3>100% Secure</h3>
            <p>Your payment is protected by blockchain technology.</p>
        </div>
    </aside>
</div>

<?php get_footer(); ?>
```

### Example 2: Minimal Single Column Layout

```php
// wp-content/themes/your-theme/x402/payment-required.php
<?php
get_header();

$paywall_data = X402_Template_Handler::get_paywall_data();
?>

<div class="x402-minimal-layout">
    <div class="x402-container">
        
        <!-- Logo/Branding -->
        <div class="payment-branding">
            <?php if (has_custom_logo()): ?>
                <?php the_custom_logo(); ?>
            <?php else: ?>
                <h1><?php bloginfo('name'); ?></h1>
            <?php endif; ?>
        </div>

        <!-- Simple Payment Box -->
        <div class="payment-box">
            <h2><?php echo esc_html(get_the_title()); ?></h2>
            <?php do_action('x402_paywall_content', $paywall_data); ?>
        </div>

        <!-- Footer -->
        <div class="payment-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?></p>
        </div>

    </div>
</div>

<?php get_footer(); ?>
```

---

## Custom Page Layouts

### Example 3: Magazine Style with Preview

```php
// In your theme's functions.php
add_action('x402_before_paywall', function($paywall_data) {
    ?>
    <div class="magazine-header">
        <div class="post-meta">
            <span class="author"><?php the_author(); ?></span>
            <span class="date"><?php echo get_the_date(); ?></span>
            <span class="reading-time">5 min read</span>
        </div>
        
        <?php if (has_post_thumbnail()): ?>
            <div class="featured-image">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}, 5);

add_action('x402_paywall_content', function($paywall_data) {
    ?>
    <div class="content-preview">
        <div class="preview-text">
            <?php echo wp_trim_words(get_the_excerpt(), 100); ?>
        </div>
        <div class="preview-fade"></div>
    </div>
    <?php
}, 5);
```

### Example 4: Video Content Paywall

```php
// In your theme's functions.php
add_action('x402_paywall_header', function($paywall_data) {
    $video_preview = get_post_meta($paywall_data['post_id'], '_video_preview_url', true);
    
    if (!empty($video_preview)): ?>
        <div class="video-preview-container">
            <video controls poster="<?php echo esc_url(get_the_post_thumbnail_url()); ?>">
                <source src="<?php echo esc_url($video_preview); ?>" type="video/mp4">
            </video>
            <div class="video-overlay">
                <div class="overlay-content">
                    <svg class="lock-icon" width="48" height="48">
                        <use xlink:href="#lock-icon"></use>
                    </svg>
                    <p>Unlock full video</p>
                </div>
            </div>
        </div>
    <?php endif;
}, 5);
```

---

## Styling Examples

### Example 5: Dark Theme with Neon Accents

```css
/* In your theme's custom CSS or style.css */
.x402-paywall-wrapper {
    --x402-bg-color: #0a0a0a;
    --x402-border-color: #1a1a1a;
    --x402-heading-color: #ffffff;
    --x402-text-color: #b0b0b0;
    --x402-primary-color: #00ff88;
    --x402-primary-hover: #00cc6a;
    --x402-highlight-bg: #1a1a1a;
    --x402-box-shadow: 0 0 30px rgba(0, 255, 136, 0.2);
    
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
    border: 1px solid rgba(0, 255, 136, 0.2);
}

.x402-payment-amount {
    background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
    border: 2px solid var(--x402-primary-color);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.3);
}

.x402-button-primary {
    background: var(--x402-primary-color);
    color: #0a0a0a;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
    transition: all 0.3s ease;
}

.x402-button-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 30px rgba(0, 255, 136, 0.7);
}
```

### Example 6: Clean Minimal White

```css
.x402-paywall-wrapper {
    --x402-bg-color: #ffffff;
    --x402-border-color: #f0f0f0;
    --x402-heading-color: #2c3e50;
    --x402-text-color: #5a6c7d;
    --x402-primary-color: #3498db;
    --x402-border-radius: 12px;
    --x402-padding: 3rem;
    
    max-width: 500px;
    margin: 4rem auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
}

.x402-paywall-title {
    font-size: 2.5rem;
    font-weight: 300;
    margin-bottom: 1rem;
    text-align: center;
}

.x402-payment-amount {
    background: transparent;
    border: none;
    text-align: center;
    padding: 2rem 0;
}

.amount-value {
    font-size: 4rem;
    font-weight: 100;
    color: var(--x402-primary-color);
}

.x402-button {
    width: 100%;
    padding: 1.25rem;
    border-radius: 50px;
    font-size: 1.125rem;
}
```

### Example 7: Gradient Premium Look

```css
.x402-paywall-wrapper {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    --x402-heading-color: #ffffff;
    --x402-text-color: rgba(255, 255, 255, 0.9);
    --x402-border-color: rgba(255, 255, 255, 0.2);
    --x402-highlight-bg: rgba(255, 255, 255, 0.1);
}

.x402-payment-amount {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.x402-wallet-address {
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
}

.x402-button-primary {
    background: white;
    color: #667eea;
    font-weight: bold;
}

.x402-button-primary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: scale(1.05);
}
```

---

## Hook Usage Examples

### Example 8: Adding Social Proof

```php
// In your theme's functions.php
add_action('x402_paywall_footer', function($paywall_data) {
    $payment_count = get_option('x402_total_payments', 0);
    $recent_buyers = get_option('x402_recent_buyers', array());
    ?>
    <div class="x402-social-proof">
        <p class="payment-count">
            <strong><?php echo number_format($payment_count); ?></strong> people have unlocked this content
        </p>
        
        <?php if (!empty($recent_buyers)): ?>
        <div class="recent-buyers">
            <p>Recent supporters:</p>
            <ul>
                <?php foreach (array_slice($recent_buyers, 0, 5) as $buyer): ?>
                    <li>
                        <?php echo get_avatar($buyer['email'], 32); ?>
                        <span><?php echo esc_html($buyer['name']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php
}, 25);
```

### Example 9: Adding Countdown Timer

```php
add_action('x402_paywall_header', function($paywall_data) {
    $sale_end = get_post_meta($paywall_data['post_id'], '_sale_end_time', true);
    
    if (!empty($sale_end) && time() < $sale_end): ?>
        <div class="x402-countdown" data-end-time="<?php echo esc_attr($sale_end); ?>">
            <p>Limited time offer ends in:</p>
            <div class="countdown-timer">
                <span class="hours">00</span>:
                <span class="minutes">00</span>:
                <span class="seconds">00</span>
            </div>
        </div>
        
        <script>
        (function() {
            var endTime = <?php echo $sale_end; ?> * 1000;
            var timer = setInterval(function() {
                var now = new Date().getTime();
                var distance = endTime - now;
                
                if (distance < 0) {
                    clearInterval(timer);
                    document.querySelector('.x402-countdown').innerHTML = '<p>Offer expired</p>';
                    return;
                }
                
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.querySelector('.x402-countdown .hours').textContent = 
                    String(hours).padStart(2, '0');
                document.querySelector('.x402-countdown .minutes').textContent = 
                    String(minutes).padStart(2, '0');
                document.querySelector('.x402-countdown .seconds').textContent = 
                    String(seconds).padStart(2, '0');
            }, 1000);
        })();
        </script>
    <?php endif;
}, 15);
```

### Example 10: Adding Author Bio

```php
add_action('x402_paywall_content', function($paywall_data) {
    $author_id = get_post_field('post_author', $paywall_data['post_id']);
    ?>
    <div class="x402-author-bio">
        <div class="author-avatar">
            <?php echo get_avatar($author_id, 80); ?>
        </div>
        <div class="author-info">
            <h4>About <?php echo get_the_author_meta('display_name', $author_id); ?></h4>
            <p><?php echo get_the_author_meta('description', $author_id); ?></p>
            <p class="support-message">
                Support my work by unlocking this premium content
            </p>
        </div>
    </div>
    <?php
}, 15);
```

---

## Shortcode Examples

### Example 11: Inline Payment Button

```html
<!-- In your post content -->
<p>This article contains premium insights that are worth reading.</p>

[x402_payment_button text="🔓 Unlock Premium Content" style="primary" class="my-custom-class"]

<p>Already paid? <a href="#verify">Verify your payment here</a></p>
```

### Example 12: Custom Paywall Widget

```html
<!-- In a widget or page -->
<h3>Access This Premium Guide</h3>
<p>Learn advanced strategies used by professionals.</p>

[x402_paywall 
    amount="15" 
    currency="USD" 
    title="Premium Guide Access"
    description="One-time payment for lifetime access"
    button_text="Get Instant Access"]
```

### Example 13: Protected Content Section

```html
<!-- In your post content -->
<h2>Introduction (Free)</h2>
<p>This is the free introduction that everyone can see...</p>

[x402_protected_content message="🔒 The advanced techniques are available to paying members only" show_excerpt="true"]
<h2>Advanced Techniques</h2>
<p>Here are the secret techniques that professionals use...</p>
<ul>
    <li>Technique 1: Advanced strategy</li>
    <li>Technique 2: Pro tips</li>
    <li>Technique 3: Expert insights</li>
</ul>
[/x402_protected_content]
```

### Example 14: Wallet Display with QR

```html
<!-- Sidebar widget or page -->
<h3>Support This Content</h3>
<p>Send a tip to support more content like this:</p>

[x402_wallet_address 
    wallet="YOUR_WALLET_ADDRESS_HERE" 
    show_qr="true"
    label="Tip Jar Address"]
```

### Example 15: Dynamic Amount Display

```html
<!-- In your post -->
<div class="pricing-box">
    <h3>Access Price</h3>
    [x402_payment_amount amount="10" currency="USD" token="SOL" show_conversion="true"]
    <p>Or pay with any supported cryptocurrency</p>
</div>
```

---

## Advanced Customizations

### Example 16: Multi-Tier Pricing

```php
// In your theme's functions.php
add_action('x402_paywall_content', function($paywall_data) {
    $tiers = array(
        'basic' => array('price' => 5, 'name' => 'Basic Access', 'features' => array('Read once', '24-hour access')),
        'premium' => array('price' => 10, 'name' => 'Premium Access', 'features' => array('Unlimited reads', 'Lifetime access', 'Download PDF')),
        'supporter' => array('price' => 25, 'name' => 'Supporter', 'features' => array('Everything in Premium', 'Support the author', 'Early access to new content')),
    );
    ?>
    <div class="x402-pricing-tiers">
        <?php foreach ($tiers as $tier_id => $tier): ?>
            <div class="pricing-tier tier-<?php echo esc_attr($tier_id); ?>">
                <h3><?php echo esc_html($tier['name']); ?></h3>
                <div class="tier-price">
                    $<?php echo number_format($tier['price'], 2); ?>
                </div>
                <ul class="tier-features">
                    <?php foreach ($tier['features'] as $feature): ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button class="x402-button x402-button-primary x402-connect-wallet" 
                        data-post-id="<?php echo esc_attr($paywall_data['post_id']); ?>"
                        data-amount="<?php echo esc_attr($tier['price']); ?>">
                    Choose <?php echo esc_html($tier['name']); ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}, 5);
```

### Example 17: Geographic Pricing

```php
add_filter('x402_paywall_data', function($data, $post_id) {
    // Get user's country (you'd use a geolocation service)
    $user_country = 'US'; // Example
    
    // Adjust pricing based on country
    $pricing_map = array(
        'US' => 10,
        'GB' => 8,
        'IN' => 3,
        'BR' => 4,
    );
    
    $data['amount'] = isset($pricing_map[$user_country]) 
        ? $pricing_map[$user_country] 
        : $data['amount'];
        
    return $data;
}, 10, 2);
```

### Example 18: Bundle Discounts

```php
add_action('x402_after_paywall', function($paywall_data) {
    // Show related content that can be purchased as a bundle
    $related_posts = get_posts(array(
        'category__in' => wp_get_post_categories($paywall_data['post_id']),
        'posts_per_page' => 3,
        'exclude' => array($paywall_data['post_id']),
    ));
    
    if (!empty($related_posts)): ?>
        <div class="x402-bundle-offer">
            <h3>💰 Bundle & Save</h3>
            <p>Get this article plus <?php echo count($related_posts); ?> more for 30% off</p>
            <div class="bundle-items">
                <?php foreach ($related_posts as $post): ?>
                    <div class="bundle-item">
                        <?php echo get_the_post_thumbnail($post->ID, 'thumbnail'); ?>
                        <h4><?php echo esc_html($post->post_title); ?></h4>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="x402-button x402-button-primary">
                Get Bundle for $<?php echo number_format($paywall_data['amount'] * 3 * 0.7, 2); ?>
            </button>
        </div>
    <?php endif;
}, 20);
```

---

## Testing Your Customizations

Always test your customizations:

1. **Test payment flow** - Ensure payments still work
2. **Test responsiveness** - Check mobile, tablet, desktop
3. **Test accessibility** - Use keyboard navigation, screen readers
4. **Test different themes** - Ensure compatibility
5. **Test with different content** - Long titles, no featured image, etc.

---

## Need Help?

- Check the full documentation: `TEMPLATE-CUSTOMIZATION.md`
- Review all available hooks: `DEVELOPER-GUIDE.md`
- Report issues: https://github.com/mondb-dev/x402-wp/issues

