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
            'Enlight_Controller_Action_Frontend_PaymentPaypal_SaveStep2inSession' => 'onSaveStep2inSession'
        );
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return bool
     */
    public function onSaveStep2inSession(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_PaymentPaypal $controller */
        $controller = $args->get('subject');
        $cameFromStep2 = (bool) $controller->Request()->getParam('cameFromStep2');
        /** @var Session $session */
        $session = $this->bootstrap->get('session');

        $session->offsetSet('PayPalPlusCameFromStep2', $cameFromStep2);

        $controller->View()->loadTemplate('');

        return true;
    }
}
