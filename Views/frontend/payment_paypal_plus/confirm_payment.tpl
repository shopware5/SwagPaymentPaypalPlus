{if $sUserData.additional.payment.name == 'paypal'}
    {block name="frontend_checkout_paypal_panel"}
        <div class="panel has--border">
            {block name="frontend_checkout_paypal_panel_title"}
                <div class="panel--title">
                    {s namespace='frontend/checkout/confirm_payment' name='CheckoutPaymentHeadline'}Zahlungsart{/s}
                </div>
            {/block}

            {block name="frontend_checkout_paypal_panel_body"}
                <div class="panel--body">
                    <div id="ppplus"> </div>
                </div>
            {/block}
        </div>
    {/block}
{/if}
