{if $PaypalPlusApprovalUrl}
    {$PayPalPlusContinue = "{s name='PaypalPlusLinkChangePayment'}Weiter{/s}"}
    <script type="text/javascript">
        var jQuery_SW = $.noConflict(true);
    </script>
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        var ppp, basketButton, bbFunction = 'val';
        var productTable;
        ppp = PAYPAL.apps.PPP({
            approvalUrl: "{$PaypalPlusApprovalUrl|escape:javascript}",
            placeholder: "ppplus",
            mode: "{if $PaypalPlusModeSandbox}sandbox{else}live{/if}",
            buttonLocation: "outside",
            useraction: "commit",
            country: '{$sUserData.additional.country.countryiso}',
            {if $PaypalLocale == 'de_DE' || $PaypalLocale == 'de_AT'}
                {$PaypalPlusLang = 'DE_de'}
            {elseif $PaypalLocale == 'en_US' || $PaypalLocale == 'en_GB'}
                {$PaypalPlusLang = 'US_en'}
            {else}
                {$PaypalPlusLang = $PaypalLocale}
            {/if}
            language: '{$PaypalPlusLang}',
            onThirdPartyPaymentMethodDeselected: function(e) {
                if (basketButton) {
                    basketButton[bbFunction](basketButton.data('orgValue'));
                }
                if (productTable) {
                    productTable.show();
                }
            },
            onThirdPartyPaymentMethodSelected: function(e) {
                if (basketButton) {
                    basketButton[bbFunction]({$PayPalPlusContinue|json_encode});
                }
                if (productTable) {
                    productTable.hide();
                }
            },
            //preselection: "{if $sUserData.additional.payment.name == 'paypal'}paypal{else}none{/if}",
            thirdPartyPaymentMethods: [{foreach from=$sPayments item=payment key=paymentKey}{if $payment.name != 'paypal' && isset($PaypalPlusThirdPartyPaymentMethods[$payment.id])}{
                "redirectUrl": "{url controller=payment_paypal action=plusRedirect selectPaymentId=$payment.id}",
                "methodName": {$payment.description|strip_tags|html_entity_decode:null:utf8|trim|json_encode},
                {if !empty($PaypalPlusThirdPartyPaymentMethods[$payment.id]['media'])}
                "imageUrl": "{link file={$PaypalPlusThirdPartyPaymentMethods[$payment.id]['media']} fullPath}",
                {/if}
                "description": {$payment.additionaldescription|strip_tags|html_entity_decode:null:utf8|trim|json_encode}
            }{if !$payment@last},{/if}{/if}{/foreach}]
        });
    </script>
    <script type="text/javascript">
        var jQuery = $ = jQuery_SW;
        $(document).ready(function($) {
            basketButton = $('#basketButton');
            productTable = $('.confirm--content .product--table .panel');
            if (!basketButton[0]) {
                basketButton = $('.main--actions button[type=submit]');
                bbFunction = 'html';
            }
            basketButton.data('orgValue', basketButton[bbFunction]());
            var onConfirm = function () {
                var $agb = $('#sAGB');
                if (!$agb.length || $agb.attr('checked') || $agb[0].checked) {
                    ppp.doCheckout();
                    return false;
                }
                return true;
            };
            $('#confirm--form').on('submit', onConfirm);
            basketButton.on('click', onConfirm);
        });
    </script>
{/if}