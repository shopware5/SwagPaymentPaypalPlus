<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use \Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap as Bootstrap;

/**
 * Class PlusRedirect
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class PlusRedirect
{
    /**
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var \sAdmin
     */
    protected $admin;

    public function __construct(Bootstrap $bootstrap)
    {
        $this->admin = $bootstrap->get('modules')->getModule('Admin');
    }

    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_Frontend_PaymentPaypal_PlusRedirect' => 'onPaypalPlusRedirect',
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onPostDispatchAccount'
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
        $selectPaymentId = (int)$request->get('selectPaymentId');
        $request->setPost('sPayment', $selectPaymentId);
        $checkData = $this->admin->sValidateStep3();
        if (!empty($checkData['checkPayment']['sErrorMessages']) || empty($checkData['sProcessed'])) {
            $action->forward('payment', 'account', 'frontend', array(
                'ppplusRedirect' => 1
            ));
            return true;
        } else {
            $this->admin->sUpdatePayment();
        }
        $action->forward('confirm', 'checkout', 'frontend', array(
            'ppplusRedirect' => 1
        ));
        return true;
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPostDispatchAccount($args)
    {
        $action = $args->getSubject();
        $request = $action->Request();
        if($request->getParam('ppplusRedirect')) {
            $values = $request->getPost();
            $values['payment'] = $values['sPayment'];
            $values['isPost'] = true;
            $action->View()->sFormData = $values;
        }
    }
}