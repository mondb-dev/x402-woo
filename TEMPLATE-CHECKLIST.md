# X402 Frontend Template System - Setup & Verification Checklist

## Quick Setup Verification

### ✅ Installation Checklist

- [ ] Plugin files uploaded correctly
- [ ] `templates/` directory exists in plugin folder
- [ ] `includes/class-x402-template-handler.php` exists
- [ ] `includes/class-x402-template-functions.php` exists
- [ ] `includes/class-x402-shortcodes.php` exists
- [ ] `assets/css/templates.css` exists
- [ ] No PHP errors in debug log

### ✅ Template System Verification

**Test 1: Default Templates Work**
- [ ] Create a test post
- [ ] Enable X402 paywall on the post
- [ ] View the post (logged out)
- [ ] See styled paywall page (not raw text)
- [ ] Header and footer from your theme appear
- [ ] Payment form displays correctly

**Test 2: Template Override Works**
- [ ] Create directory: `your-theme/x402/`
- [ ] Copy `payment-required.php` from plugin to theme
- [ ] Add comment `<!-- CUSTOM TEMPLATE -->` at top
- [ ] View paywall page
- [ ] See your comment in page source (View Source in browser)
- [ ] ✅ Override working!

**Test 3: Hooks Work**
- [ ] Add to `functions.php`:
```php
add_action('x402_paywall_footer', function() {
    echo '<!-- CUSTOM HOOK WORKS -->';
});
```
- [ ] View paywall page source
- [ ] Find comment in HTML
- [ ] ✅ Hooks working!

**Test 4: CSS Variables Work**
- [ ] Add to Customizer Additional CSS:
```css
:root {
    --x402-primary-color: #ff0000;
}
```
- [ ] View paywall page
- [ ] Button should be red
- [ ] ✅ CSS variables working!

**Test 5: Shortcodes Work**
- [ ] Create a new page
- [ ] Add shortcode: `[x402_payment_button text="Test Button"]`
- [ ] Publish and view page
- [ ] See styled button
- [ ] ✅ Shortcodes working!

### ✅ Responsive Design Verification

- [ ] Test on desktop (1920px+)
- [ ] Test on laptop (1366px)
- [ ] Test on tablet (768px)
- [ ] Test on mobile (375px)
- [ ] All breakpoints look good
- [ ] Text is readable
- [ ] Buttons are tappable
- [ ] Forms are usable

### ✅ Accessibility Verification

- [ ] Tab through page with keyboard only
- [ ] All interactive elements focusable
- [ ] Focus indicators visible
- [ ] Screen reader can read content (test with NVDA/JAWS/VoiceOver)
- [ ] Color contrast meets WCAG AA
- [ ] Form labels properly associated
- [ ] ARIA labels present where needed

### ✅ Browser Compatibility

- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

### ✅ Theme Compatibility

- [ ] Works with your active theme
- [ ] Header displays correctly
- [ ] Footer displays correctly
- [ ] Content area properly sized
- [ ] No style conflicts
- [ ] Theme colors not conflicting

## Customization Checklist

### Level 1: Basic Styling (CSS Only)

- [ ] Identify colors to change
- [ ] Find CSS variables in `TEMPLATE-QUICK-REFERENCE.md`
- [ ] Add custom CSS via Customizer
- [ ] Test changes
- [ ] Adjust as needed

**Common Changes:**
```css
:root {
    /* Brand colors */
    --x402-primary-color: #your-color;
    --x402-primary-hover: #your-hover-color;
    
    /* Spacing */
    --x402-padding: 2rem;
    --x402-border-radius: 8px;
    
    /* Typography */
    --x402-h1-size: 2rem;
    --x402-text-size: 1rem;
}
```

### Level 2: Content Addition (Hooks)

- [ ] Identify where to add content
- [ ] Find appropriate hook in docs
- [ ] Add function to `functions.php`
- [ ] Test hook fires
- [ ] Adjust priority if needed

**Example:**
```php
add_action('x402_paywall_footer', function($data) {
    echo '<div class="my-content">Custom HTML</div>';
}, 20); // Priority 20
```

### Level 3: Template Override

- [ ] Decide which template to override
- [ ] Create `your-theme/x402/` directory
- [ ] Copy template from plugin
- [ ] Modify template HTML
- [ ] Test override works
- [ ] Make incremental changes

### Level 4: Full Customization

- [ ] Disable default styles if needed
- [ ] Create custom CSS file
- [ ] Override all necessary templates
- [ ] Add custom JavaScript
- [ ] Test extensively

## Troubleshooting Checklist

### Templates Not Loading?

- [ ] Check file permissions (644 for files, 755 for directories)
- [ ] Verify file path: `yourtheme/x402/template-name.php`
- [ ] Check for PHP errors in debug log
- [ ] Ensure `get_header()` and `get_footer()` work in theme
- [ ] Try different theme to isolate issue

### Styles Not Applying?

- [ ] Check if custom CSS is enqueued
- [ ] Verify CSS file loads (check browser Network tab)
- [ ] Check CSS specificity (use browser DevTools)
- [ ] Clear cache (browser, plugin, server)
- [ ] Check if theme overrides variables

