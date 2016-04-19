{block name="frontend_checkout_payment_paypalplus_paymentwall"}
    {$PayPalPlusContinue = "{s name='PaypalPlusLinkChangePayment'}Weiter{/s}"}
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">

        function paymentWall($, approvalUrl) {
            var isCurrentPaymentMethodPaypal = {if $sUserData.additional.payment.id == $PayPalPaymentId}true{else}false{/if},
                $basketButton = $('#basketButton'),
                $productTable = $('.confirm--content .product--table .panel'),
                bbFunction = 'val',
                $agb = $('#sAGB'),
                ppp,
                preSelection = 'none',
                $payPalCheckBox = $("#payment_mean" + {$PayPalPaymentId}),
                isConfirmAction = $('.is--act-confirm').length > 0,
                onConfirm = function () {
                    if (isCurrentPaymentMethodPaypal && (!$agb.length || $agb.attr('checked') || $agb[0].checked)) {
                        ppp.doCheckout();
                        return false;
                    }
                    return true;
                },
                onContinue = function () {
                    if ($payPalCheckBox.prop('checked')) {
                        ppp.doContinue();
                        return false;
                    }
                    return true;
                };

            approvalUrl = approvalUrl || "{$PaypalPlusApprovalUrl|escape:javascript}";

            if (!$basketButton[0]) {
                $basketButton = $('.main--actions button[type=submit]');
                bbFunction = 'html';
            }

            $basketButton.data('orgValue', $basketButton[bbFunction]());

            $('#confirm--form').on('submit', onConfirm);
            $basketButton.on('click', onConfirm);

            $('button.main--actions').on('click', onContinue);

            if (!$('#ppplus').length) {
                return;
            }

            if ($payPalCheckBox.length > 0 && $payPalCheckBox.prop('checked')) {
                preSelection = 'paypal';
            } else if (isConfirmAction && isCurrentPaymentMethodPaypal) {
                preSelection = 'paypal'
            }

            ppp = PAYPAL.apps.PPP({
                approvalUrl: approvalUrl,
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
                onThirdPartyPaymentMethodDeselected: function (e) {
                    if ($basketButton) {
                        $basketButton[bbFunction]($basketButton.data('orgValue'));
                    }
                    if ($productTable) {
                        $productTable.show();
                    }
                },
                onThirdPartyPaymentMethodSelected: function (e) {
                    if ($basketButton) {
                        $basketButton[bbFunction]({$PayPalPlusContinue|json_encode});
                    }
                    if ($productTable) {
                        $productTable.hide();
                    }
                },
                onContinue: function () {
                    window.location.href = '{url action=confirm}';
                },
                preselection: preSelection,
                thirdPartyPaymentMethods: [
                {foreach from=$sPayments item=payment key=paymentKey}
                {if $payment.name != 'paypal' && isset($PaypalPlusThirdPartyPaymentMethods[$payment.id])}
                    {
                        "redirectUrl": "{url controller=payment_paypal action=plusRedirect selectPaymentId=$payment.id forceSecure}",
                        "methodName": {$payment.description|strip_tags|html_entity_decode:null:utf8|trim|json_encode},
                    {if !empty($PaypalPlusThirdPartyPaymentMethods[$payment.id]['media'])}
                        "imageUrl": "{link file={$PaypalPlusThirdPartyPaymentMethods[$payment.id]['media']} fullPath}",
                    {/if}
                        "description": {$payment.additionaldescription|strip_tags|html_entity_decode:null:utf8|trim|json_encode}
                    }
                {if !$payment@last}, {/if}
                {/if}
                {/foreach}
                ],
                showPuiOnSandbox: true,
                showLoadingIndicator: true
            });
        }

        jQuery(document).ready(function ($) {
            paymentWall($);
        });
    </script>
{/block}
