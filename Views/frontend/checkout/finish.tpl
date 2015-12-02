{extends file="parent:frontend/checkout/finish.tpl"}

{block name='frontend_checkout_finish_info'}
    {if $instruction}

        {block name='paypal_plus_main'}
            <div class="paypal-plus--finish-main block panel has--border is--rounded">

                {block name='paypal_plus_main_header'}
                    <div class="finish-main--ppp-header">
                        <table>
                            <tr>
                                <td class="ppp-header--left-td"><h3>{$instruction.amount_value|currency}</h3></td>
                                <td class="ppp-header--center-td"><img class="" src="{link file='frontend/_public/src/img/PP_PLUS_PUI_ArrowGraphic.png'}"></td>
                                <td class="ppp-header--right-td"><img class="" src="{link file='frontend/_public/src/img/PP_PLUS_PUI_logo.png'}" /></td>
                            </tr>
                        </table>
                    </div>
                {/block}

                {block name='paypal_plus_main_content'}
                    <div class="finish-main--content">
                        
                        {block name='paypal_plus_main_content_instructions'}
                            <div class="content--instruction">
                                <p class="instruction--paragraph">
                                    {s name=pleaseTransfer namespace=frontend/snippets}Please transfer{/s} {$instruction.amount_value|currency} {s name=to namespace=frontend/snippets}to{/s} {$instruction.payment_due_date|date_format: "%d.%m.%Y"} {s name=atPaypal namespace=frontend/snippets}at PayPal.{/s}
                                </p>
                            </div>
                        {/block}
                        
                        {block name='paypal_plus_main_content_container'}
                            <div class="content--instruction-details">
                                {block name='paypal_plus_main_content_container_table'}
                                    <table class="instruction-details--table">
                                        {block name='paypal_plus_main_content_table_receiver'}
                                            <tr class="instruction-details--receiver">
                                                <td>{s name=receiver namespace=frontend/snippets}Receiver:{/s}</td>
                                                <td class="bolder">{$instruction.account_holder_name}</td>
                                            </tr>
                                        {/block}
                                        {block name='paypal_plus_main_content_table_bank'}
                                            <tr class="instruction-details--bank">
                                                <td>{s name=bankName namespace=frontend/snippets}Bank:{/s}</td>
                                                <td class="bolder">{$instruction.bank_name}</td>
                                            </tr>
                                        {/block}
                                        {block name='paypal_plus_main_content_table_amount'}
                                            <tr class="instruction-details--amount">
                                                <td>{s name=amount namespace=frontend/snippets}Amount:{/s}</td>
                                                <td class="bolder">{$instruction.amount_value|currency}</td>
                                            </tr>
                                        {/block}
                                        {block name='paypal_plus_main_content_table_usage'}
                                            <tr class="instruction-details--usage">
                                                <td>{s name=usage namespace=frontend/snippets}Usage:{/s}</td>
                                                <td class="bolder">{$instruction.reference_number}</td>
                                            </tr>
                                        {/block}
                                        {block name='paypal_plus_main_content_table_iban'}
                                            <tr class="instruction-details--iban">
                                                <td>IBAN:</td>
                                                <td class="bolder">{$instruction.international_bank_account_number}</td>
                                            </tr>
                                        {/block}
                                        {block name='paypal_plus_main_content_table_iban'}
                                            <tr class="instruction-details--bic">
                                                <td>BIC:</td>
                                                <td class="bolder">{$instruction.bank_identifier_code}</td>
                                            </tr>
                                        {/block}
                                    </table>
                                {/block}                                
                            </div>
                        {/block}
                    </div>
                {/block}

                {block name='paypal_plus_main_footer'}
                    <div class="finish-main--ppp-footer">
                        <p>{s name=whyPayPal namespace=frontend/snippets}Why PayPal? PayPal is our partner for processing invoice payments. PayPal has just transferred the amount to us directly. You pay the amount to PayPal according to the payment instructions after you have received and checked your purchase.{/s}</p>
                    </div>
                {/block}

            </div>
        {/block}

    {/if}

    {$smarty.block.parent}
{/block}