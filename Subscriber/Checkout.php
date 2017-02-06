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
use Enlight_View_Default as View;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Shopware\Components\Logger;
use Shopware\SwagPaymentPaypalPlus\Components\APIValidator;
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
     * @var Logger
     */
    private $pluginLogger;

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
        $this->pluginLogger = $bootstrap->get('pluginlogger');
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckoutSecure',
            'Enlight_Controller_Action_Frontend_Checkout_PreRedirect' => 'onPreRedirectToPayPal'
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onPostDispatchCheckoutSecure($args)
    {
        $controller = $args->getSubject();
        $request = $controller->Request();
        $view = $controller->View();

        if ($controller->Response()->isRedirect()) {
            return;
        }

        $cameFromStep2 = $this->session->offsetGet('PayPalPlusCameFromStep2');

        if (!$cameFromStep2 && $request->getActionName() !== 'preRedirect') {
            $this->session->offsetUnset('PaypalPlusPayment');
        }

        /** @var $shop \Shopware\Models\Shop\Shop */
        $shop = $this->bootstrap->get('shop');
        $templateVersion = $shop->getTemplate()->getVersion();

        if ($request->getActionName() === 'finish') {
            $this->addInvoiceInstructionsToView($view, $templateVersion);
        }

        $allowedActions = array(
            'confirm',
            'shippingPayment'
        );

        // Check action
        if (!in_array($request->getActionName(), $allowedActions, true)) {
            $this->session->offsetUnset('PayPalPlusCameFromStep2');
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
            $countries = (array)$countries;
        }

        if (!empty($this->session->PaypalResponse['TOKEN']) // PP-Express
            || empty($user['additional']['payment']['name'])
            || !in_array($user['additional']['country']['id'], $countries)
        ) {
            return;
        }

        $this->bootstrap->registerMyTemplateDir();
        if ($templateVersion < 3) { // emotion template
            $view->extendsTemplate('frontend/payment_paypal_plus/checkout.tpl');
        }

        $this->addTemplateVariables($view);

        if ($request->getActionName() === 'shippingPayment') {
            $this->session->offsetSet('PayPalPlusCameFromStep2', true);
            $this->onPaypalPlus($controller);

            return;
        }

        $view->assign('cameFromStep2', $cameFromStep2);
        $this->session->offsetUnset('PayPalPlusCameFromStep2');

        if (!$cameFromStep2 && $user['additional']['payment']['name'] === 'paypal') {
            $this->onPaypalPlus($controller);
        }
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return bool
     * @throws \Exception
     */
    public function onPreRedirectToPayPal($args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();
        $userData = $view->getAssign('sUserData');
        $paymentId = $this->session->offsetGet('PaypalPlusPayment');
        $payment = $userData['additional']['payment'];

        $this->session->sOrderVariables['sPayment'] = $payment;
        $this->session->sOrderVariables['sUserData']['additional']['payment'] = $payment;

        $requestData = array(
            array(
                'op' => 'add',
                'path' => '/transactions/0/item_list/shipping_address',
                'value' => $this->getShippingAddress($userData)
            )
        );

        $uri = 'payments/payment/' . $paymentId;
        $view->loadTemplate('');

        try {
            $this->restClient->patch($uri, $requestData);
        } catch (Exception $e) {
            $this->logException('An error occurred on patching the address to the payment', $e);
            throw $e;
        }

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
        $view->assign('payPalPlusInvoiceInstruction', $instruction);
        $payment = $orderData['sPayment'];

        if ($payment['name'] !== 'paypal') {
            return;
        }

        $validator = new APIValidator($this->restClient);

        if ($validator->isAPIAvailable()) {
            $payment['description'] = $this->bootstrap->Config()->get('paypalPlusDescription', '');
            $view->assign('sPayment', $payment);
        }

        $this->bootstrap->registerMyTemplateDir();

        if ($templateVersion < 3) {
            $view->extendsTemplate('frontend/checkout/emotion/finish.tpl');
        }
    }

    /**
     * extends the PayPal description
     *
     * @param View $view
     */
    private function addTemplateVariables(View $view)
    {
        $newDescription = $this->bootstrap->Config()->get('paypalPlusDescription', '');
        $newAdditionalDescription = $this->bootstrap->Config()->get('paypalPlusAdditionalDescription', '');
        $payments = $view->getAssign('sPayments');
        $validator = new APIValidator($this->restClient);

        if (empty($payments)) {
            return;
        }

        foreach ($payments as $key => $payment) {
            if ($payment['name'] !== 'paypal' || !$validator->isAPIAvailable()) {
                continue;
            }

            //Update the payment description
            $payments[$key]['description'] = $newDescription;
            $payments[$key]['additionaldescription'] = $payment['additionaldescription'] . $newAdditionalDescription;

            break;
        }

        $view->assign('sPayments', $payments);

        $user = $view->getAssign('sUserData');
        if (!empty($user['additional']['payment']['name']) && $user['additional']['payment']['name'] === 'paypal' && $validator->isAPIAvailable()) {
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

        $payment = array();
        try {
            $payment = $this->restClient->create($uri, $params);
        } catch (Exception $e) {
            $this->logException('An error occurred on creating a payment', $e);
        }

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
            $profileList = array();
            try {
                $profileList = $this->restClient->get($uri);
            } catch (Exception $e) {
                $this->logException('An error occurred getting the experience profiles', $e);
            }

            foreach ($profileList as $entry) {
                if ($entry['name'] == $profile['name']) {
                    $this->restClient->put("$uri/{$entry['id']}", $profile);
                    $this->session['PaypalProfile'] = array('id' => $entry['id']);
                    break;
                }
            }

            if (!isset($this->session['PaypalProfile'])) {
                $payPalProfile = null;
                try {
                    $payPalProfile = $this->restClient->create($uri, $profile);
                } catch (Exception $e) {
                    $this->logException('An error occurred on creating an experience profiles', $e);
                }
                $this->session['PaypalProfile'] = $payPalProfile;
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

        // (max length 127)
        if (strlen($shopName) > 127) {
            $shopName = substr($shopName, 0, 124) . '...';
        }

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
        $lastCustomProduct = null;

        foreach ($basket['content'] as $basketItem) {
            $sku = $basketItem['ordernumber'];
            $name = $basketItem['articlename'];
            $quantity = (int)$basketItem['quantity'];
            if (!empty($user['additional']['charge_vat']) && !empty($basketItem['amountWithTax'])) {
                $amount = round($basketItem['amountWithTax'], 2);
            } else {
                $amount = str_replace(',', '.', $basketItem['amount']);
            }

            // If more than 2 decimal places
            if (round($amount / $quantity, 2) * $quantity != $amount) {
                if ($quantity != 1) {
                    $name = $quantity . 'x ' . $name;
                }
                $quantity = 1;
            } else {
                $amount = round($amount / $quantity, 2);
            }

            // Add support for custom products
            if (!empty($basketItem['customProductMode'])) {
                switch ($basketItem['customProductMode']) {
                    case 1: // Product
                        $lastCustomProduct = count($list);
                        break;
                    case 2: // Option
                        if (empty($sku) && isset($list[$lastCustomProduct])) {
                            $sku = $list[$lastCustomProduct]['sku'];
                        }
                        break;
                    case 3; // Value
                        $last = count($list) - 1;
                        if (isset($list[$last])) {
                            if (strpos($list[$last]['name'], ': ') === false) {
                                $list[$last]['name'] .= ': ' . $name;
                            } else {
                                $list[$last]['name'] .= ', ' . $name;
                            }
                            $list[$last]['price'] += $amount;
                        }
                        continue 2;
                    default:
                        break;
                }
            }

            $list[] = array(
                'name' => $name,
                'sku' => $sku,
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

    /**
     * Writes an exception to the plugin log.
     *
     * @param string $message
     * @param Exception $e
     */
    private function logException($message, Exception $e)
    {
        $context = ['exception' => $e];
        if ($e instanceof RequestException) {
            $context['response'] = $e->getResponse();
        }
        $this->pluginLogger->error($message . ': ' . $e->getMessage(), $context);
    }
}
