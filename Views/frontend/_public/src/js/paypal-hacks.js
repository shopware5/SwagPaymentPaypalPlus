(function ($) {
    /**
     * this override is necessary to pass an empty function to
     * $.loadingIndicator.close() to prevent a JS error in SW 5.0.x
     *
     * additionally the PayPal Payment Wall has to be called after AJAX request
     */
    $.overridePlugin('swShippingPayment', {
        onInputChanged: function () {
            var me = this,
                form = me.$el.find(me.opts.formSelector),
                url = form.attr('action'),
                data = form.serialize() + '&isXHR=1';

            $.publish('plugin/swShippingPayment/onInputChangedBefore', me);

            $.loadingIndicator.open();

            $.ajax({
                type: "POST",
                url: url,
                data: data,
                success: function (res) {
                    me.$el.empty().html(res);
                    me.$el.find('input[type="submit"][form], button[form]').swFormPolyfill();
                    $.loadingIndicator.close(function () {
                    });
                    window.picturefill();
                    paymentWall($);

                    $.publish('plugin/swShippingPayment/onInputChanged', me);
                }
            })
        }
    });

    /**
     * listens to message events fired by the PayPal PaymentWall iframe
     *
     * makes AJAX call to save the paypalplus_session cookie value in the session
     * sets the current payment method to paypal if clicked in the iframe
     */
    $(function () {
        window.addEventListener('message', function (event) {
            var data = JSON.parse(event.data),
                $paypalPlusContainer = $('#ppplus'),
                paypalPaymentId = $paypalPlusContainer.attr('data-paypal-payment-id'),
                paypalSandbox = $paypalPlusContainer.attr('data-paypal-sandbox'),
                payPalCheckBox = $("#payment_mean" + paypalPaymentId),
                originUrl = 'https://www.paypal.com',
                isConfirmAction = $('.is--act-confirm').length > 0;

            if (isConfirmAction) {
                return false;
            }

            if (paypalSandbox == 'true') {
                originUrl = 'https://www.sandbox.paypal.com';
            }

            if (event.origin !== originUrl) {
                return false;
            }

            if (payPalCheckBox.prop('checked')) {
                if (data.action == 'resizeHeightOfTheIframe') {
                    $.ajax({
                        type: 'POST',
                        url: $paypalPlusContainer.attr('data-paypal-cookie-url'),
                        data: {
                            cookies: document.cookie,
                            cameFromStep2: true
                        }
                    });
                    return false;
                }
                return false;
            } else if (data.action == 'enableContinueButton') {
                payPalCheckBox.prop('checked', true);
                $('*[data-ajax-shipping-payment="true"]').data('plugin_swShippingPayment').onInputChanged();
            } else {
                return false;
            }
        }, false);
    });
})(jQuery);
