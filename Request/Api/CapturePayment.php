<?php

namespace DachcomDigital\Payum\Saferpay\Request\Api;

use Payum\Core\Request\Generic;

class CapturePayment extends Generic
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @param string $type PAYMENT|REFUND
     */
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
