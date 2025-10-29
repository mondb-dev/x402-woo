/**
 * Admin JavaScript for X402 Solana Paywall
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize admin functionality
        initAdminFeatures();
    });
    
    /**
     * Initialize admin features
     */
    function initAdminFeatures() {
        // Toggle payment amount field based on paywall enabled checkbox
        $('input[name="x402_paywall_enabled"]').on('change', function() {
            var $amountField = $('#x402_payment_amount');
            if ($(this).is(':checked')) {
                $amountField.prop('disabled', false).focus();
            } else {
                $amountField.prop('disabled', true);
            }
        }).trigger('change');
        
        // Initialize custom token fields toggle
        initCustomTokenFields();
        
        // Auto-detect network from CA
        initCADetection();
    }
    
    /**
     * Initialize custom token fields visibility
     */
    function initCustomTokenFields() {
        var $useCustomCheckbox = $('#woocommerce_x402_use_custom_token');
        var $customFields = $(
            '#woocommerce_x402_custom_token_ca, ' +
            '#woocommerce_x402_custom_token_symbol, ' +
            '#woocommerce_x402_custom_token_name, ' +
            '#woocommerce_x402_custom_token_decimals, ' +
            '#woocommerce_x402_custom_token_price, ' +
            '#woocommerce_x402_custom_token_coingecko_id'
        ).closest('tr');
        
        function toggleCustomFields() {
            if ($useCustomCheckbox.is(':checked')) {
                $customFields.show();
            } else {
                $customFields.hide();
            }
        }
        
        $useCustomCheckbox.on('change', toggleCustomFields);
        toggleCustomFields();
    }
    
    /**
     * Auto-detect network type from contract address
     */
    function initCADetection() {
        var $caField = $('#woocommerce_x402_custom_token_ca');
        var $networkHint = $('<div class="x402-custom-token-hint"></div>');
        
        $caField.after($networkHint);
        
        $caField.on('input', function() {
            var ca = $(this).val().trim();
            
            if (!ca) {
                $networkHint.text('');
                return;
            }
            
            // Detect address type
            if (ca.startsWith('0x') && ca.length === 42) {
                $networkHint.html('✓ Valid EVM address detected (Ethereum/Base/Polygon)');
                $networkHint.css('color', '#46b450');
            } else if (/^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(ca)) {
                $networkHint.html('✓ Valid Solana address detected (SPL token)');
                $networkHint.css('color', '#46b450');
            } else {
                $networkHint.html('⚠ Invalid address format. Must be EVM (0x...) or Solana (base58)');
                $networkHint.css('color', '#dc3232');
            }
        });
        
        // Trigger on load if there's already a value
        $caField.trigger('input');
    }
    
})(jQuery);
