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
            showError('Please enter your wallet address');
            $walletInput.focus();
            return;
        }
        
        if (!signature) {
            showError('Please enter the transaction signature');
            $signatureInput.focus();
            return;
        }
        
        // Disable button and show loading
        $button.prop('disabled', true).text('Verifying...');
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
                    showError(response.data.message || 'Payment verification failed');
                    $button.prop('disabled', false).text('Verify Payment');
                }
            },
            error: function(xhr, status, error) {
                var message = 'An error occurred. Please try again.';
                
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                } else if (xhr.status === 429) {
                    message = 'Too many requests. Please wait a moment and try again.';
                }
                
                showError(message);
                $button.prop('disabled', false).text('Verify Payment');
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
