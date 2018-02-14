{block name="frontend_checkout_payment_paypalplus_paymentwall"}
    {$PayPalPlusContinue = "{s name='PaypalPlusLinkChangePayment'}Weiter{/s}"}
    <script type="text/javascript">
        var asyncConf = ~~("{$theme.asyncJavascriptLoading}");
        if (typeof document.asyncReady === 'function' && asyncConf) {
            document.asyncReady(function() {
                window.jQuery_SW = $.noConflict(true);
            });
        } else {
            window.jQuery_SW = $.noConflict(true);
        }
    </script>
    <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        var paymentWall,
            paymentWallLoaded = false,
            // This callback will be triggered when the plus iframe has been loaded.
            paymentWallLoadCallback = function () {
                paymentWallLoaded = false;

                if (getSelectedPaymentMethodId() !== {$PayPalPaymentId}) {
                    window.ppp.deselectPaymentMethod();
                }

                paymentWallLoaded = true;
            },
            // This callback will be triggered, when a new payment method was selected within the iframe.
            paymentWallSelectCallback = function () {
                if (!paymentWallLoaded) {
                    return;
                }

                var $payPalCheckBox = $("#payment_mean" + {$PayPalPaymentId});

                if (getSelectedPaymentMethodId() !== {$PayPalPaymentId} && !$payPalCheckBox.prop('checked')) {
                    $payPalCheckBox.prop('checked', true);

                    $('*[data-ajax-shipping-payment="true"]').data('plugin_swShippingPayment').onInputChanged();
                }
            },
            // A helper function that returns the currently selected payment id.
            getSelectedPaymentMethodId = function () {
                var $selectedPaymentMethod = $('*[checked="checked"][name="payment"]');

                return parseInt($selectedPaymentMethod.attr('value'));
            };

        var paymentWallFn = function() {
            window.jQuery = $ = window.jQuery_SW;
            window.paypalIsCurrentPaymentMethodPaypal = {if $sUserData.additional.payment.id == $PayPalPaymentId}true{else}false{/if};

            paymentWall = function($, approvalUrl) {
                var $basketButton = $('#basketButton'),
                    bbFunction = 'val',
                    $agb = $('#sAGB'),
                    ppp,
                    preSelection = 'none',
                    $payPalCheckBox = $("#payment_mean" + {$PayPalPaymentId}),
                    isConfirmAction = $('.is--act-confirm').length > 0,
                    urlForSendingCustomerData = '{url controller=checkout action=preRedirect forceSecure}',
                    urlForSendingCustomerDataError = '{url controller=payment_paypal action=return forceSecure}',
                    customerCommentField = $(".user-comment--hidden"),
                    onConfirm = function(event) {
                        if (!window.paypalIsCurrentPaymentMethodPaypal || ($agb && $agb.length > 0 && !$agb.prop('checked'))) {
                            return;
                        }

                        event.preventDefault();

                        $.ajax({
                            type: "POST",
                            url: urlForSendingCustomerData,
                            data: { sComment: customerCommentField.val() },
                            success: function() {
                                ppp.doCheckout();
                            },
                            error: function() {
                                $(location).attr('href', urlForSendingCustomerDataError);
                            }
                        });
                    };

                approvalUrl = approvalUrl || "{$PaypalPlusApprovalUrl|escape:javascript}";

                if (!$basketButton[0]) {
                    $basketButton = $('.main--actions button[type=submit]');
                    bbFunction = 'html';
                }

                $basketButton.data('orgValue', $basketButton[bbFunction]());

                // Delegate the confirm button function to the onConfirm function in this plugin.
                $('#confirm--form').on('submit', onConfirm);
                $basketButton.on('click', onConfirm);

                if (!$('#ppplus').length) {
                    return;
                }

                // Pre-select the paypal payment method in the iframe, if the paypal payment method is currently
                // selected.
                if ($payPalCheckBox.length > 0 && $payPalCheckBox.prop('checked')) {
                    preSelection = 'paypal';
                } else if (isConfirmAction && window.paypalIsCurrentPaymentMethodPaypal) {
                    preSelection = 'paypal'
                }

                // PayPal iframe options.
                var opts = {
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
                    preselection: preSelection,
                    showPuiOnSandbox: true,
                    showLoadingIndicator: true
                };

                // Without this check, the payment wall would register the callbacks in the background too often, since it
                // has to be initialized again after any payment method change. Therefore, it's being registered only once.
                if (!paymentWallLoaded) {
                    opts.onLoad = paymentWallLoadCallback;
                    opts.enableContinue = paymentWallSelectCallback
                }

                paymentWallLoaded = false;

                // Create a new PayPal Plus instance using the options that were provided above.
                ppp = PAYPAL.apps.PPP(opts);

                return ppp;
            };

            if (!paymentWallLoaded) {
                window.ppp = paymentWall($);
            }
        };

        var asyncConf = ~~("{$theme.asyncJavascriptLoading}");
        if (typeof document.asyncReady === 'function' && asyncConf) {
            document.asyncReady(paymentWallFn);
        } else {
            paymentWallFn();
        }
    </script>
{/block}
