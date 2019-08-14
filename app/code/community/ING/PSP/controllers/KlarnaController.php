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
class ING_PSP_KlarnaController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var ING_PSP_Helper_Klarna
     */
    protected $_klarna;

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
        $this->_klarna = Mage::helper('ingpsp/klarna');
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
        $order = Mage::getModel('sales/order')->loadByIncrementId($this->_getCheckout()->last_real_order_id);

        try {
            $amount = $order->getGrandTotal();
            $orderId = $order->getIncrementId();
            $description = str_replace('%', $orderId,
                Mage::getStoreConfig("payment/ingpsp_klarna/description", $order->getStoreId())
            );
            $currency = $order->getOrderCurrencyCode();
            $customer = $this->_getCustomerData($order);
            $orderLines = $this->getOrderLines($order);

            if ($this->_klarna->createOrder($orderId, $amount, $currency, $description, $customer, $orderLines)) {
                if (!$order->getId()) {
                    Mage::throwException('No order found!');
                }

                $payment = $order->getPayment();

                if (!$payment->getId()) {
                    $payment = Mage::getModel('sales/order_payment')->setId(null);
                }

                $payment->setIsTransactionClosed(false)->setIngOrderId($this->_klarna->getOrderId());
                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
                $order->setPayment($payment);
                $order->setIngOrderId($this->_klarna->getOrderId());
                $order->save();

                $pendingMessage = Mage::helper('ingpsp')->__(ING_PSP_Model_Klarna::PAYMENT_FLAG_PENDING);
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

                if (Mage::getStoreConfig("payment/ingpsp_klarna/send_order_mail", $order->getStoreId())) {
                    if (!$order->getEmailSent()) {
                        $order->setEmailSent(true);
                        $order->sendNewOrderEmail();
                        $order->save();
                    }
                }
                $this->_redirect('checkout/onepage/success', array('_secure' => true));
            } else {
                $this->_restoreCart();
                if ($this->_klarna->getErrorMessage()) {
                    Mage::getSingleton('core/session')->addError($this->_klarna->getErrorMessage());
                    $this->_redirect('checkout/onepage', array('_secure' => true));
                } else {
                    Mage::getSingleton('core/session')->addError(
                        $this->__('
                            Unfortunately, we can not currently accept your purchase with Klarna. 
                            Please choose another payment option to complete your order. 
                            We apologize for the inconvenience.'
                        )
                    );
                    $this->_redirect('checkout/onepage/failure', array('_secure' => true));
                }
            }
        } catch (Exception $e) {
            Mage::log($e);
            Mage::throwException(
                "Could not start transaction. Contact the owner.<br />
                Error message: ".$this->_klarna->getErrorMessage().$e->getMessage()
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
            'address' => trim($billingAddress->getStreetFull())
                .' '.trim($billingAddress->getPostcode())
                .' '.trim($billingAddress->getCity()),
            'postal_code' => $billingAddress->getPostcode(),
            'housenumber' => $houseNumber,
            'country' => $billingAddress->getCountryId(),
            'phone_numbers' => [$billingAddress->getTelephone()],
            'user_agent' => $this->_coreHttp->getHttpUserAgent(),
            'referrer' => $this->_coreHttp->getHttpReferer(),
            'ip_address' => $this->_coreHttp->getRemoteAddr(),
            'forwarded_ip' => $this->getRequest()->getServer('HTTP_X_FORWARDED_FOR'),
            'gender' => $order->getCustomerGender() ? ('1' ? 'male' : ('2' ? 'female' : null)) : null,
            'birthdate' => $order->getCustomerDob() ? Mage::getModel('core/date')->date('Y-m-d',
                strtotime($order->getCustomerDob())) : null,
            'locale' => Mage::app()->getLocale()->getLocaleCode()
        );
    }

    /**
     * @param $order
     * @return array
     */
    protected function getOrderLines($order)
    {
        $orderLines = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $orderLines[] = [
                'url' => $item->getProduct()->getProductUrl(),
                'name' => $item->getName(),
                'type' => \GingerPayments\Payment\Order\OrderLine\Type::PHYSICAL,
                'amount' => ING_PSP_Helper_Data::getAmountInCents(
                    Mage::helper('tax')->getPrice($item->getProduct(), $item->getProduct()->getFinalPrice(), true)
                ),
                'currency' => \GingerPayments\Payment\Currency::EUR,
                'quantity' => (int) $item->getQtyOrdered() ? $item->getQtyOrdered() : 1,
                'image_url' => Mage::getModel('catalog/product_media_config')
                    ->getMediaUrl($item->getProduct()->getThumbnail()),
                'vat_percentage' => ING_PSP_Helper_Data::getAmountInCents($item->getTaxPercent()),
                'merchant_order_line_id' => $item->getId()
            ];
        }

        if ($order->getShippingAmount() > 0) {
            $orderLines[] = $this->getShippingOrderLine($order);
        }

        return $orderLines;
    }

    /**
     * @param $order
     * @return array
     */
    protected function getShippingOrderLine($order)
    {
        return [
            'name' => $order->getShippingDescription(),
            'type' => \GingerPayments\Payment\Order\OrderLine\Type::SHIPPING_FEE,
            'amount' => ING_PSP_Helper_Data::getAmountInCents($order->getShippingInclTax()),
            'currency' => \GingerPayments\Payment\Currency::EUR,
            'vat_percentage' => ING_PSP_Helper_Data::getAmountInCents(
                (100 * $order->getShippingTaxAmount() / $order->getShippingInclTax())
            ),
            'quantity' => 1,
            'merchant_order_line_id' => ($order->getTotalItemCount() + 1)
        ];
    }
}
