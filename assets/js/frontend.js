/**
 * Frontend JavaScript for X402 Solana Paywall
 * Handles payment verification and UI interactions
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize payment form
        initPaymentForm();
    });
    
    /**
     * Initialize payment form
     */
    function initPaymentForm() {
        $('#x402-verify-payment').on('click', function(e) {
            e.preventDefault();
            verifyPayment();
        });
        
        // Allow Enter key to submit
        $('#x402-wallet-address, #x402-transaction-signature').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                verifyPayment();
            }
        });
    }
    
    /**
     * Verify payment
     */
    function verifyPayment() {
        var $button = $('#x402-verify-payment');
        var $status = $('.x402-status-message');
        var $walletInput = $('#x402-wallet-address');
        var $signatureInput = $('#x402-transaction-signature');
        
        // Get values
        var walletAddress = $walletInput.val().trim();
        var signature = $signatureInput.val().trim();
        var postId = x402_ajax.post_id;
        
        // Validate inputs
        if (!walletAddress) {
            showError(getMessage('wallet_required'));
            $walletInput.focus();
            return;
        }

        if (!signature) {
            showError(getMessage('signature_required'));
            $signatureInput.focus();
            return;
        }

        // Disable button and show loading
        $button.prop('disabled', true).text(getMessage('verifying'));
        $status.hide();
        
        // Send AJAX request
        $.ajax({
            url: x402_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'x402_verify_payment',
                nonce: x402_ajax.nonce,
                post_id: postId,
                wallet_address: walletAddress,
                signature: signature
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    // Reload page after short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showError(response.data.message || getMessage('generic_error'));
                    $button.prop('disabled', false).text(getMessage('verify'));
                }
            },
            error: function(xhr, status, error) {
                var message = getMessage('generic_error');

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                } else if (xhr.status === 429) {
                    message = getMessage('rate_limited');
                }

                showError(message);
                $button.prop('disabled', false).text(getMessage('verify'));
            }
        });
    }
    
    /**
     * Show success message
     */
    function showSuccess(message) {
        var $status = $('.x402-status-message');
        $status
            .removeClass('x402-error')
            .addClass('x402-success')
            .html('<span class="x402-icon">✓</span> ' + escapeHtml(message))
            .fadeIn();
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        var $status = $('.x402-status-message');
        $status
            .removeClass('x402-success')
            .addClass('x402-error')
            .html('<span class="x402-icon">✗</span> ' + escapeHtml(message))
            .fadeIn();
    }

    /**
     * Retrieve a localized message with a fallback key
     */
    function getMessage(key) {
        if (typeof x402_ajax !== 'undefined' && x402_ajax.messages && x402_ajax.messages[key]) {
            return x402_ajax.messages[key];
        }

        return key;
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
})(jQuery);
