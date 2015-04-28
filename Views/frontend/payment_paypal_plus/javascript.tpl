{if $PaypalPlusApprovalUrl}
    <script type="text/javascript">
        var jQuery_SW = $.noConflict(true);
    </script>
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        var ppp; disable = false; ppp = PAYPAL.apps.PPP({
            approvalUrl: "{$PaypalPlusApprovalUrl|escape:javascript}",
            placeholder: "ppplus",
            mode: "{if $PaypalPlusModeSandbox}sandbox{else}live{/if}",
            buttonLocation: "outside",
            useraction: "commit",
            country: '{$sUserData.additional.country.countryiso}',
            language: '{$PaypalLocale}',
            disableContinue: function() {
                if(disable) { // Fix preselection issue
                    var changeMethodForm = $('#ppplusChangeForm'),
                            basketButton = $('#basketButton');
                    changeMethodForm.hide();
                    basketButton.attr('disabled', 'disabled').addClass('paypal_plus_disable_button');
                }
                disable = true;
            },
            enableContinue: function() {
                var selectedMethod = ppp.getPaymentMethod(),
                        changeMethodForm = $('#ppplusChangeForm'),
                        changeMethodInput = $('#ppplusChangeInput'),
                        changeRedirect = $('#ppplusRedirect'),
                        basketButton = $('#basketButton'),
                        config = this;
                changeMethodForm.hide();
                changeRedirect.val(0);
                if(selectedMethod.indexOf('pp-') === 0) {
                    basketButton.removeAttr('disabled').removeClass('paypal_plus_disable_button');
                } else {
                    basketButton.attr('disabled', 'disabled').addClass('paypal_plus_disable_button');
                }
                $.each(config.thirdPartyPaymentMethods, function( index, method ) {
                    if(method.methodName == selectedMethod) {
                        var redirect = method.redirectUrl.match(/\?redirect=1/),
                            paymentId = parseInt(method.redirectUrl.match(/[0-9]+($|\?)/), 10);
                        changeMethodInput.val(paymentId);
                        changeRedirect.val(redirect ? 1 : 0);
                        changeMethodForm.show();
                    }
                });
                disable = true;
            },
            //preselection: "{if $sUserData.additional.payment.name == 'paypal'}paypal{else}none{/if}",
            thirdPartyPaymentMethods: [{foreach from=$sPayments item=payment key=paymentKey}{if $payment.name != 'paypal' && isset($PaypalPlusThirdPartyPaymentMethods[$payment.id])}{
                "redirectUrl": "{url controller=account action=savePayment selectPaymentId=$payment.id}{if !empty($PaypalPlusThirdPartyPaymentMethods[$payment.id]['redirect'])}?redirect=1{/if}",
                "methodName": {$payment.description|unescape:entity|json_encode},
                "imageUrl": "{if !empty($PaypalPlusThirdPartyPaymentMethods[$payment.id]['media'])}{link file={$PaypalPlusThirdPartyPaymentMethods[$payment.id]['media']} fullPath}{/if}",
                "description": {$payment.additionaldescription|strip_tags|unescape:entity|trim|json_encode}
            }{if !$payment@last},{/if}{/if}{/foreach}]
        });
    </script>
    <script type="text/javascript">
        var jQuery = $ = jQuery_SW;
        $(document).ready(function($) {
            $('#basketButton').on('click', function () {
                var $agb = $('#sAGB');
                if (!$agb.length || $agb.attr('checked')) {
                    ppp.doCheckout();
                    return false;
                }
            });
            $('#shippingPaymentForm').submit(function (event) {
                ppp.doCheckout();
                event.preventDefault();
            });
        });
    </script>
{/if}