<?php

namespace DachcomDigital\Payum\Saferpay\Exception;

class SaferpayException extends \Exception
{
    public function __toString()
    {
        return $this->getMessage() . ' (#' . $this->code . ')';
    }
}