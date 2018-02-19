<?php

namespace DachcomDigital\Payum\Saferpay\Action;

use League\Uri\Http as HttpUri;
use League\Uri\Modifiers\MergeQuery;
use Payum\Core\Action\ActionInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;
use DachcomDigital\Payum\Saferpay\Request\Api\CreateTransaction;
use DachcomDigital\Payum\Saferpay\Request\Api\CapturePayment;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Sync;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;

class CaptureAction implements ActionInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface
{
    use GatewayAwareTrait;
    use GenericTokenFactoryAwareTrait;
    
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
            return;
        }

        if (isset($httpRequest->query['failed'])) {
            $details['transaction_failed'] = true;
            return;
        }

        if (false == $details['token']) {

            if (false == $details['success_url'] && $request->getToken()) {
                $details['success_url'] = $request->getToken()->getTargetUrl();
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

        $this->gateway->execute(new Sync($details));

        if($details['transaction_id'] !== false) {
            $this->gateway->execute(new CapturePayment($details));
        }
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
