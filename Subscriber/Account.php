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
 * Class Account
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class Account
{
    /**
     * @var Bootstrap $bootstrap
     */
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
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onPostDispatchAccount'
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPostDispatchAccount($args)
    {
        $action = $args->getSubject();
        $request = $action->Request();
        $view = $action->View();

        if ($request->getParam('ppplusRedirect')) {
            $values = $request->getPost();
            $values['payment'] = $values['sPayment'];
            $values['isPost'] = true;
            $view->assign('sFormData', $values);
        }

        $newDescription = $this->bootstrap->Config()->get('paypalPlusDescription', '');
        $newAdditionalDescription = $this->bootstrap->Config()->get('paypalPlusAdditionalDescription', '');
        if ($request->getActionName() == 'payment') {
            //Fix payment description
            $payments = $action->View()->getAssign('sPaymentMeans');
            if (!empty($payments)) {
                foreach ($payments as $key => $payment) {
                    if ($payment['name'] == 'paypal') {
                        $payments[$key]['description'] = $newDescription;
                        $payments[$key]['additionaldescription'] = $payment['additionaldescription'] . $newAdditionalDescription;
                        break;
                    }
                }
                $view->assign('sPaymentMeans', $payments);
            }
        }

        $user = $view->getAssign('sUserData');
        if (!empty($user['additional']['payment']['name']) && $user['additional']['payment']['name'] == 'paypal') {
            $user['additional']['payment']['description'] = $newDescription;
            $user['additional']['payment']['additionaldescription'] = $newAdditionalDescription;
            $view->assign('sUserData', $user);
        }
    }
}
