<?php
/**
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
use Shopware_Plugins_Frontend_SwagPaymentPaypal_Bootstrap as PaypalBootstrap;
use Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap as Bootstrap;

/**
 * Class Checkout
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
     * @var bool
     */
    private $isShopware53;

    /**
     * @param Bootstrap $bootstrap
     * @param bool      $isShopware53
     */
    public function __construct(Bootstrap $bootstrap, $isShopware53)
    {
        $this->bootstrap = $bootstrap;
        $this->paypalBootstrap = $bootstrap->Collection()->get('SwagPaymentPaypal');
        $this->config = $this->paypalBootstrap->Config();
        $this->session = $bootstrap->get('session');
        $this->restClient = $bootstrap->get('paypal_plus.rest_client');
        $this->pluginLogger = $bootstrap->get('pluginlogger');
        $this->isShopware53 = $isShopware53;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckoutSecure',
            'Enlight_Controller_Action_Frontend_Checkout_PreRedirect' => 'onPreRedirectToPayPal',
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

        $view->assign('isShopware53', $this->isShopware53);

        /** @var $shop \Shopware\Models\Shop\Shop */
        $shop = $this->bootstrap->get('shop');
        $templateVersion = $shop->getTemplate()->getVersion();

        if ($request->getActionName() === 'finish') {
            $this->addInvoiceInstructionsToView($view, $templateVersion);
        }

        $allowedActions = array(
            'confirm',
            'shippingPayment',
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
                    'sAGB' => 1,
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
     *
     * @throws \Exception
     *
     * @return bool
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

        $customerComment = trim(strip_tags($controller->Request()->getParam('sComment')));
        if ($customerComment) {
            $this->session['sComment'] = $customerComment;
        }

        $requestData = array(
            array(
                'op' => 'add',
                'path' => '/transactions/0/item_list/shipping_address',
                'value' => $this->getShippingAddress($userData),
            ),
            array(
                'op' => 'replace',
                'path' => '/payer/payer_info',
                'value' => $this->getPayerInfo($userData),
            ),
        );

        $uri = 'payments/payment/' . $paymentId;
        $this->bootstrap->get('front')->Plugins()->ViewRenderer()->setNoRender();

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
     * @param int                   $templateVersion
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
                'payment_method' => 'paypal',
            ),
            'transactions' => $this->getTransactionData($view->getAssign('sBasket'), $view->getAssign('sUserData')),
            'redirect_urls' => array(
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
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
        if ($logoImage !== null) {
            if ($this->paypalBootstrap->isShopware51() && !$this->paypalBootstrap->isShopware52()) {
                /** @var \Shopware\Bundle\MediaBundle\MediaService $mediaService */
                $mediaService = $this->bootstrap->get('shopware_media.media_service');
                $logoImage = $mediaService->getUrl($logoImage);
            }

            $logoImage = 'string:{link file=' . var_export($logoImage, true) . ' fullPath}';
            $logoImage = $template->fetch($logoImage);
        }

        $notifyUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'notify',
                'forceSecure' => true,
            )
        );

        return array(
            'name' => $profileName,
            'presentation' => array(
                'brand_name' => $shopName,
                'logo_image' => $logoImage,
                'locale_code' => $localeCode,
            ),
            'input_fields' => array(
                'allow_note' => true,
                'no_shipping' => 0,
                'address_override' => 1,
            ),
            'flow_config' => array(
                'bank_txn_pending_url' => $notifyUrl,
            ),
        );
    }

    /**
     * @param $basket
     * @param $user
     *
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
                    ),
                ),
                'item_list' => array(
                    'items' => $this->getItemList($basket, $user),
                ),
            ),
        );
    }

    /**
     * @param $basket
     * @param $user
     *
     * @return string
     */
    private function getTotalAmount($basket, $user)
    {
        if (!empty($user['additional']['charge_vat'])) {
            return empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
        }

        return $basket['AmountNetNumeric'];
    }

    /**
     * @param $basket
     * @param $user
     *
     * @return mixed
     */
    private function getTotalShipment($basket, $user)
    {
        if (!empty($user['additional']['charge_vat'])) {
            return $basket['sShippingcostsWithTax'];
        }

        return str_replace(',', '.', $basket['sShippingcosts']);
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
     *
     * @return array
     */
    private function getItemList($basket, $user)
    {
        $list = array();
        $currency = $this->getCurrency();
        $lastCustomProduct = null;

        $index = 0;
        foreach ($basket['content'] as $basketItem) {
            $sku = $basketItem['ordernumber'];
            $name = $basketItem['articlename'];
            $quantity = (int) $basketItem['quantity'];
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

            //In the following part, we modify the CustomProducts positions.
            //By default, custom products may add alot of different positions to the basket, which would probably reach
            //the items limit of PayPal. Therefore, we group the values with the options.
            //Actually, that causes a loss of quantity precision but there is no other way around this issue but this.
            if (!empty($basketItem['customProductMode'])) {
                //A value indicating if the surcharge of this position is only being added once
                $isSingleSurcharge = $basketItem['customProductIsOncePrice'];

                switch ($basketItem['customProductMode']) {
                    /*
                     * The current basket item is of type Option (a group of values)
                     * This will be our first starting point.
                     * In this procedure we fake the amount by simply adding a %value%x to the actual name of the group.
                     * Further more, we add a : to the end of the name (if a value follows this option) to indicate that more values follow.
                     * At the end, we set the quantity to 1, so PayPal doesn't calculate the total amount. That would cause calculation errors, since we calculate the
                     * whole position already.
                     */
                    case 2: //Option
                        $nextProduct = $basket['content'][$index + 1];

                        $name = $quantity . 'x ' . $name;

                        //Another value is following?
                        if ($nextProduct && '3' === $nextProduct['customProductMode']) {
                            $name .= ': ';
                        }

                        //Calculate the total price of this option
                        if (!$isSingleSurcharge) {
                            $amount *= $quantity;
                        }

                        $quantity = 1;
                        break;

                    /*
                     * This basket item is of type Value.
                     * In this procedure we calculate the actual price of the value and add it to the option price.
                     * Further more, we add a comma to the end of the value (if another value is following) to improve the readability on the PayPal page.
                     * Afterwards, we set the quantity to 0, so that the basket item is not being added to the list. We don't have to add it again,
                     * since it's already grouped to the option.
                     */
                    case 3: //Value
                        //The last option that has been added to the final list.
                        //This value will be grouped to it.
                        $lastGroup = &$list[count($list) - 1];
                        $nextProduct = $basket['content'][$index + 1];

                        if ($lastGroup) {
                            //Check if another value is following, if so, add a comma to the end of the name.
                            if ($nextProduct && '3' === $nextProduct['customProductMode']) {
                                //Another value is following
                                $lastGroup['name'] .= $name . ', ';
                            } else {
                                //This is the last value in this option
                                $lastGroup['name'] .= $name;
                            }

                            //Calculate the total price.
                            $lastGroup['price'] += $isSingleSurcharge ? $amount : $amount * $quantity;

                            //Don't add it to the final list
                            $quantity = 0;
                        }
                        break;
                }
            }

            if ($quantity !== 0) {
                $list[] = array(
                    'name' => $name,
                    'sku' => $sku,
                    'price' => number_format($amount, 2, '.', ','),
                    'currency' => $currency,
                    'quantity' => $quantity,
                );
            }

            $index++;
        }

        return $list;
    }

    /**
     * @param array $user
     *
     * @return array
     */
    private function getShippingAddress(array $user)
    {
        $address = array(
            'recipient_name' => $user['shippingaddress']['firstname'] . ' ' . $user['shippingaddress']['lastname'],
            'line1' => trim($user['shippingaddress']['street'] . ' ' . $user['shippingaddress']['streetnumber']),
            'city' => $user['shippingaddress']['city'],
            'postal_code' => $user['shippingaddress']['zipcode'],
            'country_code' => $user['additional']['countryShipping']['countryiso'],
            'state' => $user['additional']['stateShipping']['shortcode'],
        );

        return $address;
    }

    /**
     * @param array $user
     *
     * @return array
     */
    private function getPayerInfo(array $user)
    {
        $payerInfo = array(
            'country_code' => $user['additional']['country']['countryiso'],
            'email' => $user['additional']['user']['email'],
            'first_name' => $user['billingaddress']['firstname'],
            'last_name' => $user['billingaddress']['lastname'],
            'phone' => $user['billingaddress']['phone'],
            'billing_address' => $this->getBillingAddress($user),
        );

        return $payerInfo;
    }

    /**
     * @param array $user
     *
     * @return array
     */
    private function getBillingAddress(array $user)
    {
        $billingAddress = array(
            'line1' => $user['billingaddress']['street'],
            'postal_code' => $user['billingaddress']['zipcode'],
            'city' => $user['billingaddress']['city'],
            'country_code' => $user['additional']['country']['countryiso'],
            'state' => $user['additional']['state']['shortcode'],
        );

        return $billingAddress;
    }

    /**
     * Writes an exception to the plugin log.
     *
     * @param string    $message
     * @param Exception $e
     */
    private function logException($message, Exception $e)
    {
        $context = array('exception' => $e);
        if ($e instanceof RequestException) {
            $context['response'] = $e->getResponse();
        }
        $this->pluginLogger->error($message . ': ' . $e->getMessage(), $context);
    }
}
