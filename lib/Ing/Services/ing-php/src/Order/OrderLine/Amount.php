<?php

namespace GingerPayments\Payment\Order\OrderLine;

use Assert\Assertion as Guard;
use GingerPayments\Payment\Common\IntegerBasedValueObject;

final class Amount
{
    use IntegerBasedValueObject;

    /**
     * @param integer $value
     */
    private function __construct($value)
    {
        Guard::min($value, 1, 'Order line amount must be at least one');

        $this->value = $value;
    }
}
