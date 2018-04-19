(function ($, window, undefined) {

    /**
     * prevent closing of the indicator on click by overwriting the default value
     */
    var hasLoadingIndicatorPrototype = window.hasOwnProperty('LoadingIndicator');

    $.subscribe('plugin/swShippingPayment/onInputChangedBefore', function () {
        if (hasLoadingIndicatorPrototype) {
            window.LoadingIndicator.prototype.defaults.closeOnClick = false;

            return;
        }

        $.loadingIndicator.defaults.closeOnClick = false;
    });

    /**
     * event listener which will be triggered if the customer changes their shipping or payment method
     * to call the PayPal payment wall after AJAX request
     */
    $.subscribe('plugin/swShippingPayment/onInputChanged', function (event, plugin) {
        var me = plugin,
            approvalUrl = me.$el.find('.pp--approval-url');

        if (typeof paymentWall !== 'function') {
            return false;
        }

        if (approvalUrl) {
            window.ppp = paymentWall($, approvalUrl.text());
        } else {
            window.ppp = paymentWall($);
        }
    });
})(jQuery, window);
