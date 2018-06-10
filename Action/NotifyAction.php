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
use Payum\Core\Request\GetHumanStatus;
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

        // check lock
        if ($this->api->getLockHandler()->transactionIsLocked($details['token'])) {
            throw new HttpResponse('TRANSACTION_LOCKED', 503);
        }

        if (!isset($details['process_notify'])) {

            // since we're handling with some sort of a race condition here,
            // we need to throttle the unlock process for half of a second.
            usleep(500000);

            $details['process_notify'] = true;
            throw new HttpResponse('TRANSACTION_AWAITING', 503);
        }

        // set lock
        $this->api->getLockHandler()->lockTransaction($details['token']);

        // sync data
        $this->gateway->execute(new Sync($details));

        // remove tmp capture state
        unset($details['capture_state_reached']);

        $this->gateway->execute($status = new GetHumanStatus($request->getToken()));

        if ($status->isCaptured()) {
            throw new HttpResponse('OK', 200);
        }

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
            $request->getModel() instanceof \ArrayAccess;
    }
}
