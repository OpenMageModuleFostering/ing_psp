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
 * @version     v1.1.7
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/
class ING_PSP_Helper_Cashondelivery extends Mage_Core_Helper_Abstract
{
    const XML_PATH_EMAIL_TEMPLATE = "payment/ingpsp_cashondelivery/order_email_template";
    const XML_PATH_EMAIL_GUEST_TEMPLATE = "payment/ingpsp_cashondelivery/order_email_template_guest";

    protected $orderId = null;
    protected $amount = 0;
    protected $description = null;
    protected $orderStatus = null;
    protected $consumerInfo = array();
    protected $errorMessage = '';
    protected $errorCode = 0;
    protected $ingLib = null;

    public function __construct()
    {
        require_once(Mage::getBaseDir('lib').DS.'Ing'.DS.'Services'.DS.'ing-php'.DS.'vendor'.DS.'autoload.php');

        if (Mage::getStoreConfig("payment/ingpsp/apikey")) {
            $this->ingLib = \GingerPayments\Payment\Ginger::createClient(
                Mage::getStoreConfig("payment/ingpsp/apikey"),
                Mage::getStoreConfig("payment/ingpsp/product")
            );

            if (Mage::getStoreConfig("payment/ingpsp/bundle_cacert")) {
                $this->ingLib->useBundledCA();
            }
        }
    }

    /**
     * Prepare an order and get a redirect URL
     *
     * @param int $orderId
     * @param float $amount
     * @param string $currency
     * @param string $description
     * @param array $customer
     * @return bool
     */
    public function createOrder($orderId, $amount, $currency, $description, $customer = array())
    {
        if (!$this->setOrderId($orderId) ||
            !$this->setAmount($amount) ||
            !$this->setDescription($description)
        ) {
            $this->errorMessage = "Error in the given payment data";
            return false;
        }

        $webhookUrl = Mage::getStoreConfig("payment/ingpsp/webhook") ? Mage::getUrl('ingpsp/cashondelivery/webhook') : null;

        $ingOrder = $this->ingLib->createCashOnDeliveryOrder(
            ING_PSP_Helper_Data::getAmountInCents($amount),
            $currency,
            [],
            $description,
            $orderId,
            null,
            null,
            $customer,
            null,
            $webhookUrl
        )->toArray();

        Mage::log($ingOrder);

        if (!is_array($ingOrder) or array_key_exists('error', $ingOrder) or $ingOrder['status'] == 'error') {
            Mage::throwException(
                "Could not start transaction. Contact the owner."
            );
        }

        if (!is_array($ingOrder) || array_key_exists('error', $ingOrder)) {
            // TODO: handle the error
            return false;
        } else {
            $this->orderId = (string) $ingOrder['id'];
            return true;
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getSuccessHtml(Mage_Sales_Model_Order $order)
    {
        if ($order->getPayment()->getMethodInstance() instanceof ING_PSP_Model_Cashondelivery) {
            $paymentBlock = $order->getPayment()->getMethodInstance()->getMailingAddress($order->getStoreId());

            $grandTotal = $order->getGrandTotal();
            $currency = Mage::app()->getLocale()->currency($order->getOrderCurrencyCode())->getSymbol();

            $amountStr = $currency.' '.number_format(round($grandTotal, 2), 2, '.', '');;

            $paymentBlock = str_replace('%AMOUNT%', $amountStr, $paymentBlock);
            $paymentBlock = str_replace('\n', PHP_EOL, $paymentBlock);

            return $paymentBlock;
        }

        return '';
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
