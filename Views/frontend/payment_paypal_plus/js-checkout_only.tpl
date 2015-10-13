{block name="frontend_checkout_payment_paypalplus_checkoutonly"}
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        function prepareCheckout($) {
            var $agb = $('#sAGB'),
                onConfirm = function () {
                    if (!$agb.length || $agb.attr('checked') || $agb[0].checked) {
                        PAYPAL.apps.PPP.doCheckout();
                        return false;
                    }
                    return true;
                };

            $('#confirm--form').on('submit', onConfirm);
        }

        jQuery(document).ready(function ($) {
            prepareCheckout($);
        });
    </script>
{/block}
