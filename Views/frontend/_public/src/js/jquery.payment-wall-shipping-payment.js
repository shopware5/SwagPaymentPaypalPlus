(function ($, undefined) {
    /**
     * event listener which will be triggered if the customer changes their shipping or payment method
     * to call the PayPal payment wall after AJAX request
     */
    $.subscribe('plugin/swShippingPayment/onInputChanged', function (event, plugin) {
        var approvalUrl = plugin.$el.find('.pp--approval-url');

        if (approvalUrl) {
            paymentWall($, approvalUrl.text());
            return;
        }

        paymentWall($);
    });

    /**
     * listens to message events fired by the PayPal payment wall iFrame
     *
     * makes AJAX call to save the PayPalPlus_session cookie value in the session
     * sets the current payment method to paypal if clicked in the iFrame
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
