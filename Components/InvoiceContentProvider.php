<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
     *
     * @return array
     */
    public function getPayPalInvoiceContentInfo(array $containers, array $orderData)
    {
        $footer = $containers['Paypal_Content_Info'];

        $translationComp = new Shopware_Components_Translation();
        $translation = $translationComp->read($orderData['_order']['language'], 'documents', 1);

        $query = 'SELECT * FROM s_core_documents_box WHERE id = ?';

        $rawFooter = $this->db->fetchAssoc($query, array($footer['id']));

        if (!empty($translation[1]['Paypal_Content_Info_Value'])) {
            $rawFooter['value'] = $translation[1]['Paypal_Content_Info_Value'];
        }

        return $rawFooter[$footer['id']];
    }
}
