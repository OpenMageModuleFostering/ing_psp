<?php

/**
 *   ╲          ╱
 * ╭──────────────╮  COPYRIGHT (C) 2017 GINGER PAYMENTS B.V.
 * │╭──╮      ╭──╮│
 * ││//│      │//││
 * │╰──╯      ╰──╯│
 * ╰──────────────╯
 *   ╭──────────╮    The MIT License (MIT)
 *   │ () () () │
 *
 * @category    ING
 * @package     ING_PSP
 * @author      Ginger Payments B.V. (info@gingerpayments.com)
 * @version     v1.1.8
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/
class ING_PSP_Helper_Klarna extends Mage_Core_Helper_Abstract
{
    protected $orderId = null;
    protected $amount = 0;
    protected $description = null;
    protected $returnUrl = null;
    protected $paymentUrl = null;
    protected $orderStatus = null;
    protected $consumerInfo = array();
    protected $errorMessage = '';
    protected $errorCode = 0;
    protected $ingLib = null;

    public function __construct()
    {
        require_once(Mage::getBaseDir('lib').DS.'Ing'.DS.'Services'.DS.'ing-php'.DS.'vendor'.DS.'autoload.php');

        $apiKey = Mage::getStoreConfig("payment/ingpsp/test_apikey")
            ?: Mage::getStoreConfig("payment/ingpsp/apikey");

        if (strlen($apiKey) > 0) {
            $this->ingLib = \GingerPayments\Payment\Ginger::createClient(
                $apiKey,
                Mage::getStoreConfig("payment/ingpsp/product")
            );

            if (Mage::getStoreConfig("payment/ingpsp/bundle_cacert")) {
                $this->ingLib->useBundledCA();
            }
        }
    }

    public function extensionEnabled()
    {
        return false;
    }

    /**
     * Prepare an order and get a redirect URL
     *
     * @param int $orderId
     * @param float $amount
     * @param string $currency
     * @param string $description
     * @param array $customer
     * @param array $orderLines
     * @return bool
     */
    public function createOrder($orderId, $amount, $currency, $description, $customer = [], $orderLines = [])
    {
        if (!$this->setOrderId($orderId) ||
            !$this->setAmount($amount) ||
            !$this->setDescription($description)
        ) {
            $this->errorMessage = "Error in the given payment data";
            return false;
        }

        $webhookUrl = Mage::getStoreConfig("payment/ingpsp/webhook") ? Mage::getUrl('ingpsp/klarna/webhook') : null;

        try {
            $ingOrder = $this->ingLib->createKlarnaOrder(
                ING_PSP_Helper_Data::getAmountInCents($amount),
                $currency,
                $description,
                $orderId,
                null,
                null,
                \GingerPayments\Payment\Common\ArrayFunctions::withoutNullValues($customer),
                ['plugin' => ING_PSP_Helper_Data::getPluginVersion()],
                $webhookUrl,
                $orderLines
            );
        } catch (\Exception $exception) {
            Mage::throwException($exception->getMessage());
        }

        Mage::log($ingOrder->toArray());

        if ($ingOrder->status()->isError()) {
            $this->errorMessage = $ingOrder->transactions()->current()->reason()->toString();
            return false;
        } elseif ($ingOrder->status()->isCancelled()) {
            return false;
        } else {
            return true;
        }
    }

    public function getOrderDetails($ingOrderId)
    {
        return $this->ingLib->getOrder($ingOrderId)->toArray();
    }

    public function setAmount($amount)
    {
        return ($this->amount = $amount);
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setOrderId($orderId)
    {
        return ($this->orderId = $orderId);
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function setDescription($description)
    {
        $description = substr($description, 0, 29);

        return ($this->description = $description);
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
}
