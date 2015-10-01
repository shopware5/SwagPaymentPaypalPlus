{extends file="parent:frontend/checkout/change_payment.tpl"}

{* Method Description *}
{block name='frontend_checkout_payment_fieldset_description'}
    {if $PaypalPlusApprovalUrl && $payment_mean.name == 'paypal'}
        {include file="frontend/payment_paypal_plus/payment_wall.tpl"}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
