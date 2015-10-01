{extends file="parent:frontend/checkout/confirm.tpl"}

{* Loaded in Shopware 5 only *}
{block name="frontend_index_header_javascript_jquery"}
    {$smarty.block.parent}
    {if $sUserData.additional.payment.id == $PayPalPaymentId && $cameFromStep2}
        {include file="frontend/payment_paypal_plus/js-checkout_only.tpl"}
    {elseif $sUserData.additional.payment.id == $PayPalPaymentId && $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/js-payment_wall.tpl"}
    {/if}
{/block}

{block name='frontend_checkout_confirm_premiums'}
    {if $sUserData.additional.payment.id == $PayPalPaymentId && $PaypalPlusApprovalUrl && !$cameFromStep2}
        <div id="ppplus"></div>
    {/if}
    {$smarty.block.parent}
{/block}
