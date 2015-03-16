{block name="frontend_index_header_javascript" append}
    {include file="frontend/payment_paypal_plus/javascript.tpl"}
{/block}

{block name="frontend_index_header_css_screen" append}
<style type="text/css">
    #ppplusChangeForm {
        display: none;
        position: absolute;
        bottom: 20px;
        right: 0;
        z-index: 10;
    }
    .paypal_plus_disable_button {
        display: none;
    }
    #confirm .additional_footer {
        height: 46px;
    }
</style>
{/block}

{block name="frontend_checkout_confirm_payment"}
    {if $PaypalPlusApprovalUrl}
        <div class="payment_method">
            <h3 class="underline">{s namespace='frontend/checkout/confirm_payment' name='CheckoutPaymentHeadline'}Zahlungsart{/s}</h3>
            <div id="ppplus"> </div>
            <div class="clear">&nbsp;</div>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name='frontend_checkout_confirm_error_messages' append}
    {if $PaypalPlusApprovalUrl}
        <form id="ppplusChangeForm" style="display: none" name="" method="POST" action="{url controller=account action=savePayment sTarget=checkout}">
            <input type="hidden" name="sourceCheckoutConfirm" value="1" />
            <input id="ppplusChangeInput" type="hidden" name="register[payment]" class="radio auto_submit" value="" />
            <input type="submit" value="{s name='PaypalPlusLinkChangePayment'}Weiter{/s}" class="button-right large" />
        </form>
    {/if}
{/block}

{block name='frontend_checkout_confirm_left_payment_method'}
    {if !$PaypalPlusApprovalUrl}
        {$smarty.block.parent}
    {/if}
{/block}