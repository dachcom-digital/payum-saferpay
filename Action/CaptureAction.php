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
            return;
        }

        if (isset($httpRequest->query['failed'])) {
            $details['transaction_failed'] = true;
            return;
        }

        // no token given, we need to initialize payment page first.
        if (!$details->offsetExists('token') || $details->offsetGet('token') === null) {
            $this->paymentPageInitializeAction($request, $details);
            return;
        }

        // we're back from payment page. let's assert the payment
        $this->paymentPageAssertAction($request, $details);

    }

    /**
     * @param Capture     $request
     * @param ArrayObject $details
     */
    protected function paymentPageInitializeAction(Capture $request, ArrayObject $details)
    {
        if (!$details->offsetExists('success_url')) {
            $successUrl = HttpUri::createFromString($request->getToken()->getTargetUrl());
            $modifier = new MergeQuery('success=1');
            $successUrl = $modifier->process($successUrl);
            $details->offsetSet('success_url', (string) $successUrl);
        }

        if (!$details->offsetExists('fail_url')) {
            $failedUrl = HttpUri::createFromString($request->getToken()->getTargetUrl());
            $modifier = new MergeQuery('failed=1');
            $failedUrl = $modifier->process($failedUrl);
            $details->offsetSet('fail_url', (string) $failedUrl);
        }

        if (!$details->offsetExists('abort_url')) {
            $cancelUri = HttpUri::createFromString($request->getToken()->getTargetUrl());
            $modifier = new MergeQuery('cancelled=1');
            $cancelUri = $modifier->process($cancelUri);
            $details->offsetSet('abort_url', (string) $cancelUri);
        }

        if (!$details->offsetExists('notify_url')) {
            $notifyToken = $this->tokenFactory->createNotifyToken(
                $request->getToken()->getGatewayName(),
                $request->getToken()->getDetails()
            );
            $details->offsetSet('notify_url', $notifyToken->getTargetUrl());
        }

        $this->gateway->execute(new CreateTransaction($details));
    }

    /**
     * @param Capture     $request
     * @param ArrayObject $details
     */
    protected function paymentPageAssertAction(Capture $request, ArrayObject $details)
    {
        // get current payment status directly from saferpay.
        $this->gateway->execute(new Sync($details));

        // mark payment as captured since everything seems ok so far.
        // we dont actually capture payment here -> the notify action needs to do this.
        // otherwise we'll run into a multi thread action loop
        if ($details->offsetExists('transaction_status') && in_array($details->get('transaction_status'), ['PENDING', 'AUTHORIZED'])) {
            $details->replace(['capture_authorized_or_pending' => true]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}