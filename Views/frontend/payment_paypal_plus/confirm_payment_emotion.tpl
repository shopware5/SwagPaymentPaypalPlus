<form name="" method="POST" action="{url controller=account action=savePayment sTarget='checkout'}" class="payment">
    <div class="payment_method">
        <h3 class="underline">{s name='CheckoutPaymentHeadline' namespace="frontend/checkout/confirm_payment"}Zahlungsart{/s}</h3>

        <div class="clear">&nbsp;</div>

        {foreach from=$sPayments item=payment_mean name=register_payment_mean}
            <div class="grid_15 method">
                {block name='frontend_checkout_payment_fieldset_input_radio'}
                    {if !{config name='IgnoreAGB'}}
                        <input type="hidden" class="agb-checkbox" name="sAGB" value="{if $sAGBChecked}1{else}0{/if}"/>
                    {/if}
                    <input type="hidden" name="sourceCheckoutConfirm" value="1"/>
                    <input type="radio" name="register[payment]" class="radio auto_submit payment_radio" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}"{if $payment_mean.id eq $sPayment.id} checked="checked"{/if} />
                    <label class="description payment_row{if $sUserData.additional.payment.name == $payment_mean.name} active{if $payment_mean.name == 'paypal'} has_border{/if}{/if}" for="payment_mean{$payment_mean.id}">
                        <span class="payment_name grid_5">{$payment_mean.description}</span>
                        {if $payment_mean.name != 'paypal'}
                            <span class="payment_description grid_10">
                                {include file="string:{$payment_mean.additionaldescription}"}
                            </span>
                        {/if}
                    </label>
                {/block}

                {block name='frontend_checkout_payment_fieldset_description'}
                    {if $payment_mean.name == 'paypal' && $sUserData.additional.payment.name == 'paypal'}
                        <div id="ppplus"></div>
                    {/if}
                {/block}

                {block name='frontend_checkout_payment_fieldset_template'}
                    <div class="payment_logo_{$payment_mean.name}"></div>
                    {if "frontend/plugins/payment/show_`$payment_mean.template`"|template_exists && !{config name=paymentEditingInCheckoutPage}}
                        <div class="space">&nbsp;</div>
                        <div class="grid_10 bankdata">
                            {if $payment_mean.id eq $sPayment.id}
                                {include file="frontend/plugins/payment/show_`$payment_mean.template`" form_data=$sPayment.data}
                            {/if}
                        </div>
                    {elseif "frontend/plugins/payment/`$payment_mean.template`"|template_exists}
                        <div class="grid_10 bankdata">
                            {if $payment_mean.id eq $sPayment.id}
                                <div class="space">&nbsp;</div>
                                {include file="frontend/plugins/payment/`$payment_mean.template`" form_data=$sPayment.data}
                            {/if}
                        </div>
                    {/if}
                {/block}
            </div>
        {/foreach}
    </div>
</form>

<div class="doublespace"></div>