<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Action as Controller;
use Shopware\SwagPaymentPaypalPlus\Components\PaymentInstructionProvider;
use Shopware\SwagPaymentPaypalPlus\Components\RestClient;
use Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap as Bootstrap;
use Shopware_Plugins_Frontend_SwagPaymentPaypal_Bootstrap as PaypalBootstrap;

/**
 * Class Checkout
 *
 * @package Shopware\SwagPaymentPaypal\Subscriber
 */
class Checkout
{
    /**
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var PaypalBootstrap
     */
    protected $paypalBootstrap;

    /**
     * @var \Enlight_Config
     */
    protected $config;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var RestClient
     */
    protected $restClient;

    /**
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
        $this->paypalBootstrap = $bootstrap->Collection()->get('SwagPaymentPaypal');
        $this->config = $this->paypalBootstrap->Config();
        $this->session = $bootstrap->get('session');
        $this->restClient = $bootstrap->get('paypal_plus.rest_client');
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckout',
            'Enlight_Controller_Action_Frontend_Checkout_PreRedirect' => 'onPreRedirectToPayPal'
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onPostDispatchCheckout($args)
    {
        $controller = $args->getSubject();
        $request = $controller->Request();
        $view = $controller->View();

        $cameFromStep2 = $this->session->offsetGet('PayPalPlusCameFromStep2');

        if (!$cameFromStep2 && $request->getActionName() !== 'preRedirect') {
            unset($this->session->PaypalPlusPayment);
        }

        /** @var $shopContext \Shopware\Models\Shop\Shop */
        $shopContext = $this->bootstrap->get('shop');
        $templateVersion = $shopContext->getTemplate()->getVersion();

        if ($request->getActionName() === 'finish') {
            $this->addInvoiceInstructionsToView($view, $templateVersion);
        }

        //Fix payment description
        $newDescription = $this->bootstrap->Config()->get('paypalPlusDescription', '');
        $newAdditionalDescription = $this->bootstrap->Config()->get('paypalPlusAdditionalDescription', '');
        $payments = $view->getAssign('sPayments');
        if (!empty($payments)) {
            foreach ($payments as $key => $payment) {
                if ($payment['name'] === 'paypal') {
                    $payments[$key]['description'] = $newDescription;
                    $payments[$key]['additionaldescription'] = $payment['additionaldescription'] . $newAdditionalDescription;
                    break;
                }
            }
            $view->assign('sPayments', $payments);
        }
        $user = $view->getAssign('sUserData');

        if (!empty($user['additional']['payment']['name']) && $user['additional']['payment']['name'] === 'paypal') {
            $user['additional']['payment']['description'] = $newDescription;
            $user['additional']['payment']['additionaldescription'] = $newAdditionalDescription;
            $view->assign('sUserData', $user);
        }

        if (method_exists($this->paypalBootstrap, 'getPayment')) {
            $payPalPaymentId = $this->paypalBootstrap->getPayment()->getId();
        } else {
            //fallback for SwagPaymentPaypal version < 3.3.4
            $payPalPaymentId = $this->paypalBootstrap->Payment()->getId();
        }
        $view->assign('PayPalPaymentId', $payPalPaymentId);

        $allowedActions = array(
            'confirm',
            'shippingPayment',
            'saveShippingPayment',
        );

        // Check action
        if (!in_array($request->getActionName(), $allowedActions, true)) {
            return;
        }

        if ($request->get('ppplusRedirect')) {
            $controller->redirect(
                array(
                    'controller' => 'checkout',
                    'action' => 'payment',
                    'sAGB' => 1
                )
            );

            return;
        }

        // Paypal plus conditions
        $user = $view->getAssign('sUserData');
        $countries = $this->bootstrap->Config()->get('paypalPlusCountries');
        if ($countries instanceof \Enlight_Config) {
            $countries = $countries->toArray();
        } else {
            $countries = (array) $countries;
        }

        if (!empty($this->session->PaypalResponse['TOKEN']) // PP-Express
            || empty($user['additional']['payment']['name'])
            || !in_array($user['additional']['country']['id'], $countries)
        ) {
            return;
        }

