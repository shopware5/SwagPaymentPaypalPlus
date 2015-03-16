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
     * Installs the plugin
     *
     * @return bool
     */
    public function install()
    {
        $this->createMyEvents();
        $this->createMyForm();
        $this->createMyTranslations();
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

        return array(
            'success' => true,
            'invalidateCache' => array('config', 'backend', 'proxy')
        );
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if(version_compare(Shopware::VERSION, '4.2.0', '<') && Shopware::VERSION != '___VERSION___') {
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
    }

    /**
     * Creates and stores the payment config form.
     */
    private function createMyForm()
    {
        $form = $this->Form();

        $form->setElement('select', 'paypalPlusCountries', array(
            'label' => 'Länder bei denen „PayPal PLUS“ angezeigt wird',
            'value' => array(2),
            'store' => 'base.Country',
            'multiSelect' => true
        ));
    }

    private function createMyTranslations()
    {
        $form = $this->Form();
        $translations = array(
            'en_GB' => array(
                'paypalUsername' => 'API username',
                'paypalPassword' => 'API password',
            )
        );
        $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');
        foreach ($translations as $locale => $snippets) {
            $localeModel = $shopRepository->findOneBy(array(
                'locale' => $locale
            ));
            foreach ($snippets as $element => $snippet) {
                if ($localeModel === null) {
                    continue;
                }
                $elementModel = $form->getElement($element);
                if ($elementModel === null) {
                    continue;
                }
                $translationModel = new \Shopware\Models\Config\ElementTranslation();
                $translationModel->setLabel($snippet);
                $translationModel->setLocale($localeModel);
                $elementModel->addTranslation($translationModel);
            }
        }
    }

    private function createMyAttributes()
    {
        /** @var $modelManager \Shopware\Components\Model\ModelManager */
        $modelManager = $this->get('models');

        try {
            $modelManager->addAttribute(
                's_core_paymentmeans_attributes', 'paypal',
                'plus_media', 'VARCHAR(255)'
            );
            $modelManager->addAttribute(
                's_core_paymentmeans_attributes', 'paypal',
                'plus_active', 'tinyint(1)'
            );
        } catch(Exception $e) { }
        try {
            $modelManager->generateAttributeModels(array(
                's_core_paymentmeans_attributes'
            ));
        } catch(Exception $e) { }
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
            $modelManager->generateAttributeModels(array(
                's_core_paymentmeans_attributes'
            ));
        } catch(Exception $e) { }
    }

    /**
     * @param bool $next
     */
    public function registerMyTemplateDir($next = false)
    {
        $this->get('template')->addTemplateDir(
            __DIR__ . '/Views/', 'paypal_plus'
        );
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchCheckout(Enlight_Event_EventArgs $args)
    {
        static $subscriber;
        if(!isset($subscriber)) {
            require_once __DIR__ . '/Subscriber/Checkout.php';
            $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\Checkout($this);
        }
        $subscriber->onPostDispatchCheckout($args);
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     */
    public function onExtendBackendPayment(Enlight_Controller_ActionEventArgs $args)
    {
        static $subscriber;
        if(!isset($subscriber)) {
            require_once __DIR__ . '/Subscriber/PaymentForm.php';
            $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PaymentForm($this);
        }
        $subscriber->onExtendBackendPayment($args);
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     */
    public function onPreDispatchPaymentPaypal(Enlight_Controller_ActionEventArgs $args)
    {
        static $subscriber;
        if(!isset($subscriber)) {
            require_once __DIR__ . '/Subscriber/PaymentPaypal.php';
            $subscriber = new \Shopware\SwagPaymentPaypalPlus\Subscriber\PaymentPaypal(
                $this->get('paypalRestClient'),
                $this->get('session')
            );
        }
        $subscriber->onPreDispatchPaymentPaypal($args);
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return 'PayPal Plus';
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
