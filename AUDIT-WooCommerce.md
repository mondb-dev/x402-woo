# X402 WooCommerce Gateway Audit

## Summary
- **Scope**: `includes/class-x402-woocommerce-gateway.php`, supporting utilities (`includes/class-x402-logger.php`, `x402-solana-paywall.php`, `assets/js/frontend.js`).
- **Focus areas**: runtime stability, security posture, administrative UX, and payment verification flows.

## Critical issues
1. **Fatal error in checkout renderer**  
   `payment_fields()` calls an undefined helper `wptautokses_post()`. PHP fatals when WooCommerce renders the gateway form, blocking checkout for every store that enables the method.【F:includes/class-x402-woocommerce-gateway.php†L892-L905】  
   _Remediation_: Replace the typo with `wp_kses_post()` (or equivalent sanitization) before wrapping in `wpautop()`.

2. **Dynamic logger invocation crashes**  
   `X402_Logger::log_facilitator_response()` attempts to call `self::$level(...)`. Because `$level` is a string, PHP interprets this as a static property reference and throws a fatal error before any facilitator response can be recorded.【F:includes/class-x402-logger.php†L169-L175】  
   _Remediation_: Resolve via `call_user_func(array(__CLASS__, $level), ...)` or an explicit `if/else` dispatch to `self::info()` / `self::error()`.

## High issues
3. **Admin validation runs after options are stored**  
   Both `process_admin_options()` and `validate_admin_options()` are bound to the same WooCommerce hook with default priority. The save routine executes first, persists whatever was submitted, and only then adds validation errors while still returning success.【F:includes/class-x402-woocommerce-gateway.php†L28-L106】  
   _Risk_: Invalid pay-to, asset, or facilitator URLs remain saved, undermining subsequent runtime checks. In turn, `maybe_create_facilitator()` can pass unsanitized custom URLs straight into the facilitator client when validation should have prevented this.【F:includes/class-x402-woocommerce-gateway.php†L682-L698】  
   _Remediation_: Implement `validate_fields()`/`validate_*` accessors or move the validation logic into field-specific callbacks so WooCommerce blocks the save before persisting.

4. **Frontend AJAX contracts are inconsistent**  
   The localized script exports `verify_nonce`/`status_nonce`, yet the JavaScript expects `x402_ajax.nonce`, and separate routines reference a non-existent `x402_vars` object for the same values. Manual verification requests therefore omit the nonce entirely, leading to 403 responses, and price conversions rely on `x402_ajax.price_nonce` while the backend still checks for `x402_payment_nonce` in other paths.【F:x402-solana-paywall.php†L247-L274】【F:assets/js/frontend.js†L167-L219】【F:assets/js/frontend.js†L299-L337】  
   _Risk_: Payment verification UX is effectively broken and weakens nonce-based CSRF protection.  
   _Remediation_: Align PHP localization keys with the JavaScript contract (or vice versa) and ensure every AJAX handler verifies the matching nonce action.

## Medium issues
5. **Custom facilitator endpoint bypasses URL sanitization**  
   When the “Custom” facilitator mode is selected, the stored endpoint is passed raw to `FacilitatorClient::selfHosted()` without `esc_url_raw()` or format checks, so malformed URLs survive even when validation should have failed.【F:includes/class-x402-woocommerce-gateway.php†L687-L713】  
   _Remediation_: Reuse the same sanitizer as `get_facilitator_endpoint()` or call `X402_Crypto_Validator::validate_facilitator_url()` before instantiation.

6. **REST exposure relies solely on WooCommerce order keys**  
   The `/x402/v1/orders/<id>` route is world-readable (`permission_callback => __return_true`) and only gates access by comparing the supplied `key` parameter with the order key.【F:includes/class-x402-api.php†L63-L133】  
   _Risk_: Anyone with a leaked order link can continually pull fresh payment payloads. Consider scoping the permission callback (e.g., to the order owner or authenticated facilitators) or rate-limiting/expiring the shared token.

## Low issues & observations
- Repeated logging sanitization is thorough, but consider redacting wallet addresses before storing in WooCommerce order meta to avoid exposing PII in exports.【F:includes/class-x402-woocommerce-gateway.php†L562-L612】
- Scheduled cleanup hooks (`x402_cleanup_*`) are registered globally, yet uninstall routines do not unschedule them—worth confirming deactivation cleans up cron entries.【F:x402-solana-paywall.php†L101-L150】

## Suggested next steps
1. Fix the fatal runtime regressions (items 1 & 2) before shipping any WooCommerce release.
2. Rework admin validation to happen pre-save and tighten facilitator endpoint sanitization.
3. Audit every AJAX/REST contract, ensuring localized data, nonce actions, and server checks align.
4. Revisit REST exposure and logging practices for least-privilege access and data minimization.