        $view->assign('cameFromStep2', $cameFromStep2);
        $this->session->offsetUnset('PayPalPlusCameFromStep2');

        $this->bootstrap->registerMyTemplateDir();
        if ($request->getActionName() === 'shippingPayment' || !$cameFromStep2) {
            $this->onPaypalPlus($controller);
        }

        if ($templateVersion < 3) { // emotion template
            $view->extendsTemplate('frontend/payment_paypal_plus/checkout.tpl');
        }
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPreRedirectToPayPal($args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();
        $user = $view->getAssign('sUserData');
        $paymentId = $this->session->get('PaypalPlusPayment');

        $requestData = array(
            array(
                'op' => 'add',
                'path' => '/transactions/0/item_list/shipping_address',
                'value' => $this->getShippingAddress($user)
            )
        );

        $uri = 'payments/payment/' . $paymentId;
        $view->loadTemplate('');

        try {
            $this->restClient->patch($uri, $requestData);
        } catch (\Exception $e) {
            echo json_encode(['success' => false]);
        }

        echo json_encode(['success' => true]);

        return true;
    }

    /**
     * @param \Enlight_View_Default $view
     * @param int $templateVersion
     */
    private function addInvoiceInstructionsToView($view, $templateVersion)
    {
        $paymentInstructionProvider = new PaymentInstructionProvider($this->bootstrap->get('db'));
        $orderData = $view->getAssign();

        $instruction = $paymentInstructionProvider->getInstructionsByOrderNumberAndTransactionId($orderData['sOrderNumber'], $orderData['sTransactionumber']);
        $view->assign('instruction', $instruction);
        $payment = $orderData['sPayment'];

        if ($payment['name'] === 'paypal') {
            $payment['description'] = $this->bootstrap->Config()->get('paypalPlusDescription', '');
            $view->assign('sPayment', $payment);
        }

        $this->bootstrap->registerMyTemplateDir();

        if ($templateVersion < 3) {
            $view->extendsTemplate('frontend/checkout/emotion/finish.tpl');
        }
    }

