<?php

namespace DachcomDigital\Payum\Saferpay\Action\Api;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use DachcomDigital\Payum\Saferpay\Api;
use DachcomDigital\Payum\Saferpay\Request\Api\RefundTransaction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

class RefundTransactionAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritdoc}
     *
     * @param $request RefundTransaction
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $model->validateNotEmpty([
            'transaction_id',
            'amount',
            'currency_code',
        ]);

        $model->replace(
            $this->api->refundTransaction((array) $model)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof RefundTransaction &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
