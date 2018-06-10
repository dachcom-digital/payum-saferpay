<?php

namespace DachcomDigital\Payum\Saferpay\Action;

use DachcomDigital\Payum\Saferpay\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Exception\RequestNotSupportedException;

class StatusAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    const TYPE_PAYMENT = 'PAYMENT';
    const TYPE_REFUND = 'REFUND';

    const STATUS_AUTHORIZED = 'AUTHORIZED';
    const STATUS_CAPTURED = 'CAPTURED';
    const STATUS_PENDING = 'PENDING';

    /**
     * CaptureAction constructor.
     */
    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritdoc}
     *
     * @param $request GetStatusInterface
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        if (!isset($details['transaction_status'])
            && isset($details['token'])
            && isset($details['expiration'])
            && !is_null($details['expiration']) && $details['expiration'] < time()) {
            $request->markExpired();
            return;
        }

        //handle failed cancellation
        if (isset($details['transaction_cancelled']) && $details['transaction_cancelled'] === true) {
            $request->markCanceled();
            return;
        }

        //handle failed transaction
        if (isset($details['transaction_failed']) && $details['transaction_failed'] === true) {
            $request->markFailed();
            return;
        }

        //handle tmp capture transaction
        if (isset($details['capture_state_reached']) && $details['capture_state_reached'] === true) {
            $request->markAuthorized();
            return;
        }

        if (!isset($details['token']) || !strlen($details['token'])) {
            $request->markNew();
            return;
        }

        if (!isset($details['transaction_status'])) {
            $request->markNew();
            return;
        }

        $status = isset($details['transaction_status']) ? $details['transaction_status'] : null;

        switch ($details['transaction_type']) {
            case self::TYPE_PAYMENT:
                switch ($status) {
                    case self::STATUS_AUTHORIZED:
                        $request->markAuthorized();
                        break;
                    case self::STATUS_CAPTURED:
                        $request->markCaptured();
                        break;
                    case self::STATUS_PENDING:
                        $request->markPending();
                        break;
                    default:
                        $request->markUnknown();
                        break;
                }
                break;
            case self::TYPE_REFUND:
                switch ($status) {
                    case self::STATUS_AUTHORIZED:
                        $request->markAuthorized();
                        break;
                    case self::STATUS_CAPTURED:
                        $request->markCaptured();
                        break;
                    case self::STATUS_PENDING:
                        $request->markPending();
                        break;
                    default:
                        $request->markUnknown();
                        break;
                }
                break;
            default:
                $request->markUnknown();
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
