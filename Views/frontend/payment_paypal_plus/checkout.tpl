{block name="frontend_index_header_javascript" append}
    {include file="frontend/payment_paypal_plus/javascript.tpl"}
{/block}

{block name="frontend_index_header_css_screen" append}
    {include file="frontend/payment_paypal_plus/css_screen.tpl"}
{/block}

{block name="frontend_checkout_confirm_payment"}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/confirm_payment.tpl"}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name='frontend_checkout_confirm_error_messages' append}
    {if $PaypalPlusApprovalUrl}
        <form id="ppplusChangeForm" style="display: none" name="" method="POST" action="{url controller=account action=savePayment sTarget=checkout}">
            <input id="ppplusRedirect" type="hidden" name="ppplusRedirect" value="0" />
            <input type="hidden" name="sourceCheckoutConfirm" value="1" />
            <input id="ppplusChangeInput" type="hidden" name="register[payment]" class="radio" value="" />
            <input type="submit" value="{s name='PaypalPlusLinkChangePayment'}Weiter{/s}" class="button-right large" />
        </form>
    {/if}
{/block}

{block name='frontend_checkout_confirm_left_payment_method'}
    {if !$PaypalPlusApprovalUrl}
        {$smarty.block.parent}
    {/if}
{/block}