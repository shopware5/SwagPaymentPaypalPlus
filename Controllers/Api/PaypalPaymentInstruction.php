<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Shopware\Components\Api\Resource\PaymentInstruction;

class Shopware_Controllers_Api_PaypalPaymentInstruction extends Shopware_Controllers_Api_Rest
{
    /**
     * @var PaymentInstruction
     */
    private $resource;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('PaymentInstruction');
    }

    public function getAction()
    {
        $orderNumber = $this->Request()->get('id');

        $result = $this->resource->getOne($orderNumber);

        $this->View()->assign(array('success' => true, 'data' => $result[0]));
    }
}
