{extends file="parent:frontend/checkout/shipping_payment.tpl"}

{* Loaded in Shopware 5 only *}
{block name="frontend_index_javascript_async_ready"}
    {$smarty.block.parent}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/js-payment_wall.tpl"}
    {/if}
{/block}

{block name="frontend_index_header_javascript_jquery"}
    {$smarty.block.parent}
    {if !$isShopware53}
        {if $PaypalPlusApprovalUrl}
            {include file="frontend/payment_paypal_plus/js-payment_wall.tpl"}
        {/if}
    {/if}
{/block}
