<?php

namespace DachcomDigital\Payum\Saferpay\Action;

use League\Uri\Http as HttpUri;
use League\Uri\Modifiers\MergeQuery;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Sync;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use DachcomDigital\Payum\Saferpay\Api;
use DachcomDigital\Payum\Saferpay\Request\Api\CreateTransaction;
use DachcomDigital\Payum\Saferpay\Request\Api\CapturePayment;

class CaptureAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface
{
    use GatewayAwareTrait;
    use GenericTokenFactoryAwareTrait;
    use ApiAwareTrait;

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
     * @param Capture $request
     */
    public function execute($request)
    {
        /* @var $request Capture */
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        if (isset($httpRequest->query['cancelled'])) {
            $details['transaction_cancelled'] = true;
        }

        if (isset($httpRequest->query['failed'])) {
            $details['transaction_failed'] = true;
        }

        if (false == $details['token']) {

            if (false == $details['success_url'] && $request->getToken()) {
                $successUrl = HttpUri::createFromString($request->getToken()->getTargetUrl());
                $modifier = new MergeQuery('success=1');
                $successUrl = $modifier->process($successUrl);
                $details['success_url'] = (string)$successUrl;
            }

            if (false == $details['fail_url'] && $request->getToken()) {
                $failedUrl = HttpUri::createFromString($request->getToken()->getTargetUrl());
                $modifier = new MergeQuery('failed=1');
                $failedUrl = $modifier->process($failedUrl);
                $details['fail_url'] = (string)$failedUrl;
            }

            if (false == $details['abort_url'] && $request->getToken()) {
                $cancelUri = HttpUri::createFromString($request->getToken()->getTargetUrl());
                $modifier = new MergeQuery('cancelled=1');
                $cancelUri = $modifier->process($cancelUri);
                $details['abort_url'] = (string)$cancelUri;
            }

            if (false == $details['notify_url'] && $request->getToken() && $this->tokenFactory) {
                $notifyToken = $this->tokenFactory->createNotifyToken(
                    $request->getToken()->getGatewayName(),
                    $request->getToken()->getDetails()
                );

                $details['notify_url'] = $notifyToken->getTargetUrl();
            }

            $this->gateway->execute(new CreateTransaction($details));
        }

        if($this->api->getLockHandler()->transactionIsLocked($details['token'])) {
            return;
        }

         // set lock
        $this->api->getLockHandler()->lockTransaction($details['token']);

        $this->gateway->execute(new Sync($details));

        if (isset($details['transaction_status']) && in_array($details['transaction_status'], ['PENDING', 'AUTHORIZED'])) {
            $this->gateway->execute(new CapturePayment($details));
        }

        $this->gateway->execute(new Sync($details));

        $this->api->getLockHandler()->unlockTransaction($details['token']);

    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}