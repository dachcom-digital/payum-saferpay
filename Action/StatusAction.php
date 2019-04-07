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

        if (!$details->offsetExists('token') || $details->offsetGet('token') === null) {
            $request->markNew();
            return;
        }

        if ($details->offsetExists('capture_authorized_or_pending') && $details->offsetGet('capture_authorized_or_pending') === true) {
            $request->markAuthorized();
            return;
        }

        if ($details->offsetExists('transaction_cancelled') && $details->get('transaction_cancelled') === true) {
            $request->markCanceled();
            return;
        }

        if ($details->offsetExists('transaction_failed') && $details->get('transaction_failed') === true) {
            $request->markFailed();
            return;
        }

        if (!$details->offsetExists('transaction_status')) {
            $request->markNew();
            return;
        }

        if ($details->offsetExists('expiration') && !is_null($details->get('expiration')) && $details->get('expiration') < time()) {
            $request->markExpired();
            return;
        }

        switch ($details->get('transaction_type')) {
            case self::TYPE_PAYMENT:
                $status = $details->offsetExists('transaction_status') ? $details->get('transaction_status') : null;
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
                $status = $details->offsetExists('refund_transaction_status') ? $details->get('refund_transaction_status') : null;
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
