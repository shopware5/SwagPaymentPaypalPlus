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

use Shopware\Components\DependencyInjection\Container;

class PaymentInstructionProvider
{
    /** @var Container  */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getInstructionsByOrdernumberAndTransactionId($ordernumber, $transactionId)
    {
        $result = $this->container->get('dbal_connection')->createQueryBuilder()
            ->select('*')
            ->from('s_payment_paypal_plus_payment_instruction', 'instuctions')
            ->where('ordernumber = :ordernumber')
            ->andWhere('reference_number = :referenceNumber')
            ->setParameter(':ordernumber', $ordernumber)
            ->setParameter(':referenceNumber', $transactionId)
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);

        if($result) {
            $links = json_decode($result['links'], true);
            $result['links'] = $links;
        }

        return $result;
    }

    public function saveInstructionByOrdernumber($ordernumber, array $instructions)
    {
        $parameter = array(
            'ordernumber'                           => $ordernumber,
            'reference_number'                      => $instructions['reference_number'],
            'instruction_type'                      => $instructions['instruction_type'],
            'bank_name'                             => $instructions['recipient_banking_instruction']['bank_name'],
            'account_holder_name'                   => $instructions['recipient_banking_instruction']['account_holder_name'],
            'international_bank_account_number'     => $instructions['recipient_banking_instruction']['international_bank_account_number'],
            'bank_identifier_code'                  => $instructions['recipient_banking_instruction']['bank_identifier_code'],
            'amount_value'                          => $instructions['amount']['value'],
            'amount_currency'                       => $instructions['amount']['currency'],
            'payment_due_date'                      => $instructions['payment_due_date'],
            'links'                                 => json_encode($instructions['links'])
        );

        $this->container->get('db')->query($this->getInsertSql(), $parameter);
    }

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