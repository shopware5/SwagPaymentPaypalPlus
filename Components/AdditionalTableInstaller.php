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

use Enlight_Components_Db_Adapter_Pdo_Mysql as DatabaseConnection;

class AdditionalTableInstaller
{
    /** @var DatabaseConnection */
    private $databaseConnection;

    /**
     * AdditionalTableInstaller constructor.
     *
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->databaseConnection = $db;
    }

    /**
     * @return \Zend_Db_Statement_Pdo
     */
    public function installAdditionalDatabaseTable()
    {
        $sql = $this->getSql();
        return $this->databaseConnection->query($sql);
    }

    /**
     * @return string
     */
    private function getSql()
    {
        return "CREATE TABLE IF NOT EXISTS s_payment_paypal_plus_payment_instruction (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `ordernumber` VARCHAR(255),
                `reference_number` VARCHAR(255),
                `instruction_type` VARCHAR(255),
                `bank_name` VARCHAR(255),
                `account_holder_name` VARCHAR(255),
                `international_bank_account_number` VARCHAR(255),
                `bank_identifier_code` VARCHAR(255),
                `amount_value` VARCHAR(255),
                `amount_currency` VARCHAR(10),
                `payment_due_date` DATETIME,
                `links` TEXT);";
    }
}
