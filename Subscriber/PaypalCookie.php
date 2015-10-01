<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use Enlight_Components_Session_Namespace as Session;
use Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap as Bootstrap;

/**
 * Class PaypalCookie
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class PaypalCookie
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
            'Enlight_Controller_Action_Frontend_PaymentPaypal_SaveCookieInSession' => 'onSaveCookieInSession'
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onSaveCookieInSession(\Enlight_Controller_ActionEventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $cookies = $request->getParam('cookies');
        $cameFromStep2 = (bool) $request->getParam('cameFromStep2');
        /** @var Session $session */
        $session = $this->bootstrap->get('session');

        $cookies = explode(';', $cookies);
        $payPalCookieName = 'paypalplus_session=';

        foreach ($cookies as $cookie) {
            if (substr($cookie, 0, strlen($payPalCookieName)) == $payPalCookieName) {
                $payPalCookieValue = substr($cookie, strlen($payPalCookieName));
                $payPalCookieValue = urldecode($payPalCookieValue);

                $session->offsetSet('PaypalCookieValue', $payPalCookieValue);
            }
        }

        $session->offsetSet('PayPalPlusCameFromStep2', $cameFromStep2);

        $args->getSubject()->View()->loadTemplate('');

        return true;
    }
}
