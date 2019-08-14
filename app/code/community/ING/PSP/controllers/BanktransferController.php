<?php

/**
 *   ╲          ╱
 * ╭──────────────╮  COPYRIGHT (C) 2016 GINGER PAYMENTS B.V.
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
 * @version     v1.1.2
 * @copyright   COPYRIGHT (C) 2016 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/
class ING_PSP_BanktransferController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var ING_PSP_Helper_Banktransfer
     */
    protected $_banktransfer;

    /**
     * @var ING_PSP_Helper_Data
     */
    protected $_helper;

    /**
     * @var Mage_Core_Helper_Http
     */
    protected $_coreHttp;

    /**
     * @var Varien_Db_Adapter_Pdo_Mysql
     */
    protected $_read;

    /**
     * @var Varien_Db_Adapter_Pdo_Mysql
     */
    protected $_write;

    /**
     * Get iDEAL core
     * Give $_write mage writing resource
     * Give $_read mage reading resource
     */
    public function _construct()
    {
        $this->_banktransfer = Mage::helper('ingpsp/banktransfer');
        $this->_helper = Mage::helper('ingpsp');
        $this->_coreHttp = Mage::helper('core/http');

        $this->_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');

        parent::_construct();
    }

    /**
     * Create the order and sets the redirect url
     *
     * @return void
     */
    public function paymentAction()
    {
        // Load last order
        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($this->_getCheckout()->last_real_order_id);

        try {
            $amount = $order->getGrandTotal();
            $orderId = $order->getIncrementId();
            $description = str_replace('%', $orderId,
                Mage::getStoreConfig("payment/ingpsp_banktransfer/description", $order->getStoreId())
            );
            $currency = $order->getOrderCurrencyCode();
            $customer = $this->_getCustomerData($order);
            $reference = $this->_banktransfer->createOrder($orderId, $amount, $currency, $description, $customer);

            if ($reference) {
                if ($order->getPayment()->getMethodInstance() instanceof ING_PSP_Model_Banktransfer &&
                    $paymentBlock = $order->getPayment()->getMethodInstance()->getMailingAddress($order->getStoreId())
                ) {
                    $details = array();
                    $grandTotal = $order->getGrandTotal();
                    $currency = Mage::app()->getLocale()->currency($order->getOrderCurrencyCode())->getSymbol();
                    $amountStr = $currency.' '.number_format(round($grandTotal, 2), 2, '.', '');

                    $paymentBlock = str_replace('%AMOUNT%', $amountStr, $paymentBlock);
                    $paymentBlock = str_replace('%REFERENCE%', $reference, $paymentBlock);
                    $paymentBlock = str_replace('\n', PHP_EOL, $paymentBlock);

                    $details['mailing_address'] = $paymentBlock;
                    if (!empty($details)) {
                        $order->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalData(serialize($details));
                    }
                }

                if (!$order->getId()) {
                    Mage::log('Geen order voor verwerking gevonden');
                    Mage::throwException('Geen order voor verwerking gevonden');
                }

                // Creates transaction
                /** @var $payment Mage_Sales_Model_Order_Payment */
                $payment = $order->getPayment();

                if (!$payment->getId()) {
                    $payment = Mage::getModel('sales/order_payment')->setId(null);
                }

                $payment->setIsTransactionClosed(false)
                    ->setIngOrderId($this->_banktransfer->getOrderId())
                    ->setIngBanktransferReference($reference);

                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

                // Sets the above transaction
                $order->setPayment($payment);

                $order->setIngOrderId($this->_banktransfer->getOrderId())
                    ->setIngBanktransferReference($reference);
                $order->save();

                $pendingMessage = Mage::helper('ingpsp')->__(ING_PSP_Model_Banktransfer::PAYMENT_FLAG_PENDING);
                if ($order->getData('ing_order_id')) {
                    $pendingMessage .= '. '.'ING Order ID: '.$order->getData('ing_order_id');
                }

                $order->setState(
                    Mage_Sales_Model_Order::STATE_PROCESSING,
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $pendingMessage,
                    false
                );
                $order->save();

                $order->addStatusToHistory($order->getStatus(), 'Reference: '.$reference)->save();

                if (Mage::getStoreConfig("payment/ingpsp_banktransfer/send_order_mail", $order->getStoreId())) {
                    if (!$order->getEmailSent()) {
                        $order->setEmailSent(true);
                        $order->sendNewOrderEmail();
                        $order->save();
                    }
                }

                $this->_redirect('checkout/onepage/success', array('_secure' => true, 'reference' => $reference));
            } else {
                $this->_restoreCart();

                // Redirect to failure page
                $this->_redirect('checkout/onepage/failure', array('_secure' => true));
            }
        } catch (Exception $e) {
            Mage::log($e);
            Mage::throwException(
                "Could not start transaction. Contact the owner.<br />
                Error message: ".$this->_banktransfer->getErrorMessage()
            );
        }
    }

    /**
     * Gets the current checkout session with order information
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return void
     */
    protected function _restoreCart()
    {
        $session = Mage::getSingleton('checkout/session');
        $orderId = $session->getLastRealOrderId();
        if (!empty($orderId)) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        }
        $quoteId = $order->getQuoteId();

        $quote = Mage::getModel('sales/quote')->load($quoteId)->setIsActive(true)->save();

        Mage::getSingleton('checkout/session')->replaceQuote($quote);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    protected function _getCustomerData(Mage_Sales_Model_Order $order)
    {
        $billingAddress = $order->getBillingAddress();

        list($address, $houseNumber) = $this->_helper->parseAddress($billingAddress->getStreetFull());

        return array(
            'merchant_customer_id' => $order->getCustomerId(),
            'email_address' => $order->getCustomerEmail(),
            'first_name' => $order->getCustomerFirstname(),
            'last_name' => $order->getCustomerLastname(),
            'address_type' => 'billing',
            'address' => trim($billingAddress->getCity()).' '.trim($address),
            'postal_code' => $billingAddress->getPostcode(),
            'housenumber' => $houseNumber,
            'country' => $billingAddress->getCountryId(),
            'phone_numbers' => [$billingAddress->getTelephone()],
            'user_agent' => $this->_coreHttp->getHttpUserAgent(),
            'referrer' => $this->_coreHttp->getHttpReferer(),
            'ip_address' => $this->_coreHttp->getRemoteAddr(),
            'forwarded_ip' => $this->getRequest()->getServer('HTTP_X_FORWARDED_FOR'),
            'gender' => $order->getCustomerGender() ? ('1' ? 'male' : ('2' ? 'female' : null)) : null,
            // "male", "female", "other", null
            'birth_date' => $order->getCustomerDob(),
            // Date (ISO 8601 / RFC 3339)
            'locale' => Mage::app()->getLocale()->getLocaleCode(),
            // "^[a-zA-Z]{2}(_[a-zA-Z]{2})?$"
        );
    }
}
