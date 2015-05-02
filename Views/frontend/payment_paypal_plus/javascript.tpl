{if $PaypalPlusApprovalUrl}
    <script type="text/javascript">
        var jQuery_SW = $.noConflict(true);
    </script>
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        // $('button[form="confirm--form"]')
        var ppp; disable = false; ppp = PAYPAL.apps.PPP({
            approvalUrl: "{$PaypalPlusApprovalUrl|escape:javascript}",
            placeholder: "ppplus",
            mode: "{if $PaypalPlusModeSandbox}sandbox{else}live{/if}",
            buttonLocation: "outside",
            useraction: "commit",
            country: '{$sUserData.additional.country.countryiso}',
            {if $PaypalLocale == 'de_DE' || $PaypalLocale == 'en_US'}
                {$PaypalReverse = '_'|explode:$PaypalLocale|array_reverse}
                {$PaypalPlusLang = '_'|implode:$PaypalReverse}
            {else}
                {$PaypalPlusLang = $PaypalLocale}
            {/if}
            language: '{$PaypalPlusLang}',
            //preselection: "{if $sUserData.additional.payment.name == 'paypal'}paypal{else}none{/if}",
            thirdPartyPaymentMethods: [{foreach from=$sPayments item=payment key=paymentKey}{if $payment.name != 'paypal' && isset($PaypalPlusThirdPartyPaymentMethods[$payment.id])}{
                "redirectUrl": "{url controller=payment_paypal action=plusRedirect selectPaymentId=$payment.id}",
                "methodName": {$payment.description|unescape:entity|json_encode},
                "imageUrl": "{if !empty($PaypalPlusThirdPartyPaymentMethods[$payment.id]['media'])}{link file={$PaypalPlusThirdPartyPaymentMethods[$payment.id]['media']} fullPath}{/if}",
                "description": {$payment.additionaldescription|strip_tags|html_entity_decode:null:utf8|trim|json_encode}
            }{if !$payment@last},{/if}{/if}{/foreach}]
        });
    </script>
    <script type="text/javascript">
        var jQuery = $ = jQuery_SW;
        $(document).ready(function($) {
            var onConfirm = function () {
                var $agb = $('#sAGB');
                if (!$agb.length || $agb.attr('checked') || $agb[0].checked) {
                    ppp.doCheckout();
                    return false;
                }
                return true;
            };
            $('#confirm--form').on('submit', onConfirm);
            $('#basketButton').on('click', onConfirm);
        });
    </script>
{/if}