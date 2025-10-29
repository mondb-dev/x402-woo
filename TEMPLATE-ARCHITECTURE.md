# X402 Template System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     WORDPRESS REQUEST                            │
│                  (User visits payment page)                      │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│              X402_Template_Handler::template_loader()            │
│  - Detects payment page types (required/success/failed)         │
│  - Locates appropriate template                                 │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
                  ┌────────┴────────┐
                  │  Template Path  │
                  │   Resolution    │
                  └────────┬────────┘
                           │
        ┌──────────────────┼──────────────────┐
        ▼                  ▼                  ▼
┌───────────────┐  ┌───────────────┐  ┌──────────────┐
│ Theme Path    │  │ Theme Root    │  │ Plugin Path  │
│ yourtheme/    │  │ yourtheme/    │  │ x402-wp/     │
│ x402/         │  │               │  │ templates/   │
│ template.php  │  │ template.php  │  │ template.php │
│ (1st check)   │  │ (2nd check)   │  │ (fallback)   │
└───────┬───────┘  └───────┬───────┘  └──────┬───────┘
        │                  │                  │
        └──────────────────┼──────────────────┘
                           │ Template found!
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                      TEMPLATE LOADS                              │
│  - Calls get_header()                                           │
│  - Fires 'before' action hooks                                  │
│  - Renders content with hooks                                   │
│  - Fires 'after' action hooks                                   │
│  - Calls get_footer()                                           │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                       HOOK SYSTEM                                │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Action Hooks (50+)                                   │      │
│  │ • x402_before_paywall_content                        │      │
│  │ • x402_paywall_header ──► Default functions         │      │
│  │ • x402_paywall_content ──► Can be overridden        │      │
│  │ • x402_paywall_footer ──► Or removed entirely       │      │
│  │ • x402_after_paywall_content                         │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Filter Hooks (15+)                                   │      │
│  │ • x402_paywall_data → Modify configuration          │      │
│  │ • x402_paywall_title → Change text                  │      │
│  │ • x402_success_message → Customize messages         │      │
│  │ • x402_load_default_styles → Toggle CSS             │      │
│  └──────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                    STYLING SYSTEM                                │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ CSS Priority (Cascade)                               │      │
│  │ 1. User's custom CSS (highest)                       │      │
│  │ 2. Theme's style.css                                 │      │
│  │ 3. X402 templates.css (with CSS variables)          │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ CSS Variables (30+)                                  │      │
│  │ --x402-primary-color ────────────┐                  │      │
│  │ --x402-bg-color                  │ Easy to override │      │
│  │ --x402-border-radius             │ in theme CSS     │      │
│  │ --x402-padding ──────────────────┘                  │      │
│  └──────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                  JAVASCRIPT ENHANCEMENT                          │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Frontend Interactions                                │      │
│  │ • Copy wallet address                                │      │
│  │ • Connect wallet                                     │      │
│  │ • Verify payment                                     │      │
│  │ • Update token amounts                               │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Custom Events (dispatched)                           │      │
│  │ • x402:payment:initiated                             │      │
│  │ • x402:payment:success                               │      │
│  │ • x402:payment:failed                                │      │
│  │ • x402:wallet:connected                              │      │
│  └──────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     RENDERED PAGE                                │
│  • Theme header                                                 │
│  • Paywall content (customized via hooks)                       │
│  • Payment form (with all interactions)                         │
│  • Theme footer                                                 │
│  • Fully styled and interactive                                 │
└─────────────────────────────────────────────────────────────────┘


═══════════════════════════════════════════════════════════════════
                    CUSTOMIZATION FLOW DIAGRAM
═══════════════════════════════════════════════════════════════════

