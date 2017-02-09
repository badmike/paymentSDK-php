<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard Central Eastern Europe GmbH
 * (abbreviated to Wirecard CEE) and are explicitly not part of the Wirecard CEE range of
 * products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard CEE does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard CEE does not guarantee their full
 * functionality neither does Wirecard CEE assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard CEE does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

namespace Wirecard\PaymentSdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Class TransactionService
 *
 * This service manages communication  to the elastic engine
 * @package Wirecard\PaymentSdk
 */
class TransactionService
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var RequestMapper
     */
    private $requestMapper;

    /**
     * @var ResponseMapper
     */
    private $responseMapper;

    /**
     * @var RequestIdGenerator
     */
    private $requestIdGenerator;

    /**
     * TransactionService constructor.
     * @param Config $config
     * @param LoggerInterface|null $logger
     * @param Client|null $httpClient
     * @param RequestMapper|null $requestMapper
     * @param ResponseMapper|null $responseMapper
     * @param RequestIdGenerator|null $requestIdGenerator
     */
    public function __construct(
        Config $config,
        LoggerInterface $logger = null,
        Client $httpClient = null,
        RequestMapper $requestMapper = null,
        ResponseMapper $responseMapper = null,
        RequestIdGenerator $requestIdGenerator = null
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->requestMapper = $requestMapper;
        $this->responseMapper = $responseMapper;
        $this->requestIdGenerator = $requestIdGenerator;
    }

    /**
     * @param Transaction $transaction
     * @throws RequestException|MalformedResponseException|\RuntimeException
     * @return InteractionResponse|FailureResponse
     */
    public function pay(Transaction $transaction)
    {
        $response = $this->getHttpClient()->request(
            'POST',
            $this->getConfig()->getUrl(),
            [
                'auth' => [
                    $this->getConfig()->getHttpUser(),
                    $this->getConfig()->getHttpPassword()
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/xml'
                ],
                'body' => $this->getRequestMapper()->map($transaction)
            ]
        );

        return $this->getResponseMapper()->map($response->getBody()->getContents());
    }

    public function reserve(Transaction $transaction)
    {
        $requestBody = $this->getRequestMapper()->map($transaction);
        $response = $this->getHttpClient()->request(
            'POST',
            $this->getConfig()->getUrl(),
            [
                'auth' => [
                    $this->getConfig()->getHttpUser(),
                    $this->getConfig()->getHttpPassword()
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/xml'
                ],
                'body' => $requestBody
            ]
        );

        return $this->getResponseMapper()->map($response->getBody()->getContents());
    }

    /**
     * @return Client
     */
    protected function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client(['http_errors' => false]);
        }

        return $this->httpClient;
    }

    /**
     * @return Config
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * @return RequestMapper
     */
    protected function getRequestMapper()
    {
        if ($this->requestMapper === null) {
            $this->requestMapper = new RequestMapper($this->getConfig(), $this->getRequestIdGenerator());
        }

        return $this->requestMapper;
    }

    /**
     * @return RequestIdGenerator
     */
    protected function getRequestIdGenerator()
    {
        if ($this->requestIdGenerator === null) {
            $this->requestIdGenerator = new RequestIdGenerator();
        }

        return $this->requestIdGenerator;
    }

    /**
     * @return ResponseMapper
     */
    protected function getResponseMapper()
    {
        if ($this->responseMapper === null) {
            $this->responseMapper = new ResponseMapper();
        }

        return $this->responseMapper;
    }

    /**
     * @param $xmlResponse
     * @return FailureResponse|InteractionResponse|SuccessResponse
     * @throws \Wirecard\PaymentSdk\MalformedResponseException
     */
    public function handleNotification($xmlResponse)
    {
        return $this->getResponseMapper()->map($xmlResponse);
    }

    /**
     * @param array $payload
     * @return FailureResponse|InteractionResponse|SuccessResponse
     * @throws MalformedResponseException
     */
    public function handleResponse(array $payload)
    {
        if (array_key_exists('eppresponse', $payload)) {
            return $this->getResponseMapper()->map($payload['eppresponse']);
        } else {
            throw new MalformedResponseException('Missing response in payload');
        }
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = new Logger('wirecard_payment_sdk');
            $this->logger->pushHandler(new ErrorLogHandler());
        }

        return $this->logger;
    }
}
