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
use Shopware_Components_Translation;

class InvoiceContentProvider
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
     * @param array $containers
     * @param array $orderData
     * @return array
     */
    public function getPayPalInvoiceContentInfo(array $containers, array $orderData)
    {
        $footer = $containers['Paypal_Content_Info'];

        $translationComp = new Shopware_Components_Translation();
        $translation = $translationComp->read($orderData['_order']['language'], 'documents', 1);

        $query = "SELECT * FROM s_core_documents_box WHERE id = ?";

        $rawFooter = $this->db->fetchAssoc($query, array($footer['id']));

        if (!empty($translation[1]["Paypal_Content_Info_Value"])) {
            $rawFooter["value"] = $translation[1]["Paypal_Content_Info_Value"];
        }

        return $rawFooter[$footer['id']];
    }
}
