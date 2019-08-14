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
class ING_PSP_IdealController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var ING_PSP_Helper_Ideal
     */
    protected $_ideal;

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
        $this->_ideal = Mage::helper('ingpsp/ideal');
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
     * Creats the order and sets the redirect url
     *
     */
    public function paymentAction()
    {
        // Load last order
        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($this->_getCheckout()->last_real_order_id);

        try {
            $issuerId = Mage::app()->getRequest()->getParam('issuer_id');
            $amount = $order->getGrandTotal();
            $orderId = $order->getIncrementId();
            $description = str_replace('%', $orderId,
                Mage::getStoreConfig("payment/ingpsp_ideal/description", $order->getStoreId())
            );
            $returnUrl = Mage::getUrl('ingpsp/ideal/return');
            $customer = $this->_getCustomerData($order);
            $currency = $order->getOrderCurrencyCode();

            if ($this->_ideal->createOrder($orderId, $issuerId, $amount, $currency, $description, $returnUrl, $customer)) {

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

                //->setMethod('iDEAL')
                $payment->setIsTransactionClosed(false)
                    ->setIngOrderId($this->_ideal->getOrderId())
                    ->setIngIdealIssuerId($issuerId);

                // Sets the above transaction
                $order->setPayment($payment);

                $order->setIngOrderId($this->_ideal->getOrderId())
                    ->setIngIdealIssuerId($issuerId);
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

                Mage::log("Issuer url: ".$this->_ideal->getPaymentUrl());
                $this->_redirectUrl($this->_ideal->getPaymentUrl());
            }
        } catch (Exception $e) {
            Mage::log($e);
            Mage::throwException(
                "Could not start transaction. Contact the owner.<br />
                Error message: ".$this->_ideal->getErrorMessage()
            );
        }
    }

    /**
     * This action is getting called by Payment service to report the payment status
     */
    public function webhookAction()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            die("Invalid JSON");
        }

        if (in_array($input['event'], array("status_changed"))) {

            $ingOrderId = $input['order_id'];

            $ingOrder = $this->_ideal->getOrderDetails($ingOrderId);

            $orderId = $ingOrder['merchant_order_id'];
            $orderStatus = $ingOrder['status'];

            /** @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

            try {
                if ($order->getData('status') == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    // Creates transaction
                    $payment = Mage::getModel('sales/order_payment')
                        ->setMethod('iDEAL')
                        ->setIsTransactionClosed(true);

                    // Sets the above transaction
                    $order->setPayment($payment);
                    $orderAmountCents = (int) (100 * round($order->getGrandTotal(), 2, PHP_ROUND_HALF_UP));

                    switch ($orderStatus) {
                        case "completed":
                            // store the amount paid in the order
                            $amountPaidCents = (int) $ingOrder['amount'];
                            // $this->_setAmountPaid($order, $amount_paid_cents / 100);

                            if ($amountPaidCents == $orderAmountCents) {
                                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);

                                $order->setState(
                                    Mage_Sales_Model_Order::STATE_PROCESSING,
                                    Mage_Sales_Model_Order::STATE_PROCESSING,
                                    Mage::helper('ingpsp')->__(ING_PSP_Model_Ideal::PAYMENT_FLAG_COMPLETED),
                                    true
                                );
                                $order->save();

                                $order->setState(
                                    Mage_Sales_Model_Order::STATE_PROCESSING,
                                    Mage_Sales_Model_Order::STATE_PROCESSING,
                                    Mage::helper('sales')->__('Registered notification about captured amount of %s.',
                                        $this->_formatPrice($order, $order->getGrandTotal())),
                                    false
                                );
                                $order->save();

                                // Sends email to customer.
                                if (Mage::getStoreConfig("payment/ingpsp_ideal/send_invoice_mail",
                                    $order->getStoreId())
                                ) {
                                    $order->sendNewOrderEmail()->setEmailSent(true)->save();
                                }

                                // Create invoice.
                                if (Mage::getStoreConfig("payment/ingpsp_ideal/generate_invoice_upon_completion",
                                    $order->getStoreId())
                                ) {
                                    $this->_savePaidInvoice($order);
                                }
                            } else {
                                $order->setState(
                                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                                    Mage_Sales_Model_Order::STATUS_FRAUD,
                                    Mage::helper('ingpsp')->__(ING_PSP_Model_Ideal::PAYMENT_FLAG_FRAUD),
                                    false
                                );
                                $order->save();
                            }
                            break;
                        case "cancelled":
                        case "expired":
                            $order->cancel();
                            $order->setState(
                                Mage_Sales_Model_Order::STATE_CANCELED,
                                Mage_Sales_Model_Order::STATE_CANCELED,
                                Mage::helper('ingpsp')->__(ING_PSP_Model_Ideal::PAYMENT_FLAG_CANCELLED),
                                true
                            );
                            $order->save();
                            break;
                        case "error":
                        case "pending":
                        case "see-transactions":
                        case "new":
                        default:
                            // just wait
                            break;
                    }
                }
            } catch (Exception $e) {
                Mage::log($e);
                Mage::throwException($e);
            }
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
                $ingOrderDetails = $this->_ideal->getOrderDetails($orderId);

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

        if (Mage::getStoreConfig("payment/ingpsp_ideal/send_invoice_mail", $order->getStoreId())) {
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
