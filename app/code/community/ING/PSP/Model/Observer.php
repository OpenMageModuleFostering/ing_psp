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
 * @version     v1.1.3
 * @copyright   COPYRIGHT (C) 2016 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/
class ING_PSP_Model_Observer
{
    private $ing_modules = [
        'ingpsp_ideal',
        'ingpsp_banktransfer',
        'ingpsp_creditcard',
        'ingpsp_bancontact',
        'ingpsp_cashondelivery'
    ];

    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function convertPayment(Varien_Event_Observer $observer)
    {
        $orderPayment = $observer->getEvent()->getOrderPayment();
        $quotePayment = $observer->getEvent()->getQuotePayment();

        $orderPayment->setIngOrderId($quotePayment->getIngOrderId());
        $orderPayment->setIngBanktransferReference($quotePayment->getIngBanktransferReference());
        $orderPayment->setIngIdealIssuerId($quotePayment->getIngIdealIssuerId());

        return $this;
    }

    /**
     * Hide payment methods that are not allowed by ING.
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function checkPaymentMethodStatus(Varien_Event_Observer $observer)
    {
        $allowedProducts = $this->getActiveINGProducts();
        $config = $observer->getConfig();

        foreach ($this->ing_modules AS $product) {
            $ingModule = $config->getNode('sections/payment/groups/'.$product);
            if (in_array(str_replace('ingpsp_', '', $product), $allowedProducts)) {
                $ingModule->show_in_default = 1;
                $ingModule->show_in_website = 1;
                $ingModule->show_in_store = 1;
                $ingModule->active = 1;
            } else {
                $ingModule->show_in_default = 0;
                $ingModule->show_in_website = 0;
                $ingModule->show_in_store = 0;
                $ingModule->active = 0;
            }
            $ingModule->saveXML();
        }

        return $this;
    }

    /**
     * Hide payment methods that are not allowed by ING.
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function paymentMethodIsActive(Varien_Event_Observer $observer)
    {
        $allowedProducts = $this->getActiveINGProducts();
        $event = $observer->getEvent();
        $method = $event->getMethodInstance();
        $result = $event->getResult();

        if (in_array($method->getCode(), $this->ing_modules)) {
            if (in_array(str_replace('ingpsp_', '', $method->getCode()), $allowedProducts)) {
                $result->isAvailable = true;
            } else {
                $result->isAvailable = false;
            }
        }

        return $this;
    }

    /**
     * Request ING for available payment methods.
     *
     * @return array
     */
    protected function getActiveINGProducts()
    {
        require_once(Mage::getBaseDir('lib').DS.'Ing'.DS.'Services'.DS.'ing-php'.DS.'vendor'.DS.'autoload.php');

        if (Mage::getStoreConfig("payment/ingpsp/apikey")) {
            $ingAPI = \GingerPayments\Payment\Ginger::createClient(
                Mage::getStoreConfig("payment/ingpsp/apikey"),
                Mage::getStoreConfig("payment/ingpsp/product")
            );

            return $ingAPI->getAllowedProducts();
        }

        return [];
    }
}
