{extends file="frontend/checkout/finish.tpl"}

{block name="frontend_index_header_css_screen"}
    <link type="text/css" media="all" rel="stylesheet" href="{link file='frontend/_resources/styles/paypalplus.css'}" />
{/block}

{block name='frontend_checkout_finish_header_items'}
    {if $instruction}
        <div class="finish-ppp block panel has--border is--rounded">

            <div class="ppp-header">
                <table id="veryImportante">
                    <tr>
                        <td class="ppp-left-td"><h3>{$instruction.amount_value|currency}</h3></td>
                        <td class="ppp-center-td"><img class="" src="{link file='frontend/_public/src/img/PP_PLUS_PUI_ArrowGraphic.png'}"></td>
                        <td class="ppp-right-td"><img class="" src="{link file='frontend/_public/src/img/PP_PLUS_PUI_logo.png'}" /></td>
                    </tr>
                </table>
            </div>

            <div class="ppp-content">
                <div class="ppp-instruction">
                    <p>
                        {s name=pleaseTransfer namespace=frontend/snippets}Please transfer{/s} {$instruction.amount_value|currency} {s name=to namespace=frontend/snippets}to{/s} {$instruction.payment_due_date|date_format: "%d.%m.%Y"} {s name=atPaypal namespace=frontend/snippets}at PayPal.{/s}
                    </p>
                </div>

                <div class="ppp-instruction-detail-container">
                    <table>
                        <tr>
                            <td>{s name=receiver namespace=frontend/snippets}Receiver:{/s}</td>
                            <td class="bolder">{$instruction.account_holder_name}</td>
                        </tr>
                        <tr>
                            <td>{s name=bankName namespace=frontend/snippets}Bank:{/s}</td>
                            <td class="bolder">{$instruction.bank_name}</td>
                        </tr>
                        <tr>
                            <td>{s name=amount namespace=frontend/snippets}Amount:{/s}</td>
                            <td class="bolder">{$instruction.amount_value|currency}</td>
                        </tr>
                        <tr>
                            <td>{s name=usage namespace=frontend/snippets}Usage:{/s}</td>
                            <td class="bolder">{$instruction.reference_number}</td>
                        </tr>
                        <tr>
                            <td>IBAN:</td>
                            <td class="bolder">{$instruction.international_bank_account_number}</td>
                        </tr>
                        <tr>
                            <td>BIC:</td>
                            <td class="bolder">{$instruction.bank_identifier_code}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="ppp-footer">
                <p>{s name=whyPayPal namespace=frontend/snippets}Why PayPal? PayPal is our partner for processing invoice payments. PayPal has just transferred the amount to us directly. You pay the amount to PayPal according to the payment instructions after you have received and checked your purchase.{/s}</p>
            </div>

        </div>
    {/if}

    {$smarty.block.parent}
{/block}