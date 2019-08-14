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
class ING_PSP_SofortController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var ING_PSP_Helper_Sofort
     */
    protected $_sofort;

    /**
     * @var Varien_Db_Adapter_Pdo_Mysql
     */
    protected $_read;

    /**
     * @var Varien_Db_Adapter_Pdo_Mysql
     */
    protected $_write;

    /**
     * Get CreditCard core
     * Give $_write mage writing resource
     * Give $_read mage reading resource
     */
    public function _construct()
    {
        $this->_sofort = Mage::helper('ingpsp/sofort');
        $this->_helper = Mage::helper('ingpsp');
        $this->_coreHttp = Mage::helper('core/http');

        $this->_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');

        parent::_construct();
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
     * Creates the order and sets the redirect url
     */
    public function paymentAction()
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($this->_getCheckout()->last_real_order_id);

        try {
            $amount = $order->getGrandTotal();
            $orderId = $order->getIncrementId();
            $description = str_replace('%', $orderId,
                Mage::getStoreConfig("payment/ingpsp_sofort/description", $order->getStoreId())
            );
            $currency = $order->getOrderCurrencyCode();
            $customer = $this->_getCustomerData($order);
            $returnUrl = Mage::getUrl('ingpsp/sofort/return');

            if ($this->_sofort->createOrder($orderId, $amount, $currency, $description, $returnUrl, $customer)) {
                if (!$order->getId()) {
                    Mage::log('Geen order voor verwerking gevonden');
                    Mage::throwException('Geen order voor verwerking gevonden');
                }

                $payment = $order->getPayment();

                if (!$payment->getId()) {
                    $payment = Mage::getModel('sales/order_payment')->setId(null);
                }

                $payment->setIsTransactionClosed(false)
                    ->setIngOrderId($this->_sofort->getOrderId())
                    ->setIngIdealIssuerId(null);

                $order->setPayment($payment);
                $order->setIngOrderId($this->_sofort->getOrderId())
                    ->setIngIdealIssuerId(null);
                $order->save();

                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
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

                Mage::log("Issuer url redirect: ".$this->_sofort->getPaymentUrl());
                $this->_redirectUrl($this->_sofort->getPaymentUrl());
            }
        } catch (Exception $e) {
            Mage::log($e);
            Mage::throwException(
                "Could not start transaction. Contact the owner.<br />
                Error message: ".$this->_sofort->getErrorMessage()
            );
        }
    }

    /**
     * Customer returning with an order_id
     * Depending on the order state redirected to the corresponding page
     */
    public function returnAction()
    {
        $orderId = Mage::app()->getRequest()->getParam('order_id');

        try {
            if (!empty($orderId)) {
                $ingOrderDetails = $this->_sofort->getOrderDetails($orderId);

                $paymentStatus = isset($ingOrderDetails['status']) ? $ingOrderDetails['status'] : null;

                if ($paymentStatus == "completed") {
                    // Redirect to success page
                    $this->_redirect('checkout/onepage/success', array('_secure' => true));
                } else {
                    $this->_restoreCart();

                    // Redirect to failure page
                    $this->_redirect('checkout/onepage/failure', array('_secure' => true));
                }
            }
        } catch (Exception $e) {
            $this->_restoreCart();

            Mage::log($e);
            $this->_redirectUrl(Mage::getBaseUrl());
        }
    }

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
     * @param float $orderAmount
     */
    protected function _setAmountPaid(Mage_Sales_Model_Order $order, $orderAmount)
    {
        // set the amounts paid
        $currentBase = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentStore = Mage::app()->getStore()->getCurrentCurrencyCode();

        $amountBase = Mage::helper('directory')->currencyConvert($orderAmount, 'EUR', $currentBase);
        $amountStore = Mage::helper('directory')->currencyConvert($orderAmount, 'EUR', $currentStore);

        $order->setBaseTotalPaid($amountBase);
        $order->setTotalPaid($amountStore);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param null|string $transactionId
     * @return bool
     */
    protected function _savePaidInvoice(Mage_Sales_Model_Order $order, $transactionId = null)
    {
        $invoice = $order->prepareInvoice()
            ->register()
            ->setTransactionId($transactionId)
            ->pay();

        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();

        if (Mage::getStoreConfig("payment/ingpsp_sofort/send_invoice_mail", $order->getStoreId())) {
            $invoice->sendEmail();
        }

        return true;
    }

    /**
     * Format price with currency sign
     *
     * @param Mage_Sales_Model_Order $order
     * @param float $amount
     * @return string
     */
    protected function _formatPrice($order, $amount)
    {
        return $order->getBaseCurrency()->formatTxt($amount);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    protected function _getCustomerData(Mage_Sales_Model_Order $order)
    {
        $billingAddress = $order->getBillingAddress();

        list($address, $houseNumber) = $this->_helper->parseAddress($billingAddress->getStreetFull());

        return GingerPayments\Payment\Common\ArrayFunctions::withoutNullValues([
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
            'birth_date' => $order->getCustomerDob(),
            'locale' => Mage::app()->getLocale()->getLocaleCode(),
        ]);
    }
}
