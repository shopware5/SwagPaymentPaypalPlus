{block name="frontend_checkout_paypalplus_paymentwall"}
    <div id="ppplus" class="method--description"
         data-paypal-payment-id="{$PayPalPaymentId}"
         data-paypal-sandbox="{if $PaypalPlusModeSandbox}true{else}false{/if}"
         data-paypal-save-in-session-url="{url controller=PaymentPaypal action=SaveStep2inSession forceSecure}">
    </div>
{/block}
