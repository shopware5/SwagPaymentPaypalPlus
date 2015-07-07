{block name="frontend_index_header_javascript" append}
    {include file="frontend/payment_paypal_plus/javascript.tpl"}
{/block}

{block name="frontend_index_header_css_screen" append}
    <link type="text/css" media="all" rel="stylesheet" href="{link file='frontend/_resources/styles/paypalplus.css'}" />
{/block}

{block name="frontend_checkout_confirm_payment"}
    {if $PaypalPlusApprovalUrl}
        {include file="frontend/payment_paypal_plus/confirm_payment_emotion.tpl"}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name='frontend_checkout_confirm_left_payment_method'}
    {if !$PaypalPlusApprovalUrl || !{config name=paypalHidePaymentSelection}}
        {$smarty.block.parent}
    {/if}
{/block}