<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

/**
 * Class PaymentPaypal
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class Webhook
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_Frontend_PaymentPaypal_Webhook' => 'onPaymentPaypalWebhook',
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPaymentPaypalWebhook($args)
    {
        $action = $args->getSubject();
        $payment = $action->Request()->getRawBody();
        $payment = json_decode($payment, true);
        $transactionId = null;

        if (empty($payment['resource']['id'])) {
            $message = "PayPal-Webhook";
            $context = array('request.body' => $payment);
            $action->get('pluginlogger')->error($message, $context);
        } else {
            $transactionId = $payment['resource']['id'];
        }

        $action->forward('notify', null, null, array('txn_id' => $transactionId));

        return true;
    }
}
