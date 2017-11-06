<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
        return 'CREATE TABLE IF NOT EXISTS s_payment_paypal_plus_payment_instruction (
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
                `links` TEXT);';
    }
}
