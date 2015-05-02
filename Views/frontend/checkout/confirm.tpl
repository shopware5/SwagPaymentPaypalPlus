{extends file="parent:frontend/checkout/confirm.tpl"}

{block name="frontend_index_header_javascript" append}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/javascript.tpl"}
    {/if}
{/block}

{block name='frontend_checkout_confirm_premiums' append}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/confirm_payment.tpl"}
    {/if}
{/block}

{block name='frontend_checkout_confirm_payment_method_panel'}
    {if !$PaypalPlusApprovalUrl || !{config name=paypalHidePaymentSelection}}
        {$smarty.block.parent}
    {/if}
{/block}