### Hooks Not Firing?

- [ ] Verify hook name spelling
- [ ] Check function is added to `functions.php`
- [ ] Ensure priority is set correctly
- [ ] Check if default function was removed
- [ ] Add debug output to verify

**Debug example:**
```php
add_action('x402_paywall_footer', function() {
    error_log('X402: Hook fired!'); // Check debug.log
    echo '<!-- Hook works -->';    // Check page source
});
```

### Shortcodes Not Working?

- [ ] Check shortcode syntax (no spaces in attributes)
- [ ] Verify shortcode name spelling
- [ ] Test with simple example first
- [ ] Check if content is being filtered
- [ ] View page source to see if shortcode was processed

### JavaScript Issues?

- [ ] Open browser console (F12)
- [ ] Check for JavaScript errors
- [ ] Verify jQuery is loaded
- [ ] Check if `x402_vars` object exists
- [ ] Test with browser DevTools

### Mobile Issues?

- [ ] Test in real devices, not just emulators
- [ ] Check viewport meta tag
- [ ] Verify responsive CSS
- [ ] Test touch interactions
- [ ] Check mobile browser console

## Performance Checklist

- [ ] CSS file size reasonable (< 50KB)
- [ ] JavaScript file size reasonable (< 100KB)
- [ ] Images optimized
- [ ] No unnecessary HTTP requests
- [ ] Page loads in < 3 seconds
- [ ] Lighthouse score > 90

## Security Checklist

- [ ] All output is escaped (`esc_html`, `esc_url`, etc.)
- [ ] All input is sanitized
- [ ] Nonces verified for forms
- [ ] No inline JavaScript with user data
- [ ] HTTPS enforced for payment pages
- [ ] No sensitive data in HTML comments

## Documentation Checklist

- [ ] Read `TEMPLATE-CUSTOMIZATION.md`
- [ ] Review `CUSTOMIZATION-EXAMPLES.md`
- [ ] Keep `TEMPLATE-QUICK-REFERENCE.md` handy
- [ ] Check `TEMPLATE-ARCHITECTURE.md` for structure
- [ ] Document your customizations

## Pre-Launch Checklist

### Must Test

- [ ] Complete payment flow works
- [ ] Success page displays correctly
- [ ] Error page displays correctly
- [ ] Wallet address copies correctly
- [ ] QR codes display (if used)
- [ ] Token selection works (if enabled)
- [ ] Price conversion accurate
- [ ] Mobile experience smooth
- [ ] All links work
- [ ] Forms submit properly

### Recommended Tests

- [ ] Test with real payment
- [ ] Test with different browsers
- [ ] Test with different devices
- [ ] Test with slow internet
- [ ] Test with ad blockers
- [ ] Test with privacy extensions
- [ ] Test accessibility with screen reader
- [ ] Get feedback from beta users

### Final Checks

- [ ] Remove debug code
- [ ] Remove console.log statements
- [ ] Clean up test content
- [ ] Optimize images
- [ ] Minify custom CSS/JS (if large)
- [ ] Test on production
- [ ] Create backup
- [ ] Monitor error logs

## Maintenance Checklist

### Regular (Monthly)

- [ ] Check for plugin updates
- [ ] Review error logs
- [ ] Test payment flow still works
- [ ] Check template overrides still compatible
- [ ] Review customizations

### After Plugin Updates

- [ ] Test payment flow
- [ ] Check if templates changed
- [ ] Update template overrides if needed
- [ ] Test customizations still work
- [ ] Review changelog

### After Theme Updates

- [ ] Test template system
- [ ] Check style compatibility
- [ ] Verify header/footer integration
- [ ] Test responsive design
- [ ] Update customizations if needed

## Getting Help

### Before Asking for Help

- [ ] Check documentation thoroughly
- [ ] Search GitHub issues
- [ ] Test with default theme (Twenty Twenty-Four)
- [ ] Disable other plugins temporarily
- [ ] Check browser console for errors
- [ ] Check PHP error log
- [ ] Try reproducing on clean install

### Information to Provide

- [ ] WordPress version
- [ ] PHP version
- [ ] Theme name and version
- [ ] Plugin version
- [ ] Error messages (exact text)
- [ ] Steps to reproduce
- [ ] Expected vs actual behavior
- [ ] Browser and OS
- [ ] Screenshots (if visual issue)

### Where to Get Help

- **Documentation:** Read all .md files in plugin directory
- **GitHub Issues:** https://github.com/mondb-dev/x402-wp/issues
- **Support Forum:** WordPress.org plugin support
- **Community:** Discord/Slack (if available)

## Success Criteria

✅ **You're good to go if:**

1. Payment pages load without errors
2. Styling matches your site design
3. All interactions work (buttons, forms, etc.)
4. Mobile experience is smooth
5. Accessibility is good
6. Performance is acceptable
7. No PHP/JS errors in logs
8. You understand how to make changes
9. You have backups
10. You've tested real payments

---

**Ready to Launch? 🚀**

If all checkboxes are checked, your X402 frontend template system is ready for production!

