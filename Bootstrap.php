<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template', 'theme')
        );
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        $this->secureUninstall();
        $this->removeMyAttributes();

        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template', 'theme')
        );
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

        if ($version == '1.0.0') {
            $this->createMyAttributes();
        }

        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy')
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
                'label' => 'Zahlungsart-Beschreibung überschreiben',
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
            $modelManager->removeAttribute(
                's_core_paymentmeans_attributes',
                'paypal',
                'plus_active'
            );
            $modelManager->generateAttributeModels(array('s_core_paymentmeans_attributes'));
        } catch (Exception $e) {
        }
    }

    public function registerMyTemplateDir()
    {
        $this->get('template')->addTemplateDir(__DIR__ . '/Views/', 'paypal_plus');
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
        $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PlusRedirect($this);
        $subscriber->onPostDispatchAccount($args);
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
}
