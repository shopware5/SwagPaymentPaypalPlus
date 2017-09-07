<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\Components\Api\Resource;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Components\Api\Exception\NotFoundException;
use Shopware\Components\Api\Exception\ParameterMissingException;

class PaymentInstruction extends Resource
{
    /**
     * @param int $orderNumber
     *
     * @throws NotFoundException
     * @throws ParameterMissingException
     *
     * @return array
     */
    public function getOne($orderNumber)
    {
        if ($orderNumber === null) {
            throw new ParameterMissingException('ordernumber');
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getContainer()->get('dbal_connection')->createQueryBuilder();

        $result = $queryBuilder->select('*')
                ->from('s_payment_paypal_plus_payment_instruction', 'pi')
                ->where('pi.ordernumber = :orderNumber')
                ->setParameter(':orderNumber', $orderNumber)
                ->execute()
                ->fetchAll();

        if (count($result) === 0) {
            throw new NotFoundException('Payment instruction with ordernumber ' . $orderNumber . ' not found');
        }

        return $result;
    }
}
