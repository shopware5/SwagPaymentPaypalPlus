<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap as Bootstrap;

/**
 * Class PlusRedirect
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class PlusRedirect
{
    /**
     * @var \sAdmin
     */
    protected $admin;

    /**
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->admin = $bootstrap->get('modules')->getModule('Admin');
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_Frontend_PaymentPaypal_PlusRedirect' => 'onPaypalPlusRedirect'
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPaypalPlusRedirect($args)
    {
        $action = $args->getSubject();
        $request = $action->Request();
        $selectPaymentId = (int) $request->get('selectPaymentId');
        $request->setPost('sPayment', $selectPaymentId);
        $checkData = $this->admin->sValidateStep3();
        if (!empty($checkData['checkPayment']['sErrorMessages']) || empty($checkData['sProcessed'])) {
            $action->forward(
                'payment',
                'account',
                'frontend',
                array(
                    'ppplusRedirect' => 1
                )
            );

            return true;
        } else {
            $this->admin->sUpdatePayment();
        }
        $action->forward(
            'confirm',
            'checkout',
            'frontend',
            array(
                'ppplusRedirect' => 1
            )
        );

        return true;
    }
}
