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
 * Class PaymentForm
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class PaymentForm
{
    protected $bootstrap;

    /**
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatch_Backend_Payment' => 'onExtendBackendPayment',
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onExtendBackendPayment($args)
    {
        $request = $args->getRequest();
        $view = $args->getSubject()->View();

        if ($request->getActionName() == 'load') {
            $this->bootstrap->registerMyTemplateDir();
            $view->extendsTemplate(
                'backend/payment/model/paypal_attribute.js'
            );
            $view->extendsTemplate(
                'backend/payment/view/payment/paypal_form.js'
            );
        }
    }
}
