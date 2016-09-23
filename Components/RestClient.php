<?php

/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Components;

require(__DIR__ . "/../vendor/autoload.php");

use Enlight_Config as PayPalConfig;
use GuzzleHttp\Client;

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

    /** @var Client $restClient */
    private $restClient;

    /** @var array $authHeader */
    private $authHeader;

    /**
     * @param PayPalConfig $config
     * @param string|bool $certPath path to Bundle of CA Root Certificates (see: https://curl.haxx.se/ca/cacert.pem)
     */
    public function __construct(PayPalConfig $config, $certPath = true)
    {
        $restUser = $config->get('paypalClientId');
        $restPw = $config->get('paypalSecret');
        $sandBoxMode = $config->get('paypalSandbox');

        if ($sandBoxMode) {
            $base_url = self::URL_SANDBOX;
        } else {
            $base_url = self::URL_LIVE;
        }

        $this->restClient = new Client(
            [
                'base_url' => $base_url,
                'defaults' => [
                    'headers' => [
                        'PayPal-Partner-Attribution-Id' => 'ShopwareAG_Cart_PayPalPlus_1017'
                    ],
                    'verify' => $certPath
                ]
            ]
        );

        $this->setAuth($restUser, $restPw);
    }

    /**
     * gets the resource depending on uri
     *
     * @param string $uri
     * @param array $params
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
     * @param array $params
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
     * @param array $params
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
     * @param array $params
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
            )
        );

        $authorization = ' ';

        try {
            $auth = $this->createToken('oauth2/token', $params);
            $authorization = $auth['token_type'] . ' ' . $auth['access_token'];
        } catch (\Exception $e) {
        }

        $this->authHeader = array(
            'headers' => array(
                'Authorization' => $authorization
            )
        );
    }

    /**
     * creates a new OAuth2 token
     *
     * @param string $uri
     * @param array $params
     * @return array
     */
    private function createToken($uri, array $params = array())
    {
        $result = $this->restClient->post($uri, $params);

        return json_decode($result->getBody()->getContents(), true);
    }
}
