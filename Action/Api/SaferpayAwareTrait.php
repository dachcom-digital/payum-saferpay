<?php

namespace DachcomDigital\Payum\Saferpay\Action\Api;

use DachcomDigital\Payum\Saferpay\Exception\SaferpayException;

trait SaferpayAwareTrait
{
    /**
     * @param \ArrayAccess      $details
     * @param SaferpayException $e
     * @param object            $request
     */
    protected function populateDetailsWithError(\ArrayAccess $details, SaferpayException $e, $request)
    {
        $details['error_request'] = get_class($request);
        $details['error_file'] = $e->getFile();
        $details['error_line'] = $e->getLine();
        $details['error_code'] = (int)$e->getCode();
        $details['error_message'] = utf8_encode($e->getMessage());
    }
}
