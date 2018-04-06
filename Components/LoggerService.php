<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Components;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Shopware\Components\Logger;

class LoggerService
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string    $message
     * @param Exception $e
     */
    public function log($message, Exception $e)
    {
        $context = array('exception' => $e);
        $message = $message . ': ' . $e->getMessage();
        if ($e instanceof RequestException) {
            $error = json_decode($e->getResponse()->getBody(), true);
            $context['response'] = $error;
            if (isset($error['message'])) {
                $message = $message . ': ' . $error['message'];
                if (isset($error['details'])) {
                    $message .= ' Details: ';
                    foreach ($error['details'] as $index => $detail) {
                        ++$index;
                        $message = $message . $index . ') ' . $detail['issue'] . ' "' . $detail['field'] . '" ';
                    }
                }
            } elseif (isset($error['error'])) {
                $message = $message . ': ' . $error['error'] . ', ' . $error['error_description'];
            }
        }

        $this->logger->error($message, $context);
    }
}