LEVEL 1: CSS Variables Only
────────────────────────────────────────
Input:  :root { --x402-primary-color: #ff0000; }
Action: CSS variables override
Result: ✅ Colors/spacing changed
Time:   5 minutes

LEVEL 2: Hook Injection
────────────────────────────────────────
Input:  add_action('x402_paywall_footer', 'my_func');
Action: Function attached to hook
Result: ✅ Custom content added
Time:   15-30 minutes

LEVEL 3: Template Override
────────────────────────────────────────
Input:  Copy template to yourtheme/x402/
Action: Theme template takes priority
Result: ✅ Complete layout control
Time:   1-2 hours

LEVEL 4: Full Custom System
────────────────────────────────────────
Input:  Custom templates + CSS + JS
Action: Disable defaults, use custom
Result: ✅ Completely unique design
Time:   3-4 hours


═══════════════════════════════════════════════════════════════════
                        DATA FLOW DIAGRAM
═══════════════════════════════════════════════════════════════════

Post Meta (Database)
  ↓
  ├─ _x402_paywall_enabled
  ├─ _x402_payment_amount
  ├─ _x402_payment_currency
  ├─ _x402_wallet_address
  └─ _x402_network
       ↓
X402_Template_Handler::get_paywall_data()
       ↓
  apply_filters('x402_paywall_data', $data)  ← Theme can modify
       ↓
$paywall_data array
       ↓
       ├─→ Passed to all hooks
       ├─→ Used in templates
       └─→ Rendered in HTML


═══════════════════════════════════════════════════════════════════
                      SHORTCODE INTEGRATION
═══════════════════════════════════════════════════════════════════

Post/Page Content
  ↓
[x402_paywall amount="10" currency="USD"]
  ↓
WordPress Shortcode Parser
  ↓
X402_Shortcodes::paywall_shortcode()
  ↓
  ├─→ Check user access
  ├─→ Build paywall data
  ├─→ Load template part
  └─→ Return HTML
       ↓
Rendered inline in content


═══════════════════════════════════════════════════════════════════
                    COMPONENT STRUCTURE
═══════════════════════════════════════════════════════════════════

payment-required.php (Main Template)
│
├── get_header() ─────────────→ Theme's header.php
│
├── x402_before_paywall_content ─→ Hook point
│
├── x402-paywall-container
│   │
│   ├── x402_before_paywall ────→ Hook point
│   │
│   ├── x402-paywall-wrapper
│   │   │
│   │   ├── x402_paywall_header ───→ Hook: Title, Description
│   │   │
│   │   ├── x402-paywall-content
│   │   │   │
│   │   │   ├── x402_paywall_content ──→ Hook: Form, Preview, Info
│   │   │   │   │
│   │   │   │   └── payment-form.php (Template Part)
│   │   │   │       │
│   │   │   │       ├── x402_payment_amount_display ──→ Hook
│   │   │   │       ├── x402_payment_wallet_display ──→ Hook
│   │   │   │       └── x402_payment_actions ──────────→ Hook
│   │   │   │
│   │   │
│   │   └── x402_paywall_footer ────→ Hook: Security, Support
│   │
│   └── x402_after_paywall ─────→ Hook point
│
├── x402_after_paywall_content ─→ Hook point
│
└── get_footer() ──────────────→ Theme's footer.php


═══════════════════════════════════════════════════════════════════
                        FILE DEPENDENCIES
═══════════════════════════════════════════════════════════════════

x402-solana-paywall.php
  ↓ requires
  ├── class-x402-template-handler.php
  │     ↓ uses
  │     ├── class-x402-content-protection.php (checks access)
  │     └── templates/*.php (loads templates)
  │
  ├── class-x402-template-functions.php
  │     ↓ hooks into
  │     └── template action hooks (provides defaults)
  │
  └── class-x402-shortcodes.php
        ↓ uses
        ├── class-x402-template-handler.php
        ├── class-x402-content-protection.php
        └── class-x402-token-handler.php


═══════════════════════════════════════════════════════════════════
                     THEME COMPATIBILITY
═══════════════════════════════════════════════════════════════════

┌──────────────────┐
│   Any WP Theme   │
└────────┬─────────┘
         │
         ├─→ Block Theme (FSE)
         │   └─→ Uses get_header()/get_footer() ✅
         │
         ├─→ Classic Theme
         │   └─→ Uses get_header()/get_footer() ✅
         │
         ├─→ Page Builder Theme
         │   ├─→ Elementor: Works in canvas mode ✅
         │   ├─→ Divi: Works in blank template ✅
         │   └─→ Beaver: Works ✅
         │
         └─→ Custom Theme
             └─→ Override templates in theme/x402/ ✅


═══════════════════════════════════════════════════════════════════
                    CUSTOMIZATION DECISION TREE
═══════════════════════════════════════════════════════════════════

Need to customize?
  │
  ├─→ Just colors/spacing?
  │   └─→ Use CSS variables ✅
  │
  ├─→ Add/remove content?
  │   └─→ Use action hooks ✅
  │
  ├─→ Change text?
  │   └─→ Use filter hooks ✅
  │
  ├─→ Rearrange layout?
  │   └─→ Override template file ✅
  │
  ├─→ Different interaction?
  │   └─→ Add custom JavaScript ✅
  │
  └─→ Completely different?
      └─→ Disable defaults + build custom ✅


═══════════════════════════════════════════════════════════════════
```

**Legend:**
- `→` Data/control flow
- `├─` Tree branch
- `└─` Tree end
- `↓` Vertical flow
- `✅` Supported/Working