    /**
     * @param Controller $controller
     */
    private function onPaypalPlus(Controller $controller)
    {
        $router = $controller->Front()->Router();
        $view = $controller->View();

        $cancelUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'cancel',
                'forceSecure' => true,
            )
        );

        $returnUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'return',
                'forceSecure' => true,
            )
        );

        $profile = $this->getProfile();

        $uri = 'payments/payment';
        $params = array(
            'intent' => 'sale',
            'experience_profile_id' => $profile['id'],
            'payer' => array(
                'payment_method' => 'paypal'
            ),
            'transactions' => $this->getTransactionData($view->getAssign('sBasket'), $view->getAssign('sUserData')),
            'redirect_urls' => array(
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl
            ),
        );
        $payment = $this->restClient->create($uri, $params);

        if (!empty($payment['links'][1]['href'])) {
            $view->assign('PaypalPlusApprovalUrl', $payment['links'][1]['href']);
            $view->assign('PaypalPlusModeSandbox', $this->config->get('paypalSandbox'));
            $view->assign('PaypalLocale', $this->paypalBootstrap->getLocaleCode());

            $this->session->PaypalPlusPayment = $payment['id'];
        }
    }

    /**
     * @return array
     */
    private function getProfile()
    {
        if (!isset($this->session['PaypalProfile'])) {
            $profile = $this->getProfileData();
            $uri = 'payment-experience/web-profiles';
            $profileList = $this->restClient->get($uri);
            foreach ($profileList as $entry) {
                if ($entry['name'] == $profile['name']) {
                    $this->restClient->put("$uri/{$entry['id']}", $profile);
                    $this->session['PaypalProfile'] = array('id' => $entry['id']);
                    break;
                }
            }
            if (!isset($this->session['PaypalProfile'])) {
                $this->session['PaypalProfile'] = $this->restClient->create($uri, $profile);
            }
        }

        return $this->session['PaypalProfile'];
    }

    /**
     * @return array
     */
    private function getProfileData()
    {
        $template = $this->bootstrap->get('template');
        $router = $this->bootstrap->get('router');
        $shop = $this->bootstrap->get('shop');

        $localeCode = $this->paypalBootstrap->getLocaleCode(true);

        $profileName = "{$shop->getHost()}{$shop->getBasePath()}[{$shop->getId()}]";

        $shopName = $this->config->get('paypalBrandName') ?: $this->bootstrap->get('config')->get('shopName');

        $logoImage = $this->config->get('paypalLogoImage');
        $logoImage = 'string:{link file=' . var_export($logoImage, true) . ' fullPath}';
        $logoImage = $template->fetch($logoImage);

        $notifyUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'notify',
                'forceSecure' => true
            )
        );

        return array(
            'name' => $profileName,
            'presentation' => array(
                'brand_name' => $shopName,
                'logo_image' => $logoImage,
                'locale_code' => $localeCode
            ),
            'input_fields' => array(
                'allow_note' => true,
                'no_shipping' => 0,
                'address_override' => 1
            ),
            'flow_config' => array(
                'bank_txn_pending_url' => $notifyUrl
            ),
        );
    }

    /**
     * @param $basket
     * @param $user
     * @return array
     */
    private function getTransactionData($basket, $user)
    {
        $total = $this->getTotalAmount($basket, $user);
        $shipping = $this->getTotalShipment($basket, $user);

        return array(
            array(
                'amount' => array(
                    'currency' => $this->getCurrency(),
                    'total' => number_format($total, 2, '.', ','),
                    'details' => array(
                        'shipping' => number_format($shipping, 2, '.', ','),
                        'subtotal' => number_format($total - $shipping, 2, '.', ','),
                        'tax' => number_format(0, 2, '.', ','),
                    )
                ),
                'item_list' => array(
                    'items' => $this->getItemList($basket, $user)
                ),
            )
        );
    }

    /**
     * @param $basket
     * @param $user
     * @return string
     */
    private function getTotalAmount($basket, $user)
    {
        if (!empty($user['additional']['charge_vat'])) {
            return empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
        } else {
            return $basket['AmountNetNumeric'];
        }
    }

    /**
     * @param $basket
     * @param $user
     * @return mixed
     */
    private function getTotalShipment($basket, $user)
    {
        if (!empty($user['additional']['charge_vat'])) {
            return $basket['sShippingcostsWithTax'];
        } else {
            return str_replace(',', '.', $basket['sShippingcosts']);
        }
    }

    /**
     * @return string
     */
    private function getCurrency()
    {
        return $this->bootstrap->get('currency')->getShortName();
    }

    /**
     * @param $basket
     * @param $user
     * @return array
     */
    private function getItemList($basket, $user)
    {
        $list = array();
        $currency = $this->getCurrency();
        foreach ($basket['content'] as $basketItem) {
            if (!empty($user['additional']['charge_vat']) && !empty($basketItem['amountWithTax'])) {
                $amount = round($basketItem['amountWithTax'], 2);
                $quantity = 1;
            } else {
                $amount = str_replace(',', '.', $basketItem['amount']);
                $quantity = (int) $basketItem['quantity'];
                $amount = $amount / $basketItem['quantity'];
            }
            $amount = round($amount, 2);
            $list[] = array(
                'name' => $basketItem['articlename'],
                'sku' => $basketItem['ordernumber'],
                'price' => number_format($amount, 2, '.', ','),
                'currency' => $currency,
                'quantity' => $quantity,
            );
        }

        return $list;
    }

    /**
     * @param $user
     * @return array
     */
    private function getShippingAddress($user)
    {
        $address = array(
            'recipient_name' => $user['shippingaddress']['firstname'] . ' ' . $user['shippingaddress']['lastname'],
            'line1' => trim($user['shippingaddress']['street'] . ' ' . $user['shippingaddress']['streetnumber']),
            'city' => $user['shippingaddress']['city'],
            'postal_code' => $user['shippingaddress']['zipcode'],
            'country_code' => $user['additional']['countryShipping']['countryiso'],
        );

        return $address;
    }
}
