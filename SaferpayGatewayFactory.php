<?php

namespace DachcomDigital\Payum\Saferpay;

use DachcomDigital\Payum\Saferpay\Action\Api\ActivateAction;
use DachcomDigital\Payum\Saferpay\Action\Api\PopulateSaferpayFromDetailsAction;
use DachcomDigital\Payum\Saferpay\Action\AuthorizeAction;
use DachcomDigital\Payum\Saferpay\Action\CaptureAction;
use DachcomDigital\Payum\Saferpay\Action\ConvertPaymentAction;
use DachcomDigital\Payum\Saferpay\Action\StatusAction;
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
            'payum.action.authorize'       => new AuthorizeAction(),
            'payum.action.status'          => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),

            'payum.action.api.activate'                       => new ActivateAction(),
            'payum.action.api.populate_saferpay_from_details' => new PopulateSaferpayFromDetailsAction(),

        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = [
                'environment'   => Api::TEST,
                'paymentMethod' => '',
                'sandbox'       => true,
            ];
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = [
                'username',
                'password',
                'SpecVersion',
                'CustomerId'
            ];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api(
                    [
                        'sandbox'     => $config['environment'] === Api::TEST,
                        'username'    => $config['username'],
                        'password'    => $config['password'],
                        'SpecVersion' => $config['merchantId'],
                        'CustomerId'  => $config['filialId']
                    ],
                    $config['payum.http_client'],
                    $config['httplug.message_factory']
                );
            };
        }
    }
}
