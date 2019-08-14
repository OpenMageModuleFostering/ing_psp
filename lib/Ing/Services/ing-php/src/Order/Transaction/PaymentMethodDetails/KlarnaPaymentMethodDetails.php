<?php

namespace GingerPayments\Payment\Order\Transaction\PaymentMethodDetails;

use GingerPayments\Payment\Order\Transaction\PaymentMethodDetails;
use GingerPayments\Payment\Order\Transaction\PaymentMethodDetails\KlarnaPaymentMethodDetails\ErrorCode;

final class KlarnaPaymentMethodDetails implements PaymentMethodDetails
{
    /**
     * @var ErrorCode
     */
    private $errorCode;

    /**
     * @param array $details
     * @return static
     */
    public static function fromArray(array $details)
    {
        return new static(
            array_key_exists('error_code', $details) ? ErrorCode::fromString($details['error_code']) : null
        );
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'error_code' => ($this->errorCode() !== null) ? $this->errorCode()->toString() : null
        ];
    }

    /**
     * @return ErrorCode
     */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /**
     * KlarnaPaymentMethodDetails constructor.
     * @param ErrorCode|null $errorCode
     */
    private function __construct(
        ErrorCode $errorCode = null
    ) {
        $this->errorCode = $errorCode;
    }
}
