<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use \Shopware_Components_Paypal_RestClient as RestClient;

/**
 * Class PaymentPaypal
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class PaymentPaypal
{
    /**
     * @var RestClient
     */
    protected $restClient;

    protected $session;

    protected $paypalBootstrap;

    public function __construct(RestClient $restClient, $session, $paypalBootstrap)
    {
        $this->restClient = $restClient;
        $this->session = $session;
        $this->paypalBootstrap = $paypalBootstrap;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PreDispatch_Frontend_PaymentPaypal' => 'onPreDispatchPaymentPaypal',
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onPreDispatchPaymentPaypal(\Enlight_Controller_ActionEventArgs $args)
    {
        $request = $args->getRequest();
        /** @var \Shopware_Controllers_Frontend_PaymentPaypal $action */
        $action = $args->getSubject();

        if ($request->getActionName() != 'return') {
            return;
        }

        $paymentId = $this->session->PaypalPlusPayment;
        if(empty($paymentId)) {
            return;
        }

        $payerId = $request->getParam('PayerID');
        $this->restClient->setAuthToken();
        $uri = 'payments/payment/' . $paymentId;
        $payment = $this->restClient->get($uri, array('payer_id' => $payerId));
        $statusId = $this->paypalBootstrap->Config()->get('paypalStatusId', 12);

        if(!empty($payment['transactions'][0]['amount']['total'])) {
            $ppAmount = floatval($payment['transactions'][0]['amount']['total']);
        } else {
            $ppAmount = 0;
        }
        $swAmount = $action->getAmount();
        if (abs($swAmount - $ppAmount) >= 0.01) {
            $action->redirect(array(
                'controller' => 'checkout',
                'action' => 'confirm'
            ));
            return;
        }

        if($payment['state'] == 'created') {
            $uri = "payments/payment/$paymentId/execute";
            $this->restClient->setAuthToken();
            $payment = $this->restClient->create($uri, array('payer_id' => $payerId));
        }

        if($payment['state'] == 'approved') {
            if(!empty($payment['transactions'][0]['related_resources'][0]['sale']['id'])) {
                $transactionId = $payment['transactions'][0]['related_resources'][0]['sale']['id'];
            } else {
                $transactionId = $payment['id'];
            }
            $action->saveOrder($transactionId, sha1($payment['id']), $statusId);

            $action->redirect(array(
                'controller' => 'checkout',
                'action' => 'finish',
                'sUniqueID' => sha1($payment['id'])
            ));
        }
    }
}