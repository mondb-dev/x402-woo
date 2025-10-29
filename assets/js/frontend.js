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
        
        // Token selection handler
        $('#x402_token_select, select[name="x402_token"], .x402-token-select').on('change', function() {
            updateTokenAmount();
        });
        
        // Initialize token amount display
        if ($('#x402_token_select, select[name="x402_token"], .x402-token-select').length) {
            updateTokenAmount();
        }

        // Copy button handler
        initCopyButtons();

        // Connect wallet button handler
        initWalletButtons();

        // Manual verify button handler
        initVerifyButtons();
    }

    /**
     * Initialize copy buttons
     */
    function initCopyButtons() {
        $('.x402-copy-button').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var copyTarget = $btn.data('copy-target');
            var copyText = $btn.data('copy-text');
            
            var textToCopy = copyText || $(copyTarget).text();
            
            copyToClipboard(textToCopy);
            
            // Visual feedback
            var originalText = $btn.text();
            $btn.text('Copied!').addClass('copied');
            
            setTimeout(function() {
                $btn.text(originalText).removeClass('copied');
            }, 2000);
        });
    }

    /**
     * Initialize wallet connect buttons
     */
    function initWalletButtons() {
        $('.x402-connect-wallet').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var postId = $btn.data('post-id');
            
            // Show loading state
            showPaymentStatus('Connecting wallet...');
            
            // Trigger custom event for wallet integrations
            $(document).trigger('x402:payment:initiated', { postId: postId });
            
            // Here you would integrate with actual wallet providers
            connectWallet(postId);
        });
    }

    /**
     * Initialize verify buttons
     */
    function initVerifyButtons() {
        $('.x402-verify-payment').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var postId = $btn.data('post-id');
            
            showPaymentStatus('Verifying payment...');
            verifyManualPayment(postId);
        });
    }

    /**
     * Show payment status
     */
    function showPaymentStatus(message) {
        var $status = $('.x402-payment-status');
        if ($status.length) {
            $status.find('.x402-status-message').text(message);
            $status.fadeIn();
        }
    }

    /**
     * Hide payment status
     */
    function hidePaymentStatus() {
        $('.x402-payment-status').fadeOut();
    }

    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }
    }

    /**
     * Connect wallet (placeholder for wallet integration)
     */
    function connectWallet(postId) {
        // This would integrate with Phantom, MetaMask, etc.
        console.log('Connect wallet for post:', postId);
        
        // Placeholder - in real implementation, this would:
        // 1. Detect available wallets
        // 2. Request connection
        // 3. Get wallet address
        // 4. Initiate transaction
        // 5. Wait for confirmation
        // 6. Verify on backend
        
        setTimeout(function() {
            hidePaymentStatus();
            alert('Wallet integration coming soon. Please use manual payment verification.');
        }, 1000);
    }

    /**
     * Verify manual payment
     */
    function verifyManualPayment(postId) {
        // Trigger AJAX verification
        $.ajax({
            url: x402_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'x402_verify_payment',
                nonce: x402_vars.payment_nonce,
                post_id: postId
            },
            success: function(response) {
                hidePaymentStatus();
                if (response.success) {
                    $(document).trigger('x402:payment:success', response.data);
                    window.location.href = response.data.redirect_url;
                } else {
                    $(document).trigger('x402:payment:failed', { error: response.data.message });
                    alert(response.data.message || 'Payment verification failed');
                }
            },
            error: function() {
                hidePaymentStatus();
                alert('An error occurred. Please try again.');
            }
        });
    }

    /**
     * Update token amount display based on order total
     */
    function updateTokenAmount() {
        var $select = $('#x402_token_select, select[name="x402_token"]');
        var selectedToken = $select.val();
        var orderTotal = parseFloat($('input[name="order_total"]').val() || $('#order_total').text() || 0);
        var $amountDisplay = $('.x402-token-amount');
        var $priceDisplay = $('.x402-token-price');
        
        if (!selectedToken || !orderTotal) {
            return;
        }
        
        // Show loading state
        if ($amountDisplay.length) {
            $amountDisplay.html('<span class="x402-loading">Calculating...</span>');
        }
        
        $.ajax({
            url: x402_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'x402_get_token_amount',
                _wpnonce: x402_ajax.price_nonce,
                token: selectedToken,
                amount: orderTotal
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update amount display
                    if ($amountDisplay.length) {
                        $amountDisplay.html(
                            '<strong>' + data.token_amount + ' ' + data.symbol + '</strong>'
                        );
                    }
                    
                    // Update price display
                    if ($priceDisplay.length) {
                        $priceDisplay.text('1 ' + data.symbol + ' ≈ $' + (orderTotal / parseFloat(data.token_amount)).toFixed(2));
                    }
                    
                    // Update wallet address placeholder based on network
                    var $walletInput = $('#x402-wallet-address, input[name="x402_wallet_address"]');
                    if ($walletInput.length) {
                        if (data.network.indexOf('solana') !== -1) {
                            $walletInput.attr('placeholder', 'Solana wallet address (base58)');
                        } else {
                            $walletInput.attr('placeholder', 'EVM wallet address (0x...)');
                        }
                    }
                    
                    // Show token info for SPL tokens
                    if (data.token_info && data.token_info.mint && data.token_info.mint !== 'native') {
                        showTokenInfo(data.token_info);
                    }
                } else {
                    if ($amountDisplay.length) {
                        $amountDisplay.html('<span class="x402-error">Unable to calculate</span>');
                    }
                }
            },
            error: function() {
                if ($amountDisplay.length) {
                    $amountDisplay.html('<span class="x402-error">Error loading price</span>');
                }
            }
        });
    }

    /**
     * Show token information (for SPL tokens)
     */
    function showTokenInfo(tokenInfo) {
        var $info = $('.x402-token-info');
        
        if (!$info.length) {
            return;
        }
        
        var html = '<div class="x402-token-details">';
        
        if (tokenInfo.mint && tokenInfo.mint !== 'native') {
            html += '<div class="x402-mint-address">';
            html += '<label>Token Mint:</label> ';
            html += '<code>' + tokenInfo.mint.substring(0, 8) + '...' + tokenInfo.mint.substring(tokenInfo.mint.length - 8) + '</code>';
            html += '</div>';
        }
        
        if (tokenInfo.decimals) {
            html += '<div class="x402-decimals">';
            html += '<label>Decimals:</label> ' + tokenInfo.decimals;
            html += '</div>';
        }
        
        html += '</div>';
        
        $info.html(html).show();
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
