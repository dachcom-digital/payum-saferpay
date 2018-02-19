<?php

namespace DachcomDigital\Payum\Saferpay\Handler;

use Payum\Core\HttpClientInterface;
use Http\Message\MessageFactory;

class RequestHandler
{
    const PAYMENT_PAGE_INITIALIZE_PATH = '/Payment/v1/PaymentPage/Initialize';
    const PAYMENT_PAGE_ASSERT_PATH = '/Payment/v1/PaymentPage/Assert';

    const TRANSACTION_INITIALIZE_PATH = '/Payment/v1/Transaction/Initialize';
    const TRANSACTION_AUTHORIZE_PATH = '/Payment/v1/Transaction/Authorize';
    const TRANSACTION_CAPTURE_PATH = '/Payment/v1/Transaction/Capture';
    const TRANSACTION_CANCEL_PATH = '/Payment/v1/Transaction/Cancel';
    const TRANSACTION_REFUND_PATH = '/Payment/v1/Transaction/Refund';

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     * @param array               $options
     *
     */
    public function __construct(HttpClientInterface $client, MessageFactory $messageFactory, $options)
    {
        $this->client = $client;
        $this->messageFactory = $messageFactory;
        $this->options = $options;
    }

    /**
     * @param $data
     * @return array
     */
    public function createTransactionRequest($data)
    {
        $requestData = [
            'RequestHeader' => [
                'SpecVersion'    => $this->options['spec_version'],
                'CustomerId'     => $this->options['customer_id'],
                'RequestId'      => uniqid(),
                'RetryIndicator' => 0,
            ],
            'TerminalId'    => $this->options['terminal_id'],
            'Payment'       => [
                'Amount'      => [
                    'Value'        => $data['amount'],
                    'CurrencyCode' => $data['currency_code'],
                ],
                'OrderId'     => $data['order_id'],
                'Description' => $data['description'],
            ],
            'ReturnUrls'    => [
                'Success' => $data['success_url'],
                'Fail'    => $data['fail_url'],
                'Abort'   => $data['abort_url'],
            ],
            'Notification'  => [
                'NotifyUrl' => $data['notify_url']
            ]
        ];

        $requestData = $this->mergeOptionalRequestData($requestData, $data);

        $url = $this->getApiEndpoint() . self::PAYMENT_PAGE_INITIALIZE_PATH;
        $request = $this->doRequest($requestData, $url);

        $response = [
            'redirect_url' => null,
            'token'        => null,
            'expiration'   => null,
            'has_error'    => false,
            'error'        => null
        ];

        if ($request['error'] === false) {
            $responseData = $request['data'];
            $response['token'] = $responseData['Token'];
            $response['expiration'] = $responseData['Expiration'];
            $response['redirect_url'] = $responseData['RedirectUrl'];
        } else {
            $response['has_error'] = true;
            $response['error'] = $request['data'];
        }

        return $response;
    }

    /**
     * @param $token
     * @return array
     */
    public function createTransactionAssertRequest($token)
    {
        $requestData = [
            'RequestHeader' => [
                'SpecVersion'    => $this->options['spec_version'],
                'CustomerId'     => $this->options['customer_id'],
                'RequestId'      => uniqid(),
                'RetryIndicator' => 0,
            ],
            'Token'         => $token,
        ];

        $url = $this->getApiEndpoint() . self::PAYMENT_PAGE_ASSERT_PATH;
        $request = $this->doRequest($requestData, $url);

        $response = [
            'transaction'   => null,
            'payment_means' => null,
            'payer'         => null,
            'has_error'     => false,
            'error'         => null
        ];

        if ($request['error'] === false) {
            $responseData = $request['data'];
            $response['transaction'] = $responseData['Transaction'];
            $response['payment_means'] = $responseData['PaymentMeans'];
            $response['payer'] = $responseData['Payer'];
        } else {
            $response['has_error'] = true;
            $response['error'] = $request['data'];
        }

        return $response;
    }

    /**
     * @param $transactionId
     * @return array
     */
    public function createTransactionCaptureRequest($transactionId)
    {
        $requestData = [
            'RequestHeader'        => [
                'SpecVersion'    => $this->options['spec_version'],
                'CustomerId'     => $this->options['customer_id'],
                'RequestId'      => uniqid(),
                'RetryIndicator' => 0,
            ],
            'TransactionReference' => [
                'TransactionId' => $transactionId
            ],
        ];

        $url = $this->getApiEndpoint() . self::TRANSACTION_CAPTURE_PATH;
        $request = $this->doRequest($requestData, $url);

        $response = [
            'transaction_id'     => null,
            'transaction_status' => null,
            'date'               => null,
            'has_error'          => false,
            'error'              => null
        ];

        if ($request['error'] === false) {
            $responseData = $request['data'];
            $response['transaction_id'] = $responseData['TransactionId'];
            $response['transaction_status'] = $responseData['Status'];
            $response['date'] = $responseData['Date'];
            //implement invoice data
            //$invoiceData = $responseData['Invoice'];
        } else {
            $response['has_error'] = true;
            $response['error'] = $request['data'];
        }

        return $response;
    }

