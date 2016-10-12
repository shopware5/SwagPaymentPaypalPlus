<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Shopware\SwagPaymentPaypalPlus\Components\RestClient;
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
        $plusAvailable = $this->paypalPlusAvailable();

        if ($plusAvailable && $request->getActionName() === 'payment') {
            //Fix payment description
            $payments = $action->View()->getAssign('sPaymentMeans');
            if (!empty($payments)) {
                foreach ($payments as $key => $payment) {
                    if ($payment['name'] === 'paypal') {
                        $payments[$key]['description'] = $newDescription;
                        $payments[$key]['additionaldescription'] = $payment['additionaldescription'] . $newAdditionalDescription;
                        break;
                    }
                }
                $view->assign('sPaymentMeans', $payments);
            }
        }

        $user = $view->getAssign('sUserData');
        if ($plusAvailable && !empty($user['additional']['payment']['name']) && $user['additional']['payment']['name'] === 'paypal') {
            $user['additional']['payment']['description'] = $newDescription;
            $user['additional']['payment']['additionaldescription'] = $newAdditionalDescription;
            $view->assign('sUserData', $user);
        }
    }

    /**
     * Returns a value indicating whether or not the payment method "paypal plus" is available at the moment.
     * It sends a test call to the api and if everything went fine, the method is available.
     * Currently, the main purpose of this function is the SSL test.
     *
     * @return bool
     */
    private function paypalPlusAvailable()
    {
        /** @var RestClient $client */
        $client = $this->bootstrap->get('paypal_plus.rest_client');

        if($client === null) {
            return false;
        }

        //Test the SSL Certificate that was provided by sending a simple test call to the PP API.
        //This is required, because in this step no other request will be sent that may be validated.
        //If this call fails, the payment method will not be modified and stay as paypal classic.
        //The method can be extended with several other exceptions to generate a correct result. The existing ones
        //do only check whether or not the SSL setup is correct.
        try {
            $client->get('identity/openidconnect/userinfo', array('schema' => 'openid'));
        } catch (ClientException $ce) {
            //It's okay, since this call is just a SSL test.
        } catch (RequestException $re) {
            //It's not okay, since it's an SSL exception
            return false;
        }

        //Resets the rest client in order to use it later
        $client = null;

        return true;
    }
}
