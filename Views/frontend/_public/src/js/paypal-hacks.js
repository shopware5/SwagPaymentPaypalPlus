(function ($, undefined) {
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
                    var approvalUrlCt;

                    me.$el.empty().html(res);
                    me.$el.find('input[type="submit"][form], button[form]').swFormPolyfill();
                    $.loadingIndicator.close(function () {
                    });
                    window.picturefill();

                    approvalUrlCt = me.$el.find('.pp--approval-url');

                    if (approvalUrlCt) {
                        paymentWall($, approvalUrlCt.text());
                    } else {
                        paymentWall($);
                    }

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
        var isClick = function () {
                return events.indexOf('loaded') == -1;
            },
            handleEvents = function () {
                var $paypalPlusContainer = $('#ppplus'),
                    paypalPaymentId = $paypalPlusContainer.attr('data-paypal-payment-id'),
                    payPalCheckBox = $("#payment_mean" + paypalPaymentId);

                if (payPalCheckBox.prop('checked')) {
                    $.ajax({
                        type: 'POST',
                        url: $paypalPlusContainer.attr('data-paypal-save-in-session-url'),
                        data: {
                            cameFromStep2: true
                        }
                    });
                } else if (isClick()) {
                    payPalCheckBox.prop('checked', true);
                    $('*[data-ajax-shipping-payment="true"]').data('plugin_swShippingPayment').onInputChanged();
                }
                events = [];
            },
            events = [],
            timeOut;

        window.addEventListener('message', function (event) {
            var $paypalPlusContainer = $('#ppplus'),
                paypalSandbox = $paypalPlusContainer.attr('data-paypal-sandbox'),
                originUrl = paypalSandbox == 'true' ? "https://www.sandbox.paypal.com" : 'https://www.paypal.com',
                isConfirmAction = $('.is--act-confirm').length > 0;

            if (isConfirmAction) {
                return false;
            }

            if (event.origin !== originUrl) {
                return false;
            }

            if (timeOut !== undefined) {
                clearTimeout(timeOut);
            }

            var data = JSON.parse(event.data);

            events.push(data.action);
            //wait until all events are fired
            timeOut = setTimeout(function () {
                handleEvents();
            }, 500);
        }, false);
    });
})(jQuery);
