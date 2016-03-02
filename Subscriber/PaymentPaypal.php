<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use Enlight_Components_Session_Namespace as Session;
use Shopware\SwagPaymentPaypalPlus\Components\PaymentInstructionProvider;
use Shopware_Components_Paypal_RestClient as RestClient;
use Shopware_Plugins_Frontend_SwagPaymentPaypal_Bootstrap as PaypalBootstrap;

/**
 * Class PaymentPaypal
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class PaymentPaypal
{
    /**
     * @var RestClient
     */
    protected $restClient;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var PaypalBootstrap
     */
    protected $paypalBootstrap;

    /**
     * @param RestClient $restClient
     * @param Session $session
     * @param PaypalBootstrap $paypalBootstrap
     */
    public function __construct(RestClient $restClient, $session, $paypalBootstrap)
    {
        $this->restClient = $restClient;
        $this->restClient->setHeaders('PayPal-Partner-Attribution-Id', 'ShopwareAG_Cart_PayPalPlus_1017');
        $this->session = $session;
        $this->paypalBootstrap = $paypalBootstrap;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PreDispatch_Frontend_PaymentPaypal' => 'onPreDispatchPaymentPaypal'
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
        if (empty($paymentId)) {
            return;
        }

        $payerId = $request->getParam('PayerID');
        $this->restClient->setAuthToken();
        $uri = 'payments/payment/' . $paymentId;
        $payment = $this->restClient->get($uri, array('payer_id' => $payerId));

        $statusId = $this->paypalBootstrap->Config()->get('paypalStatusId', 12);

        if (!empty($payment['transactions'][0]['amount']['total'])) {
            $ppAmount = floatval($payment['transactions'][0]['amount']['total']);
            $ppCurrency = floatval($payment['transactions'][0]['amount']['currency']);
        } else {
            $ppAmount = 0;
            $ppCurrency = '';
        }

        $swAmount = $action->getAmount();
        $swCurrency = $action->getCurrencyShortName();
        if (abs($swAmount - $ppAmount) >= 0.01 || $ppCurrency != $swCurrency) {
            $action->redirect(
                array(
                    'controller' => 'checkout',
                    'action' => 'confirm'
                )
            );

            return;
        }

        if ($payment['state'] == 'created') {
            $uri = "payments/payment/$paymentId/execute";
            $payment = $this->restClient->create($uri, array('payer_id' => $payerId));
        }

        if ($payment['state'] == 'approved') {
            if (!empty($payment['transactions'][0]['related_resources'][0]['sale']['id'])) {
                $transactionId = $payment['transactions'][0]['related_resources'][0]['sale']['id'];
            } else {
                $transactionId = $payment['id'];
            }

            $orderNumber = $action->saveOrder($transactionId, sha1($payment['id']), $statusId);

            if (!empty($payment['transactions'][0]['related_resources'][0]['sale']['state'])) {
                $paymentStatus = ucfirst($payment['transactions'][0]['related_resources'][0]['sale']['state']);
                $this->paypalBootstrap->setPaymentStatus($transactionId, $paymentStatus);
            }

            if ($payment['payment_instruction']) {
                $this->saveInvoiceInstructions($orderNumber, $payment);
            }

            try {
                $sql = '
                    INSERT INTO s_order_attributes (orderID, swag_payal_express)
                    SELECT id, 2 FROM s_order WHERE ordernumber = ?
                    ON DUPLICATE KEY UPDATE swag_payal_express = 2
                ';
                $action->get('db')->query($sql, array($orderNumber));
            } catch (\Exception $e) {
            }

            $action->redirect(
                array(
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => sha1($payment['id'])
                )
            );
        }
    }

    /**
     * @param string $orderNumber
     * @param array $payment
     */
    private function saveInvoiceInstructions($orderNumber, array $payment)
    {
        /**
         * SAVE THE INVOICE-INSTRUCTIONS FROM PAYPAL
         */
        $paymentInstructionProvider = new PaymentInstructionProvider($this->paypalBootstrap->get('db'));
        $paymentInstructionProvider->saveInstructionByOrderNumber($orderNumber, $payment['payment_instruction']);
    }
}
