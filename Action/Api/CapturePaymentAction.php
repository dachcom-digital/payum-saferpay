<?php

namespace DachcomDigital\Payum\Saferpay\Action\Api;

use DachcomDigital\Payum\Saferpay\Request\Api\CapturePayment;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use DachcomDigital\Payum\Saferpay\Api;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

class CapturePaymentAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    /**
     * CapturePaymentAction constructor.
     */
    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritdoc}
     *
     * @param $request CapturePayment
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        $transactionIdKey = null;
        if ($request->getType() === 'PAYMENT') {
            $transactionIdKey = 'transaction_id';
        } else {
            $transactionIdKey = sprintf('%s_transaction_id', strtolower($request->getType()));
        }

        $details->validateNotEmpty([$transactionIdKey]);
        $details->replace($this->api->captureTransaction($details->get($transactionIdKey), $request->getType()));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof CapturePayment &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