    /**
     * @param $data
     * @return array
     */
    public function createRefundRequest($data)
    {
        $requestData = [
            'RequestHeader'        => [
                'SpecVersion'    => $this->options['spec_version'],
                'CustomerId'     => $this->options['customer_id'],
                'RequestId'      => uniqid(),
                'RetryIndicator' => 0,
            ],
            'Refund'               => [
                'Amount' => [
                    'Value'        => $data['amount'],
                    'CurrencyCode' => $data['currency_code']
                ]
            ],
            'TransactionReference' => [
                'TransactionId' => $data['transaction_id']
            ],
        ];

        $url = $this->getApiEndpoint() . self::TRANSACTION_REFUND_PATH;
        $request = $this->doRequest($requestData, $url);

        $response = [
            'transaction'   => null,
            'payment_means' => null,
            'dcc'           => null,
            'has_error'     => false,
            'error'         => null
        ];

        if ($request['error'] === false) {
            $responseData = $request['data'];
            $response['transaction'] = $responseData['Transaction'];
            $response['payment_means'] = $responseData['PaymentMeans'];
            $response['dcc'] = $responseData['Dcc'];
        } else {
            $response['has_error'] = true;
            $response['error'] = $request['data'];
        }

        return $response;
    }

    /**
     * @param $data
     * @param $url
     * @return array
     */
    private function doRequest($data, $url)
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->options['username'] . ':' . $this->options['password']),
            'Content-Type'  => 'application/json; charset=utf-8',
            'Accept'        => 'application/json',
        ];

        $responseData = [
            'error' => false,
            'data'  => []
        ];

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->messageFactory->createRequest('POST', $url, $headers, json_encode($data));
        $response = $this->client->send($request);

        if ($response->getStatusCode() === 400) {
            $data = json_decode($response->getBody()->getContents(), true);
            $responseData['error'] = true;
            $responseData['data'] = [
                'behavior'      => $data['Behavior'],
                'error_name'    => $data['ErrorName'],
                'error_message' => $data['ErrorMessage'],
                'error_detail'  => $data['ErrorDetail']
            ];
        } elseif (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            $responseData['error'] = true;
            $responseData['data'] = 'Error ' . $response->getStatusCode() . ': ' . $response->getReasonPhrase();
        } else {
            $content = json_decode($response->getBody()->getContents(), true);
            $responseData['data'] = $content;
        }

        return $responseData;
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

    /**
     * @param $requestData
     * @param $data
     * @return mixed
     */
    private function mergeOptionalRequestData($requestData, $data)
    {
        /**
         * config set
         * Example: name of your payment page config (case-insensitive)
         */
        if (isset($data['optional_config_set'])) {
            $requestData['ConfigSet'] = (string)$data['optional_config_set'];
        }

        /**
         * language code. allowed code-List:
         * de - German
         * en - English
         * fr - French
         * da - Danish
         * cs - Czech
         * es - Spanish
         * hr - Croatian
         * it - Italian
         * hu - Hungarian
         * nl - Dutch
         * nn - Norwegian
         * pl - Polish
         * pt - Portuguese
         * ru - Russian
         * ro - Romanian
         * sk - Slovak
         * sl - Slovenian
         * fi - Finnish
         * sv - Swedish
         * tr - Turkish
         * el - Greek
         * ja - Japanese
         * zh - Chinese
         */
        if (isset($data['optional_payer_language_code'])) {
            $requestData['Payer']['LanguageCode'] = (string)$data['optional_payer_language_code'];
        }

        /**
         * payment methods
         * Possible values: AMEX, BANCONTACT, BONUS, DINERS, DIRECTDEBIT, EPRZELEWY, EPS, GIROPAY, IDEAL, INVOICE, JCB, MAESTRO, MASTERCARD, MYONE, PAYPAL, PAYDIREKT, POSTCARD, POSTFINANCE, SOFORT, TWINT, UNIONPAY, VISA.
         */
        if (isset($data['optional_payment_methods']) && is_array($data['optional_payment_methods'])) {
            $requestData['PaymentMethods'] = $data['optional_payment_methods'];
        }

        //notifications
        if (isset($data['optional_notification_merchant_email'])) {
            $requestData['Notification']['PayerEmail'] = $data['optional_notification_merchant_email'];
        }
        if (isset($data['optional_notification_payer_email'])) {
            $requestData['Notification']['PayerEmail'] = $data['optional_notification_payer_email'];
        }

        //styling
        if (isset($data['optional_styling_css_url'])) {
            $requestData['Styling']['CssUrl'] = $data['optional_styling_css_url'];
        }
        if (isset($data['optional_styling_content_security_enabled'])) {
            $requestData['Styling']['ContentSecurityEnabled'] = $data['optional_styling_content_security_enabled'];
        }
        if (isset($data['optional_styling_theme'])) {
            $requestData['Styling']['Theme'] = $data['optional_styling_theme'];
        }

        return $requestData;
    }
}