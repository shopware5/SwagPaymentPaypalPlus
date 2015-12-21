<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Collections\ArrayCollection;
use Shopware\SwagPaymentPaypalPlus\Components\AdditionalTableInstaller;
use Shopware\SwagPaymentPaypalPlus\Components\DocumentInstaller;
use Shopware\SwagPaymentPaypalPlus\Components\InvoiceContentProvider;
use Shopware\SwagPaymentPaypalPlus\Components\PaymentInstructionProvider;

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
     */
    public function install()
    {
        $this->createMyEvents();
        $this->createMyForm();
        $this->createMyAttributes();

        $documentInstaller = new DocumentInstaller($this);
        $documentInstaller->installDocuments();

        $tableInstaller = new AdditionalTableInstaller($this);
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
     * @return bool|array
     */
    public function update($version)
    {
        $this->createMyForm();
        $this->createMyEvents();

        $documentInstaller = new DocumentInstaller($this);
        $documentInstaller->installDocuments();

        $tableInstaller = new AdditionalTableInstaller($this);
        $tableInstaller->installAdditionalDatabaseTable();

        if ($version == '1.0.0') {
            $this->createMyAttributes();
        }

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
        if (version_compare(Shopware::VERSION, '4.2.0', '<') && Shopware::VERSION != '___VERSION___') {
            if ($name == 'loader') {
                return $this->Application()->Loader();
            }
            $name = ucfirst($name);

            return $this->Application()->Bootstrap()->getResource($name);
        }

        return parent::get($name);
    }

    /**
     * Creates and subscribe the events and hooks.
     */
    private function createMyEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
            'onPostDispatchCheckout'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Backend_Payment',
            'onExtendBackendPayment'
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
            'Enlight_Controller_Action_Frontend_PaymentPaypal_SaveCookieInSession',
            'onSaveCookieInSession'
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
            'Enlight_Bootstrap_InitResource_payment_instruction_provider',
            'intiPaymentInstructionProvider'
        );

        $this->subscribeEvent(
            'Enlight_Bootstrap_InitResource_invoice_content_provider',
            'intiInvoiceContentProvider'
        );
        $this->subscribeEvent(
            'Theme_Compiler_Collect_Plugin_Less',
            'addLessFiles'
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
                'multiSelect' => true
            )
        );
        $form->setElement(
            'boolean',
            'paypalHidePaymentSelection',
            array(
                'label' => 'Zahlungsart-Auswahl im Bestellabschluss ausblenden (Shopware 4)',
                'value' => true
            )
        );
        $form->setElement(
            'text',
            'paypalPlusDescription',
            array(
                'label' => 'Zahlungsart-Bezeichnung überschreiben',
                'value' => 'PayPal, Lastschrift oder Kreditkarte'
            )
        );
        $form->setElement(
            'text',
            'paypalPlusAdditionalDescription',
            array(
                'label' => 'Zahlungsart-Beschreibung ergänzen',
                'value' => ' Zahlung per Lastschrift oder Kreditkarte ist auch ohne PayPal-Konto möglich.'
            )
        );
    }

    private function createMyAttributes()
    {
        /** @var $modelManager \Shopware\Components\Model\ModelManager */
        $modelManager = $this->get('models');

        try {
            $modelManager->addAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_media',
                'VARCHAR(255)'
            );
        } catch (Exception $e) {
        }
        try {
            $modelManager->addAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_active',
                'tinyint(1)'
            );
        } catch (Exception $e) {
        }
        try {
            $modelManager->addAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_redirect',
                'tinyint(1)'
            );
        } catch (Exception $e) {
        }
        try {
            $modelManager->generateAttributeModels(array('s_core_paymentmeans_attributes'));
        } catch (Exception $e) {
        }
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
    public function onPostDispatchCheckout($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\Checkout($this);
        $subscriber->onPostDispatchCheckout($args);
    }

    /**
     * @param $args
     */
    public function onExtendBackendPayment($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PaymentForm($this);
        $subscriber->onExtendBackendPayment($args);
    }

    /**
     * @param $args
     */
    public function onPreDispatchPaymentPaypal($args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PaymentPaypal(
            $this->get('paypalRestClient'),
            $this->get('session'),
            $this->Collection()->get('SwagPaymentPaypal')
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
     * @param Enlight_Controller_ActionEventArgs $args
     * @return bool
     */
    public function onSaveCookieInSession(Enlight_Controller_ActionEventArgs $args)
    {
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PaypalCookie($this);

        return $subscriber->onSaveCookieInSession($args);
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
            $payPalPlusPuiOrderNumbers = $this->getPuiOrderNumbers();
            $orders = $view->getAssign('data');

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
            $payPalPlusPuiOrderNumbers = $this->getPuiOrderNumbers();
            $orders = $view->getAssign('data');

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

        if ($document->_order->payment['name'] != 'paypal') {
            return;
        }

        /* @var Smarty_Data $view */
        $view = $document->_view;

        $documentData = $view->getTemplateVars('Document');
        $orderData = $view->getTemplateVars('Order');
        $containers = $view->getTemplateVars('Containers');

        if (!isset($containers['Paypal_Content_Info'])) {
            return;
        }

        /** @var InvoiceContentProvider $invoiceContentProvider */
        $invoiceContentProvider = $this->get('invoice_content_provider');
        $rawFooter = $invoiceContentProvider->getPayPalInvoiceContentInfo($containers, $orderData);

        $containers['Paypal_Content_Info']['value'] = $rawFooter['value'];

        $transactionId = $orderData['_order']['transactionID'];
        $orderNumber = $orderData['_order']['ordernumber'];

        /** @var PaymentInstructionProvider $paymentInstructionProvider */
        $paymentInstructionProvider = $this->get('payment_instruction_provider');
        $paymentInstruction = $paymentInstructionProvider
            ->getInstructionsByOrderNumberAndTransactionId($orderNumber, $transactionId);

        $document->_template->addTemplateDir(dirname(__FILE__) . '/Views/');
        $document->_template->assign('instruction', (array) $paymentInstruction);

        $containerData = $view->getTemplateVars('Containers');
        $containerData['Footer'] = $containerData['Paypal_Footer'];
        $containerData['Content_Info'] = $containerData['Paypal_Content_Info'];
        $containerData['Content_Info']['value'] = $document->_template
            ->fetch('string:' . $containerData['Content_Info']['value']);
        $containerData['Content_Info']['style'] = '}' . $containerData['Content_Info']['style'] . ' #info {';
        $view->assign('Containers', $containerData);
    }

    /**
     * @return PaymentInstructionProvider
     */
    public function intiPaymentInstructionProvider()
    {
        return new PaymentInstructionProvider(Shopware()->Container());
    }

    /**
     * @return InvoiceContentProvider
     */
    public function intiInvoiceContentProvider()
    {
        return new InvoiceContentProvider(Shopware()->Container());
    }

    /**
     * @return ArrayCollection
     */
    public function onCollectJavascript()
    {
        $jsPath = array(__DIR__ . '/Views/frontend/_public/src/js/paypal-hacks.js');

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
     * @return array
     */
    private function getPuiOrderNumbers()
    {
        $sql = "SELECT ordernumber FROM s_payment_paypal_plus_payment_instruction;";

        return $this->get('db')->fetchCol($sql);
    }
}
