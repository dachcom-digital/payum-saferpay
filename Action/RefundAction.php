<?php

namespace DachcomDigital\Payum\Saferpay\Action;

use DachcomDigital\Payum\Saferpay\Request\Api\CapturePayment;
use Payum\Core\Action\ActionInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use DachcomDigital\Payum\Saferpay\Request\Api\RefundTransaction;
use Payum\Core\Request\Refund;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Bridge\Spl\ArrayObject;

class RefundAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritdoc}
     *
     * @param $request Refund
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute(new RefundTransaction($details));

        // capture refund since everything seems ok so far.
        if ($details->offsetExists('refund_transaction_type') && $details->get('refund_transaction_type') === 'REFUND') {
            if ($details->offsetExists('refund_transaction_status') && in_array($details->get('refund_transaction_status'), ['PENDING', 'AUTHORIZED'])) {
                $capturePayment = new CapturePayment($details);
                $capturePayment->setType('REFUND');
                $this->gateway->execute($capturePayment);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Refund &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
