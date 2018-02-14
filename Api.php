<?php

namespace DachcomDigital\Payum\Saferpay;

use DachcomDigital\Payum\Saferpay\Exception\SaferpayException;
use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\Exception\LogicException;
use Payum\Core\HttpClientInterface;

class Api
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    const TEST = 'test';

    const PRODUCTION = 'production';

    // parameters that will be included in the SHA-OUT Hash
    protected $signatureParams = [

    ];

    protected $options = [
        'environment' => self::TEST
    ];

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory)
    {
        $options = ArrayObject::ensureArrayObject($options);
        $options->defaults($this->options);
        $options->validateNotEmpty([
            'username',
            'password',
            'SpecVersion',
            'CustomerId'
        ]);

        if (false == is_bool($options['sandbox'])) {
            throw new LogicException('The boolean sandbox option must be set.');
        }

        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
    }

    /**
     * @param ArrayObject $details
     * @return array
     * @throws SaferpayException
     */
    public function generateRequest(ArrayObject $details)
    {
        try {
            $request = $this->doRequest();
        } catch (\Exception $e) {
            throw new SaferpayException($e->getMessage());
        }

        return $request;
    }

    /**
     * @param array $fields
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function doRequest(array $fields)
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->options['username'] . ':' . $this->options['password']),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->messageFactory->createRequest('POST', $this->getApiEndpoint(), $headers, http_build_query($fields));

        $response = $this->client->send($request);
        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            throw HttpException::factory($request, $response);
        }

        $xmlResponse = $response->getBody()->getContents();

        try {
           //$xmlResponse
        } catch (\Exception $e) {
            throw new LogicException("Response content is not valid xml: \n\n{$xmlResponse}");
        }

        return $response;
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        if ($this->options['sandbox'] === false) {
            return 'https://www.saferpay.com/api';
        }

        return 'https://test.saferpay.com/api';
    }
}
