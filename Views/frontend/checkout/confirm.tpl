{extends file="parent:frontend/checkout/confirm.tpl"}

{* Loaded in Shopware 5 only *}
{block name="frontend_index_header_javascript_jquery"}
    {$smarty.block.parent}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/javascript.tpl"}
    {/if}
{/block}

{block name='frontend_checkout_confirm_premiums'}
    {$smarty.block.parent}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/confirm_payment.tpl"}
    {/if}
{/block}