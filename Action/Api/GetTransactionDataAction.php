<?php

namespace DachcomDigital\Payum\Saferpay\Action\Api;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use DachcomDigital\Payum\Saferpay\Api;
use DachcomDigital\Payum\Saferpay\Request\Api\GetTransactionData;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\LogicException;

class GetTransactionDataAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritdoc}
     *
     * @param $request GetTransactionData
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        if (false == $details['token']) {
            throw new LogicException('The parameter "token" must be set. Have you run CreateTransactionAction?');
        }

        $details->replace($this->api->getTransactionData($details['token']));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetTransactionData &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
