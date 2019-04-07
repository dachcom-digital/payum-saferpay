<?php

namespace DachcomDigital\Payum\Saferpay;

use DachcomDigital\Payum\Saferpay\Handler\RequestHandler;
use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\HttpClientInterface;

class Api
{
    const TEST = 'test';
    const PRODUCTION = 'production';

    /**
     * @var RequestHandler
     */
    protected $requestHandler;

    /**
     * @var array|ArrayObject
     */
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
            'spec_version',
            'customer_id',
            'terminal_id',
            'lock_path'
        ]);

        if (false == is_bool($options['sandbox'])) {
            throw new LogicException('The boolean sandbox option must be set.');
        }

        if (false == is_dir($options['lock_path'])) {
            throw new LogicException(sprintf('%s is not a valid lock_path', $options['lock_path']));
        }

        $this->options = $options;
        $this->requestHandler = new RequestHandler($client, $messageFactory, $this->options);
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    public function createTransaction(array $fields)
    {
        $response = $this->requestHandler->createTransactionRequest($fields);

        return array_filter([
            'error'        => $response['has_error'] === true ? $response['data'] : false,
            'token'        => $response['token'],
            'redirect_url' => $response['redirect_url'],
        ]);
    }

    /**
     * @param $token
     *
     * @return array
     */
    public function getTransactionData($token)
    {
        $response = $this->requestHandler->createTransactionAssertRequest($token);

        $params = [];

        if ($response['has_error'] === true) {
            $params['error'] = $response['error'];
        } else {

            $transaction = $response['transaction'];
            $params['transaction_type'] = $transaction['Type'];
            $params['transaction_status'] = $transaction['Status'];
            $params['transaction_id'] = $transaction['Id'];
            $params['transaction_date'] = $transaction['Date'];
            $params['transaction_amount'] = $transaction['Amount']['Value'];
            $params['transaction_currency_code'] = $transaction['Amount']['CurrencyCode'];
            $params['transaction_acquirer_name'] = $transaction['AcquirerName'];
            $params['transaction_acquirer_reference'] = $transaction['AcquirerReference'];
            $params['transaction_six_transaction_reference'] = $transaction['SixTransactionReference'];
            $params['transaction_approval_code'] = $transaction['ApprovalCode'];

            $paymentMeans = $response['payment_means'];
            $params['payment_means_brand_payment_method'] = $paymentMeans['Brand']['PaymentMethod'];
            $params['payment_means_brand_name'] = $paymentMeans['Brand']['Name'];

            $params['payment_means_display_text'] = $paymentMeans['DisplayText'];
            $params['payment_means_wallet'] = $paymentMeans['Wallet'];

            $params['payment_means_cart_masked_number'] = $paymentMeans['Card']['MaskedNumber'];
            $params['payment_means_cart_exp_year'] = $paymentMeans['Card']['ExpYear'];
            $params['payment_means_cart_exp_month'] = $paymentMeans['Card']['ExpMonth'];
            $params['payment_means_cart_holder_name'] = $paymentMeans['Card']['HolderName'];
            $params['payment_means_cart_hash_value'] = $paymentMeans['Card']['HashValue'];

            $params['payment_means_bank_account_iban'] = $paymentMeans['BankAccount']['IBAN'];
            $params['payment_means_bank_account_holder_name'] = $paymentMeans['BankAccount']['HolderName'];
            $params['payment_means_bank_account_bic'] = $paymentMeans['BankAccount']['BIC'];
            $params['payment_means_bank_account_bank_name'] = $paymentMeans['BankAccount']['BankName'];
            $params['payment_means_bank_account_country_code'] = $paymentMeans['BankAccount']['CountryCode'];

            $payer = $response['payer'];
            $params['payment_payer_ip_address'] = $payer['IpAddress'];
            $params['payment_payer_ip_location'] = $payer['IpLocation'];
        }

        return array_filter($params);
    }

    /**
     * @param string $transactionId
     * @param null   $transactionType
     *
     * @return array
     */
    public function captureTransaction($transactionId, $transactionType = null)
    {
        $response = $this->requestHandler->createTransactionCaptureRequest($transactionId);

        $params = [];

        if ($response['has_error'] === true) {
            $params['error'] = $response['error'];
        } else {

            if ($transactionType === 'PAYMENT') {
                $params['capture_id'] = $response['capture_id'];
                $params['transaction_status'] = $response['transaction_status'];
                $params['transaction_date'] = $response['date'];
            } else {
                $params[sprintf('%s_capture_id', strtolower($transactionType))] = $response['capture_id'];
                $params[sprintf('%s_transaction_status', strtolower($transactionType))] = $response['transaction_status'];
                $params[sprintf('%s_transaction_date', strtolower($transactionType))] = $response['date'];
            }
        }

        return array_filter($params);
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    public function refundTransaction(array $fields)
    {
        $response = $this->requestHandler->createRefundRequest($fields);

        $params = [];

        if ($response['has_error'] === true) {
            $params['error'] = $response['error'];
        } else {
            $transaction = $response['transaction'];
            $params['refund_transaction_type'] = $transaction['Type'];
            $params['refund_transaction_id'] = $transaction['Id'];
            $params['refund_transaction_status'] = $transaction['Status'];
            $params['refund_transaction_date'] = $transaction['Date'];
            $params['refund_transaction_amount'] = $transaction['Amount']['Value'];
            $params['refund_transaction_currency_code'] = $transaction['Amount']['CurrencyCode'];
        }

        return array_filter($params);
    }
}
