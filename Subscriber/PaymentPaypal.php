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
use Shopware\SwagPaymentPaypalPlus\Components\RestClient;
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
    public function __construct(RestClient $restClient, Session $session, PaypalBootstrap $paypalBootstrap)
    {
        $this->restClient = $restClient;
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
        /** @var \Shopware_Controllers_Frontend_PaymentPaypal $controller */
        $controller = $args->getSubject();

        if ($request->getActionName() != 'return') {
            return;
        }

        $paymentId = $this->session->get('PaypalPlusPayment');
        if (!$paymentId) {
            return;
        }

        $payerId = $request->getParam('PayerID');
        $uri = 'payments/payment/' . $paymentId;
        $payment = $this->restClient->get($uri, array('payer_id' => $payerId));

        if (!empty($payment['transactions'][0]['amount']['total'])) {
            $ppAmount = floatval($payment['transactions'][0]['amount']['total']);
            $ppCurrency = floatval($payment['transactions'][0]['amount']['currency']);
        } else {
            $ppAmount = 0;
            $ppCurrency = '';
        }

        $swAmount = $controller->getAmount();
        $swCurrency = $controller->getCurrencyShortName();
        if (abs($swAmount - $ppAmount) >= 0.01 || $ppCurrency != $swCurrency) {
            $controller->redirect(
                array(
                    'controller' => 'checkout',
                    'action' => 'confirm'
                )
            );

            return;
        }

        $paypalConfig = $this->paypalBootstrap->Config();
        $statusId = $paypalConfig->get('paypalStatusId', 12);
        $transactionId = $payment['id'];
        $orderNumber = null;

        if ($payment['state'] == 'created') {
            if ($paypalConfig->get('paypalSendInvoiceId')) {
                $orderNumber = $controller->saveOrder($transactionId, sha1($payment['id']), $statusId);
                $params = array(
                    array(
                        'op' => 'add',
                        'path' => '/transactions/0/invoice_number',
                        'value' => $orderNumber
                    )
                );

                $prefix = $paypalConfig->get('paypalPrefixInvoiceId');
                if ($prefix) {
                    // Set prefixed invoice id - Remove special chars and spaces
                    $prefix = str_replace(' ', '', $prefix);
                    $prefix = preg_replace('/[^A-Za-z0-9\_]/', '', $prefix);

                    $params[0]['value'] = $prefix . $orderNumber;
                }

                $uri = 'payments/payment/' . $paymentId;

                $this->restClient->patch($uri, $params);
            }

            $uri = "payments/payment/$paymentId/execute";
            $payment = $this->restClient->create($uri, array('payer_id' => $payerId));
        }

        if ($payment['state'] == 'approved') {
            if (!empty($payment['transactions'][0]['related_resources'][0]['sale']['id'])) {
                $transactionId = $payment['transactions'][0]['related_resources'][0]['sale']['id'];
            } else {
                $transactionId = $payment['id'];
            }

            if (!$orderNumber) {
                $orderNumber = $controller->saveOrder($transactionId, sha1($payment['id']), $statusId);
            } else {
                $sql = 'UPDATE s_order
                        SET transactionID = ?
                        WHERE ordernumber = ?;';

                $controller->get('db')->query($sql, array($transactionId, $orderNumber));
            }

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
                $controller->get('db')->query($sql, array($orderNumber));
            } catch (\Exception $e) {
            }

            $controller->redirect(
                array(
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => sha1($payment['id'])
                )
            );
        }
    }

    /**
     * save the invoice instructions from paypal
     *
     * @param string $orderNumber
     * @param array $payment
     */
    private function saveInvoiceInstructions($orderNumber, array $payment)
    {
        $paymentInstructionProvider = new PaymentInstructionProvider($this->paypalBootstrap->get('db'));
        $paymentInstructionProvider->saveInstructionByOrderNumber($orderNumber, $payment['payment_instruction']);
    }
}
