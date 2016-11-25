{block name="frontend_checkout_payment_paypalplus_checkoutonly"}
    <script type="text/javascript">
        var jQuery_SW = $.noConflict(true);
    </script>
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        var jQuery = $ = jQuery_SW;
        jQuery(document).ready(function($) {
            var $agb = $('#sAGB'),
                    urlForSendingCustomerData = '{url controller=checkout action=preRedirect forceSecure}',
                    onConfirm = function(event) {
                        if ($agb && $agb.length > 0 && !$agb.prop('checked')) {
                            return;
                        }

                        event.preventDefault();

                        $.ajax({
                            type: "POST",
                            url: urlForSendingCustomerData,
                            success: function(result) {
                                var resultObject = $.parseJSON(result);

                                if (resultObject.success) {
                                    PAYPAL.apps.PPP.doCheckout();
                                }
                            }
                        });
                    };

            $('#confirm--form').on('submit', onConfirm);
        });
    </script>
{/block}
