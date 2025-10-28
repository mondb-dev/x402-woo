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
    }
    
})(jQuery);
