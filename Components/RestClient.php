<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Components;

require __DIR__ . '/../vendor/autoload.php';

use Enlight_Config as PayPalConfig;
use GuzzleHttp\Client;
use Shopware\Components\CacheManager;
use Shopware\Components\Logger;
use Shopware\Models\Shop\DetachedShop;

class RestClient
{
    /**
     * The sandbox url.
     *
     * @var string
     */
    const URL_SANDBOX = 'https://api.sandbox.paypal.com/v1/';

    /**
     * The live url.
     *
     * @var string
     */
    const URL_LIVE = 'https://api.paypal.com/v1/';

    /**
     * @var string
     */
    const CACHE_ID = 'paypal_classic_auth';

    /**
     * @var Client
     */
    private $restClient;

    /**
     * @var array
     */
    private $authHeader;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var DetachedShop
     */
    private $shop;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param PayPalConfig $config
     * @param CacheManager $cacheManager
     * @param string|bool  $certPath     path to Bundle of CA Root Certificates (see: https://curl.haxx.se/ca/cacert.pem)
     * @param DetachedShop $shop
     * @param Logger       $logger
     */
    public function __construct(
        PayPalConfig $config,
        CacheManager $cacheManager,
        $certPath = true,
        DetachedShop $shop,
        Logger $logger
    ) {
        $this->shop = $shop;
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
        $restUser = $config->get('paypalClientId');
        $restPw = $config->get('paypalSecret');
        $sandBoxMode = $config->get('paypalSandbox');

        if ($sandBoxMode) {
            $base_url = self::URL_SANDBOX;
        } else {
            $base_url = self::URL_LIVE;
        }

        $this->restClient = new Client(
            array(
                'base_url' => $base_url,
                'defaults' => array(
                    'headers' => array(
                        'PayPal-Partner-Attribution-Id' => 'ShopwareAG_Cart_PayPalPlus_1017',
                    ),
                    'verify' => $certPath,
                ),
            )
        );

        $this->setAuth($restUser, $restPw);
    }

    /**
     * gets the resource depending on uri
     *
     * @param string $uri
     * @param array  $params
     *
     * @return array
     */
    public function get($uri, array $params = array())
    {
        $params = array('json' => $params);

        return $this->sendRequest('GET', $uri, $params);
    }

    /**
     * creates a new resource depending on uri
     *
     * @param string $uri
     * @param array  $params
     *
     * @return array
     */
    public function create($uri, array $params = array())
    {
        $params = array('json' => $params);

        return $this->sendRequest('POST', $uri, $params);
    }

    /**
     * updates a given resource
     *
     * @param string $uri
     * @param array  $params
     *
     * @return array
     */
    public function put($uri, array $params = array())
    {
        $params = array('json' => $params);

        return $this->sendRequest('PUT', $uri, $params);
    }

    /**
     * updates a given resource
     *
     * @param string $uri
     * @param array  $params
     *
     * @return array
     */
    public function patch($uri, array $params = array())
    {
        $params = array('json' => $params);

        return $this->sendRequest('PATCH', $uri, $params);
    }

    /**
     * send the request with the given HTTP method
     *
     * @param $method
     * @param $uri
     * @param array $params
     *
     * @return array
     */
    private function sendRequest($method, $uri, array $params = array())
    {
        $params = array_merge($params, $this->authHeader);
        $request = $this->restClient->createRequest($method, $uri, $params);
        $result = $this->restClient->send($request);

        return json_decode($result->getBody()->getContents(), true);
    }

    /**
     * @param string $restUser
     * @param string $restPw
     */
    private function setAuth($restUser, $restPw)
    {
        $params = array(
            'auth' => array($restUser, $restPw),
            'body' => array(
                'grant_type' => 'client_credentials',
            ),
        );

        $authorization = $this->getAuthorizationFromCache();
        if (!$authorization) {
            try {
                $auth = $this->createToken('oauth2/token', $params);
                $authorization = $auth['token_type'] . ' ' . $auth['access_token'];
                $this->setAuthorizationToCache($authorization, (int) $auth['expires_in']);
            } catch (\Exception $e) {
                $logger = new LoggerService($this->logger);
                $logger->log('An error occurred on initialising PayPal Plus: ', $e);
            }
        }

        $this->authHeader = array(
            'headers' => array(
                'Authorization' => $authorization,
            ),
        );
    }

    /**
     * creates a new OAuth2 token
     *
     * @param string $uri
     * @param array  $params
     *
     * @return array
     */
    private function createToken($uri, array $params = array())
    {
        $result = $this->restClient->post($uri, $params);

        return json_decode($result->getBody()->getContents(), true);
    }

    /**
     * @return string|false
     */
    private function getAuthorizationFromCache()
    {
        return $this->cacheManager->getCoreCache()->load($this->createCacheId());
    }

    /**
     * @param string $token
     * @param int    $expiresIn
     */
    private function setAuthorizationToCache($token, $expiresIn)
    {
        //Decrease expire date by one hour (3600s) just to make sure, we don't run into an unauthorized exception.
        $this->cacheManager->getCoreCache()->save(
            $token,
            $this->createCacheId(),
            array(),
            $expiresIn - 3600
        );
    }

    /**
     * @return string
     */
    private function createCacheId()
    {
        return self::CACHE_ID . '_' . $this->shop->getId();
    }
}
