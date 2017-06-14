<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Components;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;

class PaymentInstructionProvider
{
    /** @var PDOConnection $db */
    private $db;

    /**
     * InvoiceContentProvider constructor.
     *
     * @param PDOConnection $db
     */
    public function __construct(PDOConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param string $orderNumber
     * @param string $transactionId
     * @return array
     */
    public function getInstructionsByOrderNumberAndTransactionId($orderNumber, $transactionId)
    {
        $sql = 'SELECT *
                FROM s_payment_paypal_plus_payment_instruction
                WHERE ordernumber = :orderNumber
                  AND reference_number = :referenceNumber;';

        $result = $this->db->fetchRow(
            $sql,
            array('orderNumber' => $orderNumber, 'referenceNumber' => $transactionId)
        );

        if ($result) {
            $links = json_decode($result['links'], true);
            $result['links'] = $links;
        }

        return $result;
    }

    /**
     * @param string $orderNumber
     * @param array $instructions
     */
    public function saveInstructionByOrderNumber($orderNumber, array $instructions)
    {
        $parameter = array(
            'ordernumber' => $orderNumber,
            'reference_number' => $instructions['reference_number'],
            'instruction_type' => $instructions['instruction_type'],
            'bank_name' => $instructions['recipient_banking_instruction']['bank_name'],
            'account_holder_name' => $instructions['recipient_banking_instruction']['account_holder_name'],
            'international_bank_account_number' => $instructions['recipient_banking_instruction']['international_bank_account_number'],
            'bank_identifier_code' => $instructions['recipient_banking_instruction']['bank_identifier_code'],
            'amount_value' => $instructions['amount']['value'],
            'amount_currency' => $instructions['amount']['currency'],
            'payment_due_date' => $instructions['payment_due_date'],
            'links' => json_encode($instructions['links'])
        );

        $this->db->query($this->getInsertSql(), $parameter);

        $sql = 'UPDATE  s_order
                SET internalcomment = CONCAT(internalcomment, :invoiceComment)
                WHERE ordernumber = :orderNumber';
        $this->db->query($sql, array('invoiceComment' => "\nPaid with Paypal Invoice\n", 'orderNumber' => $orderNumber));
    }

    /**
     * @return string
     */
    private function getInsertSql()
    {
        return 'INSERT INTO s_payment_paypal_plus_payment_instruction (
                    ordernumber,
                    reference_number,
                    instruction_type,
                    bank_name,
                    account_holder_name,
                    international_bank_account_number,
                    bank_identifier_code,
                    amount_value,
                    amount_currency,
                    payment_due_date,
                    links
                ) VALUES (
                    :ordernumber,
                    :reference_number,
                    :instruction_type,
                    :bank_name,
                    :account_holder_name,
                    :international_bank_account_number,
                    :bank_identifier_code,
                    :amount_value,
                    :amount_currency,
                    :payment_due_date,
                    :links
              );';
    }
}
