{block name="frontend_checkout_payment_paypalplus_checkoutonly"}
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        function prepareCheckout($) {
            var $agb = $('#sAGB'),
                urlForSendingCustomerData = '{url controller=checkout action=preRedirect forceSecure}',
                onConfirm = function (event) {
                    if (!$agb.prop('checked')) {
                        return;
                    }

                    event.preventDefault();

                    $.ajax({
                        type: "POST",
                        url: urlForSendingCustomerData,
                        success: function (result) {
                            var resultObject = $.parseJSON(result);

                            if (resultObject.success) {
                                PAYPAL.apps.PPP.doCheckout();
                            }
                        }
                    });
                };

            $('#confirm--form').on('submit', onConfirm);
        }

        jQuery(document).ready(function ($) {
            prepareCheckout($);
        });
    </script>
{/block}
