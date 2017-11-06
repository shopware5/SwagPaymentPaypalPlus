<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Components;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class APIValidator
{
    /** @var RestClient */
    private $restClient;

    /**
     * @param RestClient $restClient
     */
    public function __construct(RestClient $restClient)
    {
        $this->restClient = $restClient;
    }

    /**
     * Returns a value indicating whether or not the payment method "paypal plus" is available at the moment.
     * It sends a test call to the api and if everything went fine, the method is available.
     * Currently, the main purpose of this function is the SSL and authentication test.
     *
     * @return bool
     */
    public function isAPIAvailable()
    {
        if ($this->restClient === null) {
            return false;
        }

        //Test the SSL Certificate that was provided by sending a simple test call to the PP API.
        //This is required, because in this step no other request will be sent that may be validated.
        //If this call fails, the payment method will not be modified and stay as paypal classic.
        //The method can be extended with several other exceptions to generate a correct result. The existing ones
        //do only check whether or not the SSL setup is correct.
        try {
            $response = $this->restClient->create('invoicing/invoices/next-invoice-number', array());

            if ($response) {
                return true;
            }

            return false;
        } catch (ClientException $ce) {
            //Unauthorized: impossible to continue using PayPal plus
            if ($ce->getCode() === 401) {
                return false;
            }

            //Internal server error
            if ($ce->getCode() === 500) {
                return false;
            }
        } catch (RequestException $re) {
            //It's not okay, since it's an SSL exception
            return false;
        }

        return true;
    }
}
