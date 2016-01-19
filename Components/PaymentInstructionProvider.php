<?php

/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
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
        $sql = "SELECT *
                FROM s_payment_paypal_plus_payment_instruction
                WHERE ordernumber = :orderNumber
                  AND reference_number = :referenceNumber;";

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
    }

    /**
     * @return string
     */
    private function getInsertSql()
    {
        return "INSERT INTO s_payment_paypal_plus_payment_instruction (
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
              );";
    }
}
