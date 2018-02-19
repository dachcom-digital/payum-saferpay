<?php

namespace DachcomDigital\Payum\Saferpay;

use DachcomDigital\Payum\Saferpay\Action\Api\CapturePaymentAction;
use DachcomDigital\Payum\Saferpay\Action\Api\CreateTransactionAction;
use DachcomDigital\Payum\Saferpay\Action\Api\GetTransactionDataAction;
use DachcomDigital\Payum\Saferpay\Action\Api\RefundTransactionAction;
use DachcomDigital\Payum\Saferpay\Action\CaptureAction;
use DachcomDigital\Payum\Saferpay\Action\ConvertPaymentAction;
use DachcomDigital\Payum\Saferpay\Action\NotifyAction;
use DachcomDigital\Payum\Saferpay\Action\RefundAction;
use DachcomDigital\Payum\Saferpay\Action\StatusAction;
use DachcomDigital\Payum\Saferpay\Action\SyncAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class SaferpayGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'payum.factory_name'  => 'saferpay',
            'payum.factory_title' => 'Saferpay',

            'payum.action.capture'         => new CaptureAction(),
            'payum.action.status'          => new StatusAction(),
            'payum.action.notify'          => new NotifyAction(),
            'payum.action.sync'            => new SyncAction(),
            'payum.action.refund'          => new RefundAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),

            'payum.action.api.create_transaction'   => new CreateTransactionAction(),
            'payum.action.api.get_transaction_data' => new GetTransactionDataAction(),
            'payum.action.api.refund_transaction'   => new RefundTransactionAction(),
            'payum.action.api.capture_payment'      => new CapturePaymentAction(),
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = [
                'environment' => Api::TEST,
                'specVersion' => '1.8', //https://saferpay.github.io/jsonapi/index.html
                'sandbox'     => true,
            ];
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = [
                'username',
                'password',
                'specVersion',
                'customerId',
                'terminalId'
            ];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api(
                    [
                        'sandbox'      => $config['environment'] === Api::TEST,
                        'username'     => $config['username'],
                        'password'     => $config['password'],
                        'spec_version' => $config['specVersion'],
                        'customer_id'  => $config['customerId'],
                        'terminal_id'  => $config['terminalId']
                    ],
                    $config['payum.http_client'],
                    $config['httplug.message_factory']
                );
            };
        }
    }
}
