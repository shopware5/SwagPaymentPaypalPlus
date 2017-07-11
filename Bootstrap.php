<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Shopware\SwagPaymentPaypalPlus\Components\AdditionalTableInstaller;
use Shopware\SwagPaymentPaypalPlus\Components\DocumentInstaller;
use Shopware\SwagPaymentPaypalPlus\Components\InvoiceContentProvider;
use Shopware\SwagPaymentPaypalPlus\Components\PaymentInstructionProvider;
use Shopware\SwagPaymentPaypalPlus\Components\RestClient;

class Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Register the autoloader
     */
    public function afterInit()
    {
        $this->get('loader')->registerNamespace('Shopware\SwagPaymentPaypalPlus', __DIR__ . DIRECTORY_SEPARATOR);
    }

    /**
     * Installs the plugin
     *
     * @return bool
     * @throws Exception
     */
    public function install()
    {
        // Check if PHP version matches
        if (version_compare(phpversion(), '5.4', '<')) {
            throw new Exception("This plugin requires PHP 5.4 or a later version");
        }

        $this->createMyEvents();
        $this->createMyForm();

        $documentInstaller = new DocumentInstaller($this->get('db'));
        $documentInstaller->installDocuments();

        $tableInstaller = new AdditionalTableInstaller($this->get('db'));
        $tableInstaller->installAdditionalDatabaseTable();

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        $this->secureUninstall();
        $this->removeMyAttributes();

        return true;
    }

    /**
     * @return bool
     */
    public function secureUninstall()
    {
        return true;
    }

    /**
     * @param string $version
     * @return array
     * @throws Exception
     */
    public function update($version)
    {
        // Check if PHP version matches
        if (version_compare(phpversion(), '5.4', '<')) {
            throw new Exception("This plugin requires PHP 5.4 or a later version");
        }

        $this->createMyForm();
        $this->createMyEvents();

        $documentInstaller = new DocumentInstaller($this->get('db'));
        $documentInstaller->installDocuments();

        $tableInstaller = new AdditionalTableInstaller($this->get('db'));
        $tableInstaller->installAdditionalDatabaseTable();

        return array(
            'success' => true,
            'invalidateCache' => $this->getInvalidateCacheArray()
        );
    }

    /**
     * @return array
     */
    public function enable()
    {
        return array(
            'success' => true,
            'invalidateCache' => $this->getInvalidateCacheArray()
        );
    }

    /**
     * @return array
     */
    public function disable()
    {
        return array(
            'success' => true,
            'invalidateCache' => $this->getInvalidateCacheArray()
        );
    }

    /**
     * Legacy wrapper for di container
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (Shopware::VERSION != '___VERSION___') {
            if (version_compare(Shopware::VERSION, '4.3.3', '<') && $name == 'dbal_connection') {
                return $this->get('models')->getConnection();
            }

            if (version_compare(Shopware::VERSION, '4.2.0', '<')) {
                if ($name == 'loader') {
                    return $this->Application()->Loader();
                }

                $name = ucfirst($name);

                return $this->Application()->Bootstrap()->getResource($name);
            }
        }

        return parent::get($name);
    }

    /**
     * Creates and subscribe the events and hooks.
     */
    private function createMyEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout',
            'onPostDispatchCheckoutSecure'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_Frontend_Checkout_PreRedirect',
            'onPreRedirectToPayPal'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PreDispatch_Frontend_PaymentPaypal',
            'onPreDispatchPaymentPaypal'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_Frontend_PaymentPaypal_Webhook',
            'onPaymentPaypalWebhook'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_Frontend_PaymentPaypal_PlusRedirect',
            'onPaymentPaypalPlusRedirect'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Account',
            'onPostDispatchAccount'
        );
        $this->subscribeEvent(
            'Theme_Compiler_Collect_Plugin_Javascript',
            'onCollectJavascript'
        );
        $this->subscribeEvent(
            'Shopware_Components_Document::assignValues::after',
            'onBeforeRenderDocument'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Config',
            'onPostDispatchConfig'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Backend_Order',
            'onPostDispatchOrder'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Backend_PaymentPaypal',
            'onPostDispatchPaymentPaypal'
        );
        $this->subscribeEvent(
            'Theme_Compiler_Collect_Plugin_Less',
            'addLessFiles'
        );
        $this->subscribeEvent(
            'Enlight_Bootstrap_InitResource_paypal_plus.rest_client',
            'onInitRestClient'
        );
    }

    /**
     * Creates and stores the payment config form.
     */
    private function createMyForm()
    {
        $form = $this->Form();

        $form->setElement(
            'select',
            'paypalPlusCountries',
            array(
                'label' => 'Länder bei denen „PayPal PLUS“ angezeigt wird',
                'value' => array(2),
                'store' => 'base.Country',
                'multiSelect' => true,
                'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        $form->setElement(
            'boolean',
            'paypalHidePaymentSelection',
            array(
                'label' => 'Zahlungsart-Auswahl im Bestellabschluss ausblenden (Shopware 4)',
                'value' => true,
                'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        $form->setElement(
            'text',
            'paypalPlusDescription',
            array(
                'label' => 'Zahlungsart-Bezeichnung überschreiben',
                'value' => 'PayPal, Lastschrift oder Kreditkarte',
                'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        $form->setElement(
            'text',
            'paypalPlusAdditionalDescription',
            array(
                'label' => 'Zahlungsart-Beschreibung ergänzen',
                'value' => 'Zahlung per Lastschrift oder Kreditkarte ist auch ohne PayPal-Konto möglich.',
                'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
    }

    private function removeMyAttributes()
    {
        /** @var $modelManager \Shopware\Components\Model\ModelManager */
        $modelManager = $this->get('models');
        try {
            $modelManager->removeAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_media'
            );
        } catch (Exception $e) {
        }
        try {
            $modelManager->removeAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_active'
            );
        } catch (Exception $e) {
        }
        try {
            $modelManager->removeAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_redirect'
            );
        } catch (Exception $e) {
        }
        try {
            $modelManager->generateAttributeModels(array('s_core_paymentmeans_attributes'));
        } catch (Exception $e) {
        }
    }

    public function registerMyTemplateDir()
    {
        $this->get('template')->addTemplateDir(__DIR__ . '/Views/', 'paypal_plus');
    }

    /**
     * @return RestClient
     */
    public function onInitRestClient()
    {
        $rootDir = Shopware()->Container()->getParameter('kernel.root_dir');
        $certPath = $rootDir . '/engine/Shopware/Components/HttpClient/cacert.pem';
        if (!is_readable($certPath)) {
            $certPath = true;
        }

        $client = new RestClient(
            $this->Collection()->get('SwagPaymentPaypal')->Config(),
            Shopware()->Container()->get('shopware.cache_manager'),
            $certPath,
            Shopware()->Shop()
        );

        return $client;
    }

    /**
     * Provide the file collection for less
     *
     * @param Enlight_Event_EventArgs $args
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function addLessFiles(Enlight_Event_EventArgs $args)
    {
        $less = new \Shopware\Components\Theme\LessDefinition(
            array(),
            array(__DIR__ . '/Views/frontend/_public/src/less/all.less'),
            __DIR__
        );

        return new Doctrine\Common\Collections\ArrayCollection(array($less));
    }

    /**
     * @param $args
     */
    public function onPostDispatchCheckoutSecure($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\Checkout($this, $this->assertMinimumVersion('5.3.0'));
        $subscriber->onPostDispatchCheckoutSecure($args);
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPreRedirectToPayPal($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\Checkout($this, $this->assertMinimumVersion('5.3.0'));

        return $subscriber->onPreRedirectToPayPal($args);
    }

    /**
     * @param $args
     */
    public function onPreDispatchPaymentPaypal($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PaymentPaypal(
            $this->get('paypal_plus.rest_client'),
            $this->get('session'),
            $this->Collection()->get('SwagPaymentPaypal'),
            $this->get('pluginlogger')
        );
        $subscriber->onPreDispatchPaymentPaypal($args);
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPaymentPaypalWebhook($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\Webhook();

        return $subscriber->onPaymentPaypalWebhook($args);
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onPaymentPaypalPlusRedirect($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PlusRedirect($this);

        return $subscriber->onPaypalPlusRedirect($args);
    }

    /**
     * @param $args
     */
    public function onPostDispatchAccount($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\Account($this);
        $subscriber->onPostDispatchAccount($args);
    }

    /**
     * @param Enlight_Event_EventArgs $arguments
     */
    public function onPostDispatchConfig(Enlight_Event_EventArgs $arguments)
    {
        /* @var Enlight_View_Default $view */
        $view = $arguments->getSubject()->View();

        //if the controller action name equals "load" we have to load all application components.
        if ($arguments->getRequest()->getActionName() === 'load') {
            $view->addTemplateDir($this->Path() . 'Views/');
            $view->extendsTemplate('backend/config/view/form/document_paypal_plus.js');
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchOrder(Enlight_Event_EventArgs $args)
    {
        /* @var Enlight_View_Default $view */
        $view = $args->getSubject()->View();

        if ($args->getRequest()->getActionName() === 'getList') {
            $orders = $view->getAssign('data');
            $orderNumbers = array_map(function ($order) {
                return $order['number'];
            }, $orders);
            $payPalPlusPuiOrderNumbers = $this->getPuiOrderNumbers($orderNumbers);

            foreach ($orders as &$order) {
                if (in_array($order['number'], $payPalPlusPuiOrderNumbers)) {
                    $order['payment']['description'] = $order['payment']['description'] . ' Plus (R)';
                }
            }

            $view->assign('data', $orders);
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchPaymentPaypal(Enlight_Event_EventArgs $args)
    {
        /* @var Enlight_View_Default $view */
        $view = $args->getSubject()->View();

        if ($args->getRequest()->getActionName() === 'getList') {
            $orders = $view->getAssign('data');
            $orderNumbers = array_map(function ($order) {
                return $order['orderNumber'];
            }, $orders);
            $payPalPlusPuiOrderNumbers = $this->getPuiOrderNumbers($orderNumbers);

            foreach ($orders as &$order) {
                if (in_array($order['orderNumber'], $payPalPlusPuiOrderNumbers)) {
                    $order['paymentDescription'] = $order['paymentDescription'] . ' Plus (R)';
                }
            }

            $view->assign('data', $orders);
        }
    }

    /**
     * @param Enlight_Hook_HookArgs $args
     */
    public function onBeforeRenderDocument(Enlight_Hook_HookArgs $args)
    {
        /* @var Shopware_Components_Document $document */
        $document = $args->getSubject();
        $order = $document->_order;

        if ($order->payment['name'] != 'paypal') {
            return;
        }

        $orderNumber = $order->order->ordernumber;
        $puiOrderNumber = $this->getPuiOrderNumbers(array($orderNumber));

        if (empty($puiOrderNumber)) {
            return;
        }

        /* @var Smarty_Data $view */
        $view = $document->_view;

        $orderData = $view->getTemplateVars('Order');
        $orderNumber = $orderData['_order']['ordernumber'];
        $transactionId = $orderData['_order']['transactionID'];
        $paymentInstructionProvider = new PaymentInstructionProvider($this->get('db'));
        $paymentInstruction = $paymentInstructionProvider->getInstructionsByOrderNumberAndTransactionId($orderNumber, $transactionId);

        //The manual bank transfer is a special case in invoice. This case does not require to have
        //instructions on the invoice document
        if (empty($paymentInstruction) || $paymentInstruction['instruction_type'] === 'MANUAL_BANK_TRANSFER') {
            return;
        }

        $containers = $view->getTemplateVars('Containers');

        if (!isset($containers['Paypal_Content_Info'])) {
            return;
        }

        $invoiceContentProvider = new InvoiceContentProvider($this->get('db'));
        $rawFooter = $invoiceContentProvider->getPayPalInvoiceContentInfo($containers, $orderData);

        $containers['Paypal_Content_Info']['value'] = $rawFooter['value'];

        // is necessary to get the data in the invoice template
        $view->assign('Containers', $containers);

        $document->_template->addTemplateDir(dirname(__FILE__) . '/Views/');
        $document->_template->assign('instruction', (array) $paymentInstruction);

        $containerData = $view->getTemplateVars('Containers');
        $containerData['Footer'] = $containerData['Paypal_Footer'];
        $containerData['Content_Info'] = $containerData['Paypal_Content_Info'];
        $containerData['Content_Info']['value'] = $document->_template->fetch('string:' . $containerData['Content_Info']['value']);
        $containerData['Content_Info']['style'] = '}' . $containerData['Content_Info']['style'] . ' #info {';

        $view->assign('Containers', $containerData);
    }

    /**
     * @return ArrayCollection
     */
    public function onCollectJavascript()
    {
        $jsPath = array(__DIR__ . '/Views/frontend/_public/src/js/jquery.payment-wall-shipping-payment.js');

        return new ArrayCollection($jsPath);
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return 'PayPal PLUS';
    }

    /**
     * Returns the version of plugin as string.
     *
     * @throws Exception
     * @return string
     */
    public function getVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
        if ($info) {
            return $info['currentVersion'];
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel()
        );
    }

    /**
     * @return array
     */
    private function getInvalidateCacheArray()
    {
        return array('config', 'backend', 'proxy', 'template', 'theme');
    }

    /**
     * @param string[] $orderNumbers
     * @return array
     */
    private function getPuiOrderNumbers($orderNumbers)
    {
        $sql = "SELECT ordernumber
                FROM s_payment_paypal_plus_payment_instruction
                WHERE ordernumber IN(:orderNumbers);";

        /** @var Connection $dbalConnection */
        $dbalConnection = $this->get('dbal_connection');
        $statement = $dbalConnection->executeQuery(
            $sql,
            array('orderNumbers' => $orderNumbers),
            array('orderNumbers' => Connection::PARAM_STR_ARRAY)
        );

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }
}
