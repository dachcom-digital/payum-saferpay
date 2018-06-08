<?php

namespace DachcomDigital\Payum\Saferpay\Action;

use DachcomDigital\Payum\Saferpay\Api;
use DachcomDigital\Payum\Saferpay\Request\Api\CapturePayment;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Sync;
use Payum\Core\Exception\RequestNotSupportedException;

class NotifyAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    /**
     * NotifyAction constructor.
     */
    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritdoc}
     *
     * @param $request Notify
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        // check lock
        if($this->api->getLockHandler()->transactionIsLocked($details['token'])) {
            throw new HttpResponse('OK', 200);
        }

        // set lock
        $this->api->getLockHandler()->lockTransaction($details['token']);

        $this->gateway->execute(new Sync($details));

        if (isset($details['transaction_status']) && in_array($details['transaction_status'], ['PENDING', 'AUTHORIZED'])) {
            $this->gateway->execute(new CapturePayment($details));
        }

        $this->gateway->execute(new Sync($details));

        $this->api->getLockHandler()->unlockTransaction($details['token']);

        throw new HttpResponse('OK', 200);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
