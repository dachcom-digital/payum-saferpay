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
use Payum\Core\Storage\StorageInterface;

class NotifyAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    /**
     * @var StorageInterface
     */
    protected $tokenStorage;

    /**
     * NotifyAction constructor.
     *
     * @param StorageInterface $tokenStorage
     */
    public function __construct(StorageInterface $tokenStorage)
    {
        $this->apiClass = Api::class;
        $this->tokenStorage = $tokenStorage;
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

        // get current payment status directly from saferpay.
        $this->gateway->execute(new Sync($details));

        // capture payment since everything seems ok so far.
        if ($details->offsetExists('transaction_status') && in_array($details->get('transaction_status'), ['PENDING', 'AUTHORIZED'])) {
            $capturePayment = new CapturePayment($details);
            $capturePayment->setType('PAYMENT');
            $this->gateway->execute($capturePayment);
        }

        throw new HttpResponse('OK', 200);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
