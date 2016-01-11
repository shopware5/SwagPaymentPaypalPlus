{block name="frontend_checkout_paypalplus_paymentwall"}
    <div id="ppplus" class="method--description"
         data-paypal-payment-id="{$PayPalPaymentId}"
         data-paypal-sandbox="{if $PaypalPlusModeSandbox}true{else}false{/if}"
         data-paypal-cookie-url="{url controller=PaymentPaypal action=SaveCookieInSession forceSecure}">
    </div>
{/block}
